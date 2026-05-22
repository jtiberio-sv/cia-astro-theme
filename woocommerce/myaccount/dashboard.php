<?php
/**
 * Dashboard my-account — cia-astro override.
 *
 * Remove o texto default WC ("Hello X (not X? Log out) From your account...")
 * e renderiza apenas o nosso cdm-account-welcome + cdm-account-shortcuts via
 * action woocommerce_account_dashboard (handler em inc/woo-hooks.php).
 *
 * Substitui woocommerce/templates/myaccount/dashboard.php
 */

defined('ABSPATH') || exit;

/**
 * My Account dashboard.
 *
 * @hooked cia_astro_account_dashboard - 10 (inc/woo-hooks.php)
 */
do_action('woocommerce_account_dashboard');

/**
 * Deprecated WC core action — mantido pra compat com plugins de terceiros.
 *
 * @deprecated 2.6.0
 */
do_action('woocommerce_before_my_account');

/**
 * @deprecated 2.6.0
 */
do_action('woocommerce_after_my_account');
