<?php
/**
 * URL rewrites: intercepta TODOS os permalinks do WP/Woo que apontam para
 * conteudo de vitrine e redireciona para o Astro (dev/prod).
 *
 * Cobre:
 *   1. post_type_link de 'product'       -> vitrine /produto/<slug>/
 *   2. term_link de 'product_cat'        -> vitrine /categoria/<slug>/
 *   3. term_link de 'product_brand'      -> vitrine /marca/<slug>/
 *   4. post_type_link de 'post' (blog)   -> vitrine /blog/<slug>/
 *   5. page_link de pages institucionais -> vitrine (mapeamento por slug)
 *   6. Woo: return_to_shop, continue_shopping
 *   7. wc_get_page_permalink('shop')     -> vitrine /loja/
 *
 * Backend WP mantem: cart, checkout, my-account, wishlist, track, lost-password.
 *
 * Pre-requisito: inc/urls.php carregado antes (helpers cia_astro_frontend_url /
 * cia_astro_backend_url ja registrados).
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Pages WP cujo conteudo vive na vitrine Astro (e nao deve linkar para o WP).
 * Mapeamento slug -> path vitrine.
 */
function cia_astro_frontend_pages_map() {
    return [
        'shop'                     => '/loja/',
        'loja'                     => '/loja/',
        'quem-somos'               => '/quem-somos/',
        'como-comprar'             => '/como-comprar/',
        'prazo-e-entrega'          => '/prazo-e-entrega/',
        'trocas-e-devolucoes'      => '/trocas-e-devolucoes/',
        'politica-de-privacidade'  => '/politica-de-privacidade/',
        'termos-de-uso'            => '/termos-de-uso/',
        'contato'                  => '/contato/',
        'promocoes'                => '/promocoes/',
        'marcas'                   => '/marcas/',
        'blog'                     => '/blog/',
    ];
}

/**
 * Slugs WP que DEVEM permanecer no backend (links absolutos WP).
 * Tudo que envolve sessao/checkout/conta.
 */
function cia_astro_backend_only_slugs() {
    return [
        'carrinho', 'cart',
        'finalizar-compra', 'checkout',
        'minha-conta', 'my-account',
        'my-wishlist', 'wishlist',
        'track-my-order',
        'lost-password', 'esqueci-a-senha',
        'order-received', 'pedido-recebido',
        'view-order', 'edit-account', 'edit-address',
    ];
}

// ============ 1. Produtos: permalink do CPT product ============
add_filter('post_type_link', function ($url, $post) {
    if ($post && isset($post->post_type) && $post->post_type === 'product') {
        return cia_astro_frontend_url('/produto/' . $post->post_name . '/');
    }
    return $url;
}, 10, 2);

// ============ 2 e 3. Taxonomies: product_cat e product_brand ============
add_filter('term_link', function ($url, $term, $taxonomy) {
    if ($taxonomy === 'product_cat') {
        return cia_astro_frontend_url('/categoria/' . $term->slug . '/');
    }
    if ($taxonomy === 'product_brand') {
        return cia_astro_frontend_url('/marca/' . $term->slug . '/');
    }
    return $url;
}, 10, 3);

// ============ 4. Posts do blog ============
add_filter('post_link', function ($url, $post) {
    if ($post && isset($post->post_type) && $post->post_type === 'post') {
        return cia_astro_frontend_url('/blog/' . $post->post_name . '/');
    }
    return $url;
}, 10, 2);

// ============ 5. Pages institucionais ============
add_filter('page_link', function ($url, $page_id) {
    if (!$page_id) return $url;
    $page = get_post($page_id);
    if (!$page) return $url;

    // Backend-only: nao mexer
    if (in_array($page->post_name, cia_astro_backend_only_slugs(), true)) {
        return $url;
    }

    // Vitrine: redireciona
    $map = cia_astro_frontend_pages_map();
    if (isset($map[$page->post_name])) {
        return cia_astro_frontend_url($map[$page->post_name]);
    }
    return $url;
}, 10, 2);

// ============ 6 e 7. WooCommerce: shop, return, continue ============
add_filter('woocommerce_get_shop_page_permalink', function () {
    return cia_astro_frontend_url('/loja/');
});

// Botao "Voltar a loja" quando carrinho vazio
add_filter('woocommerce_return_to_shop_redirect', function () {
    return cia_astro_frontend_url('/loja/');
});

// Page permalink Woo (cart/checkout/myaccount usam isso internamente).
// Filtra apenas 'shop' e 'terms' para vitrine; resto fica no WP.
add_filter('woocommerce_get_cart_page_permalink',     function ($url) { return $url; }); // fica
add_filter('woocommerce_get_checkout_page_permalink', function ($url) { return $url; }); // fica
add_filter('woocommerce_get_myaccount_page_permalink', function ($url) { return $url; }); // fica
add_filter('woocommerce_get_terms_page_permalink', function () {
    return cia_astro_frontend_url('/termos-de-uso/');
});
add_filter('woocommerce_get_privacy_policy_permalink', function () {
    return cia_astro_frontend_url('/politica-de-privacidade/');
});

/**
 * Sanity: hook universal pra qualquer permalink que use get_permalink/
 * get_the_permalink, alem dos filters especificos acima (defesa em
 * profundidade caso WC use helpers que escapam dos hooks individuais).
 */
add_filter('the_permalink', function ($url, $post = null) {
    if (!$post || !is_object($post)) return $url;
    if (!isset($post->post_type)) return $url;

    if ($post->post_type === 'product') {
        return cia_astro_frontend_url('/produto/' . $post->post_name . '/');
    }
    if ($post->post_type === 'post') {
        return cia_astro_frontend_url('/blog/' . $post->post_name . '/');
    }
    if ($post->post_type === 'page') {
        $map = cia_astro_frontend_pages_map();
        if (isset($map[$post->post_name])) {
            return cia_astro_frontend_url($map[$post->post_name]);
        }
    }
    return $url;
}, 10, 2);
