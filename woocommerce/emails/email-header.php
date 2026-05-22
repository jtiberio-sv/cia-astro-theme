<?php
/**
 * Email Header — cia-astro override.
 * Logo PNG centralizado em gradient cream/brand + heading branco em barra brand.
 *
 * Substitui woocommerce/templates/emails/email-header.php
 */

if (!defined('ABSPATH')) exit;

// Logo HD do tema (PNG 2x retina servido como 280px display) — fallback
// para o legado da loja caso o arquivo do tema ainda nao esteja deployado.
// Logo original da loja (sera substituido por versao HD/vetorizada quando
// disponivel). Forca HTTPS — emails enviados via cron/cli podem gerar URL
// http quando $_SERVER['HTTPS'] nao esta setado, quebrando preview.
$logo_url = 'https://loja.ciadasmochilas.com.br/wp-content/uploads/2025/08/logomarca_principal_transparent_clean.png';
$logo_url = set_url_scheme($logo_url, 'https');
$tagline    = 'Material escolar com qualidade desde 2010';
$brand      = '#0f4a7a';
$brand_2    = '#1d6fb3';
$accent     = '#fff8e1';
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title><?php echo get_bloginfo('name', 'display'); ?></title>
</head>
<body <?php echo is_rtl() ? 'rightmargin' : 'leftmargin'; ?>="0" topmargin="0" marginwidth="0" marginheight="0" offset="0" style="margin:0;padding:0;background-color:#eef2f7;">
<div id="wrapper" dir="<?php echo is_rtl() ? 'rtl' : 'ltr'; ?>" style="background-color:#eef2f7;margin:0;padding:32px 12px;-webkit-text-size-adjust:none;width:100%;">
  <table border="0" cellpadding="0" cellspacing="0" height="100%" width="100%">
    <tr>
      <td align="center" valign="top">

        <div id="template_header_image" style="text-align:center;margin:0 0 16px;">
          <a href="<?php echo esc_url(home_url('/')); ?>" style="display:inline-block;text-decoration:none;">
            <img src="<?php echo esc_url($logo_url); ?>"
                 alt="<?php echo esc_attr(get_bloginfo('name', 'display')); ?>"
                 width="280"
                 style="display:inline-block;border:0;outline:none;text-decoration:none;-ms-interpolation-mode:bicubic;max-width:280px;height:auto;" />
          </a>
        </div>

        <table border="0" cellpadding="0" cellspacing="0" width="600" id="template_container" style="background-color:#ffffff;border:1px solid #e5e7eb;border-radius:16px;box-shadow:0 6px 24px rgba(15,23,42,0.06);max-width:600px;width:100%;overflow:hidden;">
          <tr>
            <td align="center" valign="top">

              <!-- Heading bar com gradient brand -->
              <table border="0" cellpadding="0" cellspacing="0" width="100%" id="template_header" style="background:<?php echo $brand; ?>;background-image:linear-gradient(135deg, <?php echo $brand; ?> 0%, <?php echo $brand_2; ?> 100%);background-color:<?php echo $brand; ?>;color:#ffffff;border-radius:16px 16px 0 0;">
                <tr>
                  <td id="header_wrapper" style="padding:28px 36px;text-align:<?php echo is_rtl() ? 'right' : 'left'; ?>;">
                    <h1 style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:22px;font-weight:700;line-height:1.25;margin:0;color:#ffffff;text-shadow:none;display:block;"><?php echo $email_heading; ?></h1>
                    <p style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:13px;font-weight:500;margin:6px 0 0;color:rgba(255,255,255,0.85);"><?php echo esc_html($tagline); ?></p>
                  </td>
                </tr>
              </table>

              <!-- Body -->
              <table border="0" cellpadding="0" cellspacing="0" width="100%">
                <tr>
                  <td valign="top" id="body_content" style="background-color:#ffffff;">
                    <table border="0" cellpadding="20" cellspacing="0" width="100%">
                      <tr>
                        <td valign="top" style="padding:36px 36px 24px;">
                          <div id="body_content_inner" style="color:#1f2937;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:15px;line-height:1.65;text-align:<?php echo is_rtl() ? 'right' : 'left'; ?>;">
