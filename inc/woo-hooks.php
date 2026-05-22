<?php
/**
 * WooCommerce hooks: injetar trust strips, badges, etc nas paginas WC.
 *
 * Fase 0: vazio. Fase 3+ vai popular com trust strip cart/checkout, badges,
 * mensagens contextuais, etc.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Mensagem amigavel de agradecimento na pagina order-received (apos checkout).
 * Substitui o 'Thank you. Your order has been received.' default do WC.
 */
add_filter('woocommerce_thankyou_order_received_text', function ($text, $order) {
    if (!$order) return $text;
    $first_name = $order->get_billing_first_name();
    $greeting   = $first_name ? esc_html($first_name) : 'tudo certo';
    $email      = esc_html($order->get_billing_email());
    return sprintf(
        '<span class="cdm-thankyou-hello"><span class="cdm-wave" aria-hidden="true">👋</span> Obrigado, %s!</span>' .
        '<span class="cdm-thankyou-sub">Recebemos seu pedido e ja estamos preparando tudo com carinho. Acompanhe o status pelo e-mail %s ou pela area %sMinha conta%s.</span>',
        $greeting,
        '<strong>' . $email . '</strong>',
        '<a href="' . esc_url(wc_get_account_endpoint_url('orders')) . '">',
        '</a>'
    );
}, 10, 2);

/**
 * Move o cross-sell do carrinho da sidebar para abaixo do form do cart.
 *
 * Default do WC: 'woocommerce_cart_collaterals' agrupa cross-sells +
 * cart-totals na mesma coluna. Em layouts 2-col com totals sticky, isso
 * cria uma sidebar interminavel. Mover cross-sells para depois do form
 * (full-width) replica o padrao "Voce tambem pode gostar" do PDP.
 */
add_action('init', function () {
    if (!function_exists('woocommerce_cross_sell_display')) {
        return;
    }
    remove_action('woocommerce_cart_collaterals', 'woocommerce_cross_sell_display');
    add_action('woocommerce_after_cart', 'woocommerce_cross_sell_display', 5);
});

/**
 * Substitui o dashboard plain "Olá, X! Você pode visualizar..." por uma boas-vindas
 * + 4 cards de atalhos (Pedidos / Endereços / Conta / Sair).
 *
 * Removemos o conteudo default WC (woocommerce_account_dashboard) e renderizamos
 * o nosso. Markup compativel com .cdm-account-welcome + .cdm-account-shortcuts
 * estilizados em assets/css/woo-account.css.
 */
/* Hook nosso handler no action do template dashboard.php (override em
   woocommerce/myaccount/dashboard.php que removeu o texto default WC).
   Sem function_exists check — function 'woocommerce_account_dashboard'
   nao existe no WC core (texto vem direto do template), e o guard estava
   abortando o registro silenciosamente. */
add_action('woocommerce_account_dashboard', 'cia_astro_account_dashboard');

/* Plugin Vindi Pagamentos tem hook woocommerce_locate_template priority 10
   que sobrescreve templates do tema (inclusive nosso dashboard.php override),
   fazendo voltar o texto default WC. Forcamos nosso path com priority maior. */
add_filter('woocommerce_locate_template', function ($template, $template_name, $template_path) {
    $our_overrides = [
        'myaccount/dashboard.php',
        'emails/email-header.php',
        'emails/email-footer.php',
    ];
    if (in_array($template_name, $our_overrides, true)) {
        $our = CIA_ASTRO_DIR . '/woocommerce/' . $template_name;
        if (file_exists($our)) {
            return $our;
        }
    }
    return $template;
}, 99, 3);

function cia_astro_account_dashboard() {
    $current_user = wp_get_current_user();
    $first_name   = $current_user->first_name ?: $current_user->display_name;

    $orders_url    = wc_get_account_endpoint_url('orders');
    $favorites_url = wc_get_account_endpoint_url('favoritos');
    $addresses_url = wc_get_account_endpoint_url('edit-address');
    $account_url   = wc_get_account_endpoint_url('edit-account');
    $shop_url      = function_exists('cia_astro_frontend_url')
        ? cia_astro_frontend_url('/loja/')
        : home_url('/loja/');

    // Saudacao dinamica por hora do dia (BRT = current_time)
    $hour = (int) current_time('H');
    if ($hour >= 5 && $hour < 12)       { $greet = 'Bom dia';       $emoji = '☀️'; $cls = 'morning'; }
    elseif ($hour >= 12 && $hour < 18)  { $greet = 'Boa tarde';     $emoji = '🌤️'; $cls = 'afternoon'; }
    elseif ($hour >= 18 && $hour < 23)  { $greet = 'Boa noite';     $emoji = '🌙'; $cls = 'evening'; }
    else                                 { $greet = 'Boa madrugada'; $emoji = '✨'; $cls = 'night'; }

    // Stats rapidos (orders count + favoritos count)
    $orders_count = wc_get_customer_order_count($current_user->ID);
    $fav_count    = function_exists('cdm_wl_get_ids') ? count(cdm_wl_get_ids($current_user->ID)) : 0;

    // Ultimo pedido (se houver, pra hero card)
    $last_orders = wc_get_orders([
        'customer_id' => $current_user->ID,
        'limit'       => 1,
        'orderby'     => 'date',
        'order'       => 'DESC',
        'status'      => ['processing','on-hold','completed','pending'],
    ]);
    $last_order = !empty($last_orders) ? $last_orders[0] : null;

    // Cookie key inclui data BRT — confete dispara so 1x por dia
    $cookie_key = 'cdm_dash_seen_' . current_time('Y-m-d');
    ?>
    <div class="cdm-account-welcome cdm-greet-<?php echo esc_attr($cls); ?>"
         data-confetti-cookie="<?php echo esc_attr($cookie_key); ?>">
      <h2>
        <span class="cdm-greet-emoji" aria-hidden="true"><?php echo $emoji; ?></span>
        <?php echo esc_html($greet); ?>, <?php echo esc_html($first_name); ?>!
      </h2>
      <p>Que bom te ver por aqui. Aqui voc&ecirc; acompanha pedidos, gerencia favoritos e atualiza seus dados.</p>

      <!-- Mini stats: pedidos + favoritos -->
      <div class="cdm-account-stats" aria-label="Resumo da conta">
        <div class="cdm-stat">
          <span class="cdm-stat-num"><?php echo (int) $orders_count; ?></span>
          <span class="cdm-stat-lbl"><?php echo $orders_count === 1 ? 'Pedido' : 'Pedidos'; ?></span>
        </div>
        <div class="cdm-stat">
          <span class="cdm-stat-num"><?php echo (int) $fav_count; ?></span>
          <span class="cdm-stat-lbl"><?php echo $fav_count === 1 ? 'Favorito' : 'Favoritos'; ?></span>
        </div>
        <div class="cdm-stat cdm-stat-cta">
          <a href="<?php echo esc_url($shop_url); ?>">Continuar comprando &rarr;</a>
        </div>
      </div>

      <!-- Confete container (so renderizado no 1o acesso do dia via JS) -->
      <div class="cdm-confetti-burst" aria-hidden="true"></div>
    </div>

    <?php if ($last_order) : ?>
      <?php
        $status      = $last_order->get_status();
        $status_lbl  = wc_get_order_status_name($status);
        $status_cls  = 'cdm-status-' . sanitize_html_class($status);
        $order_url   = $last_order->get_view_order_url();
        $order_num   = $last_order->get_order_number();
        $order_total = $last_order->get_formatted_order_total();
        $order_date  = $last_order->get_date_created() ? $last_order->get_date_created()->date_i18n('d/m/Y') : '';
      ?>
      <a class="cdm-last-order <?php echo esc_attr($status_cls); ?>" href="<?php echo esc_url($order_url); ?>">
        <div class="cdm-last-order-head">
          <span class="cdm-last-order-eyebrow">Último pedido</span>
          <span class="cdm-last-order-status"><?php echo esc_html($status_lbl); ?></span>
        </div>
        <div class="cdm-last-order-body">
          <strong>#<?php echo esc_html($order_num); ?></strong>
          <span class="cdm-last-order-date"><?php echo esc_html($order_date); ?></span>
          <span class="cdm-last-order-total"><?php echo wp_kses_post($order_total); ?></span>
        </div>
        <span class="cdm-last-order-cta">Ver detalhes &rarr;</span>
      </a>
    <?php endif; ?>

    <h3 class="cdm-shortcuts-title">Atalhos r&aacute;pidos</h3>
    <div class="cdm-account-shortcuts">
      <a href="<?php echo esc_url($orders_url); ?>" class="cdm-shortcut cdm-shortcut-orders">
        <span class="ico" aria-hidden="true">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
        </span>
        <span class="label">Meus pedidos</span>
        <span class="hint">Status, rastreio e detalhes</span>
      </a>
      <a href="<?php echo esc_url($favorites_url); ?>" class="cdm-shortcut cdm-shortcut-favorites">
        <span class="ico" aria-hidden="true">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78L12 21.23l8.84-8.84a5.5 5.5 0 0 0 0-7.78z"/></svg>
        </span>
        <span class="label">Favoritos</span>
        <span class="hint">Sua lista de desejos</span>
      </a>
      <a href="<?php echo esc_url($addresses_url); ?>" class="cdm-shortcut cdm-shortcut-addresses">
        <span class="ico" aria-hidden="true">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
        </span>
        <span class="label">Endere&ccedil;os</span>
        <span class="hint">Cobran&ccedil;a e entrega</span>
      </a>
      <a href="<?php echo esc_url($account_url); ?>" class="cdm-shortcut cdm-shortcut-account">
        <span class="ico" aria-hidden="true">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-7 8-7s8 3 8 7"/></svg>
        </span>
        <span class="label">Detalhes da conta</span>
        <span class="hint">Nome, e-mail e senha</span>
      </a>
    </div>
    <?php
}

/**
 * Calculador de frete simplificado: cliente preenche APENAS o CEP.
 * Pais, Estado e Cidade ficam ocultos (Brasil + SP default ou inferido do CEP
 * pelo Melhor Envio/Correios via shipping zones do WC).
 *
 * Hide via filter dos shipping fields (mais robusto que CSS).
 */
add_filter('woocommerce_shipping_calculator_enable_country', '__return_false');
add_filter('woocommerce_shipping_calculator_enable_state',   '__return_false');
add_filter('woocommerce_shipping_calculator_enable_city',    '__return_false');
add_filter('woocommerce_shipping_calculator_enable_postcode','__return_true');

/**
 * Loja vende apenas para o Brasil. Esconde o campo Pais do checkout
 * (billing + shipping) e forca BR como default. Mantém o valor no banco
 * (WC ainda precisa do country pra calcular frete/imposto).
 *
 * Tambem torna o Telefone billing OPCIONAL real (label ja dizia "opcional"
 * mas WC validava como required, bloqueando submit silenciosamente).
 */
add_filter('woocommerce_checkout_fields', function ($fields) {
    if (isset($fields['billing']['billing_country'])) {
        $fields['billing']['billing_country']['type']     = 'hidden';
        $fields['billing']['billing_country']['default']  = 'BR';
        $fields['billing']['billing_country']['label']    = '';
        $fields['billing']['billing_country']['class']    = ['cdm-hidden-field'];
    }
    if (isset($fields['shipping']['shipping_country'])) {
        $fields['shipping']['shipping_country']['type']    = 'hidden';
        $fields['shipping']['shipping_country']['default'] = 'BR';
        $fields['shipping']['shipping_country']['label']   = '';
        $fields['shipping']['shipping_country']['class']   = ['cdm-hidden-field'];
    }
    // Telefone OBRIGATORIO (decisao cliente 2026-05-22 — necessario pra
    // contato sobre entrega). Validacao Ajax bloqueia submit.
    if (isset($fields['billing']['billing_phone'])) {
        $fields['billing']['billing_phone']['required'] = true;
        $fields['billing']['billing_phone']['label']    = 'Telefone';
    }
    // Bairro OBRIGATORIO (ECFB plugin marca como opcional default).
    // ViaCEP autocomplete preenche automaticamente quando CEP valido.
    if (isset($fields['billing']['billing_neighborhood'])) {
        $fields['billing']['billing_neighborhood']['required'] = true;
        $fields['billing']['billing_neighborhood']['label']    = 'Bairro';
    }
    if (isset($fields['shipping']['shipping_neighborhood'])) {
        $fields['shipping']['shipping_neighborhood']['required'] = true;
        $fields['shipping']['shipping_neighborhood']['label']    = 'Bairro';
    }
    return $fields;
}, 20);

// Garante BR como default mesmo se WC reverter
add_filter('default_checkout_billing_country',  function () { return 'BR'; });
add_filter('default_checkout_shipping_country', function () { return 'BR'; });
add_filter('woocommerce_customer_default_location', function () { return 'BR'; });
add_filter('woocommerce_countries_allowed_countries', function () { return ['BR' => 'Brasil']; });

/**
 * Checkbox "Criar conta" marcado por default no checkout + auto-login.
 * Decisao 2026-05-22: maior captura de leads sem barrar guest checkout.
 */
add_filter('woocommerce_create_account_default_checked', '__return_true');

// Customiza copy do checkbox pra ser mais convidativo
add_filter('woocommerce_checkout_fields', function ($fields) {
    add_filter('gettext', function ($translation, $text, $domain) {
        if ($domain === 'woocommerce' && $text === 'Create an account?') {
            return 'Criar minha conta (acompanhar pedidos e receber alertas dos favoritos)';
        }
        return $translation;
    }, 20, 3);
    return $fields;
}, 30);

// Auto-login apos criar conta no checkout — cliente sai logado da pagina de obrigado
add_action('woocommerce_created_customer', function ($customer_id) {
    if (is_user_logged_in() || !$customer_id) return;
    wc_set_customer_auth_cookie($customer_id);
}, 10, 1);

/**
 * Nudge "Faltam R$ X para frete gratis" — barra de progresso topo do cart.
 * Le threshold real do free_shipping ativo (zona Brasil = R$ 199 em 2026-05).
 */
function cdm_get_free_shipping_threshold() {
    static $cache = null;
    if ($cache !== null) return $cache;

    $threshold = 0;
    $zones = WC_Shipping_Zones::get_zones();
    foreach ($zones as $zone) {
        foreach ($zone['shipping_methods'] as $m) {
            if ($m->id === 'free_shipping' && $m->enabled === 'yes' && in_array($m->requires, ['min_amount', 'either', 'both'], true)) {
                $amt = (float) $m->min_amount;
                if ($amt > 0) {
                    $threshold = $threshold ? min($threshold, $amt) : $amt;
                }
            }
        }
    }
    // Tambem checa "Rest of the world" (zone 0)
    $rest = (new WC_Shipping_Zone(0))->get_shipping_methods();
    foreach ($rest as $m) {
        if ($m->id === 'free_shipping' && $m->enabled === 'yes' && in_array($m->requires, ['min_amount', 'either', 'both'], true)) {
            $amt = (float) $m->min_amount;
            if ($amt > 0) {
                $threshold = $threshold ? min($threshold, $amt) : $amt;
            }
        }
    }
    $cache = $threshold;
    return $threshold;
}

add_action('woocommerce_before_cart', 'cdm_render_free_shipping_nudge', 5);
add_action('woocommerce_before_checkout_form', 'cdm_render_free_shipping_nudge', 5);

function cdm_render_free_shipping_nudge() {
    if (!function_exists('WC') || !WC()->cart) return;
    $threshold = cdm_get_free_shipping_threshold();
    if (!$threshold) return;

    $subtotal = (float) WC()->cart->get_displayed_subtotal();
    // Se WC inclui taxes no displayed_subtotal e o free_shipping considera so subtotal antes, ajustar:
    $sub_no_tax = (float) WC()->cart->get_subtotal();
    $base = max($subtotal, $sub_no_tax);

    if ($base <= 0) return;

    $pct = min(100, ($base / $threshold) * 100);
    $missing = max(0, $threshold - $base);

    // SVG inline (substitui emoji que renderiza como tofu na font do site)
    $svg_truck = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>';
    $svg_check = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>';

    if ($missing <= 0) {
        ?>
        <div class="cdm-freeship-nudge cdm-freeship-won">
          <span class="ico" aria-hidden="true"><?php echo $svg_check; ?></span>
          <div class="msg">
            <strong>Voce ganhou frete gratis!</strong>
            <div class="bar"><span style="width:100%;"></span></div>
          </div>
        </div>
        <?php
    } else {
        ?>
        <div class="cdm-freeship-nudge">
          <span class="ico" aria-hidden="true"><?php echo $svg_truck; ?></span>
          <div class="msg">
            <span>Faltam <strong><?php echo wp_kses_post(wc_price($missing)); ?></strong> para voce ganhar <strong>frete gratis</strong>!</span>
            <div class="bar"><span style="width:<?php echo round($pct, 1); ?>%;"></span></div>
          </div>
        </div>
        <?php
    }
}

/**
 * Reformula label de shipping_method no cart_totals + review_order:
 *   ANTES: 'Loggi Express (Melhor Envio) (2 a 3 dias uteis): R$ 9,02'
 *   DEPOIS: nome limpo + 'X a Y dias uteis' menor + preco a direita
 */
add_filter('woocommerce_cart_shipping_method_full_label', function ($label, $method) {
    if (!$method) return $label;
    $name = $method->get_label();

    // Extrai prazo "(X a Y dias uteis)"
    $prazo = null;
    if (preg_match('/\((\d+\s*a\s*\d+\s*dias?\s*[^)]+)\)/iu', $name, $m)) {
        $prazo = trim($m[1]);
    }

    // Limpa nome: remove "(Melhor Envio)" e o prazo
    $clean = preg_replace('/\s*\(Melhor Envio\)/iu', '', $name);
    $clean = preg_replace('/\s*\(\d+\s*a\s*\d+\s*dias?\s*[^)]+\)/iu', '', $clean);
    $clean = trim($clean);

    // Preco
    $cost = (float) $method->cost;
    $taxes = $method->get_shipping_tax();
    $price_html = wc_price($cost + (float) $taxes);

    $html  = '<span class="cdm-ship-method">';
    $html .= '<span class="cdm-ship-info">';
    $html .= '<span class="cdm-ship-name">' . esc_html($clean) . '</span>';
    if ($prazo) {
        $html .= '<small class="cdm-ship-prazo">' . esc_html($prazo) . '</small>';
    }
    $html .= '</span>';
    $html .= '<span class="cdm-ship-price">' . $price_html . '</span>';
    $html .= '</span>';

    return $html;
}, 10, 2);

/**
 * Pix-first banner: se metodo de pagamento for Vindi Pix/Bolepix, mostra
 * destaque acima do QR Vindi com instrucoes claras (pague em segundos).
 * Roda priority 4 — antes do woocommerce_thankyou_<gw> (10) do Vindi e
 * antes dos CTAs cdm_thankyou_actions (5).
 */
add_action('woocommerce_thankyou', function ($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) return;
    $method = $order->get_payment_method();
    if (!in_array($method, ['vindi-pagamentos-pix', 'vindi-pagamentos-bolepix'], true)) {
        return;
    }
    ?>
    <div class="cdm-pix-hero">
      <div class="cdm-pix-hero-icon">
        <svg width="36" height="36" viewBox="0 0 32 32" fill="none">
          <path d="M16 2L29.5 11.5L29.5 20.5L16 30L2.5 20.5L2.5 11.5L16 2Z" fill="#32BCAD"/>
          <path d="M16 9.5l5.5 4v5L16 22.5l-5.5-4v-5L16 9.5z" fill="white"/>
        </svg>
      </div>
      <div class="cdm-pix-hero-text">
        <strong>Pague em segundos com Pix</strong>
        <span>Escaneie o QR Code abaixo ou copie o codigo. Confirmamos automaticamente quando o banco compensar.</span>
      </div>
    </div>
    <?php
}, 4);

/**
 * Adiciona CTAs amigaveis (acompanhar pedido / continuar comprando) logo
 * apos o cabecalho de agradecimento na pagina order-received.
 */
add_action('woocommerce_thankyou', function ($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) return;
    $orders_url = wc_get_account_endpoint_url('orders');
    $shop_url   = 'https://ciadasmochilas.com.br/loja/';
    ?>
    <div class="cdm-thankyou-actions">
      <a class="primary" href="<?php echo esc_url($shop_url); ?>">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
        Continuar comprando
      </a>
      <a href="<?php echo esc_url($orders_url); ?>">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/></svg>
        Acompanhar pedidos
      </a>
    </div>
    <?php
}, 5);

// Fase 3 — exemplos planejados (NAO ATIVOS ainda):
//
// add_action('woocommerce_before_cart', 'cia_astro_cart_trust_strip', 5);
// add_action('woocommerce_checkout_before_customer_details', 'cia_astro_checkout_trust_strip', 5);
// add_action('woocommerce_review_order_after_order_total', 'cia_astro_pix_discount_hint', 10);
