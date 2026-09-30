// Plugin Check von wordpress.org über die Admin-Seite im Playground (Werkzeuge → Plugin Check), Kategorie
// «Plugin Repo», wie die Review sie anwendet. Gibt die Befunde aus; 0 Befunde = bereit zum Einreichen.
//   node plugin-check.mjs [http://127.0.0.1:9400]
import { chromium } from 'playwright-core'

const BASE = process.argv[2] ?? 'http://127.0.0.1:9400'
const browser = await chromium.launch(process.env.BROWSER_PFAD
  ? { executablePath: process.env.BROWSER_PFAD }
  : { channel: process.env.BROWSER ?? 'chrome' })
const page = await browser.newPage()
await page.goto(BASE)
await page.goto(`${BASE}/wp-admin/tools.php?page=plugin-check`)
await page.selectOption('#plugin-check__plugins-dropdown', 'strainovic-it-rappenrundung/strainovic-it-rappenrundung.php')
await page.click('#plugin-check__submit')
await page.locator('#plugin-check__results-complete, .plugin-check__results-heading').first().waitFor({ timeout: 180000 }).catch(() => {})
await page.waitForTimeout(3000)
const text = await page.locator('#plugin-check__results').innerText().catch(() => '')
const zeilen = await page.locator('#plugin-check__results table tbody tr').allInnerTexts()
console.log(text.split('\n').slice(0, 5).join('\n'))
for (const z of zeilen) console.log('BEFUND ' + z.replace(/\s+/g, ' ').slice(0, 300))
console.log(`${zeilen.length} Befunde`)
await page.screenshot({ path: 'plugin-check.png', fullPage: true })
await browser.close()
process.exit(zeilen.length ? 1 : 0)
