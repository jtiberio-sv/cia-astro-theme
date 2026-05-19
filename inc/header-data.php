<?php
/**
 * Header data: top categorias com decor (cor confete + emoji + imagem cover).
 *
 * Espelha a logica de src/components/Header.astro do repo Astro.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Retorna as top N categorias WC (parent=0, count>0) com decoracao
 * (cor confete + emoji + imagem cover). Inclui as sub-categorias de cada uma.
 *
 * @param int $top quantas categorias raiz retornar
 * @return array<int, array> categorias ordenadas por count desc, com 'children' e 'decor'.
 */
function cia_astro_get_top_categories($top = 6) {
    $img_base = CIA_ASTRO_URI . '/assets/img';
    $cat_decor_tpl = [
        'mochilas'          => ['image' => '__CIA_IMG__/cat-escolar.webp',       'emoji' => '🎒', 'confetti' => 'cyan'],
        'material-escolar'  => ['image' => '__CIA_IMG__/flatlay-papelaria.webp', 'emoji' => '✏️', 'confetti' => 'pink'],
        'escrita'           => ['image' => '__CIA_IMG__/flatlay-papelaria.webp', 'emoji' => '🖊️', 'confetti' => 'mint'],
        'escritorio'        => ['image' => '__CIA_IMG__/cat-executiva.webp',     'emoji' => '💼', 'confetti' => 'coral'],
        'cadernos'          => ['image' => '__CIA_IMG__/kid-portrait.webp',      'emoji' => '📓', 'confetti' => 'green'],
        'ficharios'         => ['image' => '__CIA_IMG__/flatlay-papelaria.webp', 'emoji' => '📋', 'confetti' => 'yellow'],
        'dia-do-consumidor' => ['image' => '__CIA_IMG__/kid-portrait.webp',      'emoji' => '🎉', 'confetti' => 'purple'],
    ];
    // Substitui o placeholder __CIA_IMG__ pela URL absoluta do diretorio assets/img.
    $cat_decor = [];
    foreach ($cat_decor_tpl as $slug => $d) {
        $d['image'] = str_replace('__CIA_IMG__', $img_base, $d['image']);
        $cat_decor[$slug] = $d;
    }
    $fallback_confetti = ['cyan', 'pink', 'mint', 'coral', 'yellow', 'purple'];
    $fallback_images   = [
        $img_base . '/cat-escolar.webp',
        $img_base . '/flatlay-papelaria.webp',
        $img_base . '/cat-executiva.webp',
        $img_base . '/kid-portrait.webp',
    ];

    $all = get_terms([
        'taxonomy'   => 'product_cat',
        'hide_empty' => true,
        'parent'     => 0,
    ]);

    if (is_wp_error($all) || !is_array($all)) {
        return [];
    }

    usort($all, function ($a, $b) { return $b->count - $a->count; });
    $top_cats = array_slice($all, 0, $top);

    $out = [];
    foreach ($top_cats as $i => $cat) {
        $children = get_terms([
            'taxonomy'   => 'product_cat',
            'hide_empty' => true,
            'parent'     => $cat->term_id,
        ]);
        if (is_wp_error($children)) { $children = []; }
        usort($children, function ($a, $b) { return $b->count - $a->count; });

        $decor = $cat_decor[$cat->slug] ?? [
            'image'    => $fallback_images[$i % count($fallback_images)],
            'emoji'    => '🛒',
            'confetti' => $fallback_confetti[$i % count($fallback_confetti)],
        ];

        $out[] = [
            'term'     => $cat,
            'name'     => $cat->name,
            'slug'     => $cat->slug,
            'count'    => $cat->count,
            'url'      => '/categoria/' . $cat->slug,
            'children' => $children,
            'decor'    => $decor,
        ];
    }
    return $out;
}
