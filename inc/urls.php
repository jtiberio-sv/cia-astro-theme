<?php
/**
 * URLs helper: roteamento explicito entre vitrine (Astro/CF Pages) e checkout (WP).
 *
 * Backend WP renderiza apenas: cart, checkout, my-account, wishlist, track.
 * TUDO mais (browse, categorias, marcas, paginas institucionais, blog) e' o Astro.
 *
 * Mapeamento host -> vitrine:
 *   loja-dev.ciadasmochilas.com.br  -> https://dev.ciadasmochilas.com.br
 *   loja.ciadasmochilas.com.br      -> https://ciadasmochilas.com.br  (pos-cutover)
 *   ciadasmochilas.com.br           -> https://ciadasmochilas.com.br  (legado pre-cutover)
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * URL absoluta na vitrine Astro a partir de um path relativo.
 * Use sempre que o link for de browse/conteudo (categoria, marca, busca, institucional).
 */
function cia_astro_frontend_url($path = '/') {
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
    $map = [
        'loja-dev.ciadasmochilas.com.br' => 'https://dev.ciadasmochilas.com.br',
        'loja.ciadasmochilas.com.br'     => 'https://ciadasmochilas.com.br',
    ];
    $base = isset($map[$host]) ? $map[$host] : home_url();
    return rtrim($base, '/') . '/' . ltrim($path, '/');
}

/**
 * URL absoluta no backend WP (cart/checkout/account/wishlist/track).
 * Wrapper sobre home_url() pra explicitar intent na leitura do header.php.
 */
function cia_astro_backend_url($path = '/') {
    return home_url($path);
}
