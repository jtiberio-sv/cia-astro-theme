<?php
/**
 * WooCommerce config: remove elementos do parent Storefront que nao queremos.
 *
 * Mantemos a logica WC intacta — apenas reorganizamos hooks.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', function () {
    if (!class_exists('WooCommerce')) {
        return;
    }

    // Remove breadcrumb do Storefront (Astro/cia-astro ja tem o seu).
    remove_action('storefront_before_content', 'woocommerce_breadcrumb', 10);

    // Remove sidebar das paginas Woo (nosso layout e centralizado).
    remove_action('storefront_sidebar', 'storefront_get_sidebar', 10);

    // Remove header cart e header search do Storefront (substituidos pelo nosso header).
    remove_action('storefront_header', 'storefront_header_cart', 60);
    remove_action('storefront_header', 'storefront_product_search', 40);
}, 20);
