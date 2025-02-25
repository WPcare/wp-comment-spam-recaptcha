<?php
class GRC_ReCaptcha {
    public function __construct() {
        add_action('comment_form_after_fields', array($this, 'add_recaptcha_field'));
        add_filter('preprocess_comment', array($this, 'verify_recaptcha'));
    }

    public function add_recaptcha_field() {
        $site_key = get_option('grc_site_key');
        if (empty($site_key)) {
            return;
        }
        ?>
        <div class="comment-form-recaptcha">
            <div class="g-recaptcha" data-sitekey="<?php echo esc_attr($site_key); ?>"></div>
        </div>
        <script src="https://www.google.com/recaptcha/api.js" async defer></script>
        <?php
    }

    public function verify_recaptcha($commentdata) {
        // Skip verification for logged-in users
        if (is_user_logged_in()) {
            return $commentdata;
        }

        $secret_key = get_option('grc_secret_key');
        if (empty($secret_key)) {
            return $commentdata;
        }

        $recaptcha_response = isset($_POST['g-recaptcha-response']) ? $_POST['g-recaptcha-response'] : '';
        
        if (empty($recaptcha_response)) {
            wp_die(__('Please complete the ReCaptcha.', 'google-recaptcha-comments'), 
                   __('Comment Submission Error', 'google-recaptcha-comments'), 
                   array('back_link' => true));
        }

        $verify_url = 'https://www.google.com/recaptcha/api/siteverify';
        $response = wp_remote_post($verify_url, array(
            'body' => array(
                'secret' => $secret_key,
                'response' => $recaptcha_response,
                'remoteip' => $_SERVER['REMOTE_ADDR']
            )
        ));

        if (is_wp_error($response)) {
            return $commentdata;
        }

        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);

        if (!$result['success']) {
            wp_die(__('ReCaptcha verification failed. Please try again.', 'google-recaptcha-comments'), 
                   __('Comment Submission Error', 'google-recaptcha-comments'), 
                   array('back_link' => true));
        }

        return $commentdata;
    }
} 