/* Marberger Signage – Diagnoseansicht (Paket 1). Wird in Paket 2 durch den Player ersetzt. */
(function () {
  'use strict';
  var root = document.getElementById('mms-diag');
  var cfg = JSON.parse(root.getAttribute('data-config'));
  var last = null;
  var RANKS = { fullpage: 'Vollbild-Seite', greeting: 'Gruß', closure: 'Schließzeit', closed: 'Geschlossen', standard: 'Standard (Wegweiser / Abfolge)' };

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function render(d) {
    var s = d.state, h = '';
    h += '<div class="head">' + (d.location && d.location.logo ? '<img src="' + esc(d.location.logo) + '" alt="">' : '<b>' + esc(d.display.name) + '</b>') +
      '<span class="time">' + esc(new Date(d.server_time).toLocaleTimeString('de-AT', { hour: '2-digit', minute: '2-digit' })) + '</span></div>';
    h += '<h1>' + esc(d.display.name) + '</h1>';
    h += '<div><span class="rank">' + esc(RANKS[s.rank] || s.rank) + (s.below ? ' · darunter: ' + esc(RANKS[s.below] || s.below) : '') + '</span></div>';
    (s.banners || []).forEach(function (b) {
      h += '<div class="banner">' + esc(b.text_de) + '<small>' + esc(b.text_en) + '</small></div>';
    });
    if (s.closed) {
      h += '<div class="box"><b>' + esc(s.closed.reason_de || (s.closed.kind === 'pause' ? 'Pause' : 'Geschlossen')) + '</b><br>' + esc(s.closed.back_de) + '<br><span class="muted">' + esc(s.closed.back_en) + '</span></div>';
    }
    if (d.opening && d.opening.week && d.opening.week.length) {
      h += '<div class="box"><table>' + d.opening.week.map(function (r) {
        return '<tr><td>' + esc(r.days_de) + '</td><td>' + esc(r.times || 'geschlossen') + '</td></tr>';
      }).join('') + '</table></div>';
    }
    (s.ticker || []).forEach(function (t) {
      h += '<div class="box">Laufband: ' + esc(t.text_de) + '</div>';
    });
    h += '<p class="muted">Diagnoseansicht · Display „' + esc(d.display.key) + '“ · ' + esc(d.location ? d.location.name : 'ohne Standort') + ' · Version ' + esc(d.version) + '</p>';
    root.innerHTML = h;
  }

  function poll() {
    fetch(cfg.stateUrl, { cache: 'no-store', credentials: 'same-origin' })
      .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
      .then(function (d) { last = d; render(d); schedule(d.settings.poll); })
      .catch(function (e) {
        if (!last) root.innerHTML = '<div class="err">Zustand nicht erreichbar (' + esc(e.message) + ')</div>';
        schedule(30);
      });
  }
  function schedule(sec) { setTimeout(poll, Math.max(15, sec || 60) * 1000); }
  poll();
})();
