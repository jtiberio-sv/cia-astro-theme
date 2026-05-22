<?php
/**
 * Email Footer — cia-astro override.
 * Barra "Por que comprar" + redes sociais + atendimento + credito.
 *
 * Substitui woocommerce/templates/emails/email-footer.php
 */

if (!defined('ABSPATH')) exit;

$brand = '#0f4a7a';
$site  = 'https://ciadasmochilas.com.br';
$logo_white_theme = CIA_ASTRO_DIR . '/assets/img/logo-email-white.png';
$logo_white_url   = file_exists($logo_white_theme)
    ? CIA_ASTRO_URI . '/assets/img/logo-email-white.png'
    : '';
?>
                          </div>
                        </td>
                      </tr>
                    </table>
                  </td>
                </tr>
              </table>

              <!-- Trust bar (por que comprar) -->
              <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background:#f8fafc;border-top:1px solid #e5e7eb;">
                <tr>
                  <td style="padding:20px 28px;">
                    <table border="0" cellpadding="0" cellspacing="0" width="100%">
                      <tr>
                        <td width="33%" align="center" valign="top" style="padding:0 6px;">
                          <div style="font-size:20px;line-height:1;margin-bottom:4px;">&#128666;</div>
                          <div style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:11px;font-weight:700;color:<?php echo $brand; ?>;text-transform:uppercase;letter-spacing:0.05em;">Frete gratis</div>
                          <div style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:11px;color:#6b7280;line-height:1.4;margin-top:2px;">acima de R$ 199</div>
                        </td>
                        <td width="33%" align="center" valign="top" style="padding:0 6px;">
                          <div style="font-size:20px;line-height:1;margin-bottom:4px;">&#128274;</div>
                          <div style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:11px;font-weight:700;color:<?php echo $brand; ?>;text-transform:uppercase;letter-spacing:0.05em;">Compra segura</div>
                          <div style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:11px;color:#6b7280;line-height:1.4;margin-top:2px;">SSL + Pix</div>
                        </td>
                        <td width="33%" align="center" valign="top" style="padding:0 6px;">
                          <div style="font-size:20px;line-height:1;margin-bottom:4px;">&#128190;</div>
                          <div style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:11px;font-weight:700;color:<?php echo $brand; ?>;text-transform:uppercase;letter-spacing:0.05em;">Troca facil</div>
                          <div style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:11px;color:#6b7280;line-height:1.4;margin-top:2px;">em 30 dias</div>
                        </td>
                      </tr>
                    </table>
                  </td>
                </tr>
              </table>

              <!-- Footer principal -->
              <table border="0" cellpadding="10" cellspacing="0" width="100%" id="template_footer" style="background:<?php echo $brand; ?>;border-radius:0 0 16px 16px;">
                <tr>
                  <td valign="top" style="padding:24px 28px;text-align:center;">
                    <?php if ($logo_white_url) : ?>
                    <img src="<?php echo esc_url($logo_white_url); ?>"
                         alt="Cia das Mochilas"
                         width="160"
                         style="display:inline-block;margin:0 0 16px;max-width:160px;height:auto;border:0;outline:none;-ms-interpolation-mode:bicubic;" />
                    <?php endif; ?>
                    <p style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:14px;font-weight:700;color:#ffffff;margin:0 0 8px;">Fale com a gente</p>
                    <p style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:13px;color:rgba(255,255,255,0.85);margin:0 0 16px;line-height:1.6;">
                      <a href="mailto:contato@ciadasmochilas.com.br" style="color:#ffffff;text-decoration:none;font-weight:600;">contato@ciadasmochilas.com.br</a><br>
                      <a href="<?php echo esc_url($site . '/contato/'); ?>" style="color:rgba(255,255,255,0.85);text-decoration:underline;text-underline-offset:2px;">Central de atendimento</a>
                    </p>
                    <!-- CTAs -->
                    <p style="margin:8px 0 18px;">
                      <a href="<?php echo esc_url($site); ?>" style="display:inline-block;background:#ffc73c;color:#1f2937;padding:10px 22px;border-radius:9999px;text-decoration:none;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-weight:700;font-size:13px;">Visitar a loja</a>
                    </p>
                    <p style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:12px;color:rgba(255,255,255,0.7);margin:14px 0 0;line-height:1.6;">
                      <strong style="color:#ffffff;">Cia das Mochilas</strong> — Material escolar com qualidade desde 2010<br>
                      <a href="<?php echo esc_url($site); ?>" style="color:rgba(255,255,255,0.85);text-decoration:none;">ciadasmochilas.com.br</a>
                    </p>
                  </td>
                </tr>
              </table>

            </td>
          </tr>
        </table>

        <!-- Disclaimer fora do card -->
        <table border="0" cellpadding="10" cellspacing="0" width="600" style="max-width:600px;width:100%;">
          <tr>
            <td valign="top" style="padding:16px 24px;text-align:center;">
              <p style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:11px;color:#9ca3af;line-height:1.6;margin:0;">
                Voce recebeu este e-mail porque tem uma conta ou fez uma compra na Cia das Mochilas.<br>
                Em caso de duvidas, responda este e-mail ou acesse nosso <a href="<?php echo esc_url($site . '/contato/'); ?>" style="color:<?php echo $brand; ?>;text-decoration:none;">canal de atendimento</a>.
              </p>
            </td>
          </tr>
        </table>

      </td>
    </tr>
  </table>
</div>
</body>
</html>
