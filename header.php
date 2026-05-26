<?php
/**
 * Header — espelha src/components/TopBar.astro + Header.astro.
 *
 * Markup intencionalmente identico ao Astro pra compartilhar CSS.
 */
if (!defined('ABSPATH')) { exit; }

require_once CIA_ASTRO_DIR . '/inc/header-data.php';
$cia_topcats = cia_astro_get_top_categories(6);
// Prioridade: logo-hd.png (alta-res gerado via IA) > logo.webp (legado) > custom_logo
$cia_logo_hd_path = CIA_ASTRO_DIR . '/assets/img/logo-hd.png';
$cia_logo = file_exists($cia_logo_hd_path)
    ? CIA_ASTRO_URI . '/assets/img/logo-hd.png'
    : CIA_ASTRO_URI . '/assets/img/logo.webp';
// Logo aponta pra vitrine (Astro), nao pro backend WP
$cia_home    = cia_astro_frontend_url('/');
// Fallback de logo: pega o custom_logo configurado no Customizer se existir
$cia_custom_logo_id = get_theme_mod('custom_logo');
if ($cia_custom_logo_id) {
    $cia_logo_src = wp_get_attachment_image_src($cia_custom_logo_id, 'full');
    if ($cia_logo_src && !empty($cia_logo_src[0])) {
        $cia_logo = $cia_logo_src[0];
    }
}
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<meta name="theme-color" content="#1E7BB8" />
<link rel="preload" as="image" href="<?php echo esc_url($cia_logo); ?>" fetchpriority="high" />
<style>
  /* Inline critical: esconde nav horizontal em mobile + tablet (mega-menus nao funcionam touch).
     Inline porque header.css esta cached com max-age 1 ano e nao atualiza imediato. */
  @media (max-width: 1023px) {
    .cdm-nav { display: none !important; }
  }
</style>
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>

<!-- ============ TopBar ============ -->
<div class="cdm-topbar" role="region" aria-label="Barra de avisos">
  <span class="cdm-topbar-confetti" aria-hidden="true" style="left:10%; top:50%; transform:translateY(-50%); background:var(--color-confetti-yellow);"></span>
  <span class="cdm-topbar-confetti s2" aria-hidden="true" style="left:20%; top:2px; background:var(--color-confetti-pink);"></span>
  <span class="cdm-topbar-confetti s2" aria-hidden="true" style="left:35%; bottom:2px; background:var(--color-confetti-mint);"></span>
  <span class="cdm-topbar-confetti" aria-hidden="true" style="right:15%; top:2px; background:var(--color-confetti-coral);"></span>
  <span class="cdm-topbar-confetti s2" aria-hidden="true" style="right:35%; bottom:2px; background:var(--color-confetti-cyan);"></span>

  <div class="cdm-topbar-inner">
    <div class="cdm-topbar-msgs" aria-live="polite">
      <ul class="cdm-marquee">
        <li class="cdm-marquee-item"><span class="ic">🚚</span><span>Frete grátis acima de R$ 199 para todo Brasil</span></li>
        <li class="cdm-marquee-item"><span class="ic">💳</span><span>10x sem juros · 10% OFF no Pix</span></li>
        <li class="cdm-marquee-item"><span class="ic">🔄</span><span>Troca grátis em 30 dias</span></li>
        <li class="cdm-marquee-item"><span class="ic">🎁</span><span>Cadastre-se e ganhe R$ 30 OFF</span></li>
      </ul>
    </div>
    <div class="cdm-topbar-links">
      <a href="<?php echo esc_url(cia_astro_backend_url('/rastrear-pedido/')); ?>">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
        Rastrear pedido
      </a>
      <a href="https://wa.me/5511973584809" target="_blank" rel="noopener">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M20.5 3.5A11.7 11.7 0 0 0 12 .1 12 12 0 0 0 1.6 17.5L.1 23.9l6.5-1.7a12 12 0 0 0 5.4 1.4A12 12 0 0 0 24 11.6c0-3.1-1.2-6-3.5-8.1z"/></svg>
        (11) 9 7358-4809
      </a>
    </div>
  </div>
</div>

<!-- ============ Header ============ -->
<header class="cdm-header" role="banner">
  <!-- Bloco superior: logo + busca + acoes -->
  <div class="cdm-header-top">
    <div class="cdm-header-top-inner">
      <button type="button" id="cdm-burger" class="cdm-burger" aria-label="Abrir menu" aria-controls="cdm-mobile-drawer" aria-expanded="false">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
      </button>

      <a href="<?php echo esc_url($cia_home); ?>" class="cdm-logo" aria-label="Cia das Mochilas — voltar para a home">
        <img src="<?php echo esc_url($cia_logo); ?>" alt="Cia das Mochilas" width="200" height="40" fetchpriority="high" />
      </a>

      <form role="search" class="cdm-search-form" action="<?php echo esc_url(cia_astro_frontend_url('/busca/')); ?>" method="get">
        <label for="cdm-header-search" class="cdm-sr-only">Buscar produtos</label>
        <input id="cdm-header-search" name="q" type="search" class="cdm-search-input"
               placeholder="O que está procurando? mochila, lápis, caderno, marca…" autocomplete="off" />
        <button type="submit" aria-label="Buscar">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
        </button>
      </form>

      <div class="cdm-actions">
        <button type="button" id="cdm-search-mobile" class="cdm-search-mobile" aria-label="Buscar">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
        </button>
        <a href="<?php echo esc_url(cia_astro_backend_url('/minha-conta/')); ?>" class="cdm-action-link" aria-label="<?php echo is_user_logged_in() ? 'Minha conta' : 'Entrar / Cadastrar'; ?>">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-7 8-7s8 3 8 7"/></svg>
          <span><?php
            if (is_user_logged_in()) {
                $u = wp_get_current_user();
                $first = $u->first_name ?: strtok($u->display_name ?: $u->user_login, ' ');
                echo 'Olá, ' . esc_html(ucfirst(strtolower($first)));
            } else {
                echo 'Minha conta';
            }
          ?></span>
        </a>
        <a href="<?php echo esc_url(cia_astro_backend_url('/minha-conta/favoritos/')); ?>" class="cdm-action-link wishlist" aria-label="Favoritos">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78L12 21.23l8.84-8.84a5.5 5.5 0 0 0 0-7.78z"/></svg>
          <span>Favoritos</span>
        </a>
        <a href="<?php echo esc_url(wc_get_cart_url()); ?>" class="cdm-cart-btn" aria-label="Carrinho">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.7 13.4a2 2 0 0 0 2 1.6h9.7a2 2 0 0 0 2-1.6L23 6H6"/></svg>
          <span class="cdm-cart-text">Carrinho</span>
          <span class="cdm-cart-count<?php echo (WC()->cart && WC()->cart->get_cart_contents_count() > 0) ? ' has-items' : ''; ?>"><?php echo (int) (WC()->cart ? WC()->cart->get_cart_contents_count() : 0); ?></span>
        </a>
      </div>
    </div>
  </div>

  <!-- Fita confete decorativa -->
  <div class="cdm-confetti-bar" aria-hidden="true">
    <div class="cdm-confetti-bar-inner">
      <span style="background:var(--color-confetti-yellow);"></span>
      <span class="lg" style="background:var(--color-confetti-pink);"></span>
      <span style="background:var(--color-confetti-cyan);"></span>
      <span class="lg" style="background:var(--color-confetti-mint);"></span>
      <span style="background:var(--color-confetti-coral);"></span>
      <span class="lg" style="background:var(--color-confetti-green);"></span>
      <span style="background:var(--color-confetti-yellow);"></span>
    </div>
  </div>

  <!-- Nav categorias (mega-menu desktop) -->
  <nav class="cdm-nav" aria-label="Categorias principais">
    <ul class="cdm-nav-list">
      <li>
        <a href="<?php echo esc_url(cia_astro_frontend_url('/loja/')); ?>" class="cdm-nav-item todos">Todos</a>
      </li>
      <?php foreach ($cia_topcats as $cat): $decor = $cat['decor']; $has_children = !empty($cat['children']); ?>
        <?php if ($has_children): ?>
          <li class="cdm-mega" data-cat="<?php echo esc_attr($decor['confetti']); ?>">
            <a href="<?php echo esc_url(cia_astro_frontend_url($cat['url'] . '/')); ?>" class="cdm-nav-item" aria-haspopup="true">
              <?php echo esc_html($cat['name']); ?>
              <svg class="caret" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
            </a>
            <div class="cdm-mega-panel" role="menu">
              <div class="cdm-mega-stripe" style="background: linear-gradient(90deg, var(--color-confetti-<?php echo esc_attr($decor['confetti']); ?>) 0%, var(--color-confetti-<?php echo esc_attr($decor['confetti']); ?>) 60%, color-mix(in srgb, var(--color-confetti-<?php echo esc_attr($decor['confetti']); ?>) 40%, white) 100%);"></div>
              <div class="cdm-mega-inner">
                <div class="cdm-mega-cats">
                  <div class="cdm-mega-head">
                    <span class="cdm-mega-emoji" style="background: color-mix(in srgb, var(--color-confetti-<?php echo esc_attr($decor['confetti']); ?>) 22%, white);" aria-hidden="true"><?php echo $decor['emoji']; ?></span>
                    <div>
                      <h3><?php echo esc_html($cat['name']); ?></h3>
                      <small>Sub-categorias</small>
                    </div>
                  </div>
                  <ul class="cdm-mega-sublist<?php echo count($cat['children']) > 6 ? ' wide' : ''; ?>">
                    <?php foreach ($cat['children'] as $sub): ?>
                      <li>
                        <a href="<?php echo esc_url(cia_astro_frontend_url('/categoria/' . $sub->slug . '/')); ?>"
                           style="--cdm-sub-hover: var(--color-confetti-<?php echo esc_attr($decor['confetti']); ?>); background-image: linear-gradient(currentColor, currentColor); background-size: 0 0;"
                           onmouseover="this.style.background='var(--cdm-sub-hover)';"
                           onmouseout="this.style.background='transparent';"
                           role="menuitem">
                          <span><?php echo esc_html($sub->name); ?></span>
                          <span class="count"><?php echo (int) $sub->count; ?></span>
                        </a>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                </div>
                <aside class="cdm-mega-aside">
                  <img src="<?php echo esc_url($decor['image']); ?>" alt="<?php echo esc_attr($cat['name'] . ' — Cia das Mochilas'); ?>" loading="lazy" width="320" height="240" />
                  <div class="veil" style="background: linear-gradient(180deg, color-mix(in srgb, var(--color-confetti-<?php echo esc_attr($decor['confetti']); ?>) 35%, transparent) 0%, rgba(0,0,0,0.35) 55%, rgba(0,0,0,0.85) 100%);"></div>
                  <div class="content">
                    <span class="badge" style="background: var(--color-confetti-<?php echo esc_attr($decor['confetti']); ?>); color: <?php echo $decor['confetti'] === 'yellow' ? 'var(--color-ink)' : 'white'; ?>;">Categoria</span>
                    <h4><?php echo esc_html($cat['name']); ?></h4>
                    <p><?php echo (int) $cat['count']; ?> <?php echo $cat['count'] === 1 ? 'produto' : 'produtos'; ?> · <?php echo count($cat['children']); ?> sub-categorias</p>
                    <a href="<?php echo esc_url(cia_astro_frontend_url($cat['url'] . '/')); ?>" class="cta">
                      Ver todos
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                    </a>
                  </div>
                </aside>
              </div>
            </div>
          </li>
        <?php else: ?>
          <li class="cdm-mega" data-cat="<?php echo esc_attr($decor['confetti']); ?>">
            <a href="<?php echo esc_url(cia_astro_frontend_url($cat['url'] . '/')); ?>" class="cdm-nav-item"><?php echo esc_html($cat['name']); ?></a>
          </li>
        <?php endif; ?>
      <?php endforeach; ?>
      <li>
        <a href="<?php echo esc_url(cia_astro_frontend_url('/marcas/')); ?>" class="cdm-nav-item marcas">Marcas</a>
      </li>
      <li style="margin-left:auto;">
        <a href="<?php echo esc_url(cia_astro_frontend_url('/promocoes/')); ?>" class="cdm-promo-link">🔥 Promoções</a>
      </li>
    </ul>
  </nav>
</header>

<!-- ============ Drawer mobile ============ -->
<div id="cdm-mobile-drawer" class="cdm-drawer-overlay" aria-hidden="true" role="dialog" aria-modal="true" aria-label="Menu de navegação">
  <div data-cdm-close style="position:absolute; inset:0;"></div>
  <aside class="cdm-drawer">
    <div class="cdm-drawer-head">
      <img src="<?php echo esc_url($cia_logo); ?>" alt="Cia das Mochilas" width="160" height="32" />
      <button type="button" data-cdm-close class="cdm-drawer-close" aria-label="Fechar menu">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>

    <form role="search" action="<?php echo esc_url(cia_astro_frontend_url('/busca/')); ?>" method="get" class="cdm-drawer-search">
      <label for="cdm-drawer-search" class="cdm-sr-only">Buscar produtos</label>
      <div class="wrap">
        <input id="cdm-drawer-search" name="q" type="search" placeholder="Buscar mochilas, lápis, marcas…" autocomplete="off" />
        <button type="submit" aria-label="Buscar">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
        </button>
      </div>
    </form>

    <div class="cdm-drawer-section">
      <a href="<?php echo esc_url(cia_astro_frontend_url('/promocoes/')); ?>" class="cdm-drawer-cta sale">
        <span>🔥 Promoções</span>
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
      </a>
      <a href="<?php echo esc_url(cia_astro_frontend_url('/loja/')); ?>" class="cdm-drawer-cta shop">
        <span>🛍️ Todos os produtos</span>
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
      </a>
    </div>

    <nav class="cdm-drawer-cats" aria-label="Categorias">
      <h4>Categorias</h4>
      <?php foreach ($cia_topcats as $cat): $decor = $cat['decor']; $has_children = !empty($cat['children']); ?>
        <?php if ($has_children): ?>
          <details class="cdm-drawer-cat" data-cat="<?php echo esc_attr($decor['confetti']); ?>">
            <summary>
              <span class="label">
                <span class="emoji" aria-hidden="true"><?php echo $decor['emoji']; ?></span>
                <span><?php echo esc_html($cat['name']); ?></span>
                <span class="count">(<?php echo (int) $cat['count']; ?>)</span>
              </span>
              <svg class="caret" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
            </summary>
            <ul>
              <li><a href="<?php echo esc_url(cia_astro_frontend_url($cat['url'] . '/')); ?>" class="see-all">Ver todos →</a></li>
              <?php foreach ($cat['children'] as $sub): ?>
                <li><a href="<?php echo esc_url(cia_astro_frontend_url('/categoria/' . $sub->slug . '/')); ?>"><span><?php echo esc_html($sub->name); ?></span><span class="count"><?php echo (int) $sub->count; ?></span></a></li>
              <?php endforeach; ?>
            </ul>
          </details>
        <?php else: ?>
          <a href="<?php echo esc_url(cia_astro_frontend_url($cat['url'] . '/')); ?>" style="display:flex;align-items:center;gap:0.75rem;padding:0.75rem;border-radius:0.5rem;">
            <span style="font-size:1.25rem;" aria-hidden="true"><?php echo $decor['emoji']; ?></span>
            <span style="font-weight:500;"><?php echo esc_html($cat['name']); ?></span>
            <span style="margin-left:auto;font-size:0.75rem;color:#9ca3af;">(<?php echo (int) $cat['count']; ?>)</span>
          </a>
        <?php endif; ?>
      <?php endforeach; ?>
      <a href="<?php echo esc_url(cia_astro_frontend_url('/marcas/')); ?>" style="display:flex;align-items:center;gap:0.75rem;padding:0.75rem;border-radius:0.5rem;margin-top:0.25rem;">
        <span style="font-size:1.25rem;" aria-hidden="true">🏷️</span>
        <span style="font-weight:500;">Marcas</span>
      </a>
    </nav>

    <div class="cdm-drawer-section cdm-drawer-account">
      <h4>Sua conta</h4>
      <a href="<?php echo esc_url(cia_astro_backend_url('/minha-conta/')); ?>">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="color:#4b5563;"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-7 8-7s8 3 8 7"/></svg>
        <span><?php
          if (is_user_logged_in()) {
              $u = wp_get_current_user();
              $first = $u->first_name ?: strtok($u->display_name ?: $u->user_login, ' ');
              echo 'Olá, ' . esc_html(ucfirst(strtolower($first)));
          } else {
              echo 'Minha conta';
          }
        ?></span>
      </a>
      <a href="<?php echo esc_url(cia_astro_backend_url('/minha-conta/favoritos/')); ?>">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="color:var(--color-confetti-pink);"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78L12 21.23l8.84-8.84a5.5 5.5 0 0 0 0-7.78z"/></svg>
        <span>Favoritos</span>
      </a>
      <a href="<?php echo esc_url(cia_astro_backend_url('/rastrear-pedido/')); ?>">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="color:var(--color-brand);"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
        <span>Rastrear pedido</span>
      </a>
      <a href="https://wa.me/5511973584809" target="_blank" rel="noopener" class="whatsapp">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" style="color:var(--color-pix);"><path d="M20.5 3.5A11.7 11.7 0 0 0 12 .1 12 12 0 0 0 1.6 17.5L.1 23.9l6.5-1.7a12 12 0 0 0 5.4 1.4A12 12 0 0 0 24 11.6c0-3.1-1.2-6-3.5-8.1z"/></svg>
        <span>WhatsApp · (11) 9 7358-4809</span>
      </a>
    </div>

    <div class="cdm-drawer-footer">
      <p>📞 (11) 2249-1024 · Seg-Sex 9h-18h</p>
      <p>✉️ contato@ciadasmochilas.com.br</p>
      <p style="padding-top:0.5rem;font-size:10px;">© Cia das Mochilas Comercial Ltda</p>
    </div>
  </aside>
</div>

<main id="content" class="cdm-main" role="main">
  <div class="cdm-container cdm-content">
