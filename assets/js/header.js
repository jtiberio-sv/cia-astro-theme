/* header.js — drawer mobile (abrir/fechar) + integracao com botao de busca mobile. */
(function () {
  var drawer = document.getElementById('cdm-mobile-drawer');
  var burger = document.getElementById('cdm-burger');
  var searchBtn = document.getElementById('cdm-search-mobile');
  var input = document.getElementById('cdm-drawer-search');
  if (!drawer || !burger) return;

  function open(focusSearch) {
    drawer.classList.add('open');
    drawer.setAttribute('aria-hidden', 'false');
    burger.setAttribute('aria-expanded', 'true');
    document.body.classList.add('cdm-drawer-open');
    if (focusSearch && input) setTimeout(function () { input.focus(); }, 50);
  }
  function close() {
    drawer.classList.remove('open');
    drawer.setAttribute('aria-hidden', 'true');
    burger.setAttribute('aria-expanded', 'false');
    document.body.classList.remove('cdm-drawer-open');
  }

  burger.addEventListener('click', function () { open(false); });
  if (searchBtn) searchBtn.addEventListener('click', function () { open(true); });

  drawer.querySelectorAll('[data-cdm-close]').forEach(function (el) {
    el.addEventListener('click', close);
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && drawer.classList.contains('open')) close();
  });

  // Atualiza contador do carrinho via WC fragments (refresh_fragments).
  // Fragment espera o seletor `.cdm-cart-count` e injeta a quantidade.
  if (typeof jQuery !== 'undefined') {
    jQuery(document.body).on('updated_cart_totals wc_fragments_refreshed added_to_cart removed_from_cart', function () {
      var badge = document.querySelector('.cdm-cart-count');
      if (!badge) return;
      // wc cart hash mantem qty no localStorage como `wc_cart_hash_<key>`. Mais robusto: ler do fragment server-side.
      // Por ora, fazemos request ao Store API:
      fetch('/wp-json/wc/store/v1/cart', { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          var qty = (d && d.items_count) || 0;
          badge.textContent = qty;
          if (qty > 0) badge.classList.add('has-items');
          else badge.classList.remove('has-items');
        })
        .catch(function () {});
    });
    // Trigger inicial no load
    jQuery(document.body).trigger('wc_fragments_refreshed');
  }
})();
