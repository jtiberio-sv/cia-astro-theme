<?php
/**
 * Login com Google — OAuth 2.0 custom, sem plugin.
 *
 * Configuracao: defina constantes em wp-config.php OU options:
 *   CDM_GOOGLE_CLIENT_ID
 *   CDM_GOOGLE_CLIENT_SECRET
 * (fallback para options 'cdm_google_client_id' / 'cdm_google_client_secret')
 *
 * Setup no Google Cloud Console:
 *   1. console.cloud.google.com/apis/credentials → Create OAuth 2.0 Client ID
 *   2. Application type: Web application
 *   3. Authorized redirect URIs:
 *      https://loja.ciadasmochilas.com.br/?cdm_google_oauth=callback
 *   4. Copiar Client ID + Secret e:
 *      wp option update cdm_google_client_id 'XXX.apps.googleusercontent.com'
 *      wp option update cdm_google_client_secret 'GOCSPX-...'
 */

if (!defined('ABSPATH')) {
    exit;
}

const CDM_GOOGLE_USER_META   = '_cdm_google_id';
const CDM_GOOGLE_OAUTH_URL   = 'https://accounts.google.com/o/oauth2/v2/auth';
const CDM_GOOGLE_TOKEN_URL   = 'https://oauth2.googleapis.com/token';
const CDM_GOOGLE_USERINFO    = 'https://www.googleapis.com/oauth2/v2/userinfo';

function cdm_google_client_id() {
    if (defined('CDM_GOOGLE_CLIENT_ID')) return CDM_GOOGLE_CLIENT_ID;
    return get_option('cdm_google_client_id', '');
}
function cdm_google_client_secret() {
    if (defined('CDM_GOOGLE_CLIENT_SECRET')) return CDM_GOOGLE_CLIENT_SECRET;
    return get_option('cdm_google_client_secret', '');
}
function cdm_google_redirect_uri() {
    return home_url('/?cdm_google_oauth=callback');
}
function cdm_google_enabled() {
    return cdm_google_client_id() && cdm_google_client_secret();
}

/* ============================================================
 * 1) Botao "Entrar com Google" injetado nos forms de login/register
 * ============================================================ */

add_action('woocommerce_login_form_start', 'cdm_google_login_button');
add_action('woocommerce_register_form_start', 'cdm_google_login_button');

function cdm_google_login_button() {
    if (!cdm_google_enabled()) return;
    $redirect = isset($_REQUEST['redirect_to']) ? esc_url_raw($_REQUEST['redirect_to']) : '';
    $start = add_query_arg([
        'cdm_google_oauth' => 'start',
        'redirect_to'      => $redirect ?: '',
    ], home_url('/'));
    ?>
    <div class="cdm-social-login">
      <a href="<?php echo esc_url($start); ?>" class="cdm-btn-google" rel="nofollow">
        <span class="g-icon" aria-hidden="true">
          <svg width="20" height="20" viewBox="0 0 48 48">
            <path fill="#FFC107" d="M43.611 20.083H42V20H24v8h11.303C33.683 32.658 29.211 36 24 36c-6.627 0-12-5.373-12-12s5.373-12 12-12c3.059 0 5.842 1.154 7.961 3.039l5.657-5.657C33.046 6.053 28.785 4 24 4 12.955 4 4 12.955 4 24s8.955 20 20 20 20-8.955 20-20c0-1.341-.138-2.65-.389-3.917z"/>
            <path fill="#FF3D00" d="m6.306 14.691 6.571 4.819C14.655 15.108 18.961 12 24 12c3.059 0 5.842 1.154 7.961 3.039l5.657-5.657C33.046 6.053 28.785 4 24 4 16.318 4 9.656 8.337 6.306 14.691z"/>
            <path fill="#4CAF50" d="M24 44c4.7 0 8.969-1.799 12.198-4.726l-5.633-4.768C28.701 35.61 26.469 36 24 36c-5.197 0-9.66-3.326-11.298-7.964l-6.522 5.025C9.564 39.556 16.227 44 24 44z"/>
            <path fill="#1976D2" d="M43.611 20.083H42V20H24v8h11.303c-.792 2.237-2.231 4.166-4.087 5.571l5.633 4.768C40.99 35.337 44 30 44 24c0-1.341-.138-2.65-.389-3.917z"/>
          </svg>
        </span>
        <span class="g-label">Entrar com Google</span>
      </a>
      <div class="cdm-social-divider"><span>ou</span></div>
    </div>
    <?php
}

/* ============================================================
 * 2) Start: redirect para Google authorize
 * ============================================================ */

add_action('init', function () {
    if (empty($_GET['cdm_google_oauth'])) return;
    $action = sanitize_key($_GET['cdm_google_oauth']);

    if ($action === 'start') {
        if (!cdm_google_enabled()) {
            wp_die('Login com Google nao configurado. Defina cdm_google_client_id e cdm_google_client_secret.');
        }
        $redirect_to = isset($_GET['redirect_to']) ? esc_url_raw($_GET['redirect_to']) : '';
        $state = wp_generate_password(24, false);
        set_transient('cdm_google_state_' . $state, $redirect_to ?: 'myaccount', 600);

        $url = add_query_arg([
            'client_id'     => cdm_google_client_id(),
            'redirect_uri'  => cdm_google_redirect_uri(),
            'response_type' => 'code',
            'scope'         => 'openid email profile',
            'state'         => $state,
            'access_type'   => 'online',
            'prompt'        => 'select_account',
        ], CDM_GOOGLE_OAUTH_URL);
        wp_redirect($url);
        exit;
    }

    if ($action === 'callback') {
        cdm_google_handle_callback();
        exit;
    }
}, 1);

function cdm_google_handle_callback() {
    $code  = isset($_GET['code'])  ? sanitize_text_field($_GET['code']) : '';
    $state = isset($_GET['state']) ? sanitize_text_field($_GET['state']) : '';
    if (!$code || !$state) {
        wp_die('Resposta invalida do Google.');
    }
    $redirect_target = get_transient('cdm_google_state_' . $state);
    if ($redirect_target === false) {
        wp_die('Sessao expirada. Tente novamente.');
    }
    delete_transient('cdm_google_state_' . $state);

    // Troca code por token
    $r = wp_remote_post(CDM_GOOGLE_TOKEN_URL, [
        'body' => [
            'code'          => $code,
            'client_id'     => cdm_google_client_id(),
            'client_secret' => cdm_google_client_secret(),
            'redirect_uri'  => cdm_google_redirect_uri(),
            'grant_type'    => 'authorization_code',
        ],
        'timeout' => 15,
    ]);
    if (is_wp_error($r)) wp_die('Erro ao trocar codigo: ' . $r->get_error_message());
    $tok = json_decode(wp_remote_retrieve_body($r), true);
    if (empty($tok['access_token'])) wp_die('Token nao recebido do Google.');

    // Busca user info
    $u = wp_remote_get(CDM_GOOGLE_USERINFO, [
        'headers' => ['Authorization' => 'Bearer ' . $tok['access_token']],
        'timeout' => 15,
    ]);
    if (is_wp_error($u)) wp_die('Erro userinfo: ' . $u->get_error_message());
    $info = json_decode(wp_remote_retrieve_body($u), true);
    if (empty($info['email']) || empty($info['verified_email'])) {
        wp_die('Email do Google nao verificado.');
    }

    // Match user existente por google_id OU email
    $email     = sanitize_email($info['email']);
    $google_id = sanitize_text_field($info['id']);
    $first     = sanitize_text_field($info['given_name'] ?? '');
    $last      = sanitize_text_field($info['family_name'] ?? '');

    $users = get_users(['meta_key' => CDM_GOOGLE_USER_META, 'meta_value' => $google_id, 'number' => 1]);
    $user  = $users ? $users[0] : null;

    if (!$user) {
        $user = get_user_by('email', $email);
        if ($user) {
            update_user_meta($user->ID, CDM_GOOGLE_USER_META, $google_id);
        }
    }

    // Cria conta nova se nao existe
    if (!$user) {
        $username = wp_unique_user_login(sanitize_user(strtok($email, '@')));
        $user_id  = wp_insert_user([
            'user_login'    => $username,
            'user_email'    => $email,
            'user_pass'     => wp_generate_password(32, true),
            'first_name'    => $first,
            'last_name'     => $last,
            'display_name'  => trim($first . ' ' . $last) ?: $username,
            'role'          => 'customer',
        ]);
        if (is_wp_error($user_id)) wp_die('Erro ao criar conta: ' . $user_id->get_error_message());
        update_user_meta($user_id, CDM_GOOGLE_USER_META, $google_id);
        update_user_meta($user_id, 'billing_first_name', $first);
        update_user_meta($user_id, 'billing_last_name', $last);
        update_user_meta($user_id, 'billing_email', $email);
        $user = get_user_by('id', $user_id);

        // Dispara email "boas-vindas" (sem expor senha)
        if (function_exists('WC')) {
            $wc_email = WC()->mailer()->emails['WC_Email_Customer_New_Account'] ?? null;
            if ($wc_email) {
                $wc_email->trigger($user_id, '', false);
            }
        }
    }

    // Login
    wp_set_current_user($user->ID);
    wp_set_auth_cookie($user->ID, true);
    do_action('wp_login', $user->user_login, $user);

    // Redirect: usa target salvo no transient ou volta pra myaccount
    if ($redirect_target === 'myaccount' || !$redirect_target) {
        $redirect_target = wc_get_page_permalink('myaccount');
    }
    wp_safe_redirect($redirect_target);
    exit;
}

/**
 * Garante que wp_unique_user_login existe (WP nao tem helper oficial)
 */
if (!function_exists('wp_unique_user_login')) {
    function wp_unique_user_login($base) {
        $base = sanitize_user($base, true);
        if (!$base) $base = 'cliente';
        $candidate = $base;
        $i = 1;
        while (get_user_by('login', $candidate)) {
            $candidate = $base . $i;
            $i++;
        }
        return $candidate;
    }
}
