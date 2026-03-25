<?php
/**
 * Plugin Name: Kansleri Cookie Consent
 * Description: Free, no-ads GDPR cookie consent with script blocking, consent logging, Google Consent Mode v2, and AI-assisted setup. Perfect for personal sites and small businesses.
 * Version: 1.3.0
 * Author: Kansleri.fi
 * Author URI: https://kansleri.fi
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: kansleri-cookie-consent
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 *
 * @package Kansleri_Cookie_Consent
 */

if (!defined('ABSPATH')) {
  exit;
}

define('KNCC_VERSION', '1.3.0');
define('KNCC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('KNCC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('KNCC_BASENAME', plugin_basename(__FILE__));

require_once KNCC_PLUGIN_DIR . 'includes/class-admin.php';
require_once KNCC_PLUGIN_DIR . 'includes/class-scanner.php';
require_once KNCC_PLUGIN_DIR . 'includes/class-consent.php';
require_once KNCC_PLUGIN_DIR . 'includes/class-policy-page.php';
require_once KNCC_PLUGIN_DIR . 'includes/class-consent-mode.php';
require_once KNCC_PLUGIN_DIR . 'includes/class-wp-consent-api.php';
require_once KNCC_PLUGIN_DIR . 'includes/class-stats.php';
require_once KNCC_PLUGIN_DIR . 'includes/class-script-blocker.php';
require_once KNCC_PLUGIN_DIR . 'includes/class-consent-log.php';

// Ensure daily cron is always scheduled (survives updates without reactivation).
add_action('init', function () {
  if (!wp_next_scheduled('kncc_daily_cookie_check')) {
    wp_schedule_event(time(), 'daily', 'kncc_daily_cookie_check');
  }
});

if (!function_exists('kncc_get_default_settings')) :
function kncc_get_default_settings() {
  return array(
    'banner_style'       => 'bar',
    'banner_position'    => 'bottom',
    'text_style_mode'    => 'theme',
    'primary_color'      => '#3D4872',
    'heading_text'       => __('We use cookies', 'kansleri-cookie-consent'),
    'description_text'   => __('We use cookies to improve your experience and analyze site traffic. Choose which cookies you allow.', 'kansleri-cookie-consent'),
    'accept_text'        => __('Accept all', 'kansleri-cookie-consent'),
    'reject_text'        => __('Reject all', 'kansleri-cookie-consent'),
    'customize_text'     => __('Customize', 'kansleri-cookie-consent'),
    'save_text'          => __('Save preferences', 'kansleri-cookie-consent'),
    'consent_mode'       => true,
    'gtm_container_id'   => '',
    'policy_page_id'     => 0,
    'show_floating_btn'  => true,
  );
}
endif;

if (!function_exists('kncc_get_settings')) :
function kncc_get_settings() {
  $defaults = kncc_get_default_settings();
  $saved = get_option('kncc_settings', array());
  return wp_parse_args($saved, $defaults);
}
endif;

if (!function_exists('kncc_get_current_language')) :
/**
 * Detect the current language code from WPML or Polylang.
 * Returns empty string if no multilingual plugin is active or if default language.
 */
function kncc_get_current_language() {
  // During AJAX, the client passes the language explicitly because
  // Polylang (and sometimes WPML) does not set the language context.
  // phpcs:ignore WordPress.Security.NonceVerification.Missing -- language hint only, nonce checked by the calling handler.
  if (wp_doing_ajax() && isset($_POST['kncc_lang']) && is_string($_POST['kncc_lang'])) {
    $lang = sanitize_key(wp_unslash($_POST['kncc_lang'])); // phpcs:ignore WordPress.Security.NonceVerification.Missing
    return $lang;
  }

  // WPML.
  if (defined('ICL_LANGUAGE_CODE')) {
    $default = apply_filters('wpml_default_language', ''); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML API hook.
    if (ICL_LANGUAGE_CODE && ICL_LANGUAGE_CODE !== $default) {
      return ICL_LANGUAGE_CODE;
    }
    return '';
  }
  // Polylang.
  if (function_exists('pll_current_language') && function_exists('pll_default_language')) {
    $current = pll_current_language('slug');
    $default = pll_default_language('slug');
    if ($current && $current !== $default) {
      return $current;
    }
  }
  return '';
}
endif;

if (!function_exists('kncc_get_localized_settings')) :
/**
 * Get settings for the current language.
 * Falls back to the base settings for keys not set in the language override.
 */
function kncc_get_localized_settings() {
  $settings = kncc_get_settings();
  $lang = kncc_get_current_language();
  if (!$lang) {
    return $settings;
  }
  $overrides = get_option('kncc_texts_' . $lang, array());
  if (empty($overrides)) {
    return $settings;
  }
  return array_merge($settings, $overrides);
}
endif;

// Backward-compatible alias.
if (!function_exists('kncc_get_localized_texts')) :
function kncc_get_localized_texts() {
  return kncc_get_localized_settings();
}
endif;

if (!function_exists('kncc_get_localized_cookies')) :
/**
 * Get cookies with per-language descriptions merged in.
 * Cookie names, categories, providers, and durations are always shared.
 * Only descriptions can differ per language.
 */
function kncc_get_localized_cookies() {
  $cookies = kncc_get_cookies();
  $lang = kncc_get_current_language();
  if (!$lang) {
    return $cookies;
  }
  $desc_map = get_option('kncc_cookie_descriptions_' . $lang, array());
  if (empty($desc_map)) {
    return $cookies;
  }
  foreach ($cookies as &$cookie) {
    if (isset($desc_map[$cookie['name']])) {
      $cookie['description'] = $desc_map[$cookie['name']];
    }
  }
  unset($cookie);
  return $cookies;
}
endif;

if (!function_exists('kncc_get_cookies')) :
function kncc_get_cookies() {
  return get_option('kncc_cookies', array());
}
endif;

if (!function_exists('kncc_get_categories')) :
function kncc_get_categories() {
  return array(
    'necessary'   => array(
      'label'       => __('Necessary', 'kansleri-cookie-consent'),
      'description' => __('Required for the website to function. Cannot be disabled.', 'kansleri-cookie-consent'),
    ),
    'analytics'   => array(
      'label'       => __('Analytics', 'kansleri-cookie-consent'),
      'description' => __('Help us understand how visitors interact with the website.', 'kansleri-cookie-consent'),
    ),
    'marketing'   => array(
      'label'       => __('Marketing', 'kansleri-cookie-consent'),
      'description' => __('Used to deliver relevant advertisements and track campaigns.', 'kansleri-cookie-consent'),
    ),
    'preferences' => array(
      'label'       => __('Preferences', 'kansleri-cookie-consent'),
      'description' => __('Remember your settings and preferences for a better experience.', 'kansleri-cookie-consent'),
    ),
  );
}
endif;

if (!function_exists('kncc_activate')) :
function kncc_activate() {
  $settings = kncc_get_settings();
  if (empty($settings) || !get_option('kncc_settings')) {
    update_option('kncc_settings', kncc_get_default_settings(), false);
  }
  if (!get_option('kncc_cookies')) {
    update_option('kncc_cookies', array(), false);
  }
  KNCC_Policy_Page::maybe_create_page();
  KNCC_Consent_Log::create_table();
  update_option('kncc_show_activation_notice', 1, false);
  delete_option('kncc_cron_error');
  if (!wp_next_scheduled('kncc_daily_cookie_check')) {
    wp_schedule_event(time(), 'daily', 'kncc_daily_cookie_check');
  }
}
endif;
register_activation_hook(__FILE__, 'kncc_activate');

if (!function_exists('kncc_deactivate')) :
function kncc_deactivate() {
  wp_clear_scheduled_hook('kncc_daily_cookie_check');
}
endif;
register_deactivation_hook(__FILE__, 'kncc_deactivate');

if (!function_exists('kncc_maybe_localize_defaults')) :
/**
 * One-time migration: if the saved banner texts are still the English defaults
 * and the site locale is not English, update them to the translated defaults.
 */
function kncc_maybe_localize_defaults() {
  if (get_option('kncc_defaults_localized')) {
    return;
  }

  $locale = get_locale();
  if (strpos($locale, 'en') === 0) {
    update_option('kncc_defaults_localized', 1, false);
    return;
  }

  $saved = get_option('kncc_settings', array());
  if (empty($saved)) {
    update_option('kncc_defaults_localized', 1, false);
    return;
  }

  $translations = array(
    'heading_text'     => array( 'We use cookies', __( 'We use cookies', 'kansleri-cookie-consent' ) ),
    'description_text' => array( 'We use cookies to improve your experience and analyze site traffic. Choose which cookies you allow.', __( 'We use cookies to improve your experience and analyze site traffic. Choose which cookies you allow.', 'kansleri-cookie-consent' ) ),
    'accept_text'      => array( 'Accept all', __( 'Accept all', 'kansleri-cookie-consent' ) ),
    'reject_text'      => array( 'Reject all', __( 'Reject all', 'kansleri-cookie-consent' ) ),
    'customize_text'   => array( 'Customize', __( 'Customize', 'kansleri-cookie-consent' ) ),
    'save_text'        => array( 'Save preferences', __( 'Save preferences', 'kansleri-cookie-consent' ) ),
  );

  $changed = false;
  foreach ( $translations as $key => $pair ) {
    $english    = $pair[0];
    $translated = $pair[1];
    if ( isset( $saved[ $key ] ) && $saved[ $key ] === $english && $translated !== $english ) {
      $saved[ $key ] = $translated;
      $changed = true;
    }
  }

  if ($changed) {
    update_option('kncc_settings', $saved, false);
  }
  update_option('kncc_defaults_localized', 1, false);
}
endif;
add_action('plugins_loaded', 'kncc_maybe_localize_defaults', 20);

if (!function_exists('kncc_migrate_floating_btn_default')) :
/**
 * One-time migration: enable floating button for existing installs
 * that never explicitly set the option.
 */
function kncc_migrate_floating_btn_default() {
  if (get_option('kncc_floating_btn_migrated')) {
    return;
  }
  $saved = get_option('kncc_settings', array());
  if (!empty($saved) && !isset($saved['show_floating_btn'])) {
    $saved['show_floating_btn'] = true;
    update_option('kncc_settings', $saved, false);
  }
  update_option('kncc_floating_btn_migrated', 1, false);
}
endif;
add_action('plugins_loaded', 'kncc_migrate_floating_btn_default', 21);

if (!function_exists('kncc_maybe_create_consent_log_table')) :
/**
 * Ensure the consent log table exists for sites upgrading from < 1.2.0.
 */
function kncc_maybe_create_consent_log_table() {
  $db_version = get_option('kncc_db_version', '0');
  if (version_compare($db_version, '1.2.0', '<')) {
    KNCC_Consent_Log::create_table();
    update_option('kncc_db_version', '1.2.0', false);
  }
}
endif;
add_action('plugins_loaded', 'kncc_maybe_create_consent_log_table', 22);

if (!function_exists('kncc_get_text_field_keys')) :
/**
 * Return the setting keys that are translatable text fields.
 */
function kncc_get_text_field_keys() {
  return array(
    'heading_text',
    'description_text',
    'accept_text',
    'reject_text',
    'customize_text',
    'save_text',
    'iframe_placeholder_text',
    'iframe_accept_text',
  );
}
endif;

if (!function_exists('kncc_init')) :
function kncc_init() {
  new KNCC_Admin();
  new KNCC_Scanner();
  new KNCC_Consent();
  new KNCC_Policy_Page();
  new KNCC_Consent_Mode();
  new KNCC_WP_Consent_API();
  new KNCC_Stats();
  new KNCC_Script_Blocker();
  new KNCC_Consent_Log();
}
endif;
add_action('plugins_loaded', 'kncc_init');

if (!function_exists('kncc_plugin_action_links')) :
function kncc_plugin_action_links($links) {
  $url = admin_url('options-general.php?page=kansleri-cookie-consent');
  $settings_link = '<a href="' . esc_url($url) . '">' . esc_html__('Settings', 'kansleri-cookie-consent') . '</a>';
  array_unshift($links, $settings_link);
  return $links;
}
endif;
add_filter('plugin_action_links_' . KNCC_BASENAME, 'kncc_plugin_action_links');

if (!function_exists('kncc_render_activation_notice')) :
function kncc_render_activation_notice() {
  if (!is_admin() || !current_user_can('manage_options')) {
    return;
  }
  if (!get_option('kncc_show_activation_notice')) {
    return;
  }

  delete_option('kncc_show_activation_notice');

  $cookies_url = admin_url('options-general.php?page=kansleri-cookie-consent&tab=cookies');
  $general_url = admin_url('options-general.php?page=kansleri-cookie-consent&tab=general');

  $message = sprintf(
    /* translators: 1: Cookies tab link, 2: General tab link */
    __('Kansleri Cookie Consent is now active. Next steps: %1$s to discover cookies and %2$s to review banner and consent settings.', 'kansleri-cookie-consent'),
    '<a href="' . esc_url($cookies_url) . '">' . esc_html__('Scan cookies', 'kansleri-cookie-consent') . '</a>',
    '<a href="' . esc_url($general_url) . '">' . esc_html__('review settings', 'kansleri-cookie-consent') . '</a>'
  );
  ?>
  <div class="notice notice-success is-dismissible">
    <p><?php echo wp_kses($message, array('a' => array('href' => array()))); ?></p>
  </div>
  <?php
}
endif;
add_action('admin_notices', 'kncc_render_activation_notice');

if (!function_exists('kncc_render_incomplete_cookies_notice')) :
function kncc_render_incomplete_cookies_notice() {
  if (!is_admin() || !current_user_can('manage_options')) {
    return;
  }

  $incomplete = get_option('kncc_incomplete_cookies');
  if (empty($incomplete) || !is_array($incomplete)) {
    return;
  }

  $cookies_url = admin_url('options-general.php?page=kansleri-cookie-consent&tab=cookies');
  $count = count($incomplete);
  $names = implode(', ', array_slice($incomplete, 0, 5));
  if ($count > 5) {
    $names .= sprintf(' (+%d more)', $count - 5);
  }

  $message = sprintf(
    /* translators: 1: count, 2: cookie names, 3: cookies tab link */
    __('Cookie Consent: %1$d cookie(s) are missing descriptions: %2$s. %3$s to add them.', 'kansleri-cookie-consent'),
    $count,
    '<code>' . esc_html($names) . '</code>',
    '<a href="' . esc_url($cookies_url) . '">' . esc_html__('Go to Cookies tab', 'kansleri-cookie-consent') . '</a>'
  );
  ?>
  <div class="notice notice-warning is-dismissible">
    <p><?php echo wp_kses($message, array('a' => array('href' => array()), 'code' => array())); ?></p>
  </div>
  <?php
}
endif;
add_action('admin_notices', 'kncc_render_incomplete_cookies_notice');

if (!function_exists('kncc_privacy_policy_content')) :
function kncc_privacy_policy_content() {
  $content = '<h2>' . __('Cookies', 'kansleri-cookie-consent') . '</h2>' .
    '<p>' . __('This site uses a cookie consent banner powered by Kansleri Cookie Consent. When you interact with the banner, a cookie named <code>kncc_consent</code> is stored in your browser for up to 1 year. It records your consent preferences (which cookie categories you accepted or rejected) so the site can remember your choice on future visits. No personal data is stored in this cookie.', 'kansleri-cookie-consent') . '</p>' .
    '<p>' . __('The plugin also records anonymous, aggregated consent statistics (how many visitors accepted, rejected, or customized cookies each day) and individual consent log entries for GDPR audit compliance. Consent log entries contain a hashed (non-reversible) visitor identifier, the consent choice, which categories were accepted, and the policy version. No IP addresses, user identifiers, or personal data are stored in plain text. Consent log records are automatically deleted after 13 months.', 'kansleri-cookie-consent') . '</p>';

  wp_add_privacy_policy_content(
    'Kansleri Cookie Consent',
    wp_kses_post($content)
  );
}
endif;
add_action('admin_init', 'kncc_privacy_policy_content');

if (!function_exists('kncc_cookie_settings_shortcode')) :
/**
 * Shortcode [kncc_cookie_settings] — renders a link that opens the consent banner.
 * Usage: [kncc_cookie_settings] or [kncc_cookie_settings text="Manage cookies"]
 */
function kncc_cookie_settings_shortcode($atts) {
  $atts = shortcode_atts(array(
    'text' => __('Cookie settings', 'kansleri-cookie-consent'),
  ), $atts, 'kncc_cookie_settings');

  return '<a href="#" class="kncc-open-settings" onclick="event.preventDefault();if(typeof knccEditConsent===\'function\')knccEditConsent();">'
    . esc_html($atts['text'])
    . '</a>';
}
endif;
add_shortcode('kncc_cookie_settings', 'kncc_cookie_settings_shortcode');
