<?php
/**
 * Plugin Name: WW Cookie Consent
 * Description: Lightweight cookie consent with Google Consent Mode v2, GTM dataLayer, cookie scanning, and auto-generated policy page.
 * Version: 1.0.0
 * Author: Webwarden
 * Text Domain: ww-cookie-consent
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 *
 * @package WW_Cookie_Consent
 */

if (!defined('ABSPATH')) {
  exit;
}

define('WWCC_VERSION', '1.0.0');
define('WWCC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WWCC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WWCC_BASENAME', plugin_basename(__FILE__));

require_once WWCC_PLUGIN_DIR . 'includes/class-admin.php';
require_once WWCC_PLUGIN_DIR . 'includes/class-scanner.php';
require_once WWCC_PLUGIN_DIR . 'includes/class-consent.php';
require_once WWCC_PLUGIN_DIR . 'includes/class-policy-page.php';
require_once WWCC_PLUGIN_DIR . 'includes/class-consent-mode.php';

function wwcc_get_default_settings() {
  return array(
    'banner_style'       => 'bar',
    'banner_position'    => 'bottom',
    'primary_color'      => '#3D4872',
    'heading_text'       => 'We use cookies',
    'description_text'   => 'We use cookies to improve your experience and analyze site traffic. Choose which cookies you allow.',
    'accept_text'        => 'Accept all',
    'reject_text'        => 'Reject all',
    'customize_text'     => 'Customize',
    'save_text'          => 'Save preferences',
    'consent_mode'       => true,
    'gtm_container_id'   => '',
    'policy_page_id'     => 0,
    'show_floating_btn'  => true,
  );
}

function wwcc_get_settings() {
  $defaults = wwcc_get_default_settings();
  $saved = get_option('wwcc_settings', array());
  return wp_parse_args($saved, $defaults);
}

function wwcc_get_cookies() {
  return get_option('wwcc_cookies', array());
}

function wwcc_activate() {
  $settings = wwcc_get_settings();
  if (empty($settings) || !get_option('wwcc_settings')) {
    update_option('wwcc_settings', wwcc_get_default_settings());
  }
  if (!get_option('wwcc_cookies')) {
    update_option('wwcc_cookies', array());
  }
  WWCC_Policy_Page::maybe_create_page();
}
register_activation_hook(__FILE__, 'wwcc_activate');

function wwcc_init() {
  new WWCC_Admin();
  new WWCC_Scanner();
  new WWCC_Consent();
  new WWCC_Policy_Page();
  new WWCC_Consent_Mode();
}
add_action('plugins_loaded', 'wwcc_init');
