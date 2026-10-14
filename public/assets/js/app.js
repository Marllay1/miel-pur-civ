/* Miel Pur de Côte d'Ivoire — JavaScript natif, amélioration progressive (le site fonctionne sans JS). */
(function () {
  'use strict';
  var doc = document;
  doc.documentElement.classList.add('js');

  function $(sel, root) { return (root || doc).querySelector(sel); }
  function $all(sel, root) { return Array.prototype.slice.call((root || doc).querySelectorAll(sel)); }
  function csrf(form) { var i = form && form.querySelector('input[name="_csrf"]'); return i ? i.value : ''; }
  function announce(msg) { var r = $('[data-live-region]'); if (r) { r.textContent = ''; setTimeout(function () { r.textContent = msg; }, 50); } }

  doc.addEventListener('DOMContentLoaded', function () {
    // Navigation mobile
    var toggle = $('.nav-toggle');
    var nav = toggle ? doc.getElementById(toggle.getAttribute('aria-controls')) : null;
    if (toggle && nav) {
      toggle.addEventListener('click', function () {
        var open = nav.classList.toggle('is-open');
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      });
    }

    // Résumé d'erreurs : focus pour les lecteurs d'écran
    var summary = $('[data-error-summary]');
    if (summary) { summary.focus(); }

    // Confirmation des actions destructrices
    $all('form[data-confirm]').forEach(function (f) {
      f.addEventListener('submit', function (e) { if (!window.confirm(f.getAttribute('data-confirm'))) { e.preventDefault(); } });
    });
    $all('[data-print]').forEach(function (b) { b.addEventListener('click', function () { window.print(); }); });

    // Filtres repliés par défaut sur mobile
    var filters = $('[data-filters]');
    if (filters && window.matchMedia('(max-width: 899px)').matches) { filters.removeAttribute('open'); }

    initQty();
    initVariants();
    initAddToCart();
    initGallery();
    initCheckout();
    initConsent();
  });

  function initQty() {
    $all('[data-qty]').forEach(function (box) {
      var input = $('input', box);
      $all('[data-qty-step]', box).forEach(function (btn) {
        btn.addEventListener('click', function () {
          var step = parseInt(btn.getAttribute('data-qty-step'), 10);
          var min = parseInt(input.min || '1', 10), max = parseInt(input.max || '99', 10);
          var v = (parseInt(input.value, 10) || min) + step;
          input.value = Math.max(min, Math.min(max, v));
        });
      });
    });
  }

  function initVariants() {
    var form = $('[data-add-to-cart]');
    if (!form) { return; }
    var price = $('[data-price]'), state = $('[data-stock-state]'), qty = $('#qty'), buy = $('[data-buy-btn]');
    $all('input[name="variant_id"]', form).forEach(function (radio) {
      radio.addEventListener('change', function () {
        var stock = parseInt(radio.getAttribute('data-stock'), 10), low = parseInt(radio.getAttribute('data-low'), 10);
        if (price) { price.textContent = radio.getAttribute('data-price'); }
        if (state) {
          state.innerHTML = '';
          var s = doc.createElement('span');
          if (stock === 0) { s.className = 'stock-out'; s.textContent = 'Rupture de stock'; }
          else if (stock <= low) { s.className = 'stock-low'; s.textContent = 'Plus que ' + stock + ' en stock'; }
          else { s.className = 'stock-ok'; s.textContent = 'En stock'; }
          state.appendChild(s);
        }
        if (qty) {
          var maxLine = parseInt(qty.getAttribute('data-max-line'), 10) || 50;
          var max = Math.max(1, Math.min(maxLine, stock));
          qty.max = max;
          if (parseInt(qty.value, 10) > max) { qty.value = max; }
        }
        if (buy) { buy.disabled = stock === 0; }
      });
    });
  }

  function initAddToCart() {
    var form = $('[data-add-to-cart]');
    if (!form || !window.fetch || !window.FormData) { return; }
    var feedback = $('[data-cart-feedback]', form);
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var btn = $('[data-buy-btn]', form);
      if (btn) { btn.disabled = true; }
      fetch(form.action, {
        method: 'POST',
        body: new FormData(form),
        headers: { 'Accept': 'application/json', 'X-CSRF-Token': csrf(form), 'X-Requested-With': 'fetch' },
        credentials: 'same-origin'
      }).then(function (res) {
        return res.json().then(function (data) { return { ok: res.ok, data: data }; });
      }).then(function (r) {
        var msg = r.data.message || r.data.error || 'Erreur';
        if (feedback) {
          feedback.textContent = msg;
          feedback.className = 'form-feedback ' + (r.ok ? 'is-ok' : 'is-error');
          if (r.ok) {
            var a = doc.createElement('a');
            a.href = '/panier'; a.textContent = ' Voir le panier';
            feedback.appendChild(a);
          }
        }
        announce(msg);
        if (r.ok && typeof r.data.count === 'number') {
          $all('[data-cart-count]').forEach(function (c) { c.textContent = r.data.count; c.setAttribute('aria-label', r.data.count + ' article(s)'); });
        }
      }).catch(function () {
        form.submit();
      }).then(function () { if (btn) { btn.disabled = false; } });
    });
  }

  function initGallery() {
    var main = $('[data-gallery-main]');
    if (!main) { return; }
    $all('[data-gallery-thumb]').forEach(function (t) {
      t.addEventListener('click', function () {
        main.src = t.getAttribute('data-gallery-thumb');
        main.alt = t.getAttribute('data-alt') || '';
        $all('[data-gallery-thumb]').forEach(function (o) { o.removeAttribute('aria-current'); });
        t.setAttribute('aria-current', 'true');
      });
    });
  }

  function initCheckout() {
    var form = $('[data-checkout]');
    if (!form) { return; }
    var steps = $all('[data-step]', form);
    var indicators = $all('[data-step-indicator]');
    var methodsByZone = {};
    try { methodsByZone = JSON.parse(form.getAttribute('data-methods-by-zone') || '{}'); } catch (err) { methodsByZone = {}; }
    var current = 1;
    form.classList.add('is-stepped');

    function show(n, quiet) {
      current = n;
      steps.forEach(function (s) { s.classList.toggle('is-active', parseInt(s.getAttribute('data-step'), 10) === n); });
      indicators.forEach(function (li) {
        var i = parseInt(li.getAttribute('data-step-indicator'), 10);
        li.classList.toggle('is-active', i === n);
        li.classList.toggle('is-done', i < n);
        if (i === n) { li.setAttribute('aria-current', 'step'); } else { li.removeAttribute('aria-current'); }
      });
      var active = steps[n - 1];
      if (active && !quiet) {
        var legend = $('legend', active);
        if (legend) { legend.setAttribute('tabindex', '-1'); legend.focus({ preventScroll: true }); }
        active.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    }

    function validStep(n) {
      var step = steps[n - 1], ok = true, first = null;
      $all('input, select, textarea', step).forEach(function (el) {
        if (el.closest('[hidden]') || el.disabled) { return; }
        if (!el.checkValidity()) { ok = false; if (!first) { first = el; } el.setAttribute('aria-invalid', 'true'); }
        else if (el.type !== 'radio') { el.removeAttribute('aria-invalid'); }
      });
      if (!ok && first) { first.reportValidity(); }
      return ok;
    }

    $all('[data-step-next]', form).forEach(function (b) {
      b.addEventListener('click', function () { if (validStep(current)) { show(Math.min(steps.length, current + 1)); } });
    });
    $all('[data-step-prev]', form).forEach(function (b) {
      b.addEventListener('click', function () { show(Math.max(1, current - 1)); });
    });

    // Adresse facultative pour le retrait
    var addressFields = $('[data-address-fields]', form), pickupNote = $('[data-pickup-note]', form);
    function selectedZone() { return $('input[name="zone_id"]:checked', form); }
    function applyZone() {
      var z = selectedZone();
      var pickup = z && z.getAttribute('data-pickup') === '1';
      if (addressFields) {
        addressFields.hidden = !!pickup;
        ['city', 'address'].forEach(function (n) { var el = form.elements[n]; if (el) { el.required = !pickup; } });
      }
      if (pickupNote) { pickupNote.hidden = !pickup; }
      var allowed = z ? (methodsByZone[z.value] || []) : null;
      $all('[data-method]', form).forEach(function (card) {
        var input = $('input', card);
        var ok = !allowed || allowed.indexOf(card.getAttribute('data-method')) !== -1;
        card.hidden = !ok;
        input.disabled = !ok;
        if (!ok && input.checked) { input.checked = false; }
      });
      if (z) { estimate(z.value); }
    }

    var expected = $('[data-expected-total]', form);
    function setText(sel, txt) { var el = $(sel); if (el) { el.textContent = txt; } }
    function estimate(zoneId) {
      if (!window.fetch) { return; }
      fetch(form.getAttribute('data-estimate-url') + '?zone_id=' + encodeURIComponent(zoneId), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (!d || !d.ok) { return; }
          setText('[data-total-subtotal]', d.formatted.subtotal);
          setText('[data-total-discount]', d.formatted.discount);
          setText('[data-total-shipping]', d.formatted.shipping);
          setText('[data-total-total]', d.formatted.total);
          if (expected) { expected.value = d.total; }
        }).catch(function () {});
    }
    $all('input[name="zone_id"]', form).forEach(function (r) { r.addEventListener('change', applyZone); });
    applyZone();

    // Adresses enregistrées
    var saved = $('[data-saved-address]', form);
    if (saved) {
      saved.addEventListener('change', function () {
        var o = saved.options[saved.selectedIndex];
        if (!o || !o.value) { return; }
        [['city', 'data-city'], ['district', 'data-district'], ['address', 'data-address'], ['landmark', 'data-landmark']].forEach(function (p) {
          if (form.elements[p[0]]) { form.elements[p[0]].value = o.getAttribute(p[1]) || ''; }
        });
      });
    }

    // Ouvre l'étape contenant la première erreur serveur
    var firstError = $('[aria-invalid="true"], .field-error', form);
    var start = 1;
    if (firstError) {
      var holder = firstError.closest('[data-step]');
      if (holder) { start = parseInt(holder.getAttribute('data-step'), 10); }
    } else if ($('.error-summary', form)) {
      start = steps.length;
    }
    show(start, true);

    // Anti double-clic
    form.addEventListener('submit', function (e) {
      if (form.getAttribute('data-submitting') === '1') { e.preventDefault(); return; }
      if (!form.checkValidity()) {
        e.preventDefault();
        for (var i = 1; i <= steps.length; i++) { if (!validStep(i)) { show(i); return; } }
        return;
      }
      form.setAttribute('data-submitting', '1');
      var b = $('[data-submit-order]', form);
      if (b) { b.textContent = 'Envoi en cours…'; b.setAttribute('aria-disabled', 'true'); }
    });
  }

  function initConsent() {
    var body = doc.body, provider = body.getAttribute('data-analytics'), id = body.getAttribute('data-analytics-id');
    var box = $('[data-consent]');
    if (!provider || !id || !box) { return; }
    var choice = (doc.cookie.match(/(?:^|; )mpci_consent=([^;]+)/) || [])[1];
    function load() {
      var s = doc.createElement('script');
      s.defer = true;
      if (provider === 'plausible') {
        s.src = 'https://plausible.io/js/script.js';
        s.setAttribute('data-domain', id);
      } else if (provider === 'ga4') {
        s.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(id);
        window.dataLayer = window.dataLayer || [];
        window.gtag = function () { window.dataLayer.push(arguments); };
        window.gtag('js', new Date());
        window.gtag('config', id, { anonymize_ip: true });
      }
      doc.head.appendChild(s);
    }
    function remember(v) { doc.cookie = 'mpci_consent=' + v + '; Max-Age=' + (180 * 86400) + '; Path=/; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : ''); }
    if (choice === 'yes') { load(); return; }
    if (choice === 'no') { return; }
    box.hidden = false;
    $('[data-consent-accept]', box).addEventListener('click', function () { remember('yes'); box.hidden = true; load(); });
    $('[data-consent-refuse]', box).addEventListener('click', function () { remember('no'); box.hidden = true; });
  }
})();
