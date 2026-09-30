# Strainovic IT Rappenrundung

WordPress plugin that rounds the WooCommerce cart and order total to 5 Swiss centimes (0.05 CHF), as on every
Swiss invoice. The difference is its own line without VAT, so order lines, total, invoice and accounting match.

- Plugin directory: https://wordpress.org/plugins/strainovic-it-rappenrundung/
- Product page (DE/FR/IT/EN): https://www.strainovic-it.ch/rappenrundung/
- Donate: https://www.strainovic-it.ch/spenden/

This repository mirrors the released code, tests and translations. Issues and pull requests are welcome.

## Development

- Unit tests (PHPUnit with Brain Monkey, in Docker): `./bin-test.sh`
- End-to-end with WordPress and WooCommerce: `cd e2e && docker compose up -d && ./e2e.sh`
- Translations: `translations/` (German, Swiss German, French, Italian); in WordPress they come as language
  packs from translate.wordpress.org.

## License

GPL-2.0-or-later, see `LICENSE`. © Goran Strainovic, Strainovic IT, Steinach (Switzerland).
