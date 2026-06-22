<?php
/*
Plugin Name: Coupon Importer
Description: Importador unificado de cupons para WP Coupons and Deals. Suporta múltiplas redes de afiliado via URL de planilha ou upload de CSV.
Version: 1.3.2
Author: Siteguy.dev
*/

if (!defined('ABSPATH')) exit;

define('CI_PATH', plugin_dir_path(__FILE__));
define('CI_URL',  plugin_dir_url(__FILE__));

require_once CI_PATH . 'includes/helpers.php';
require_once CI_PATH . 'includes/processor.php';
require_once CI_PATH . 'includes/ajax.php';
require_once CI_PATH . 'admin/menu.php';

register_activation_hook(__FILE__, function() {

    // Redes padrão primeiro (necessário para mapear network_id)
    if (!get_option('ci_networks')) {
        update_option('ci_networks', ci_default_networks());
    }

    if (!get_option('ci_shops')) {

        $ci_shops  = [];
        $used_ids  = [];

        // Migra lojas do AWIN Crawler (network_id = 1 = AWIN)
        $awin_shops = get_option('awin_crawler_shops', []);
        foreach ($awin_shops as $s) {
            $id = intval($s['id']);
            $used_ids[] = $id;
            $ci_shops[] = [
                'id'           => $id,
                'name'         => $s['name'],
                'network_id'   => 1, // AWIN
                'mid'          => $s['mid'] ?? '',
                'publisher_id' => $s['affid'] ?? '',
                'active'       => $s['active'] ?? 1,
                'aliases'      => $s['aliases'] ?? [],
            ];
        }

        // Migra lojas do CSV Importer
        $csv_shops = get_option('csv_importer_shops', []);
        foreach ($csv_shops as $s) {
            // Evita duplicar lojas com mesmo nome
            $names = array_column($ci_shops, 'name');
            if (in_array($s['name'], $names)) continue;

            // Mapeia rede antiga para network_id
            $network_name = strtolower($s['network'] ?? 'outra');
            $network_map  = ['rakuten' => 2, 'cj' => 3, 'impact' => 4, 'admitad' => 5, 'cityads' => 6, 'lomadee' => 7, 'afilio' => 8];
            $network_id   = $network_map[$network_name] ?? 9; // 9 = Outra

            // Gera ID único
            $id = intval($s['id'] ?? time());
            while (in_array($id, $used_ids)) $id++;
            $used_ids[] = $id;

            $ci_shops[] = [
                'id'           => $id,
                'name'         => $s['name'],
                'network_id'   => $network_id,
                'mid'          => $s['mid'] ?? '',
                'publisher_id' => $s['rakuten_id'] ?? '',
                'active'       => $s['active'] ?? 1,
                'aliases'      => $s['aliases'] ?? [],
            ];
        }

        if (!empty($ci_shops)) {
            update_option('ci_shops', $ci_shops);
        }
    }
});
