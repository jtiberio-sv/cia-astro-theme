<?php
/**
 * Express Checkout: botao "Comprar com 1 clique" no /carrinho.
 *
 * Aparece somente se:
 *   - user logado
 *   - tem endereco de cobranca salvo (rua + numero + cidade + UF + CEP)
 *   - carrinho tem itens validos
 *
 * Acao: aplica endereco padrao no checkout e seta Pix como metodo
 * padrao via URL params, redireciona pra finalizar-compra.
 *
 * Nao processa pagamento por traz — leva pro form de checkout com tudo
 * pre-preenchido (Pix ja selecionado). Cliente so confirma e finaliza.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('woocommerce_proceed_to_checkout', 'cdm_express_checkout_button', 5);

function cdm_express_checkout_button() {
    if (!is_user_logged_in()) return;
    if (!function_exists('WC') || !WC()->cart || WC()->cart->is_empty()) return;

    $user_id = get_current_user_id();
    $required = ['billing_address_1', 'billing_number', 'billing_city', 'billing_state', 'billing_postcode'];
    foreach ($required as $f) {
        if (!get_user_meta($user_id, $f, true)) return;
    }

    $first_name = get_user_meta($user_id, 'billing_first_name', true) ?: wp_get_current_user()->display_name;
    $checkout_url = add_query_arg([
        'cdm_express' => '1',
        'payment'     => 'pix',
    ], wc_get_checkout_url());
    ?>
    <a href="<?php echo esc_url($checkout_url); ?>" class="cdm-express-checkout-btn">
      <span class="cdm-ec-icon">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
        </svg>
      </span>
      <span class="cdm-ec-text">
        <strong>Comprar com 1 clique</strong>
        <small>Usar endereco salvo de <?php echo esc_html($first_name); ?> e pagar com Pix</small>
      </span>
      <span class="cdm-ec-arrow">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
      </span>
    </a>
    <?php
}

/**
 * Quando express=1, seta Pix como metodo selecionado e marca flag pra
 * eventual auto-submit JS (opcional, futuro).
 */
add_action('woocommerce_before_checkout_form', function () {
    if (empty($_GET['cdm_express']) || $_GET['cdm_express'] !== '1') return;
    if (!empty($_GET['payment']) && $_GET['payment'] === 'pix') {
        WC()->session->set('chosen_payment_method', 'vindi-pagamentos-pix');
    }
    ?>
    <div class="cdm-express-active">
      <span aria-hidden="true">⚡</span>
      <span>Checkout expresso: seu endereco padrao foi aplicado e o Pix esta selecionado. So conferir e finalizar.</span>
    </div>
    <?php
}, 6);
