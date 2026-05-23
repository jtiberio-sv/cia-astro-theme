<?php
/**
 * cia-astro — bootstrap.
 *
 * Carrega modulos do tema na ordem: setup → enqueue → woo-config → woo-hooks.
 */

if (!defined('ABSPATH')) {
    exit;
}

define('CIA_ASTRO_VERSION', '0.2.0');
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
require_once CIA_ASTRO_DIR . '/inc/wishlist.php';
require_once CIA_ASTRO_DIR . '/inc/emails.php';
require_once CIA_ASTRO_DIR . '/inc/cart-abandonment.php';
require_once CIA_ASTRO_DIR . '/inc/express-checkout.php';
require_once CIA_ASTRO_DIR . '/inc/login-google.php';

// Admin-only — carrega so quando estamos no wp-admin pra economizar
// parsing PHP em requests do front (vitrine e WC frontend nao precisam).
if (is_admin()) {
    require_once CIA_ASTRO_DIR . '/inc/admin-ui.php';
}
