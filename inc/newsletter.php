<?php
/**
 * Newsletter — cadastro de email + cupom de R$30 OFF na primeira compra.
 *
 * Cria tabela wp_cdm_newsletter (lazy via dbDelta on first request), expoe
 * REST endpoint POST /wp-json/cdm/v1/newsletter para o front Astro, gera
 * cupom WC 'BEMVINDO30' (lazy) e dispara email transacional via wp_mail.
 *
 * Specs (alinhadas com cliente 2026-05-21):
 *   - Codigo master compartilhado 'BEMVINDO30'
 *   - R$ 30 fixed_cart
 *   - Valor minimo pedido: R$ 199 (mesma threshold do frete gratis)
 *   - 1 uso por usuario (por billing_email do pedido)
 *   - Validade: 30 dias a partir do cadastro (info-only no email; cupom WC
 *     em si nao expira globalmente porque e master)
 *   - Todas as categorias
 *   - Email unico (rejeita 2o cadastro)
 *   - Grava IP + timestamp + user_agent pra LGPD/audit
 *   - CORS: permite origin da vitrine (ciadasmochilas.com.br + dev.)
 */

if (!defined('ABSPATH')) {
    exit;
}

define('CDM_NEWSLETTER_TABLE_VERSION', '1.0');
define('CDM_NEWSLETTER_COUPON_CODE', 'BEMVINDO30');

// ============ Tabela ============

/**
 * Cria/atualiza a tabela wp_cdm_newsletter via dbDelta.
 * Idempotente (dbDelta so altera se diff existir).
 */
function cdm_newsletter_create_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'cdm_newsletter';
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        email VARCHAR(190) NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        expires_at DATETIME NULL,
        ip VARCHAR(45) NULL,
        user_agent VARCHAR(255) NULL,
        consent_text VARCHAR(500) NULL,
        coupon_code VARCHAR(50) NULL,
        redeemed TINYINT(1) NOT NULL DEFAULT 0,
        redeemed_order_id BIGINT UNSIGNED NULL,
        redeemed_at DATETIME NULL,
        PRIMARY KEY (id),
        UNIQUE KEY email (email),
        KEY created_at (created_at),
        KEY redeemed (redeemed)
    ) {$charset};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);

    update_option('cdm_newsletter_table_version', CDM_NEWSLETTER_TABLE_VERSION);
}

// Lazy create: roda na inicializacao se versao mudou
add_action('init', function () {
    if (get_option('cdm_newsletter_table_version') !== CDM_NEWSLETTER_TABLE_VERSION) {
        cdm_newsletter_create_table();
    }
});

// ============ Cupom WC ============

/**
 * Garante que o cupom master existe no WC. Cria se nao.
 * Lazy: roda no primeiro cadastro de newsletter.
 */
function cdm_newsletter_ensure_coupon() {
    if (!function_exists('wc_get_coupon_id_by_code')) {
        return null;
    }
    $code = CDM_NEWSLETTER_COUPON_CODE;
    $coupon_id = wc_get_coupon_id_by_code($code);
    if ($coupon_id) {
        return $coupon_id;
    }

    $coupon = new WC_Coupon();
    $coupon->set_code($code);
    $coupon->set_discount_type('fixed_cart');
    $coupon->set_amount(30);
    $coupon->set_individual_use(true);
    $coupon->set_minimum_amount(199);
    $coupon->set_usage_limit_per_user(1);
    $coupon->set_exclude_sale_items(false);
    $coupon->set_description('Bem-vindo! R$30 OFF na primeira compra acima de R$199. 1 uso por cliente. Cadastro via newsletter.');
    return $coupon->save();
}

// ============ REST endpoint ============

add_action('rest_api_init', function () {
    register_rest_route('cdm/v1', '/newsletter', [
        'methods'             => 'POST',
        'callback'            => 'cdm_newsletter_subscribe',
        'permission_callback' => '__return_true',
        'args'                => [
            'email' => [
                'required'          => true,
                'type'              => 'string',
                'format'            => 'email',
                'sanitize_callback' => 'sanitize_email',
                'validate_callback' => function ($value) {
                    return is_email($value) !== false;
                },
            ],
        ],
    ]);
});

function cdm_newsletter_subscribe($request) {
    global $wpdb;
    $table = $wpdb->prefix . 'cdm_newsletter';
    $email = strtolower(trim($request->get_param('email')));

    if (!is_email($email)) {
        return new WP_Error('invalid_email', 'E-mail invalido.', ['status' => 400]);
    }

    // Rate limit simples: 5 tentativas por IP em 10min
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if ($ip) {
        $rate_key = 'cdm_news_rate_' . md5($ip);
        $hits = (int) get_transient($rate_key);
        if ($hits >= 5) {
            return new WP_Error('rate_limited', 'Muitas tentativas, tente em alguns minutos.', ['status' => 429]);
        }
        set_transient($rate_key, $hits + 1, 10 * MINUTE_IN_SECONDS);
    }

    // Duplicata
    $existing = $wpdb->get_row($wpdb->prepare("SELECT id, coupon_code, created_at, expires_at FROM {$table} WHERE email = %s", $email));
    if ($existing) {
        return new WP_REST_Response([
            'ok'           => true,
            'duplicated'   => true,
            'coupon_code'  => $existing->coupon_code ?: CDM_NEWSLETTER_COUPON_CODE,
            'expires_at'   => $existing->expires_at,
            'message'      => 'Esse e-mail ja esta cadastrado. Aqui esta seu cupom de novo.',
        ], 200);
    }

    // Garante cupom existe
    $coupon_id = cdm_newsletter_ensure_coupon();
    if (!$coupon_id) {
        return new WP_Error('coupon_failed', 'WC indisponivel.', ['status' => 503]);
    }

    $now        = current_time('mysql');
    $expires    = date('Y-m-d H:i:s', strtotime('+30 days', strtotime($now)));
    $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : null;
    $consent    = sprintf('Consent dado em %s (IP %s)', $now, $ip);

    $inserted = $wpdb->insert($table, [
        'email'         => $email,
        'created_at'    => $now,
        'expires_at'    => $expires,
        'ip'            => $ip,
        'user_agent'    => $user_agent,
        'consent_text'  => $consent,
        'coupon_code'   => CDM_NEWSLETTER_COUPON_CODE,
    ], ['%s','%s','%s','%s','%s','%s','%s']);

    if ($inserted === false) {
        return new WP_Error('db_error', 'Erro ao salvar.', ['status' => 500]);
    }

    // Email transacional
    cdm_newsletter_send_email($email, CDM_NEWSLETTER_COUPON_CODE, $expires);

    return new WP_REST_Response([
        'ok'          => true,
        'duplicated'  => false,
        'coupon_code' => CDM_NEWSLETTER_COUPON_CODE,
        'expires_at'  => $expires,
        'message'     => 'Cupom gerado! Verifique tambem seu e-mail.',
    ], 201);
}

// ============ CORS pro front Astro ============

add_action('rest_api_init', function () {
    remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');
    add_filter('rest_pre_serve_request', function ($value) {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $allowed = [
            'https://ciadasmochilas.com.br',
            'https://dev.ciadasmochilas.com.br',
            'https://ciadasmochilas.pages.dev',
            'http://localhost:4321',
            'http://localhost:4322',
        ];
        if (in_array($origin, $allowed, true)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
            header('Access-Control-Allow-Headers: Authorization, Content-Type, X-WP-Nonce');
            header('Access-Control-Allow-Credentials: true');
            header('Vary: Origin');
        }
        return $value;
    });
}, 15);

// ============ Email transacional ============

function cdm_newsletter_send_email($email, $coupon, $expires) {
    $expires_pt = date_i18n('d/m/Y', strtotime($expires));
    $site_url   = 'https://ciadasmochilas.com.br';

    $subject = 'Seu cupom de R$ 30 OFF chegou! 🎁';

    // Wordmark textual em vez de logo com filter (que falha em Outlook/Gmail iOS).
    // Fontes em fallback seguro: tenta Fredoka -> fallback web-safe (Arial Black/Verdana).
    $html = <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="utf-8"><title>{$subject}</title></head>
<body style="margin:0;padding:0;background:#FFF8EC;font-family:Arial,Helvetica,sans-serif;color:#1A2B42;">
<div style="max-width:560px;margin:0 auto;background:white;border-radius:16px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.06);margin-top:24px;margin-bottom:24px;">
  <div style="background:linear-gradient(135deg,#1E7BB8,#0F4A7A);padding:36px 32px 32px;text-align:center;">
    <div style="font-family:'Trebuchet MS','Helvetica Neue',Arial,sans-serif;font-size:30px;font-weight:900;letter-spacing:-0.5px;color:white;line-height:1;">
      <span style="color:#FFC73C;">cia</span> <span>das</span> <span style="color:#FFC73C;">mochilas</span>
    </div>
    <div style="display:inline-block;margin-top:14px;background:rgba(255,255,255,0.18);padding:5px 14px;border-radius:9999px;font-size:11px;font-weight:700;letter-spacing:1px;color:white;text-transform:uppercase;">🎁 Bem-vinda(o)</div>
    <h1 style="color:white;font-family:'Trebuchet MS',Arial,sans-serif;font-size:24px;margin:18px 0 6px;font-weight:700;line-height:1.25;">Seu cupom de boas-vindas chegou!</h1>
    <p style="color:rgba(255,255,255,0.9);margin:0;font-size:14px;line-height:1.5;">Aproveite na sua primeira compra.</p>
  </div>
  <div style="padding:32px;text-align:center;">
    <p style="font-size:15px;line-height:1.55;margin:0 0 18px;color:#4b5563;">Cole o codigo abaixo no checkout pra receber <strong style="color:#1E7BB8;">R$ 30 OFF</strong> na sua primeira compra acima de <strong>R$ 199</strong>:</p>
    <div style="background:#FFF8EC;border:2px dashed #FFC73C;border-radius:12px;padding:18px 24px;display:inline-block;margin:8px 0 16px;">
      <div style="font-family:'Courier New',monospace;font-size:28px;font-weight:700;letter-spacing:3px;color:#1A2B42;">{$coupon}</div>
    </div>
    <p style="font-size:13px;color:#6b7280;margin:8px 0 24px;">Valido ate <strong>{$expires_pt}</strong> &middot; 1 uso por cliente</p>
    <a href="{$site_url}/loja/" style="display:inline-block;background:#FFC73C;color:#1A2B42;font-weight:700;padding:14px 32px;border-radius:9999px;text-decoration:none;font-size:15px;box-shadow:0 4px 12px rgba(255,199,60,0.4);">Comecar a comprar</a>
  </div>
  <div style="background:#FFF8EC;padding:20px 32px;text-align:center;border-top:1px solid #f3f4f6;">
    <p style="font-size:12px;color:#6b7280;margin:0 0 6px;">Cia das Mochilas Comercial Ltda &middot; CNPJ 21.095.320/0001-42</p>
    <p style="font-size:11px;color:#9ca3af;margin:0;">Voce esta recebendo este email porque se cadastrou em ciadasmochilas.com.br.</p>
  </div>
</div>
</body>
</html>
HTML;

    $headers = [
        'Content-Type: text/html; charset=UTF-8',
        'From: Cia das Mochilas <contato@ciadasmochilas.com.br>',
    ];

    return wp_mail($email, $subject, $html, $headers);
}

// ============ Tracker de uso (marca como redeemed quando o pedido for completado) ============

add_action('woocommerce_order_status_completed', function ($order_id) {
    global $wpdb;
    $order = wc_get_order($order_id);
    if (!$order) return;
    $codes = $order->get_coupon_codes();
    if (!in_array(strtolower(CDM_NEWSLETTER_COUPON_CODE), array_map('strtolower', $codes), true)) {
        return;
    }
    $email = strtolower($order->get_billing_email());
    if (!$email) return;
    $table = $wpdb->prefix . 'cdm_newsletter';
    $wpdb->update($table,
        ['redeemed' => 1, 'redeemed_order_id' => $order_id, 'redeemed_at' => current_time('mysql')],
        ['email' => $email],
        ['%d','%d','%s'],
        ['%s']
    );
});
