<?php
// Hilfsendpunkt nur für den Testshop: Produkt-ID, fremdes Snippet an/aus, Bestellung als JSON
require __DIR__ . '/../wp-load.php';
header('Content-Type: application/json');
if (isset($_GET['snippet'])) {
    // Snippet wie in https://wordpress.org/support/topic/rounding-issue-6/ als MU-Plugin
    $datei = WPMU_PLUGIN_DIR . '/fremdes-snippet.php';
    wp_mkdir_p(WPMU_PLUGIN_DIR);
    if ($_GET['snippet'] === '1') {
        file_put_contents($datei, "<?php\nadd_filter('woocommerce_calculated_total', fn (\$p) => round((\$p + 0.000001) * 20) / 20);\n");
    } elseif (file_exists($datei)) {
        unlink($datei);
    }
    echo json_encode(['snippet' => file_exists($datei)]);
    exit;
}
if (isset($_GET['leeren'])) {
    // Warenkorb des angemeldeten Playground-Admins leeren (Sitzung und dauerhafter Warenkorb)
    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->prefix}woocommerce_sessions");
    foreach (get_user_meta(get_current_user_id()) as $schluessel => $_) {
        if (str_starts_with($schluessel, '_woocommerce_persistent_cart')) {
            delete_user_meta(get_current_user_id(), $schluessel);
        }
    }
    echo json_encode(['geleert' => true, 'benutzer' => get_current_user_id()]);
    exit;
}
if (isset($_GET['id'])) {
    $o = wc_get_order((int) $_GET['id']);
    echo json_encode([
        'total' => (float) $o->get_total(),
        'steuer' => (float) $o->get_total_tax(),
        'gebuehren' => array_map(fn ($f) => ['name' => $f->get_name(), 'total' => (float) $f->get_total(), 'steuer' => (float) $f->get_total_tax()], array_values($o->get_fees())),
        'erstellt_ueber' => $o->get_created_via(),
    ]);
    exit;
}
echo json_encode(['produkt' => (int) get_option('e2e_produkt'), 'woocommerce' => WC()->version]);
