# Arquitetura — Cia das Mochilas (WordPress backend)

Documento canônico sobre **onde mora cada tipo de código** no backend WordPress da Cia das Mochilas. Use isso como matriz de decisão ao adicionar uma nova feature, hook, integração ou customização.

> **TL;DR**: Use **mu-plugin** pra coisas que **não podem quebrar** se trocar de tema (auth, security, SEO, redirects, integrações). Use **tema** (`cia-astro/`) pra tudo que é **visual ou específico desta UX** (templates, CSS, hooks WC, customizações admin visuais). Use **plugin custom** se for uma feature reutilizável entre projetos.

---

## 1. Mapa de alto nível

```
loja.ciadasmochilas.com.br (WordPress + WooCommerce)
│
├── wp-content/mu-plugins/        ← "Must-Use" — sempre carregam, não desativáveis no painel
│   ├── cdm-jwt-bearer-auth.php   ← Auth crítico (vitrine Astro chama REST com Bearer JWT)
│   ├── cdm-sso-bridge.php        ← SSO vitrine↔loja (nonce one-time, cookie WP)
│   ├── cdm-301-redirects.php     ← SEO crítico (sobrevive a troca de tema)
│   ├── cdm-vitrine-rebuild.php   ← Webhook → GH Actions (independente de tema)
│   ├── log-rest.php              ← Debug REST + mime fix uploads
│   └── (admin-menu-rename + wfls-style FORAM MOVIDOS pro tema em 2026-05-22)
│
├── wp-content/themes/cia-astro/  ← Tema filho do Storefront (este repo)
│   ├── functions.php             ← Bootstrap (require_once inc/*)
│   ├── inc/
│   │   ├── theme-setup.php       ← add_theme_support, register_nav_menus
│   │   ├── enqueue.php           ← Cascata CSS/JS controlada
│   │   ├── urls.php              ← Helpers de URL
│   │   ├── url-rewrites.php      ← Rewrites de slug WC
│   │   ├── woo-config.php        ← remove_action Storefront que conflita
│   │   ├── woo-hooks.php         ← add_action WC visual (dashboard, view-order)
│   │   ├── newsletter.php        ← Form newsletter + integração
│   │   ├── products-defaults.php ← Defaults de produto (campos custom)
│   │   ├── wishlist.php          ← REST endpoints favoritos + menu account
│   │   ├── emails.php            ← Subjects + headings WC PT-BR
│   │   ├── cart-abandonment.php  ← Recuperação carrinho
│   │   ├── express-checkout.php  ← Botão checkout rápido
│   │   ├── login-google.php      ← Login social
│   │   └── admin-ui.php          ← Customizações admin visuais (menu rename + page styles)
│   ├── assets/
│   │   ├── css/                  ← Front CSS (tokens, base, header, footer, woo-*)
│   │   └── js/                   ← Front JS
│   └── woocommerce/              ← Template overrides (último recurso)
│       ├── emails/               ← email-header.php, email-footer.php
│       └── myaccount/            ← dashboard.php, view-order.php
│
└── wp-content/plugins/           ← Plugins de terceiros (Wordfence, W3TC, EWWW, etc.)
                                    Nenhum plugin custom nosso hoje.
```

---

## 2. Matriz de decisão — onde mora o quê

Quando for adicionar código novo, pergunte:

### Pergunta 1 — Se desativar o tema, esse código DEVE continuar funcionando?

| Resposta | Onde |
|---|---|
| **Sim** — é infra crítica (auth, security, SEO, redirects, integrações com sistemas externos) | **mu-plugin** |
| **Não** — é visual ou específico desta UX (templates, CSS, hooks WC visuais, admin styling) | **tema** |

### Pergunta 2 — Esse código é reutilizável em outros projetos?

| Resposta | Onde |
|---|---|
| **Sim** — feature genérica que pode virar produto interno SV/Tyber | **plugin custom próprio** (em `plugins/`, ativável, versionável) |
| **Não** — é específico do Cia das Mochilas | mu-plugin OU tema (volta pra pergunta 1) |

### Pergunta 3 — É CSS/JS do front ou do admin?

| Resposta | Onde |
|---|---|
| Front (vitrine WC: cart, checkout, account, single-product) | `assets/css/woo-*.css` + `inc/enqueue.php` |
| Admin visual (página de plugin terceiro, menu rename) | `inc/admin-ui.php` |
| Email transacional (header, footer, subjects) | `woocommerce/emails/*.php` (template override) ou `inc/emails.php` (subjects/headings) |

---

## 3. Convenções por tipo de código

### 3.1 Mu-plugins (`wp-content/mu-plugins/*.php`)

**Quando criar:**
- Auth/security crítico (JWT decoder, capability filters, session hardening)
- SEO crítico (301 redirects de URLs legadas, canonical filters)
- Integrações com sistemas externos (webhooks, APIs)
- Patches de plugins terceiros que NÃO podem ser desativados por acidente

**Padrão de nomenclatura:** `cdm-<feature>.php` (prefixo `cdm-` = Cia Das Mochilas, identifica todos os nossos mu-plugins na pasta).

**Cabeçalho mínimo:**
```php
<?php
/**
 * Plugin Name: CDM <Nome Curto>
 * Description: <O que faz + por que é mu-plugin e não tema>.
 */
if (!defined('ABSPATH')) exit;
```

**Boas práticas:**
- Hooks em prioridade adequada (auth filters geralmente prioridade alta, ex: `determine_current_user` priority 30)
- Guard `if (!defined('ABSPATH')) exit;` no topo
- Documentar no header POR QUE é mu-plugin (futuro dev precisa saber se pode mover)
- Funções com prefixo `cdm_` pra evitar colisão (ex: `cdm_jwt_bearer_determine_user`)

### 3.2 Tema (`wp-content/themes/cia-astro/`)

**Quando criar arquivo novo em `inc/`:**
- Nova feature visual coesa que precisa de >50 linhas (ex: `inc/cart-abandonment.php`)
- Grupo de hooks WC relacionados (ex: `inc/woo-hooks.php`)
- Customizações admin (ex: `inc/admin-ui.php`)

**Quando expandir arquivo existente:**
- Hook isolado que pertence claramente a uma feature já mapeada
- Helper function relacionada a um módulo existente

**Padrão de nomenclatura:** `inc/<area>.php` ou `inc/<area>-<subarea>.php`.

**Carregar via `functions.php`:**
```php
require_once CIA_ASTRO_DIR . '/inc/<arquivo>.php';
```

Se o arquivo é apenas admin, embrulhe em `if (is_admin())` pra economizar parsing no front.

**Convenção interna de seções:**
Arquivos com múltiplas responsabilidades relacionadas (ex: `inc/admin-ui.php` = menu rename + i18n + page styles) usam marcadores `// === SECTION: <nome> ===`. Facilita busca e mantém arquivo navegável.

### 3.3 Plugin custom próprio (NÃO usamos hoje)

**Quando criaríamos:**
- Feature 100% reutilizável que faz sentido publicar (open source ou plugin pago)
- Feature complexa que precisa ter versionamento próprio (changelog, releases, update mechanism)

**Não estamos nesse caso hoje.** Toda customização atual é específica do Cia das Mochilas → mu-plugin ou tema basta.

### 3.4 Template overrides (`woocommerce/`)

**Último recurso.** Antes de copiar um template do WC, tente:
1. Resolver com action hook (`woocommerce_account_dashboard`, `woocommerce_after_single_product_summary`, etc.)
2. Resolver com filter (`woocommerce_locate_template`, `wc_get_template_part`)
3. Só se não dá → copia o template e edita

**Por quê:** templates copiados ficam **desatualizados** quando WC atualiza. Você precisa lembrar de re-mergear mudanças do upstream a cada update grande do WC.

**Templates atualmente overridados:**
- `woocommerce/myaccount/dashboard.php` — remove texto default ("Olá X (não é X? Sair)")
- `woocommerce/myaccount/view-order.php` — layout 2-col Pedido | Vindi
- `woocommerce/emails/email-header.php` — logo + brand
- `woocommerce/emails/email-footer.php` — trust bar + CTAs + logo branco

---

## 4. Inventário dos mu-plugins atuais (2026-05-22)

| Arquivo | Por que é mu-plugin | Pode mover pro tema? |
|---|---|---|
| `cdm-jwt-bearer-auth.php` | Decoder JWT em rotas REST custom — se desabilitar, vitrine Astro deslogga todo mundo | **Não.** Auth crítico, independente de tema |
| `cdm-sso-bridge.php` | SSO vitrine↔loja via nonce one-time (endpoints `/cdm/v1/sso-create` + `/sso`) — se desabilitar, usuário loga 2x | **Não.** Auth/sessão crítico, independente de tema |
| `cdm-301-redirects.php` | Redirects 301 de slugs renomeados — se trocar tema, redirects DEVEM sobreviver | **Não.** SEO crítico, independente de tema |
| `cdm-vitrine-rebuild.php` | Webhook que dispara GH Actions rebuild da vitrine ao salvar produto — totalmente desacoplado de tema | **Não.** Integração externa, independente de tema |
| `log-rest.php` | Debug REST API (logs) + fallback de detecção mime em uploads | **Parcialmente.** Debug é debug (talvez disable em prod); mime fix poderia ir pro tema mas é nicho |

### Mu-plugins removidos em 2026-05-22 (movidos pro tema)

| Arquivo | Pra onde foi | Motivo |
|---|---|---|
| `cdm-admin-menu-rename.php` | `cia-astro/inc/admin-ui.php` (section: Menu rename) | Visual admin puro, faz parte da identidade do tema |
| `cdm-wfls-style.php` | `cia-astro/inc/admin-ui.php` (section: Page styles WFLS) | CSS admin puro, faz parte da identidade do tema |

---

## 5. Anti-patterns (evitar)

- ❌ **Mu-plugin pra CSS de página admin** → vai no tema (`inc/admin-ui.php`)
- ❌ **Tema pra auth/security/redirects** → quebra ao trocar tema, vai pra mu-plugin
- ❌ **Adicionar require_once direto em `functions.php`** pra hook isolado → cria arquivo em `inc/` com nome semântico
- ❌ **Copiar template WC sem comentar por quê** → adicione header no template explicando o motivo do override
- ❌ **Functions sem prefixo `cdm_` ou `cia_astro_`** → polui namespace global, risco de colisão
- ❌ **Hooks com prioridade default (10) em filters que terceiros também tocam** → use prioridade explícita (alta pra forçar última palavra, baixa pra preparar)

---

## 6. Workflow de mudança

1. **Identificar onde mora** (matriz seção 2)
2. **Implementar local** no arquivo apropriado
3. **Deploy via rsync** (`scripts/deploy.sh` — TODO criar, hoje é manual; ver README)
4. **Validar** a feature em produção (loja.ciadasmochilas.com.br)
5. **Commit + push** no repo
6. **Atualizar este doc** se a mudança altera a estrutura (novo mu-plugin, novo arquivo `inc/`, novo template override)

---

## 6.5 Routing e blindagem da loja

Arquitetura headless implica que **vitrine e loja são domínios separados com responsabilidades distintas**. URLs do front (catálogo, conteúdo) devem responder APENAS na vitrine; URLs dinâmicas WP (cart, checkout, my-account) APENAS na loja.

### Camadas de routing (em ordem de execução)

```
Browser hit
    │
    ├─ 1. Vitrine (ciadasmochilas.com.br via CF Pages)
    │     └─ public/_redirects (CF edge) — paths amigaveis vitrine -> URLs canonicas loja
    │
    ├─ 2. SSO interceptor (src/lib/sso-bridge.ts)
    │     └─ Click <a> pra path da loja -> handshake JWT -> redirect com cookie WP
    │
    └─ 3. Loja (loja.ciadasmochilas.com.br via Hetzner nginx)
          ├─ conf/nginx/cia-redirects.conf (server-level if-return 301) ← BLINDAGEM
          │     ├─ Section 0: Redirects legacy especificos (dia-do-consumidor etc)
          │     ├─ Section 1: Front paths catch-all -> vitrine
          │     └─ Section 2: Aliases canonicos (favoritos, checkout, etc)
          ├─ conf/nginx/w3tc.conf (W3TC page cache rewrite)
          └─ vhost default -> WP/WC normal
```

### Lista canônica de paths

**Apenas LOJA serve (whitelist):**
- `/wp-json/*`, `/wp-admin/*`, `/wp-login.php`, `/wp-cron.php`, `/xmlrpc.php` (bloqueado)
- `/carrinho/*`, `/finalizar-compra/*`, `/minha-conta/*`, `/rastrear-pedido/*`
- `/wp-content/*`, `/wp-includes/*`
- `/favicon.ico`, `/robots.txt`, `/sitemap*.xml`
- `/` (home da loja — pode redirect futuro pra vitrine)

**LOJA redireciona 301 -> VITRINE:**
- `/produto/*`, `/categoria/*`, `/categoria-produto/*`, `/marca/*`
- `/loja/`, `/shop/`, `/marcas/`, `/promocoes/`, `/busca/`
- `/blog/*`, `/guia/*`
- Pages institucionais: `/contato`, `/quem-somos`, `/como-comprar`, `/trocas-e-devolucoes`, `/prazo-e-entrega`, `/politica-de-privacidade-2`, `/termos-de-uso`, `/sample-page`, `/newsletter`

**Aliases (LOJA redireciona pra path canônico):**
| Alias | Path canônico |
|---|---|
| `/favoritos`, `/wishlist`, `/my-wishlist` | `/minha-conta/favoritos/` |
| `/checkout` | `/finalizar-compra/` |
| `/rastrear`, `/rastreio`, `/track-my-order` | `/rastrear-pedido/` |
| `/esqueci-a-senha`, `/recuperar-senha` | `/minha-conta/esqueci-a-senha/` |
| `/categoria/dia-do-consumidor` | `/categoria/ofertas/` (campanha encerrada) |

### Três pontos de manutenção sincronizada

Quando adicionar/mudar alias ou path interceptado, atualizar **os três**:
1. **Vitrine** — `public/_redirects` (CF Pages edge redirect 302)
2. **Vitrine** — `src/lib/sso-bridge.ts` (`LOJA_ALIASES` + `LOJA_PATH_PREFIXES`)
3. **Loja** — `/var/www/.../conf/nginx/cia-redirects.conf` (server-level 301)

### Detalhe técnico: por que `if` e não `location`

O W3TC tem `rewrite .* "/wp-content/cache/..." last;` em **server level** que roda na rewrite phase do nginx (antes do location matching). Pra URLs com cache, isso reescreve a URI pra `/wp-content/cache/...` e nosso `location ~ ^/produto` nunca seria avaliado.

Solução: usar `if ($request_uri ~ ^/produto)` que também roda na rewrite phase. Como `cia-redirects.conf` é incluído antes de `w3tc.conf` (alfabético), nossos ifs disparam primeiro.

(O famoso "if is evil" do nginx refere-se a `if` em LOCATION context com operações complexas. `if` em SERVER context com `return` é oficialmente documentado e seguro.)

---

## 7. Referências cruzadas

- `README.md` — visão geral do tema, deploy, rollback
- `inc/admin-ui.php` — exemplo de arquivo com múltiplas seções documentadas
- `inc/woo-hooks.php` — exemplo de arquivo com hooks WC visuais
- `wp-content/mu-plugins/cdm-jwt-bearer-auth.php` — exemplo de mu-plugin crítico documentado
- `d:/tmp/devops-docs/projects/ciadasmochilas/` — docs operacionais SV (devops-docs interno, não público)

---

## 8. Histórico de decisões arquiteturais

| Data | Decisão | Motivo |
|---|---|---|
| 2026-05-22 | Movido `cdm-wfls-style.php` + `cdm-admin-menu-rename.php` pra `inc/admin-ui.php` | CSS/UX admin puro pertence ao tema, não a mu-plugin. Convenção firmada nesse doc |
| 2026-05-22 | Criado `inc/admin-ui.php` como home pra customizações admin visuais (com convenção `// === SECTION: ===`) | Padronização pra futuras customizações admin terem um lugar canônico |
| 2026-05-22 | `cdm-jwt-bearer-auth.php` mantido como mu-plugin | Auth crítico — não pode quebrar se trocar tema |
| 2026-05-22 | `cdm-301-redirects.php` mantido como mu-plugin | SEO crítico — redirects devem sobreviver a troca de tema |
| 2026-05-22 | `cdm-vitrine-rebuild.php` mantido como mu-plugin | Integração externa (GH Actions) — independente de tema |
| 2026-05-22 | Criado `cdm-sso-bridge.php` (mu-plugin) — SSO vitrine↔loja via nonce one-time | Auth/sessão crítico. Endpoints `POST /cdm/v1/sso-create` (Bearer JWT → nonce) + `GET /cdm/v1/sso?nonce=...&redirect=...` (set_auth_cookie + 302). Frontend interceptor em `src/lib/sso-bridge.ts` da vitrine intercepta clicks pra paths da loja. Evita login duplicado quando usuário loga na vitrine e navega pra `/minha-conta`, `/carrinho`, etc. |
| 2026-05-22 | Criado `cia-redirects.conf` (nginx) — blindagem da loja | Loja respondia URLs do front (`/produto/*`, `/categoria/*`, `/marca/*`, pages institucionais) causando conteúdo duplicado no Google + UX ruim (500s). Server-level `if ($request_uri ~ ...)` retorna 301 pra vitrine. Roda antes do W3TC rewrite (cia-* < w3tc alfabeticamente). Cobertura validada em 19 paths via curl. Doc na seção 6.5 deste arquivo + comentários in-file no nginx config. |
| 2026-05-22 | SSO bridge ganhou mapa `LOJA_ALIASES` | Antes: bridge construía dest=loja.X+pathname (assumia paths iguais). Quebrou `/favoritos` → `loja.X/favoritos` (404 em vez de `loja.X/minha-conta/favoritos/`). Agora: `LOJA_ALIASES` espelha `_redirects` e `cia-redirects.conf`. Os três devem ser atualizados juntos sempre. |
