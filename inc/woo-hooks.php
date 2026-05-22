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

// Fase 3 — exemplos planejados (NAO ATIVOS ainda):
//
// add_action('woocommerce_before_cart', 'cia_astro_cart_trust_strip', 5);
// add_action('woocommerce_checkout_before_customer_details', 'cia_astro_checkout_trust_strip', 5);
// add_action('woocommerce_review_order_after_order_total', 'cia_astro_pix_discount_hint', 10);
