<?php
/**
 * Plugin Name: Google ReCaptcha for Comments
 * Description: Adds Google ReCaptcha v2 protection to WordPress comments
 * Version: 1.0.0
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

// Define plugin constants
define('GRC_VERSION', '1.0.0');
define('GRC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GRC_PLUGIN_URL', plugin_dir_url(__FILE__));

// Add settings page
require_once GRC_PLUGIN_DIR . 'includes/class-grc-settings.php';

// Add ReCaptcha functionality
require_once GRC_PLUGIN_DIR . 'includes/class-grc-recaptcha.php';

// Initialize plugin
function grc_init() {
    new GRC_Settings();
    new GRC_ReCaptcha();
}
add_action('plugins_loaded', 'grc_init'); 