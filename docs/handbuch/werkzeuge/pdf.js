// HTML → PDF über Chrome (CSS @page, Kopf-/Fußzeilen aus der Vorlage). Aufruf: node pdf.js <quelle.html> <ziel.pdf>
const puppeteer = require('puppeteer-core');
(async () => {
  const [src, out] = process.argv.slice(2);
  const b = await puppeteer.launch({ executablePath: process.env.CHROME || '/usr/bin/google-chrome', args: ['--no-sandbox', '--allow-file-access-from-files'] });
  const p = await b.newPage();
  await p.goto('file://' + src, { waitUntil: 'networkidle0', timeout: 120000 });
  await p.evaluate(() => document.fonts.ready);
  await p.pdf({ path: out, preferCSSPageSize: true, printBackground: true, timeout: 180000 });
  await b.close();
})().catch(e => { console.error(e); process.exit(1); });
