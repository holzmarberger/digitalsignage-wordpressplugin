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
    var wrap = $(this).closest('.mms-rep-wrap, .mms-own-seq, td');
    var table = wrap.find('.mms-repeater');
    var i = parseInt(table.attr('data-next'), 10) || 0;
    var tpl = wrap.find('template.mms-row-template').html().replace(/__i__/g, i);
    var row = $(tpl);
    table.find('tbody').first().append(row);
    table.attr('data-next', i + 1);
    row.find('.mms-seq-type').trigger('change');
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


  // Abfolge: Felder je Typ, Zeilen verschieben
  function seqType(sel) {
    var tr = $(sel).closest('tr'), t = $(sel).val();
    tr.find('.mms-seq-url').toggle(t === 'url');
    tr.find('.mms-seq-page').toggle(t === 'page');
    tr.find('.mms-seq-news').toggle(t === 'news');
    tr.find('.mms-seq-res').css('visibility', t === 'url' ? 'visible' : 'hidden');
  }
  $(document).on('change', '.mms-seq-type', function () { seqType(this); });
  $(function () { $('.mms-seq-type').each(function () { seqType(this); }); });
  $(document).on('click', '.mms-up', function (e) {
    e.preventDefault();
    var tr = $(this).closest('tr');
    tr.prev('tr').before(tr);
  });
  $(document).on('click', '.mms-down', function (e) {
    e.preventDefault();
    var tr = $(this).closest('tr');
    tr.next('tr').after(tr);
  });
  // Namen nach dem Verschieben neu durchnummerieren, damit die Reihenfolge gespeichert wird
  $(document).on('submit', 'form#post, form.mms-settings', function () {
    $(this).find('.mms-repeater').each(function () {
      $(this).children('tbody').children('tr').each(function (idx) {
        $(this).find('[name]').each(function () {
          this.name = this.name.replace(/\[(\d+|__i__)\]/, function (m, g, off, str) { return '[' + idx + ']'; });
        });
      });
    });
  });

  // Display: eigene Abfolge ein-/ausblenden
  function ownSeq() { $('.mms-own-seq').toggle($('input[name="mms[sequence_own]"]').is(':checked')); }
  $(document).on('change', 'input[name="mms[sequence_own]"]', ownSeq);
  $(ownSeq);

  // Vollbild-Seite: Zeitregel umschalten
  function ruleType() {
    var t = $('.mms-ruletype').val();
    if (!t) return;
    $('.mms-rule').hide();
    $('.mms-rule-' + t).show();
  }
  $(document).on('change', '.mms-ruletype', ruleType);
  $(ruleType);

  // Gruß: Schnellvorlage
  $(document).on('change', '.mms-template', function () {
    if (this.value === '') return;
    var t = JSON.parse(this.getAttribute('data-templates') || '[]')[parseInt(this.value, 10)];
    if (!t) return;
    var title = $('#title');
    if (title.val() && !window.confirm('Die bisherigen Texte durch die Vorlage ersetzen?')) { this.value = ''; return; }
    title.val(t.name || '').trigger('input');
    $('#title-prompt-text').addClass('screen-reader-text');
    ['kicker_de', 'sub_de', 'kicker_en', 'sub_en'].forEach(function (k) { $('[name="mms[' + k + ']"]').val(t[k] || ''); });
    var el = title.get(0), m = /\[[^\]]+\]/.exec(el.value);
    el.focus();
    if (m) el.setSelectionRange(m.index, m.index + m[0].length);
    this.value = '';
  });

  // Passwort anzeigen
  $(document).on('click', '.mms-reveal', function (e) {
    e.preventDefault();
    var f = $(this).siblings('.mms-secret');
    var show = f.attr('type') === 'password';
    f.attr('type', show ? 'text' : 'password');
    $(this).text(show ? 'verbergen' : 'anzeigen');
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
