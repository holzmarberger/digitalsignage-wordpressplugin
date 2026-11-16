/* Marberger Signage – Handy-Pflegemaske (Grüße, Hinweise, News). Vanilla JS. */
(function () {
  'use strict';

  var cfg = JSON.parse(document.getElementById('mms-config').textContent);
  var app = document.getElementById('app');
  var TOKEN_KEY = 'mms_greet_token';
  var S = { token: '', boot: null, tab: 'greetings', items: [], edit: null, busy: false };
  var DAYS = [['1', 'Mo'], ['2', 'Di'], ['3', 'Mi'], ['4', 'Do'], ['5', 'Fr'], ['6', 'Sa'], ['7', 'So']];
  var PH = /\[[^\]]+\]/;

  try { S.token = localStorage.getItem(TOKEN_KEY) || ''; } catch (e) { S.token = ''; }

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function $(sel) { return app.querySelector(sel); }
  function $all(sel) { return Array.prototype.slice.call(app.querySelectorAll(sel)); }
  function pad(n) { return (n < 10 ? '0' : '') + n; }
  function today(offsetDays) {
    var d = new Date(Date.now() + (offsetDays || 0) * 864e5);
    return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
  }
  function nowTime() { var d = new Date(); return pad(d.getHours()) + ':' + pad(Math.floor(d.getMinutes() / 15) * 15); }
  function fmtDate(s) { if (!s) return ''; var p = s.split(' ')[0].split('-'); return p[2] + '.' + p[1] + '.' + (s.indexOf(' ') > -1 ? ' ' + s.split(' ')[1] : ''); }

  function toast(msg) {
    var t = document.createElement('div');
    t.className = 'toast';
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(function () { t.parentNode && t.parentNode.removeChild(t); }, 3500);
  }

  function api(method, path, body, isForm) {
    var h = {};
    if (cfg.nonce) h['X-WP-Nonce'] = cfg.nonce;
    if (S.token) h['X-MMS-Token'] = S.token;
    if (body && !isForm) h['Content-Type'] = 'application/json';
    var url = cfg.rest + path;
    return fetch(url, { method: method, credentials: 'same-origin', headers: h, body: body ? (isForm ? body : JSON.stringify(body)) : undefined })
      .then(function (r) {
        return r.json().catch(function () { return {}; }).then(function (j) {
          if (r.ok) return j;
          var err = new Error(j.message || ('Fehler ' + r.status));
          err.status = r.status;
          throw err;
        });
      });
  }

  function handleAuthError(e) {
    if ((e.status === 401 || e.status === 403) && !cfg.editor) {
      S.token = '';
      try { localStorage.removeItem(TOKEN_KEY); } catch (x) {}
      renderLogin('Die Anmeldung ist abgelaufen oder das Passwort wurde geändert. Bitte neu anmelden.');
      return true;
    }
    return false;
  }

  // ------------------------------------------------------------ Anmeldung
  function renderLogin(msg) {
    app.innerHTML = '<div class="login"><img src="' + esc(cfg.logo) + '" alt="Holz Marberger"><h2>Handy-Pflegemaske</h2><div style="color:var(--muted)">Grüße am Empfangsdisplay</div>' +
      '<form id="lf">' + (msg ? '<div class="err">' + esc(msg) + '</div>' : '') +
      '<label class="l" for="pw">Gruß-Passwort</label><input type="password" id="pw" autocomplete="current-password" required>' +
      '<button class="btn" type="submit">Anmelden</button></form>' +
      '<p class="hint" style="margin-top:26px">Display-Redaktion? <a href="' + esc(cfg.loginUrl) + '">Mit WordPress-Konto anmelden</a> – dann sind auch Hinweise und News bearbeitbar.</p></div>';
    $('#lf').addEventListener('submit', function (ev) {
      ev.preventDefault();
      var btn = $('#lf .btn');
      btn.disabled = true;
      api('POST', 'login', { password: $('#pw').value }).then(function (r) {
        S.token = r.token;
        try { localStorage.setItem(TOKEN_KEY, r.token); } catch (e) {}
        start();
      }).catch(function (e) { renderLogin(e.message); });
    });
  }

  function logout() {
    S.token = '';
    try { localStorage.removeItem(TOKEN_KEY); } catch (e) {}
    if (cfg.logoutUrl) { location.href = cfg.logoutUrl; return; }
    renderLogin('');
  }

  function start() {
    api('GET', 'bootstrap').then(function (b) {
      S.boot = b;
      if (!b.editor) S.tab = 'greetings';
      loadList();
    }).catch(function (e) { if (!handleAuthError(e)) renderLogin(e.message); });
  }

  // ------------------------------------------------------------ Liste
  function shell(inner) {
    var tabs = S.boot.editor ? '<nav class="tabs">' + [['greetings', 'Grüße'], ['notices', 'Hinweise'], ['news', 'News']].map(function (t) {
      return '<button data-tab="' + t[0] + '" class="' + (S.tab === t[0] ? 'on' : '') + '">' + t[1] + '</button>';
    }).join('') + '</nav>' : '';
    app.innerHTML = '<header class="top"><h1>' + (cfg.user ? esc(cfg.user) : 'Grüße') + '</h1><button id="lo">Abmelden</button></header>' + tabs + '<div class="body">' + inner + '</div>';
    $('#lo').addEventListener('click', logout);
    $all('[data-tab]').forEach(function (b) {
      b.addEventListener('click', function () { S.tab = b.getAttribute('data-tab'); loadList(); });
    });
  }

  function loadList() {
    shell('<p class="empty">Lade …</p>');
    api('GET', S.tab).then(function (items) { S.items = items; renderList(); })
      .catch(function (e) { if (!handleAuthError(e)) shell('<div class="err">' + esc(e.message) + '</div>'); });
  }

  function statusChip(s) {
    var cls = s === 'läuft jetzt' || s === 'läuft' || s === 'aktiv' ? 'run' : (s === 'geplant' || s === 'heute später' ? 'plan' : (s === 'unvollständig' ? 'bad' : ''));
    return '<span class="chipst ' + cls + '">' + esc(s) + '</span>';
  }

  function renderList() {
    var label = { greetings: '+ Neuer Gruß', notices: '+ Neuer Hinweis', news: '+ Neue News' }[S.tab];
    var rows = S.items.map(function (it, i) {
      var th, nm, zt;
      if (S.tab === 'greetings') {
        th = it.logo_url ? '<img src="' + esc(it.logo_url) + '" alt="">' : 'kein Logo';
        nm = it.name;
        zt = fmtDate(it.from) + ' – ' + fmtDate(it.to) + (it.daily ? ' · täglich' : '');
      } else if (S.tab === 'notices') {
        th = '<b style="font-size:20px">' + ({ right: '→', left: '←', up: '↑', warn: '!', info: 'i' }[it.icon] || 'i') + '</b>';
        nm = it.text_de;
        zt = (it.days || []).map(function (d) { return DAYS[d - 1] ? DAYS[d - 1][1] : ''; }).join(' ') + ' ' + (it.from || '') + '–' + (it.to === 'close' ? 'Geschäftsschluss' : (it.to || ''));
      } else {
        th = it.image_url ? '<img src="' + esc(it.image_url) + '" alt="">' : 'News';
        nm = it.title;
        zt = fmtDate(it.from) + ' – ' + fmtDate(it.to) + ' · ' + [it.slide ? 'Folie' : '', it.ticker ? 'Laufband' : ''].filter(Boolean).join(' + ');
      }
      return '<div class="card"><div class="th">' + th + '</div><div class="in"><div class="nm">' + esc(nm) + '</div><div class="zt">' + esc(zt) + '</div>' + statusChip(it.status) + '</div><button class="ed" data-i="' + i + '">Bearbeiten</button></div>';
    }).join('');
    shell('<button class="btn" id="new" style="margin:0 0 16px">' + label + '</button>' + (rows || '<p class="empty">Noch keine Einträge.</p>'));
    $('#new').addEventListener('click', function () { S.edit = null; renderForm(); });
    $all('.ed').forEach(function (b) {
      b.addEventListener('click', function () { S.edit = S.items[parseInt(b.getAttribute('data-i'), 10)]; renderForm(); });
    });
  }

  // ------------------------------------------------------------ Formular-Bausteine
  function field(id, label, value, type, extra) {
    return '<label class="l" for="' + id + '">' + label + '</label><input type="' + (type || 'text') + '" id="' + id + '" value="' + esc(value) + '"' + (extra || '') + '>';
  }
  function checks(name, list, selected) {
    return list.map(function (o) {
      return '<label class="cbx"><input type="checkbox" name="' + name + '" value="' + o.id + '"' + (selected.indexOf(o.id) > -1 ? ' checked' : '') + '> ' + esc(o.name) + '</label>';
    }).join('') || '<p class="hint">Keine vorhanden.</p>';
  }
  function checked(name) { return $all('input[name="' + name + '"]:checked').map(function (i) { return parseInt(i.value, 10); }); }
  function dayChips(sel) {
    return '<div class="chips">' + DAYS.map(function (d) {
      return '<button type="button" class="chip day' + (sel.indexOf(parseInt(d[0], 10)) > -1 ? ' on' : '') + '" data-d="' + d[0] + '">' + d[1] + '</button>';
    }).join('') + '</div>';
  }
  function uploadBox(id, url, label) {
    return '<label class="l">' + label + '</label><div class="up"><div class="pv" id="' + id + '-pv">' + (url ? '<img src="' + esc(url) + '" alt="">' : 'kein Bild') + '</div>' +
      '<div><input type="file" id="' + id + '-f" accept="image/*" style="display:none"><button type="button" class="chip" id="' + id + '-b">Foto wählen</button> ' +
      '<button type="button" class="chip" id="' + id + '-x"' + (url ? '' : ' style="display:none"') + '>entfernen</button><div class="hint">JPG, PNG oder iPhone-Foto (HEIC)</div></div></div>' +
      '<input type="hidden" id="' + id + '" value="">';
  }
  function bindUpload(id, current) {
    $('#' + id).value = current || '';
    $('#' + id + '-b').addEventListener('click', function () { $('#' + id + '-f').click(); });
    $('#' + id + '-x').addEventListener('click', function () {
      $('#' + id).value = '';
      $('#' + id + '-pv').innerHTML = 'kein Bild';
      this.style.display = 'none';
    });
    $('#' + id + '-f').addEventListener('change', function () {
      if (!this.files[0]) return;
      var fd = new FormData();
      fd.append('file', this.files[0]);
      $('#' + id + '-pv').innerHTML = 'lädt …';
      api('POST', 'upload', fd, true).then(function (r) {
        $('#' + id).value = r.id;
        $('#' + id + '-pv').innerHTML = '<img src="' + esc(r.url) + '" alt="">';
        $('#' + id + '-x').style.display = '';
      }).catch(function (e) {
        $('#' + id + '-pv').innerHTML = 'Fehler';
        if (!handleAuthError(e)) toast(e.message);
      });
    });
  }
  function formShell(title, inner) {
    app.innerHTML = '<header class="top"><h1>' + esc(title) + '</h1><button id="cx">Abbrechen</button></header><form class="body" id="f" novalidate><div id="ferr"></div>' + inner +
      '<button class="btn" type="submit" id="save">Speichern</button>' + (S.edit ? '<button class="btn del" type="button" id="del">Löschen</button>' : '') + '</form>';
    $('#cx').addEventListener('click', renderList);
    $all('.chip.day').forEach(function (c) { c.addEventListener('click', function () { c.classList.toggle('on'); }); });
    if (S.edit) {
      $('#del').addEventListener('click', function () {
        if (!confirm('Wirklich löschen?')) return;
        api('DELETE', S.tab + '/' + S.edit.id).then(function () { toast('Gelöscht.'); loadList(); })
          .catch(function (e) { if (!handleAuthError(e)) toast(e.message); });
      });
    }
  }
  function showErrors(list) {
    $('#ferr').innerHTML = list.length ? '<div class="err">' + list.map(esc).join('<br>') + '</div>' : '';
    if (list.length) window.scrollTo(0, 0);
  }
  function submit(payload, clientErrors) {
    if (clientErrors.length) { showErrors(clientErrors); return; }
    var btn = $('#save');
    btn.disabled = true;
    btn.textContent = 'Speichere …';
    api('POST', S.tab + (S.edit ? '/' + S.edit.id : ''), payload).then(function () {
      toast('Gespeichert.');
      loadList();
    }).catch(function (e) {
      btn.disabled = false;
      btn.textContent = 'Speichern';
      if (!handleAuthError(e)) showErrors([e.message]);
    });
  }
  function val(id) { var el = $('#' + id); return el ? el.value.trim() : ''; }
  function split(dt) { return dt ? dt.split(' ') : ['', '']; }

  // ------------------------------------------------------------ Gruß
  function greetingForm() {
    var g = S.edit || { name: '', kicker_de: 'Herzlich willkommen', sub_de: '', kicker_en: '', sub_en: '', logo: 0, logo_url: '', from: today() + ' ' + nowTime(), to: today() + ' 17:00', daily: false,
      displays: S.boot.displays_reception.map(function (d) { return d.id; }) };
    var f = split(g.from), t = split(g.to);
    var tpl = S.edit ? '' : '<label class="l">Schnellvorlage</label><div class="chips">' + S.boot.templates.map(function (x, i) {
      return '<button type="button" class="chip tpl" data-t="' + i + '">' + esc(x.label) + '</button>';
    }).join('') + '</div>';
    formShell(S.edit ? 'Gruß bearbeiten' : 'Neuer Gruß', tpl +
      '<label class="l" for="name">Name / Gruppe <span class="counter" id="cnt"></span></label><input type="text" id="name" maxlength="' + S.boot.name_max + '" value="' + esc(g.name) + '">' +
      field('kicker_de', 'Begrüßungszeile', g.kicker_de) + field('sub_de', 'Zusatzzeile (optional)', g.sub_de) +
      '<details class="box"' + (g.kicker_en || g.sub_en ? ' open' : '') + '><summary>Englisch (optional)</summary>' + field('kicker_en', 'Begrüßungszeile (Englisch)', g.kicker_en) + field('sub_en', 'Zusatzzeile (Englisch)', g.sub_en) + '</details>' +
      uploadBox('logo', g.logo_url, 'Gast-Logo (optional)') +
      '<div class="box"><b>Zeitraum</b><div class="row2">' + field('from_date', 'Von (Tag)', f[0], 'date') + field('from_time', 'Uhrzeit', f[1], 'time') + '</div>' +
      '<div class="row2">' + field('to_date', 'Bis (Tag)', t[0], 'date') + field('to_time', 'Uhrzeit', t[1], 'time') + '</div>' +
      '<label class="cbx"><input type="checkbox" id="daily"' + (g.daily ? ' checked' : '') + '> nur täglich von Start- bis Endzeit (bei mehreren Tagen)</label></div>' +
      '<div class="box"><b>Anzeigen auf</b>' + checks('displays', S.boot.displays_reception, g.displays || []) + '</div>');
    bindUpload('logo', g.logo);
    var cnt = function () { $('#cnt').textContent = $('#name').value.length + ' / ' + S.boot.name_max; };
    $('#name').addEventListener('input', cnt);
    cnt();
    $all('.tpl').forEach(function (b) {
      b.addEventListener('click', function () {
        var x = S.boot.templates[parseInt(b.getAttribute('data-t'), 10)];
        ['name', 'kicker_de', 'sub_de', 'kicker_en', 'sub_en'].forEach(function (k) { if ($('#' + k)) $('#' + k).value = x[k] || ''; });
        if (x.kicker_en || x.sub_en) $('details.box').open = true;
        cnt();
        var m = PH.exec($('#name').value);
        if (m) { $('#name').focus(); $('#name').setSelectionRange(m.index, m.index + m[0].length); }
      });
    });
    $('#f').addEventListener('submit', function (ev) {
      ev.preventDefault();
      var p = { name: val('name'), kicker_de: val('kicker_de'), sub_de: val('sub_de'), kicker_en: val('kicker_en'), sub_en: val('sub_en'), logo: parseInt(val('logo') || 0, 10),
        from_date: val('from_date'), from_time: val('from_time'), to_date: val('to_date'), to_time: val('to_time'), daily: $('#daily').checked, displays: checked('displays') };
      var errs = [];
      if (!p.name) errs.push('Bitte Name bzw. Gruppe angeben.');
      if ([p.name, p.kicker_de, p.sub_de, p.kicker_en, p.sub_en].some(function (s) { return PH.test(s); })) errs.push('Bitte die Platzhalter in eckigen Klammern (z. B. [Schule]) durch den echten Text ersetzen.');
      if (!p.from_date || !p.from_time || !p.to_date || !p.to_time) errs.push('Bitte Beginn und Ende vollständig angeben.');
      if (!p.displays.length) errs.push('Bitte mindestens ein Display wählen.');
      submit(p, errs);
    });
  }

  // ------------------------------------------------------------ Hinweis
  function noticeForm() {
    var n = S.edit || { text_de: '', text_en: '', icon: 'right', days: [5], from: '12:00', to: 'close', valid_from: '', valid_to: '', active: true, priority: 0,
      displays: S.boot.displays_all.map(function (d) { return d.id; }) };
    var icons = Object.keys(S.boot.notice_icons).map(function (k) {
      return '<option value="' + k + '"' + (n.icon === k ? ' selected' : '') + '>' + esc(S.boot.notice_icons[k]) + '</option>';
    }).join('');
    formShell(S.edit ? 'Hinweis bearbeiten' : 'Neuer Hinweis',
      field('text_de', 'Text (Deutsch)', n.text_de, 'text', ' maxlength="160"') + field('text_en', 'Text (Englisch, optional)', n.text_en, 'text', ' maxlength="160"') +
      '<label class="l" for="icon">Symbol</label><select id="icon">' + icons + '</select>' +
      '<div class="box"><b>Wann?</b><label class="l">Wochentage</label>' + dayChips(n.days || []) +
      '<div class="row2">' + field('from', 'Von', n.from, 'time') + field('to', 'Bis', n.to === 'close' ? '' : n.to, 'time') + '</div>' +
      '<label class="cbx"><input type="checkbox" id="until_close"' + (n.to === 'close' ? ' checked' : '') + '> bis Geschäftsschluss</label>' +
      '<div class="row2">' + field('valid_from', 'Gültig von (optional)', n.valid_from, 'date') + field('valid_to', 'Gültig bis (optional)', n.valid_to, 'date') + '</div></div>' +
      '<div class="box"><b>Anzeigen auf</b>' + checks('displays', S.boot.displays_all, n.displays || []) + '</div>' +
      '<label class="cbx"><input type="checkbox" id="active"' + (n.active ? ' checked' : '') + '> Hinweis ist aktiv</label>' +
      field('priority', 'Priorität (0–99, höher = weiter oben)', n.priority, 'number', ' min="0" max="99" inputmode="numeric"'));
    var sync = function () { $('#to').disabled = $('#until_close').checked; };
    $('#until_close').addEventListener('change', sync);
    sync();
    $('#f').addEventListener('submit', function (ev) {
      ev.preventDefault();
      var p = { text_de: val('text_de'), text_en: val('text_en'), icon: val('icon'), days: $all('.chip.day.on').map(function (c) { return parseInt(c.getAttribute('data-d'), 10); }),
        from: val('from'), to: val('to'), until_close: $('#until_close').checked, valid_from: val('valid_from'), valid_to: val('valid_to'),
        displays: checked('displays'), active: $('#active').checked, priority: parseInt(val('priority') || 0, 10) };
      var errs = [];
      if (!p.text_de) errs.push('Bitte einen Text angeben.');
      if (!p.days.length) errs.push('Bitte mindestens einen Wochentag wählen.');
      if (!p.displays.length) errs.push('Bitte mindestens ein Display wählen.');
      submit(p, errs);
    });
  }

  // ------------------------------------------------------------ News
  function newsForm() {
    var n = S.edit || { title: '', text: '', image: 0, image_url: '', from: today(), to: today(14), sites: S.boot.sites.map(function (s) { return s.id; }), slide: true, ticker: true };
    formShell(S.edit ? 'News bearbeiten' : 'Neue News',
      field('title', 'Titel', n.title, 'text', ' maxlength="140"') +
      '<label class="l" for="text">Text (optional)</label><textarea id="text" rows="4" maxlength="600">' + esc(n.text) + '</textarea>' +
      uploadBox('image', n.image_url, 'Bild (optional)') +
      '<div class="row2">' + field('from', 'Gültig von', n.from, 'date') + field('to', 'Gültig bis', n.to, 'date') + '</div>' +
      '<div class="box"><b>Standorte</b>' + checks('sites', S.boot.sites, n.sites || []) + '</div>' +
      '<div class="box"><b>Anzeige</b><label class="cbx"><input type="checkbox" id="slide"' + (n.slide ? ' checked' : '') + '> als eigene Folie</label>' +
      '<label class="cbx"><input type="checkbox" id="ticker"' + (n.ticker ? ' checked' : '') + '> im Laufband</label></div>');
    bindUpload('image', n.image);
    $('#f').addEventListener('submit', function (ev) {
      ev.preventDefault();
      var p = { title: val('title'), text: $('#text').value, image: parseInt(val('image') || 0, 10), from: val('from'), to: val('to'), sites: checked('sites'), slide: $('#slide').checked, ticker: $('#ticker').checked };
      var errs = [];
      if (!p.title) errs.push('Bitte einen Titel angeben.');
      if (!p.from || !p.to) errs.push('Bitte „gültig von“ und „gültig bis“ angeben.');
      if (!p.sites.length) errs.push('Bitte mindestens einen Standort wählen.');
      if (!p.slide && !p.ticker) errs.push('Bitte „als Folie“ und/oder „im Laufband“ wählen.');
      submit(p, errs);
    });
  }

  function renderForm() {
    if (S.tab === 'greetings') greetingForm(); else if (S.tab === 'notices') noticeForm(); else newsForm();
  }

  if (cfg.editor || S.token) start(); else renderLogin('');
})();
