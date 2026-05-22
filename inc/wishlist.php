<?php
/**
 * Wishlist (favoritos) — implementacao propria, sem dependencia YITH.
 *
 * Armazena IDs de produtos favoritados em user_meta '_cdm_wishlist'
 * (array de int) e expoe:
 *   - REST endpoints  : /wp-json/cdm/v1/wishlist (GET, POST toggle, DELETE)
 *   - Endpoint WC     : /minha-conta/favoritos (lista + acoes)
 *   - Shortcode       : [cdm_wishlist_button id="X"] no PDP/loop
 *   - Cron diario     : envia 1 email por user com price-drop / back-in-stock
 *
 * Snapshot de price + stock por produto fica em user_meta
 * '_cdm_wishlist_snapshot' = [ pid => ['price'=>'9.90','stock'=>true,'ts'=>1716...] ]
 * pra detectar mudancas entre sweeps.
 */

if (!defined('ABSPATH')) {
    exit;
}

const CDM_WL_META       = '_cdm_wishlist';
const CDM_WL_SNAPSHOT   = '_cdm_wishlist_snapshot';
const CDM_WL_ENDPOINT   = 'favoritos';
const CDM_WL_CRON_HOOK  = 'cdm_wishlist_daily_alerts';

/* ============================================================
 * 1) HELPERS
 * ============================================================ */

function cdm_wl_get_ids($user_id = null) {
    $user_id = $user_id ?: get_current_user_id();
    if (!$user_id) return [];
    $ids = get_user_meta($user_id, CDM_WL_META, true);
    return is_array($ids) ? array_values(array_unique(array_map('intval', $ids))) : [];
}

function cdm_wl_set_ids($user_id, array $ids) {
    $clean = array_values(array_unique(array_filter(array_map('intval', $ids))));
    update_user_meta($user_id, CDM_WL_META, $clean);
    return $clean;
}

function cdm_wl_has($pid, $user_id = null) {
    return in_array((int) $pid, cdm_wl_get_ids($user_id), true);
}

function cdm_wl_toggle($user_id, $pid) {
    $ids = cdm_wl_get_ids($user_id);
    $pid = (int) $pid;
    if (in_array($pid, $ids, true)) {
        $ids = array_values(array_diff($ids, [$pid]));
        $added = false;
    } else {
        $ids[] = $pid;
        $added = true;
    }
    cdm_wl_set_ids($user_id, $ids);
    return ['added' => $added, 'count' => count($ids), 'ids' => $ids];
}

/* ============================================================
 * 2) REST API
 * ============================================================ */

add_action('rest_api_init', function () {
    register_rest_route('cdm/v1', '/wishlist', [
        'methods'             => 'GET',
        'callback'            => 'cdm_wl_rest_list',
        'permission_callback' => function () { return is_user_logged_in(); },
    ]);
    register_rest_route('cdm/v1', '/wishlist/toggle', [
        'methods'             => 'POST',
        'callback'            => 'cdm_wl_rest_toggle',
        'permission_callback' => function () { return is_user_logged_in(); },
        'args'                => [
            'product_id' => ['required' => true, 'type' => 'integer'],
        ],
    ]);
    register_rest_route('cdm/v1', '/wishlist/(?P<pid>\d+)', [
        'methods'             => 'DELETE',
        'callback'            => 'cdm_wl_rest_remove',
        'permission_callback' => function () { return is_user_logged_in(); },
    ]);
});

function cdm_wl_rest_list() {
    return rest_ensure_response(['ids' => cdm_wl_get_ids()]);
}

function cdm_wl_rest_toggle(WP_REST_Request $r) {
    $pid = (int) $r->get_param('product_id');
    if (!$pid || !wc_get_product($pid)) {
        return new WP_Error('cdm_wl_bad_product', 'Produto invalido', ['status' => 400]);
    }
    return rest_ensure_response(cdm_wl_toggle(get_current_user_id(), $pid));
}

function cdm_wl_rest_remove(WP_REST_Request $r) {
    $pid = (int) $r->get_param('pid');
    $ids = cdm_wl_get_ids();
    $ids = array_values(array_diff($ids, [$pid]));
    cdm_wl_set_ids(get_current_user_id(), $ids);
    return rest_ensure_response(['count' => count($ids), 'ids' => $ids]);
}

/* ============================================================
 * 3) AJAX p/ usuarios deslogados — leva pro login
 * (apenas reflete estado, nao persiste)
 * ============================================================ */

add_action('wp_enqueue_scripts', function () {
    // priority 110: roda DEPOIS do enqueue.php (priority 100) registrar 'cdm-wishlist'
    wp_localize_script('cdm-wishlist', 'cdmWishlist', [
        'restUrl'  => rest_url('cdm/v1/wishlist'),
        'nonce'    => wp_create_nonce('wp_rest'),
        'loggedIn' => is_user_logged_in(),
        'loginUrl' => wc_get_page_permalink('myaccount'),
        'ids'      => cdm_wl_get_ids(),
    ]);
}, 110);

/* ============================================================
 * 4) BOTAO HEART (shortcode + auto-inject no PDP)
 * ============================================================ */

function cdm_wl_button_html($pid) {
    $pid    = (int) $pid;
    $active = cdm_wl_has($pid);
    $label  = $active ? 'Remover dos favoritos' : 'Adicionar aos favoritos';
    $cls    = 'cdm-wishlist-toggle' . ($active ? ' is-active' : '');
    ob_start(); ?>
    <button type="button"
            class="<?php echo esc_attr($cls); ?>"
            data-pid="<?php echo $pid; ?>"
            aria-pressed="<?php echo $active ? 'true' : 'false'; ?>"
            aria-label="<?php echo esc_attr($label); ?>">
      <svg class="heart" viewBox="0 0 24 24" fill="<?php echo $active ? 'currentColor' : 'none'; ?>"
           stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78L12 21.23l8.84-8.84a5.5 5.5 0 0 0 0-7.78z"/>
      </svg>
      <span class="lbl"><?php echo $active ? 'Favoritado' : 'Favoritar'; ?></span>
    </button>
    <?php
    return ob_get_clean();
}

add_shortcode('cdm_wishlist_button', function ($atts) {
    $atts = shortcode_atts(['id' => 0], $atts);
    $pid  = (int) $atts['id'] ?: (int) get_the_ID();
    return $pid ? cdm_wl_button_html($pid) : '';
});

/* Auto-inject no PDP, logo apos add-to-cart (priority 35 — antes do meta) */
add_action('woocommerce_single_product_summary', function () {
    global $product;
    if (!$product) return;
    echo '<div class="cdm-wishlist-pdp">' . cdm_wl_button_html($product->get_id()) . '</div>';
}, 35);

/* ============================================================
 * 5) ENDPOINT WC /minha-conta/favoritos
 * ============================================================ */

add_action('init', function () {
    add_rewrite_endpoint(CDM_WL_ENDPOINT, EP_ROOT | EP_PAGES);
});

add_filter('query_vars', function ($vars) {
    $vars[] = CDM_WL_ENDPOINT;
    return $vars;
}, 0);

add_filter('woocommerce_account_menu_items', function ($items) {
    // Remove "Downloads" — loja nao vende produtos digitais
    unset($items['downloads']);
    // Insere "Favoritos" logo apos Pedidos
    $new = [];
    foreach ($items as $k => $v) {
        $new[$k] = $v;
        if ($k === 'orders') {
            $new[CDM_WL_ENDPOINT] = 'Favoritos';
        }
    }
    // Se nao havia 'orders', joga antes de logout
    if (!isset($new[CDM_WL_ENDPOINT])) {
        $logout = $items['customer-logout'] ?? null;
        unset($new['customer-logout']);
        $new[CDM_WL_ENDPOINT] = 'Favoritos';
        if ($logout) $new['customer-logout'] = $logout;
    }
    return $new;
});

add_filter('woocommerce_endpoint_' . CDM_WL_ENDPOINT . '_title', function () {
    return 'Meus favoritos';
});

add_action('woocommerce_account_' . CDM_WL_ENDPOINT . '_endpoint', 'cdm_wl_render_account_page');

function cdm_wl_render_account_page() {
    $ids = cdm_wl_get_ids();
    $shop_url = wc_get_page_permalink('shop');
    ?>
    <div class="cdm-wishlist">
      <?php if (empty($ids)) : ?>
        <div class="cdm-wishlist-empty">
          <span class="icon">💛</span>
          <p>Voce ainda nao favoritou nenhum produto.<br>Clique no coracao em qualquer item para guardar aqui.</p>
          <a href="<?php echo esc_url($shop_url); ?>">Explorar loja</a>
        </div>
      <?php else : ?>
        <p style="margin: 0 0 1rem; font-size: 0.9rem; color: #4b5563;">
          <?php echo count($ids); ?> produto<?php echo count($ids) > 1 ? 's' : ''; ?> favoritado<?php echo count($ids) > 1 ? 's' : ''; ?>.
          Vamos te avisar por e-mail se algum entrar em promocao ou voltar ao estoque.
        </p>
        <div class="cdm-wishlist-grid">
          <?php foreach ($ids as $pid) :
              $product = wc_get_product($pid);
              if (!$product) continue;
              $url   = get_permalink($pid);
              $img   = get_the_post_thumbnail_url($pid, 'woocommerce_thumbnail') ?: wc_placeholder_img_src('woocommerce_thumbnail');
              $price = $product->get_price_html();
              $cart  = $product->is_type('simple') && $product->is_in_stock()
                  ? add_query_arg(['add-to-cart' => $pid], wc_get_cart_url())
                  : $url;
              $cart_label = $product->is_type('simple') && $product->is_in_stock() ? 'Comprar' : 'Ver';
          ?>
            <div class="cdm-wishlist-item" data-pid="<?php echo $pid; ?>">
              <button type="button" class="remove cdm-wishlist-remove" data-pid="<?php echo $pid; ?>" aria-label="Remover">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
              </button>
              <a href="<?php echo esc_url($url); ?>"><img src="<?php echo esc_url($img); ?>" alt="<?php echo esc_attr($product->get_name()); ?>" loading="lazy"></a>
              <a class="name" href="<?php echo esc_url($url); ?>" style="text-decoration:none;color:inherit;"><span class="name"><?php echo esc_html($product->get_name()); ?></span></a>
              <div class="price"><?php echo $price; ?></div>
              <div class="actions">
                <a href="<?php echo esc_url($url); ?>">Detalhes</a>
                <a class="primary" href="<?php echo esc_url($cart); ?>"><?php echo esc_html($cart_label); ?></a>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
    <?php
}

/* ============================================================
 * 6) MIGRACAO YITH → custom (se YITH existir)
 * ============================================================ */

add_action('init', function () {
    if (!is_user_logged_in()) return;
    $user_id = get_current_user_id();
    // Ja migrado?
    if (get_user_meta($user_id, '_cdm_wl_migrated_yith', true)) return;
    global $wpdb;
    $table_wl = $wpdb->prefix . 'yith_wcwl';
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_wl'") !== $table_wl) {
        update_user_meta($user_id, '_cdm_wl_migrated_yith', 1);
        return;
    }
    $rows = $wpdb->get_col($wpdb->prepare(
        "SELECT prod_id FROM $table_wl WHERE user_id = %d",
        $user_id
    ));
    if ($rows) {
        $current = cdm_wl_get_ids($user_id);
        $merged  = array_values(array_unique(array_merge($current, array_map('intval', $rows))));
        cdm_wl_set_ids($user_id, $merged);
    }
    update_user_meta($user_id, '_cdm_wl_migrated_yith', 1);
}, 5);

/* ============================================================
 * 7) CRON DIARIO — alertas price drop / back-in-stock
 * ============================================================ */

register_activation_hook(__FILE__, function () {
    if (!wp_next_scheduled(CDM_WL_CRON_HOOK)) {
        wp_schedule_event(time() + 300, 'daily', CDM_WL_CRON_HOOK);
    }
});

add_action('after_switch_theme', function () {
    if (!wp_next_scheduled(CDM_WL_CRON_HOOK)) {
        wp_schedule_event(time() + 300, 'daily', CDM_WL_CRON_HOOK);
    }
});

add_action(CDM_WL_CRON_HOOK, 'cdm_wl_run_alerts');

function cdm_wl_run_alerts() {
    global $wpdb;
    // pega todos users com wishlist nao-vazia
    $user_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT user_id FROM {$wpdb->usermeta}
         WHERE meta_key = %s AND meta_value != '' AND meta_value != 'a:0:{}'",
        CDM_WL_META
    ));

    foreach ($user_ids as $user_id) {
        cdm_wl_check_user_alerts((int) $user_id);
    }
}

function cdm_wl_check_user_alerts($user_id) {
    $ids = cdm_wl_get_ids($user_id);
    if (empty($ids)) return;

    $user = get_userdata($user_id);
    if (!$user || !$user->user_email) return;

    $snapshot = get_user_meta($user_id, CDM_WL_SNAPSHOT, true);
    if (!is_array($snapshot)) $snapshot = [];
    $new_snap   = [];
    $drops      = []; // [pid, name, url, img, old_price, new_price, pct]
    $restocks   = []; // [pid, name, url, img, price]

    foreach ($ids as $pid) {
        $product = wc_get_product($pid);
        if (!$product) continue;

        $price = (float) $product->get_price();
        $stock = $product->is_in_stock();
        $new_snap[$pid] = ['price' => $price, 'stock' => $stock, 'ts' => time()];

        $prev = $snapshot[$pid] ?? null;
        if (!$prev) continue; // primeira vez vista — so registra

        // Price drop: queda >= 5%
        if ($prev['price'] > 0 && $price > 0 && $price < $prev['price']) {
            $pct = round(($prev['price'] - $price) / $prev['price'] * 100);
            if ($pct >= 5) {
                $drops[] = [
                    'pid'       => $pid,
                    'name'      => $product->get_name(),
                    'url'       => get_permalink($pid),
                    'img'       => get_the_post_thumbnail_url($pid, 'woocommerce_thumbnail'),
                    'old_price' => $prev['price'],
                    'new_price' => $price,
                    'pct'       => $pct,
                ];
            }
        }
        // Back in stock
        if (!$prev['stock'] && $stock) {
            $restocks[] = [
                'pid'   => $pid,
                'name'  => $product->get_name(),
                'url'   => get_permalink($pid),
                'img'   => get_the_post_thumbnail_url($pid, 'woocommerce_thumbnail'),
                'price' => $price,
            ];
        }
    }

    update_user_meta($user_id, CDM_WL_SNAPSHOT, $new_snap);

    if (empty($drops) && empty($restocks)) return;

    cdm_wl_send_alert_email($user, $drops, $restocks);
}

function cdm_wl_send_alert_email($user, $drops, $restocks) {
    $first = $user->first_name ?: 'tudo certo';
    $brand_color = '#0f4a7a';
    $accent      = '#fff8e1';

    $subject = 'Novidades dos seus favoritos na Cia das Mochilas';
    if (!empty($drops) && empty($restocks)) {
        $subject = sprintf('Caiu o preco de %d %s favoritado%s na Cia das Mochilas',
            count($drops), count($drops) > 1 ? 'produtos' : 'produto', count($drops) > 1 ? 's' : '');
    } elseif (empty($drops) && !empty($restocks)) {
        $subject = sprintf('%d %s da sua lista voltou ao estoque',
            count($restocks), count($restocks) > 1 ? 'favoritos' : 'favorito');
    }

    ob_start();
    ?>
    <table cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#f8fafc;padding:24px 0;font-family:Arial,Helvetica,sans-serif;">
      <tr><td align="center">
        <table cellpadding="0" cellspacing="0" border="0" width="600" style="max-width:600px;background:white;border-radius:12px;overflow:hidden;">
          <tr><td style="padding:24px 32px;background:linear-gradient(135deg,<?php echo $accent; ?> 0%,white 100%);border-bottom:1px solid #f3f4f6;">
            <h1 style="margin:0;font-size:22px;color:#0f172a;font-weight:700;">Ola, <?php echo esc_html($first); ?>!</h1>
            <p style="margin:8px 0 0;font-size:14px;color:#4b5563;line-height:1.5;">Tem novidade nos produtos que voce favoritou.</p>
          </td></tr>

          <?php if (!empty($drops)) : ?>
          <tr><td style="padding:24px 32px 8px;">
            <h2 style="margin:0 0 12px;font-size:15px;color:<?php echo $brand_color; ?>;text-transform:uppercase;letter-spacing:0.06em;">Caiu de preco</h2>
            <?php foreach ($drops as $d) : ?>
              <table cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 12px;border:1px solid #f3f4f6;border-radius:8px;">
                <tr>
                  <?php if ($d['img']) : ?>
                  <td width="80" style="padding:12px;vertical-align:top;">
                    <a href="<?php echo esc_url($d['url']); ?>"><img src="<?php echo esc_url($d['img']); ?>" width="64" height="64" style="border-radius:6px;display:block;" alt=""></a>
                  </td>
                  <?php endif; ?>
                  <td style="padding:12px;vertical-align:top;">
                    <a href="<?php echo esc_url($d['url']); ?>" style="font-size:14px;color:#0f172a;text-decoration:none;font-weight:600;"><?php echo esc_html($d['name']); ?></a>
                    <div style="margin-top:6px;font-size:13px;">
                      <span style="color:#9ca3af;text-decoration:line-through;"><?php echo wc_price($d['old_price']); ?></span>
                      &nbsp;<span style="color:<?php echo $brand_color; ?>;font-weight:700;font-size:15px;"><?php echo wc_price($d['new_price']); ?></span>
                      &nbsp;<span style="background:#dcfce7;color:#166534;padding:2px 6px;border-radius:4px;font-size:11px;font-weight:600;">-<?php echo $d['pct']; ?>%</span>
                    </div>
                  </td>
                </tr>
              </table>
            <?php endforeach; ?>
          </td></tr>
          <?php endif; ?>

          <?php if (!empty($restocks)) : ?>
          <tr><td style="padding:24px 32px 8px;">
            <h2 style="margin:0 0 12px;font-size:15px;color:<?php echo $brand_color; ?>;text-transform:uppercase;letter-spacing:0.06em;">Voltou ao estoque</h2>
            <?php foreach ($restocks as $r) : ?>
              <table cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 12px;border:1px solid #f3f4f6;border-radius:8px;">
                <tr>
                  <?php if ($r['img']) : ?>
                  <td width="80" style="padding:12px;vertical-align:top;">
                    <a href="<?php echo esc_url($r['url']); ?>"><img src="<?php echo esc_url($r['img']); ?>" width="64" height="64" style="border-radius:6px;display:block;" alt=""></a>
                  </td>
                  <?php endif; ?>
                  <td style="padding:12px;vertical-align:top;">
                    <a href="<?php echo esc_url($r['url']); ?>" style="font-size:14px;color:#0f172a;text-decoration:none;font-weight:600;"><?php echo esc_html($r['name']); ?></a>
                    <div style="margin-top:6px;font-size:13px;color:<?php echo $brand_color; ?>;font-weight:700;"><?php echo wc_price($r['price']); ?></div>
                  </td>
                </tr>
              </table>
            <?php endforeach; ?>
          </td></tr>
          <?php endif; ?>

          <tr><td style="padding:8px 32px 24px;">
            <a href="<?php echo esc_url(wc_get_account_endpoint_url(CDM_WL_ENDPOINT)); ?>"
               style="display:inline-block;background:<?php echo $brand_color; ?>;color:white;padding:12px 24px;border-radius:9999px;text-decoration:none;font-weight:600;font-size:14px;">Ver meus favoritos</a>
          </td></tr>

          <tr><td style="padding:18px 32px;background:#f8fafc;border-top:1px solid #f3f4f6;text-align:center;font-size:12px;color:#6b7280;line-height:1.5;">
            Voce recebeu este e-mail porque favoritou produtos na <strong>Cia das Mochilas</strong>.<br>
            Para desativar os alertas, basta remover os produtos dos seus favoritos.
          </td></tr>
        </table>
      </td></tr>
    </table>
    <?php
    $html = ob_get_clean();

    $headers = [
        'Content-Type: text/html; charset=UTF-8',
        'From: Cia das Mochilas <contato@ciadasmochilas.com.br>',
    ];

    wp_mail($user->user_email, $subject, $html, $headers);
}
