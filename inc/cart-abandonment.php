<?php
/**
 * Cart Abandonment Recovery.
 *
 * Captura email do cliente no checkout (debounce 1.5s), salva snapshot do
 * cart em tabela propria, e via cron dispara 2 emails:
 *   - 1h apos abandono  → "Voce esqueceu algo!"
 *   - 24h apos abandono → "Ultima chance! 10% off com cupom RECUPERA10"
 *
 * Quando o pedido e' finalizado (woocommerce_thankyou), marca como recovered.
 */

if (!defined('ABSPATH')) {
    exit;
}

const CDM_AB_TABLE     = 'cdm_abandoned_carts';
const CDM_AB_VER       = '1.0.0';
const CDM_AB_CRON_HOOK = 'cdm_abandonment_sweep';
const CDM_AB_COUPON    = 'RECUPERA10';

/* ============================================================
 * 1) Tabela
 * ============================================================ */

function cdm_ab_install() {
    global $wpdb;
    $table = $wpdb->prefix . CDM_AB_TABLE;
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        email VARCHAR(190) NOT NULL,
        cart_token VARCHAR(64) NOT NULL,
        cart_data LONGTEXT NOT NULL,
        cart_total DECIMAL(10,2) NOT NULL DEFAULT 0,
        item_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        captured_at DATETIME NOT NULL,
        recovered_at DATETIME DEFAULT NULL,
        email_1h_sent_at DATETIME DEFAULT NULL,
        email_24h_sent_at DATETIME DEFAULT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        PRIMARY KEY  (id),
        UNIQUE KEY cart_token (cart_token),
        KEY email (email),
        KEY status (status),
        KEY captured_at (captured_at)
    ) $charset;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
    update_option('cdm_ab_db_version', CDM_AB_VER);
}
add_action('after_switch_theme', 'cdm_ab_install');
add_action('init', function () {
    if (get_option('cdm_ab_db_version') !== CDM_AB_VER) cdm_ab_install();
}, 5);

/* ============================================================
 * 2) Cupom RECUPERA10 (criado on-demand)
 * ============================================================ */

function cdm_ab_ensure_coupon() {
    static $checked = false;
    if ($checked) return;
    $checked = true;

    $exists = get_page_by_path(CDM_AB_COUPON, OBJECT, 'shop_coupon');
    if ($exists) return;

    $coupon = new WC_Coupon();
    $coupon->set_code(CDM_AB_COUPON);
    $coupon->set_discount_type('percent');
    $coupon->set_amount(10);
    $coupon->set_individual_use(true);
    $coupon->set_usage_limit_per_user(1);
    $coupon->set_minimum_amount(50);
    $coupon->set_description('Cupom auto-gerado para recuperacao de carrinho abandonado.');
    $coupon->save();
}

/* ============================================================
 * 3) Captura via REST (chamada pelo JS no checkout)
 * ============================================================ */

add_action('rest_api_init', function () {
    register_rest_route('cdm/v1', '/abandonment/capture', [
        'methods'             => 'POST',
        'callback'            => 'cdm_ab_rest_capture',
        'permission_callback' => '__return_true',
        'args'                => [
            'email' => ['required' => true, 'type' => 'string'],
        ],
    ]);
});

function cdm_ab_rest_capture(WP_REST_Request $r) {
    $email = sanitize_email($r->get_param('email'));
    if (!is_email($email)) {
        return new WP_Error('cdm_ab_invalid_email', 'Email invalido', ['status' => 400]);
    }
    if (!WC()->cart || WC()->cart->is_empty()) {
        return rest_ensure_response(['skipped' => 'empty_cart']);
    }

    // Token unico por sessao
    if (!WC()->session) {
        WC()->session = new WC_Session_Handler();
        WC()->session->init();
    }
    $token = WC()->session->get('cdm_ab_token');
    if (!$token) {
        $token = bin2hex(random_bytes(24));
        WC()->session->set('cdm_ab_token', $token);
    }

    global $wpdb;
    $table = $wpdb->prefix . CDM_AB_TABLE;

    // Snapshot leve dos itens
    $items = [];
    foreach (WC()->cart->get_cart() as $item) {
        $product = $item['data'];
        $items[] = [
            'product_id'   => $item['product_id'],
            'variation_id' => $item['variation_id'] ?? 0,
            'quantity'     => $item['quantity'],
            'name'         => $product ? $product->get_name() : '',
            'price'        => $product ? (float) $product->get_price() : 0,
        ];
    }
    $cart_data = wp_json_encode([
        'items'    => $items,
        'subtotal' => (float) WC()->cart->get_subtotal(),
        'total'    => (float) WC()->cart->get_total('edit'),
    ]);

    $row = [
        'email'      => $email,
        'cart_token' => $token,
        'cart_data'  => $cart_data,
        'cart_total' => (float) WC()->cart->get_total('edit'),
        'item_count' => count($items),
        'captured_at'=> current_time('mysql'),
        'status'     => 'pending',
    ];

    // UPSERT por cart_token (mesmo cart_token = atualiza em vez de duplicar)
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $table WHERE cart_token = %s", $token
    ));
    if ($existing) {
        $wpdb->update($table, [
            'email'       => $email,
            'cart_data'   => $cart_data,
            'cart_total'  => $row['cart_total'],
            'item_count'  => $row['item_count'],
            'captured_at' => $row['captured_at'],
        ], ['id' => $existing]);
        return rest_ensure_response(['updated' => true, 'id' => (int) $existing]);
    }
    $wpdb->insert($table, $row);
    return rest_ensure_response(['inserted' => true, 'id' => (int) $wpdb->insert_id]);
}

/* ============================================================
 * 4) Marca como recovered quando pedido e' criado
 * ============================================================ */

add_action('woocommerce_checkout_order_processed', function ($order_id) {
    global $wpdb;
    if (!WC()->session) return;
    $token = WC()->session->get('cdm_ab_token');
    if (!$token) return;
    $table = $wpdb->prefix . CDM_AB_TABLE;
    $wpdb->update(
        $table,
        ['status' => 'recovered', 'recovered_at' => current_time('mysql')],
        ['cart_token' => $token]
    );
}, 10, 1);

/* ============================================================
 * 5) JS de captura no checkout
 * ============================================================ */

add_action('wp_enqueue_scripts', function () {
    if (!function_exists('is_checkout') || !is_checkout()) return;
    wp_localize_script('cia-checkout-cep', 'cdmAbandonment', [
        'restUrl' => rest_url('cdm/v1/abandonment/capture'),
        'nonce'   => wp_create_nonce('wp_rest'),
    ]);
}, 115);

add_action('wp_footer', function () {
    if (!function_exists('is_checkout') || !is_checkout()) return;
    if (is_wc_endpoint_url('order-received') || is_wc_endpoint_url('pedido-recebido')) return;
    ?>
    <script>
    (function () {
      if (!window.cdmAbandonment) return;
      var cfg = window.cdmAbandonment;
      var lastSent = '';
      var timer = null;
      var EMAIL_RX = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      function capture(email) {
        if (!email || email === lastSent || !EMAIL_RX.test(email)) return;
        lastSent = email;
        fetch(cfg.restUrl, {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': cfg.nonce,
          },
          body: JSON.stringify({ email: email }),
        }).catch(function () { lastSent = ''; });
      }
      function bindEmail() {
        var el = document.querySelector('#billing_email');
        if (!el || el.dataset.cdmAbBound === '1') return;
        el.dataset.cdmAbBound = '1';
        el.addEventListener('input', function () {
          clearTimeout(timer);
          var v = el.value.trim();
          timer = setTimeout(function () { capture(v); }, 1500);
        });
        el.addEventListener('blur', function () {
          var v = el.value.trim();
          if (v) capture(v);
        });
      }
      bindEmail();
      if (window.jQuery) jQuery(document.body).on('updated_checkout', bindEmail);
    })();
    </script>
    <?php
}, 99);

/* ============================================================
 * 6) Cron sweep — envia 1h e 24h
 * ============================================================ */

add_action('after_switch_theme', function () {
    if (!wp_next_scheduled(CDM_AB_CRON_HOOK)) {
        wp_schedule_event(time() + 600, 'hourly', CDM_AB_CRON_HOOK);
    }
});

add_action(CDM_AB_CRON_HOOK, 'cdm_ab_sweep');

function cdm_ab_sweep() {
    global $wpdb;
    $table = $wpdb->prefix . CDM_AB_TABLE;

    // 1) Email 1h: capturados entre 1h e 2h atras, sem email_1h enviado
    $rows_1h = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table
         WHERE status = 'pending'
           AND email_1h_sent_at IS NULL
           AND captured_at <= %s
           AND captured_at >= %s",
        date('Y-m-d H:i:s', strtotime('-1 hour')),
        date('Y-m-d H:i:s', strtotime('-3 hours'))
    ));
    foreach ($rows_1h as $row) {
        if (cdm_ab_send_email($row, '1h')) {
            $wpdb->update($table, ['email_1h_sent_at' => current_time('mysql')], ['id' => $row->id]);
        }
    }

    // 2) Email 24h: capturados entre 24h e 48h, sem email_24h
    $rows_24h = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table
         WHERE status = 'pending'
           AND email_24h_sent_at IS NULL
           AND captured_at <= %s
           AND captured_at >= %s",
        date('Y-m-d H:i:s', strtotime('-24 hours')),
        date('Y-m-d H:i:s', strtotime('-72 hours'))
    ));
    foreach ($rows_24h as $row) {
        if (cdm_ab_send_email($row, '24h')) {
            $wpdb->update($table, ['email_24h_sent_at' => current_time('mysql')], ['id' => $row->id]);
        }
    }

    // 3) Cleanup: descarta >30 dias
    $wpdb->query($wpdb->prepare(
        "DELETE FROM $table WHERE captured_at < %s",
        date('Y-m-d H:i:s', strtotime('-30 days'))
    ));
}

function cdm_ab_send_email($row, $variant) {
    $cart = json_decode($row->cart_data, true);
    if (empty($cart['items'])) return false;

    // Garante cupom criado (so quando vai usar)
    if ($variant === '24h') cdm_ab_ensure_coupon();

    $cart_url = wc_get_cart_url() . '?cdm_ab=' . rawurlencode($row->cart_token);
    $brand    = '#0f4a7a';
    $accent   = '#fff8e1';

    if ($variant === '1h') {
        $subject = 'Voce esqueceu algo na Cia das Mochilas';
        $headline = 'Seu carrinho esta esperando!';
        $sub = 'Notamos que voce deixou algumas coisas no carrinho. Quer finalizar agora?';
        $cta_label = 'Voltar ao meu carrinho';
        $extra = '';
    } else {
        $subject = 'Ultima chance: 10% off no seu carrinho';
        $headline = 'Volte e ganhe 10% off!';
        $sub = 'Pra incentivar voce a finalizar, separamos 10% de desconto no cupom abaixo. Valido por 48h.';
        $cta_label = 'Aplicar cupom e finalizar';
        $extra = '<div style="background:' . $accent . ';border-radius:10px;padding:14px 18px;margin:12px 0;text-align:center;border:1px dashed #f59e0b;">'
               . '<div style="font-size:11px;color:#92400e;text-transform:uppercase;letter-spacing:0.06em;font-weight:700;margin-bottom:4px;">Seu cupom</div>'
               . '<div style="font-family:ui-monospace,monospace;font-size:24px;font-weight:700;color:#92400e;letter-spacing:0.1em;">' . CDM_AB_COUPON . '</div>'
               . '<div style="font-size:11px;color:#92400e;margin-top:4px;">10% off · pedido min RS 50</div>'
               . '</div>';
        $cart_url = add_query_arg(['coupon' => CDM_AB_COUPON, 'cdm_ab' => $row->cart_token], wc_get_cart_url());
    }

    ob_start();
    ?>
    <p style="font-size:15px;margin:0 0 12px;">Ola! &#128075;</p>
    <p style="margin:0 0 14px;"><?php echo esc_html($sub); ?></p>

    <?php echo $extra; ?>

    <h2 style="margin:18px 0 12px;">No seu carrinho:</h2>
    <table border="0" cellpadding="0" cellspacing="0" width="100%" style="border:1px solid #e5e7eb;border-radius:10px;border-collapse:separate;border-spacing:0;overflow:hidden;">
      <?php foreach ($cart['items'] as $item) :
          $img = get_the_post_thumbnail_url($item['product_id'], 'woocommerce_thumbnail');
      ?>
      <tr>
        <?php if ($img) : ?>
        <td width="72" style="padding:10px;vertical-align:top;border-bottom:1px solid #f3f4f6;">
          <img src="<?php echo esc_url($img); ?>" width="56" height="56" style="border-radius:6px;display:block;" alt="">
        </td>
        <?php endif; ?>
        <td style="padding:10px;vertical-align:top;border-bottom:1px solid #f3f4f6;">
          <div style="font-size:14px;font-weight:600;color:#1f2937;line-height:1.4;"><?php echo esc_html($item['name']); ?></div>
          <div style="font-size:12px;color:#6b7280;margin-top:3px;">Qtd: <?php echo (int) $item['quantity']; ?> &nbsp;·&nbsp; <?php echo wc_price($item['price']); ?></div>
        </td>
      </tr>
      <?php endforeach; ?>
      <tr>
        <td colspan="2" style="padding:14px;background:#f8fafc;text-align:right;">
          <strong style="font-size:13px;color:#6b7280;text-transform:uppercase;letter-spacing:0.05em;">Total: </strong>
          <strong style="font-size:18px;color:<?php echo $brand; ?>;"><?php echo wc_price($row->cart_total); ?></strong>
        </td>
      </tr>
    </table>

    <p style="text-align:center;margin:24px 0 12px;">
      <a href="<?php echo esc_url($cart_url); ?>" style="display:inline-block;background:#ffc73c;color:#1f2937;padding:14px 32px;border-radius:9999px;text-decoration:none;font-weight:700;font-size:15px;box-shadow:0 4px 12px rgba(255,199,60,0.4);"><?php echo esc_html($cta_label); ?></a>
    </p>
    <?php
    $body = ob_get_clean();

    // Wrap em template WC
    $mailer = WC()->mailer();
    $html = $mailer->wrap_message($headline, $body);

    return $mailer->send($row->email, $subject, $html, [
        'Content-Type: text/html; charset=UTF-8',
        'From: Cia das Mochilas <contato@ciadasmochilas.com.br>',
    ]);
}

/* ============================================================
 * 7) Aplica cupom automatico quando link tem ?coupon=
 * ============================================================ */

add_action('template_redirect', function () {
    if (empty($_GET['coupon'])) return;
    $code = sanitize_text_field($_GET['coupon']);
    if (!function_exists('WC') || !WC()->cart) return;
    if (!WC()->cart->has_discount($code)) {
        WC()->cart->apply_coupon($code);
    }
});
