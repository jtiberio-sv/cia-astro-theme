<?php
/**
 * Plugin Name: CDM JWT Bearer Auth (REST custom routes)
 * Description: Autentica user via Authorization: Bearer <JWT> em rotas REST CUSTOM
 *              (/cdm/v1/*). CoCart JWT so autentica suas proprias rotas
 *              (/cocart/v2/*), por design. Este mu-plugin estende pra outros.
 *
 * Decodifica JWT emitido por CoCart JWT Authentication usando o secret
 * COCART_JWT_AUTH_SECRET_KEY do wp-config.php. Valida assinatura HS256 +
 * expiracao. Seta wp_set_current_user(id) se valido.
 */
if (!defined('ABSPATH')) exit;

add_filter('determine_current_user', 'cdm_jwt_bearer_determine_user', 30);

function cdm_jwt_bearer_determine_user($user_id) {
    if ($user_id) return $user_id;

    // Authorization header
    $auth = '';
    if (function_exists('getallheaders')) {
        foreach ((array) getallheaders() as $k => $v) {
            if (strtolower($k) === 'authorization') { $auth = $v; break; }
        }
    }
    if (!$auth && isset($_SERVER['HTTP_AUTHORIZATION']))         $auth = $_SERVER['HTTP_AUTHORIZATION'];
    if (!$auth && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) $auth = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    if (!$auth || stripos($auth, 'Bearer ') !== 0) return $user_id;

    $token = trim(substr($auth, 7));
    if (!$token || substr_count($token, '.') !== 2) return $user_id;
    list($head_b64, $payload_b64, $sig_b64) = explode('.', $token);

    // Secret prioridade: CoCart > Simple JWT Login > skip
    $secret = '';
    if (defined('COCART_JWT_AUTH_SECRET_KEY') && COCART_JWT_AUTH_SECRET_KEY) {
        $secret = COCART_JWT_AUTH_SECRET_KEY;
    } else {
        $json = get_option('simple_jwt_login_settings');
        $opts = is_string($json) ? json_decode($json, true) : (array) $json;
        $secret = $opts['decryption_key'] ?? '';
    }
    if (!$secret) return $user_id;

    // Valida assinatura HS256
    $expected_sig = cdm_b64url_encode(hash_hmac('sha256', "$head_b64.$payload_b64", $secret, true));
    if (!hash_equals($expected_sig, $sig_b64)) return $user_id;

    // Decodifica payload
    $payload_json = cdm_b64url_decode($payload_b64);
    $payload = json_decode($payload_json, true);
    if (!is_array($payload)) return $user_id;
    if (isset($payload['exp']) && $payload['exp'] < time()) return $user_id;

    // Pega user — CoCart usa data.user.id, Simple JWT Login usa id ou user
    $uid = 0;
    if (isset($payload['data']['user']['id'])) {
        $uid = (int) $payload['data']['user']['id'];
    } elseif (!empty($payload['id'])) {
        $uid = (int) $payload['id'];
    } elseif (!empty($payload['user'])) {
        $uval = $payload['user'];
        if (is_numeric($uval)) {
            $uid = (int) $uval;
        } else {
            $u = is_email($uval) ? get_user_by('email', $uval) : get_user_by('login', $uval);
            if ($u) $uid = $u->ID;
        }
    } elseif (!empty($payload['email'])) {
        $u = get_user_by('email', $payload['email']);
        if ($u) $uid = $u->ID;
    }

    return $uid ?: $user_id;
}

function cdm_b64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}
function cdm_b64url_decode($data) {
    return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
}
