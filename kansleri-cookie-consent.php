<?php
/**
 * Plugin Name: Kansleri Cookie Consent
 * Description: Lightweight cookie consent with Google Consent Mode v2, GTM dataLayer, cookie scanning, and auto-generated policy page.
 * Version: 1.0.1
 * Author: Webwarden
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

define('KCC_VERSION', '1.0.1');
define('KCC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('KCC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('KCC_BASENAME', plugin_basename(__FILE__));

require_once KCC_PLUGIN_DIR . 'includes/class-admin.php';
require_once KCC_PLUGIN_DIR . 'includes/class-scanner.php';
require_once KCC_PLUGIN_DIR . 'includes/class-consent.php';
require_once KCC_PLUGIN_DIR . 'includes/class-policy-page.php';
require_once KCC_PLUGIN_DIR . 'includes/class-consent-mode.php';
require_once KCC_PLUGIN_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

PucFactory::buildUpdateChecker(
  'https://github.com/Tapiokansleri/Kansleri-cookie-consent/',
  __FILE__,
  'kansleri-cookie-consent'
);

function kcc_get_default_settings() {
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

function kcc_get_settings() {
  $defaults = kcc_get_default_settings();
  $saved = get_option('kcc_settings', array());
  return wp_parse_args($saved, $defaults);
}

function kcc_get_cookies() {
  return get_option('kcc_cookies', array());
}

function kcc_activate() {
  $settings = kcc_get_settings();
  if (empty($settings) || !get_option('kcc_settings')) {
    update_option('kcc_settings', kcc_get_default_settings());
  }
  if (!get_option('kcc_cookies')) {
    update_option('kcc_cookies', array());
  }
  KCC_Policy_Page::maybe_create_page();
}
register_activation_hook(__FILE__, 'kcc_activate');

function kcc_init() {
  new KCC_Admin();
  new KCC_Scanner();
  new KCC_Consent();
  new KCC_Policy_Page();
  new KCC_Consent_Mode();
}
add_action('plugins_loaded', 'kcc_init');
