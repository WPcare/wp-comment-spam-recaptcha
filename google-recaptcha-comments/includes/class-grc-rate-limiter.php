<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class GRC_Rate_Limiter {

    private const MAX_FAILURES = 5;
    private const LOCKOUT_DURATION = 900; // 15 minutes
    private const TRANSIENT_PREFIX = 'grc_fail_';

    public function __construct() {
        add_filter('preprocess_comment', array($this, 'check_rate_limit'), 1);
    }

    /**
     * Check if the IP is currently rate-limited before reCAPTCHA verification.
     *
     * @param array $commentdata
     * @return array
     */
    public function check_rate_limit(array $commentdata): array {
        $skip_logged_in = get_option('grc_skip_logged_in', '1');
        if ($skip_logged_in === '1' && is_user_logged_in()) {
            return $commentdata;
        }

        $ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? ''));
        if (empty($ip)) {
            return $commentdata;
        }

        $failures = self::get_failures($ip);

        if ($failures >= self::MAX_FAILURES) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('GRC ReCaptcha: Rate limited IP - ' . $ip);
            }
            wp_die(
                esc_html__('Too many failed attempts. Please try again later.', 'google-recaptcha-comments'),
                esc_html__('Comment Submission Error', 'google-recaptcha-comments'),
                array('back_link' => true, 'response' => 429)
            );
        }

        return $commentdata;
    }

    /**
     * Record a failed reCAPTCHA attempt for an IP.
     */
    public static function record_failure(string $ip): void {
        if (empty($ip)) {
            return;
        }

        $key = self::TRANSIENT_PREFIX . md5($ip);
        $failures = (int) get_transient($key);
        set_transient($key, $failures + 1, self::LOCKOUT_DURATION);
    }

    /**
     * Get the number of failures for an IP.
     */
    private static function get_failures(string $ip): int {
        $key = self::TRANSIENT_PREFIX . md5($ip);
        return (int) get_transient($key);
    }
}
