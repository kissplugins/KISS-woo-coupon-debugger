<?php
// Minimal bootstrap for running PHPUnit tests without full WordPress/WooCommerce.

// Prevent plugin file from exiting on ABSPATH check
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

// Stub WordPress functions used at definition/initialization time
if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {}
}
if (!function_exists('add_submenu_page')) {
    function add_submenu_page($parent_slug, $page_title, $menu_title, $capability, $menu_slug, $callback = '', $position = null) { return ''; }
}
if (!function_exists('register_setting')) {
    function register_setting($option_group, $option_name, $args = array()) {}
}
if (!function_exists('register_activation_hook')) {
    function register_activation_hook($file, $callback) {}
}
if (!function_exists('register_deactivation_hook')) {
    function register_deactivation_hook($file, $callback) {}
}
if (!function_exists('__')) { function __($text, $domain = null) { return $text; } }
if (!function_exists('esc_html__')) { function esc_html__($text, $domain = null) { return $text; } }
if (!function_exists('esc_attr__')) { function esc_attr__($text, $domain = null) { return $text; } }
if (!function_exists('plugins_url')) { function plugins_url($path = '', $plugin = '') { return $path; } }
if (!function_exists('admin_url')) { function admin_url($path = '') { return $path; } }
if (!function_exists('wp_create_nonce')) { function wp_create_nonce($action = -1) { return 'nonce'; } }
if (!function_exists('wp_get_theme')) { function wp_get_theme() { return new class { public function get($k){ return 'test'; } }; }
}
if (!function_exists('get_bloginfo')) { function get_bloginfo($show = '') { return 'test'; } }
if (!function_exists('wc_price')) { function wc_price($amount){ return (string)$amount; } }
if (!function_exists('wp_set_current_user')) { function wp_set_current_user($id) {} }
if (!function_exists('get_current_user_id')) { function get_current_user_id(){ return 1; } }

// Provide stubs for WooCommerce references used only in signatures or in code paths we won't hit
if (!class_exists('WC_Coupon')) { class WC_Coupon {} }
if (!class_exists('WC_Product')) { class WC_Product {} }
if (!class_exists('WC_Cart')) { class WC_Cart {} }
if (!class_exists('WP_Error')) { class WP_Error { public function get_error_code(){ return 'code'; } public function get_error_message(){ return 'message'; } } }

// Prevent auto-initialization of plugin on plugins_loaded
// by stubbing class_exists('WooCommerce') to false via custom function not needed, add_action is a no-op.

require_once dirname(__DIR__) . '/kiss-coupon-debugger.php';

