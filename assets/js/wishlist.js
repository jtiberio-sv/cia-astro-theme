/* wishlist.js — toggle heart no PDP/loop + remove na pagina /favoritos.
 *
 * Depende de window.cdmWishlist (localizado em PHP):
 *   { restUrl, nonce, loggedIn, loginUrl, ids: number[] }
 *
 * Comportamentos:
 *  - clique em .cdm-wishlist-toggle: POST /toggle (se logado) ou redirect login
 *  - clique em .cdm-wishlist-remove: DELETE /<pid> e some o card
 */
(function () {
  if (!window.cdmWishlist) return;
  var cfg = window.cdmWishlist;

  function setActive(btn, active) {
    btn.classList.toggle('is-active', active);
    btn.setAttribute('aria-pressed', active ? 'true' : 'false');
    var heart = btn.querySelector('.heart');
    if (heart) heart.setAttribute('fill', active ? 'currentColor' : 'none');
    var lbl = btn.querySelector('.lbl');
    if (lbl) lbl.textContent = active ? 'Favoritado' : 'Favoritar';
  }

  function toast(msg) {
    var t = document.createElement('div');
    t.className = 'cdm-toast';
    t.textContent = msg;
    t.style.cssText = 'position:fixed;bottom:24px;left:50%;transform:translateX(-50%);' +
      'background:#0f172a;color:white;padding:10px 18px;border-radius:9999px;font-size:14px;' +
      'z-index:99999;box-shadow:0 4px 12px rgba(0,0,0,0.2);opacity:0;transition:opacity 0.2s;';
    document.body.appendChild(t);
    requestAnimationFrame(function () { t.style.opacity = '1'; });
    setTimeout(function () {
      t.style.opacity = '0';
      setTimeout(function () { t.remove(); }, 250);
    }, 2200);
  }

  // Toggle no PDP/cards
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('.cdm-wishlist-toggle');
    if (!btn) return;
    e.preventDefault();
    var pid = parseInt(btn.dataset.pid, 10);
    if (!pid) return;

    if (!cfg.loggedIn) {
      window.location.href = cfg.loginUrl + '?wl_after=' + pid;
      return;
    }

    btn.disabled = true;
    fetch(cfg.restUrl + '/toggle', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': cfg.nonce,
      },
      body: JSON.stringify({ product_id: pid }),
    })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        btn.disabled = false;
        if (data && typeof data.added !== 'undefined') {
          setActive(btn, data.added);
          toast(data.added ? 'Adicionado aos favoritos!' : 'Removido dos favoritos');
        }
      })
      .catch(function () {
        btn.disabled = false;
        toast('Erro ao salvar. Tente novamente.');
      });
  });

  // Remove na lista
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('.cdm-wishlist-remove');
    if (!btn) return;
    e.preventDefault();
    var pid = parseInt(btn.dataset.pid, 10);
    if (!pid) return;
    var item = btn.closest('.cdm-wishlist-item');
    if (item) {
      item.style.transition = 'opacity 0.2s, transform 0.2s';
      item.style.opacity = '0';
      item.style.transform = 'scale(0.95)';
    }
    fetch(cfg.restUrl + '/' + pid, {
      method: 'DELETE',
      credentials: 'same-origin',
      headers: { 'X-WP-Nonce': cfg.nonce },
    })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (item) {
          setTimeout(function () { item.remove(); }, 200);
        }
        if (data && data.count === 0) {
          setTimeout(function () { window.location.reload(); }, 250);
        }
      })
      .catch(function () {
        if (item) {
          item.style.opacity = '1';
          item.style.transform = 'none';
        }
        toast('Erro ao remover. Recarregue a pagina.');
      });
  });

  // Re-sync ao carregar (caso ids mudem entre paginas)
  document.querySelectorAll('.cdm-wishlist-toggle').forEach(function (btn) {
    var pid = parseInt(btn.dataset.pid, 10);
    if (!pid) return;
    var active = cfg.ids.indexOf(pid) !== -1;
    setActive(btn, active);
  });
})();
