<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class GRC_ReCaptcha {
    public function __construct() {
        add_action('comment_form_after_fields', array($this, 'add_recaptcha_field'));
        add_action('comment_form_logged_in_after', array($this, 'add_recaptcha_field_logged_in'));
        add_filter('preprocess_comment', array($this, 'verify_recaptcha'));
    }

    /**
     * Render reCAPTCHA for guest visitors.
     */
    public function add_recaptcha_field(): void {
        $this->render_recaptcha();
    }

    /**
     * Render reCAPTCHA for logged-in users only if skip is disabled.
     */
    public function add_recaptcha_field_logged_in(): void {
        $skip = get_option('grc_skip_logged_in', '1');
        if ($skip === '1') {
            return;
        }
        $this->render_recaptcha();
    }

    /**
     * Output the reCAPTCHA widget or hidden field depending on version.
     */
    private function render_recaptcha(): void {
        $site_key = get_option('grc_site_key');
        if (empty($site_key)) {
            return;
        }

        $version = get_option('grc_recaptcha_version', 'v2');

        // Nonce for CSRF protection
        wp_nonce_field('grc_comment_nonce', 'grc_nonce');

        if ($version === 'v3') {
            $this->render_v3($site_key);
        } else {
            $this->render_v2($site_key);
        }
    }

    /**
     * Render reCAPTCHA v2 checkbox widget.
     */
    private function render_v2(string $site_key): void {
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
     * Render reCAPTCHA v3 invisible widget.
     */
    private function render_v3(string $site_key): void {
        ?>
        <input type="hidden" id="grc-recaptcha-response" name="g-recaptcha-response" value="">
        <?php
        wp_enqueue_script(
            'google-recaptcha',
            GRC_RECAPTCHA_API_URL . '?render=' . esc_attr($site_key),
            array(),
            null,
            true
        );

        wp_add_inline_script('google-recaptcha', sprintf(
            'grecaptcha.ready(function(){' .
                'var form=document.getElementById("commentform");' .
                'if(form){' .
                    'form.addEventListener("submit",function(e){' .
                        'e.preventDefault();' .
                        'grecaptcha.execute(%s,{action:"comment"}).then(function(token){' .
                            'document.getElementById("grc-recaptcha-response").value=token;' .
                            'form.submit();' .
                        '});' .
                    '});' .
                '}' .
            '});',
            wp_json_encode($site_key)
        ));
    }

    /**
     * Verify reCAPTCHA on comment submission.
     *
     * @param array $commentdata
     * @return array
     */
    public function verify_recaptcha(array $commentdata): array {
        // Skip verification for logged-in users if setting enabled
        $skip_logged_in = get_option('grc_skip_logged_in', '1');
        if ($skip_logged_in === '1' && is_user_logged_in()) {
            return $commentdata;
        }

        $secret_key = get_option('grc_secret_key');
        if (empty($secret_key)) {
            return $commentdata;
        }

        // Verify nonce
        if (!isset($_POST['grc_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['grc_nonce'])), 'grc_comment_nonce')) {
            wp_die(
                esc_html__('Security check failed. Please try again.', 'google-recaptcha-comments'),
                esc_html__('Comment Submission Error', 'google-recaptcha-comments'),
                array('back_link' => true, 'response' => 403)
            );
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

        $fail_action = get_option('grc_fail_action', 'open');

        if (is_wp_error($response)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('GRC ReCaptcha: API request failed - ' . $response->get_error_message());
            }
            if ($fail_action === 'closed') {
                wp_die(
                    esc_html__('ReCaptcha verification is temporarily unavailable. Please try again later.', 'google-recaptcha-comments'),
                    esc_html__('Comment Submission Error', 'google-recaptcha-comments'),
                    array('back_link' => true, 'response' => 503)
                );
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

            // Track failed attempt for rate limiting
            if (class_exists('GRC_Rate_Limiter')) {
                GRC_Rate_Limiter::record_failure(
                    sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? ''))
                );
            }

            wp_die(
                esc_html__('ReCaptcha verification failed. Please try again.', 'google-recaptcha-comments'),
                esc_html__('Comment Submission Error', 'google-recaptcha-comments'),
                array('back_link' => true, 'response' => 403)
            );
        }

        // For v3, check the score threshold
        $version = get_option('grc_recaptcha_version', 'v2');
        if ($version === 'v3') {
            $threshold = (float) get_option('grc_v3_score_threshold', '0.5');
            $score = isset($result['score']) ? (float) $result['score'] : 0.0;

            if ($score < $threshold) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log(sprintf('GRC ReCaptcha: v3 score too low - %.1f (threshold: %.1f)', $score, $threshold));
                }

                if (class_exists('GRC_Rate_Limiter')) {
                    GRC_Rate_Limiter::record_failure(
                        sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? ''))
                    );
                }

                wp_die(
                    esc_html__('Your comment was flagged as potential spam. Please try again.', 'google-recaptcha-comments'),
                    esc_html__('Comment Submission Error', 'google-recaptcha-comments'),
                    array('back_link' => true, 'response' => 403)
                );
            }
        }

        return $commentdata;
    }
}
