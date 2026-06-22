<?php
if (!defined('ABSPATH')) exit;

function ci_default_networks() {
    return [
        ['id' => 1,  'name' => 'AWIN',     'publisher_id' => 'YOUR_AWIN_PUBLISHER_ID',     'template' => 'https://www.awin1.com/cread.php?awinmid={MID}&awinaffid={AFFID}&platform=dl&ued={URL}', 'link_direct' => 0, 'active' => 1, 'notes' => ''],
        ['id' => 2,  'name' => 'Rakuten',  'publisher_id' => 'YOUR_RAKUTEN_PUBLISHER_ID',  'template' => 'https://click.linksynergy.com/deeplink?id={AFFID}&mid={MID}&murl={URL}',                  'link_direct' => 0, 'active' => 1, 'notes' => ''],
        ['id' => 3,  'name' => 'CJ',       'publisher_id' => '',             'template' => '{AFFID}url={URL}',                                                                          'link_direct' => 0, 'active' => 1, 'notes' => 'Template completo no campo AFFID da loja. Ex: https://www.tkqlhce.com/click-101362943-17014161?'],
        ['id' => 4,  'name' => 'Impact',   'publisher_id' => '',             'template' => '{AFFID}{URL}',                                                                              'link_direct' => 0, 'active' => 1, 'notes' => ''],
        ['id' => 5,  'name' => 'Admitad',  'publisher_id' => '',             'template' => '{AFFID}{URL}',                                                                              'link_direct' => 0, 'active' => 1, 'notes' => ''],
        ['id' => 6,  'name' => 'CityAds',  'publisher_id' => '',             'template' => '{AFFID}{URL}',                                                                              'link_direct' => 0, 'active' => 1, 'notes' => ''],
        ['id' => 7,  'name' => 'Lomadee',  'publisher_id' => '',             'template' => '',                                                                                          'link_direct' => 1, 'active' => 1, 'notes' => 'Link vem pronto no CSV'],
        ['id' => 8,  'name' => 'Afilio',   'publisher_id' => '',             'template' => '',                                                                                          'link_direct' => 1, 'active' => 1, 'notes' => 'Link vem pronto no CSV'],
        ['id' => 9,  'name' => 'Outra',    'publisher_id' => '',             'template' => '',                                                                                          'link_direct' => 1, 'active' => 1, 'notes' => 'Link vem pronto no CSV'],
        ['id' => 10, 'name' => 'Amazon',   'publisher_id' => 'yourtag-20',   'template' => '',                                                                                          'link_direct' => 0, 'active' => 1, 'notes' => 'Adiciona ?tag= ao final do link'],
    ];
}

function ci_get_network_by_name($name) {
    $networks = get_option('ci_networks', []);
    $normalized = ci_normalize($name);
    foreach ($networks as $net) {
        if (!$net['active']) continue;
        if (ci_normalize($net['name']) === $normalized) return $net;
    }
    return null;
}

function ci_get_network_by_id($id) {
    $networks = get_option('ci_networks', []);
    foreach ($networks as $net) {
        if ($net['id'] == $id) return $net;
    }
    return null;
}

function ci_build_link($shop, $raw_url) {
    $network = ci_get_network_by_id($shop['network_id'] ?? 0);

    if (!$network || $network['link_direct']) {
        return $raw_url;
    }

    // Template próprio da loja (ex: Admitad por loja)
    if (!empty($shop['link_template'])) {
        return str_replace('{URL}', urlencode($raw_url), $shop['link_template']);
    }

    // Amazon: appenda ?tag=
    if (ci_normalize($network['name']) === 'amazon') {
        $tag = !empty($shop['publisher_id']) ? $shop['publisher_id'] : $network['publisher_id'];
        if (empty($tag)) return $raw_url;
        $separator = (strpos($raw_url, '?') !== false) ? '&' : '?';
        return rtrim($raw_url, '&?') . $separator . 'tag=' . urlencode($tag);
    }

    // Template genérico da rede
    $affid = !empty($shop['publisher_id']) ? $shop['publisher_id'] : $network['publisher_id'];
    $mid   = $shop['mid'] ?? '';

    $link = $network['template'];
    $link = str_replace('{MID}',   $mid,                $link);
    $link = str_replace('{AFFID}', $affid,              $link);
    $link = str_replace('{URL}',   urlencode($raw_url), $link);

    return $link;
}

function ci_get_shop_by_name($advertiser) {
    $shops      = get_option('ci_shops', []);
    $normalized = ci_normalize($advertiser);

    foreach ($shops as $shop) {
        if (empty($shop['active'])) continue;

        $names = [ci_normalize($shop['name'])];
        if (!empty($shop['aliases']) && is_array($shop['aliases'])) {
            foreach ($shop['aliases'] as $alias) $names[] = ci_normalize(trim($alias));
        }

        if (in_array($normalized, $names)) return $shop;
    }

    return false;
}

// ORIGINAL — não alterado
function ci_coupon_exists($code, $shop_name) {
    global $wpdb;

    $results = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta}
             WHERE meta_key='coupon_details_coupon-code-text'
             AND meta_value=%s",
            $code
        )
    );

    if (empty($results)) return false;

    $term = get_term_by('name', $shop_name, 'wpcd_coupon_vendor');
    if (!$term) return false;

    foreach ($results as $post_id) {
        $terms = wp_get_object_terms($post_id, 'wpcd_coupon_vendor', ['fields' => 'ids']);
        if (in_array($term->term_id, $terms)) return $post_id;
    }

    return false;
}

function ci_assign_vendor($post_id, $shop_name) {
    $term = get_term_by('name', $shop_name, 'wpcd_coupon_vendor');
    if ($term) {
        wp_set_object_terms($post_id, $term->term_id, 'wpcd_coupon_vendor');
    } else {
        $new = wp_insert_term($shop_name, 'wpcd_coupon_vendor');
        if (!is_wp_error($new)) wp_set_object_terms($post_id, $new['term_id'], 'wpcd_coupon_vendor');
    }
}

function ci_count_coupons($shop_name) {
    $term = get_term_by('name', $shop_name, 'wpcd_coupon_vendor');
    if (!$term) return 0;

    $q = new WP_Query([
        'post_type'      => 'wpcd_coupons',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'tax_query'      => [[
            'taxonomy' => 'wpcd_coupon_vendor',
            'field'    => 'term_id',
            'terms'    => $term->term_id,
        ]],
    ]);

    return $q->found_posts;
}

function ci_normalize($text) {
    $text = remove_accents($text);
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9\s]/', '', $text);
    $text = preg_replace('/\s+/', ' ', $text);
    return trim($text);
}

function ci_normalize_type($type) {
    $t = strtolower(trim($type));
    $map = ['coupon' => 'Coupon', 'offer' => 'Deal', 'deal' => 'Deal'];
    return $map[$t] ?? 'Coupon';
}

add_action('save_post_wpcd_coupons', function($post_id, $post, $update) {
    if (!$update) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (defined('LSCI_ACTIVE') && LSCI_ACTIVE) return;
    if (is_admin() && current_user_can('edit_post', $post_id)) {
        update_post_meta($post_id, '_ci_manually_edited', '1');
    }
}, 10, 3);
