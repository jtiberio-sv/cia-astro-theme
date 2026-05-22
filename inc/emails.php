<?php
/**
 * Identidade visual dos e-mails transacionais WooCommerce.
 *
 * Estrutura:
 *  - Cores via filter pre_option_* (sobrescreve admin sem mexer no DB)
 *  - Templates customizados:
 *      woocommerce/emails/email-header.php (logo + heading + tagline)
 *      woocommerce/emails/email-footer.php (trust bar + atendimento + brand)
 *  - Subjects/headings em PT-BR amigaveis
 */

if (!defined('ABSPATH')) {
    exit;
}

/* ============================================================
 * 1) Opcoes WC (cores + identidade)
 * ============================================================ */

add_filter('pre_option_woocommerce_email_base_color', function () {
    return '#0f4a7a'; // brand
});
add_filter('pre_option_woocommerce_email_background_color', function () {
    return '#eef2f7'; // fundo externo levemente azulado
});
add_filter('pre_option_woocommerce_email_body_background_color', function () {
    return '#ffffff'; // card branco
});
add_filter('pre_option_woocommerce_email_text_color', function () {
    return '#1f2937';
});
// Footer text fica vazio porque o template-footer.php ja renderiza tudo
add_filter('pre_option_woocommerce_email_footer_text', function () {
    return '';
});

/* ============================================================
 * 2) Subjects e headings PT-BR amigaveis (com acentuacao correta)
 * ============================================================ */

/* ---- E-mails do cliente ---- */
add_filter('woocommerce_email_subject_customer_processing_order', function ($subject, $order) {
    if (!$order) return $subject;
    return sprintf('Recebemos seu pedido #%s na Cia das Mochilas!', $order->get_order_number());
}, 10, 2);

add_filter('woocommerce_email_subject_customer_completed_order', function ($subject, $order) {
    if (!$order) return $subject;
    return sprintf('Seu pedido #%s foi enviado!', $order->get_order_number());
}, 10, 2);

add_filter('woocommerce_email_subject_customer_on_hold_order', function ($subject, $order) {
    if (!$order) return $subject;
    return sprintf('Aguardando confirmação do pagamento — pedido #%s', $order->get_order_number());
}, 10, 2);

add_filter('woocommerce_email_subject_customer_refunded_order', function ($subject, $order) {
    if (!$order) return $subject;
    return sprintf('Reembolso processado — pedido #%s', $order->get_order_number());
}, 10, 2);

add_filter('woocommerce_email_subject_customer_note', function ($subject, $order) {
    if (!$order) return $subject;
    return sprintf('Atualização do pedido #%s', $order->get_order_number());
}, 10, 2);

add_filter('woocommerce_email_subject_customer_cancelled_order', function ($subject, $order) {
    if (!$order) return $subject;
    return sprintf('Seu pedido #%s foi cancelado', $order->get_order_number());
}, 10, 2);

add_filter('woocommerce_email_subject_customer_failed_order', function ($subject, $order) {
    if (!$order) return $subject;
    return sprintf('Seu pedido #%s na Cia das Mochilas não foi concluído', $order->get_order_number());
}, 10, 2);

add_filter('woocommerce_email_subject_customer_invoice', function ($subject, $order) {
    if (!$order) return $subject;
    return sprintf('Fatura do pedido #%s — Cia das Mochilas', $order->get_order_number());
}, 10, 2);

add_filter('woocommerce_email_subject_customer_new_account', function ($subject) {
    return 'Bem-vindo(a) à Cia das Mochilas!';
}, 10);

add_filter('woocommerce_email_subject_customer_reset_password', function ($subject) {
    return 'Redefinição de senha — Cia das Mochilas';
}, 10);

/* ---- E-mails do admin/loja (interno) ---- */
add_filter('woocommerce_email_subject_new_order', function ($subject, $order) {
    if (!$order) return $subject;
    return sprintf('[Cia das Mochilas] Novo pedido recebido: #%s', $order->get_order_number());
}, 10, 2);

add_filter('woocommerce_email_subject_cancelled_order', function ($subject, $order) {
    if (!$order) return $subject;
    return sprintf('[Cia das Mochilas] Pedido #%s cancelado', $order->get_order_number());
}, 10, 2);

add_filter('woocommerce_email_subject_failed_order', function ($subject, $order) {
    if (!$order) return $subject;
    return sprintf('[Cia das Mochilas] Falha no pedido #%s', $order->get_order_number());
}, 10, 2);

/* ---- Headings (titulo da barra azul) ---- */
add_filter('woocommerce_email_heading_customer_processing_order', function () {
    return 'Obrigado pelo seu pedido!';
});
add_filter('woocommerce_email_heading_customer_completed_order', function () {
    return 'Seu pedido está a caminho!';
});
add_filter('woocommerce_email_heading_customer_on_hold_order', function () {
    return 'Aguardando confirmação do pagamento';
});
add_filter('woocommerce_email_heading_customer_cancelled_order', function () {
    return 'Seu pedido foi cancelado';
});
add_filter('woocommerce_email_heading_customer_failed_order', function () {
    return 'Não foi possível concluir seu pedido';
});
add_filter('woocommerce_email_heading_customer_refunded_order', function () {
    return 'Reembolso processado';
});
add_filter('woocommerce_email_heading_customer_invoice', function () {
    return 'Fatura do seu pedido';
});
add_filter('woocommerce_email_heading_customer_note', function () {
    return 'Atualização sobre o seu pedido';
});
add_filter('woocommerce_email_heading_customer_new_account', function () {
    return 'Sua conta foi criada!';
});
add_filter('woocommerce_email_heading_customer_reset_password', function () {
    return 'Redefinir sua senha';
});

/* ============================================================
 * 3) CSS extra injetado depois do default WC
 * ============================================================ */

add_filter('woocommerce_email_styles', function ($css, $email = null) {
    $extra = '
    /* === Cia das Mochilas brand — body styling === */
    body {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif !important;
        background-color: #eef2f7 !important;
    }
    #body_content_inner h2 {
        color: #0f4a7a !important;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif !important;
        font-size: 17px !important;
        font-weight: 700 !important;
        margin: 0 0 14px !important;
        padding-bottom: 8px !important;
        border-bottom: 2px solid #fff8e1 !important;
    }
    #body_content_inner h3 {
        color: #1f2937 !important;
        font-size: 14px !important;
        font-weight: 700 !important;
        margin: 18px 0 10px !important;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }
    #body_content_inner p { margin: 0 0 12px !important; }
    #body_content_inner a {
        color: #0f4a7a !important;
        font-weight: 600;
    }
    .address {
        background: #f8fafc !important;
        border: 1px solid #e5e7eb !important;
        border-radius: 10px !important;
        padding: 14px 16px !important;
        line-height: 1.5;
    }
    /* Tabelas de itens */
    table.td {
        border: 1px solid #e5e7eb !important;
        border-radius: 10px !important;
        border-collapse: separate !important;
        border-spacing: 0 !important;
        overflow: hidden;
    }
    table.td th, table.td td {
        border-color: #f3f4f6 !important;
        padding: 12px 14px !important;
        font-size: 14px !important;
    }
    table.td thead th {
        background: #f8fafc !important;
        font-size: 11px !important;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #6b7280 !important;
        font-weight: 700 !important;
    }
    table.td tfoot th, table.td tfoot td {
        background: #fafbfc !important;
        font-weight: 600 !important;
    }
    table.td tfoot tr:last-child th,
    table.td tfoot tr:last-child td {
        background: #fff8e1 !important;
        font-size: 16px !important;
        color: #0f4a7a !important;
        font-weight: 700 !important;
    }
    /* CTAs button-like */
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
