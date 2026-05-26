<?php
/**
 * Plugin Name: CDM Vitrine Auto-Rebuild
 * Description: Dispara rebuild da vitrine Astro (via GitHub Actions workflow_dispatch)
 *              quando produtos sao salvos/atualizados/deletados, com debounce 5min
 *              (junta varios saves em 1 build).
 *
 * Setup:
 *   1. Criar fine-grained PAT em github.com/settings/personal-access-tokens
 *      - Repo: ciadasmochilas
 *      - Permissions: Actions (read+write) + Contents (read)
 *   2. Copiar token
 *   3. WP-Admin > Tools > Rebuild Vitrine > colar token + salvar
 */
if (!defined('ABSPATH')) exit;

const CDM_REBUILD_TOKEN    = 'cdm_rebuild_gh_token';
const CDM_REBUILD_REPO     = 'jtiberio-sv/ciadasmochilas';
const CDM_REBUILD_WORKFLOW = 'deploy.yml';
const CDM_REBUILD_BRANCH   = 'main';
const CDM_REBUILD_PENDING  = 'cdm_rebuild_pending';
const CDM_REBUILD_LAST     = 'cdm_rebuild_last_at';
const CDM_REBUILD_DEBOUNCE = 5 * MINUTE_IN_SECONDS;
const CDM_REBUILD_CRON     = 'cdm_rebuild_check_cron';

/* ============================================================
 * 1) HOOKS — marca rebuild como pendente
 * ============================================================ */
function cdm_mark_rebuild_pending($context = '') {
    set_transient(CDM_REBUILD_PENDING, time(), HOUR_IN_SECONDS);
    if (!wp_next_scheduled(CDM_REBUILD_CRON)) {
        wp_schedule_single_event(time() + CDM_REBUILD_DEBOUNCE, CDM_REBUILD_CRON);
    }
    error_log("[cdm-rebuild] pending: $context");
}

add_action('save_post_product', function ($post_id, $post, $update) {
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) return;
    if (!in_array($post->post_status, ['publish', 'draft'], true)) return;
    cdm_mark_rebuild_pending("save_post_product:$post_id");
}, 10, 3);

add_action('delete_post', function ($post_id) {
    if (get_post_type($post_id) === 'product') cdm_mark_rebuild_pending("delete_post:$post_id");
});
add_action('wp_trash_post', function ($post_id) {
    if (get_post_type($post_id) === 'product') cdm_mark_rebuild_pending("trash_post:$post_id");
});
add_action('saved_product_cat',   function () { cdm_mark_rebuild_pending('saved_product_cat'); });
add_action('saved_product_brand', function () { cdm_mark_rebuild_pending('saved_product_brand'); });
add_action('delete_product_cat',  function () { cdm_mark_rebuild_pending('delete_product_cat'); });

/* ============================================================
 * 2) CRON — dispara rebuild se debounce expirou
 * ============================================================ */
add_action(CDM_REBUILD_CRON, function () {
    $pending = get_transient(CDM_REBUILD_PENDING);
    if (!$pending) return;
    $elapsed = time() - $pending;
    if ($elapsed < CDM_REBUILD_DEBOUNCE) {
        wp_schedule_single_event(time() + (CDM_REBUILD_DEBOUNCE - $elapsed) + 5, CDM_REBUILD_CRON);
        return;
    }
    cdm_trigger_rebuild('cron-debounce');
});

function cdm_trigger_rebuild($source = 'unknown') {
    $token = get_option(CDM_REBUILD_TOKEN, '');
    if (!$token) {
        error_log("[cdm-rebuild] GitHub token nao configurado (Tools > Rebuild Vitrine)");
        return ['ok' => false, 'msg' => 'GitHub token nao configurado'];
    }

    $url = 'https://api.github.com/repos/' . CDM_REBUILD_REPO . '/actions/workflows/' . CDM_REBUILD_WORKFLOW . '/dispatches';

    $response = wp_remote_post($url, [
        'timeout' => 15,
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Accept'        => 'application/vnd.github+json',
            'X-GitHub-Api-Version' => '2022-11-28',
            'Content-Type'  => 'application/json',
        ],
        'body' => json_encode([
            'ref' => CDM_REBUILD_BRANCH,
            'inputs' => ['source' => substr("wp-$source", 0, 100)],
        ]),
    ]);

    if (is_wp_error($response)) {
        error_log('[cdm-rebuild] erro: ' . $response->get_error_message());
        return ['ok' => false, 'msg' => $response->get_error_message()];
    }

    $code = wp_remote_retrieve_response_code($response);
    if ($code !== 204) {
        $body = wp_remote_retrieve_body($response);
        error_log("[cdm-rebuild] HTTP $code: $body");
        return ['ok' => false, 'msg' => "HTTP $code: " . substr($body, 0, 200)];
    }

    delete_transient(CDM_REBUILD_PENDING);
    update_option(CDM_REBUILD_LAST, ['at' => time(), 'source' => $source]);
    error_log("[cdm-rebuild] OK source=$source");
    return ['ok' => true];
}

/* ============================================================
 * 3) ADMIN — settings page + botao forcar
 * ============================================================ */
add_action('admin_menu', function () {
    add_management_page('Rebuild Vitrine', 'Rebuild Vitrine', 'manage_options', 'cdm-rebuild', 'cdm_rebuild_admin_page');
});

function cdm_rebuild_admin_page() {
    if (isset($_POST['cdm_save_token']) && check_admin_referer('cdm_rebuild_save')) {
        $token = sanitize_text_field(trim($_POST['gh_token'] ?? ''));
        if ($token) update_option(CDM_REBUILD_TOKEN, $token);
        echo '<div class="notice notice-success"><p>Token salvo.</p></div>';
    }
    if (isset($_POST['cdm_force']) && check_admin_referer('cdm_rebuild_force')) {
        $res = cdm_trigger_rebuild('manual');
        $cls = $res['ok'] ? 'success' : 'error';
        $msg = $res['ok'] ? 'Rebuild disparado! Veja em github.com/' . CDM_REBUILD_REPO . '/actions' : 'Falha: ' . esc_html($res['msg']);
        echo '<div class="notice notice-' . $cls . '"><p>' . $msg . '</p></div>';
    }

    $has_token = (bool) get_option(CDM_REBUILD_TOKEN, '');
    $pending = get_transient(CDM_REBUILD_PENDING);
    $last = get_option(CDM_REBUILD_LAST, null);
    $next = wp_next_scheduled(CDM_REBUILD_CRON);
    ?>
    <div class="wrap">
        <h1>Rebuild Vitrine Astro (GitHub Actions)</h1>
        <p>Dispara rebuild da vitrine <code>ciadasmochilas.com.br</code> automaticamente quando produtos sao editados (debounce 5min — junta varias edicoes em 1 build).</p>

        <h2>Setup — Token GitHub</h2>
        <ol>
            <li>Cria fine-grained PAT em <a href="https://github.com/settings/personal-access-tokens/new" target="_blank">github.com/settings/personal-access-tokens/new</a></li>
            <li><strong>Repository access:</strong> Only select repositories → <code><?php echo CDM_REBUILD_REPO; ?></code></li>
            <li><strong>Repository permissions:</strong> <code>Actions</code> = Read and write</li>
            <li>Generate token, copia e cola abaixo</li>
        </ol>

        <form method="post">
            <?php wp_nonce_field('cdm_rebuild_save'); ?>
            <table class="form-table">
                <tr>
                    <th><label for="gh_token">GitHub Personal Access Token</label></th>
                    <td>
                        <input type="password" id="gh_token" name="gh_token" value="" class="large-text" placeholder="<?php echo $has_token ? '(token ja salvo — preencha pra atualizar)' : 'github_pat_xxxxx...'; ?>" autocomplete="off" />
                        <p class="description"><?php echo $has_token ? '<span style="color:green">&#10004; Token configurado</span>' : '<span style="color:#cc0">&#9888; Sem token — rebuild nao funciona</span>'; ?></p>
                    </td>
                </tr>
            </table>
            <p><button type="submit" name="cdm_save_token" class="button button-primary">Salvar Token</button></p>
        </form>

        <hr>
        <h2>Status</h2>
        <ul>
            <li><strong>Rebuild pendente:</strong> <?php echo $pending ? 'SIM (desde ' . human_time_diff($pending) . ' atras)' : 'nao'; ?></li>
            <li><strong>Proximo cron:</strong> <?php echo $next ? date('Y-m-d H:i:s', $next) . ' (' . human_time_diff($next) . ')' : 'nao agendado'; ?></li>
            <li><strong>Ultimo rebuild:</strong> <?php echo $last ? date('Y-m-d H:i:s', $last['at']) . ' (' . esc_html($last['source']) . ')' : 'nunca'; ?></li>
            <li><strong>Repo target:</strong> <a href="https://github.com/<?php echo CDM_REBUILD_REPO; ?>/actions" target="_blank"><?php echo CDM_REBUILD_REPO; ?></a></li>
        </ul>

        <h2>Forcar rebuild agora</h2>
        <form method="post">
            <?php wp_nonce_field('cdm_rebuild_force'); ?>
            <p><button type="submit" name="cdm_force" class="button button-secondary"<?php echo $has_token ? '' : ' disabled'; ?>>Disparar rebuild agora</button></p>
        </form>
    </div>
    <?php
}
