<?php
if (!defined('ABSPATH')) exit;

function ci_menu() {
    add_menu_page('Coupon Importer', 'Coupon Importer', 'manage_options', 'coupon-importer', 'ci_page_import', 'dashicons-upload', 26);
    add_submenu_page('coupon-importer', 'Importar',  'Importar',  'manage_options', 'coupon-importer',          'ci_page_import');
    add_submenu_page('coupon-importer', 'Lojas',     'Lojas',     'manage_options', 'coupon-importer-shops',    'ci_page_shops');
    add_submenu_page('coupon-importer', 'Redes',     'Redes',     'manage_options', 'coupon-importer-networks', 'ci_page_networks');
}
add_action('admin_menu', 'ci_menu');

function ci_page_import()   { require CI_PATH . 'admin/pages/sync.php'; }
function ci_page_shops()    { require CI_PATH . 'admin/pages/shops.php'; }
function ci_page_networks() { require CI_PATH . 'admin/pages/networks.php'; }
