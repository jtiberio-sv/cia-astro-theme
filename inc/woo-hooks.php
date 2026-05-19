<?php
/**
 * WooCommerce hooks: injetar trust strips, badges, etc nas paginas WC.
 *
 * Fase 0: vazio. Fase 3+ vai popular com trust strip cart/checkout, badges,
 * mensagens contextuais, etc.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Move o cross-sell do carrinho da sidebar para abaixo do form do cart.
 *
 * Default do WC: 'woocommerce_cart_collaterals' agrupa cross-sells +
 * cart-totals na mesma coluna. Em layouts 2-col com totals sticky, isso
 * cria uma sidebar interminavel. Mover cross-sells para depois do form
 * (full-width) replica o padrao "Voce tambem pode gostar" do PDP.
 */
add_action('init', function () {
    if (!function_exists('woocommerce_cross_sell_display')) {
        return;
    }
    remove_action('woocommerce_cart_collaterals', 'woocommerce_cross_sell_display');
    add_action('woocommerce_after_cart', 'woocommerce_cross_sell_display', 5);
});

// Fase 3 — exemplos planejados (NAO ATIVOS ainda):
//
// add_action('woocommerce_before_cart', 'cia_astro_cart_trust_strip', 5);
// add_action('woocommerce_checkout_before_customer_details', 'cia_astro_checkout_trust_strip', 5);
// add_action('woocommerce_review_order_after_order_total', 'cia_astro_pix_discount_hint', 10);
