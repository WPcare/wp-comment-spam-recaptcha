<?php
class GRC_Settings {
    public function __construct() {
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function add_settings_page() {
        add_options_page(
            __('Google ReCaptcha Settings', 'google-recaptcha-comments'),
            __('Google ReCaptcha', 'google-recaptcha-comments'),
            'manage_options',
            'google-recaptcha-settings',
            array($this, 'render_settings_page')
        );
    }

    public function register_settings() {
        register_setting('grc_settings', 'grc_site_key');
        register_setting('grc_settings', 'grc_secret_key');
    }

    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Google ReCaptcha Settings', 'google-recaptcha-comments'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('grc_settings');
                do_settings_sections('grc_settings');
                ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="grc_site_key"><?php _e('Site Key', 'google-recaptcha-comments'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="grc_site_key" name="grc_site_key" 
                                value="<?php echo esc_attr(get_option('grc_site_key')); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="grc_secret_key"><?php _e('Secret Key', 'google-recaptcha-comments'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="grc_secret_key" name="grc_secret_key" 
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