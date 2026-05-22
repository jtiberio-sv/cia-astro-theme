<?php
/**
 * cia-astro — bootstrap.
 *
 * Carrega modulos do tema na ordem: setup → enqueue → woo-config → woo-hooks.
 */

if (!defined('ABSPATH')) {
    exit;
}

define('CIA_ASTRO_VERSION', '0.1.0');
define('CIA_ASTRO_DIR', get_stylesheet_directory());
define('CIA_ASTRO_URI', get_stylesheet_directory_uri());

require_once CIA_ASTRO_DIR . '/inc/theme-setup.php';
require_once CIA_ASTRO_DIR . '/inc/enqueue.php';
require_once CIA_ASTRO_DIR . '/inc/urls.php';
require_once CIA_ASTRO_DIR . '/inc/url-rewrites.php';
require_once CIA_ASTRO_DIR . '/inc/woo-config.php';
require_once CIA_ASTRO_DIR . '/inc/woo-hooks.php';
require_once CIA_ASTRO_DIR . '/inc/newsletter.php';
require_once CIA_ASTRO_DIR . '/inc/products-defaults.php';
