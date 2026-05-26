<?php
/**
 * Plugin Name: CDM SSO Bridge
 * Description: Single Sign-On entre vitrine Astro (ciadasmochilas.com.br) e
 *              loja WP (loja.ciadasmochilas.com.br). Vitrine pega JWT do
 *              localStorage, POSTa /cdm/v1/sso-create (autenticado via
 *              cdm-jwt-bearer-auth.php), recebe nonce one-time (32 hex, TTL 60s),
 *              navega pra /cdm/v1/sso?nonce=...&redirect=... que valida o nonce
 *              consumindo-o, seta cookie WP nativo e redireciona.
 *
 * Por que mu-plugin (e nao tema): auth/sessao crítico — sobrevive a troca de tema.
 *
 * Endpoints:
 *  - POST /wp-json/cdm/v1/sso-create  (Bearer JWT) -> { nonce: "abc..." }
 *  - GET  /wp-json/cdm/v1/sso?nonce=...&redirect=... -> 302 com Set-Cookie
 *
 * Seguranca:
 *  - Nonce one-time (deletado ao consumir)
 *  - TTL 60s (curto pra mitigar replay)
 *  - Whitelist de hosts no redirect (so dominios da Cia das Mochilas)
 *  - Random_bytes(16) -> 128 bits de entropia (suficiente contra brute force)
 *  - JWT da vitrine validado pelo determine_current_user filter do
 *    cdm-jwt-bearer-auth.php (HS256 + exp check), entao /sso-create so
 *    aceita request de user logado via JWT valido
 */
if (!defined('ABSPATH')) {
    exit;
}

// Hosts validos pra redirect pos-SSO. Atualizar se vitrine mudar de dominio.
const CDM_SSO_ALLOWED_HOSTS = [
    'loja.ciadasmochilas.com.br',
    'ciadasmochilas.com.br',
];

const CDM_SSO_NONCE_TTL = 60;        // segundos
const CDM_SSO_NONCE_PREFIX = 'cdm_sso_';
const CDM_SSO_DEFAULT_REDIRECT = 'https://loja.ciadasmochilas.com.br/minha-conta/';

add_action('rest_api_init', function () {
    // POST /cdm/v1/sso-create — cria nonce one-time pra user autenticado via JWT
    register_rest_route('cdm/v1', '/sso-create', [
        'methods'  => 'POST',
        'callback' => 'cdm_sso_create',
        'permission_callback' => function () {
            // cdm-jwt-bearer-auth.php ja popula current_user a partir do Bearer JWT
            return is_user_logged_in();
        },
    ]);

    // GET /cdm/v1/sso?nonce=...&redirect=... — consome nonce, seta cookie, redireciona
    register_rest_route('cdm/v1', '/sso', [
        'methods'  => 'GET',
        'callback' => 'cdm_sso_consume',
        'permission_callback' => '__return_true',
    ]);
});

/**
 * Cria nonce one-time atrelado ao user_id atual. Salvo em transient (Redis ou DB).
 */
function cdm_sso_create(WP_REST_Request $req): WP_REST_Response
{
    $uid = get_current_user_id();
    if (!$uid) {
        return new WP_REST_Response(['error' => 'unauthorized'], 401);
    }

    try {
        $nonce = bin2hex(random_bytes(16));
    } catch (Exception $e) {
        return new WP_REST_Response(['error' => 'rng_failure'], 500);
    }

    set_transient(CDM_SSO_NONCE_PREFIX . $nonce, (int) $uid, CDM_SSO_NONCE_TTL);

    return new WP_REST_Response([
        'nonce'   => $nonce,
        'expires' => CDM_SSO_NONCE_TTL,
    ], 200);
}

/**
 * Consome nonce, seta cookie WP, redireciona.
 * Em caso de falha (nonce invalido/expirado), redireciona pra login normal.
 */
function cdm_sso_consume(WP_REST_Request $req): void
{
    $nonce    = (string) $req->get_param('nonce');
    $redirect = (string) $req->get_param('redirect');

    // Validacao de formato do nonce (32 chars hex)
    if (!$nonce || !preg_match('/^[a-f0-9]{32}$/', $nonce)) {
        wp_safe_redirect(CDM_SSO_DEFAULT_REDIRECT);
        exit;
    }

    // Resolve e valida redirect destino contra whitelist
    $dest = cdm_sso_resolve_redirect($redirect);

    // Consome nonce — get + delete atomico-ish (delete sempre, mesmo se falhar)
    $uid = get_transient(CDM_SSO_NONCE_PREFIX . $nonce);
    delete_transient(CDM_SSO_NONCE_PREFIX . $nonce);

    if (!$uid) {
        // Nonce invalido ou ja consumido — vai pra login normal
        wp_safe_redirect($dest);
        exit;
    }

    // Seta cookie WP nativo. remember=true -> cookie persiste 14 dias.
    wp_set_current_user((int) $uid);
    wp_set_auth_cookie((int) $uid, true, is_ssl());

    // Headers de seguranca: nao vazar nonce/redirect via Referer pra terceiros
    header('Referrer-Policy: no-referrer');
    header('Cache-Control: no-store, no-cache, must-revalidate');

    // Permite redirect pros nossos dominios (wp_safe_redirect respeita)
    add_filter('allowed_redirect_hosts', function ($hosts) {
        return array_merge((array) $hosts, CDM_SSO_ALLOWED_HOSTS);
    });

    wp_safe_redirect($dest);
    exit;
}

/**
 * Resolve URL de redirect: se for valida e dentro do whitelist, retorna.
 * Senao, retorna default (minha-conta).
 */
function cdm_sso_resolve_redirect(string $url): string
{
    if (!$url) {
        return CDM_SSO_DEFAULT_REDIRECT;
    }

    $parsed = wp_parse_url($url);
    $host   = $parsed['host'] ?? '';

    if (!$host || !in_array($host, CDM_SSO_ALLOWED_HOSTS, true)) {
        return CDM_SSO_DEFAULT_REDIRECT;
    }

    // Se host valido mas e a vitrine, troca pra loja (mesmo path)
    if ($host === 'ciadasmochilas.com.br') {
        $path  = $parsed['path']  ?? '/';
        $query = isset($parsed['query'])    ? '?' . $parsed['query']    : '';
        $frag  = isset($parsed['fragment']) ? '#' . $parsed['fragment'] : '';
        return 'https://loja.ciadasmochilas.com.br' . $path . $query . $frag;
    }

    return $url;
}
