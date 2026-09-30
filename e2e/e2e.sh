#!/usr/bin/env bash
# End-to-End: echtes WordPress mit WooCommerce, CHF, Schweizer MWST inklusive; Bestellungen über die
# Store-API (dieselbe Schnittstelle wie der Checkout-Block) und über den klassischen Warenkorb.
set -euo pipefail
cd "$(dirname "$0")"
wp() { docker compose exec -T cli wp "$@"; }
BASE=http://localhost:8093
until docker compose exec -T wp test -f /var/www/html/wp-config.php; do sleep 2; done
until docker compose exec -T cli wp db query "SELECT 1" >/dev/null 2>&1; do sleep 2; done
wp core is-installed 2>/dev/null || wp core install --url=$BASE --title=Rundung-Test --admin_user=admin --admin_password=admin --admin_email=test@example.com --skip-email
wp plugin is-installed woocommerce || wp plugin install woocommerce
wp plugin activate woocommerce strainovic-it-rappenrundung >/dev/null 2>&1 || true
# Sprachpakete für WordPress und WooCommerce, damit jede Seite rundum in ihrer Sprache ist; Grundsprache de_CH
SPRACHEN="de_CH fr_FR it_IT"
wp language core install $SPRACHEN >/dev/null 2>&1 || true
wp language plugin install woocommerce $SPRACHEN >/dev/null 2>&1 || true
# Eigene Übersetzungen wie ein Sprachpaket von translate.wordpress.org (das Plugin liefert keine mit)
docker compose exec -T cli sh -c 'mkdir -p /var/www/html/wp-content/languages/plugins && cp /translations/*.mo /var/www/html/wp-content/languages/plugins/'
wp site switch-language de_CH >/dev/null
wp eval-file - <<'PHP'
<?php
update_option('woocommerce_currency', 'CHF');
update_option('woocommerce_default_country', 'CH:SG');
update_option('woocommerce_calc_taxes', 'yes');
update_option('woocommerce_prices_include_tax', 'yes');
update_option('woocommerce_tax_based_on', 'billing');
update_option('rappenrundung_bezeichnung', '');
global $wpdb;
$wpdb->query("DELETE FROM {$wpdb->prefix}woocommerce_tax_rates");
WC_Tax::_insert_tax_rate(['tax_rate_country' => 'CH', 'tax_rate' => '8.1000', 'tax_rate_name' => 'MWST', 'tax_rate_priority' => 1, 'tax_rate_shipping' => 1, 'tax_rate_class' => '']);
$cod = WC()->payment_gateways()->payment_gateways()['cod'];
$cod->update_option('enabled', 'yes');
$ids = [];
foreach (['A' => '9.97', 'B' => '29.90'] as $k => $preis) {
    $p = new WC_Product_Simple();
    $p->set_name("Rundungsprodukt $k"); $p->set_regular_price($preis); $p->save();
    $ids[$k] = $p->get_id();
}
if (!wc_get_coupon_id_by_code('zehn')) {
    $c = new WC_Coupon(); $c->set_code('zehn'); $c->set_discount_type('percent'); $c->set_amount(10); $c->save();
}
update_option('e2e_produkte', $ids);
PHP
A=$(wp eval 'echo get_option("e2e_produkte")["A"];'); B=$(wp eval 'echo get_option("e2e_produkte")["B"];')

fail=0
check() { if [ "$2" = "1" ]; then echo "OK   $1"; else echo "FAIL $1"; fail=$((fail+1)); fi; }
wahr() { [ "$1" = "true" ] && echo 1 || echo 0; }

# Store-API: Warenkorb anlegen, Artikel hinein, optional Gutschein, bestellen
warenkorb() { # produkt menge [gutschein] -> setzt TOKEN, NONCE und W (Warenkorb-JSON); nicht in $(…) aufrufen
  local hdr; hdr=$(mktemp)
  curl -s -D "$hdr" -o /dev/null "$BASE/?rest_route=/wc/store/v1/cart"
  TOKEN=$(grep -i '^cart-token:' "$hdr" | tr -d '\r' | cut -d' ' -f2); NONCE=$(grep -i '^nonce:' "$hdr" | tr -d '\r' | cut -d' ' -f2)
  local r
  r=$(curl -s -H "Cart-Token: $TOKEN" -H "Nonce: $NONCE" -H 'Content-Type: application/json' -d "{\"id\":$1,\"quantity\":$2}" "$BASE/?rest_route=/wc/store/v1/cart/add-item")
  if [ -n "${3:-}" ]; then
    r=$(curl -s -H "Cart-Token: $TOKEN" -H "Nonce: $NONCE" -H 'Content-Type: application/json' -d "{\"code\":\"$3\"}" "$BASE/?rest_route=/wc/store/v1/cart/apply-coupon")
  fi
  W=$r
}
bestellen() {
  curl -s -H "Cart-Token: $TOKEN" -H "Nonce: $NONCE" -H 'Content-Type: application/json' -d '{
    "billing_address":{"first_name":"Anna","last_name":"Muster","address_1":"Bahnhofstrasse 1","city":"St. Gallen","postcode":"9000","country":"CH","email":"anna@muster.ch"},
    "shipping_address":{"first_name":"Anna","last_name":"Muster","address_1":"Bahnhofstrasse 1","city":"St. Gallen","postcode":"9000","country":"CH"},
    "payment_method":"cod"}' "$BASE/?rest_route=/wc/store/v1/checkout" | jq -r '.order_id // empty'
}
bestellung() { wp eval "\$o = wc_get_order($1); \$f = array_values(\$o->get_fees()); echo json_encode(['total' => (float) \$o->get_total(), 'steuer' => (float) \$o->get_total_tax(), 'gebuehren' => array_map(fn (\$x) => ['name' => \$x->get_name(), 'total' => (float) \$x->get_total(), 'steuer' => (float) \$x->get_total_tax()], \$f)]);"; }

# 9.97: abrunden auf 9.95, Rundungsposition -0.02 ohne MWST (Store-API rechnet in Rappen)
warenkorb $A 1
check "Warenkorb 9.97 → Total 995 Rappen ($(jq -r .totals.total_price <<<"$W"))" "$(ist=$(jq -r .totals.total_price <<<"$W"); [ "$ist" = "995" ] && echo 1 || echo 0)"
check "Rundungsposition im Warenkorb: $(jq -c '[.fees[] | {name, t: .totals.total}]' <<<"$W")" "$(wahr "$(jq '[.fees[] | select(.name == "Rundung auf 5 Rappen" and .totals.total == "-2" and .totals.total_tax == "0")] | length == 1' <<<"$W")")"
O=$(bestellen)
check "Bestellung über den Checkout-Block angelegt (#$O)" "$([ -n "$O" ] && echo 1 || echo 0)"
J=$(bestellung "$O")
check "Bestellung: Total 9.95, Rundung -0.02 ohne MWST, MWST 0.75 unverändert: $J" "$(wahr "$(jq '.total == 9.95 and (.gebuehren | length) == 1 and .gebuehren[0].total == -0.02 and .gebuehren[0].steuer == 0 and .steuer == 0.75' <<<"$J")")"

# fremdes Rundungs-Snippet auf woocommerce_calculated_total, wie es viele Shops einsetzen: rundet nur die Anzeige, die Bestellung
# aus dem Checkout-Block braucht trotzdem die Rundungsposition
wp eval 'file_put_contents(WPMU_PLUGIN_DIR . "/fremdes-snippet.php", "<?php add_filter(\"woocommerce_calculated_total\", fn (\$p) => round((\$p + 0.000001) * 20) / 20);");'
warenkorb $A 1
O=$(bestellen)
J=$(bestellung "$O")
wp eval 'unlink(WPMU_PLUGIN_DIR . "/fremdes-snippet.php");'
check "fremdes Snippet: Bestellung 9.95 mit Rundung -0.02: $J" "$(wahr "$(jq '.total == 9.95 and (.gebuehren | length) == 1 and .gebuehren[0].total == -0.02' <<<"$J")")"

# 2 × 29.90 mit 10 % Gutschein = 53.82 → 53.80
warenkorb $B 2 zehn
check "Gutschein: 53.82 → 5380 Rappen ($(jq -r .totals.total_price <<<"$W"))" "$([ "$(jq -r .totals.total_price <<<"$W")" = "5380" ] && echo 1 || echo 0)"

# schon rund: keine Rundungsposition
warenkorb $B 1
check "29.90: keine Rundungsposition" "$(wahr "$(jq '(.fees | length) == 0 and .totals.total_price == "2990"' <<<"$W")")"

# Aufrunden: 3 × 9.97 = 29.91 → 29.90 (ab); 4 × 9.97 = 39.88 → 39.90 (auf)
warenkorb $A 4
check "39.88 → 3990 Rappen, Rundung +0.02" "$(wahr "$(jq '.totals.total_price == "3990" and ([.fees[] | select(.totals.total == "2")] | length) == 1' <<<"$W")")"

# eigene Bezeichnung
wp option update rappenrundung_bezeichnung "Rundung 5 Rp." >/dev/null
warenkorb $A 1
check "eigene Bezeichnung: $(jq -r '.fees[0].name' <<<"$W")" "$([ "$(jq -r '.fees[0].name' <<<"$W")" = "Rundung 5 Rp." ] && echo 1 || echo 0)"
wp option update rappenrundung_bezeichnung "" >/dev/null

# Standardbezeichnung in der Sprache der Seite
for paar in "en_US:Rounding to 5 cents" "fr_FR:Arrondi à 5 centimes" "it_IT:Arrotondamento a 5 centesimi" "de_CH:Rundung auf 5 Rappen"; do
  wp site switch-language "${paar%%:*}" >/dev/null
  warenkorb $A 1
  check "${paar%%:*}: $(jq -r '.fees[0].name' <<<"$W")" "$([ "$(jq -r '.fees[0].name' <<<"$W")" = "${paar#*:}" ] && echo 1 || echo 0)"
done
# gespeicherter Standard aus 0.1.0 blockiert die Übersetzung nicht
wp option update rappenrundung_bezeichnung "Rundungsdifferenz" >/dev/null
wp site switch-language fr_FR >/dev/null
warenkorb $A 1
check "0.1.0-Standard «Rundungsdifferenz» gespeichert, fr_FR: $(jq -r '.fees[0].name' <<<"$W")" "$([ "$(jq -r '.fees[0].name' <<<"$W")" = "Arrondi à 5 centimes" ] && echo 1 || echo 0)"
wp option update rappenrundung_bezeichnung "" >/dev/null
wp site switch-language de_CH >/dev/null

# klassischer Warenkorb (PHP) und andere Währung
R=$(wp eval "WC()->frontend_includes(); WC()->session = new WC_Session_Handler(); WC()->session->init(); WC()->customer = new WC_Customer(0, true); WC()->cart = new WC_Cart(); WC()->cart->add_to_cart($A, 1); WC()->cart->calculate_totals(); echo WC()->cart->get_total('edit');")
check "klassischer Warenkorb: $R" "$([ "$R" = "9.95" ] && echo 1 || echo 0)"
wp option update woocommerce_currency EUR >/dev/null
warenkorb $A 1
check "EUR: keine Rundung ($(jq -r .totals.total_price <<<"$W"))" "$(wahr "$(jq '(.fees | length) == 0 and .totals.total_price == "997"' <<<"$W")")"
wp option update woocommerce_currency CHF >/dev/null

# Einstellung unter WooCommerce → Einstellungen → Allgemein
S=$(wp eval 'echo count(array_filter(apply_filters("woocommerce_general_settings", [["id" => "pricing_options", "type" => "sectionend"]]), fn ($f) => ($f["id"] ?? "") === "rappenrundung_bezeichnung"));')
check "Einstellung in den Währungsoptionen" "$([ "$S" = "1" ] && echo 1 || echo 0)"

# Hinweis auf die Connectoren: unter dem Einstellungsfeld und in der eigenen Plugin-Zeile, Seite in der Sprache des Admins
D=$(wp eval 'foreach (apply_filters("woocommerce_general_settings", [["id" => "pricing_options", "type" => "sectionend"]]) as $f) { if (($f["id"] ?? "") === "rappenrundung_bezeichnung") echo $f["desc"]; }')
check "de_CH: Hinweis unter dem Feld verlinkt KLARA- und AbaNinja-Connector" "$(grep -q 'href="https://www.strainovic-it.ch/klara-shop-connector/"[^>]*>KLARA-Shop-Connector' <<<"$D" && grep -q 'href="https://www.strainovic-it.ch/abaninja-shop-connector/"' <<<"$D" && echo 1 || echo 0)"
wp site switch-language fr_FR >/dev/null
Z=$(wp eval 'echo implode("\n", apply_filters("plugin_row_meta", [], "strainovic-it-rappenrundung/strainovic-it-rappenrundung.php", [], "all"));')
F=$(wp eval 'echo count(array_filter(apply_filters("plugin_row_meta", [], "woocommerce/woocommerce.php", [], "all"), fn ($l) => str_contains($l, "strainovic-it.ch")));')
wp site switch-language de_CH >/dev/null
check "fr_FR: Plugin-Zeile mit connecteur de boutique KLARA/AbaNinja unter /fr/" "$(grep -q '/fr/klara-shop-connector/"[^>]*>connecteur de boutique KLARA' <<<"$Z" && grep -q '/fr/abaninja-shop-connector/' <<<"$Z" && echo 1 || echo 0)"
check "fremde Plugin-Zeile ohne Links ($F)" "$([ "$F" = "0" ] && echo 1 || echo 0)"

echo "$fail Fehler"
exit $fail
