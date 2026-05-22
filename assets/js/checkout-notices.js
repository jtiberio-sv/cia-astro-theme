/* checkout-notices.js — move notices de erro do checkout pra DENTRO do card
 * #order_review (logo acima do botao Finalizar pedido), em vez de ficarem
 * empilhados no topo do form (que com layout 2-col sticky cria espaco
 * estranho). */
(function () {
  if (typeof jQuery === 'undefined') return;
  var $ = jQuery;

  function moveNotices() {
    var $review = $('#order_review');
    if (!$review.length) return;

    // Notices que WC injeta dinamicamente
    var $errors = $('form.checkout > .woocommerce-NoticeGroup, form.checkout > .woocommerce-error, .woocommerce-NoticeGroup-checkout');
    if (!$errors.length) return;

    // Limpa duplicatas que possam ter ficado dentro do #order_review
    $review.find('.cdm-notice-wrap').remove();

    var $wrap = $('<div class="cdm-notice-wrap"></div>').append($errors.clone());
    $review.prepend($wrap);
    $errors.remove();

    // Scroll suave pro card de pedido (que e onde esta agora a notice)
    if (typeof $wrap[0].scrollIntoView === 'function') {
      $wrap[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
  }

  // WC dispara esses eventos quando ha erros de validacao no checkout
  $(document.body).on('checkout_error', moveNotices);
  $(document.body).on('updated_checkout', function () {
    // Re-mover se WC re-renderizou e colocou notice na posicao default
    setTimeout(moveNotices, 100);
  });

  // Tambem corre 1x no load caso ja tenha notice (refresh apos erro)
  $(function () {
    setTimeout(moveNotices, 200);
  });
})();
