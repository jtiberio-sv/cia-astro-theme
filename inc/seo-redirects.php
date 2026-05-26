<?php
/**
 * Plugin Name: CDM 301 Redirects
 * Description: Redirects 301 manuais (slug changes, URL legacy etc).
 */
if (!defined('ABSPATH')) exit;
add_action('template_redirect', function() {
    $path = trim($_SERVER['REQUEST_URI'] ?? '', '/');
    $redirects = [
        'categoria/dia-do-consumidor' => '/categoria/ofertas/',
    ];
    foreach ($redirects as $from => $to) {
        if ($path === $from || strpos($path, $from . '/') === 0) {
            wp_redirect(home_url($to), 301);
            exit;
        }
    }
});
