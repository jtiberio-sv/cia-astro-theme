<?php
/**
 * Enqueue: remove assets do parent Storefront que conflitam, carrega cascata propria,
 * adiciona Google Fonts CDN (Fredoka + Inter) — espelhando Layout.astro do front.
 *
 * Ordem: google-fonts → tokens → base → header → footer → woo-* → header.js
 *
 * Self-hosting fonts e tech-debt LGPD a revisitar (ver wp-theme/architecture.md secao 4).
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_enqueue_scripts', function () {
    // --- Dequeue do parent Storefront ---
    // Storefront-style traz reset + tipografia + cores default que conflitam com nossa identidade.
    // Mantemos apenas o ecossistema WC (form/cart/checkout scripts).
    wp_dequeue_style('storefront-style');
    wp_dequeue_style('storefront-icons');
    wp_dequeue_style('storefront-fonts');
    wp_dequeue_style('storefront-woocommerce-style');
    wp_dequeue_style('storefront-gutenberg-blocks');
    wp_deregister_style('storefront-style');
    wp_deregister_style('storefront-icons');
    wp_deregister_style('storefront-fonts');

    // --- Google Fonts (mesma URL do Astro Layout.astro) ---
    // Preconnect via wp_resource_hints (filter abaixo). Aqui apenas o stylesheet.
    wp_enqueue_style(
        'cia-google-fonts',
        'https://fonts.googleapis.com/css2?family=Fredoka:wght@400;500;600;700&family=Inter:wght@400;500;600;700&display=swap',
        [],
        null
    );

    // --- Cascata propria ---
    $base = CIA_ASTRO_URI . '/assets/css';
    $ver  = CIA_ASTRO_VERSION;

    $cascade = [
        ['cia-tokens',        '/tokens.css',        []],
        ['cia-base',          '/base.css',          ['cia-tokens']],
        ['cia-header',        '/header.css',        ['cia-base']],
        ['cia-footer',        '/footer.css',        ['cia-base']],
        // woo-base depois de base/header/footer pra sobrescrever WC inline com !important
        ['cia-woo-base',          '/woo-base.css',          ['cia-base']],
        ['cia-woo-cart',          '/woo-cart.css',          ['cia-woo-base']],
        ['cia-woo-checkout',      '/woo-checkout.css',      ['cia-woo-base']],
        ['cia-woo-account',       '/woo-account.css',       ['cia-woo-base']],
        ['cia-woo-single-product','/woo-single-product.css',['cia-woo-base']],
    ];

    foreach ($cascade as [$handle, $path, $deps]) {
        $abs = CIA_ASTRO_DIR . '/assets/css' . $path;
        if (file_exists($abs)) {
            wp_enqueue_style($handle, $base . $path, $deps, $ver);
        }
    }

    // --- JS ---
    $js_files = [
        ['cia-header',            '/assets/js/header.js',            ['jquery'], true],
        ['cia-checkout-cep',      '/assets/js/checkout-cep.js',      ['jquery'], true],
        ['cia-checkout-notices',  '/assets/js/checkout-notices.js',  ['jquery'], true],
    ];
    foreach ($js_files as [$handle, $path, $deps, $in_footer]) {
        $abs = CIA_ASTRO_DIR . $path;
        if (file_exists($abs)) {
            wp_enqueue_script($handle, CIA_ASTRO_URI . $path, $deps, $ver, $in_footer);
        }
    }
}, 100);

/**
 * Preconnect para Google Fonts (espelhando Layout.astro do front).
 */
add_filter('wp_resource_hints', function ($urls, $relation_type) {
    if ($relation_type === 'preconnect') {
        $urls[] = [
            'href' => 'https://fonts.googleapis.com',
        ];
        $urls[] = [
            'href'        => 'https://fonts.gstatic.com',
            'crossorigin' => 'anonymous',
        ];
    }
    return $urls;
}, 10, 2);
