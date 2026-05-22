<?php
/**
 * Identidade visual dos e-mails transacionais WooCommerce.
 *
 * - Forca cores brand via filter pre_option_* (sobrescreve admin sem mexer no DB).
 * - Sobrescreve woocommerce/emails/email-header.php + email-footer.php
 *   + email-styles.php via cascata de templates do tema.
 * - Customiza footer text + remove tagline padrao Storefront.
 */

if (!defined('ABSPATH')) {
    exit;
}

/* ============================================================
 * 1) Opcoes WC: cores + footer text (sem precisar mexer no admin)
 * ============================================================ */

add_filter('pre_option_woocommerce_email_base_color', function () {
    return '#0f4a7a'; // brand
});
add_filter('pre_option_woocommerce_email_background_color', function () {
    return '#f8fafc'; // gray-50 (fundo externo)
});
add_filter('pre_option_woocommerce_email_body_background_color', function () {
    return '#ffffff'; // card branco
});
add_filter('pre_option_woocommerce_email_text_color', function () {
    return '#1f2937'; // ink
});
add_filter('pre_option_woocommerce_email_footer_text', function () {
    return 'Cia das Mochilas — Material escolar com qualidade desde 2010<br>'
         . '<a href="https://ciadasmochilas.com.br/" style="color:#0f4a7a;text-decoration:none;">ciadasmochilas.com.br</a>'
         . ' &nbsp;·&nbsp; '
         . '<a href="https://ciadasmochilas.com.br/contato/" style="color:#0f4a7a;text-decoration:none;">Fale conosco</a>';
});

/* ============================================================
 * 2) Header image (usa logo a partir do tema se admin nao configurou)
 * ============================================================ */

add_filter('woocommerce_email_header_image', function ($url) {
    if ($url) return $url;
    // Logo do tema (mesmo asset do header)
    return CIA_ASTRO_URI . '/assets/img/logo.webp';
});

/* ============================================================
 * 3) Subject e heading PT-BR mais amigaveis
 * ============================================================ */

add_filter('woocommerce_email_subject_customer_processing_order', function ($subject, $order) {
    return sprintf('Recebemos seu pedido #%s na Cia das Mochilas!', $order->get_order_number());
}, 10, 2);

add_filter('woocommerce_email_subject_customer_completed_order', function ($subject, $order) {
    return sprintf('Seu pedido #%s foi enviado!', $order->get_order_number());
}, 10, 2);

add_filter('woocommerce_email_subject_customer_on_hold_order', function ($subject, $order) {
    return sprintf('Aguardando confirmacao do pagamento — pedido #%s', $order->get_order_number());
}, 10, 2);

add_filter('woocommerce_email_subject_customer_refunded_order', function ($subject, $order) {
    return sprintf('Reembolso processado — pedido #%s', $order->get_order_number());
}, 10, 2);

add_filter('woocommerce_email_subject_customer_note', function ($subject, $order) {
    return sprintf('Atualizacao do pedido #%s', $order->get_order_number());
}, 10, 2);

add_filter('woocommerce_email_subject_customer_new_account', function ($subject) {
    return 'Bem-vindo(a) a Cia das Mochilas!';
}, 10);

add_filter('woocommerce_email_subject_customer_reset_password', function ($subject) {
    return 'Redefinicao de senha — Cia das Mochilas';
}, 10);

add_filter('woocommerce_email_heading_customer_processing_order', function () {
    return 'Obrigado pelo seu pedido!';
});
add_filter('woocommerce_email_heading_customer_completed_order', function () {
    return 'Seu pedido esta a caminho!';
});
add_filter('woocommerce_email_heading_customer_on_hold_order', function () {
    return 'Aguardando confirmacao do pagamento';
});
add_filter('woocommerce_email_heading_customer_new_account', function () {
    return 'Sua conta foi criada!';
});

/* ============================================================
 * 4) Customizacoes CSS adicionais aplicadas no head do email
 * ============================================================ */

add_filter('woocommerce_email_styles', function ($css, $email = null) {
    $extra = '
    /* === Cia das Mochilas brand override === */
    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif !important; }
    #wrapper { background-color: #f8fafc !important; padding: 40px 0 !important; }
    #template_container {
        border-radius: 14px !important;
        box-shadow: 0 4px 16px rgba(15, 23, 42, 0.06) !important;
        border: 1px solid #f3f4f6 !important;
        overflow: hidden !important;
    }
    #template_header {
        background: linear-gradient(135deg, #e8f0fa 0%, #ffffff 100%) !important;
        border-bottom: 1px solid #f3f4f6 !important;
        border-radius: 14px 14px 0 0 !important;
    }
    #template_header h1, #template_header h1 a {
        color: #0f4a7a !important;
        font-weight: 700 !important;
        font-size: 22px !important;
        line-height: 1.2 !important;
        text-shadow: none !important;
        padding: 28px 32px !important;
    }
    #template_header_image img {
        max-height: 56px !important;
        margin: 24px 0 8px !important;
    }
    #body_content {
        background-color: #ffffff !important;
    }
    #body_content_inner {
        color: #1f2937 !important;
        font-size: 15px !important;
        line-height: 1.6 !important;
        padding: 28px 32px !important;
    }
    #body_content_inner h2 {
        color: #0f4a7a !important;
        font-size: 18px !important;
        font-weight: 700 !important;
        margin: 0 0 14px !important;
        padding: 0 !important;
    }
    #body_content_inner h3 {
        color: #1f2937 !important;
        font-size: 15px !important;
        font-weight: 700 !important;
        margin: 18px 0 10px !important;
    }
    #body_content_inner p { margin: 0 0 12px !important; }
    #body_content_inner a { color: #0f4a7a !important; font-weight: 600; }
    .address {
        background: #f8fafc !important;
        border: 1px solid #f3f4f6 !important;
        border-radius: 10px !important;
        padding: 14px !important;
    }
    table.td {
        border: 1px solid #f3f4f6 !important;
        border-radius: 10px !important;
        border-collapse: separate !important;
        border-spacing: 0 !important;
    }
    table.td th, table.td td {
        border-color: #f3f4f6 !important;
        padding: 12px 14px !important;
        font-size: 14px !important;
    }
    table.td thead th {
        background: #f8fafc !important;
        font-size: 12px !important;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #6b7280 !important;
    }
    table.td tfoot th, table.td tfoot td {
        background: #fafbfc !important;
        font-weight: 600 !important;
    }
    table.td tfoot tr:last-child th, table.td tfoot tr:last-child td {
        font-size: 16px !important;
        color: #0f4a7a !important;
    }
    #template_footer td {
        background: #f8fafc !important;
        border-top: 1px solid #f3f4f6 !important;
        border-radius: 0 0 14px 14px !important;
    }
    #credit {
        color: #6b7280 !important;
        font-size: 12px !important;
        line-height: 1.6 !important;
        padding: 18px 24px !important;
    }
    #credit a { color: #0f4a7a !important; text-decoration: none !important; font-weight: 600; }
    /* Botao "View order" / CTA — pill amarela cta */
    .email-introduction + p > a,
    a.button {
        display: inline-block !important;
        background: #ffc73c !important;
        color: #1f2937 !important;
        padding: 12px 24px !important;
        border-radius: 9999px !important;
        text-decoration: none !important;
        font-weight: 700 !important;
        font-size: 14px !important;
        box-shadow: 0 4px 12px rgba(255, 199, 60, 0.4) !important;
    }
    ';
    return $css . $extra;
}, 10, 2);
