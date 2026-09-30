<?php
/**
 * Plugin Name:       Strainovic IT Rappenrundung
 * Plugin URI:        https://www.strainovic-it.ch/rappenrundung/
 * Description:       Rounds the cart and checkout total to 5 Swiss centimes (Rappen). The difference is its own line without VAT in the order and on the invoice.
 * Version:           0.4.3
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Requires Plugins:  woocommerce
 * Author:            Strainovic IT
 * Author URI:        https://www.strainovic-it.ch/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       strainovic-it-rappenrundung
 * WC requires at least: 8.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

spl_autoload_register(static function (string $klasse): void {
    $praefix = 'StrainovicIT\\Rappenrundung\\';
    if (str_starts_with($klasse, $praefix)) {
        $datei = __DIR__ . '/src/' . str_replace('\\', '/', substr($klasse, strlen($praefix))) . '.php';
        if (is_file($datei)) {
            require_once $datei;
        }
    }
});

add_action('before_woocommerce_init', static function (): void {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
});

add_action('plugins_loaded', static function (): void {
    StrainovicIT\Rappenrundung\Plugin::starten(plugin_basename(__FILE__));
});
