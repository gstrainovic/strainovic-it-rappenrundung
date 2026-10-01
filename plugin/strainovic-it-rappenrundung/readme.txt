=== Strainovic IT Rappenrundung ===
Contributors: gstrainovic
Donate link: https://www.strainovic-it.ch/spenden/
Tags: woocommerce, switzerland, chf, rounding, checkout
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.4.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Rounds the WooCommerce cart and order total to 5 Swiss centimes (0.05 CHF) with a visible, VAT-free rounding line.

== Description ==

Swiss invoices and cash payments are rounded to 5 centimes (Rappen). WooCommerce calculates to the centime, so totals like CHF 29.93 end up in orders, invoices and the accounting software.

This plugin adds the difference as its own line "Rounding to 5 cents" (for example -0.02 or +0.02):

* Works in the classic checkout and in the Cart and Checkout blocks
* The rounding line has no VAT, the VAT of your products and shipping stays exactly as calculated
* Correct with coupons, shipping and fees from other plugins: the total is checked after every calculation
* The rounding line is saved in the order like any fee, so invoices, exports and accounting connectors see the same total
* Only active when the shop currency is CHF
* The label of the line can be changed under WooCommerce → Settings → General → Currency options
* Ready for translation; translations come from translate.wordpress.org, and the label of the line can be set in any language

= Accounting connectors =

The rounded total only helps if it also arrives in the accounting software. For shops that use KLARA or AbaNinja there are paid connectors from the same developer that create every order there as an invoice, with the same rounded total: [KLARA shop connector](https://www.strainovic-it.ch/en/klara-shop-connector/) and [AbaNinja shop connector](https://www.strainovic-it.ch/en/abaninja-shop-connector/). The links are also shown on the Plugins page and below the label setting. The plugin itself does not contact any external service.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install it from the plugin directory.
2. Activate it under Plugins. WooCommerce must be active and the shop currency must be CHF.
3. Optional: change the label of the rounding line under WooCommerce → Settings → General → Currency options.

== Frequently Asked Questions ==

= Is the rounding difference subject to VAT? =

No. The plugin adds it as a non-taxable fee, the VAT of the order is calculated before rounding and is not changed.

= What about orders created in the admin? =

The rounding is applied to the cart and checkout. Orders created or recalculated manually in the admin are not rounded.

= Does it round for card payments too? =

Yes, the whole order total is rounded, regardless of the payment method.

= In which language is the rounding line shown? =

In the language of the site, as soon as the translation for it is available on translate.wordpress.org; otherwise in English ("Rounding to 5 cents"). If you enter your own label, for example "Rundung auf 5 Rappen", that label is used in every language.

= Where does the rounding line appear? =

Directly before the total, after the VAT: in the classic cart and checkout, in order emails, on the thank-you page, in My Account and in invoice plugins that use the WooCommerce order totals. In the cart and checkout blocks WooCommerce sets the order itself and shows the line next to the other fees.

= I already use a rounding snippet. Do I need to remove it? =

It is better to remove it, but it does no harm. Snippets on the filter woocommerce_calculated_total only round the total shown in the cart; the block checkout creates the order without them. The plugin calculates the rounding from the order lines, so the order is rounded either way.

== Changelog ==

= 0.4.4 =
* Classic cart and checkout: the rounding line now comes directly before the total, after the VAT, as in the order and on the invoice.

= 0.4.3 =
* Donation link on the plugins page and on the plugin page in the directory.

= 0.4.2 =
* Translations are no longer bundled; WordPress loads them from translate.wordpress.org. Until the German, French and Italian translations are available there, the default label is English; a label entered in the settings is not affected.

= 0.4.1 =
* The rounding line now comes directly before the order total in order emails, on the thank-you page, in My Account and in invoice plugins that use the WooCommerce order totals, as usual on Swiss invoices. The sidebar of the Checkout block keeps the order WooCommerce sets. Applies to orders placed with 0.4.1 or later.

= 0.4.0 =
* New name "Strainovic IT Rappenrundung", plugin folder and text domain strainovic-it-rappenrundung. Coming from 0.3.x (folder "rappenrundung"): install 0.4.0, then deactivate and delete the old plugin; the label setting is kept. While both are active, only one of them rounds.

= 0.3.1 =
* Block checkout: the rounding line is also added when another snippet or plugin already rounds the displayed total with the filter woocommerce_calculated_total. Before, the order was created with the unrounded amount.

= 0.3.0 =
* Links to the KLARA and AbaNinja shop connectors on the Plugins page and below the label setting, in the language of the admin.

= 0.2.0 =
* English source texts with German (de_DE, de_CH), French and Italian translations. The default label of the rounding line follows the language of the site, also when the German default of 0.1.0 was saved.

= 0.1.0 =
* First release: total rounded to 0.05 CHF with a VAT-free rounding line, classic and block checkout.
