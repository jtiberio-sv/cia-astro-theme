<?php
/**
 * Defaults de peso/dimensoes para produtos sem essa info preenchida.
 *
 * Aplica-se automaticamente quando produto e salvo (admin manual, REST API
 * do Tiny ERP, importacao, etc). Se peso/dim ja existirem nao mexe.
 *
 * Defaults por categoria (peso em kg, dimensoes em cm — L x W x H):
 *   mochilas          0.8 kg  40x30x15
 *   material-escolar  0.3 kg  22x16x3
 *   cadernos          0.4 kg  28x20x2
 *   escrita           0.15 kg 20x10x2
 *   escritorio        0.5 kg  30x20x5
 *   ficharios         0.6 kg  30x25x3
 *   default           0.3 kg  25x18x3
 *
 * Garante que o calculador Melhor Envio sempre tenha medidas pra cotar,
 * evitando o cart cair no Flat Rate fallback desnecessariamente.
 *
 * Aplica em produto E suas variations (variable products).
 */

if (!defined('ABSPATH')) {
    exit;
}

function cia_astro_default_dims_for_categories($cats) {
    $by_cat = [
        'mochilas'          => [0.8,  40, 30, 15],
        'material-escolar'  => [0.3,  22, 16, 3],
        'cadernos'          => [0.4,  28, 20, 2],
        'escrita'           => [0.15, 20, 10, 2],
        'escritorio'        => [0.5,  30, 20, 5],
        'ficharios'         => [0.6,  30, 25, 3],
    ];
    if (!is_array($cats) || empty($cats)) {
        return [0.3, 25, 18, 3];
    }
    foreach ($cats as $cat) {
        $slug = is_object($cat) ? $cat->slug : (string) $cat;
        if (isset($by_cat[$slug])) {
            return $by_cat[$slug];
        }
        if (is_object($cat) && isset($cat->term_id)) {
            $ancestors = get_ancestors($cat->term_id, 'product_cat');
            foreach ($ancestors as $aid) {
                $aterm = get_term($aid, 'product_cat');
                if ($aterm && !is_wp_error($aterm) && isset($by_cat[$aterm->slug])) {
                    return $by_cat[$aterm->slug];
                }
            }
        }
    }
    return [0.3, 25, 18, 3];
}

/**
 * Aplica defaults se peso OU qualquer dimensao estiverem vazios.
 * Hook: woocommerce_new_product, woocommerce_update_product (cobre Tiny REST,
 * admin save, importacao CSV, qualquer fluxo padrao WC).
 */
function cia_astro_apply_product_defaults($product_id) {
    $product = wc_get_product($product_id);
    if (!$product) return;

    $w  = $product->get_weight();
    $l  = $product->get_length();
    $wi = $product->get_width();
    $h  = $product->get_height();
    $missing = empty($w) || (float)$w === 0.0
            || empty($l) || empty($wi) || empty($h);
    if (!$missing) return;

    $cats = get_the_terms($product_id, 'product_cat');
    $dims = cia_astro_default_dims_for_categories($cats);

    if (empty($w) || (float)$w === 0.0) $product->set_weight((string) $dims[0]);
    if (empty($l))  $product->set_length((string) $dims[1]);
    if (empty($wi)) $product->set_width((string)  $dims[2]);
    if (empty($h))  $product->set_height((string) $dims[3]);

    remove_action('woocommerce_new_product',    'cia_astro_apply_product_defaults');
    remove_action('woocommerce_update_product', 'cia_astro_apply_product_defaults');
    $product->save();
    add_action('woocommerce_new_product',    'cia_astro_apply_product_defaults', 20);
    add_action('woocommerce_update_product', 'cia_astro_apply_product_defaults', 20);
}
add_action('woocommerce_new_product',    'cia_astro_apply_product_defaults', 20);
add_action('woocommerce_update_product', 'cia_astro_apply_product_defaults', 20);

/**
 * Variations: herda do parent, senao aplica default da categoria do parent.
 */
function cia_astro_apply_variation_defaults($variation_id) {
    $variation = wc_get_product($variation_id);
    if (!$variation || $variation->get_type() !== 'variation') return;

    $w  = $variation->get_weight();
    $l  = $variation->get_length();
    $wi = $variation->get_width();
    $h  = $variation->get_height();
    $missing = empty($w) || (float)$w === 0.0
            || empty($l) || empty($wi) || empty($h);
    if (!$missing) return;

    $parent_id = $variation->get_parent_id();
    $parent    = wc_get_product($parent_id);
    if ($parent) {
        $pw  = $parent->get_weight();
        $pl  = $parent->get_length();
        $pwi = $parent->get_width();
        $ph  = $parent->get_height();
        if (empty($w)  && !empty($pw))  $variation->set_weight((string) $pw);
        if (empty($l)  && !empty($pl))  $variation->set_length((string) $pl);
        if (empty($wi) && !empty($pwi)) $variation->set_width((string)  $pwi);
        if (empty($h)  && !empty($ph))  $variation->set_height((string) $ph);
    }

    $w  = $variation->get_weight();
    $l  = $variation->get_length();
    $wi = $variation->get_width();
    $h  = $variation->get_height();
    if (empty($w) || empty($l) || empty($wi) || empty($h)) {
        $cats = $parent ? get_the_terms($parent_id, 'product_cat') : null;
        $dims = cia_astro_default_dims_for_categories($cats);
        if (empty($w))  $variation->set_weight((string) $dims[0]);
        if (empty($l))  $variation->set_length((string) $dims[1]);
        if (empty($wi)) $variation->set_width((string)  $dims[2]);
        if (empty($h))  $variation->set_height((string) $dims[3]);
    }

    remove_action('woocommerce_new_product_variation',    'cia_astro_apply_variation_defaults');
    remove_action('woocommerce_update_product_variation', 'cia_astro_apply_variation_defaults');
    $variation->save();
    add_action('woocommerce_new_product_variation',    'cia_astro_apply_variation_defaults', 20);
    add_action('woocommerce_update_product_variation', 'cia_astro_apply_variation_defaults', 20);
}
add_action('woocommerce_new_product_variation',    'cia_astro_apply_variation_defaults', 20);
add_action('woocommerce_update_product_variation', 'cia_astro_apply_variation_defaults', 20);
