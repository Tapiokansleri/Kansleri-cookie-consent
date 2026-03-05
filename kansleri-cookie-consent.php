<?php
/**
 * Plugin Name: Kansleri Cookie Consent
 * Description: Lightweight cookie consent with Google Consent Mode v2, GTM dataLayer, cookie scanning, and auto-generated policy page.
 * Version: 1.1.0
 * Author: Kansleri.fi
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

define('KCC_VERSION', '1.1.0');
define('KCC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('KCC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('KCC_BASENAME', plugin_basename(__FILE__));

require_once KCC_PLUGIN_DIR . 'includes/class-admin.php';
require_once KCC_PLUGIN_DIR . 'includes/class-scanner.php';
require_once KCC_PLUGIN_DIR . 'includes/class-consent.php';
require_once KCC_PLUGIN_DIR . 'includes/class-policy-page.php';
require_once KCC_PLUGIN_DIR . 'includes/class-consent-mode.php';
require_once KCC_PLUGIN_DIR . 'includes/class-wp-consent-api.php';
require_once KCC_PLUGIN_DIR . 'includes/class-stats.php';

/**
 * GitHub-based update checker. Must be removed before submitting to WordPress.org,
 * as wordpress.org handles updates through its own infrastructure.
 */
if (file_exists(KCC_PLUGIN_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php')) {
  require_once KCC_PLUGIN_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php';
  add_action('plugins_loaded', function () {
    YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
      'https://github.com/Tapiokansleri/Kansleri-cookie-consent/',
      __FILE__,
      'kansleri-cookie-consent'
    );
  }, 0);
}

if (!function_exists('kcc_get_default_settings')) :
function kcc_get_default_settings() {
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
    'show_floating_btn'  => false,
  );
}
endif;

if (!function_exists('kcc_get_settings')) :
function kcc_get_settings() {
  $defaults = kcc_get_default_settings();
  $saved = get_option('kcc_settings', array());
  return wp_parse_args($saved, $defaults);
}
endif;

if (!function_exists('kcc_get_cookies')) :
function kcc_get_cookies() {
  return get_option('kcc_cookies', array());
}
endif;

if (!function_exists('kcc_get_categories')) :
function kcc_get_categories() {
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

if (!function_exists('kcc_activate')) :
function kcc_activate() {
  $settings = kcc_get_settings();
  if (empty($settings) || !get_option('kcc_settings')) {
    update_option('kcc_settings', kcc_get_default_settings(), false);
  }
  if (!get_option('kcc_cookies')) {
    update_option('kcc_cookies', array(), false);
  }
  KCC_Policy_Page::maybe_create_page();
  update_option('kcc_show_activation_notice', 1, false);
  delete_option('kcc_cron_error');
  if (!wp_next_scheduled('kcc_daily_cookie_check')) {
    wp_schedule_event(time(), 'daily', 'kcc_daily_cookie_check');
  }
}
endif;
register_activation_hook(__FILE__, 'kcc_activate');

if (!function_exists('kcc_deactivate')) :
function kcc_deactivate() {
  wp_clear_scheduled_hook('kcc_daily_cookie_check');
}
endif;
register_deactivation_hook(__FILE__, 'kcc_deactivate');

if (!function_exists('kcc_maybe_localize_defaults')) :
/**
 * One-time migration: if the saved banner texts are still the English defaults
 * and the site locale is not English, update them to the translated defaults.
 */
function kcc_maybe_localize_defaults() {
  if (get_option('kcc_defaults_localized')) {
    return;
  }

  $locale = get_locale();
  if (strpos($locale, 'en') === 0) {
    update_option('kcc_defaults_localized', 1, false);
    return;
  }

  $saved = get_option('kcc_settings', array());
  if (empty($saved)) {
    update_option('kcc_defaults_localized', 1, false);
    return;
  }

  $english_defaults = array(
    'heading_text'     => 'We use cookies',
    'description_text' => 'We use cookies to improve your experience and analyze site traffic. Choose which cookies you allow.',
    'accept_text'      => 'Accept all',
    'reject_text'      => 'Reject all',
    'customize_text'   => 'Customize',
    'save_text'        => 'Save preferences',
  );

  $changed = false;
  foreach ($english_defaults as $key => $english) {
    if (isset($saved[$key]) && $saved[$key] === $english) {
      $translated = __($english, 'kansleri-cookie-consent');
      if ($translated !== $english) {
        $saved[$key] = $translated;
        $changed = true;
      }
    }
  }

  if ($changed) {
    update_option('kcc_settings', $saved, false);
  }
  update_option('kcc_defaults_localized', 1, false);
}
endif;
add_action('plugins_loaded', 'kcc_maybe_localize_defaults', 20);

if (!function_exists('kcc_translate_setting')) :
/**
 * Translate a user-entered setting string via WPML or Polylang.
 * Falls back to the original value when neither plugin is active.
 */
function kcc_translate_setting($value, $name) {
  if (function_exists('pll__')) {
    return pll__($value);
  }
  return apply_filters('wpml_translate_single_string', $value, 'kansleri-cookie-consent', 'kcc_' . $name);
}
endif;

if (!function_exists('kcc_register_polylang_strings')) :
function kcc_register_polylang_strings() {
  if (!function_exists('pll_register_string')) {
    return;
  }
  $settings = kcc_get_settings();
  $fields = array(
    'heading_text'     => 'Heading text',
    'description_text' => 'Description text',
    'accept_text'      => 'Accept button text',
    'reject_text'      => 'Reject button text',
    'customize_text'   => 'Customize button text',
    'save_text'        => 'Save preferences button text',
  );
  foreach ($fields as $key => $label) {
    pll_register_string($label, $settings[$key], 'Kansleri Cookie Consent');
  }
}
endif;

if (!function_exists('kcc_init')) :
function kcc_init() {
  load_plugin_textdomain('kansleri-cookie-consent', false, dirname(KCC_BASENAME) . '/languages');
  new KCC_Admin();
  new KCC_Scanner();
  new KCC_Consent();
  new KCC_Policy_Page();
  new KCC_Consent_Mode();
  new KCC_WP_Consent_API();
  new KCC_Stats();
}
endif;
add_action('plugins_loaded', 'kcc_init');
add_action('init', 'kcc_register_polylang_strings');

if (!function_exists('kcc_plugin_action_links')) :
function kcc_plugin_action_links($links) {
  $url = admin_url('options-general.php?page=kansleri-cookie-consent');
  $settings_link = '<a href="' . esc_url($url) . '">' . esc_html__('Settings', 'kansleri-cookie-consent') . '</a>';
  array_unshift($links, $settings_link);
  return $links;
}
endif;
add_filter('plugin_action_links_' . KCC_BASENAME, 'kcc_plugin_action_links');

if (!function_exists('kcc_render_activation_notice')) :
function kcc_render_activation_notice() {
  if (!is_admin() || !current_user_can('manage_options')) {
    return;
  }
  if (!get_option('kcc_show_activation_notice')) {
    return;
  }

  delete_option('kcc_show_activation_notice');

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
add_action('admin_notices', 'kcc_render_activation_notice');

if (!function_exists('kcc_render_incomplete_cookies_notice')) :
function kcc_render_incomplete_cookies_notice() {
  if (!is_admin() || !current_user_can('manage_options')) {
    return;
  }

  $incomplete = get_option('kcc_incomplete_cookies');
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
add_action('admin_notices', 'kcc_render_incomplete_cookies_notice');
