<?php
declare(strict_types=1);
/**
 * Plugin Name: Google ReCaptcha for Comments
 * Description: Adds Google ReCaptcha v2/v3 protection to WordPress comments
 * Version: 2.0.1
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Author: WP Care
 * Author URI: https://wpcare.ie
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: google-recaptcha-comments
 */

// Prevent direct access to this file
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants (guarded against redefinition)
if (!defined('GRC_VERSION')) {
    define('GRC_VERSION', '2.0.1');
}
if (!defined('GRC_PLUGIN_DIR')) {
    define('GRC_PLUGIN_DIR', plugin_dir_path(__FILE__));
}
if (!defined('GRC_PLUGIN_URL')) {
    define('GRC_PLUGIN_URL', plugin_dir_url(__FILE__));
}
if (!defined('GRC_RECAPTCHA_VERIFY_URL')) {
    define('GRC_RECAPTCHA_VERIFY_URL', 'https://www.google.com/recaptcha/api/siteverify');
}
if (!defined('GRC_RECAPTCHA_API_URL')) {
    define('GRC_RECAPTCHA_API_URL', 'https://www.google.com/recaptcha/api.js');
}
if (!defined('GRC_PLUGIN_BASENAME')) {
    define('GRC_PLUGIN_BASENAME', plugin_basename(__FILE__));
}

// Add settings page
require_once GRC_PLUGIN_DIR . 'includes/class-grc-settings.php';

// Add ReCaptcha functionality
require_once GRC_PLUGIN_DIR . 'includes/class-grc-recaptcha.php';

// Add rate limiting
require_once GRC_PLUGIN_DIR . 'includes/class-grc-rate-limiter.php';

/**
 * Store plugin instances for extensibility.
 *
 * @return object
 */
function grc_instance(): object {
    static $instance = null;
    if ($instance === null) {
        $instance = (object) array(
            'settings'     => new GRC_Settings(),
            'recaptcha'    => new GRC_ReCaptcha(),
            'rate_limiter' => new GRC_Rate_Limiter(),
        );
    }
    return $instance;
}

// Initialize plugin
function grc_init(): void {
    load_plugin_textdomain('google-recaptcha-comments', false, dirname(GRC_PLUGIN_BASENAME) . '/languages');
    grc_instance();
}
add_action('plugins_loaded', 'grc_init');

// Add settings link on Plugins page
function grc_plugin_action_links(array $links): array {
    $settings_link = sprintf(
        '<a href="%s">%s</a>',
        esc_url(admin_url('options-general.php?page=google-recaptcha-settings')),
        esc_html__('Settings', 'google-recaptcha-comments')
    );
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'grc_plugin_action_links');

// Activation hook — check requirements and set defaults
register_activation_hook(__FILE__, 'grc_activate');
function grc_activate(): void {
    if (version_compare(PHP_VERSION, '7.4', '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            esc_html__('Google ReCaptcha for Comments requires PHP 7.4 or higher.', 'google-recaptcha-comments'),
            esc_html__('Plugin Activation Error', 'google-recaptcha-comments'),
            array('back_link' => true)
        );
    }

    if (version_compare(get_bloginfo('version'), '5.0', '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            esc_html__('Google ReCaptcha for Comments requires WordPress 5.0 or higher.', 'google-recaptcha-comments'),
            esc_html__('Plugin Activation Error', 'google-recaptcha-comments'),
            array('back_link' => true)
        );
    }

    // Set default options if they don't exist
    add_option('grc_site_key', '');
    add_option('grc_secret_key', '');
    add_option('grc_recaptcha_version', 'v2');
    add_option('grc_v3_score_threshold', '0.5');
    add_option('grc_skip_logged_in', '1');
    add_option('grc_fail_action', 'open');
}

// Clean up options on uninstall
register_uninstall_hook(__FILE__, 'grc_uninstall');
function grc_uninstall(): void {
    delete_option('grc_site_key');
    delete_option('grc_secret_key');
    delete_option('grc_recaptcha_version');
    delete_option('grc_v3_score_threshold');
    delete_option('grc_skip_logged_in');
    delete_option('grc_fail_action');
}
