<?php
/**
 * Admin UI — customizacoes visuais e de UX dentro do wp-admin.
 *
 * ESCOPO desse arquivo:
 * - Renomear itens de menu admin (i18n / branding interno)
 * - Estilizar paginas admin de plugins terceiros (CSS injetado em admin_head)
 * - Pequenos ajustes visuais que NAO afetam o front
 *
 * O QUE NAO VAI AQUI:
 * - Logica de seguranca/auth (vai em mu-plugin — sobrevive a troca de tema)
 * - Templates do front (vai em /woocommerce ou /inc/woo-hooks.php)
 * - CSS do front (vai em /assets/css/*.css carregado via /inc/enqueue.php)
 * - Hooks de WC visuais no storefront (vai em /inc/woo-hooks.php)
 *
 * Ver docs/ARCHITECTURE.md pra matriz completa de decisao.
 *
 * Convencao interna: cada bloco abaixo e uma "secao" delimitada por
 * `// === SECTION: <nome> ===`. Ao adicionar nova customizacao admin,
 * crie nova secao ou expanda uma existente — mantenha o arquivo
 * navegavel por busca de "SECTION:".
 */

if (!defined('ABSPATH')) {
    exit;
}


// =============================================================================
// === SECTION: Menu rename — Wordfence Login Security → "Seguranca" =========
// =============================================================================
//
// Renomeia o item top-level "WFLS" (Wordfence 2FA + Login Security) pra
// "Seguranca" no menu admin. Mais amigavel pro cliente nao-tecnico do que
// o branding original do plugin. Aplica tambem no submenu Wordfence.
//
// Hook em priority 9999 garante que rodamos DEPOIS do plugin registrar
// seus itens (Wordfence adiciona em priority 10).

add_action('admin_menu', function () {
    global $menu, $submenu;

    if (is_array($menu)) {
        foreach ($menu as $k => $item) {
            if (isset($item[2]) && $item[2] === 'WFLS') {
                $menu[$k][0] = 'Segurança';
            }
        }
    }

    if (isset($submenu['Wordfence']) && is_array($submenu['Wordfence'])) {
        foreach ($submenu['Wordfence'] as $sk => $sub) {
            if (isset($sub[2]) && $sub[2] === 'WFLS') {
                $submenu['Wordfence'][$sk][0] = 'Segurança (2FA)';
            }
        }
    }
}, 9999);


// =============================================================================
// === SECTION: i18n override — strings do Wordfence em PT-BR amigavel ========
// =============================================================================
//
// Traduz strings especificas dos textdomains do Wordfence Login Security
// pra terminologia consistente com "Seguranca" (mesma label do menu).

add_filter('gettext', function ($translated, $text, $domain) {
    if ($domain === 'wordfence-2fa' || $domain === 'wordfence-ls') {
        if ($text === 'Login Security') {
            return 'Segurança';
        }
        if ($text === 'Wordfence Login Security') {
            return 'Segurança (2FA + Login)';
        }
    }
    return $translated;
}, 10, 3);


// =============================================================================
// === SECTION: Page styles — WFLS (Wordfence Login Security) ================
// =============================================================================
//
// Reescreve o visual da pagina de ativacao 2FA do Wordfence pra match com
// nossa identidade (brand #0f4a7a + radius 12px + cards com shadow + steps
// numerados via counter CSS). O plugin original entrega HTML sem classes
// claras, entao usamos seletores resilientes (combinando id, name, atributos).
//
// Carrega APENAS na page WFLS (detectada via $_GET['page'] ou screen->id),
// pra evitar poluir outras paginas admin.

add_action('admin_head', function () {
    $is_wfls = cia_astro_is_wfls_screen();
    if (!$is_wfls) {
        return;
    }
    ?>
    <style>
        /* === CDM custom layout pra Seguranca/2FA (Wordfence WFLS) === */
        #wpcontent { padding-left: 30px !important; }
        .wfls-container,
        .wfls-2fa-activate,
        .wfacp_optin_form_3,
        .wf-content {
            max-width: 980px;
            margin: 24px auto;
        }

        /* Cards de step */
        .wfls-step,
        .wf-card,
        .wfModalContent,
        table.wfls-2fa-activate,
        .wfls-2fa-activate {
            background: white !important;
            border: 1px solid #e5e7eb !important;
            border-radius: 12px !important;
            padding: 28px !important;
            margin: 18px 0 !important;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04) !important;
        }

        /* QR code — centralizar e dar destaque */
        .wfls-2fa-activate-qr-container,
        img[src*='qr_code'],
        canvas#wfls-qr-canvas,
        .wfls-2fa-secret-image,
        div:has(> canvas#wfls-qr-canvas) {
            display: block !important;
            margin: 18px auto !important;
            text-align: center !important;
            max-width: 280px !important;
        }
        canvas#wfls-qr-canvas {
            border: 8px solid white;
            box-shadow: 0 4px 16px rgba(0,0,0,0.08);
            border-radius: 8px;
            background: white;
            padding: 8px;
        }

        /* Secret key — destaque copy-to-clipboard friendly */
        .wfls-2fa-secret,
        code[id*='secret'],
        .wf-monospace,
        .wfls-secret-key {
            display: inline-block !important;
            font-family: 'SF Mono', Menlo, Consolas, monospace !important;
            font-size: 16px !important;
            font-weight: 600 !important;
            background: #f3f4f6 !important;
            color: #0f4a7a !important;
            padding: 10px 16px !important;
            border-radius: 8px !important;
            letter-spacing: 0.05em !important;
            border: 1px dashed #cbd5e1 !important;
            user-select: all !important;
            cursor: copy;
        }

        /* Tipografia */
        .wfls-container h1,
        .wfls-container h2,
        .wfls-container h3,
        .wfls-page-title {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
            color: #0f4a7a !important;
        }
        .wfls-container h2 { font-size: 22px !important; margin-bottom: 12px !important; }
        .wfls-container h3,
        .wfls-2fa-activate h3 {
            font-size: 16px !important;
            color: #0f4a7a !important;
            border-bottom: 2px solid #e5e7eb !important;
            padding-bottom: 10px !important;
            margin: 0 0 16px !important;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Steps numerados via counter CSS */
        .wfls-2fa-activate h3::before {
            content: counter(wfls-step);
            counter-increment: wfls-step;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            background: linear-gradient(135deg, #0f4a7a 0%, #1d6fb3 100%);
            color: white;
            border-radius: 9999px;
            font-size: 14px;
            font-weight: 700;
        }
        .wfls-2fa-activate { counter-reset: wfls-step; }

        /* Inputs codigo 6 digitos */
        input[type='text'][name*='code'],
        input[name='token'],
        input.wfls-token-input {
            font-family: 'SF Mono', monospace !important;
            font-size: 22px !important;
            letter-spacing: 0.3em !important;
            text-align: center !important;
            padding: 12px 16px !important;
            border: 2px solid #cbd5e1 !important;
            border-radius: 10px !important;
            max-width: 220px !important;
        }
        input[name='token']:focus {
            border-color: #0f4a7a !important;
            box-shadow: 0 0 0 3px rgba(15,74,122,0.15) !important;
            outline: none !important;
        }

        /* Botoes brand */
        .wfls-2fa-activate button,
        .button-primary[id*='wfls'],
        #wfls-activate-button {
            background: linear-gradient(135deg, #0f4a7a 0%, #1d6fb3 100%) !important;
            border: none !important;
            color: white !important;
            padding: 12px 28px !important;
            border-radius: 9999px !important;
            font-weight: 700 !important;
            font-size: 14px !important;
            box-shadow: 0 4px 12px rgba(15,74,122,0.25) !important;
            cursor: pointer;
            transition: transform 0.15s, box-shadow 0.15s !important;
        }
        .wfls-2fa-activate button:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(15,74,122,0.35) !important;
        }

        /* Codigos de recuperacao — alert ambar (importante anotar!) */
        .wfls-recovery-codes,
        .wfls-2fa-recovery {
            background: linear-gradient(135deg, #fef3c7 0%, white 100%) !important;
            border-left: 4px solid #f59e0b !important;
            padding: 16px 18px !important;
            border-radius: 10px !important;
        }
        .wfls-recovery-codes ol li {
            font-family: 'SF Mono', monospace !important;
            background: white;
            padding: 6px 12px;
            border-radius: 6px;
            margin: 4px 0;
            border: 1px solid #fde68a;
            display: inline-block;
            margin-right: 8px;
        }
    </style>
    <?php
}, 100);


/**
 * Detecta se estamos numa screen do Wordfence Login Security.
 * Usado pelo CSS injector acima — extraido em funcao pra facilitar reuso
 * caso outras secoes precisem condicionar comportamento na mesma screen.
 */
function cia_astro_is_wfls_screen(): bool
{
    if (isset($_GET['page'])) {
        $page = (string) $_GET['page'];
        if ($page === 'WFLS' || strpos($page, 'WFLS') === 0) {
            return true;
        }
    }

    if (function_exists('get_current_screen')) {
        $screen = get_current_screen();
        if ($screen && (
            strpos($screen->id, 'WFLS') !== false ||
            strpos($screen->id, 'login-security') !== false
        )) {
            return true;
        }
    }

    return false;
}
