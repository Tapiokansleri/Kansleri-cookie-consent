<?php

if (!defined('ABSPATH')) {
  exit;
}

class KNCC_Consent {

  public function __construct() {
    add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
    add_action('wp_footer', array($this, 'render_banner'), 100);
  }

  public function enqueue_assets() {
    if (is_admin()) {
      return;
    }

    $settings = kncc_get_settings();

    wp_enqueue_style(
      'kncc-consent',
      KNCC_PLUGIN_URL . 'assets/consent.css',
      array(),
      KNCC_VERSION
    );

    wp_enqueue_script(
      'kncc-consent',
      KNCC_PLUGIN_URL . 'assets/consent.js',
      array(),
      KNCC_VERSION,
      true
    );

    wp_add_inline_script('kncc-consent', 'window.knccConfig=' . wp_json_encode(array(
      'consentMode' => !empty($settings['consent_mode']),
      'ajaxUrl'     => admin_url('admin-ajax.php'),
      'statsNonce'  => wp_create_nonce('kncc_stats'),
    )) . ';', 'before');

    $css_vars = sprintf(
      ':root{--kncc-primary:%s;--kncc-primary-hover:%s;--kncc-primary-text:#fff;}',
      esc_attr($settings['primary_color']),
      esc_attr($this->darken_color($settings['primary_color'], 15))
    );
    wp_add_inline_style('kncc-consent', $css_vars);
  }

  public function render_banner() {
    if (is_admin()) {
      return;
    }

    $settings = kncc_get_localized_texts();
    $style = $settings['banner_style'];
    $text_style_mode = (isset($settings['text_style_mode']) && $settings['text_style_mode'] === 'plugin') ? 'plugin' : 'theme';

    $all_cats = kncc_get_categories();
    $categories = array();
    $cat_descriptions = array();
    foreach ($all_cats as $key => $data) {
      $categories[$key] = $data['label'];
      $cat_descriptions[$key] = $data['description'];
    }

    $policy_link = '';
    if (!empty($settings['policy_page_id'])) {
      $policy_page_id = $settings['policy_page_id'];
      $policy_page_id = apply_filters('wpml_object_id', $policy_page_id, 'page', true); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML API hook.
      if (function_exists('pll_get_post')) {
        $translated_id = pll_get_post($policy_page_id);
        if ($translated_id) {
          $policy_page_id = $translated_id;
        }
      }
      $url = get_permalink($policy_page_id);
      if ($url) {
        $policy_link = $url;
      }
    }
    ?>
    <div id="kncc-banner"
         class="kncc-banner kncc-banner--<?php echo esc_attr( $style ); ?> kncc-typography--<?php echo esc_attr($text_style_mode); ?>"
         role="dialog"
         aria-modal="true"
         aria-label="<?php esc_attr_e('Cookie consent', 'kansleri-cookie-consent'); ?>"
         style="display:none;">

      <?php if ($style === 'modal') : ?>
        <div class="kncc-overlay"></div>
      <?php endif; ?>

      <div class="kncc-inner">
        <div class="kncc-main">
          <h2 class="kncc-heading"><?php echo esc_html($settings['heading_text']); ?></h2>
          <p class="kncc-desc"><?php echo esc_html($settings['description_text']); ?>
            <?php if ($policy_link) : ?>
              <a href="<?php echo esc_url($policy_link); ?>" class="kncc-policy-link" target="_blank"><?php esc_html_e('Cookie Policy', 'kansleri-cookie-consent'); ?></a>
            <?php endif; ?>
          </p>

          <div class="kncc-actions">
            <button type="button" class="kncc-btn kncc-btn--primary" data-kncc="accept"><?php echo esc_html($settings['accept_text']); ?></button>
            <button type="button" class="kncc-btn kncc-btn--secondary" data-kncc="reject"><?php echo esc_html($settings['reject_text']); ?></button>
            <button type="button" class="kncc-btn kncc-btn--link" data-kncc="toggle-details"><?php echo esc_html($settings['customize_text']); ?></button>
            <button type="button" class="kncc-btn kncc-btn--link" data-kncc="close"><?php esc_html_e('Close', 'kansleri-cookie-consent'); ?></button>
          </div>
        </div>

        <div class="kncc-details" style="display:none;">
          <?php foreach ($categories as $key => $label) : ?>
            <div class="kncc-category">
              <label class="kncc-category__label">
                <input
                  type="checkbox"
                  class="kncc-category__check"
                  data-category="<?php echo esc_attr($key); ?>"
                  <?php echo $key === 'necessary' ? 'checked disabled' : ''; ?>
                />
                <span class="kncc-category__toggle"></span>
                <span class="kncc-category__name"><?php echo esc_html($label); ?></span>
              </label>
              <p class="kncc-category__desc"><?php echo esc_html($cat_descriptions[$key]); ?></p>
            </div>
          <?php endforeach; ?>

          <div class="kncc-details-actions">
            <button type="button" class="kncc-btn kncc-btn--primary" data-kncc="save"><?php echo esc_html($settings['save_text']); ?></button>
          </div>
        </div>
      </div>
    </div>

    <?php if (!empty($settings['show_floating_btn'])) : ?>
      <button type="button"
              id="kncc-floating-btn"
              class="kncc-floating-btn kncc-typography--<?php echo esc_attr($text_style_mode); ?>"
              aria-label="<?php esc_attr_e('Cookie settings', 'kansleri-cookie-consent'); ?>"
              title="<?php esc_attr_e('Cookie settings', 'kansleri-cookie-consent'); ?>"
              style="display:none;">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="10"/>
          <circle cx="8" cy="9" r="1.5" fill="currentColor" stroke="none"/>
          <circle cx="15" cy="11" r="1" fill="currentColor" stroke="none"/>
          <circle cx="10" cy="15" r="1.5" fill="currentColor" stroke="none"/>
          <circle cx="15" cy="16" r="1" fill="currentColor" stroke="none"/>
        </svg>
      </button>
    <?php endif; ?>

    <?php
  }

  private function darken_color($hex, $percent) {
    if (!is_string($hex) || !preg_match('/^#?([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $hex)) {
      return '#3D4872';
    }
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) {
      $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    $diff = (int)(255 * max(0, min(100, $percent)) / 100);
    $r = max(0, hexdec(substr($hex, 0, 2)) - $diff);
    $g = max(0, hexdec(substr($hex, 2, 2)) - $diff);
    $b = max(0, hexdec(substr($hex, 4, 2)) - $diff);
    return sprintf('#%02x%02x%02x', $r, $g, $b);
  }
}
