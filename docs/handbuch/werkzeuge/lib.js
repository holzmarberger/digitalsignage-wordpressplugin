// Gemeinsame Hilfen für Seed- und Screenshot-Skripte (Handbuch).
const puppeteer = require('puppeteer-core');
const path = require('path');
const fs = require('fs');
// Arbeitsdateien (ids.json, media.json …), Bildausgabe und Beispieldaten
const WORK = process.env.HB_WORK || path.join(__dirname, '..', 'arbeit');
const IMG = process.env.HB_IMG || path.join(__dirname, '..', 'img');
const DATA = path.join(__dirname, 'beispieldaten');
const PHP = path.join(__dirname, 'php');
const CHROME = process.env.CHROME || '/usr/bin/google-chrome';
fs.mkdirSync(WORK, { recursive: true });
const readJson = n => JSON.parse(fs.readFileSync(path.join(WORK, n), 'utf8'));
const writeJson = (n, d) => fs.writeFileSync(path.join(WORK, n), JSON.stringify(d, null, 1));
/** PHP-Hilfsskript aus werkzeuge/php ausführen (gleiche Umgebungsvariablen). */
const php = (script, ...args) => require('child_process').execFileSync('php', [path.join(PHP, script), ...args], { stdio: 'inherit' });
// Der Testbrowser erreicht nur localhost; in Screenshots werden die Adressen auf die echten Namen umgeschrieben.
const HOST = 'intranet-marberger.oetztal.holz-marberger.at';
const BASE = 'http://localhost';
const GRAFANA = 'http://localhost:3000';
const SHOW_BASE = 'http://' + HOST;
const SHOW_GRAFANA = 'http://grafana.oetztal.holz-marberger.at:3000';
const sleep = ms => new Promise(r => setTimeout(r, ms));

async function launch() {
  return puppeteer.launch({
    env: { ...process.env, LANG: 'de_DE.UTF-8', LANGUAGE: 'de_DE:de', TZ: 'Europe/Vienna' },
    executablePath: CHROME,
    args: ['--no-sandbox', '--lang=de-DE', '--autoplay-policy=no-user-gesture-required'],
    defaultViewport: { width: 1280, height: 900, deviceScaleFactor: 2 },
  });
}
function watch(page, tag, errors) {
  page.on('dialog', d => d.accept());
  page.on('pageerror', e => errors && errors.push(tag + ' PAGEERROR ' + e.message));
  page.on('console', m => {
    const t = m.text();
    if (errors && (/^MARK MISSING/.test(t) || (m.type() === 'error' && !/favicon|ERR_|404|401|403/.test(t)))) errors.push(tag + ' CONSOLE ' + t);
  });
}
async function login(page, user, pass) {
  await page.goto(BASE + '/wp-login.php', { waitUntil: 'domcontentloaded' });
  await page.waitForNetworkIdle({ idleTime: 300 }).catch(() => {});
  await page.$eval('#user_login', (el, v) => el.value = v, user);
  await page.$eval('#user_pass', (el, v) => el.value = v, pass);
  await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), page.$eval('#wp-submit', b => b.form.requestSubmit(b))]);
}
async function publish(page) {
  await sleep(250);
  await page.evaluate(() => { if (window.jQuery) jQuery(window).off('beforeunload.edit-post'); window.onbeforeunload = null; });
  await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 60000 }), page.$eval('#publish', b => b.form.requestSubmit(b))]);
}
async function setVal(page, sel, v) { await page.$eval(sel, (el, v) => { el.value = v; el.dispatchEvent(new Event('input', { bubbles: true })); el.dispatchEvent(new Event('change', { bubbles: true })); }, v); }
async function check(page, sel, on = true) { await page.$eval(sel, (el, on) => { el.checked = on; el.dispatchEvent(new Event('change', { bubbles: true })); }, on); }
async function content(page, html) {
  await page.waitForFunction(() => window.tinymce && tinymce.get('content') && tinymce.get('content').initialized, { timeout: 8000 }).catch(() => {});
  await page.evaluate(h => {
    document.getElementById('content').value = h;
    if (window.tinymce && tinymce.get('content')) { tinymce.get('content').setContent(h); tinymce.triggerSave(); }
  }, html);
}
async function newPost(page, type) { await page.goto(BASE + '/wp-admin/post-new.php?post_type=' + type, { waitUntil: 'domcontentloaded' }); }
const idOf = page => new URL(page.url()).searchParams.get('post');
async function notices(page) { return page.$$eval('#wpbody-content .notice, #wpbody-content .error', ns => ns.map(n => n.innerText.trim()).filter(Boolean)); }

/** Adressen in Eingabefeldern und Texten auf die echten Hostnamen umschreiben (nur Darstellung). */
async function fixUrls(page) {
  await page.evaluate((b, g, sb, sg) => {
    const fix = s => s.split(g).join(sg).split(b + '/').join(sb + '/').split(b + '?').join(sb + '/?');
    document.querySelectorAll('input, textarea').forEach(el => { if (el.value && el.value.includes(b)) el.value = fix(el.value); });
    const w = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
    let n; while ((n = w.nextNode())) { if (n.nodeValue.includes(b)) n.nodeValue = fix(n.nodeValue); }
  }, BASE, GRAFANA, SHOW_BASE, SHOW_GRAFANA);
}

async function newPage(browser, w = 1280, h = 900, dsf = 2, mobile = false) {
  const page = await browser.newPage();
  const c = await page.target().createCDPSession();
  await c.send('Emulation.setLocaleOverride', { locale: 'de-AT' }).catch(() => {});
  await page.emulateTimezone('Europe/Vienna');
  await page.setViewport({ width: w, height: h, deviceScaleFactor: dsf, isMobile: mobile, hasTouch: mobile });
  return page;
}

module.exports = { WORK, IMG, DATA, CHROME, readJson, writeJson, php, newPage, SHOW_BASE, SHOW_GRAFANA, fixUrls, HOST, BASE, GRAFANA, sleep, launch, watch, login, publish, setVal, check, content, newPost, idOf, notices };
