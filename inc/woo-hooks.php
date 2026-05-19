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

// Fase 3 — exemplos planejados (NAO ATIVOS ainda):
//
// add_action('woocommerce_before_cart', 'cia_astro_cart_trust_strip', 5);
// add_action('woocommerce_checkout_before_customer_details', 'cia_astro_checkout_trust_strip', 5);
// add_action('woocommerce_review_order_after_order_total', 'cia_astro_pix_discount_hint', 10);
