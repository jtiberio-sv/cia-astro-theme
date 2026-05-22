/* checkout-notices.js — garante que notices de erro do checkout sejam:
 *   1) Visiveis (escapam de CSS que pode estar escondendo)
 *   2) Reposicionadas pra dentro do card #order_review (logo acima do
 *      botao Finalizar pedido) — onde o cliente esta olhando
 *   3) Auto-scroll suave pra ficar imediatamente visivel
 *
 * O WC core injeta as notices via $form.prepend() apos receber o JSON
 * response do Ajax checkout. Esse JS escuta o event 'checkout_error' que
 * o WC dispara APOS injetar o HTML, entao podemos pegar e mover.
 */
(function () {
  if (typeof jQuery === 'undefined') return;
  var $ = jQuery;

  function moveNotices() {
    var $review = $('#order_review');
    if (!$review.length) return;

    // Coleta todas as notices possiveis (WC injeta em varios wrappers
    // dependendo do tipo de erro: validation, payment, ajax fail).
    var selectors = [
      'form.checkout > .woocommerce-NoticeGroup',
      'form.checkout > .woocommerce-NoticeGroup-checkout',
      'form.checkout > .woocommerce-error',
      'form.checkout > .woocommerce-info',
      'form.checkout > .woocommerce-message',
      '.woocommerce-checkout > .woocommerce-NoticeGroup',
      '.woocommerce-checkout > .woocommerce-error',
    ];
    var $errors = $(selectors.join(', ')).filter(function () {
      // Nao mover de novo se ja esta dentro do nosso wrapper
      return $(this).closest('.cdm-notice-wrap').length === 0;
    });
    if (!$errors.length) return;

    // Remove wrapper anterior se existia (substitui)
    $review.find('.cdm-notice-wrap').remove();

    var $wrap = $('<div class="cdm-notice-wrap"></div>');
    $errors.each(function () {
      $wrap.append($(this).detach());
    });
    $review.prepend($wrap);

    // Auto-scroll pra notice (sticky no sidebar)
    var top = $wrap.offset().top - 180;
    if (top > 0) {
      $('html, body').animate({ scrollTop: top }, 280);
    }
  }

  // Dispara apos WC injetar notice no DOM (com pequeno delay pra garantir)
  $(document.body).on('checkout_error', function () {
    setTimeout(moveNotices, 50);
  });
  $(document.body).on('updated_checkout', function () {
    setTimeout(moveNotices, 100);
  });
  // Tambem corre no load (refresh apos erro persistido)
  $(function () {
    setTimeout(moveNotices, 200);
  });

  // Garante reposicionamento mesmo se WC chamar update_checkout tarde
  var mo = new MutationObserver(function (mutations) {
    for (var i = 0; i < mutations.length; i++) {
      var m = mutations[i];
      for (var j = 0; j < m.addedNodes.length; j++) {
        var n = m.addedNodes[j];
        if (n.nodeType === 1 && (
          n.classList && (n.classList.contains('woocommerce-NoticeGroup') ||
                          n.classList.contains('woocommerce-error') ||
                          n.classList.contains('woocommerce-NoticeGroup-checkout'))
        )) {
          setTimeout(moveNotices, 20);
          return;
        }
      }
    }
  });
  $(function () {
    var form = document.querySelector('form.checkout');
    if (form) mo.observe(form, { childList: true });
    var co = document.querySelector('.woocommerce-checkout');
    if (co) mo.observe(co, { childList: true });
  });
})();
