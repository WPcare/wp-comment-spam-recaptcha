<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class GRC_Settings {
    public function __construct() {
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_notices', array($this, 'show_missing_keys_notice'));
    }

    public function add_settings_page(): void {
        add_options_page(
            __('Google ReCaptcha Settings', 'google-recaptcha-comments'),
            __('Google ReCaptcha', 'google-recaptcha-comments'),
            'manage_options',
            'google-recaptcha-settings',
            array($this, 'render_settings_page')
        );
    }

    public function register_settings(): void {
        register_setting('grc_settings', 'grc_site_key', array(
            'sanitize_callback' => 'sanitize_text_field',
        ));
        register_setting('grc_settings', 'grc_secret_key', array(
            'sanitize_callback' => 'sanitize_text_field',
        ));
    }

    public function show_missing_keys_notice(): void {
        $site_key = get_option('grc_site_key');
        $secret_key = get_option('grc_secret_key');

        if (!empty($site_key) && !empty($secret_key)) {
            return;
        }

        $screen = get_current_screen();
        if ($screen && $screen->id === 'settings_page_google-recaptcha-settings') {
            return;
        }

        $settings_url = admin_url('options-general.php?page=google-recaptcha-settings');
        printf(
            '<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div>',
            esc_html__('Google ReCaptcha for Comments: API keys are not configured. Comment spam protection is inactive.', 'google-recaptcha-comments'),
            esc_url($settings_url),
            esc_html__('Configure now', 'google-recaptcha-comments')
        );
    }

    public function render_settings_page(): void {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Google ReCaptcha Settings', 'google-recaptcha-comments'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('grc_settings');
                do_settings_sections('grc_settings');
                ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="grc_site_key"><?php esc_html_e('Site Key', 'google-recaptcha-comments'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="grc_site_key" name="grc_site_key"
                                value="<?php echo esc_attr(get_option('grc_site_key')); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="grc_secret_key"><?php esc_html_e('Secret Key', 'google-recaptcha-comments'); ?></label>
                        </th>
                        <td>
                            <input type="password" id="grc_secret_key" name="grc_secret_key"
                                value="<?php echo esc_attr(get_option('grc_secret_key')); ?>" class="regular-text">
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
