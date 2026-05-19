<?php
/**
 * Enqueue: remove assets pesados do parent Storefront, carrega CSS do tema na ordem certa.
 *
 * Ordem: fonts → tokens → base → header → footer → woo-*
 *
 * Fase 0: apenas placeholder, nenhum CSS carregado ainda.
 * Fase 1: adiciona fonts.css + tokens.css + base.css + header.css + footer.css
 * Fase 3: woo-cart.css + woo-checkout.css
 * Fase 4: woo-account.css + woo-wishlist.css + woo-track.css
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_enqueue_scripts', function () {
    // Remove o style principal do parent Storefront — vamos servir nossa cascata propria.
    // (Fase 0: comentado para nao quebrar visual ate Fase 1 estar pronta.)
    // wp_dequeue_style('storefront-style');
    // wp_dequeue_style('storefront-icons');
    // wp_dequeue_style('storefront-fonts');

    $base = CIA_ASTRO_URI . '/assets/css';
    $ver  = CIA_ASTRO_VERSION;

    // Cascata de CSS do tema (sera populada nas proximas fases).
    // Cada arquivo so e enfileirado se existir, para nao quebrar nada na Fase 0.
    $cascade = [
        ['cia-fonts',         '/fonts.css',         []],
        ['cia-tokens',        '/tokens.css',        ['cia-fonts']],
        ['cia-base',          '/base.css',          ['cia-tokens']],
        ['cia-header',        '/header.css',        ['cia-base']],
        ['cia-footer',        '/footer.css',        ['cia-base']],
        ['cia-woo-cart',      '/woo-cart.css',      ['cia-base']],
        ['cia-woo-checkout',  '/woo-checkout.css',  ['cia-base']],
        ['cia-woo-account',   '/woo-account.css',   ['cia-base']],
    ];

    foreach ($cascade as [$handle, $path, $deps]) {
        $abs = CIA_ASTRO_DIR . '/assets/css' . $path;
        if (file_exists($abs)) {
            wp_enqueue_style($handle, $base . $path, $deps, $ver);
        }
    }
}, 100);
