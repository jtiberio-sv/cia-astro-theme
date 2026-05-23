# cia-astro

Tema WordPress (child theme do Storefront) que espelha o frontend Astro da Cia das Mochilas em paginas WooCommerce: cart, checkout, my-account, wishlist, track.

## Por que existe

O projeto e headless:
- **Frontend** (`dev.ciadasmochilas.com.br` / `ciadasmochilas.com.br` pos-cutover) servido por Astro em Cloudflare Pages.
- **Backend** (`loja-dev.ciadasmochilas.com.br` / `loja.ciadasmochilas.com.br`) — WordPress + WooCommerce em Hetzner.

Usuario interage com o WP apenas em 5 fluxos dinamicos (cart/checkout/account/wishlist/track). Antes deste tema, esses fluxos rodavam em `besa-child` (Thembay) com identidade visual incompativel com o Astro. **`cia-astro` resolve essa quebra de UX.**

## Estado atual

- **Versao:** 0.2.0
- **Ativo em:** `loja.ciadasmochilas.com.br` (producao) — cutover concluido em 05/2026.
- **Vitrine externa:** `ciadasmochilas.com.br` (Astro SSG em Cloudflare Pages, repo separado `ciadasmochilas`).

## Arquitetura

Ver [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) — documento canonico sobre **onde mora cada tipo de codigo** (mu-plugin vs tema vs plugin custom vs template override), matriz de decisao e inventario completo.

Resumo:
- **mu-plugins** (`wp-content/mu-plugins/cdm-*.php`): auth/security/SEO/integracoes — sobrevivem a troca de tema
- **tema** (este repo): visual/UX, templates WC, hooks WC visuais, customizacoes admin visuais
- **template overrides** (`woocommerce/`): ultimo recurso — preferir hooks

## Fluxos cobertos

O tema cobre os 5 fluxos dinamicos WC (cart, checkout, my-account, wishlist, view-order) + customizacoes admin (page styles WFLS, menu rename) + emails transacionais (header, footer, subjects PT-BR).

## Deploy

### Manual (rsync local → servidor)

```bash
# Da raiz do repo (Git Bash no Windows):
rsync -avz --delete \
  --exclude='.git' --exclude='.github' --exclude='README.md' \
  --exclude='docs' --exclude='scripts' \
  ./ ciadasmochilas:/var/www/ciadasmochilas.com.br/htdocs/wp-content/themes/cia-astro/

# Tema ja esta ativo em prod — rsync basta, sem precisar reativar
```

### Automatico via GitHub Actions

A implementar (Fase 0.5 ou Fase 1). Workflow `deploy.yml` rsync para o servidor via SSH key dedicada.

## Estrutura

Ver [docs/ARCHITECTURE.md secao 1](docs/ARCHITECTURE.md#1-mapa-de-alto-nível) pro mapa completo. Resumo:

```
cia-astro/
├── style.css           # Metadata WP (vazio de regras)
├── functions.php       # Bootstrap (require_once inc/*)
├── theme.json          # Design tokens (FSE-aware)
├── header.php          # Header espelhando Astro
├── footer.php          # Footer espelhando Astro
├── inc/                # Modulos PHP (1 arquivo = 1 area)
│   ├── theme-setup.php
│   ├── enqueue.php
│   ├── woo-config.php / woo-hooks.php
│   ├── wishlist.php / emails.php / newsletter.php
│   ├── cart-abandonment.php / express-checkout.php
│   ├── login-google.php / products-defaults.php
│   ├── urls.php / url-rewrites.php
│   └── admin-ui.php    # Customizacoes admin visuais
├── assets/
│   ├── css/            # tokens, base, header, footer, woo-*
│   └── js/             # header, checkout-cep, wishlist, etc.
├── woocommerce/        # Template overrides (ultimo recurso)
│   ├── emails/
│   └── myaccount/
└── docs/
    └── ARCHITECTURE.md # Onde mora cada tipo de codigo
```

## Rollback

```bash
ssh ciadasmochilas "cd /var/www/ciadasmochilas.com.br/htdocs && sudo -u www-data wp theme activate storefront"
```

## Licenca

Privado — uso restrito ao cliente Cia das Mochilas.
