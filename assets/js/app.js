(function () {
  var $ = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };
  var base = document.documentElement.getAttribute('data-base') || '';

  // mobile sidebar
  var mb = $('.menu-btn');
  if (mb) mb.addEventListener('click', function () { document.body.classList.toggle('nav-open'); });
  document.addEventListener('click', function (e) { if (document.body.classList.contains('nav-open') && !e.target.closest('.side') && !e.target.closest('.menu-btn')) document.body.classList.remove('nav-open'); });

  // toasts fade out
  $$('.toast').forEach(function (t, i) { setTimeout(function () { t.style.display = 'none'; }, 5500 + i * 500); });

  // confirm dialogs
  document.addEventListener('submit', function (e) { var m = e.target.getAttribute('data-confirm'); if (m && !confirm(m)) e.preventDefault(); });
  document.addEventListener('click', function (e) { var a = e.target.closest('a[data-confirm]'); if (a && !confirm(a.getAttribute('data-confirm'))) e.preventDefault(); });

  // live table filter
  $$('input[data-filter]').forEach(function (inp) {
    inp.addEventListener('input', function () {
      var q = inp.value.toLowerCase();
      $$(inp.getAttribute('data-filter') + ' tbody tr').forEach(function (tr) { tr.style.display = tr.textContent.toLowerCase().indexOf(q) > -1 ? '' : 'none'; });
    });
  });

  // print buttons
  $$('[data-print]').forEach(function (b) { b.addEventListener('click', function () { window.print(); }); });

  // fare meter (public calculator + booking form)
  var form = $('[data-fare-form]');
  if (form) {
    var meter = $('#meter'), timer;
    var money = function (n) { return Number(n).toLocaleString('en-KE'); };
    var val = function (n) { var el = form.elements[n]; if (!el) return ''; if (el.length && el[0] && el[0].type === 'radio') { var c = form.querySelector('input[name="' + n + '"]:checked'); return c ? c.value : ''; } return el.value; };
    var update = function () {
      var hourly = val('mode') === 'hourly';
      var qs = new URLSearchParams({ pickup: val('pickup'), drop: hourly ? '' : val('drop'), vtype: val('vtype'), hours: hourly ? val('hours') : 0, promo: val('promo') || '' });
      $$('[data-point]', form).forEach(function (el) { el.style.display = hourly ? 'none' : ''; });
      $$('[data-hourly]', form).forEach(function (el) { el.style.display = hourly ? '' : 'none'; });
      fetch(base + '/api/fare.php?' + qs.toString()).then(function (r) { return r.json(); }).then(function (d) {
        var note = $('.note', meter);
        if (!d.ok) { $('.amt', meter).innerHTML = '<small>KES</small>--'; $('[data-base]', meter).textContent = '-'; $('[data-disc]', meter).textContent = '-'; $('[data-min]', meter).textContent = '-'; note.className = 'note bad'; note.textContent = d.msg || ''; return; }
        $('.amt', meter).innerHTML = '<small>KES</small>' + money(d.total);
        $('[data-base]', meter).textContent = 'KES ' + money(d.base);
        $('[data-disc]', meter).textContent = d.discount ? '- KES ' + money(d.discount) : 'None';
        $('[data-min]', meter).textContent = d.minutes ? (d.minutes >= 120 ? Math.round(d.minutes / 60) + ' hr' : d.minutes + ' min') : '-';
        note.className = 'note'; note.textContent = d.promo_msg || (d.type + '. Fixed fare, no surprises.');
      }).catch(function () {});
    };
    form.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(update, 160); });
    form.addEventListener('change', function () { clearTimeout(timer); timer = setTimeout(update, 60); });
    update();
  }

  // saved place -> fill pickup
  var sp = $('#savedPick');
  if (sp) sp.addEventListener('change', function () {
    var o = sp.options[sp.selectedIndex]; if (!o.value) return;
    var f = sp.form; f.elements['pickup'].value = o.getAttribute('data-loc'); f.elements['pickup_detail'].value = o.getAttribute('data-detail') || '';
    f.elements['pickup'].dispatchEvent(new Event('change', { bubbles: true }));
  });
})();
