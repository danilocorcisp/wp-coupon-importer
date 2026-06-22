<?php
if (!defined('ABSPATH')) exit;

/*
|--------------------------------------------------------------------------
| ENTRADA PRINCIPAL — aceita CSV como string ou baixa de URLs
|--------------------------------------------------------------------------
| $sources = [
|   ['type' => 'url',    'value' => 'https://...'],
|   ['type' => 'csv',    'value' => 'raw csv content'],
| ]
*/
function lsci_process_batch($sources, $mode = 'new_only', $dry_run = false) {

    if (!defined('LSCI_ACTIVE')) define('LSCI_ACTIVE', true);

    $all_to_create = [];
    $all_to_update = [];
    $total_ignored = 0;
    $all_debug     = [];

    foreach ($sources as $si => $source) {

        $label = ($source['type'] === 'url') ? "Planilha " . ($si + 1) : "CSV Upload";
        $all_debug[] = "📊 ===== {$label} =====";

        if ($source['type'] === 'url') {
            // Normaliza URL do Google Sheets para exportação CSV
            $url = $source['value'];
            if (strpos($url, 'docs.google.com/spreadsheets') !== false) {
                // Extrai o ID da planilha e monta URL de exportação
                if (preg_match('#/spreadsheets/d/([a-zA-Z0-9_-]+)#', $url, $m)) {
                    $sheet_id = $m[1];
                    // Verifica se tem gid especificado na URL original
                    $gid = '0';
                    if (preg_match('#[?&]gid=(\d+)#', $url, $gm)) $gid = $gm[1];
                    $url = "https://docs.google.com/spreadsheets/d/{$sheet_id}/export?format=csv&gid={$gid}";
                    $all_debug[] = "🔗 URL convertida para CSV: .../{$sheet_id}/export?format=csv&gid={$gid}";
                }
            }
            $response = wp_remote_get($url, ['timeout' => 30]);
            if (is_wp_error($response)) {
                $all_debug[] = "❌ Erro ao baixar: " . $response->get_error_message();
                continue;
            }
            $body = wp_remote_retrieve_body($response);
        } else {
            $body = $source['value'];
        }

        $result = lsci_parse_feed($body, $mode, $all_debug);
        $all_to_create = array_merge($all_to_create, $result['to_create']);
        $all_to_update = array_merge($all_to_update, $result['to_update']);
        $total_ignored += $result['ignored'];
        $all_debug      = $result['debug']; // already appended inside
    }

    if ($dry_run) {
        return [
            'dry_run'        => true,
            'to_create'      => count($all_to_create),
            'to_update'      => count($all_to_update),
            'ignored'        => $total_ignored,
            'debug'          => $all_debug,
            'preview_create' => $all_to_create,
            'preview_update' => $all_to_update,
        ];
    }

    $processed = 0;

    // Atualiza existentes
    foreach ($all_to_update as $data) {
        $upd = ['ID' => $data['id']];
        if (empty($data['skip_title'])) $upd['post_title'] = $data['description'];
        wp_update_post($upd);

        if (!empty($data['end_date']) && strtotime($data['end_date']) > 0) {
            update_post_meta($data['id'], 'coupon_details_expire-date', $data['end_date']);
        }
        update_post_meta($data['id'], 'coupon_details_link',             $data['link']);
        update_post_meta($data['id'], 'coupon_details_coupon-type',      $data['coupon_type']);
        if (!empty($data['code'])) update_post_meta($data['id'], 'coupon_details_coupon-code-text', $data['code']);

        $term = get_term_by('name', $data['shop']['name'], 'wpcd_coupon_vendor');
        if ($term) wp_set_object_terms($data['id'], $term->term_id, 'wpcd_coupon_vendor');
    }

    // Cria novos
    foreach ($all_to_create as $data) {

        // Garante termo antes do wp_insert_post (save_post dispara dentro do insert)
        $term = get_term_by('name', $data['shop']['name'], 'wpcd_coupon_vendor');
        if (!$term) {
            $ins     = wp_insert_term($data['shop']['name'], 'wpcd_coupon_vendor');
            $term_id = !is_wp_error($ins) ? $ins['term_id'] : 0;
        } else {
            $term_id = $term->term_id;
        }

        $post_id = wp_insert_post([
            'post_type'    => 'wpcd_coupons',
            'post_status'  => 'publish',
            'post_title'   => $data['description'],
            'post_content' => '',
            'tax_input'    => $term_id ? ['wpcd_coupon_vendor' => [$term_id]] : [],
        ]);

        if ($post_id) {
            if (!empty($data['code'])) update_post_meta($post_id, 'coupon_details_coupon-code-text', $data['code']);

            if (!empty($data['end_date']) && strtotime($data['end_date']) > 0) {
                update_post_meta($post_id, 'coupon_details_expire-date', $data['end_date']);
            }
            update_post_meta($post_id, 'coupon_details_link',        $data['link']);
            update_post_meta($post_id, 'coupon_details_coupon-type', $data['coupon_type']);

            // Fallback vendor após insert
            if ($term_id) wp_set_object_terms($post_id, $term_id, 'wpcd_coupon_vendor');

            $processed++;

            // Hook para outros plugins (ex: shortcode inserter)
            do_action('ci_coupon_imported', $post_id, $data['shop']);
            do_action('awin_coupon_imported', $post_id, $data['shop']); // compatibilidade
        }
    }

    return [
        'processed'       => $processed,
        'updated'         => count($all_to_update),
        'ignored'         => $total_ignored,
        'debug'           => $all_debug,
        'total_processed' => $processed + count($all_to_update),
    ];
}

/*
|--------------------------------------------------------------------------
| PROCESSA UM CSV (retorna arrays to_create / to_update)
|--------------------------------------------------------------------------
*/
function lsci_parse_feed($body, $mode, &$debug) {

    $lines = explode("\n", $body);
    $to_create = [];
    $to_update = [];
    $ignored   = 0;

    // Detecta delimitador
    $delimiter = ',';
    if (!empty($lines[0]) && substr_count($lines[0], ';') > substr_count($lines[0], ',')) {
        $delimiter = ';';
    }
    $debug[] = "🔍 Delimitador: '" . ($delimiter === ',' ? 'vírgula' : 'ponto-e-vírgula') . "'";

    // Preview das primeiras linhas
    $debug[] = "📋 Primeiras 3 linhas:";
    for ($i = 0; $i < min(3, count($lines)); $i++) {
        $row = str_getcsv($lines[$i], $delimiter);
        $debug[] = "  Linha {$i}: " . count($row) . " colunas — " . json_encode(array_slice($row, 0, 6));
    }

    // Anunciantes encontrados
    $advertisers = [];
    for ($i = 1; $i < min(20, count($lines)); $i++) {
        $row = str_getcsv($lines[$i], $delimiter);
        if (count($row) >= 8 && !empty($row[2])) {
            $adv = trim($row[2]);
            if (!in_array($adv, $advertisers)) $advertisers[] = $adv;
        }
    }
    if ($advertisers) $debug[] = "🔍 Anunciantes: " . implode(', ', $advertisers);

    $batch_size = get_option('ci_batch_size', 200);
    $count      = 0;

    foreach ($lines as $index => $line) {

        if ($index === 0) continue;
        if ($count >= $batch_size) break;

        $line = trim($line);
        if (empty($line)) continue;

        $row = str_getcsv($line, $delimiter);
        if (count($row) < 8) continue;

        // Mapeamento de colunas
        // 0:Data 1:Setor 2:Anunciante 3:Descrição 4:Código 5:Data-De 6:Data-Para 7:Link [8:Rede] [9:Tipo]
        $advertiser  = trim($row[2]);
        $description = trim($row[3]);
        $code        = trim($row[4]);
        $end_date    = trim($row[6]);
        $raw_link    = trim($row[7], " \t\n\r\0\x0B_");
        $coupon_type = ci_normalize_type($row[9] ?? 'Coupon');

        if (!$code && !$raw_link) { $ignored++; continue; }

        // Processa data
        $date_formatted = '';
        $timestamp      = false;

        if ($end_date) {
            $formats = ['d/m/Y', 'Y-m-d', 'm/d/Y', 'd-m-Y', 'Y/m/d', 'd/m/y'];
            foreach ($formats as $fmt) {
                $dt = DateTime::createFromFormat($fmt, $end_date);
                if ($dt && $dt->format($fmt) === $end_date) {
                    $timestamp      = $dt->getTimestamp();
                    $date_formatted = $dt->format('m/d/Y');
                    break;
                }
            }
            if (!$timestamp) {
                $ts = strtotime($end_date);
                if ($ts > 0) { $timestamp = $ts; $date_formatted = date('m/d/Y', $ts); }
            }
            if (!$timestamp) {
                $ignored++;
                $debug[] = "Linha {$index} ignorada: data inválida '$end_date'";
                continue;
            }
            if ($timestamp < time()) {
                $ignored++;
                $debug[] = "Linha {$index} ignorada: vencido ($end_date)";
                continue;
            }
        }

        // Busca loja
        $shop = ci_get_shop_by_name($advertiser);
        if (!$shop) {
            $ignored++;
            $debug[] = "Linha {$index} ignorada: loja '$advertiser' não encontrada";
            continue;
        }

        // Monta link de afiliado
        $final_link = ci_build_link($shop, $raw_link);

        // Checa duplicado
        $existing_id = $code ? ci_coupon_exists($code, $shop['name']) : false;

        if ($mode === 'new_only' && $existing_id) {
            $ignored++;
            $debug[] = "Linha {$index} ignorada: '$code' já existe em {$shop['name']}";
            continue;
        }

        $coupon_data = [
            'code'        => $code,
            'description' => $description,
            'end_date'    => $date_formatted ?: $end_date,
            'link'        => $final_link,
            'shop'        => $shop,
            'advertiser'  => $advertiser,
            'coupon_type' => $coupon_type,
        ];

        if ($existing_id) {
            $manually_edited = get_post_meta($existing_id, '_ci_manually_edited', true);
            if ($manually_edited) $coupon_data['skip_title'] = true;
            $to_update[] = array_merge($coupon_data, ['id' => $existing_id]);
        } else {
            $to_create[] = $coupon_data;
        }

        $count++;
    }

    $debug[] = "✅ Planilha: " . count($to_create) . " novos, " . count($to_update) . " a atualizar, {$ignored} ignorados";

    return compact('to_create', 'to_update', 'ignored', 'debug');
}
