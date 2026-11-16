// Screenshots für das Anwenderhandbuch.
const L = require('./lib');
const fs = require('fs');
const ids = L.readJson('ids.json');
const OUT = L.IMG;
fs.mkdirSync(OUT, { recursive: true });
const only = process.argv[2] ? new RegExp(process.argv[2]) : null;
const errors = [];
const log = (...a) => console.log('•', ...a);
const DSF = 1.5;

// ---------- Markierungen
async function mark(page, items) {
  await page.evaluate(items => {
    document.querySelectorAll('.hb-mark').forEach(e => e.remove());
    const find = sel => {
      if (sel.startsWith('row:')) {
        const label = sel.slice(4);
        const th = [...document.querySelectorAll('.form-table th, .form-table td:first-child')].find(t => t.textContent.trim().startsWith(label));
        return th ? th.closest('tr') : null;
      }
      if (sel.startsWith('text:')) {
        const [tag, txt] = sel.slice(5).split('|');
        return [...document.querySelectorAll(tag)].find(e => e.textContent.trim().startsWith(txt)) || null;
      }
      return document.querySelector(sel);
    };
    for (const it of items) {
      let el = find(it.sel);
      if (el && it.up) el = el.closest(it.up);
      if (!el) { console.log('MARK MISSING ' + it.sel); continue; }
      const r = el.getBoundingClientRect();
      const pad = it.pad === undefined ? (it.sel.startsWith('row:') ? 1 : 4) : it.pad;
      const box = document.createElement('div');
      box.className = 'hb-mark';
      Object.assign(box.style, { position: 'absolute', left: (r.left + scrollX - pad) + 'px', top: (r.top + scrollY - pad) + 'px', width: (r.width + 2 * pad) + 'px', height: (r.height + 2 * pad) + 'px',
        border: '3px solid #922224', borderRadius: '6px', zIndex: 99999, pointerEvents: 'none', boxSizing: 'border-box' });
      if (it.n) {
        const b = document.createElement('div');
        b.textContent = it.n;
        Object.assign(b.style, { position: 'absolute', top: '-13px', width: '24px', height: '24px', borderRadius: '50%', background: '#922224', color: '#fff',
          font: 'bold 14px/24px Arial, sans-serif', textAlign: 'center', boxShadow: '0 0 0 2px #fff' });
        if (it.right) b.style.right = '-13px'; else b.style.left = (r.left + scrollX - pad < 205 ? '3px' : '-13px');
        box.appendChild(b);
      }
      document.body.appendChild(box);
    }
  }, items);
}
async function unmark(page) { await page.evaluate(() => document.querySelectorAll('.hb-mark').forEach(e => e.remove())); }
async function fitHeight(page, w) {
  const h = await page.evaluate(() => Math.min(6000, Math.max(document.documentElement.scrollHeight, document.body.scrollHeight)));
  await page.setViewport({ width: w || page.viewport().width, height: h, deviceScaleFactor: DSF });
  await L.sleep(250);
}
/** Ausschnitt um ein Element (Dokumentkoordinaten), optional mit weiteren Elementen vereinigt. */
async function clipOf(page, sels, margin = 18) {
  return page.evaluate((sels, m) => {
    let x1 = 1e9, y1 = 1e9, x2 = 0, y2 = 0;
    for (const s of sels) {
      const el = document.querySelector(s);
      if (!el) continue;
      const r = el.getBoundingClientRect();
      x1 = Math.min(x1, r.left + scrollX); y1 = Math.min(y1, r.top + scrollY); x2 = Math.max(x2, r.right + scrollX); y2 = Math.max(y2, r.bottom + scrollY);
    }
    return { x: Math.max(0, x1 - m), y: Math.max(0, y1 - m), width: Math.min(document.documentElement.clientWidth, x2 + m) - Math.max(0, x1 - m), height: y2 - y1 + 2 * m };
  }, sels, margin);
}
async function shot(page, name, opts = {}) {
  if (only && !only.test(name)) return;
  await L.fixUrls(page);
  if (opts.fit !== false) await fitHeight(page, opts.width);
  if (opts.prep) await opts.prep();
  if (opts.marks) await mark(page, opts.marks);
  let clip = opts.clip;
  if (!clip && opts.sels) clip = await clipOf(page, opts.sels, opts.margin);
  if (!clip && opts.content) { clip = await clipOf(page, ['#wpbody-content'], 0); clip.x += 0; clip.width -= 0; }
  if (clip && opts.maxH) clip.height = Math.min(clip.height, opts.maxH);
  await page.screenshot({ path: `${OUT}/${name}.png`, clip, captureBeyondViewport: true });
  await unmark(page);
  log(name);
}
async function go(page, url, w = 1280) {
  await page.setViewport({ width: w, height: 900, deviceScaleFactor: DSF });
  await page.goto(L.BASE + url, { waitUntil: 'networkidle2' });
  await page.evaluate(() => { window.scrollTo(0, 0); document.querySelectorAll('.mms-help-compact').forEach(d => d.open = false); });
}
const edit = id => `/wp-admin/post.php?post=${id}&action=edit`;

// ---------- Player
async function player(page, key, at, name, fn, quer = false, wait = 1300, marks = null) {
  if (only && !only.test(name)) return;
  await page.setViewport(quer ? { width: 960, height: 540, deviceScaleFactor: 2 } : { width: 540, height: 960, deviceScaleFactor: 2 });
  await page.goto(`${L.BASE}/?mm_signage=${key}${at ? '&at=' + at : ''}`, { waitUntil: 'networkidle2' });
  await L.sleep(wait);
  if (fn) await fn(page);
  if (marks) await mark(page, marks.map(m => ({ pad: 3, ...m })));
  await page.screenshot({ path: `${OUT}/${name}.png` });
  if (marks) await unmark(page);
  log(name);
}
/** Kurzform: Player-Aufnahme mit Nummern (Feld → Anzeige). */
const pm = (p, key, at, name, marks, fn, quer = false, wait = 1300) => player(p, key, at, name, fn, quer, wait, marks);
const tap = async (p, sel, w = 700) => { await p.click(sel); await L.sleep(w); };

(async () => {
  const browser = await L.launch();
  const page = await L.newPage(browser, 1280, 900, DSF);
  L.watch(page, 'admin', errors);
  await L.login(page, 'admin', 'admin123');

  // ===== Übersicht / Menü
  // Displays melden sich (Lebenszeichen) für die Übersicht
  for (const k of ['empfang-oetztal', 'info-oetztal', 'empfang-silz', 'info-silz']) await page.evaluate(u => fetch(u), `${L.BASE}/?rest_route=/mms/v1/state/${k}`);
  await go(page, '/wp-admin/admin.php?page=mms');
  await shot(page, 'b-overview', { content: true, marks: [{ sel: '.mms-pwbox p', n: 1 }, { sel: 'text:p|Handy-Pflegemaske', n: 2 }, { sel: 'text:p|Gruß-Passwort', n: 3 }, { sel: '.widefat', n: 4 }] });
  await shot(page, 'b-menu-admin', { sels: ['#toplevel_page_mms'], margin: 6 });

  // ===== Einstellungen
  await go(page, '/wp-admin/admin.php?page=mms-settings', 1500);
  await shot(page, 'b-settings', { width: 1500, content: true, marks: [{ sel: 'row:Kurze Lücken', n: 1 }, { sel: 'row:Aktuelles Passwort', n: 2 }, { sel: 'row:Neues Passwort', n: 3 }, { sel: 'row:Adresse der Maske', n: 4 }, { sel: '.mms-tpl-table', n: 5 }] });

  // ===== Standorte
  await go(page, '/wp-admin/edit.php?post_type=mms_location');
  await shot(page, 'b-loc-list', { content: true });
  await go(page, edit(ids.loc_oetztal), 1360);
  await shot(page, 'b-loc-edit', { width: 1360, sels: ['#titlediv', '#mms-box'], marks: [{ sel: 'row:Logo', n: 1 }, { sel: 'row:Koordinaten', n: 2 }, { sel: 'row:Feiertagskalender', n: 3 }, { sel: 'row:Öffnungszeiten', n: 4 }, { sel: 'row:Feiertage', n: 5 }, { sel: 'row:Ausnahmen', n: 6 }, { sel: 'row:Vorschau', n: 7 }] });
  await go(page, edit(ids.loc_silz), 1360);
  await shot(page, 'b-loc-silz-hours', { width: 1360, sels: ['#mms-box .form-table tr:nth-child(4)'], margin: 10 });

  // ===== Schließzeiten
  await go(page, edit(ids.closure));
  await shot(page, 'b-closure-edit', { sels: ['#titlediv', '#mms-box'], marks: [{ sel: '#title', n: 1 }, { sel: 'row:Bezeichnung (Deutsch)', n: 2 }, { sel: 'row:Zeitraum', n: 3 }, { sel: 'row:Standorte', n: 4 }, { sel: 'row:Text (Deutsch)', n: 5 }, { sel: 'row:Bild', n: 6 }, { sel: 'row:Vorankündigung', n: 7 }] });

  // ===== Kategorien
  await go(page, '/wp-admin/edit.php?post_type=mms_category');
  await shot(page, 'b-cat-list', { content: true });
  const cats = await page.$$eval('#the-list a.row-title', as => as.map(a => [a.textContent.trim(), a.href]));
  const catUrl = n => cats.find(c => c[0] === n)[1].replace(L.BASE, '');
  await go(page, catUrl('Empfang'));
  await shot(page, 'b-cat-empfang', { sels: ['#titlediv', '#mms-box'], marks: [{ sel: 'row:Typ', n: 1 }, { sel: 'row:Abfrageintervall', n: 2 }, { sel: 'row:Rücksprung', n: 3 }, { sel: 'row:Anzeige in der Pause', n: 4 }, { sel: 'row:Anzeige bei Feierabend', n: 5 }, { sel: 'row:Anzeige während', n: 6 }, { sel: 'row:Vorwarnung vor', n: 7 }, { sel: 'row:Vorankündigung', n: 8 }, { sel: 'row:Knopf', n: 9 }] });
  await go(page, catUrl('Info intern'), 1600);
  await shot(page, 'b-cat-info', { width: 1600, sels: ['#titlediv', '#mms-box'] });
  await shot(page, 'b-seq-editor', { width: 1600, sels: ['#mms-box .mms-rep-wrap'], margin: 22, marks: [
    { sel: '.mms-seq tbody tr:first-child td:nth-child(1)', n: 1 }, { sel: '.mms-seq tbody tr:first-child td:nth-child(2)', n: 2 }, { sel: '.mms-seq tbody tr:first-child td:nth-child(3)', n: 3 },
    { sel: '.mms-seq tbody tr:first-child td:nth-child(4)', n: 4 }, { sel: '.mms-seq tbody tr:first-child td:nth-child(5)', n: 5 }, { sel: '.mms-seq tbody tr:first-child td:nth-child(6)', n: 6 },
    { sel: '.mms-seq tbody tr:first-child td:nth-child(7)', n: 7, right: true }, { sel: '.mms-rep-wrap .mms-add-row', n: 8 }] });

  // ===== Displays
  await go(page, '/wp-admin/edit.php?post_type=mms_display');
  await shot(page, 'b-disp-list', { content: true });
  await go(page, edit(ids.rec_oetztal));
  await shot(page, 'b-disp-edit', { content: true, marks: [{ sel: 'row:Kennung', n: 1 }, { sel: 'row:Kategorie', n: 2 }, { sel: 'row:Standort', n: 3 }, { sel: 'row:Format', n: 4 }, { sel: 'row:Wegweiser beginnt', n: 5 }, { sel: 'text:h3|Abweichungen', n: 6 }, { sel: '#mms-display-info', n: 7, right: true }, { sel: '#mms-display-preview', n: 8 }] });
  await page.$eval('.mms-preview-at', el => el.value = '2026-12-28T10:00');
  await page.click('.mms-preview-run');
  await page.waitForSelector('.mms-preview-out table');
  await shot(page, 'b-disp-preview', { sels: ['#mms-display-preview'], margin: 10 });
  await go(page, edit(ids.info_silz), 1600);
  await shot(page, 'b-disp-info-own', { width: 1600, sels: ['.mms-scope-block[data-scope="info"]'], margin: 14 });

  // ===== Wegweiser-Kacheln
  await go(page, '/wp-admin/edit.php?post_type=mms_node');
  await shot(page, 'b-node-list', { content: true, marks: [{ sel: '.page-title-action', n: 1, right: true }, { sel: '#the-list tr:nth-child(4) .row-title', n: 2 }] });
  await go(page, edit(ids.n_konstruktiv));
  await shot(page, 'b-node-edit', { content: true, marks: [{ sel: '#titlediv #title', n: 1 }, { sel: 'row:Titel (Englisch)', n: 2, pad: 1 }, { sel: 'row:Untertitel', n: 3, pad: 1 }, { sel: 'row:Symbol', n: 4 }, { sel: 'row:Weg-Bewegtbild', n: 5 }, { sel: 'row:Wegschritte', n: 6 },
    { sel: 'row:Ansprechpartner', n: 7 }, { sel: 'row:Foto Ansprechpartner', n: 8 }, { sel: 'row:QR-Code-Ziel', n: 9 }, { sel: '#pageparentdiv', n: 10, right: true }] });
  const childUrl = await page.evaluate(async b => { const r = await fetch(b + '/wp-admin/edit.php?post_type=mms_node'); const t = await r.text(); const m = t.match(/post\.php\?post=(\d+)&amp;action=edit[^>]*>[^<]*Buchhaltung/); return m ? m[1] : null; }, L.BASE);
  await go(page, edit(childUrl));
  await shot(page, 'b-node-child', { sels: ['#pageparentdiv'], margin: 12, marks: [{ sel: '#parent_id', n: 1 }, { sel: '#menu_order', n: 2 }] });

  // ===== Grüße
  await go(page, '/wp-admin/edit.php?post_type=mms_greeting');
  await shot(page, 'b-greet-list', { content: true, marks: [{ sel: '.page-title-action', n: 1, right: true }, { sel: '.column-mms_status', n: 2, pad: 2 }] });
  await go(page, '/wp-admin/post-new.php?post_type=mms_greeting');
  await page.select('.mms-template', '0');
  await L.sleep(300);
  await shot(page, 'b-greet-template', { sels: ['#titlediv', 'row:Schnellvorlage'.startsWith('row') ? '#mms-box' : ''], maxH: 560, marks: [{ sel: '#title', n: 1 }, { sel: 'row:Schnellvorlage', n: 2 }] });
  await L.publish(page);
  await page.evaluate(() => window.scrollTo(0, 0));
  await shot(page, 'b-greet-error', { content: true, maxH: 420 });
  // fehlerhaften Gruß wieder löschen
  const badId = L.idOf(page);
  await page.goto(L.BASE + '/wp-admin/edit.php?post_type=mms_greeting', { waitUntil: 'domcontentloaded' });
  await page.evaluate(async (b, id) => { const t = await (await fetch(b + `/wp-admin/post.php?post=${id}&action=edit`)).text(); const m = t.match(/href="([^"]*action=trash[^"]*)"/); if (m) await fetch(m[1].replace(/&amp;/g, '&')); }, L.BASE, badId);
  await go(page, edit(ids.g_tischlerei));
  await shot(page, 'b-greet-edit', { content: true, marks: [{ sel: '#title', n: 1 }, { sel: 'row:Schnellvorlage', n: 2 }, { sel: 'row:Gast-Logo', n: 3 }, { sel: 'row:Begrüßungszeile', n: 4 }, { sel: 'row:Zusatzzeile', n: 5 },
    { sel: 'row:Von', n: 6 }, { sel: 'row:Mehrere Tage', n: 7 }, { sel: 'row:Anzeigen auf', n: 8 }, { sel: 'row:Vorschau', n: 9 }] });
  await page.$eval('.mms-help-compact', d => d.open = true);
  await shot(page, 'b-help-compact', { sels: ['.mms-help-compact'], margin: 10 });

  // ===== Hinweise
  await go(page, '/wp-admin/edit.php?post_type=mms_notice');
  await shot(page, 'b-notice-list', { content: true });
  await go(page, edit(ids.h_freitag));
  await shot(page, 'b-notice-edit', { content: true, marks: [{ sel: '#title', n: 1 }, { sel: 'row:Text (Englisch)', n: 2 }, { sel: 'row:Symbol', n: 3 }, { sel: 'row:Wochentage', n: 4 }, { sel: 'row:Von', n: 5 }, { sel: 'row:Bis', n: 6 },
    { sel: 'row:Gültig', n: 7 }, { sel: 'row:Anzeigen auf', n: 8 }, { sel: 'row:Priorität', n: 9 }, { sel: 'row:Aktiv', n: 10 }] });

  // ===== Vollbild-Seiten
  await go(page, '/wp-admin/edit.php?post_type=mms_fullpage');
  await shot(page, 'b-fp-list', { content: true });
  await go(page, edit(ids.fp_team));
  await shot(page, 'b-fp-edit', { content: true, marks: [{ sel: '#title', n: 1 }, { sel: '#postdivrich', n: 2 }, { sel: 'row:Zeitregel', n: 3 }, { sel: 'row:Wochentage', n: 4 }, { sel: 'row:Uhrzeit', n: 5 }, { sel: 'row:Nur an Öffnungstagen', n: 6 },
    { sel: 'row:Anzeigen auf', n: 7 }, { sel: 'row:Besucher-Knopf', n: 8 }, { sel: 'row:Priorität', n: 9 }, { sel: 'row:Vorschau', n: 10 }] });
  await go(page, edit(ids.fp_danke));
  await shot(page, 'b-fp-once', { sels: ['#mms-box'], margin: 10 });

  // ===== News
  await go(page, '/wp-admin/edit.php?post_type=mms_news');
  await shot(page, 'b-news-list', { content: true });
  const newsUrl = await page.$$eval('#the-list a.row-title', as => as.find(a => a.textContent.includes('Weihnachtsfeier')).href);
  await go(page, newsUrl.replace(L.BASE, ''));
  await shot(page, 'b-news-edit', { content: true, marks: [{ sel: '#title', n: 1 }, { sel: 'row:Text', n: 2 }, { sel: 'row:Bild', n: 3 }, { sel: 'row:Gültig', n: 4 }, { sel: 'row:Standorte', n: 5 }, { sel: 'row:Anzeige', n: 6 }] });

  // ===== Display-Seiten
  await go(page, '/wp-admin/edit.php?post_type=mms_page');
  await shot(page, 'b-page-list', { content: true, marks: [{ sel: '.column-mms_used', n: 1, pad: 2 }] });
  await go(page, edit(ids.p_sicherheit));
  await shot(page, 'b-page-edit', { content: true });

  // ===== Benutzer
  await go(page, ids.user_url.replace(L.BASE, ''));
  await shot(page, 'b-user-redaktion', { sels: ['input[name="mms_redaktion"]'], margin: 0, clip: null, prep: async () => {}, marks: [] }).catch(() => {});
  const userClip = await page.evaluate(() => { const h = [...document.querySelectorAll('h2')].find(e => e.textContent.trim() === 'Displays'); const t = h.nextElementSibling; const a = h.getBoundingClientRect(), b = t.getBoundingClientRect(); return { x: 0, y: a.top + scrollY - 10, width: 1100, height: b.bottom - a.top + 20 }; });
  await mark(page, [{ sel: 'input[name="mms_redaktion"]', n: 1, up: 'label' }]);
  if (!only || only.test('b-user-redaktion')) { await page.screenshot({ path: `${OUT}/b-user-redaktion.png`, clip: { ...userClip, x: 160, width: 1000 }, captureBeyondViewport: true }); log('b-user-redaktion'); }
  await unmark(page);
  await go(page, '/wp-admin/user-new.php');
  await shot(page, 'b-user-new', { content: true, maxH: 900, marks: [{ sel: 'row:Benutzername', n: 1 }, { sel: 'row:E-Mail', n: 2 }, { sel: 'row:Rolle', n: 3 }] });

  // ===== Plugin hochladen
  await go(page, '/wp-admin/plugin-install.php?tab=upload');
  await shot(page, 'b-plugin-upload', { content: true, maxH: 420 });

  // ===== Diagnose
  await go(page, '/?mm_signage=empfang-oetztal&diag=1&at=2026-12-02T10:00');
  await shot(page, 'b-diag', { maxH: 900, clip: { x: 0, y: 0, width: 1280, height: 900 }, fit: false });

  // ===== Redaktion: Menü und Übersicht
  const ctxR = await browser.createBrowserContext();
  const r = await ctxR.newPage();
  L.watch(r, 'redaktion', errors);
  await r.setViewport({ width: 1280, height: 900, deviceScaleFactor: DSF });
  await L.login(r, 'm.muster', 'Redaktion!2026x');
  await go(r, '/wp-admin/admin.php?page=mms');
  await shot(r, 'b-menu-redaktion', { sels: ['#toplevel_page_mms'], margin: 6 });
  await shot(r, 'b-overview-redaktion', { content: true, maxH: 560 });

  // ===== Player Empfang (Hochformat)
  const p = await L.newPage(browser, 540, 960, 2);
  L.watch(p, 'player', errors);
  const E = 'empfang-oetztal', S = 'empfang-silz', I = 'info-oetztal';
  await player(p, E, '2026-12-02T10:00', 'd-rec-start');
  await player(p, E, '2026-12-03T10:00', 'd-rec-greeting');
  await player(p, E, '2026-12-21T10:00', 's-greet-with-banners');
  await player(p, E, '2026-12-09T19:00', 'd-rec-greeting-evening');
  await player(p, E, '2026-12-02T10:01', 'd-rec-sub', q => tap(q, `[data-node="${ids.n_verwaltung}"]`));
  await player(p, E, '2026-12-02T10:02', 'd-rec-detail', q => tap(q, `[data-node="${ids.n_konstruktiv}"]`, 1500));
  await player(p, E, '2026-12-02T10:03', 'd-rec-detail-novid', q => tap(q, `[data-node="${ids.n_dekorativ}"]`));
  await player(p, E, '2026-12-02T10:04', 'd-rec-en', q => tap(q, '[data-lang="en"]'));
  await player(p, E, '2026-12-02T10:05', 'd-rec-en-detail', async q => { await tap(q, '[data-lang="en"]'); await tap(q, `[data-node="${ids.n_paket}"]`); });
  await player(p, E, '2026-12-04T13:00', 'd-rec-notice-fr');
  await player(p, E, '2026-12-15T10:00', 'd-rec-kran');
  await player(p, E, '2026-12-18T13:00', 'd-rec-3banners');
  await player(p, E, '2026-12-02T16:20', 'd-rec-warn');
  await player(p, E, '2026-12-02T18:00', 'd-rec-closed');
  await player(p, E, '2026-12-05T10:00', 'd-rec-weekend');
  await player(p, E, '2026-12-28T10:00', 'd-rec-closure');
  await player(p, E, '2026-12-02T06:58', 'd-rec-team');
  await player(p, E, '2026-12-02T06:58', 'd-rec-team-visitor', q => tap(q, '.visitor'));
  await player(p, E, '2026-12-02T18:01', 'd-rec-closed-directory', q => tap(q, '.visitor'));
  await player(p, E, '2026-12-02T10:06', 'd-rec-ring', async q => { await tap(q, `[data-node="${ids.n_dekorativ}"]`); await L.sleep(39000); });
  await player(p, S, '2026-12-02T10:00', 'd-silz-start', null, true);
  await player(p, S, '2026-12-03T10:00', 'd-silz-greeting', null, true);
  await player(p, S, '2026-12-02T12:15', 'd-silz-pause', null, true);
  await player(p, S, '2026-12-02T11:50', 'd-silz-pausewarn', null, true);
  await player(p, S, '2026-12-08T10:00', 'd-silz-holiday', null, true);
  await player(p, S, '2026-12-02T10:01', 'd-silz-detail', q => tap(q, `[data-node="${ids.n_konstruktiv}"]`, 1500), true);

  // ----- Feld → Anzeige (gleiche Nummern wie in den Backend-Masken)
  await pm(p, E, '2026-12-03T10:00', 'x-greeting', [{ sel: '.guest-card h1', n: 1 }, { sel: '.guest-logo', n: 3 }, { sel: '.guest-card .kicker', n: 4 }, { sel: '.guest-card .sub', n: 5 }, { sel: '.tiles.compact', n: 'K', right: true }]);
  await pm(p, E, '2026-12-02T10:00', 'x-header', [{ sel: '.gz .logo', n: 'A' }, { sel: '.gz .wx', n: 'B', right: true }, { sel: '.gz h1', n: 'C' }, { sel: '.gz .date', n: 'D' }, { sel: '.nv > h2', n: 'E' }, { sel: '.nv .lead', n: 'F' }, { sel: '.langs', n: 'G' }]);
  await pm(p, E, '2026-12-02T10:00', 'x-tile', [{ sel: `[data-node="${ids.n_konstruktiv}"] .tt`, n: 1 }, { sel: `[data-node="${ids.n_konstruktiv}"] .st`, n: 3 }, { sel: `[data-node="${ids.n_konstruktiv}"] .ic`, n: 4 }, { sel: `[data-node="${ids.n_konstruktiv}"] .go`, n: 'H' }, { sel: `[data-node="${ids.n_verwaltung}"] .go`, n: 'I' }]);
  await pm(p, E, '2026-12-02T10:02', 'x-detail', [{ sel: '.dhead h2', n: 1 }, { sel: '.media', n: 5 }, { sel: '.steps ol', n: 6 }, { sel: '.steps .contact', n: 7 }, { sel: '.qr', n: 9, right: true }, { sel: '.back', n: 'J' }], q => tap(q, `[data-node="${ids.n_konstruktiv}"]`, 1500));
  await pm(p, E, '2026-12-02T10:05', 'x-detail-en', [{ sel: '.dhead h2', n: 2 }], async q => { await tap(q, '[data-lang="en"]'); await tap(q, `[data-node="${ids.n_konstruktiv}"]`, 1500); });
  await pm(p, E, '2026-12-04T13:00', 'x-notice', [{ sel: '.notice .ni', n: 3 }, { sel: '.notice .nt b', n: 1 }, { sel: '.notice .nt span', n: 2 }]);
  await pm(p, E, '2026-12-02T06:58', 'x-fullpage', [{ sel: '.to-head .logo', n: 'A' }, { sel: '.to-head .dt', n: 'B', right: true }, { sel: '.takeover h1', n: 1 }, { sel: '.to-content', n: 2 }, { sel: '.visitor', n: 8 }]);
  await pm(p, E, '2026-12-28T10:00', 'x-closure', [{ sel: '.takeover h1', n: 2 }, { sel: '.takeover .hmsg', n: 5 }, { sel: '.takeover .himg', n: 6 }, { sel: '.takeover .next', n: 'L' }, { sel: '.visitor', n: 'M' }]);
  await pm(p, E, '2026-12-02T18:00', 'x-closed', [{ sel: '.takeover h1', n: 'N' }, { sel: '.takeover .next', n: 'L' }, { sel: '.oh', n: 4 }, { sel: '.visitor', n: 'M' }]);
  await pm(p, E, '2026-12-15T10:00', 'x-announce', [{ sel: '.notice:nth-of-type(2)', n: 7 }]);

  // ----- Größen: Gast-Namen und Logos
  await player(p, E, '2026-12-03T10:00', 's-greet-short-wide');
  await player(p, E, '2026-12-04T10:00', 's-greet-long-square');
  await player(p, E, '2026-12-09T19:00', 's-greet-nologo');
  await player(p, E, '2026-11-30T10:00', 's-greet-max');
  await player(p, S, '2026-11-30T10:00', 's-greet-max-quer', null, true);

  // ===== Player Info (Querformat) – kurze Dauer für die Aufnahmen
  if (!only || only.test('d-info')) {
    L.php('seqdur.php', '5');
    await player(p, I, '2026-12-02T10:00', 'd-info-grafana', null, true, 2500);
    await L.sleep(4000); await p.screenshot({ path: `${OUT}/d-info-hobelwerk.png` }); log('d-info-hobelwerk');
    await L.sleep(5000); await p.screenshot({ path: `${OUT}/d-info-page.png` }); log('d-info-page');
    await L.sleep(5000); await p.screenshot({ path: `${OUT}/d-info-news.png` }); log('d-info-news');
    await pm(p, I, '2026-12-02T10:00', 'x-info-frame', [{ sel: '.ih .logo', n: 'A' }, { sel: '#ititle b', n: 2 }, { sel: '#ititle .dots', n: 'P' }, { sel: '.ih .wx', n: 'B', right: true }, { sel: '.ibar', n: 'Q', pad: 1 }, { sel: '.it', n: 'R' }, { sel: '#slide', n: 3 }], null, true, 2500);
    await pm(p, I, '2026-12-02T10:00', 'x-news', [{ sel: '.nimg', n: 3 }, { sel: '.islide.news h2', n: 1 }, { sel: '.islide.news p', n: 2 }, { sel: '.nd', n: 4 }, { sel: '.it', n: 6 }], async q => { await L.sleep(35000); }, true, 1500);
    await player(p, I, '2026-12-15T10:00', 'd-info-notice', null, true, 2500);
    await player(p, I, '2026-12-15T10:00', 'd-info-announce', async q => { await L.sleep(15000); }, true, 1500);
    await player(p, I, '2026-12-02T07:00', 'd-info-team', null, true);
    await player(p, I, '2026-12-23T11:15', 'd-info-once', null, true);
    await player(p, 'info-silz', '2026-12-02T07:30', 'd-info-silz-termine', async q => { await L.sleep(5500); }, true, 1500);
    L.php('seqdur.php', 'restore');
  }

  // ===== Handy-Pflegemaske (Gruß-Passwort)
  if (!only || only.test('m-')) {
  L.php('cleanmask.php');
  const ctxM = await browser.createBrowserContext();
  const m = await ctxM.newPage();
  L.watch(m, 'maske', errors);
  await m.setViewport({ width: 390, height: 844, deviceScaleFactor: 2, isMobile: true, hasTouch: true });
  const mshot = async (name, full = false) => { if (only && !only.test(name)) return; await L.fixUrls(m); await m.screenshot({ path: `${OUT}/${name}.png`, fullPage: full }); log(name); };
  await m.goto(L.BASE + '/?mm_pflege=1', { waitUntil: 'networkidle2' });
  await mshot('m-login');
  await m.type('#pw', 'falsch'); await m.click('#lf .btn'); await m.waitForSelector('.err');
  await mshot('m-login-error');
  await m.type('#pw', 'Empfang2026!'); await m.click('#lf .btn'); await m.waitForSelector('#new');
  await mshot('m-list');
  await m.click('#new'); await m.waitForSelector('.tpl');
  await mshot('m-new');
  await m.click('.tpl[data-t="3"]');
  await m.$eval('#from_date', el => el.value = '2026-11-18'); await m.$eval('#from_time', el => el.value = '08:00');
  await m.$eval('#to_date', el => el.value = '2026-11-18'); await m.$eval('#to_time', el => el.value = '15:00');
  await m.click('#save'); await m.waitForSelector('#ferr .err');
  await mshot('m-placeholder');
  await m.$eval('#name', el => el.value = 'Lena und Jonas');
  await m.$eval('#name', el => el.dispatchEvent(new Event('input')));
  const [chooser] = await Promise.all([m.waitForFileChooser(), m.click('#logo-b')]);
  await chooser.accept([require('path').join(L.DATA, 'logo-fachschule.png')]);
  await m.waitForSelector('#logo-pv img', { timeout: 20000 });
  await mshot('m-form', true);
  if (!only || only.test('m-form-marked')) {
    await mark(m, [{ sel: '#name', n: 1, right: true }, { sel: '.chips', n: 2, right: true }, { sel: '#logo-pv', up: '.up', n: 3, right: true }, { sel: '#kicker_de', n: 4, right: true }, { sel: '#sub_de', n: 5, right: true },
      { sel: 'text:b|Zeitraum', up: '.box', n: 6, right: true }, { sel: '#daily', up: 'label', n: 7, right: true }, { sel: 'text:b|Anzeigen auf', up: '.box', n: 8, right: true }]);
    await m.screenshot({ path: `${OUT}/m-form-marked.png`, fullPage: true }); log('m-form-marked');
    await unmark(m);
  }
  await m.click('#save'); await m.waitForSelector('#new'); await L.sleep(400);
  await mshot('m-saved');
  await m.click('.ed'); await m.waitForSelector('#del'); await m.evaluate(() => window.scrollTo(0, document.body.scrollHeight)); await L.sleep(200);
  await mshot('m-edit-bottom');

  // ===== Pflegemaske als Redaktion
  const mr = await ctxR.newPage();
  L.watch(mr, 'maske-red', errors);
  await mr.setViewport({ width: 390, height: 844, deviceScaleFactor: 2, isMobile: true, hasTouch: true });
  const mrshot = async (name, full = false) => { if (only && !only.test(name)) return; await mr.screenshot({ path: `${OUT}/${name}.png`, fullPage: full }); log(name); };
  await mr.goto(L.BASE + '/?mm_pflege=1', { waitUntil: 'networkidle2' }); await mr.waitForSelector('.tabs');
  await mrshot('m-red-greetings');
  await mr.click('[data-tab="notices"]'); await mr.waitForSelector('#new'); await L.sleep(300);
  await mrshot('m-red-notices');
  await mr.click('#new'); await mr.waitForSelector('#text_de');
  await mr.type('#text_de', 'Heute Inventur im Plattenlager – Abholung bitte an der Theke');
  await mrshot('m-red-notice-form', true);
  await mr.click('#cx'); await mr.waitForSelector('#new');
  await mr.click('[data-tab="news"]'); await mr.waitForSelector('#new'); await L.sleep(300);
  await mrshot('m-red-news');
  }

  // Login-Seite der Maske mit Hinweis auf WP-Konto bleibt m-login
  log('Fehler:', errors.length ? '\n  ' + errors.join('\n  ') : 'keine');
  await browser.close();
})().catch(e => { console.error(e); console.log(errors.join('\n')); process.exit(1); });
