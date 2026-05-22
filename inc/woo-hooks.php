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
add_action('init', function () {
    if (!function_exists('woocommerce_account_dashboard')) {
        return;
    }
    remove_action('woocommerce_account_dashboard', 'woocommerce_account_dashboard');
    add_action('woocommerce_account_dashboard', 'cia_astro_account_dashboard');
}, 30);

function cia_astro_account_dashboard() {
    $current_user = wp_get_current_user();
    $first_name   = $current_user->first_name ?: $current_user->display_name;

    $orders_url    = wc_get_account_endpoint_url('orders');
    $addresses_url = wc_get_account_endpoint_url('edit-address');
    $account_url   = wc_get_account_endpoint_url('edit-account');
    $logout_url    = wc_logout_url(wc_get_page_permalink('myaccount'));

    ?>
    <div class="cdm-account-welcome">
      <h2><span class="cdm-wave" aria-hidden="true">👋</span> Olá, <?php echo esc_html($first_name); ?>!</h2>
      <p>Acompanhe seus pedidos, atualize seu endereço de entrega ou seus dados de cadastro.</p>
    </div>

    <div class="cdm-account-shortcuts">
      <a href="<?php echo esc_url($orders_url); ?>">
        <span class="ico" aria-hidden="true">📦</span>
        <span class="label">Meus pedidos</span>
        <span class="hint">Acompanhe status, rastreio e detalhes</span>
      </a>
      <a href="<?php echo esc_url($addresses_url); ?>">
        <span class="ico" aria-hidden="true">📍</span>
        <span class="label">Endereços</span>
        <span class="hint">Cobrança e entrega</span>
      </a>
      <a href="<?php echo esc_url($account_url); ?>">
        <span class="ico" aria-hidden="true">⚙️</span>
        <span class="label">Conta</span>
        <span class="hint">Nome, e-mail e senha</span>
      </a>
      <a href="<?php echo esc_url($logout_url); ?>">
        <span class="ico" aria-hidden="true">🚪</span>
        <span class="label">Sair</span>
        <span class="hint">Encerrar sessão</span>
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
