/* Marberger Signage – Backend */
(function ($) {
  'use strict';

  // Medienauswahl
  $(document).on('click', '.mms-media-pick', function (e) {
    e.preventDefault();
    var box = $(this).closest('.mms-media');
    var frame = wp.media({ title: 'Bild wählen', library: { type: 'image' }, multiple: false });
    frame.on('select', function () {
      var a = frame.state().get('selection').first().toJSON();
      box.find('input[type=hidden]').val(a.id);
      var url = (a.sizes && a.sizes.medium) ? a.sizes.medium.url : a.url;
      box.find('.mms-media-preview').html('<img src="' + url + '" alt="">');
      box.find('.mms-media-clear').prop('hidden', false);
    });
    frame.open();
  });
  $(document).on('click', '.mms-media-clear', function (e) {
    e.preventDefault();
    var box = $(this).closest('.mms-media');
    box.find('input[type=hidden]').val('');
    box.find('.mms-media-preview').empty();
    $(this).prop('hidden', true);
  });

  // Wiederholgruppen (Ausnahmen)
  $(document).on('click', '.mms-add-row', function (e) {
    e.preventDefault();
    var wrap = $(this).closest('td');
    var table = wrap.find('.mms-repeater');
    var i = parseInt(table.attr('data-next'), 10) || 0;
    var tpl = wrap.find('template.mms-row-template').html().replace(/__i__/g, i);
    table.find('tbody').append(tpl);
    table.attr('data-next', i + 1);
  });
  $(document).on('click', '.mms-remove-row', function (e) {
    e.preventDefault();
    $(this).closest('tr').remove();
  });

  // Kategorie: Typwechsel übernimmt Standardwerte des Typs
  $(document).on('change', '#mms-box select[name="mms[type]"]', function () {
    var d = (window.MMS && MMS.defaults[this.value]) || null;
    if (!d || !window.confirm('Standardwerte für diesen Typ übernehmen?')) return;
    Object.keys(d).forEach(function (k) {
      if (k === 'type') return;
      var el = $('#mms-box [name="mms[' + k + ']"]');
      if (!el.length) return;
      el.val(typeof d[k] === 'boolean' ? (d[k] ? '1' : '0') : d[k]);
    });
  });

  // Adresse markieren
  $(document).on('focus click', '.mms-copy', function () { this.select(); });

  // Zustand testen
  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  var RANKS = { fullpage: 'Vollbild-Seite', greeting: 'Gruß', closure: 'Schließzeit-Seite', closed: 'Geschlossen-Seite', standard: 'Standard (Wegweiser bzw. Abfolge)' };
  $(document).on('click', '.mms-preview-run', function (e) {
    e.preventDefault();
    var box = $(this).closest('.mms-preview');
    var at = box.find('.mms-preview-at').val();
    var url = box.data('url');
    url += (url.indexOf('?') === -1 ? '?' : '&') + 'at=' + encodeURIComponent(at);
    var out = box.find('.mms-preview-out').html('<p>Berechne …</p>');
    fetch(url, { credentials: 'same-origin', headers: { 'X-WP-Nonce': MMS.nonce } })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d.state) { out.html('<p class="mms-err">' + esc(d.message || 'Fehler') + '</p>'); return; }
        var s = d.state, h = '<table class="widefat striped"><tbody>';
        h += '<tr><th>Anzeige</th><td><strong>' + esc(RANKS[s.rank] || s.rank) + '</strong>' + (s.below ? ' (Besucher-Knopf führt zu: ' + esc(RANKS[s.below]) + ')' : '') + '</td></tr>';
        h += '<tr><th>Öffnungsstatus</th><td>' + esc({ open: 'geöffnet', pause: 'Pause', after_hours: 'außerhalb der Öffnungszeiten', closed_day: 'geschlossener Tag', closure: 'Schließzeit' }[s.status.state] || s.status.state) + (s.status.reason_de ? ' – ' + esc(s.status.reason_de) : '') + '</td></tr>';
        if (s.closed) h += '<tr><th>Text</th><td>' + esc(s.closed.back_de) + '<br><em>' + esc(s.closed.back_en) + '</em></td></tr>';
        h += '<tr><th>Balken</th><td>' + ((s.banners || []).length ? s.banners.map(function (b) { return '• ' + esc(b.text_de); }).join('<br>') : '–') + '</td></tr>';
        if ((s.ticker || []).length) h += '<tr><th>Laufband</th><td>' + s.ticker.map(function (t) { return '• ' + esc(t.text_de); }).join('<br>') + '</td></tr>';
        h += '</tbody></table>';
        out.html(h);
      })
      .catch(function (err) { out.html('<p class="mms-err">' + esc(err.message) + '</p>'); });
  });
})(jQuery);
