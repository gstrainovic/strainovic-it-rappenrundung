// Echter Checkout-Block im Browser gegen WordPress Playground (siehe AGENTS.md, «Windows ohne Docker»):
// Produkt in den Warenkorb, Checkout ausfüllen, per Rechnung bestellen, Bestellung prüfen; einmal ohne,
// einmal mit fremdem Rundungs-Snippet auf woocommerce_calculated_total.
//   node checkout-block.mjs [http://127.0.0.1:9400]
import { chromium } from 'playwright-core'

const BASE = process.argv[2] ?? 'http://127.0.0.1:9400'
let fehler = 0
const check = (name, ok) => { console.log(`${ok ? 'OK  ' : 'FAIL'} ${name}`); if (!ok) fehler++ }

// installiertes Chrome oder Edge, kein eigenes Chromium herunterladen: BROWSER_PFAD (etwa Chrome aus scoop)
// oder BROWSER=chrome|msedge für die Standardinstallation
const browser = await chromium.launch(process.env.BROWSER_PFAD
  ? { executablePath: process.env.BROWSER_PFAD }
  : { channel: process.env.BROWSER ?? 'chrome' })
const page = await browser.newPage({ locale: 'de-CH' })
process.on('unhandledRejection', async e => {
  console.log(`FAIL ${String(e.message).split('\n')[0]}`)
  await page.screenshot({ path: 'fehler.png', fullPage: true }).catch(() => {})
  await browser.close()
  process.exit(1)
})
const json = async pfad => JSON.parse(await (await page.request.get(`${BASE}/e2e/hilfe.php${pfad}`)).text())

await page.goto(BASE)
const { produkt, woocommerce } = await json('')
console.log(`WooCommerce ${woocommerce}, Produkt ${produkt}`)

for (const snippet of ['0', '1']) {
  await json(`?snippet=${snippet}`)
  await json('?leeren=1')
  await page.goto(`${BASE}/?add-to-cart=${produkt}`)
  await page.goto(`${BASE}/checkout/`)
  await page.locator('#shipping-first_name').waitFor({ state: 'attached' })
  // Mit gespeicherter Adresse (Playground ist als Admin angemeldet) zeigt der Block nur die zugeklappte Adresskarte
  for (const [feld, wert] of [['#email', 'test@example.com'], ['#shipping-first_name', 'Anna'],
    ['#shipping-last_name', 'Muster'], ['#shipping-address_1', 'Musterstrasse 1'], ['#shipping-postcode', '8000'],
    ['#shipping-city', 'Zürich']]) {
    if (await page.locator(feld).isVisible()) await page.fill(feld, wert)
  }
  await page.locator('.wc-block-components-totals-footer-item').getByText('77.95').waitFor({ timeout: 20000 }).catch(() => {})
  const angezeigt = (await page.locator('.wc-block-components-totals-footer-item').innerText()).replace(/\s+/g, ' ')
  const zeile = await page.locator('.wc-block-components-totals-fees').innerText().catch(() => '')
  check(`Snippet ${snippet}: Checkout zeigt ${angezeigt} und «${zeile.replace(/\s+/g, ' ')}»`, angezeigt.includes('77.95') && /-\s*(CHF)?\s*0\.02/.test(zeile))
  await page.locator('input[value="bacs"]').check()
  await page.locator('.wc-block-components-checkout-place-order-button').click()
  await page.waitForURL(/order-received\/(\d+)/, { timeout: 60000 })
  const id = page.url().match(/order-received\/(\d+)/)[1]
  // Summenzeilen der Danke-Seite (WC_Order::get_order_item_totals): Rundung direkt vor dem Total, nach der MWST
  const labels = (await page.locator('tfoot tr th, tfoot tr td:first-child').allInnerTexts()).map(t => t.trim()).filter(Boolean)
  const iRundung = labels.findIndex(l => l.startsWith('Rundung'))
  const iTotal = labels.findIndex(l => /^Total/.test(l))
  const iMwst = labels.findIndex(l => l.startsWith('MWST'))
  check(`Snippet ${snippet}: Danke-Seite ${JSON.stringify(labels)}`, iRundung >= 0 && iRundung === iTotal - 1 && iMwst < iRundung)
  const b = await json(`?id=${id}`)
  check(`Snippet ${snippet}: Bestellung #${id} ${JSON.stringify(b)}`,
    b.total === 77.95 && b.gebuehren.length === 1 && b.gebuehren[0].total === -0.02 && b.gebuehren[0].steuer === 0 && b.erstellt_ueber === 'store-api')
  await page.screenshot({ path: `bestellt-snippet-${snippet}.png`, fullPage: true })
}
await json('?snippet=0')
await browser.close()
console.log(`${fehler} Fehler`)
process.exit(fehler)
