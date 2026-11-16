/* Marberger Signage – Player für Empfangs- und Info-Displays. Vanilla JS (ES5), läuft in der Android-WebView von Slide Show. */
(function () {
  'use strict';

  var cfg = JSON.parse(document.getElementById('mms-config').textContent);
  var screen = document.getElementById('screen');
  var TZ = 'Europe/Vienna';
  var S = {
    data: null, offset: 0, lang: 'de', path: [], dismissed: {}, last: Date.now(), pending: false,
    seqIdx: 0, seqTimer: null, seqSig: '', newsIdx: 0, reloadedOn: '', frameBuilt: false
  };

  var T = {
    de: { welcome: 'Willkommen bei<br>Holz Marberger', q: 'Was brauchen Sie?', lead: 'Tippen Sie auf Ihr Ziel – wir zeigen Ihnen den Weg.', hint: 'Berühren Sie eine Kachel', go: 'Weg anzeigen →', back: '← Zurück', contact: 'Ansprechpartner', qr: 'Weg aufs Handy: QR-Code scannen', ring: 'Zurück zur Startseite in', visitor: 'Besucher? Hier tippen · Visitor? Tap here', directory: 'Wegweiser öffnen · Open directory', empty: 'Noch keine Kacheln angelegt.', more: 'Auswahl' },
    en: { welcome: 'Welcome to<br>Holz Marberger', q: 'How can we help you?', lead: 'Tap your destination – we will show you the way.', hint: 'Touch a tile', go: 'Show the way →', back: '← Back', contact: 'Contact', qr: 'Directions on your phone: scan the QR code', ring: 'Back to start in', visitor: 'Besucher? Hier tippen · Visitor? Tap here', directory: 'Wegweiser öffnen · Open directory', empty: 'No tiles yet.', more: 'Choose' }
  };
  function t(k) { return T[S.lang][k]; }

  // ---------------------------------------------------------------- Hilfen
  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function now() { return new Date(Date.now() + S.offset); }
  function fmtTime(d) { return d.toLocaleTimeString('de-AT', { hour: '2-digit', minute: '2-digit', timeZone: TZ }); }
  function fmtDate(d, lang, withYear) {
    var o = { weekday: 'long', day: 'numeric', month: 'long', timeZone: TZ };
    if (withYear !== false) o.year = 'numeric';
    return d.toLocaleDateString(lang === 'en' ? 'en-GB' : 'de-AT', o);
  }
  function L(obj, key) { return (S.lang === 'en' && obj[key + '_en']) ? obj[key + '_en'] : (obj[key + '_de'] || ''); }

  var NODE_ICONS = {
    construction: '<path d="M8 30 32 10l24 20"/><path d="M14 26v28h36V26"/><path d="M14 38h36M14 46h36M26 54V38M38 54V38"/>',
    decor: '<path d="M8 50h48M8 40h48M8 30h48"/><path d="M20 30v10M40 40v10"/><path d="M42 24c0-10 8-14 14-14 0 8-4 14-14 14z"/>',
    office: '<rect x="12" y="10" width="40" height="46" rx="2"/><path d="M22 20h6M36 20h6M22 30h6M36 30h6M22 40h6M36 40h6M28 56v-8h8v8"/>',
    parcel: '<path d="M8 20 32 8l24 12v26L32 58 8 46z"/><path d="M8 20l24 12 24-12M32 32v26M20 14l24 12"/>',
    truck: '<path d="M6 16h32v28H6zM38 26h12l8 10v8H38z"/><circle cx="16" cy="48" r="5"/><circle cx="48" cy="48" r="5"/>',
    info: '<circle cx="32" cy="32" r="24"/><path d="M32 28v16M32 19v1"/>',
    person: '<circle cx="32" cy="20" r="10"/><path d="M12 56c0-12 9-20 20-20s20 8 20 20"/>',
    phone: '<path d="M20 8h10l4 12-7 5c3 6 8 11 14 14l5-7 12 4v10c0 4-3 6-7 6C28 52 12 36 12 15c0-4 3-7 8-7z"/>',
    stairs: '<path d="M8 54h12V42h12V30h12V18h12"/><path d="M8 54v-6"/>',
    door: '<path d="M16 56V8h28v48M8 56h48"/><circle cx="38" cy="32" r="2"/>',
    wc: '<circle cx="20" cy="14" r="5"/><circle cx="44" cy="14" r="5"/><path d="M14 24h12l2 18h-4v14h-8V42h-4zM38 24h12l4 18h-6v14h-8V42h-6z"/>',
    saw: '<path d="M8 40 40 8l16 16-32 32z"/><path d="M14 46l4-4M20 52l4-4M26 40l4-4M32 34l4-4"/>'
  };
  function nodeIcon(k) { return '<svg viewBox="0 0 64 64" fill="none" stroke="#006729" stroke-width="4" stroke-linejoin="round" stroke-linecap="round">' + (NODE_ICONS[k] || NODE_ICONS.info) + '</svg>'; }
  var BANNER_ICONS = {
    right: '<path d="M10 32h40M36 18l14 14-14 14"/>', left: '<path d="M54 32H14M28 18 14 32l14 14"/>', up: '<path d="M32 54V14M18 28 32 14l14 14"/>',
    info: '<circle cx="32" cy="32" r="22"/><path d="M32 30v14M32 21v1"/>', warn: '<path d="M32 10 56 52H8z"/><path d="M32 26v12M32 45v1"/>'
  };
  function bannerIcon(k) { return '<svg viewBox="0 0 64 64" fill="none" stroke="#fff" stroke-width="6" stroke-linecap="round" stroke-linejoin="round">' + (BANNER_ICONS[k] || BANNER_ICONS.info) + '</svg>'; }
  var WX = {
    sun: '<circle cx="32" cy="32" r="12" fill="#f2b632"/><g stroke="#f2b632" stroke-width="4" stroke-linecap="round"><path d="M32 6v7M32 51v7M6 32h7M51 32h7M13 13l5 5M46 46l5 5M13 51l5-5M46 18l5-5"/></g>',
    moon: '<path d="M40 10a22 22 0 1 0 14 34A18 18 0 0 1 40 10z" fill="#c9cfe0"/>',
    partly: '<circle cx="24" cy="24" r="11" fill="#f2b632"/><g stroke="#f2b632" stroke-width="3" stroke-linecap="round"><path d="M24 4v5M4 24h5M10 10l3.5 3.5M38 10l-3.5 3.5"/></g><path d="M24 50a10 10 0 0 1 3-19.5 13 13 0 0 1 24.5 4A8 8 0 0 1 50 50z" fill="#fff" stroke="#9aa39d" stroke-width="2.5"/>',
    cloudy: '<path d="M18 48a12 12 0 0 1 3-23.5 15 15 0 0 1 28.5 5A10 10 0 0 1 48 48z" fill="#e3e6e4" stroke="#9aa39d" stroke-width="2.5"/>',
    fog: '<path d="M16 36a12 12 0 0 1 3-23 15 15 0 0 1 28 5A9 9 0 0 1 46 36z" fill="#e3e6e4" stroke="#9aa39d" stroke-width="2.5"/><g stroke="#9aa39d" stroke-width="3" stroke-linecap="round"><path d="M10 44h44M14 52h36"/></g>',
    rain: '<path d="M16 38a12 12 0 0 1 3-23 15 15 0 0 1 28 5A9 9 0 0 1 46 38z" fill="#e3e6e4" stroke="#9aa39d" stroke-width="2.5"/><g stroke="#1f5fa8" stroke-width="3" stroke-linecap="round"><path d="M22 44l-3 8M32 44l-3 8M42 44l-3 8"/></g>',
    snow: '<path d="M16 38a12 12 0 0 1 3-23 15 15 0 0 1 28 5A9 9 0 0 1 46 38z" fill="#e3e6e4" stroke="#9aa39d" stroke-width="2.5"/><g fill="#1f5fa8"><circle cx="22" cy="48" r="2.5"/><circle cx="32" cy="52" r="2.5"/><circle cx="42" cy="48" r="2.5"/></g>',
    thunder: '<path d="M16 36a12 12 0 0 1 3-23 15 15 0 0 1 28 5A9 9 0 0 1 46 36z" fill="#cfd4d1" stroke="#7d8680" stroke-width="2.5"/><path d="M34 38l-8 12h8l-4 10 12-14h-8l4-8z" fill="#f2b632"/>'
  };

  function logoHtml() {
    var src = (S.data.location && S.data.location.logo) || cfg.logo;
    return '<div class="logo"><img src="' + esc(src) + '" alt="Holz Marberger"></div>';
  }
  function weatherHtml() {
    var w = S.data.weather, h = '<div class="wx">';
    if (w) h += '<svg viewBox="0 0 64 64">' + (WX[w.icon] || WX.cloudy) + '</svg><span class="t">' + esc(w.temp) + ' °C</span>';
    return h + '<span class="c clk">' + fmtTime(now()) + '</span></div>';
  }

  // ---------------------------------------------------------------- Empfangsdisplay
  function effectiveRank() {
    var st = S.data.state, r = st.rank;
    if (r === 'fullpage' && !S.dismissed.fullpage) return 'fullpage';
    var next = r === 'fullpage' ? st.below : r;
    if ((next === 'closed' || next === 'closure') && !S.dismissed[next]) return next;
    return next === 'greeting' ? 'greeting' : 'standard';
  }

  function currentNode() {
    var list = S.data.directory || [], node = null;
    for (var i = 0; i < S.path.length; i++) {
      node = null;
      for (var j = 0; j < list.length; j++) if (list[j].id === S.path[i]) node = list[j];
      if (!node) { S.path = S.path.slice(0, i); return S.path.length ? currentNode() : null; }
      list = node.children || [];
    }
    return node;
  }

  function headerHtml() {
    var h = '<header class="gz">' + logoHtml();
    var txt = '<div class="txt"><h1>' + t('welcome') + '</h1><div class="date">' + esc(fmtDate(now(), S.lang)) + '</div></div>';
    return h + (S.data.display.format === 'hoch' ? weatherHtml() + txt : txt + weatherHtml()) + '</header>';
  }

  function bannersHtml(sm) {
    return (S.data.state.banners || []).map(function (b) {
      var main = S.lang === 'en' && b.text_en ? b.text_en : b.text_de;
      var other = S.lang === 'en' ? b.text_de : b.text_en;
      return '<div class="notice' + (sm ? ' sm' : '') + '"><div class="ni">' + bannerIcon(b.icon) + '</div><div class="nt"><b>' + esc(main) + '</b>' + (other && other !== main ? '<span>' + esc(other) + '</span>' : '') + '</div></div>';
    }).join('');
  }

  function tilesHtml(list, compact) {
    if (!list.length) return '<div class="tiles n1"><div class="hint">' + t('empty') + '</div></div>';
    var n = Math.min(list.length, 8);
    return '<div class="tiles n' + n + (compact ? ' compact' : '') + '">' + list.slice(0, 8).map(function (node) {
      var sub = (S.lang === 'en' && node.sub_en) || node.sub_de;
      return '<button class="tile" data-node="' + node.id + '"><div class="ic">' + nodeIcon(node.icon) + '</div><div class="tt">' + esc(nodeTitle(node)) + '</div>' +
        (sub ? '<div class="st">' + esc(sub) + '</div>' : '') + '<div class="go">' + (node.children && node.children.length ? t('more') + ' →' : t('go')) + '</div></button>';
    }).join('') + '</div>';
  }

  function nodeTitle(n) { return (S.lang === 'en' && n.title_en) || n.title_de; }

  function langsHtml() {
    return '<div class="langs"><button data-lang="de" class="' + (S.lang === 'de' ? 'on' : '') + '">Deutsch</button><button data-lang="en" class="' + (S.lang === 'en' ? 'on' : '') + '">English</button></div>';
  }

  function guestHtml(g) {
    var name = g.name || '', cls = name.length > 45 ? 'xlong' : (name.length > 28 ? 'long' : '');
    var logo = g.logo ? '<img src="' + esc(g.logo) + '" alt="">' : '<svg viewBox="0 0 100 100" width="80%" height="80%"><path d="M50 6 88 20v30c0 22-17 38-38 44C29 88 12 72 12 50V20z" fill="#e3f0e7" stroke="#006729" stroke-width="3"/></svg>';
    return '<div class="guest-card"><div class="guest-logo">' + logo + '</div><div class="gtxt"><div class="kicker">' + esc(L(g, 'kicker') || 'Herzlich willkommen') + '</div><h1 class="' + cls + '">' + esc(name) + '</h1>' +
      (L(g, 'sub') ? '<div class="sub">' + esc(L(g, 'sub')) + '</div>' : '') + '</div></div>';
  }

  function qrHtml(url) { return url ? '<div class="qr"><div class="qrc" data-qr="' + esc(url) + '"></div><span>' + esc(t('qr')) + '</span></div>' : ''; }

  function detailHtml(node) {
    var steps = (S.lang === 'en' && node.steps_en && node.steps_en.length ? node.steps_en : node.steps_de) || [];
    var media = node.media ? (node.media.type === 'video'
      ? '<div class="media"><video src="' + esc(node.media.url) + '" autoplay muted loop playsinline></video></div>'
      : '<div class="media"><img src="' + esc(node.media.url) + '" alt=""></div>') : '';
    var info = '';
    if (steps.length || node.contact || node.photo || node.qr_url) {
      info = '<div class="steps"><div class="sx">' + (steps.length ? '<ol>' + steps.map(function (s) { return '<li>' + esc(s) + '</li>'; }).join('') + '</ol>' : '') +
        (node.contact ? '<div class="contact">' + esc(t('contact')) + ': <b>' + esc(node.contact) + (node.ext ? ' · DW ' + esc(node.ext) : '') + '</b></div>' : '') + '</div>' +
        (node.photo ? '<img class="photo" src="' + esc(node.photo) + '" alt="">' : '') + qrHtml(node.qr_url) + '</div>';
    }
    return '<div class="dbody' + (media ? '' : ' novid') + (info ? '' : ' noinfo') + '">' + media + info + '</div>';
  }

  function mainHtml(greet) {
    var st = S.data.state, node = currentNode(), b, nb, cls;
    if (!node) {
      b = bannersHtml(false);
      nb = (st.banners || []).length;
      cls = 'nv' + (nb ? ' hasn' : '') + (nb > 1 ? ' hasn2' : '');
      if (greet && st.greeting) {
        return '<section class="' + cls + '">' + b + guestHtml(st.greeting) + '<div class="cmp-head"><h2>' + esc(t('q')) + '</h2>' + langsHtml() + '</div>' + tilesHtml(S.data.directory || [], true) + '</section>';
      }
      return '<section class="' + cls + '">' + b + '<h2>' + esc(t('q')) + '</h2><p class="lead">' + esc(t('lead')) + '</p>' + tilesHtml(S.data.directory || [], false) +
        '<div class="foot">' + langsHtml() + '<span class="hint">' + esc(t('hint')) + '</span></div></section>';
    }
    var title = nodeTitle(node);
    var head = bannersHtml(true) + '<div class="dhead"><button class="back" data-act="back">' + esc(t('back')) + '</button><h2>' + esc(title) + '</h2></div>';
    if (node.children && node.children.length) return '<section class="nv sub">' + head + tilesHtml(node.children, false) + '</section>';
    return '<section class="nv detail">' + head + detailHtml(node) + '</section>';
  }

  function toHead() {
    return '<div class="to-head">' + logoHtml() + '<div class="dt">' + esc(fmtDate(now(), 'de', false)) + '<br><b class="clk">' + fmtTime(now()) + '</b></div></div>';
  }

  function fullpageHtml(fp, button) {
    return '<div class="takeover">' + toHead() + (fp.title ? '<h1>' + esc(fp.title) + '</h1>' : '') + '<div class="to-content">' + (fp.html || '') + '</div>' +
      (button && fp.visitor_button ? '<button class="visitor" data-act="dismiss" data-r="fullpage">' + esc(t('visitor')) + '</button>' : '') + '</div>';
  }

  function weekHtml() {
    var rows = (S.data.opening && S.data.opening.week) || [];
    if (!rows.length) return '';
    return '<div class="oh"><h3>Öffnungszeiten · Opening hours</h3><table>' + rows.map(function (r) {
      return '<tr><td><b>' + esc(r.days_de) + '</b><br><span>' + esc(r.days_en) + '</span></td><td>' + esc(r.times || 'geschlossen · closed') + '</td></tr>';
    }).join('') + '</table></div>';
  }

  function closedHtml(button) {
    var c = S.data.state.closed || {};
    var reason = c.reason_de ? '<div class="en">' + esc(c.reason_de) + (c.reason_en && c.reason_en !== c.reason_de ? ' · ' + esc(c.reason_en) : '') + '</div>' : '';
    return '<div class="takeover closed">' + toHead() + '<h1>Wir haben geschlossen.</h1><div class="en">We are closed.</div>' + reason +
      '<div class="next">' + esc(c.back_de) + '<br><span>' + esc(c.back_en) + '</span></div>' + weekHtml() +
      (button && S.data.settings.directory_button ? '<button class="visitor" data-act="dismiss" data-r="closed">' + esc(t('directory')) + '</button>' : '') + '</div>';
  }

  function closureHtml(button) {
    var c = S.data.state.closed || {}, cl = c.closure || {};
    var msg = cl.text_de ? '<div class="hmsg">' + esc(cl.text_de) + (cl.text_en ? '<span>' + esc(cl.text_en) + '</span>' : '') + '</div>' : '';
    return '<div class="takeover closed holiday">' + toHead() + '<h1>' + esc(cl.label_de || 'Geschlossen') + '</h1><div class="en">' + esc(cl.label_en || 'Closed') + '</div>' + msg +
      (cl.image ? '<div class="himg"><img src="' + esc(cl.image) + '" alt=""></div>' : '') +
      '<div class="next">' + esc(c.back_de) + '<br><span>' + esc(c.back_en) + '</span></div>' +
      (button && S.data.settings.directory_button ? '<button class="visitor" data-act="dismiss" data-r="closure">' + esc(t('directory')) + '</button>' : '') + '</div>';
  }

  function renderReception() {
    var eff = effectiveRank(), st = S.data.state, h;
    if (eff === 'fullpage') h = fullpageHtml(st.fullpage, true);
    else if (eff === 'closed') h = closedHtml(true);
    else if (eff === 'closure') h = closureHtml(true);
    else h = headerHtml() + mainHtml(eff === 'greeting');
    screen.innerHTML = h + '<div class="ring" id="ring" style="display:none"></div><div class="offline-dot"></div>';
    afterRender();
  }

  // Inhalte verkleinern, bis sie in ihren Rahmen passen (lange Kachel-Titel, lange Gast-Namen)
  function over(box) { return box.scrollHeight > box.clientHeight + 2 || box.scrollWidth > box.clientWidth + 2; }
  function shrink(el, factor, min) {
    if (!el) return false;
    var fs = parseFloat(window.getComputedStyle(el).fontSize);
    if (fs <= min) return false;
    el.style.fontSize = Math.max(min, fs * factor) + 'px';
    return true;
  }
  function fitTiles() {
    var tiles = screen.querySelectorAll('.tile');
    for (var i = 0; i < tiles.length; i++) {
      var tile = tiles[i], n = 0, st = tile.querySelector('.st'), tt = tile.querySelector('.tt'), ic = tile.querySelector('.ic');
      if (over(tile) && st) st.style.display = 'none';
      while (over(tile) && n++ < 30) {
        if (!shrink(tt, 0.93, 24)) {
          if (ic && ic.offsetHeight > 60) { ic.style.width = ic.style.height = (ic.offsetHeight * 0.85) + 'px'; } else break;
        }
      }
    }
  }
  function fitGuest() {
    var card = screen.querySelector('.guest-card');
    if (!card) return;
    var logo = card.querySelector('.guest-logo'), n = 0;
    while (over(card) && n++ < 40) {
      if (logo && logo.offsetHeight > 150) { logo.style.width = logo.style.height = (logo.offsetHeight * 0.88) + 'px'; continue; }
      var a = shrink(card.querySelector('h1'), 0.93, 34), b = shrink(card.querySelector('.sub'), 0.95, 24), c = shrink(card.querySelector('.kicker'), 0.95, 26);
      if (!a && !b && !c) break;
    }
  }

  function afterRender() {
    fitGuest();
    fitTiles();
    if (document.fonts && document.fonts.status !== 'loaded') document.fonts.ready.then(function () { fitGuest(); fitTiles(); });
    var qs = screen.querySelectorAll('[data-qr]');
    for (var i = 0; i < qs.length; i++) {
      try {
        var qr = qrcode(0, 'M');
        qr.addData(qs[i].getAttribute('data-qr'));
        qr.make();
        qs[i].innerHTML = qr.createSvgTag({ cellSize: 4, margin: 0, scalable: true });
      } catch (e) { qs[i].parentNode.style.display = 'none'; }
    }
    var v = screen.querySelectorAll('video');
    for (var k = 0; k < v.length; k++) {
      v[k].muted = true;
      var p = v[k].play();
      if (p && p.catch) p.catch(function () {});
    }
  }

  function atRest() {
    if (!S.data || S.data.display.type !== 'reception') return true;
    return S.path.length === 0 && S.lang === 'de' && !S.dismissed.fullpage && !S.dismissed.closed && !S.dismissed.closure;
  }

  function resetHome() {
    S.path = []; S.lang = 'de'; S.dismissed = {};
    render();
  }

  // ---------------------------------------------------------------- Info-Display
  function infoRankHtml() {
    var st = S.data.state;
    if (st.rank === 'fullpage') return fullpageHtml(st.fullpage, false);
    if (st.rank === 'closed') return closedHtml(false);
    if (st.rank === 'closure') return closureHtml(false);
    return '';
  }

  function tickerItems() {
    var items = (S.data.state.ticker || []).map(function (x) { return x.text_de; });
    return items.concat(S.data.news_ticker || []);
  }

  function seqSig() {
    return JSON.stringify(S.data.sequence || []) + S.data.display.format;
  }

  function tickerHtml(items) {
    return items.length ? '<footer class="it"><div class="itl">NEWS</div><div class="itt"><div class="marq"><span>' +
      items.map(esc).join('<em>◆</em>') + '<em>◆</em></span><span>' + items.map(esc).join('<em>◆</em>') + '<em>◆</em></span></div></div></footer>' : '';
  }

  function startMarquee() {
    var mq = screen.querySelector('.marq span');
    if (mq) screen.querySelector('.marq').style.animationDuration = Math.max(20, mq.offsetWidth / 110) + 's';
  }

  // Nur Kopf, Balken und Laufband erneuern, ohne die laufende Folie (iframe) neu zu laden
  function softInfo() {
    if (infoRankHtml() || !document.getElementById('slide') || seqSig() !== S.seqSig) return false;
    var wx = screen.querySelector('.ih .wx');
    if (wx) wx.outerHTML = weatherHtml();
    document.getElementById('izb').innerHTML = bannersHtml(false);
    var old = screen.querySelector('.iv .it'), html = tickerHtml(tickerItems());
    var oldText = old ? old.querySelector('.marq span').textContent : '';
    var tmp = document.createElement('div');
    tmp.innerHTML = html;
    var newText = tmp.querySelector('.marq span') ? tmp.querySelector('.marq span').textContent : '';
    if (oldText !== newText) {
      if (old) old.parentNode.removeChild(old);
      if (html) screen.querySelector('.iv').appendChild(tmp.firstChild);
      startMarquee();
    }
    return true;
  }

  function renderInfo() {
    clearTimeout(S.seqTimer);
    var over = infoRankHtml();
    if (over) {
      screen.innerHTML = over + '<div class="offline-dot"></div>';
      S.seqSig = '';
      return;
    }
    var ticker = tickerHtml(tickerItems());
    screen.innerHTML = '<div class="iv"><header class="ih">' + logoHtml() + '<div class="ititle" id="ititle"></div>' + weatherHtml() + '</header>' +
      '<div class="ibar"><i id="ibar"></i></div><main class="iz"><div id="izb"></div><div class="izs" id="slide"></div></main>' + ticker + '</div>' +
      '<iframe class="preload" id="preload" aria-hidden="true" tabindex="-1"></iframe><div class="offline-dot"></div>';
    startMarquee();
    var seq = S.data.sequence || [];
    var sig = seqSig();
    if (sig !== S.seqSig) { S.seqSig = sig; S.seqIdx = 0; }
    if (S.seqIdx >= seq.length) S.seqIdx = 0;
    showSlide();
  }

  function showSlide() {
    clearTimeout(S.seqTimer);
    var seq = S.data.sequence || [], slide = document.getElementById('slide');
    var title = document.getElementById('ititle'), bar = document.getElementById('ibar');
    document.getElementById('izb').innerHTML = bannersHtml(false);
    if (!seq.length) {
      title.innerHTML = '<b>' + esc(S.data.display.name) + '</b>';
      slide.innerHTML = '<div class="empty"><div class="big clk">' + fmtTime(now()) + '</div><div class="d">' + esc(fmtDate(now(), 'de')) + '</div></div>';
      S.seqTimer = setTimeout(showSlide, 60000);
      return;
    }
    var e = seq[S.seqIdx];
    title.innerHTML = '<b>' + esc(e.title) + '</b><div class="dots">' + seq.map(function (x, i) { return '<i class="' + (i === S.seqIdx ? 'on' : '') + '"></i>'; }).join('') + '</div>';
    if (e.type === 'url') {
      slide.innerHTML = '<div class="frame"><iframe src="' + esc(e.url) + '" scrolling="no"></iframe></div>';
      fitFrame(slide.querySelector('.frame'), e.width || 1920, e.height || 1080);
    } else if (e.type === 'page') {
      slide.innerHTML = '<div class="islide page">' + (e.html || '') + '</div>';
    } else if (e.type === 'news') {
      var list = e.news || [], n = list[S.newsIdx % Math.max(1, list.length)] || {};
      S.newsIdx++;
      var to = n.to ? n.to.split('-') : null;
      slide.innerHTML = '<div class="islide news' + (n.image ? '' : ' noimg') + '">' + (n.image ? '<div class="nimg"><img src="' + esc(n.image) + '" alt=""></div>' : '') +
        '<div class="ntx"><div class="nk">' + esc(n.kicker || 'NEWS') + '</div><h2>' + esc(n.title) + '</h2>' + (n.text ? '<p>' + esc(n.text).replace(/\n/g, '<br>') + '</p>' : '') +
        (to ? '<div class="nd">gültig bis ' + to[2] + '.' + to[1] + '.</div>' : '') + '</div></div>';
    }
    var nb = bar.cloneNode(false);
    nb.className = 'run';
    nb.style.animationDuration = e.duration + 's';
    bar.parentNode.replaceChild(nb, bar);
    // nächsten Web-Inhalt vorladen
    var next = seq[(S.seqIdx + 1) % seq.length], pre = document.getElementById('preload');
    if (pre && next && next.type === 'url' && seq.length > 1) pre.src = next.url;
    S.seqTimer = setTimeout(function () { S.seqIdx = (S.seqIdx + 1) % seq.length; showSlide(); }, e.duration * 1000);
  }

  function fitFrame(frame, w, h) {
    var f = frame.querySelector('iframe');
    var cw = frame.offsetWidth, ch = frame.offsetHeight;
    var s = Math.min(cw / w, ch / h);
    f.style.width = w + 'px';
    f.style.height = h + 'px';
    f.style.transform = 'translate(' + Math.round((cw - w * s) / 2) + 'px,' + Math.round((ch - h * s) / 2) + 'px) scale(' + s + ')';
  }

  // ---------------------------------------------------------------- Allgemein
  function render() {
    if (!S.data) return;
    S.pending = false;
    screen.className = S.data.display.format === 'quer' ? 'quer' : 'hoch';
    fit();
    if (S.data.display.type === 'reception') renderReception(); else renderInfo();
  }

  function fit() {
    var W = S.data && S.data.display.format === 'quer' ? 1920 : 1080, H = W === 1920 ? 1080 : 1920;
    var s = Math.min(window.innerWidth / W, window.innerHeight / H);
    screen.style.transform = 'translate(' + Math.round((window.innerWidth - W * s) / 2) + 'px,' + Math.round((window.innerHeight - H * s) / 2) + 'px) scale(' + s + ')';
  }

  function stateUrl() {
    return cfg.stateUrl + (cfg.at ? (cfg.stateUrl.indexOf('?') === -1 ? '?' : '&') + 'at=' + encodeURIComponent(cfg.at) : '');
  }

  function poll() {
    fetch(stateUrl(), { cache: 'no-store', credentials: 'same-origin', headers: cfg.nonce ? { 'X-WP-Nonce': cfg.nonce } : {} })
      .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
      .then(function (d) {
        document.body.classList.remove('offline');
        S.offset = Date.parse(d.server_time) - Date.now();
        var changed = !S.data || d.hash !== S.data.hash;
        S.data = d;
        if (changed) {
          if (d.display.type === 'info' && softInfo()) { /* Folie läuft weiter */ }
          else if (atRest()) render();
          else S.pending = true;
        }
        setTimeout(poll, Math.max(15, d.settings.poll || 60) * 1000);
      })
      .catch(function () {
        document.body.classList.add('offline');
        if (!S.data) screen.innerHTML = '<p style="color:#fff;font-size:40px;padding:60px">Verbindung zum Intranet wird aufgebaut …</p>';
        setTimeout(poll, 30000);
      });
  }

  screen.addEventListener('pointerdown', function () { S.last = Date.now(); }, true);
  screen.addEventListener('click', function (ev) {
    var b = ev.target.closest ? ev.target.closest('button') : null;
    if (!b || !S.data) return;
    S.last = Date.now();
    if (b.getAttribute('data-node')) S.path.push(parseInt(b.getAttribute('data-node'), 10));
    else if (b.getAttribute('data-lang')) S.lang = b.getAttribute('data-lang');
    else if (b.getAttribute('data-act') === 'back') S.path.pop();
    else if (b.getAttribute('data-act') === 'dismiss') S.dismissed[b.getAttribute('data-r')] = true;
    render();
  });

  // Rücksprung bei Inaktivität (nur Empfang)
  setInterval(function () {
    if (!S.data || S.data.display.type !== 'reception') return;
    var ring = document.getElementById('ring');
    if (atRest()) {
      if (ring) ring.style.display = 'none';
      if (S.pending) render();
      return;
    }
    var idle = S.data.settings.idle || 45;
    var rem = Math.ceil(idle - (Date.now() - S.last) / 1000);
    if (rem <= 0) { resetHome(); return; }
    if (ring && rem <= 10) {
      var c = 2 * Math.PI * 34;
      ring.style.display = 'flex';
      ring.innerHTML = '<svg viewBox="0 0 80 80"><circle cx="40" cy="40" r="34" fill="none" stroke="rgba(255,255,255,.2)" stroke-width="7"/><circle cx="40" cy="40" r="34" fill="none" stroke="#fff" stroke-width="7" stroke-dasharray="' + c + '" stroke-dashoffset="' + (c * (1 - rem / 10)) + '" transform="rotate(-90 40 40)"/><text x="40" y="49" text-anchor="middle" font-size="26" fill="#fff" font-family="Arial" font-weight="700">' + rem + '</text></svg><span>' + esc(t('ring')) + ' ' + rem + ' s</span>';
    } else if (ring) ring.style.display = 'none';
  }, 250);

  // Uhr und nächtliches Neuladen
  setInterval(function () {
    var n = now(), els = screen.querySelectorAll('.clk');
    for (var i = 0; i < els.length; i++) els[i].textContent = fmtTime(n);
    var hm = fmtTime(n), day = n.toLocaleDateString('de-AT', { timeZone: TZ });
    if (hm === '03:00' && S.reloadedOn !== day && !cfg.at) {
      S.reloadedOn = day;
      fetch(stateUrl(), { cache: 'no-store' }).then(function (r) { if (r.ok) location.reload(); }).catch(function () {});
    }
  }, 10000);

  window.addEventListener('resize', function () { if (S.data) { fit(); if (S.data.display.type === 'info') renderInfo(); } });
  poll();
})();
