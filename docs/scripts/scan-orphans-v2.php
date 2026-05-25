<?php
// Scan v2: mais conservador — alem do filename, verifica se attachment_id
// correspondente eh referenciado em _thumbnail_id ou _product_image_gallery
// (galeria WC armazena IDs separados por virgula).

global $wpdb;
$start = microtime(true);

echo "=== FASE 1: Carregando haystack textual ===\n";
$haystack = '';
$haystack .= implode("\n", $wpdb->get_col("SELECT post_content FROM {$wpdb->posts} WHERE post_status != 'trash' AND post_type != 'attachment'"));
$haystack .= "\n" . implode("\n", $wpdb->get_col("SELECT guid FROM {$wpdb->posts} WHERE post_type = 'attachment'"));
$haystack .= "\n" . implode("\n", $wpdb->get_col("SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key NOT IN ('_wp_attached_file','_wp_attachment_metadata') LIMIT 500000"));
$haystack .= "\n" . implode("\n", $wpdb->get_col("SELECT option_value FROM {$wpdb->options} WHERE option_name NOT LIKE '_transient%' AND option_name NOT LIKE '%cache%' LIMIT 5000"));
$haystack .= "\n" . implode("\n", $wpdb->get_col("SELECT meta_value FROM {$wpdb->termmeta} LIMIT 10000"));
echo "Haystack: " . number_format(strlen($haystack)) . " bytes\n";

echo "\n=== FASE 2: Carregando mapa file->attachment_id ===\n";
$file_to_id = [];
$attach_rows = $wpdb->get_results("SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file'");
foreach ($attach_rows as $r) {
    $file_to_id[$r->meta_value] = (int) $r->post_id;
}
echo "Attachments mapeados: " . count($file_to_id) . "\n";

echo "\n=== FASE 3: Carregando IDs referenciados em metas (thumbnail/gallery) ===\n";
$referenced_ids = [];
// _thumbnail_id (featured image)
foreach ($wpdb->get_col("SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id'") as $id) {
    $referenced_ids[(int)$id] = true;
}
// _product_image_gallery (CSV ids)
foreach ($wpdb->get_col("SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_product_image_gallery'") as $csv) {
    foreach (explode(',', $csv) as $id) {
        $id = trim($id);
        if ($id) $referenced_ids[(int)$id] = true;
    }
}
// termmeta thumbnail_id (categoria thumbnails)
foreach ($wpdb->get_col("SELECT meta_value FROM {$wpdb->termmeta} WHERE meta_key = 'thumbnail_id'") as $id) {
    $referenced_ids[(int)$id] = true;
}
echo "IDs referenciados em metas: " . count($referenced_ids) . "\n";

echo "\n=== FASE 4: Scan arquivos ===\n";
$uploads_dir = '/var/www/ciadasmochilas.com.br/htdocs/wp-content/uploads';
$years = ['2019', '2020', '2021'];

$totals = [];
$orphan_list = []; // path completo de cada orfao confirmado

foreach ($years as $year) {
    if (!is_dir("$uploads_dir/$year")) continue;
    $totals[$year] = ['count' => 0, 'orphan' => 0, 'used' => 0, 'orphan_bytes' => 0, 'used_bytes' => 0];

    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$uploads_dir/$year"));
    foreach ($rii as $file) {
        if ($file->isDir()) continue;
        $path = $file->getPathname();
        $name = $file->getBasename();
        $ext = strtolower($file->getExtension());
        if (!in_array($ext, ['jpg','jpeg','png','webp','gif'])) continue;

        $totals[$year]['count']++;
        $size = $file->getSize();

        // .webp gerados: seguem o destino do original (skip aqui, sao incluidos via .original quando deleted)
        if (substr($name, -10) === '.jpg.webp' || substr($name, -11) === '.jpeg.webp' || substr($name, -9) === '.png.webp') {
            // contabiliza no total mas nao no orphan check (vai junto)
            continue;
        }

        $relative = str_replace($uploads_dir . '/', '', $path);
        $is_referenced = false;

        // Check 1: filename no haystack textual
        if (strpos($haystack, $name) !== false) {
            $is_referenced = true;
        } else {
            // Tenta sem suffix de tamanho (image-300x200.jpg -> image.jpg)
            $name_base = preg_replace('/-\d+x\d+(\.[a-z]+)$/i', '$1', $name);
            if ($name_base !== $name && strpos($haystack, $name_base) !== false) {
                $is_referenced = true;
            }
        }

        // Check 2: attachment_id em metas thumbnail/gallery
        if (!$is_referenced && isset($file_to_id[$relative])) {
            $aid = $file_to_id[$relative];
            if (isset($referenced_ids[$aid])) {
                $is_referenced = true;
            }
        }

        // Tambem checa versao sem suffix tamanho (relative original)
        if (!$is_referenced) {
            $relative_base = preg_replace('/-\d+x\d+(\.[a-z]+)$/i', '$1', $relative);
            if ($relative_base !== $relative && isset($file_to_id[$relative_base])) {
                $aid = $file_to_id[$relative_base];
                if (isset($referenced_ids[$aid])) $is_referenced = true;
            }
        }

        if ($is_referenced) {
            $totals[$year]['used']++;
            $totals[$year]['used_bytes'] += $size;
        } else {
            $totals[$year]['orphan']++;
            $totals[$year]['orphan_bytes'] += $size;
            $orphan_list[] = $path;
        }
    }
}

echo "\n=== RESULTADO V2 (2 checks: filename + attachment_id) ===\n";
printf("%-6s %8s %8s %8s %12s %12s\n", "Ano", "Total", "Em uso", "Orfaos", "MB em uso", "MB orfao");
$grand_orphan = 0; $grand_bytes = 0; $grand_used = 0;
foreach ($totals as $year => $t) {
    printf("%-6s %8d %8d %8d %12.1f %12.1f\n",
        $year, $t['count'], $t['used'], $t['orphan'],
        $t['used_bytes'] / 1024 / 1024,
        $t['orphan_bytes'] / 1024 / 1024
    );
    $grand_orphan += $t['orphan'];
    $grand_used += $t['used'];
    $grand_bytes += $t['orphan_bytes'];
}

echo "\n=== TOTAL ORFAOS CONFIRMADOS: $grand_orphan / " . number_format($grand_bytes / 1024 / 1024, 1) . " MB ===\n";
echo "=== TOTAL EM USO (PRESERVAR): $grand_used arquivos ===\n";

// Salva lista pra futura deletion
$orphan_list_file = '/tmp/cdm-orphans-list.txt';
file_put_contents($orphan_list_file, implode("\n", $orphan_list));
echo "\nLista completa salva em: $orphan_list_file\n";

// Estimativa de quantos .webp acompanharao
$webp_count = 0;
$webp_bytes = 0;
foreach ($orphan_list as $p) {
    foreach (['.webp', '.avif'] as $suffix) {
        if (file_exists($p . $suffix)) {
            $webp_count++;
            $webp_bytes += filesize($p . $suffix);
        }
    }
}
echo "\n=== .webp/.avif acompanhantes que serao deletados junto ===\n";
echo "  Arquivos: $webp_count\n";
echo "  Tamanho: " . number_format($webp_bytes / 1024 / 1024, 1) . " MB\n";

echo "\n=== Diff vs scan v1 ===\n";
echo "v1: 3564 orfaos / 104.3 MB\n";
echo "v2: $grand_orphan orfaos / " . number_format($grand_bytes / 1024 / 1024, 1) . " MB\n";
echo "Diferenca = quantos foram resgatados pelo check de attachment_id\n";

echo "\nTempo total: " . round(microtime(true) - $start, 2) . "s\n";
