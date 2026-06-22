<?php
if (!defined('ABSPATH')) exit;

add_action('wp_ajax_lsci_preview', function() {
    check_ajax_referer('lsci_nonce', 'nonce');
    $sources = lsci_get_sources();
    if (empty($sources)) { wp_send_json_error(['error' => 'Nenhuma fonte configurada']); return; }
    wp_send_json_success(lsci_process_batch($sources, sanitize_text_field($_POST['mode'] ?? 'new_only'), true));
});

add_action('wp_ajax_lsci_sync', function() {
    check_ajax_referer('lsci_nonce', 'nonce');
    set_time_limit(300);
    $sources = lsci_get_sources();
    if (empty($sources)) { wp_send_json_error(['error' => 'Nenhuma fonte configurada']); return; }
    $result = lsci_process_batch($sources, sanitize_text_field($_POST['mode'] ?? 'new_only'), false);
    isset($result['error']) ? wp_send_json_error($result) : wp_send_json_success($result);
});

/*
|--------------------------------------------------------------------------
| Monta array de fontes a partir do POST + opções salvas
|--------------------------------------------------------------------------
*/
function lsci_get_sources() {
    $sources = [];

    // CSV colado/uploadado via POST
    $csv = isset($_POST['csv_content']) ? stripslashes($_POST['csv_content']) : '';
    if ($csv) $sources[] = ['type' => 'csv', 'value' => $csv];

    // URLs de planilha salvas
    for ($i = 1; $i <= 5; $i++) {
        $url = get_option("ci_sheet_url_{$i}");
        if ($url) $sources[] = ['type' => 'url', 'value' => $url];
    }

    return $sources;
}
