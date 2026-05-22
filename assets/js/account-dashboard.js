/**
 * Dashboard my-account: confete burst no 1o acesso do dia.
 * Cookie diario (cdm_dash_seen_YYYY-MM-DD) controla.
 */
(function () {
  document.addEventListener('DOMContentLoaded', function () {
    var welcome = document.querySelector('.cdm-account-welcome');
    if (!welcome) return;

    var cookieKey = welcome.dataset.confettiCookie;
    if (!cookieKey) return;

    // Cookie ja existe? Nao mostra confete
    if (document.cookie.split('; ').some(function (c) { return c.indexOf(cookieKey + '=') === 0; })) {
      return;
    }

    // Marca cookie (TTL: ate meia-noite + 1 dia, simplificando 24h)
    var expires = new Date();
    expires.setTime(expires.getTime() + 24 * 60 * 60 * 1000);
    document.cookie = cookieKey + '=1; expires=' + expires.toUTCString() + '; path=/; SameSite=Lax';

    // Spawn confete
    var burst = welcome.querySelector('.cdm-confetti-burst');
    if (!burst) return;

    var colors = ['#ffc73c', '#ec4899', '#5ebfd6', '#4ade80', '#f97316', '#8b5cf6'];
    var count = 28;
    var frag = document.createDocumentFragment();
    for (var i = 0; i < count; i++) {
      var s = document.createElement('span');
      s.style.left = (Math.random() * 100) + '%';
      s.style.background = colors[Math.floor(Math.random() * colors.length)];
      s.style.animationDelay = (Math.random() * 0.6) + 's';
      s.style.animationDuration = (2.2 + Math.random() * 1.4) + 's';
      s.style.transform = 'rotate(' + (Math.random() * 360) + 'deg)';
      frag.appendChild(s);
    }
    burst.appendChild(frag);
    burst.classList.add('is-active');

    // Limpa DOM apos animacao (libera memoria)
    setTimeout(function () {
      burst.classList.remove('is-active');
      burst.innerHTML = '';
    }, 4500);
  });
})();
