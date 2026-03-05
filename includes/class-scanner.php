<?php

if (!defined('ABSPATH')) {
  exit;
}

class KCC_Scanner {

  public function __construct() {
    add_action('wp_ajax_kcc_process_scan', array($this, 'process_scan'));
  }

  /**
   * Known cookie patterns: regex => meta.
   * Order matters — first match wins.
   */
  private static function get_known_cookies() {
    return array(
      // Google Analytics
      array('pattern' => '/^_ga_/', 'provider' => 'Google', 'category' => 'analytics', 'duration' => '2 years', 'description' => 'Google Analytics: stores and counts page views.'),
      array('pattern' => '/^_ga$/', 'provider' => 'Google', 'category' => 'analytics', 'duration' => '2 years', 'description' => 'Google Analytics: distinguishes unique users.'),
      array('pattern' => '/^_gid$/', 'provider' => 'Google', 'category' => 'analytics', 'duration' => '24 hours', 'description' => 'Google Analytics: distinguishes users for 24 hours.'),
      array('pattern' => '/^_gat/', 'provider' => 'Google', 'category' => 'analytics', 'duration' => '1 minute', 'description' => 'Google Analytics: throttles request rate.'),
      array('pattern' => '/^_gcl_au$/', 'provider' => 'Google', 'category' => 'marketing', 'duration' => '90 days', 'description' => 'Google Ads: stores conversion data.'),
      array('pattern' => '/^_gcl_/', 'provider' => 'Google', 'category' => 'marketing', 'duration' => '90 days', 'description' => 'Google Ads: click tracking.'),

      // Google Tag Manager
      array('pattern' => '/^_dc_gtm_/', 'provider' => 'Google', 'category' => 'analytics', 'duration' => '1 minute', 'description' => 'Google Tag Manager: throttles request rate.'),

      // Facebook / Meta
      array('pattern' => '/^_fbp$/', 'provider' => 'Meta', 'category' => 'marketing', 'duration' => '90 days', 'description' => 'Meta Pixel: tracks visits across websites for ad targeting.'),
      array('pattern' => '/^_fbc$/', 'provider' => 'Meta', 'category' => 'marketing', 'duration' => '90 days', 'description' => 'Meta Pixel: stores click identifiers.'),
      array('pattern' => '/^fr$/', 'provider' => 'Meta', 'category' => 'marketing', 'duration' => '90 days', 'description' => 'Facebook: ad delivery and measurement.'),

      // HubSpot
      array('pattern' => '/^__hs/', 'provider' => 'HubSpot', 'category' => 'marketing', 'duration' => 'Varies', 'description' => 'HubSpot: analytics and tracking.'),
      array('pattern' => '/^hubspotutk$/', 'provider' => 'HubSpot', 'category' => 'marketing', 'duration' => '6 months', 'description' => 'HubSpot: tracks visitor identity.'),
      array('pattern' => '/^__hstc$/', 'provider' => 'HubSpot', 'category' => 'marketing', 'duration' => '6 months', 'description' => 'HubSpot: tracks session information.'),
      array('pattern' => '/^__hssc$/', 'provider' => 'HubSpot', 'category' => 'marketing', 'duration' => '30 minutes', 'description' => 'HubSpot: tracks sessions for analytics.'),
      array('pattern' => '/^__hssrc$/', 'provider' => 'HubSpot', 'category' => 'analytics', 'duration' => 'Session', 'description' => 'HubSpot: determines if visitor has restarted browser.'),

      // LinkedIn
      array('pattern' => '/^li_sugr$/', 'provider' => 'LinkedIn', 'category' => 'marketing', 'duration' => '90 days', 'description' => 'LinkedIn: browser identifier for ad targeting.'),
      array('pattern' => '/^bcookie$/', 'provider' => 'LinkedIn', 'category' => 'marketing', 'duration' => '1 year', 'description' => 'LinkedIn: browser identifier.'),
      array('pattern' => '/^lidc$/', 'provider' => 'LinkedIn', 'category' => 'marketing', 'duration' => '24 hours', 'description' => 'LinkedIn: data centre selection.'),

      // WordPress core
      array('pattern' => '/^wp-settings-time-/', 'provider' => 'WordPress', 'category' => 'necessary', 'duration' => '1 year', 'description' => 'WordPress: stores admin settings timestamp.'),
      array('pattern' => '/^wp-settings-/', 'provider' => 'WordPress', 'category' => 'necessary', 'duration' => '1 year', 'description' => 'WordPress: stores admin personalisation settings.'),
      array('pattern' => '/^wordpress_logged_in/', 'provider' => 'WordPress', 'category' => 'necessary', 'duration' => 'Session', 'description' => 'WordPress: authentication cookie for logged-in users.'),
      array('pattern' => '/^wordpress_sec/', 'provider' => 'WordPress', 'category' => 'necessary', 'duration' => 'Session', 'description' => 'WordPress: secure authentication cookie.'),
      array('pattern' => '/^wordpress_test_cookie$/', 'provider' => 'WordPress', 'category' => 'necessary', 'duration' => 'Session', 'description' => 'WordPress: tests if cookies are enabled.'),
      array('pattern' => '/^wp_lang$/', 'provider' => 'WordPress', 'category' => 'necessary', 'duration' => 'Session', 'description' => 'WordPress: stores language preference.'),
      array('pattern' => '/^comment_author/', 'provider' => 'WordPress', 'category' => 'preferences', 'duration' => '1 year', 'description' => 'WordPress: remembers comment author details.'),

      // WooCommerce
      array('pattern' => '/^woocommerce_/', 'provider' => 'WooCommerce', 'category' => 'necessary', 'duration' => 'Session', 'description' => 'WooCommerce: stores cart and session data.'),
      array('pattern' => '/^wc_/', 'provider' => 'WooCommerce', 'category' => 'necessary', 'duration' => 'Session', 'description' => 'WooCommerce: stores customer session.'),

      // Polylang
      array('pattern' => '/^pll_language$/', 'provider' => 'Polylang', 'category' => 'necessary', 'duration' => '1 year', 'description' => 'Polylang: stores visitor language preference.'),

      // MailPoet
      array('pattern' => '/^mailpoet_/', 'provider' => 'MailPoet', 'category' => 'analytics', 'duration' => 'Varies', 'description' => 'MailPoet: tracks email subscriber activity.'),

      // Plausible
      array('pattern' => '/^plausible_/', 'provider' => 'Plausible', 'category' => 'analytics', 'duration' => 'Varies', 'description' => 'Plausible: privacy-friendly analytics.'),

      // Cloudflare
      array('pattern' => '/^__cf_bm$/', 'provider' => 'Cloudflare', 'category' => 'necessary', 'duration' => '30 minutes', 'description' => 'Cloudflare: bot management cookie.'),
      array('pattern' => '/^cf_clearance$/', 'provider' => 'Cloudflare', 'category' => 'necessary', 'duration' => '30 minutes', 'description' => 'Cloudflare: verifies legitimate visitor.'),
      array('pattern' => '/^__cfduid$/', 'provider' => 'Cloudflare', 'category' => 'necessary', 'duration' => '30 days', 'description' => 'Cloudflare: identifies trusted web traffic.'),

      // Hotjar
      array('pattern' => '/^_hj/', 'provider' => 'Hotjar', 'category' => 'analytics', 'duration' => 'Varies', 'description' => 'Hotjar: tracks user behaviour and feedback.'),

      // Consent cookie itself
      array('pattern' => '/^kcc_consent$/', 'provider' => 'Kansleri Cookie Consent', 'category' => 'necessary', 'duration' => '1 year', 'description' => 'Stores your cookie consent preferences.'),
    );
  }

  private static function identify_cookie($name) {
    foreach (self::get_known_cookies() as $entry) {
      if (preg_match($entry['pattern'], $name)) {
        return array(
          'provider'    => $entry['provider'],
          'category'    => $entry['category'],
          'duration'    => $entry['duration'],
          'description' => $entry['description'],
        );
      }
    }
    return null;
  }

  public function process_scan() {
    check_ajax_referer('kcc_admin', 'nonce');

    if (!current_user_can('manage_options')) {
      wp_send_json_error('Unauthorized');
    }

    $found = isset($_POST['found']) ? array_map('sanitize_text_field', wp_unslash($_POST['found'])) : array();
    $existing = kcc_get_cookies();

    $known_names = array();
    foreach ($existing as $c) {
      $known_names[] = $c['name'];
    }

    $auto_added = array();
    $unknown = array();

    foreach ($found as $name) {
      $name = trim($name);
      if ($name === '' || in_array($name, $known_names, true)) {
        continue;
      }

      $meta = self::identify_cookie($name);

      if ($meta) {
        $cookie = array(
          'name'        => $name,
          'provider'    => $meta['provider'],
          'category'    => $meta['category'],
          'duration'    => $meta['duration'],
          'description' => $meta['description'],
        );
        $existing[] = $cookie;
        $auto_added[] = $cookie;
      } else {
        $unknown[] = array(
          'name'        => $name,
          'provider'    => '',
          'category'    => 'necessary',
          'duration'    => '',
          'description' => '',
        );
      }

      $known_names[] = $name;
    }

    if (!empty($auto_added)) {
      update_option('kcc_cookies', $existing);
    }

    wp_send_json_success(array(
      'auto_added'  => $auto_added,
      'unknown'     => $unknown,
      'total_found' => count($found),
    ));
  }
}
