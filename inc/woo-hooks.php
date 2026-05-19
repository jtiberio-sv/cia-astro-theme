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

// Fase 3 — exemplos planejados (NAO ATIVOS ainda):
//
// add_action('woocommerce_before_cart', 'cia_astro_cart_trust_strip', 5);
// add_action('woocommerce_checkout_before_customer_details', 'cia_astro_checkout_trust_strip', 5);
// add_action('woocommerce_review_order_after_order_total', 'cia_astro_pix_discount_hint', 10);
