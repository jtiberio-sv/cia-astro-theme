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

/**
 * Traduz titulos de pages WC criados em ingles por defaults antigos.
 * Atua apenas se o title bater EXATAMENTE (nao mexe em produtos/posts).
 *
 * Para mudanca persistente preferir editar via wp-admin > Paginas.
 * Esse filter e defesa em profundidade ate o cliente revisar todos
 * os titles via painel.
 */
add_filter('the_title', function ($title, $post_id = null) {
    static $map = [
        'Track My Order'   => 'Rastrear meu pedido',
        'Track Your Order' => 'Rastrear meu pedido',
        'My account'       => 'Minha conta',
        'My Account'       => 'Minha conta',
        'Cart'             => 'Carrinho',
        'Checkout'         => 'Finalizar compra',
        'Order Tracking'   => 'Rastrear meu pedido',
        'Lost password'    => 'Recuperar senha',
        'My Wishlist'      => 'Meus favoritos',
    ];
    return isset($map[$title]) ? $map[$title] : $title;
}, 10, 2);

// Equivalente para casos onde Woo/plugin chama via gettext (strings em codigo)
add_filter('gettext', function ($translation, $text, $domain) {
    static $map = [
        'Track My Order'   => 'Rastrear meu pedido',
        'Track your order' => 'Rastrear seu pedido',
    ];
    return isset($map[$text]) ? $map[$text] : $translation;
}, 99, 3);

// Strings WC com placeholders %s — sprintf/printf-style — precisam gettext_with_context
// ou ngettext. Aqui usamos gettext geral com replace pra cobrir cupons em ingles.
add_filter('gettext', function ($translation, $text, $domain) {
    if ($domain !== 'woocommerce' && $domain !== 'default') {
        return $translation;
    }
    static $cupom_map = [
        'The minimum spend for coupon "%s" is %s.'        => 'O valor minimo do pedido para usar o cupom "%s" e de %s.',
        'The maximum spend for coupon "%s" is %s.'        => 'O valor maximo do pedido para usar o cupom "%s" e de %s.',
        'Coupon "%s" does not exist!'                     => 'O cupom "%s" nao existe.',
        'Coupon usage limit has been reached.'            => 'O limite de uso deste cupom foi atingido.',
        'Coupon code applied successfully.'               => 'Cupom aplicado com sucesso.',
        'Coupon code removed successfully.'               => 'Cupom removido com sucesso.',
        'Please enter a coupon code.'                     => 'Por favor, informe um codigo de cupom.',
        'Coupon "%s" already applied!'                    => 'O cupom "%s" ja foi aplicado.',
        'Sorry, coupon "%s" has already been used and cannot be used again.' => 'O cupom "%s" ja foi utilizado e nao pode ser usado novamente.',
        'Sorry, this coupon is not applicable to your cart contents.' => 'Este cupom nao pode ser aplicado aos itens do seu carrinho.',
        'Sorry, this coupon has expired.'                 => 'Este cupom expirou.',
        'Sorry, it seems the coupon "%s" is not yours - it has now been removed from your order.' => 'O cupom "%s" pertence a outro cliente e foi removido do pedido.',
        'Sorry, this coupon is not valid for sale items.' => 'Este cupom nao e valido para itens em promocao.',
        'Cart updated.'                                   => 'Carrinho atualizado.',
    ];
    return $cupom_map[$text] ?? $translation;
}, 100, 3);
