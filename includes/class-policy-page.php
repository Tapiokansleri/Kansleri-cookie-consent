<?php

if (!defined('ABSPATH')) {
  exit;
}

class KCC_Policy_Page {

  public function __construct() {
    add_shortcode('kcc_cookie_policy', array($this, 'render_shortcode'));
  }

  /**
   * Creates a cookie policy page on plugin activation if one doesn't exist yet.
   */
  public static function maybe_create_page() {
    $settings = kcc_get_settings();

    if (!empty($settings['policy_page_id'])) {
      $existing = get_post($settings['policy_page_id']);
      if ($existing && $existing->post_status !== 'trash') {
        return;
      }
    }

    $page_id = wp_insert_post(array(
      'post_title'   => __('Cookie Policy', 'kansleri-cookie-consent'),
      'post_content' => '[kcc_cookie_policy]',
      'post_status'  => 'draft',
      'post_type'    => 'page',
    ));

    if ($page_id && !is_wp_error($page_id)) {
      $settings['policy_page_id'] = $page_id;
      update_option('kcc_settings', $settings);
    }
  }

  /**
   * Renders the [kcc_cookie_policy] shortcode.
   */
  public function render_shortcode() {
    $cookies = kcc_get_cookies();
    $settings = kcc_get_settings();
    $text_style_mode = (isset($settings['text_style_mode']) && $settings['text_style_mode'] === 'plugin') ? 'plugin' : 'theme';

    if (empty($cookies)) {
      return '<p>' . esc_html__('No cookies have been registered yet.', 'kansleri-cookie-consent') . '</p>';
    }

    $all_cats = kcc_get_categories();
    $categories = array();
    foreach ($all_cats as $key => $data) {
      $categories[$key] = $data['label'];
    }

    $cat_descriptions = array(
      'necessary'   => __('These cookies are essential for the website to function and cannot be switched off.', 'kansleri-cookie-consent'),
      'analytics'   => __('These cookies help us understand how visitors interact with the website by collecting and reporting information anonymously.', 'kansleri-cookie-consent'),
      'marketing'   => __('These cookies are used to deliver relevant advertisements and track ad campaign performance.', 'kansleri-cookie-consent'),
      'preferences' => __('These cookies allow the website to remember choices you make and provide enhanced functionality and personalisation.', 'kansleri-cookie-consent'),
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
    <div class="kcc-policy kcc-typography--<?php echo esc_attr($text_style_mode); ?>">
      <?php foreach ($categories as $key => $label) : ?>
        <?php if (empty($grouped[$key])) continue; ?>
        <div class="kcc-policy__section">
          <h3><?php echo esc_html($label); ?></h3>
          <p class="kcc-policy__cat-desc"><?php echo esc_html($cat_descriptions[$key]); ?></p>
          <table class="kcc-policy__table">
            <thead>
              <tr>
                <th><?php esc_html_e('Cookie', 'kansleri-cookie-consent'); ?></th>
                <th><?php esc_html_e('Provider', 'kansleri-cookie-consent'); ?></th>
                <th><?php esc_html_e('Purpose', 'kansleri-cookie-consent'); ?></th>
                <th><?php esc_html_e('Duration', 'kansleri-cookie-consent'); ?></th>
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
