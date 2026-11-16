// Beispieldaten für das Handbuch über die echte Oberfläche anlegen.
const L = require('./lib');
const media = L.readJson('media.json');
const urls = L.readJson('media-urls.json');
const ids = {};
const errors = [];
const log = (...a) => console.log('•', ...a);

(async () => {
  const browser = await L.launch();
  const page = await browser.newPage();
  L.watch(page, 'seed', errors);
  await L.login(page, 'admin', 'admin123');

  // ---- Standorte
  const locations = [
    { key: 'oetztal', name: 'Ötztal Bahnhof', week: ['07:00-16:30', '07:00-16:30', '07:00-16:30', '07:00-16:30', '07:00-16:30', '', ''], lat: '47.2386', lon: '10.8593', open: ['mariae_empfaengnis'],
      exc: [{ date: '2026-12-23', mode: 'hours', times: '07:00-12:00', label: 'Kurzer Arbeitstag vor Weihnachten', announce: '5' }] },
    { key: 'silz', name: 'Silz', week: ['07:00-12:00, 13:00-17:00', '07:00-12:00, 13:00-17:00', '07:00-12:00, 13:00-17:00', '07:00-12:00, 13:00-17:00', '07:00-12:00', '', ''], lat: '47.2669', lon: '10.9306', open: [], exc: [] },
  ];
  for (const l of locations) {
    await L.newPost(page, 'mms_location');
    await page.type('#title', l.name);
    for (let d = 1; d <= 7; d++) if (l.week[d - 1]) await page.type(`input[name="mms[weekly][${d}]"]`, l.week[d - 1]);
    await page.type('input[name="mms[lat]"]', l.lat);
    await page.type('input[name="mms[lon]"]', l.lon);
    await L.setVal(page, 'input[name="mms[logo]"]', String(media['marberger-logo']));
    for (const k of l.open) { const el = await page.$(`input[name="mms[holiday_open][]"][value="${k}"]`); if (el) await L.check(page, `input[name="mms[holiday_open][]"][value="${k}"]`); else log('Feiertag-Schlüssel fehlt:', k); }
    for (const [i, e] of l.exc.entries()) {
      await page.click('.mms-add-row');
      await L.setVal(page, `input[name="mms[exceptions][${i}][date]"]`, e.date);
      await page.select(`select[name="mms[exceptions][${i}][mode]"]`, e.mode);
      await page.type(`input[name="mms[exceptions][${i}][times]"]`, e.times);
      await page.type(`input[name="mms[exceptions][${i}][label]"]`, e.label);
      await L.setVal(page, `input[name="mms[exceptions][${i}][announce_days]"]`, e.announce);
    }
    await L.publish(page);
    ids['loc_' + l.key] = L.idOf(page);
    log('Standort', l.name, ids['loc_' + l.key], (await L.notices(page)).join(' | ').slice(0, 120));
  }

  // ---- Schließzeit
  await L.newPost(page, 'mms_closure');
  await page.type('#title', 'Betriebsurlaub Weihnachten 2026');
  await L.setVal(page, 'input[name="mms[from]"]', '2026-12-24');
  await L.setVal(page, 'input[name="mms[to]"]', '2027-01-06');
  await L.check(page, `input[name="mms[locations][]"][value="${ids.loc_oetztal}"]`);
  await L.check(page, `input[name="mms[locations][]"][value="${ids.loc_silz}"]`);
  await page.type('textarea[name="mms[text_de]"]', 'Wir wünschen Ihnen frohe Weihnachten und einen guten Rutsch ins neue Jahr!');
  await page.type('textarea[name="mms[text_en]"]', 'We wish you a merry Christmas and a happy New Year!');
  await L.setVal(page, 'input[name="mms[image]"]', String(media['schliesszeit-winter']));
  await L.publish(page);
  ids.closure = L.idOf(page);

  // ---- Displays
  const cat = async n => page.$$eval('select[name="mms[category]"] option', (os, n) => os.find(o => o.textContent === n).value, n);
  const displays = [
    ['rec_oetztal', 'Empfang Ötztal Bahnhof', 'empfang-oetztal', 'Empfang', 'loc_oetztal', 'hoch'],
    ['info_oetztal', 'Info Ötztal Bahnhof', 'info-oetztal', 'Info intern', 'loc_oetztal', 'quer'],
    ['rec_silz', 'Empfang Silz', 'empfang-silz', 'Empfang', 'loc_silz', 'quer'],
    ['info_silz', 'Info Silz Werkhalle', 'info-silz', 'Info intern', 'loc_silz', 'quer'],
  ];
  for (const [k, title, key, c, loc, fmt] of displays) {
    await L.newPost(page, 'mms_display');
    await page.type('#title', title);
    await page.type('input[name="mms[key]"]', key);
    await page.select('select[name="mms[category]"]', await cat(c));
    await page.select('select[name="mms[location]"]', ids[loc]);
    await page.select('select[name="mms[format]"]', fmt);
    await L.publish(page);
    ids[k] = L.idOf(page);
  }
  log('Displays', JSON.stringify(ids));

  // ---- Einstellungen: Gruß-Passwort
  await page.goto(L.BASE + '/wp-admin/admin.php?page=mms-settings', { waitUntil: 'domcontentloaded' });
  await L.setVal(page, 'input[name="mms_set[greet_pw]"]', 'Empfang2026!');
  await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), page.$eval('#submit', b => b.form.requestSubmit(b))]);

  // ---- Wegweiser
  const nodes = [
    { k: 'n_konstruktiv', t: 'Verkauf konstruktiv', en: 'Construction timber', sub: 'Holzbau, Platten, Bauholz', sub_en: 'Timber construction, panels', icon: 'construction',
      steps: 'Durch die Glastür geradeaus\nNach dem Lager links abbiegen\nTheke „Verkauf konstruktiv“', steps_en: 'Straight through the glass door\nTurn left after the warehouse\nCounter “Construction timber”', contact: 'Team Verkauf konstruktiv', ext: '20', media: media['weg-verkauf-konstruktiv'], qr: 'https://www.holz-marberger.at/' },
    { k: 'n_dekorativ', t: 'Verkauf dekorativ', en: 'Interior products', sub: 'Böden, Türen, Innenausbau', sub_en: 'Flooring, doors, interiors', icon: 'decor',
      steps: 'Treppe ins 1. Obergeschoß\nSchauraum rechts\nBeratung an der Theke', steps_en: 'Stairs to the 1st floor\nShowroom on the right\nAdvice at the counter', contact: 'Team Verkauf dekorativ', ext: '30' },
    { k: 'n_verwaltung', t: 'Verwaltung', en: 'Administration', sub: 'Buchhaltung, Personal, Geschäftsführung', sub_en: 'Accounting, HR, management', icon: 'office' },
    { k: 'n_paket', t: 'Paketablieferung mit Unterschrift', en: 'Parcel delivery (signature)', sub: 'Bitte läuten – wir kommen', sub_en: 'Please ring – we will come', icon: 'parcel',
      steps: 'Klingel rechts neben der Tür drücken\nBitte kurz warten – wir kommen zu Ihnen', steps_en: 'Press the bell to the right of the door\nPlease wait a moment – we will come to you' },
  ];
  for (const [i, n] of nodes.entries()) {
    await L.newPost(page, 'mms_node');
    await page.type('#title', n.t);
    await page.type('input[name="mms[title_en]"]', n.en);
    await page.type('input[name="mms[sub_de]"]', n.sub);
    await page.type('input[name="mms[sub_en]"]', n.sub_en);
    await page.select('select[name="mms[icon]"]', n.icon);
    if (n.steps) { await page.type('textarea[name="mms[steps_de]"]', n.steps); await page.type('textarea[name="mms[steps_en]"]', n.steps_en); }
    if (n.contact) { await page.type('input[name="mms[contact]"]', n.contact); await page.type('input[name="mms[ext]"]', n.ext); }
    if (n.media) await L.setVal(page, 'input[name="mms[media]"]', String(n.media));
    if (n.qr) await page.type('input[name="mms[qr_url]"]', n.qr);
    await L.setVal(page, '#menu_order', String((i + 1) * 10));
    await L.publish(page);
    ids[n.k] = L.idOf(page);
  }
  const subs = [['Buchhaltung', 'Accounting', 'Treppe ins 1. Obergeschoß\nZweite Tür links', 'Team Buchhaltung', '40'],
    ['Personalbüro', 'Human resources', 'Treppe ins 1. Obergeschoß\nDritte Tür links', 'Personalbüro', '45'],
    ['Geschäftsführung', 'Management', 'Treppe ins 1. Obergeschoß\nEnde des Gangs, Sekretariat', 'Sekretariat', '10']];
  for (const [i, c] of subs.entries()) {
    await L.newPost(page, 'mms_node');
    await page.type('#title', c[0]);
    await page.type('input[name="mms[title_en]"]', c[1]);
    await page.select('select[name="mms[icon]"]', 'person');
    await page.type('textarea[name="mms[steps_de]"]', c[2]);
    await page.type('input[name="mms[contact]"]', c[3]);
    await page.type('input[name="mms[ext]"]', c[4]);
    await page.select('#parent_id', ids.n_verwaltung);
    await L.setVal(page, '#menu_order', String((i + 1) * 10));
    await L.publish(page);
  }

  // ---- Grüße
  const greet = async (tpl, name, from, to, logo, extra) => {
    await L.newPost(page, 'mms_greeting');
    if (tpl !== null) await page.select('.mms-template', String(tpl));
    await page.$eval('#title', (el, v) => { el.value = v; el.dispatchEvent(new Event('input', { bubbles: true })); }, name);
    await L.setVal(page, 'input[name="mms[from_date]"]', from[0]);
    await L.setVal(page, 'input[name="mms[from_time]"]', from[1]);
    await L.setVal(page, 'input[name="mms[to_date]"]', to[0]);
    await L.setVal(page, 'input[name="mms[to_time]"]', to[1]);
    if (logo) await L.setVal(page, 'input[name="mms[logo]"]', String(logo));
    if (extra) await extra();
    await L.publish(page);
    return L.idOf(page);
  };
  ids.g_tischlerei = await greet(1, 'Tischlerei Muster GmbH', ['2026-12-21', '08:00'], ['2026-12-21', '17:00'], media['logo-tischlerei-muster']);
  ids.g_fachschule = await greet(0, 'Fachschule Holztechnik Musterort – 3. Jahrgang', ['2026-12-22', '08:30'], ['2026-12-22', '12:00'], media['logo-fachschule']);
  ids.g_abend = await greet(2, 'Holzbau-Stammtisch Ötztal', ['2026-12-17', '18:00'], ['2026-12-17', '22:00'], 0, async () => {
    await page.$eval('input[name="mms[sub_de]"]', el => el.value = 'Herzlich willkommen zum Stammtisch – Treffpunkt im Schauraum.');
  });

  // ---- Hinweise
  const notice = async (text, en, icon, days, from, untilClose, to, vf, vt, disp, prio) => {
    await L.newPost(page, 'mms_notice');
    await page.type('#title', text);
    await page.type('input[name="mms[text_en]"]', en);
    await page.select('select[name="mms[icon]"]', icon);
    for (let d = 1; d <= 7; d++) await L.check(page, `input[name="mms[days][]"][value="${d}"]`, days.includes(d));
    await L.setVal(page, 'input[name="mms[from]"]', from);
    await L.check(page, 'input[name="mms[until_close]"]', untilClose);
    if (!untilClose) await L.setVal(page, 'input[name="mms[to]"]', to);
    if (vf) { await L.setVal(page, 'input[name="mms[valid_from]"]', vf); await L.setVal(page, 'input[name="mms[valid_to]"]', vt); }
    for (const d of disp) await L.check(page, `input[name="mms[displays][]"][value="${ids[d]}"]`);
    await L.setVal(page, 'input[name="mms[priority]"]', String(prio));
    await L.publish(page);
    return L.idOf(page);
  };
  ids.h_freitag = await notice('Freitag ab 12 Uhr: Eingang rechts über die Haupteinfahrt', 'Friday from 12 noon: please use the main gate on the right', 'right', [5], '12:00', true, '', '', '', ['rec_oetztal', 'info_oetztal'], 0);
  ids.h_kran = await notice('Hofeinfahrt wegen Kranarbeiten gesperrt – bitte Parkplatz Nord benützen', 'Yard entrance closed due to crane work – please use the north car park', 'warn', [1, 2, 3, 4, 5], '07:00', true, '', '2026-12-14', '2026-12-18', ['rec_oetztal', 'info_oetztal'], 5);

  // ---- Vollbild-Seiten
  await L.newPost(page, 'mms_fullpage');
  await page.type('#title', 'Guten Morgen, liebes Team!');
  await L.content(page, `<p>Heute um 09:00 Uhr: <strong>Sicherheitsunterweisung</strong> im Besprechungsraum.</p><p>Schönen Arbeitstag!</p><p><img class="aligncenter size-large wp-image-${media['team-holzstapel']}" src="${urls['team-holzstapel_large']}" alt="" width="1024" height="512" /></p>`);
  await L.check(page, 'input[name="mms[open_days_only]"]');
  await L.check(page, `input[name="mms[displays][]"][value="${ids.rec_oetztal}"]`);
  await L.check(page, `input[name="mms[displays][]"][value="${ids.info_oetztal}"]`);
  await L.check(page, 'input[name="mms[visitor_button]"]');
  await L.publish(page);
  ids.fp_team = L.idOf(page);

  await L.newPost(page, 'mms_fullpage');
  await page.type('#title', 'Danke für 2026!');
  await L.content(page, '<p>Vor dem Betriebsurlaub ein herzliches Dankeschön an alle für den Einsatz in diesem Jahr.</p><p><strong>Imbiss ab 11:30 Uhr in der Kantine.</strong></p>');
  await page.select('select[name="mms[rule_type]"]', 'once');
  await L.setVal(page, 'input[name="mms[once_from_date]"]', '2026-12-23');
  await L.setVal(page, 'input[name="mms[once_from_time]"]', '11:00');
  await L.setVal(page, 'input[name="mms[once_to_date]"]', '2026-12-23');
  await L.setVal(page, 'input[name="mms[once_to_time]"]', '12:00');
  await L.check(page, `input[name="mms[displays][]"][value="${ids.info_oetztal}"]`);
  await L.check(page, `input[name="mms[displays][]"][value="${ids.info_silz}"]`);
  await L.check(page, 'input[name="mms[visitor_button]"]', false);
  await L.setVal(page, 'input[name="mms[priority]"]', '5');
  await L.publish(page);
  ids.fp_danke = L.idOf(page);

  // ---- News
  const news = async (title, text, img, from, to, sites, slide, ticker) => {
    await L.newPost(page, 'mms_news');
    await page.type('#title', title);
    if (text) await page.type('textarea[name="mms[text]"]', text);
    if (img) await L.setVal(page, 'input[name="mms[image]"]', String(img));
    await L.setVal(page, 'input[name="mms[from]"]', from);
    await L.setVal(page, 'input[name="mms[to]"]', to);
    for (const s of sites) await L.check(page, `input[name="mms[sites][]"][value="${ids[s]}"]`);
    await L.check(page, 'input[name="mms[slide]"]', slide);
    await L.check(page, 'input[name="mms[ticker]"]', ticker);
    await L.publish(page);
  };
  await news('Weihnachtsfeier am Freitag, 18. Dezember', 'Ab 16:00 Uhr im Gasthof Post. Bitte bis Mittwoch in der Verwaltung anmelden.', media['news-weihnachtsfeier'], '2026-12-01', '2026-12-18', ['loc_oetztal', 'loc_silz'], true, true);
  await news('Neuer Plattenaufteiler ab Jänner in Betrieb', '', 0, '2026-12-01', '2027-01-31', ['loc_oetztal'], false, true);
  await news('Grippeimpfung im Betrieb am 10. Dezember', 'Die Betriebsärztin ist von 08:00 bis 12:00 Uhr im Erste-Hilfe-Raum. Anmeldung im Personalbüro.', 0, '2026-12-01', '2026-12-10', ['loc_oetztal', 'loc_silz'], true, false);

  // ---- Display-Seiten
  await L.newPost(page, 'mms_page');
  await page.type('#title', 'Sicherheit geht vor');
  await L.content(page, '<h2>Unfallfreie Tage</h2><p style="font-size:140px;font-weight:700;color:#006729;margin:0;line-height:1.1">128</p><p>Danke für eure Aufmerksamkeit – Schutzausrüstung tragen, Staplerwege freihalten!</p>');
  await L.publish(page);
  ids.p_sicherheit = L.idOf(page);
  await L.newPost(page, 'mms_page');
  await page.type('#title', 'Termine im Dezember');
  await L.content(page, '<h2>Termine im Dezember</h2><ul><li><strong>10.12.</strong> Grippeimpfung, 08:00–12:00</li><li><strong>18.12.</strong> Weihnachtsfeier, ab 16:00</li><li><strong>23.12.</strong> letzter Arbeitstag, bis 12:00</li><li><strong>7.1.</strong> Arbeitsbeginn 07:00</li></ul>');
  await L.publish(page);
  ids.p_termine = L.idOf(page);

  // ---- Standard-Abfolge der Kategorie „Info intern“
  await page.goto(L.BASE + '/wp-admin/edit.php?post_type=mms_category', { waitUntil: 'domcontentloaded' });
  const infoCat = await page.$$eval('#the-list a.row-title', as => as.find(a => a.textContent.trim() === 'Info intern').href);
  await page.goto(infoCat, { waitUntil: 'domcontentloaded' });
  const seq = [['url', 'Zuschnitt', L.GRAFANA + '/d/zuschnitt?orgId=1&kiosk', '', '20'], ['url', 'Hobelwerk', L.GRAFANA + '/d/hobelwerk?orgId=1&kiosk', '', '20'],
    ['page', '', '', ids.p_sicherheit, '15'], ['news', 'News', '', '', '15']];
  for (let i = 0; i < seq.length; i++) await page.click('.mms-rep-wrap .mms-add-row');
  for (const [i, s] of seq.entries()) {
    await page.select(`select[name="mms[sequence][${i}][type]"]`, s[0]);
    if (s[1]) await page.type(`input[name="mms[sequence][${i}][title]"]`, s[1]);
    if (s[2]) await page.type(`input[name="mms[sequence][${i}][url]"]`, s[2]);
    if (s[3]) await page.select(`select[name="mms[sequence][${i}][page]"]`, s[3]);
    await L.setVal(page, `input[name="mms[sequence][${i}][duration]"]`, s[4]);
  }
  await L.publish(page);
  log('Kategorie-Abfolge', (await page.$$eval('.mms-seq-type', s => s.map(x => x.value))).join(','));

  // ---- Eigene Abfolge Info Silz
  await page.goto(L.BASE + `/wp-admin/post.php?post=${ids.info_silz}&action=edit`, { waitUntil: 'domcontentloaded' });
  await L.check(page, 'input[name="mms[sequence_own]"]');
  for (let i = 0; i < 3; i++) await page.click('.mms-own-seq .mms-add-row');
  await page.select('select[name="mms[sequence][0][type]"]', 'url');
  await page.type('input[name="mms[sequence][0][title]"]', 'Hobelwerk');
  await page.type('input[name="mms[sequence][0][url]"]', L.GRAFANA + '/d/hobelwerk?orgId=1&kiosk');
  await page.select('select[name="mms[sequence][1][type]"]', 'page');
  await page.select('select[name="mms[sequence][1][page]"]', ids.p_termine);
  for (const d of [1, 2, 3, 4, 5]) await L.check(page, `input[name="mms[sequence][1][days][]"][value="${d}"]`);
  await L.setVal(page, 'input[name="mms[sequence][1][from]"]', '06:00');
  await L.setVal(page, 'input[name="mms[sequence][1][to]"]', '09:00');
  await page.select('select[name="mms[sequence][2][type]"]', 'news');
  await page.type('input[name="mms[sequence][2][title]"]', 'News');
  await L.publish(page);

  // ---- Redaktion
  await page.goto(L.BASE + '/wp-admin/user-new.php', { waitUntil: 'domcontentloaded' });
  await page.type('#user_login', 'm.muster');
  await page.type('#email', 'maria.muster@example.com');
  await page.type('#first_name', 'Maria');
  await page.type('#last_name', 'Muster');
  await page.$eval('#pass1', el => { el.value = 'Redaktion!2026x'; el.dispatchEvent(new Event('input', { bubbles: true })); });
  if (await page.$('.pw-checkbox')) await page.$eval('.pw-checkbox', c => c.checked = true);
  await page.select('#role', 'subscriber');
  await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), page.$eval('#createusersub', b => b.form.requestSubmit(b))]);
  await page.goto(L.BASE + '/wp-admin/users.php?s=muster', { waitUntil: 'domcontentloaded' });
  const editUrl = await page.$eval('#the-list a[href*="user-edit.php"]', a => a.href);
  ids.user_url = editUrl;
  await page.goto(editUrl, { waitUntil: 'domcontentloaded' });
  await page.select('#display_name', 'Maria Muster').catch(() => {});
  await L.check(page, 'input[name="mms_redaktion"]');
  await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), page.$eval('#submit', b => b.form.requestSubmit(b))]);

  L.writeJson('ids.json', ids);
  log('IDs', JSON.stringify(ids));
  log('Fehler:', errors.length ? '\n  ' + errors.join('\n  ') : 'keine');
  await browser.close();
})().catch(e => { console.error(e); console.log(errors.join('\n')); process.exit(1); });
