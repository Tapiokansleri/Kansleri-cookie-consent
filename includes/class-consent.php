<?php

if (!defined('ABSPATH')) {
  exit;
}

class KCC_Consent {

  public function __construct() {
    add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
    add_action('wp_footer', array($this, 'render_banner'), 100);
  }

  public function enqueue_assets() {
    if (is_admin()) {
      return;
    }

    $settings = kcc_get_settings();

    wp_enqueue_style(
      'kcc-consent',
      KCC_PLUGIN_URL . 'assets/consent.css',
      array(),
      KCC_VERSION
    );

    wp_enqueue_script(
      'kcc-consent',
      KCC_PLUGIN_URL . 'assets/consent.js',
      array(),
      KCC_VERSION,
      true
    );

    wp_add_inline_script('kcc-consent', 'window.kccConfig=' . wp_json_encode(array(
      'consentMode' => !empty($settings['consent_mode']),
      'ajaxUrl'     => admin_url('admin-ajax.php'),
    )) . ';', 'before');

    $css_vars = sprintf(
      ':root{--kcc-primary:%s;--kcc-primary-hover:%s;--kcc-primary-text:#fff;}',
      esc_attr($settings['primary_color']),
      esc_attr($this->darken_color($settings['primary_color'], 15))
    );
    wp_add_inline_style('kcc-consent', $css_vars);
  }

  public function render_banner() {
    if (is_admin()) {
      return;
    }

    $settings = kcc_get_settings();
    $style = esc_attr($settings['banner_style']);
    $text_style_mode = (isset($settings['text_style_mode']) && $settings['text_style_mode'] === 'plugin') ? 'plugin' : 'theme';

    $all_cats = kcc_get_categories();
    $categories = array();
    $cat_descriptions = array();
    foreach ($all_cats as $key => $data) {
      $categories[$key] = $data['label'];
      $cat_descriptions[$key] = $data['description'];
    }

    $policy_link = '';
    if (!empty($settings['policy_page_id'])) {
      $policy_page_id = $settings['policy_page_id'];
      $policy_page_id = apply_filters('wpml_object_id', $policy_page_id, 'page', true);
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
    <div id="kcc-banner"
         class="kcc-banner kcc-banner--<?php echo $style; ?> kcc-typography--<?php echo esc_attr($text_style_mode); ?>"
         role="dialog"
         aria-modal="true"
         aria-label="<?php esc_attr_e('Cookie consent', 'kansleri-cookie-consent'); ?>"
         style="display:none;">

      <?php if ($style === 'modal') : ?>
        <div class="kcc-overlay"></div>
      <?php endif; ?>

      <div class="kcc-inner">
        <div class="kcc-main">
          <h2 class="kcc-heading"><?php echo esc_html(kcc_translate_setting($settings['heading_text'], 'heading_text')); ?></h2>
          <p class="kcc-desc"><?php echo esc_html(kcc_translate_setting($settings['description_text'], 'description_text')); ?>
            <?php if ($policy_link) : ?>
              <a href="<?php echo esc_url($policy_link); ?>" class="kcc-policy-link" target="_blank"><?php esc_html_e('Cookie Policy', 'kansleri-cookie-consent'); ?></a>
            <?php endif; ?>
          </p>

          <div class="kcc-actions">
            <button type="button" class="kcc-btn kcc-btn--primary" data-kcc="accept"><?php echo esc_html(kcc_translate_setting($settings['accept_text'], 'accept_text')); ?></button>
            <button type="button" class="kcc-btn kcc-btn--secondary" data-kcc="reject"><?php echo esc_html(kcc_translate_setting($settings['reject_text'], 'reject_text')); ?></button>
            <button type="button" class="kcc-btn kcc-btn--link" data-kcc="toggle-details"><?php echo esc_html(kcc_translate_setting($settings['customize_text'], 'customize_text')); ?></button>
            <button type="button" class="kcc-btn kcc-btn--link" data-kcc="close"><?php esc_html_e('Close', 'kansleri-cookie-consent'); ?></button>
          </div>
        </div>

        <div class="kcc-details" style="display:none;">
          <?php foreach ($categories as $key => $label) : ?>
            <div class="kcc-category">
              <label class="kcc-category__label">
                <input
                  type="checkbox"
                  class="kcc-category__check"
                  data-category="<?php echo esc_attr($key); ?>"
                  <?php echo $key === 'necessary' ? 'checked disabled' : ''; ?>
                />
                <span class="kcc-category__toggle"></span>
                <span class="kcc-category__name"><?php echo esc_html($label); ?></span>
              </label>
              <p class="kcc-category__desc"><?php echo esc_html($cat_descriptions[$key]); ?></p>
            </div>
          <?php endforeach; ?>

          <div class="kcc-details-actions">
            <button type="button" class="kcc-btn kcc-btn--primary" data-kcc="save"><?php echo esc_html(kcc_translate_setting($settings['save_text'], 'save_text')); ?></button>
          </div>
        </div>
      </div>
    </div>

    <?php if (!empty($settings['show_floating_btn'])) : ?>
      <button type="button"
              id="kcc-floating-btn"
              class="kcc-floating-btn kcc-typography--<?php echo esc_attr($text_style_mode); ?>"
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
