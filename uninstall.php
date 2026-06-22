<?php
if (!defined('WP_UNINSTALL_PLUGIN')) exit;

for ($i = 1; $i <= 5; $i++) delete_option("ci_sheet_url_{$i}");
delete_option('ci_batch_size');
delete_option('ci_import_mode');
delete_option('ci_shops');
delete_option('ci_networks');
