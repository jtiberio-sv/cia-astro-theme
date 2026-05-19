# cia-astro

Tema WordPress (child theme do Storefront) que espelha o frontend Astro da Cia das Mochilas em paginas WooCommerce: cart, checkout, my-account, wishlist, track.

## Por que existe

O projeto e headless:
- **Frontend** (`dev.ciadasmochilas.com.br` / `ciadasmochilas.com.br` pos-cutover) servido por Astro em Cloudflare Pages.
- **Backend** (`loja-dev.ciadasmochilas.com.br` / `loja.ciadasmochilas.com.br`) — WordPress + WooCommerce em Hetzner.

Usuario interage com o WP apenas em 5 fluxos dinamicos (cart/checkout/account/wishlist/track). Antes deste tema, esses fluxos rodavam em `besa-child` (Thembay) com identidade visual incompativel com o Astro. **`cia-astro` resolve essa quebra de UX.**

## Estado atual

- **Versao:** 0.1.0 (Fase 0 — scaffold)
- **Ativo em:** `loja-dev.ciadasmochilas.com.br` apenas. Prod (`ciadasmochilas.com.br`) permanece `besa-child` ate validacao final do cliente.

## Roadmap (fases conforme plan no devops-docs)

- [x] **Fase 0** — Scaffold + ativacao isolada (1h)
- [ ] **Fase 1** — Header + Footer espelhando Astro (3h)
- [ ] **Fase 2** — Tokens + base CSS (2h)
- [ ] **Fase 3** — Cart + Checkout reskinned (3h)
- [ ] **Fase 4** — My-Account + Wishlist + Track (2h)
- [ ] **Fase 5** — Limpeza de plugins prod (2h)
- [ ] **Fase 6** — Backup S3 (1h)
- [ ] **Fase 7** — Performance audit (1h)
- [ ] **Fase 8** — Deploy prod + cutover (1h)

Detalhes completos em `d:/tmp/devops-docs/projects/ciadasmochilas/wp-theme/plan.md` (devops-docs interno SV).

## Deploy

### Manual (rsync local → servidor)

```powershell
# Da raiz do repo, no Windows:
rsync -av --delete \
  --exclude='.git' --exclude='.github' --exclude='README.md' \
  ./ ciadasmochilas:/var/www/loja-dev.ciadasmochilas.com.br/htdocs/wp-content/themes/cia-astro/

ssh ciadasmochilas "cd /var/www/loja-dev.ciadasmochilas.com.br/htdocs && sudo -u www-data wp theme activate cia-astro"
```

### Automatico via GitHub Actions

A implementar (Fase 0.5 ou Fase 1). Workflow `deploy.yml` rsync para o servidor via SSH key dedicada.

## Estrutura

```
cia-astro/
├── style.css           # Metadata WP (vazio de regras — CSS via cascata)
├── functions.php       # Bootstrap (require_once inc/*)
├── theme.json          # Design tokens (FSE-aware)
├── screenshot.png      # 1200x900px brand
├── inc/
│   ├── theme-setup.php # add_theme_support, register_nav_menus
│   ├── enqueue.php     # Cascata CSS controlada
│   ├── woo-config.php  # remove_action do Storefront que nao queremos
│   └── woo-hooks.php   # add_action para injetar conteudo nas paginas WC
├── assets/
│   ├── css/            # tokens.css, base.css, header.css, footer.css, woo-*.css
│   ├── js/             # mobile-drawer.js, cart-mini.js
│   └── fonts/          # Fredoka + Inter self-hosted (LGPD-friendly)
└── woocommerce/        # Template overrides (ultimo recurso)
```

## Rollback

Em qualquer momento, em loja-dev:

```bash
ssh ciadasmochilas "cd /var/www/loja-dev.ciadasmochilas.com.br/htdocs && sudo -u www-data wp theme activate besa-child"
```

## Licenca

Privado — uso restrito ao cliente Cia das Mochilas.
