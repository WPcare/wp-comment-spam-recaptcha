# Google ReCaptcha for Comments

A lightweight WordPress plugin that adds Google reCAPTCHA v2 or v3 protection to your comment forms, helping prevent spam submissions.

## Features

- **reCAPTCHA v2** — "I'm not a robot" checkbox
- **reCAPTCHA v3** — Invisible, score-based verification with configurable threshold
- Admin settings page under **Settings > Google ReCaptcha**
- **Status indicator** showing whether protection is active
- **Test Connection** button to verify API keys from the settings page
- Configurable behavior for logged-in users (skip or verify)
- **Fail-open / fail-closed** setting for API outages
- **Rate limiting** — blocks IPs after 5 failed attempts (15-minute lockout)
- **Nonce verification** for CSRF protection on comment submissions
- Admin notice when API keys are not configured
- "Settings" link on the Plugins page for quick access
- Secret key field masked as a password input
- Proper script enqueuing via `wp_enqueue_script`
- Input sanitization and output escaping throughout
- Debug logging for failed verifications (when `WP_DEBUG` is enabled)
- Activation checks for PHP 7.4+ and WordPress 5.0+
- Clean uninstall — removes all plugin options on deletion
- Translation-ready with `load_plugin_textdomain`

## Requirements

- WordPress 5.0+
- PHP 7.4+
- A Google reCAPTCHA site key and secret key ([Get keys here](https://www.google.com/recaptcha/admin))

## Installation

1. Download the `google-recaptcha-comments` folder (or the `.zip` file)
2. Upload it to `/wp-content/plugins/`
3. Activate the plugin in **Plugins > Installed Plugins**
4. Go to **Settings > Google ReCaptcha** and enter your site key and secret key

## Configuration

1. Visit [Google reCAPTCHA admin](https://www.google.com/recaptcha/admin) and register your site
   - For **v2**: Choose "I'm not a robot" Checkbox
   - For **v3**: Choose reCAPTCHA v3
2. Copy the **Site Key** and **Secret Key**
3. In WordPress, navigate to **Settings > Google ReCaptcha**
4. Select your reCAPTCHA version
5. Paste your keys and click **Test Connection** to verify
6. Configure additional options:
   - **Score Threshold** (v3 only) — 0.0 to 1.0, lower is more permissive (default: 0.5)
   - **Logged-in Users** — skip reCAPTCHA for authenticated users (default: enabled)
   - **On API Failure** — allow or block comments when Google's API is unreachable (default: allow)

## Plugin Structure

```
google-recaptcha-comments/
├── google-recaptcha-comments.php       # Main plugin bootstrap
├── assets/
│   └── js/
│       └── admin.js                    # Admin settings page JS
└── includes/
    ├── class-grc-settings.php          # Admin settings page & AJAX handler
    ├── class-grc-recaptcha.php         # reCAPTCHA rendering & verification
    └── class-grc-rate-limiter.php      # IP-based rate limiting
```

## Changelog

### 2.0.0
- Added reCAPTCHA v3 support with configurable score threshold
- Added settings link on the Plugins page
- Added Test Connection button to verify API keys
- Added status indicator on settings page
- Added rate limiting for failed reCAPTCHA attempts
- Added nonce verification for CSRF protection
- Added configurable skip for logged-in users
- Added fail-open / fail-closed setting for API failures
- Added activation hook with PHP and WordPress version checks
- Added `load_plugin_textdomain` for translation support
- Guarded constants against redefinition
- Stored plugin instances for extensibility
- Fixed logged-in user render/verify mismatch

### 1.1.0
- Improved security with sanitize callbacks and input sanitization
- Added admin notice for missing API keys
- Added uninstall hook for clean removal
- Switched to `wp_enqueue_script` for proper script loading
- Added debug logging for failed verifications

### 1.0.0
- Initial release

## License

GPL v2 or later — see [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html)
