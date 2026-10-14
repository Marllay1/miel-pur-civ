/* Miel Pur de Côte d'Ivoire — JavaScript natif, amélioration progressive (le site fonctionne sans JS). */
(function () {
  'use strict';
  var doc = document;
  var root = doc.documentElement;
  root.classList.add('js');
  var i18n = {};
  try { i18n = JSON.parse(root.getAttribute('data-i18n') || '{}'); } catch (e) { i18n = {}; }

  function $(sel, ctx) { return (ctx || doc).querySelector(sel); }
  function $all(sel, ctx) { return Array.prototype.slice.call((ctx || doc).querySelectorAll(sel)); }
  function csrf(form) { var i = form && form.querySelector('input[name="_csrf"]'); return i ? i.value : ''; }
  function announce(msg) { var r = $('[data-live-region]'); if (r) { r.textContent = ''; setTimeout(function () { r.textContent = msg; }, 50); } }
  function setCookie(name, value, days) {
    doc.cookie = name + '=' + value + '; Max-Age=' + (days * 86400) + '; Path=/; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
  }

  var toastTimer;
  function toast(message, ok, link) {
    var t = $('[data-toast]');
    if (!t) { return; }
    t.innerHTML = '';
    t.classList.toggle('is-error', !ok);
    var svg = doc.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('class', 'icon'); svg.setAttribute('viewBox', '0 0 24 24'); svg.setAttribute('aria-hidden', 'true');
    var p = doc.createElementNS('http://www.w3.org/2000/svg', 'path');
    p.setAttribute('d', ok ? 'm5 12.5 4.5 4.5L19 7.5' : 'M12 8v5M12 16.5v.2');
    svg.appendChild(p);
    t.appendChild(svg);
    var span = doc.createElement('span'); span.textContent = message; t.appendChild(span);
    if (link) { var a = doc.createElement('a'); a.href = link.href; a.textContent = link.label; t.appendChild(a); }
    t.classList.add('is-visible');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { t.classList.remove('is-visible'); }, 4200);
  }

  doc.addEventListener('DOMContentLoaded', function () {
    initNavbar();
    initTheme();
    var summary = $('[data-error-summary]');
    if (summary) { summary.focus(); }
    $all('form[data-confirm]').forEach(function (f) {
      f.addEventListener('submit', function (e) { if (!window.confirm(f.getAttribute('data-confirm'))) { e.preventDefault(); } });
    });
    $all('[data-print]').forEach(function (b) { b.addEventListener('click', function () { window.print(); }); });
    if (location.hash === '#recherche') { var s = $('[data-search-input]'); if (s) { s.focus(); } }
    initQty();
    initVariants();
    initAddToCart();
    initAutosubmit();
    initGallery();
    initCheckout();
    initGps();
    initPolling();
    initConsent();
    initLangMenu();
    $all('[data-carousel]').forEach(initCarousel);
    initImagePreviews();
    initAnimations();
  });

  /* Sélecteur de langue : fermeture au clic extérieur et avec Échap (le menu fonctionne aussi sans JavaScript). */
  /*
   * Carrousel de l'accueil : coulissage horizontal automatique, flèches, indicateurs, clavier (← →), glissement du doigt.
   * Une navigation manuelle relance le minuteur (le défilement continue) ; arrêt momentané pendant le survol, le focus clavier,
   * le toucher et quand l'onglet est masqué. Si le visiteur demande de réduire les animations, le changement est instantané.
   */
  function initCarousel(root) {
    var slides = $all('[data-slide]', root);
    var controls = $('[data-carousel-controls]', root);
    var track = $('[data-carousel-track]', root);
    if (slides.length < 2 || !track) { return; }
    root.classList.add('is-enhanced');
    if (controls) { controls.hidden = false; }
    var dots = $all('[data-carousel-dot]', root);
    var delay = parseInt(root.getAttribute('data-autoplay') || '0', 10);
    var index = 0, timer = null, holds = { hover: false, focus: false, touch: false, hidden: false };

    function preload(i) {
      var img = $('img', slides[i]);
      if (img && img.loading === 'lazy') { img.loading = 'eager'; }
    }
    function show(i, announce) {
      i = (i + slides.length) % slides.length;
      if (i === index) { return; }
      slides[index].classList.remove('is-active');
      slides[index].setAttribute('aria-hidden', 'true');
      index = i;
      preload(index);
      slides[index].classList.add('is-active');
      slides[index].removeAttribute('aria-hidden');
      track.style.transform = 'translateX(' + (-100 * index) + '%)';
      dots.forEach(function (d, k) { if (k === index) { d.setAttribute('aria-current', 'true'); } else { d.removeAttribute('aria-current'); } });
      preload((index + 1) % slides.length);
      if (announce) { var live = $('[data-live-region]'); if (live) { live.textContent = slides[index].getAttribute('aria-label') || ''; } }
    }
    function schedule() {
      clearTimeout(timer);
      if (delay && !holds.hover && !holds.focus && !holds.touch && !holds.hidden) {
        timer = setTimeout(function () { show(index + 1); schedule(); }, delay);
      }
    }
    function go(i) { show(i, true); schedule(); }
    var prev = $('[data-carousel-prev]', root), next = $('[data-carousel-next]', root);
    if (prev) { prev.addEventListener('click', function () { go(index - 1); }); }
    if (next) { next.addEventListener('click', function () { go(index + 1); }); }
    dots.forEach(function (d) { d.addEventListener('click', function () { go(parseInt(d.getAttribute('data-carousel-dot'), 10)); }); });
    root.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowLeft') { go(index - 1); }
      if (e.key === 'ArrowRight') { go(index + 1); }
    });
    root.addEventListener('mouseenter', function () { holds.hover = true; schedule(); });
    root.addEventListener('mouseleave', function () { holds.hover = false; schedule(); });
    root.addEventListener('focusin', function () { holds.focus = true; schedule(); });
    root.addEventListener('focusout', function (e) { if (!root.contains(e.relatedTarget)) { holds.focus = false; schedule(); } });
    doc.addEventListener('visibilitychange', function () { holds.hidden = doc.hidden; schedule(); });
    var startX = null;
    root.addEventListener('touchstart', function (e) { startX = e.touches[0].clientX; holds.touch = true; schedule(); }, { passive: true });
    root.addEventListener('touchend', function (e) {
      holds.touch = false;
      if (startX !== null) {
        var dx = e.changedTouches[0].clientX - startX;
        if (Math.abs(dx) > 40) { show(index + (dx < 0 ? 1 : -1), true); }
      }
      startX = null;
      schedule();
    }, { passive: true });
    preload(1);
    schedule();
  }

  /* Aperçu local d'une image avant téléversement (administration) : lecture du fichier dans le navigateur, rien n'est envoyé. */
  function initImagePreviews() {
    $all('input[type="file"][data-preview]').forEach(function (input) {
      var target = doc.getElementById(input.getAttribute('data-preview'));
      if (!target || !window.FileReader) { return; }
      input.addEventListener('change', function () {
        var file = input.files && input.files[0];
        if (!file || !/^image\/(png|jpeg|webp)$/.test(file.type)) { target.hidden = true; return; }
        var reader = new FileReader();
        reader.onload = function () { target.src = String(reader.result); target.hidden = false; };
        reader.readAsDataURL(file);
      });
    });
  }

  function initLangMenu() {
    var menus = $all('[data-lang-menu]');
    if (!menus.length) { return; }
    doc.addEventListener('click', function (e) {
      menus.forEach(function (m) { if (m.open && !m.contains(e.target)) { m.open = false; } });
    });
    doc.addEventListener('keydown', function (e) {
      if (e.key !== 'Escape') { return; }
      menus.forEach(function (m) { if (m.open) { m.open = false; var s = m.querySelector('summary'); if (s) { s.focus(); } } });
    });
  }

  /*
   * Animations d'apparition au défilement (AOS) sur les pages publiques. Les attributs sont posés ici, par JavaScript :
   * sans JavaScript ou avec « réduire les animations », tout le contenu reste visible immédiatement.
   */
  function initAnimations() {
    if (!window.AOS || !doc.body.classList.contains('site')) { return; }
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) { return; }
    var rules = [
      ['main .page-head, main .section-head, main .hero-copy, main .story-card, main .pro-card, main .content-card, main .form-card, main .error-page, main .empty-state', 'fade-up'],
      ['main .hero-photo, main .story-photo, main .page-banner, main .product-gallery', 'fade-in'],
      ['main .product-card, main .tile, main .gallery-item, main .steps li, main .group, main .disclosure, main .credits-list li, main .product-info', 'fade-up']
    ];
    rules.forEach(function (rule) {
      $all(rule[0]).forEach(function (el, i) {
        if (el.hasAttribute('data-aos')) { return; }
        el.setAttribute('data-aos', rule[1]);
        var siblings = el.parentElement ? el.parentElement.children.length : 1;
        if (siblings > 1) { el.setAttribute('data-aos-delay', String(Math.min(Array.prototype.indexOf.call(el.parentElement.children, el), 5) * 60)); }
      });
    });
    window.AOS.init({ once: true, duration: 550, easing: 'ease-out-cubic', offset: 24 });
  }

  function initNavbar() {
    $all('.nav-toggle[aria-controls]').forEach(function (toggle) {
      var nav = doc.getElementById(toggle.getAttribute('aria-controls'));
      if (!nav) { return; }
      toggle.addEventListener('click', function () {
        var open = nav.classList.toggle('is-open');
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      });
    });
    var bar = $('[data-navbar]');
    if (!bar) { return; }
    var onScroll = function () { bar.classList.toggle('is-scrolled', window.scrollY > 4); };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }

  /* Thème : bascule instantanée côté client, mémorisée dans un cookie lu par le serveur (aucun flash au chargement). */
  function effectiveTheme() {
    var forced = root.getAttribute('data-theme');
    if (forced) { return forced; }
    return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  }
  function applyTheme(value) {
    if (value === 'auto') { root.removeAttribute('data-theme'); setCookie('mpci_theme', '', -1); }
    else { root.setAttribute('data-theme', value); setCookie('mpci_theme', value, 365); }
    $all('[data-theme-set]').forEach(function (b) { b.setAttribute('aria-pressed', b.getAttribute('data-theme-set') === value ? 'true' : 'false'); });
  }
  function initTheme() {
    $all('[data-theme-toggle]').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        var next = effectiveTheme() === 'dark' ? 'light' : 'dark';
        applyTheme(next);
        announce(next === 'dark' ? (i18n.themeDark || 'Dark') : (i18n.themeLight || 'Light'));
      });
    });
    $all('[data-theme-set]').forEach(function (btn) {
      btn.addEventListener('click', function (e) { e.preventDefault(); applyTheme(btn.getAttribute('data-theme-set')); });
    });
  }

  function initQty() {
    $all('[data-qty]').forEach(function (box) {
      var input = $('input', box);
      $all('[data-qty-step]', box).forEach(function (btn) {
        btn.addEventListener('click', function () {
          var step = parseInt(btn.getAttribute('data-qty-step'), 10);
          var min = parseInt(input.min || '1', 10), max = parseInt(input.max || '99', 10);
          var v = (parseInt(input.value, 10) || 0) + step;
          input.value = Math.max(min, Math.min(max, v));
          input.dispatchEvent(new Event('change', { bubbles: true }));
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
        var conv = $('[data-conversion]:not(input)');
        if (conv && radio.getAttribute('data-conversion')) { conv.textContent = radio.getAttribute('data-conversion'); }
        if (state) {
          var cls = stock === 0 ? 'stock-out' : (stock <= low ? 'stock-low' : 'stock-ok');
          state.className = 'stock-state ' + cls;
          state.textContent = stock === 0 ? radio.getAttribute('data-label-out')
            : (stock <= low ? radio.getAttribute('data-label-low').replace('__N__', stock) : radio.getAttribute('data-label-ok'));
        }
        if (qty) {
          var maxLine = parseInt(qty.getAttribute('data-max-line'), 10) || 50;
          qty.max = Math.max(1, Math.min(maxLine, stock));
          if (parseInt(qty.value, 10) > parseInt(qty.max, 10)) { qty.value = qty.max; }
        }
        if (buy) { buy.disabled = stock === 0; }
      });
    });
  }

  function updateCartCount(count) {
    $all('[data-cart-count]').forEach(function (c) {
      c.textContent = count;
      c.setAttribute('data-count', count);
      c.classList.remove('bump'); void c.offsetWidth; c.classList.add('bump');
    });
  }

  function initAddToCart() {
    var form = $('[data-add-to-cart]');
    if (!form || !window.fetch || !window.FormData) { return; }
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var btn = $('[data-buy-btn]', form);
      if (btn) { btn.disabled = true; }
      fetch(form.action, {
        method: 'POST', body: new FormData(form), credentials: 'same-origin',
        headers: { 'Accept': 'application/json', 'X-CSRF-Token': csrf(form), 'X-Requested-With': 'fetch' }
      }).then(function (res) {
        return res.json().then(function (data) { return { ok: res.ok, data: data }; });
      }).then(function (r) {
        var msg = r.data.message || r.data.error || i18n.error;
        toast(msg, r.ok, r.ok ? { href: (root.lang === 'en' ? '/en' : '') + '/panier', label: i18n.viewCart } : null);
        announce(msg);
        if (r.ok && typeof r.data.count === 'number') { updateCartCount(r.data.count); }
      }).catch(function () { form.submit(); })
        .then(function () { if (btn) { btn.disabled = false; } });
    });
  }

  /* Panier : envoi automatique quand la quantité change (le bouton reste disponible sans JS). */
  function initAutosubmit() {
    $all('form[data-autosubmit]').forEach(function (form) {
      var btn = $('[data-autosubmit-btn]', form);
      if (btn) { btn.classList.add('sr-only'); }
      var timer;
      $all('input[name="qty"]', form).forEach(function (input) {
        input.addEventListener('change', function () { clearTimeout(timer); timer = setTimeout(function () { form.submit(); }, 450); });
      });
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
        var index = t.getAttribute('data-index');
        $all('[data-gallery-credit]').forEach(function (c) { c.hidden = c.getAttribute('data-gallery-credit') !== index; });
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
    $all('[data-step-next]', form).forEach(function (b) { b.addEventListener('click', function () { if (validStep(current)) { show(Math.min(steps.length, current + 1)); } }); });
    $all('[data-step-prev]', form).forEach(function (b) { b.addEventListener('click', function () { show(Math.max(1, current - 1)); }); });

    var addressFields = $('[data-address-fields]', form), pickupNote = $('[data-pickup-note]', form), expected = $('[data-expected-total]', form);
    function selectedZone() { return $('input[name="zone_id"]:checked', form); }
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
    var depositByZone = {};
    try { depositByZone = JSON.parse(form.getAttribute('data-deposit-by-zone') || '{}'); } catch (err) { depositByZone = {}; }
    var payMode = $('[data-pay-mode]', form);
    // Acompte : proposé si la zone le permet (montant calculé par le serveur) et si le moyen choisi est encaissé avant livraison.
    function applyDeposit() {
      if (!payMode) { return; }
      var z = selectedZone(), checked = $('input[name="payment_method"]:checked', form);
      var d = z ? depositByZone[z.value] : null;
      var on = !!d && (!checked || checked.getAttribute('data-deposit-ok') === '1');
      payMode.hidden = !on;
      if (d) { setText('[data-deposit-amount]', d.deposit); setText('[data-deposit-balance]', d.balance); }
      if (!on) { var full = $('input[name="pay_mode"][value="full"]', form); if (full) { full.checked = true; } }
    }
    function applyMethod() {
      var checked = $('input[name="payment_method"]:checked', form);
      $all('[data-phone-for]', form).forEach(function (box) {
        var on = !!checked && box.getAttribute('data-phone-for') === checked.value;
        box.hidden = !on;
        var input = $('input', box);
        if (input) { input.required = on; }
      });
      applyDeposit();
    }
    function applyZone() {
      var z = selectedZone();
      var pickup = z && z.getAttribute('data-pickup') === '1';
      if (addressFields) {
        addressFields.hidden = !!pickup;
        ['city', 'address'].forEach(function (n) { var el = form.elements[n]; if (el) { el.required = !pickup && !(n === 'address' && form.elements.gps_lat && form.elements.gps_lat.value); } });
      }
      if (pickupNote) { pickupNote.hidden = !pickup; }
      var allowed = z ? (methodsByZone[z.value] || []) : null;
      $all('[data-method]', form).forEach(function (card) {
        var input = $('input', card);
        var ok = !allowed || allowed.indexOf(card.getAttribute('data-method')) !== -1;
        card.classList.toggle('is-hidden', !ok);
        input.disabled = !ok;
        if (!ok && input.checked) { input.checked = false; }
      });
      applyMethod();
      if (z) { estimate(z.value); }
    }
    $all('input[name="zone_id"]', form).forEach(function (r) { r.addEventListener('change', applyZone); });
    $all('input[name="payment_method"]', form).forEach(function (r) { r.addEventListener('change', applyMethod); });
    applyZone();

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

    var firstError = $('[aria-invalid="true"], .field-error', form);
    var start = 1;
    if (firstError) {
      var holder = firstError.closest('[data-step]');
      if (holder) { start = parseInt(holder.getAttribute('data-step'), 10); }
    } else if ($('.error-summary', form)) { start = steps.length; }
    show(start, true);

    form.addEventListener('submit', function (e) {
      if (form.getAttribute('data-submitting') === '1') { e.preventDefault(); return; }
      if (!form.checkValidity()) {
        e.preventDefault();
        for (var i = 1; i <= steps.length; i++) { if (!validStep(i)) { show(i); return; } }
        return;
      }
      form.setAttribute('data-submitting', '1');
      var b = $('[data-submit-order]', form);
      if (b) { b.lastChild.textContent = ' ' + (i18n.sending || '…'); b.setAttribute('aria-disabled', 'true'); }
    });
  }

  /* Paiement validé sur le téléphone (MTN MoMo) : interrogation régulière, le serveur vérifie auprès du prestataire. */
  /*
   * Localisation à la commande (facultative) : la position n'est demandée qu'au clic sur « Utiliser ma position GPS ».
   * Carte Leaflet (si activée et chargée) avec un marqueur déplaçable pour corriger la position ; adresse estimée seulement
   * si un service de géocodage est configuré côté serveur. En cas d'échec, la saisie manuelle reste toujours possible.
   */
  function initGps() {
    var box = $('[data-gps]');
    if (!box) { return; }
    var form = box.closest('form');
    var start = $('[data-gps-start]', box), status = $('[data-gps-status]', box), mapEl = $('[data-gps-map]', box);
    var coordsEl = $('[data-gps-coords]', box), addrEl = $('[data-gps-address]', box), clear = $('[data-gps-clear]', box);
    var inLat = $('[data-gps-lat]', box), inLng = $('[data-gps-lng]', box), inAcc = $('[data-gps-accuracy]', box), inAdj = $('[data-gps-adjusted]', box);
    var bounds = {};
    try { bounds = JSON.parse(box.getAttribute('data-bounds') || '{}'); } catch (err) { bounds = {}; }
    var map = null, marker = null, tileErrors = 0;
    function msg(key) { return box.getAttribute('data-msg-' + key) || ''; }
    function say(text, kind) {
      status.textContent = text;
      status.hidden = !text;
      status.className = 'notice small' + (kind === 'error' ? ' notice-amber' : (kind === 'ok' ? ' notice-green' : ''));
    }
    function inside(lat, lng) {
      return !bounds.lat_min || (lat >= bounds.lat_min && lat <= bounds.lat_max && lng >= bounds.lng_min && lng <= bounds.lng_max);
    }
    function addressRequired() {
      var addr = form && form.elements.address, zone = form && $('input[name="zone_id"]:checked', form);
      var pickup = zone && zone.getAttribute('data-pickup') === '1';
      if (addr) { addr.required = !pickup && !inLat.value; }
    }
    function estimate(lat, lng) {
      if (box.getAttribute('data-geocoder') !== '1' || !window.fetch) { return; }
      fetch(box.getAttribute('data-geocode-url') + '?lat=' + encodeURIComponent(lat) + '&lng=' + encodeURIComponent(lng), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (d) { if (d && d.ok && d.address) { addrEl.textContent = msg('estimated') + ' ' + d.address; addrEl.hidden = false; } })
        .catch(function () {});
    }
    function setPosition(lat, lng, acc, adjusted) {
      inLat.value = lat.toFixed(6);
      inLng.value = lng.toFixed(6);
      if (acc !== null) { inAcc.value = Math.round(acc); }
      if (adjusted) { inAdj.value = '1'; }
      coordsEl.textContent = msg('coords') + ' ' + inLat.value + ', ' + inLng.value + (inAcc.value && !adjusted ? ' — ' + msg('accuracy').replace(':m', inAcc.value) : '');
      coordsEl.hidden = false;
      clear.hidden = false;
      addrEl.hidden = true;
      if (inside(lat, lng)) { say(msg('found'), 'ok'); } else { say(msg('outside'), 'error'); }
      addressRequired();
      estimate(inLat.value, inLng.value);
    }
    function showMap(lat, lng) {
      if (box.getAttribute('data-map') !== '1') { return; }
      if (!window.L || !box.getAttribute('data-tiles')) { say(msg('map'), 'error'); return; }
      mapEl.hidden = false;
      if (!map) {
        window.L.Icon.Default.imagePath = '/assets/vendor/leaflet/images/';
        map = window.L.map(mapEl, { scrollWheelZoom: false }).setView([lat, lng], 17);
        var layer = window.L.tileLayer(box.getAttribute('data-tiles'), { maxZoom: 19, attribution: box.getAttribute('data-attribution') || '' }).addTo(map);
        layer.on('tileerror', function () { tileErrors++; if (tileErrors === 4) { say(msg('map'), 'error'); } });
        marker = window.L.marker([lat, lng], { draggable: true, keyboard: true }).addTo(map);
        marker.on('dragend', function () { var p = marker.getLatLng(); setPosition(p.lat, p.lng, null, true); });
        map.on('click', function (e) { marker.setLatLng(e.latlng); setPosition(e.latlng.lat, e.latlng.lng, null, true); });
      } else {
        marker.setLatLng([lat, lng]);
        map.setView([lat, lng], 17);
        map.invalidateSize();
      }
    }
    start.addEventListener('click', function () {
      if (!('geolocation' in navigator)) { say(msg('unsupported'), 'error'); return; }
      if (window.isSecureContext === false) { say(msg('insecure'), 'error'); return; }
      say(msg('searching'));
      start.disabled = true;
      navigator.geolocation.getCurrentPosition(function (pos) {
        start.disabled = false;
        inAdj.value = '';
        setPosition(pos.coords.latitude, pos.coords.longitude, pos.coords.accuracy, false);
        showMap(pos.coords.latitude, pos.coords.longitude);
      }, function (err) {
        start.disabled = false;
        say(err && err.code === 1 ? msg('denied') : (err && err.code === 3 ? msg('timeout') : msg('unavailable')), 'error');
      }, { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 });
    });
    clear.addEventListener('click', function () {
      inLat.value = inLng.value = inAcc.value = inAdj.value = '';
      coordsEl.hidden = addrEl.hidden = mapEl.hidden = clear.hidden = true;
      say('');
      addressRequired();
    });
    if (form) { $all('input[name="zone_id"]', form).forEach(function (r) { r.addEventListener('change', addressRequired); }); }
    // Position déjà validée (formulaire renvoyé après une erreur) : affichage restauré, sans nouvelle demande au navigateur.
    if (inLat.value && inLng.value && !isNaN(parseFloat(inLat.value)) && !isNaN(parseFloat(inLng.value))) {
      var lat0 = parseFloat(inLat.value), lng0 = parseFloat(inLng.value);
      setPosition(lat0, lng0, null, inAdj.value === '1');
      showMap(lat0, lng0);
    }
    addressRequired();
  }

  function initPolling() {
    var box = $('[data-poll-url]');
    if (!box || !window.fetch) { return; }
    var url = box.getAttribute('data-poll-url'), status = $('[data-poll-status]', box), tries = 0;
    function tick() {
      tries++;
      fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (d.done) { location.href = d.redirect; return; }
          if (tries < 60) { setTimeout(tick, 5000); } else if (status) { status.textContent = ''; }
        }).catch(function () { if (tries < 60) { setTimeout(tick, 8000); } });
    }
    setTimeout(tick, 4000);
  }

  function initConsent() {
    var body = doc.body, provider = body.getAttribute('data-analytics'), id = body.getAttribute('data-analytics-id');
    var box = $('[data-consent]');
    if (!provider || !id || !box) { return; }
    var choice = (doc.cookie.match(/(?:^|; )mpci_consent=([^;]+)/) || [])[1];
    function load() {
      var s = doc.createElement('script');
      s.defer = true;
      if (provider === 'plausible') { s.src = 'https://plausible.io/js/script.js'; s.setAttribute('data-domain', id); }
      else if (provider === 'ga4') {
        s.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(id);
        window.dataLayer = window.dataLayer || [];
        window.gtag = function () { window.dataLayer.push(arguments); };
        window.gtag('js', new Date());
        window.gtag('config', id, { anonymize_ip: true });
      }
      doc.head.appendChild(s);
    }
    if (choice === 'yes') { load(); return; }
    if (choice === 'no') { return; }
    box.hidden = false;
    $('[data-consent-accept]', box).addEventListener('click', function () { setCookie('mpci_consent', 'yes', 180); box.hidden = true; load(); });
    $('[data-consent-refuse]', box).addEventListener('click', function () { setCookie('mpci_consent', 'no', 180); box.hidden = true; });
  }
})();
