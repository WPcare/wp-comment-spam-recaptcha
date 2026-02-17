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
        add_action('wp_ajax_grc_test_connection', array($this, 'ajax_test_connection'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
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
        register_setting('grc_settings', 'grc_recaptcha_version', array(
            'sanitize_callback' => array($this, 'sanitize_version'),
        ));
        register_setting('grc_settings', 'grc_v3_score_threshold', array(
            'sanitize_callback' => array($this, 'sanitize_score_threshold'),
        ));
        register_setting('grc_settings', 'grc_skip_logged_in', array(
            'sanitize_callback' => array($this, 'sanitize_checkbox'),
        ));
        register_setting('grc_settings', 'grc_fail_action', array(
            'sanitize_callback' => array($this, 'sanitize_fail_action'),
        ));
    }

    public function sanitize_version(string $value): string {
        return in_array($value, array('v2', 'v3'), true) ? $value : 'v2';
    }

    public function sanitize_score_threshold(string $value): string {
        $score = (float) $value;
        if ($score < 0.0 || $score > 1.0) {
            $score = 0.5;
        }
        return (string) $score;
    }

    public function sanitize_checkbox(string $value): string {
        return $value === '1' ? '1' : '0';
    }

    public function sanitize_fail_action(string $value): string {
        return in_array($value, array('open', 'closed'), true) ? $value : 'open';
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

    public function enqueue_admin_scripts(string $hook): void {
        if ($hook !== 'settings_page_google-recaptcha-settings') {
            return;
        }

        wp_enqueue_script(
            'grc-admin',
            GRC_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            GRC_VERSION,
            true
        );

        wp_localize_script('grc-admin', 'grcAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('grc_test_connection'),
            'strings' => array(
                'testing'  => __('Testing...', 'google-recaptcha-comments'),
                'success'  => __('Connection successful! Your API keys are valid.', 'google-recaptcha-comments'),
                'failed'   => __('Connection failed. Please check your secret key.', 'google-recaptcha-comments'),
                'error'    => __('Could not reach the reCAPTCHA API. Please try again.', 'google-recaptcha-comments'),
                'noKeys'   => __('Please enter both Site Key and Secret Key first.', 'google-recaptcha-comments'),
            ),
        ));
    }

    public function ajax_test_connection(): void {
        check_ajax_referer('grc_test_connection', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized.', 'google-recaptcha-comments')));
        }

        $secret_key = isset($_POST['secret_key']) ? sanitize_text_field(wp_unslash($_POST['secret_key'])) : '';

        if (empty($secret_key)) {
            wp_send_json_error(array('message' => __('Secret key is required.', 'google-recaptcha-comments')));
        }

        // Send a test request with an empty response token — Google will return
        // error codes but a valid API response confirms the key format is accepted
        $response = wp_remote_post(GRC_RECAPTCHA_VERIFY_URL, array(
            'body' => array(
                'secret'   => $secret_key,
                'response' => 'test',
            ),
        ));

        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()));
        }

        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);

        if (!is_array($result)) {
            wp_send_json_error(array('message' => __('Invalid response from reCAPTCHA API.', 'google-recaptcha-comments')));
        }

        // If we get 'invalid-input-secret', the key itself is wrong
        if (isset($result['error-codes']) && in_array('invalid-input-secret', $result['error-codes'], true)) {
            wp_send_json_error(array('message' => __('Invalid secret key. Please check and try again.', 'google-recaptcha-comments')));
        }

        // Any other response (including missing-input-response) means the key is valid
        wp_send_json_success(array('message' => __('Connection successful! Your API keys are valid.', 'google-recaptcha-comments')));
    }

    private function get_status(): array {
        $site_key = get_option('grc_site_key');
        $secret_key = get_option('grc_secret_key');
        $version = get_option('grc_recaptcha_version', 'v2');

        if (empty($site_key) || empty($secret_key)) {
            return array(
                'status'  => 'inactive',
                'class'   => 'notice-warning',
                'message' => __('Inactive — API keys are not configured.', 'google-recaptcha-comments'),
            );
        }

        $version_label = $version === 'v3' ? 'v3 (Invisible)' : 'v2 (Checkbox)';
        return array(
            'status'  => 'active',
            'class'   => 'notice-success',
            'message' => sprintf(
                /* translators: %s: reCAPTCHA version label */
                __('Active — reCAPTCHA %s is protecting your comment forms.', 'google-recaptcha-comments'),
                $version_label
            ),
        );
    }

    public function render_settings_page(): void {
        $status = $this->get_status();
        $version = get_option('grc_recaptcha_version', 'v2');
        $skip_logged_in = get_option('grc_skip_logged_in', '1');
        $fail_action = get_option('grc_fail_action', 'open');
        $score_threshold = get_option('grc_v3_score_threshold', '0.5');
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Google ReCaptcha Settings', 'google-recaptcha-comments'); ?></h1>

            <div class="notice <?php echo esc_attr($status['class']); ?> inline" style="margin: 15px 0;">
                <p><strong><?php esc_html_e('Status:', 'google-recaptcha-comments'); ?></strong> <?php echo esc_html($status['message']); ?></p>
            </div>

            <form method="post" action="options.php">
                <?php
                settings_fields('grc_settings');
                do_settings_sections('grc_settings');
                ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="grc_recaptcha_version"><?php esc_html_e('ReCaptcha Version', 'google-recaptcha-comments'); ?></label>
                        </th>
                        <td>
                            <select id="grc_recaptcha_version" name="grc_recaptcha_version">
                                <option value="v2" <?php selected($version, 'v2'); ?>><?php esc_html_e('v2 — "I\'m not a robot" Checkbox', 'google-recaptcha-comments'); ?></option>
                                <option value="v3" <?php selected($version, 'v3'); ?>><?php esc_html_e('v3 — Invisible (Score-based)', 'google-recaptcha-comments'); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e('v2 shows a checkbox; v3 runs invisibly and scores each request.', 'google-recaptcha-comments'); ?></p>
                        </td>
                    </tr>
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
                            <button type="button" id="grc-test-connection" class="button button-secondary">
                                <?php esc_html_e('Test Connection', 'google-recaptcha-comments'); ?>
                            </button>
                            <span id="grc-test-result" style="margin-left: 10px;"></span>
                        </td>
                    </tr>
                    <tr id="grc-v3-threshold-row" style="<?php echo $version !== 'v3' ? 'display:none;' : ''; ?>">
                        <th scope="row">
                            <label for="grc_v3_score_threshold"><?php esc_html_e('Score Threshold (v3)', 'google-recaptcha-comments'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="grc_v3_score_threshold" name="grc_v3_score_threshold"
                                value="<?php echo esc_attr($score_threshold); ?>"
                                min="0" max="1" step="0.1" style="width: 80px;">
                            <p class="description"><?php esc_html_e('Score between 0.0 and 1.0. Lower values are more permissive. Default: 0.5', 'google-recaptcha-comments'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <?php esc_html_e('Logged-in Users', 'google-recaptcha-comments'); ?>
                        </th>
                        <td>
                            <label for="grc_skip_logged_in">
                                <input type="checkbox" id="grc_skip_logged_in" name="grc_skip_logged_in" value="1"
                                    <?php checked($skip_logged_in, '1'); ?>>
                                <?php esc_html_e('Skip reCAPTCHA verification for logged-in users', 'google-recaptcha-comments'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="grc_fail_action"><?php esc_html_e('On API Failure', 'google-recaptcha-comments'); ?></label>
                        </th>
                        <td>
                            <select id="grc_fail_action" name="grc_fail_action">
                                <option value="open" <?php selected($fail_action, 'open'); ?>><?php esc_html_e('Allow comment (fail-open)', 'google-recaptcha-comments'); ?></option>
                                <option value="closed" <?php selected($fail_action, 'closed'); ?>><?php esc_html_e('Block comment (fail-closed)', 'google-recaptcha-comments'); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e('What happens when the reCAPTCHA API is unreachable.', 'google-recaptcha-comments'); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
