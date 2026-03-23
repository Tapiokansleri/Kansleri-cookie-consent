<?php

if (!defined('ABSPATH')) {
  exit;
}

class KNCC_Script_Blocker {

  /**
   * Known WordPress script handles mapped to consent categories.
   * Plugins that register scripts with these handles will be auto-blocked.
   */
  private static $known_handles = array(
    'google-analytics'           => 'analytics',
    'google_gtagjs'              => 'analytics',
    'ga-google-analytics'        => 'analytics',
    'monsterinsights-frontend-script' => 'analytics',
    'monsterinsights-tracking-script' => 'analytics',
    'exactmetrics-frontend-script' => 'analytics',
    'hotjar'                     => 'analytics',
    'hotjar-tracking'            => 'analytics',
    'plausible'                  => 'analytics',
    'plausible-analytics'        => 'analytics',
    'matomo'                     => 'analytics',
    'fathom'                     => 'analytics',
    'facebook-pixel'             => 'marketing',
    'facebook-jssdk'             => 'marketing',
    'meta-pixel'                 => 'marketing',
    'fbevents'                   => 'marketing',
    'linkedin-insight'           => 'marketing',
    'hubspot-tracking'           => 'marketing',
    'hs-analytics'               => 'marketing',
    'pinterest-tag'              => 'marketing',
    'tiktok-pixel'               => 'marketing',
    'twitter-pixel'              => 'marketing',
    'snap-pixel'                 => 'marketing',
    'google-ads'                 => 'marketing',
    'google-ads-conversion'      => 'marketing',
  );

  /**
   * URL patterns that identify third-party tracking scripts.
   */
  private static $known_url_patterns = array(
    'googletagmanager.com/gtag'  => 'analytics',
    'google-analytics.com'       => 'analytics',
    'googleanalytics.com'        => 'analytics',
    'hotjar.com'                 => 'analytics',
    'plausible.io'               => 'analytics',
    'matomo'                     => 'analytics',
    'connect.facebook.net'       => 'marketing',
    'fbevents.js'                => 'marketing',
    'snap.licdn.com'             => 'marketing',
    'ads.linkedin.com'           => 'marketing',
    'js.hs-scripts.com'          => 'marketing',
    'js.hs-analytics.net'        => 'marketing',
    'analytics.tiktok.com'       => 'marketing',
    'static.ads-twitter.com'     => 'marketing',
    'googleadservices.com'       => 'marketing',
    's.pinimg.com/ct'            => 'marketing',
    'sc-static.net/scevent'      => 'marketing',
  );

  /**
   * Iframe URL patterns mapped to consent categories.
   */
  private static $iframe_url_patterns = array(
    'youtube.com/embed'          => 'marketing',
    'youtube-nocookie.com/embed' => 'marketing',
    'player.vimeo.com'           => 'marketing',
  );

  public function __construct() {
    if (is_admin()) {
      add_action('wp_ajax_kncc_save_blocked_scripts', array($this, 'ajax_save_blocked_scripts'));
      return;
    }

    add_filter('script_loader_tag', array($this, 'maybe_block_script'), 10, 3);
    add_filter('the_content', array($this, 'block_iframes_in_content'), 999);
    add_filter('embed_oembed_html', array($this, 'block_iframes_in_content'), 999, 1);
  }

  public static function get_blocked_scripts() {
    return get_option('kncc_blocked_scripts', array());
  }

  /**
   * Intercept WordPress-enqueued scripts and block them if they match
   * a known handle, a known URL pattern, or a user-configured handle.
   */
  public function maybe_block_script($tag, $handle, $src) {
    $category = $this->get_handle_category($handle, $src);
    if (!$category || $category === 'necessary') {
      return $tag;
    }

    $tag = preg_replace(
      '/type\s*=\s*["\']text\/javascript["\']/i',
      'type="text/plain"',
      $tag
    );

    if (stripos($tag, 'type=') === false) {
      $tag = str_replace('<script', '<script type="text/plain"', $tag);
    }

    $tag = str_replace('<script', '<script data-kncc-category="' . esc_attr($category) . '"', $tag);

    return $tag;
  }

  /**
   * Determine the consent category for a given script handle and src.
   */
  private function get_handle_category($handle, $src) {
    $user_blocked = self::get_blocked_scripts();
    if (isset($user_blocked[$handle])) {
      return $user_blocked[$handle]['category'];
    }

    if (isset(self::$known_handles[$handle])) {
      return self::$known_handles[$handle];
    }

    if ($src) {
      foreach (self::$known_url_patterns as $pattern => $category) {
        if (stripos($src, $pattern) !== false) {
          return $category;
        }
      }
    }

    return null;
  }

  /**
   * Detect which enqueued scripts are currently registered and match known patterns.
   * Used in admin to show auto-detected scripts.
   */
  public static function detect_registered_scripts() {
    $detected = array();

    foreach (self::$known_handles as $handle => $category) {
      $detected[$handle] = array(
        'handle'   => $handle,
        'category' => $category,
        'source'   => 'known_handle',
      );
    }

    return $detected;
  }

  /**
   * Read the consent cookie to check if a category is already granted.
   */
  private function get_cookie_consent() {
    if (!isset($_COOKIE['kncc_consent'])) {
      return null;
    }
    // Cookie value is URL-encoded JSON set by our own JS. Decode and validate structure only.
    $raw = wp_unslash($_COOKIE['kncc_consent']); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON cookie decoded and validated below.
    $decoded = json_decode(urldecode($raw), true);
    if (!is_array($decoded)) {
      return null;
    }
    // Only keep expected boolean keys to avoid trusting arbitrary data.
    $allowed_keys = array('necessary', 'analytics', 'marketing', 'preferences');
    return array_intersect_key($decoded, array_flip($allowed_keys));
  }

  /**
   * Block third-party iframes (YouTube, Vimeo) in post content
   * until the user grants the matching consent category.
   */
  public function block_iframes_in_content($content) {
    if (!preg_match_all('/<iframe\b[^>]*>/i', $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
      return $content;
    }

    $consent = $this->get_cookie_consent();

    $settings = kncc_get_localized_texts();
    $placeholder_text = !empty($settings['iframe_placeholder_text'])
      ? $settings['iframe_placeholder_text']
      : __('This content is blocked because it requires marketing cookies.', 'kansleri-cookie-consent');
    $accept_text = !empty($settings['iframe_accept_text'])
      ? $settings['iframe_accept_text']
      : __('Accept marketing cookies', 'kansleri-cookie-consent');

    $offset_shift = 0;

    foreach ($matches as $match) {
      $iframe_tag = $match[0][0];
      $position   = $match[0][1] + $offset_shift;

      // Skip iframes already processed (prevents double-processing via embed_oembed_html + the_content).
      if (stripos($iframe_tag, 'data-kncc-category') !== false) {
        continue;
      }

      if (!preg_match('/\ssrc\s*=\s*["\']([^"\']*)/i', $iframe_tag, $src_match)) {
        continue;
      }
      $src = $src_match[1];

      $category = $this->get_iframe_category($src);
      if (!$category) {
        continue;
      }

      // If user already consented to this category, leave the iframe alone.
      if ($consent && !empty($consent[$category])) {
        continue;
      }

      $blocked_tag = preg_replace(
        '/(\s)src\s*=\s*(["\'])/i',
        '$1data-kncc-src=$2',
        $iframe_tag
      );
      $blocked_tag = str_replace('<iframe', '<iframe data-kncc-category="' . esc_attr($category) . '" class="kncc-blocked-iframe"', $blocked_tag);

      $placeholder = '<div class="kncc-iframe-placeholder" data-kncc-category="' . esc_attr($category) . '">'
        . '<p>' . esc_html($placeholder_text) . '</p>'
        . '<button type="button" class="kncc-btn kncc-btn--primary kncc-iframe-accept" data-kncc-iframe-accept="' . esc_attr($category) . '">'
        . esc_html($accept_text)
        . '</button>'
        . '</div>';

      $replacement = $placeholder . $blocked_tag;

      $content = substr_replace($content, $replacement, $position, strlen($iframe_tag));
      $offset_shift += strlen($replacement) - strlen($iframe_tag);
    }

    return $content;
  }

  /**
   * Determine the consent category for an iframe src URL.
   */
  private function get_iframe_category($src) {
    foreach (self::$iframe_url_patterns as $pattern => $category) {
      if (stripos($src, $pattern) !== false) {
        return $category;
      }
    }
    return null;
  }

  public function ajax_save_blocked_scripts() {
    check_ajax_referer('kncc_admin', 'nonce');

    if (!current_user_can('manage_options')) {
      wp_send_json_error('Unauthorized');
    }

    $valid_categories = array('necessary', 'analytics', 'marketing', 'preferences');

    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized below
    $raw_blocked = isset($_POST['blocked_scripts']) ? wp_unslash($_POST['blocked_scripts']) : array();
    $blocked = array();
    if (is_array($raw_blocked)) {
      foreach ($raw_blocked as $entry) {
        $handle = isset($entry['handle']) ? sanitize_key($entry['handle']) : '';
        if ($handle === '') {
          continue;
        }
        $category = isset($entry['category']) ? sanitize_key($entry['category']) : 'analytics';
        if (!in_array($category, $valid_categories, true)) {
          $category = 'analytics';
        }
        $blocked[$handle] = array(
          'handle'   => $handle,
          'category' => $category,
        );
      }
    }
    update_option('kncc_blocked_scripts', $blocked, false);

    wp_send_json_success(array('message' => __('Script blocking settings saved.', 'kansleri-cookie-consent')));
  }
}
