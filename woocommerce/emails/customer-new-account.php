<?php
/**
 * Email Customer New Account — cia-astro override.
 * Substitui o default WC ("Your password is XXX") por um link magico
 * "Definir minha senha" — mais seguro (senha temp nunca trafega) e
 * melhor UX (cliente clica e cai direto no form de criar senha).
 *
 * Override de: woocommerce/templates/emails/customer-new-account.php
 */

defined('ABSPATH') || exit;

/*
 * Disponivel:
 * @hooked WC_Emails::email_header() Output the email header
 */
do_action('woocommerce_email_header', $email_heading, $email);

// Gera magic link (reset key) — valido 24h por padrao
$user      = get_user_by('id', $user->ID);
$reset_key = get_password_reset_key($user);
$set_pw_url = '';
if (!is_wp_error($reset_key)) {
    $set_pw_url = wc_get_endpoint_url(
        'lost-password',
        '',
        wc_get_page_permalink('myaccount')
    );
    $set_pw_url = add_query_arg([
        'show-reset-form' => 'true',
        'action'          => 'newaccount',
        'key'             => $reset_key,
        'login'           => rawurlencode($user->user_login),
    ], $set_pw_url);
}

$first_name = $user->first_name ?: $user->display_name;
?>

<p style="font-size:15px;margin:0 0 12px;">Ola, <strong><?php echo esc_html($first_name); ?></strong> &#128075;</p>
<p style="margin:0 0 16px;">Sua conta na <strong>Cia das Mochilas</strong> foi criada com sucesso! Voce ja pode acompanhar seus pedidos, salvar favoritos e receber alertas exclusivos.</p>

<div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:12px;padding:18px 20px;margin:16px 0 18px;">
  <p style="margin:0 0 6px;font-size:13px;color:#6b7280;text-transform:uppercase;letter-spacing:0.05em;font-weight:600;">Seu acesso</p>
  <p style="margin:0;font-size:15px;"><strong>E-mail:</strong> <?php echo esc_html($user_login); ?></p>
</div>

<?php if ($set_pw_url) : ?>
<p style="margin:18px 0 12px;">Para definir uma senha segura e acessar sua conta, clique no botao abaixo:</p>
<p style="text-align:center;margin:18px 0 22px;">
  <a href="<?php echo esc_url($set_pw_url); ?>"
     style="display:inline-block;background:#0f4a7a;color:#ffffff;padding:14px 32px;border-radius:9999px;text-decoration:none;font-weight:700;font-size:15px;box-shadow:0 4px 12px rgba(15,74,122,0.25);">
    Definir minha senha
  </a>
</p>
<p style="font-size:12px;color:#6b7280;text-align:center;margin:0 0 18px;">
  Este link e valido por 24 horas. Se expirar, voce pode <a href="<?php echo esc_url(wc_get_endpoint_url('lost-password', '', wc_get_page_permalink('myaccount'))); ?>" style="color:#0f4a7a;">redefinir aqui</a>.
</p>
<?php else : ?>
<p style="margin:18px 0 12px;">Acesse sua conta com o e-mail acima e a senha que voce escolheu no checkout.</p>
<?php endif; ?>

<h2 style="margin-top:24px;">O que voce pode fazer agora</h2>
<table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin:8px 0 0;">
  <tr>
    <td valign="top" style="padding:6px 0;font-size:14px;">
      &#128230; &nbsp;<strong>Acompanhar pedidos</strong> em tempo real, com rastreio e status.
    </td>
  </tr>
  <tr>
    <td valign="top" style="padding:6px 0;font-size:14px;">
      &#10084;&#65039; &nbsp;<strong>Salvar favoritos</strong> e receber alerta se o preco cair ou voltar ao estoque.
    </td>
  </tr>
  <tr>
    <td valign="top" style="padding:6px 0;font-size:14px;">
      &#128205; &nbsp;<strong>Salvar enderecos</strong> pra acelerar suas proximas compras.
    </td>
  </tr>
</table>

<?php
/*
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action('woocommerce_email_footer', $email);
