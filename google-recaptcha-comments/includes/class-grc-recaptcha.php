<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class GRC_ReCaptcha {
    public function __construct() {
        // Both hooks needed: one for guest visitors, one for logged-in users
        add_action('comment_form_after_fields', array($this, 'add_recaptcha_field'));
        add_action('comment_form_logged_in_after', array($this, 'add_recaptcha_field'));
        add_filter('preprocess_comment', array($this, 'verify_recaptcha'));
    }

    public function add_recaptcha_field(): void {
        $site_key = get_option('grc_site_key');
        if (empty($site_key)) {
            return;
        }
        ?>
        <div class="comment-form-recaptcha">
            <div class="g-recaptcha" data-sitekey="<?php echo esc_attr($site_key); ?>"></div>
        </div>
        <?php
        wp_enqueue_script(
            'google-recaptcha',
            GRC_RECAPTCHA_API_URL,
            array(),
            null,
            true
        );
    }

    /**
     * @param array $commentdata
     * @return array
     */
    public function verify_recaptcha(array $commentdata): array {
        // Skip verification for logged-in users
        if (is_user_logged_in()) {
            return $commentdata;
        }

        $secret_key = get_option('grc_secret_key');
        if (empty($secret_key)) {
            return $commentdata;
        }

        $recaptcha_response = isset($_POST['g-recaptcha-response'])
            ? sanitize_text_field(wp_unslash($_POST['g-recaptcha-response']))
            : '';

        if (empty($recaptcha_response)) {
            wp_die(
                esc_html__('Please complete the ReCaptcha.', 'google-recaptcha-comments'),
                esc_html__('Comment Submission Error', 'google-recaptcha-comments'),
                array('back_link' => true, 'response' => 403)
            );
        }

        $response = wp_remote_post(GRC_RECAPTCHA_VERIFY_URL, array(
            'body' => array(
                'secret'   => $secret_key,
                'response' => $recaptcha_response,
                'remoteip' => sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? '')),
            ),
        ));

        if (is_wp_error($response)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('GRC ReCaptcha: API request failed - ' . $response->get_error_message());
            }
            return $commentdata;
        }

        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);

        if (!is_array($result) || empty($result['success'])) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $error_codes = isset($result['error-codes']) ? implode(', ', $result['error-codes']) : 'unknown';
                error_log('GRC ReCaptcha: Verification failed - ' . $error_codes);
            }
            wp_die(
                esc_html__('ReCaptcha verification failed. Please try again.', 'google-recaptcha-comments'),
                esc_html__('Comment Submission Error', 'google-recaptcha-comments'),
                array('back_link' => true, 'response' => 403)
            );
        }

        return $commentdata;
    }
}
