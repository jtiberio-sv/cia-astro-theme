/**
 * Atualiza o nudge "Faltam R$ X para frete gratis" via AJAX quando user
 * muda quantidade / remove item / aplica cupom no carrinho ou checkout.
 *
 * Backend retorna HTML do nudge renderizado pelo cdm_render_free_shipping_nudge.
 * Ouvinte de eventos WC nativos.
 */
(function ($) {
  if (typeof window === "undefined" || !window.jQuery) return;

  function refresh() {
    $.get(window.cdmAjaxUrl || "/wp-admin/admin-ajax.php", { action: "cdm_freeship_nudge" })
      .done(function (html) {
        var $current = $(".cdm-freeship-nudge");
        if (!$current.length || !html) return;
        var $new = $(html);
        $current.replaceWith($new);
      });
  }

  $(document.body).on(
    "updated_wc_div updated_cart_totals updated_checkout applied_coupon removed_coupon removed_from_cart",
    function () {
      refresh();
    }
  );
})(window.jQuery);
