<?php
/**
 * Footer — espelha src/components/Footer.astro.
 */
if (!defined('ABSPATH')) { exit; }

$cia_logo = CIA_ASTRO_URI . '/assets/img/logo.webp';
$cia_custom_logo_id = get_theme_mod('custom_logo');
if ($cia_custom_logo_id) {
    $cia_logo_src = wp_get_attachment_image_src($cia_custom_logo_id, 'full');
    if ($cia_logo_src && !empty($cia_logo_src[0])) {
        $cia_logo = $cia_logo_src[0];
    }
}

// Top 5 categorias pelo count para link rapido "Comprar"
$cia_footer_topcats = get_terms([
    'taxonomy'   => 'product_cat',
    'hide_empty' => true,
    'parent'     => 0,
    'number'     => 5,
    'orderby'    => 'count',
    'order'      => 'DESC',
]);
?>
  </div><!-- /.cdm-container -->
</main>

<footer class="cdm-footer" role="contentinfo">
  <div class="cdm-footer-main">
    <div class="cdm-footer-brand">
      <img src="<?php echo esc_url($cia_logo); ?>" alt="Cia das Mochilas" width="200" height="40" />
      <p>Há mais de 15 anos levando mochilas, papelaria e material escolar das melhores marcas para o Brasil inteiro. Compromisso com qualidade, atendimento humano e entrega rápida.</p>
      <div class="cdm-footer-social">
        <a href="https://instagram.com/ciadasmochilas" target="_blank" rel="noopener" aria-label="Instagram">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="5"/><path d="M16 11.4a4 4 0 1 1-8 0 4 4 0 0 1 8 0z"/><path d="M17.5 6.5h.01"/></svg>
        </a>
        <a href="https://www.facebook.com/ciadasmochilasoficial" target="_blank" rel="noopener" aria-label="Facebook">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M22 12a10 10 0 1 0-11.6 9.9V15h-2.5v-3h2.5V9.5c0-2.5 1.5-3.9 3.8-3.9 1.1 0 2.2.2 2.2.2v2.4h-1.2c-1.2 0-1.6.8-1.6 1.6V12h2.7l-.4 3h-2.3v6.9A10 10 0 0 0 22 12z"/></svg>
        </a>
        <a href="https://wa.me/5511973584809" target="_blank" rel="noopener" aria-label="WhatsApp">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M20.5 3.5A11.7 11.7 0 0 0 12 .1 12 12 0 0 0 1.6 17.5L.1 23.9l6.5-1.7a12 12 0 0 0 5.4 1.4A12 12 0 0 0 24 11.6c0-3.1-1.2-6-3.5-8.1zM12 21.4a10 10 0 0 1-5-1.4l-.4-.2-3.8 1 1-3.7-.2-.4a10 10 0 1 1 8.4 4.7z"/></svg>
        </a>
      </div>
    </div>

    <nav class="cdm-footer-col" aria-label="Comprar">
      <h3>Comprar</h3>
      <ul>
        <li><a href="<?php echo esc_url(cia_astro_frontend_url('/loja/')); ?>">Todos os produtos</a></li>
        <li><a href="<?php echo esc_url(cia_astro_frontend_url('/promocoes/')); ?>">🔥 Promoções</a></li>
        <?php if (!is_wp_error($cia_footer_topcats)) : foreach ($cia_footer_topcats as $c): ?>
          <li><a href="<?php echo esc_url(cia_astro_frontend_url('/categoria/' . $c->slug . '/')); ?>"><?php echo esc_html($c->name); ?></a></li>
        <?php endforeach; endif; ?>
      </ul>
    </nav>

    <nav class="cdm-footer-col" aria-label="Ajuda">
      <h3>Ajuda</h3>
      <ul>
        <li><a href="<?php echo esc_url(cia_astro_frontend_url('/como-comprar/')); ?>">Como comprar</a></li>
        <li><a href="<?php echo esc_url(cia_astro_frontend_url('/prazo-e-entrega/')); ?>">Prazo e entrega</a></li>
        <li><a href="<?php echo esc_url(cia_astro_frontend_url('/trocas-e-devolucoes/')); ?>">Trocas e devoluções</a></li>
        <li><a href="<?php echo esc_url(cia_astro_backend_url('/rastrear-pedido/')); ?>">Rastrear pedido</a></li>
        <li><a href="<?php echo esc_url(cia_astro_frontend_url('/politica-de-privacidade/')); ?>">Política de Privacidade</a></li>
        <li><a href="<?php echo esc_url(cia_astro_frontend_url('/termos-de-uso/')); ?>">Termos de Uso</a></li>
        <li><a href="https://www.reclameaqui.com.br/empresa/cia-das-mochilas/" target="_blank" rel="noopener">Reclame Aqui</a></li>
      </ul>
      <h3 class="mt">Institucional</h3>
      <ul>
        <li><a href="<?php echo esc_url(cia_astro_frontend_url('/quem-somos/')); ?>">Quem somos</a></li>
        <li><a href="<?php echo esc_url(cia_astro_frontend_url('/blog/')); ?>">Blog</a></li>
        <li><a href="<?php echo esc_url(cia_astro_frontend_url('/contato/')); ?>">Contato</a></li>
      </ul>
    </nav>

    <div class="cdm-footer-col">
      <h3>Atendimento</h3>
      <ul>
        <li><a href="https://wa.me/5511973584809" target="_blank" rel="noopener">📱 (11) 9 7358-4809</a></li>
        <li><a href="tel:+551122491024">☎️ (11) 2249-1024</a></li>
        <li><a href="mailto:contato@ciadasmochilas.com.br">✉️ contato@ciadasmochilas.com.br</a></li>
        <li class="hours"><span class="hours">Seg–Sex 9h às 18h (BRT)</span></li>
      </ul>
      <h3 class="mt">Pagamento</h3>
      <div class="cdm-footer-payments">
        <span>Visa</span>
        <span>Master</span>
        <span>Elo</span>
        <span>Amex</span>
        <span class="pix">Pix</span>
        <span>Boleto</span>
      </div>
    </div>
  </div>

  <div class="cdm-footer-legal">
    <div class="cdm-footer-legal-inner">
      <p>© <?php echo date('Y'); ?> <strong>Cia das Mochilas Comercial Ltda</strong> — CNPJ 21.095.320/0001-42 · Av. Edu Chaves, 804 — Parque Edu Chaves, São Paulo/SP, 02229-000</p>
      <p>Todos os direitos reservados.</p>
    </div>
  </div>

  <div class="cdm-footer-sign">
    <div class="cdm-footer-sign-inner">
      Desenvolvido por
      <a href="https://tyber.io" target="_blank" rel="noopener">tyber.io</a>
    </div>
  </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
