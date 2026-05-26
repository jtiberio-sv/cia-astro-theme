<?php
/**
 * Plugin Name: CDM Logout Cleanup
 * Description: Destrói cookies WP em TODOS os domains/paths possíveis no logout.
 *              Workaround pra cookies setados com domains diferentes (ex: sem ponto
 *              inicial em login antigo vs ponto definido depois via COOKIE_DOMAIN).
 *              Sem isso, JP click "Sair" mas cookie persiste em browser com domain
 *              "outro" e usuário continua logado.
 *
 * Por que mu-plugin: logout/auth crítico — não pode depender de tema.
 */
if (!defined('ABSPATH')) {
    exit;
}

add_action('clear_auth_cookie', function () {
    $expire = time() - YEAR_IN_SECONDS;
    $secure = is_ssl();

    // Cookies a destruir
    $cookies = [
        defined('AUTH_COOKIE') ? AUTH_COOKIE : 'wordpress_' . COOKIEHASH,
        defined('SECURE_AUTH_COOKIE') ? SECURE_AUTH_COOKIE : 'wordpress_sec_' . COOKIEHASH,
        defined('LOGGED_IN_COOKIE') ? LOGGED_IN_COOKIE : 'wordpress_logged_in_' . COOKIEHASH,
    ];

    // Domains a tentar (cobre legacy onde foi setado sem ponto + novo com ponto)
    $domains = [
        '',                                      // sem domain (browser usa host atual)
        '.ciadasmochilas.com.br',                // com ponto = cross-subdomain
        'ciadasmochilas.com.br',                 // sem ponto = host exato
        '.loja.ciadasmochilas.com.br',
        'loja.ciadasmochilas.com.br',
        $_SERVER['HTTP_HOST'] ?? '',             // host real do request
    ];

    // Paths a tentar
    $paths = array_unique(array_filter([
        '/',
        defined('COOKIEPATH') ? COOKIEPATH : '/',
        defined('SITECOOKIEPATH') ? SITECOOKIEPATH : '/',
        defined('ADMIN_COOKIE_PATH') ? ADMIN_COOKIE_PATH : '/wp-admin',
        defined('PLUGINS_COOKIE_PATH') ? PLUGINS_COOKIE_PATH : '/wp-content/plugins',
    ]));

    foreach ($cookies as $name) {
        foreach ($domains as $domain) {
            foreach ($paths as $path) {
                if (PHP_VERSION_ID >= 70300) {
                    setcookie($name, '', [
                        'expires'  => $expire,
                        'path'     => $path,
                        'domain'   => $domain,
                        'secure'   => $secure,
                        'httponly' => true,
                        'samesite' => 'Lax',
                    ]);
                } else {
                    setcookie($name, '', $expire, $path, $domain, $secure, true);
                }
            }
        }
    }

    // WC session cookie tambem (caso esteja em wrong domain)
    if (defined('COOKIEHASH')) {
        $wc_cookie = 'wp_woocommerce_session_' . COOKIEHASH;
        foreach ($domains as $domain) {
            setcookie($wc_cookie, '', $expire, '/', $domain, $secure, true);
        }
    }
}, 99); // priority alta pra rodar depois do WP nativo (10)
