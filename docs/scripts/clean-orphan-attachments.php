<?php
// Limpa attachments WP cujo _wp_attached_file aponta pra arquivo inexistente
// (movido pra quarantine). Limpa post + postmeta correspondente.

global $wpdb;
$start = microtime(true);

$uploads_dir = '/var/www/ciadasmochilas.com.br/htdocs/wp-content/uploads';

echo "Carregando attachment paths...\n";
$rows = $wpdb->get_results("
  SELECT pm.post_id, pm.meta_value AS path, p.post_title
  FROM {$wpdb->postmeta} pm
  JOIN {$wpdb->posts} p ON p.ID = pm.post_id AND p.post_type = 'attachment'
  WHERE pm.meta_key = '_wp_attached_file'
  AND (pm.meta_value LIKE '2019/%' OR pm.meta_value LIKE '2020/%' OR pm.meta_value LIKE '2021/%')
");
echo "Attachments 2019-2021: " . count($rows) . "\n";

$orphan_ids = [];
$still_existing = 0;
foreach ($rows as $r) {
    $full = $uploads_dir . '/' . $r->path;
    if (!file_exists($full)) {
        $orphan_ids[] = (int) $r->post_id;
    } else {
        $still_existing++;
    }
}

echo "Attachments com file inexistente: " . count($orphan_ids) . "\n";
echo "Attachments com file preservado: $still_existing\n";

if (empty($orphan_ids)) {
    echo "Nada a limpar.\n";
    exit;
}

// Deleta em batches via SQL direto pra eficiencia
echo "\nDeletando attachments orfaos em batches de 500...\n";
$batches = array_chunk($orphan_ids, 500);
$total_deleted = 0;
$total_meta = 0;

foreach ($batches as $i => $batch) {
    $ids = implode(',', $batch);

    $del_posts = $wpdb->query("DELETE FROM {$wpdb->posts} WHERE ID IN ($ids)");
    $del_meta  = $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE post_id IN ($ids)");

    $total_deleted += $del_posts;
    $total_meta += $del_meta;

    echo "  Batch " . ($i+1) . "/" . count($batches) . ": posts=$del_posts meta=$del_meta\n";
}

echo "\n=== Resultado ===\n";
echo "Posts (attachments) deletados: $total_deleted\n";
echo "Postmeta rows deletadas:       $total_meta\n";
echo "Tempo: " . round(microtime(true) - $start, 2) . "s\n";

// Verifica que ainda ha attachments validos
$remaining = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment'");
echo "\nAttachments restantes no banco: $remaining\n";
