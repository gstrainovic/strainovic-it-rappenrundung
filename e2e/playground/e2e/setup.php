<?php
// Typischer Schweizer Shop: CHF, Preise ohne MWST, 8.1 %, Versandpauschale 12.00, Zahlung per Rechnung (bacs)
update_option('woocommerce_currency', 'CHF');
update_option('woocommerce_default_country', 'CH:AG');
update_option('woocommerce_calc_taxes', 'yes');
update_option('woocommerce_prices_include_tax', 'no');
update_option('woocommerce_tax_based_on', 'billing');
update_option('woocommerce_coming_soon', 'no');
update_option('rappenrundung_bezeichnung', 'Rundung auf 5 Rappen');
global $wpdb;
$wpdb->query("DELETE FROM {$wpdb->prefix}woocommerce_tax_rates");
WC_Tax::_insert_tax_rate(['tax_rate_country' => 'CH', 'tax_rate' => '8.1000', 'tax_rate_name' => 'MWST', 'tax_rate_priority' => 1, 'tax_rate_shipping' => 1, 'tax_rate_class' => '']);
$zone = new WC_Shipping_Zone();
$zone->set_zone_name('Schweiz');
$zone->add_location('CH', 'country');
$zone->save();
$id = $zone->add_shipping_method('flat_rate');
update_option("woocommerce_flat_rate_{$id}_settings", ['title' => 'Post', 'tax_status' => 'taxable', 'cost' => '12.00']);
WC()->payment_gateways()->payment_gateways()['bacs']->update_option('enabled', 'yes');
$p = new WC_Product_Simple();
$p->set_name('Victory');
$p->set_regular_price('60.13');
$p->save();
update_option('e2e_produkt', $p->get_id());
