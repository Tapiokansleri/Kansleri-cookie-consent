<?php

if (!defined('ABSPATH')) {
  exit;
}

class WWCC_Policy_Page {

  public function __construct() {
    add_shortcode('ww_cookie_policy', array($this, 'render_shortcode'));
  }

  /**
   * Creates a cookie policy page on plugin activation if one doesn't exist yet.
   */
  public static function maybe_create_page() {
    $settings = wwcc_get_settings();

    if (!empty($settings['policy_page_id'])) {
      $existing = get_post($settings['policy_page_id']);
      if ($existing && $existing->post_status !== 'trash') {
        return;
      }
    }

    $page_id = wp_insert_post(array(
      'post_title'   => __('Cookie Policy', 'ww-cookie-consent'),
      'post_content' => '[ww_cookie_policy]',
      'post_status'  => 'draft',
      'post_type'    => 'page',
    ));

    if ($page_id && !is_wp_error($page_id)) {
      $settings['policy_page_id'] = $page_id;
      update_option('wwcc_settings', $settings);
    }
  }

  /**
   * Renders the [ww_cookie_policy] shortcode.
   */
  public function render_shortcode() {
    $cookies = wwcc_get_cookies();

    if (empty($cookies)) {
      return '<p>' . esc_html__('No cookies have been registered yet.', 'ww-cookie-consent') . '</p>';
    }

    $categories = array(
      'necessary'   => __('Necessary', 'ww-cookie-consent'),
      'analytics'   => __('Analytics', 'ww-cookie-consent'),
      'marketing'   => __('Marketing', 'ww-cookie-consent'),
      'preferences' => __('Preferences', 'ww-cookie-consent'),
    );

    $cat_descriptions = array(
      'necessary'   => __('These cookies are essential for the website to function and cannot be switched off.', 'ww-cookie-consent'),
      'analytics'   => __('These cookies help us understand how visitors interact with the website by collecting and reporting information anonymously.', 'ww-cookie-consent'),
      'marketing'   => __('These cookies are used to deliver relevant advertisements and track ad campaign performance.', 'ww-cookie-consent'),
      'preferences' => __('These cookies allow the website to remember choices you make and provide enhanced functionality and personalisation.', 'ww-cookie-consent'),
    );

    $grouped = array();
    foreach ($cookies as $cookie) {
      $cat = isset($cookie['category']) ? $cookie['category'] : 'necessary';
      if (!isset($grouped[$cat])) {
        $grouped[$cat] = array();
      }
      $grouped[$cat][] = $cookie;
    }

    ob_start();
    ?>
    <div class="wwcc-policy">
      <?php foreach ($categories as $key => $label) : ?>
        <?php if (empty($grouped[$key])) continue; ?>
        <div class="wwcc-policy__section">
          <h3><?php echo esc_html($label); ?></h3>
          <p class="wwcc-policy__cat-desc"><?php echo esc_html($cat_descriptions[$key]); ?></p>
          <table class="wwcc-policy__table">
            <thead>
              <tr>
                <th><?php esc_html_e('Cookie', 'ww-cookie-consent'); ?></th>
                <th><?php esc_html_e('Provider', 'ww-cookie-consent'); ?></th>
                <th><?php esc_html_e('Purpose', 'ww-cookie-consent'); ?></th>
                <th><?php esc_html_e('Duration', 'ww-cookie-consent'); ?></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($grouped[$key] as $cookie) : ?>
                <tr>
                  <td><code><?php echo esc_html($cookie['name']); ?></code></td>
                  <td><?php echo esc_html($cookie['provider'] ?? ''); ?></td>
                  <td><?php echo esc_html($cookie['description'] ?? ''); ?></td>
                  <td><?php echo esc_html($cookie['duration'] ?? ''); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
  }
}
