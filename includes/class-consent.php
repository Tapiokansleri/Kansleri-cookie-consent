<?php

if (!defined('ABSPATH')) {
  exit;
}

class WWCC_Consent {

  public function __construct() {
    add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
    add_action('wp_footer', array($this, 'render_banner'), 100);
  }

  public function enqueue_assets() {
    if (is_admin()) {
      return;
    }

    $settings = wwcc_get_settings();

    wp_enqueue_style(
      'wwcc-consent',
      WWCC_PLUGIN_URL . 'assets/consent.css',
      array(),
      WWCC_VERSION
    );

    wp_enqueue_script(
      'wwcc-consent',
      WWCC_PLUGIN_URL . 'assets/consent.js',
      array(),
      WWCC_VERSION,
      true
    );

    $css_vars = sprintf(
      ':root{--wwcc-primary:%s;--wwcc-primary-hover:%s;--wwcc-primary-text:#fff;}',
      esc_attr($settings['primary_color']),
      esc_attr($this->darken_color($settings['primary_color'], 15))
    );
    wp_add_inline_style('wwcc-consent', $css_vars);
  }

  public function render_banner() {
    if (is_admin()) {
      return;
    }

    $settings = wwcc_get_settings();
    $cookies = wwcc_get_cookies();
    $style = esc_attr($settings['banner_style']);

    $categories = array(
      'necessary'   => __('Necessary', 'ww-cookie-consent'),
      'analytics'   => __('Analytics', 'ww-cookie-consent'),
      'marketing'   => __('Marketing', 'ww-cookie-consent'),
      'preferences' => __('Preferences', 'ww-cookie-consent'),
    );

    $cat_descriptions = array(
      'necessary'   => __('Required for the website to function. Cannot be disabled.', 'ww-cookie-consent'),
      'analytics'   => __('Help us understand how visitors interact with the website.', 'ww-cookie-consent'),
      'marketing'   => __('Used to deliver relevant advertisements and track campaigns.', 'ww-cookie-consent'),
      'preferences' => __('Remember your settings and preferences for a better experience.', 'ww-cookie-consent'),
    );

    $policy_link = '';
    if (!empty($settings['policy_page_id'])) {
      $url = get_permalink($settings['policy_page_id']);
      if ($url) {
        $policy_link = $url;
      }
    }
    ?>
    <div id="wwcc-banner"
         class="wwcc-banner wwcc-banner--<?php echo $style; ?>"
         role="dialog"
         aria-modal="true"
         aria-label="<?php esc_attr_e('Cookie consent', 'ww-cookie-consent'); ?>"
         style="display:none;">

      <?php if ($style === 'modal') : ?>
        <div class="wwcc-overlay"></div>
      <?php endif; ?>

      <div class="wwcc-inner">
        <div class="wwcc-main">
          <h2 class="wwcc-heading"><?php echo esc_html($settings['heading_text']); ?></h2>
          <p class="wwcc-desc"><?php echo esc_html($settings['description_text']); ?>
            <?php if ($policy_link) : ?>
              <a href="<?php echo esc_url($policy_link); ?>" class="wwcc-policy-link" target="_blank"><?php esc_html_e('Cookie Policy', 'ww-cookie-consent'); ?></a>
            <?php endif; ?>
          </p>

          <div class="wwcc-actions">
            <button type="button" class="wwcc-btn wwcc-btn--primary" data-wwcc="accept"><?php echo esc_html($settings['accept_text']); ?></button>
            <button type="button" class="wwcc-btn wwcc-btn--secondary" data-wwcc="reject"><?php echo esc_html($settings['reject_text']); ?></button>
            <button type="button" class="wwcc-btn wwcc-btn--link" data-wwcc="toggle-details"><?php echo esc_html($settings['customize_text']); ?></button>
          </div>
        </div>

        <div class="wwcc-details" style="display:none;">
          <?php foreach ($categories as $key => $label) : ?>
            <div class="wwcc-category">
              <label class="wwcc-category__label">
                <input
                  type="checkbox"
                  class="wwcc-category__check"
                  data-category="<?php echo esc_attr($key); ?>"
                  <?php echo $key === 'necessary' ? 'checked disabled' : ''; ?>
                />
                <span class="wwcc-category__toggle"></span>
                <span class="wwcc-category__name"><?php echo esc_html($label); ?></span>
              </label>
              <p class="wwcc-category__desc"><?php echo esc_html($cat_descriptions[$key]); ?></p>
            </div>
          <?php endforeach; ?>

          <div class="wwcc-details-actions">
            <button type="button" class="wwcc-btn wwcc-btn--primary" data-wwcc="save"><?php echo esc_html($settings['save_text']); ?></button>
          </div>
        </div>
      </div>
    </div>

    <?php if (!empty($settings['show_floating_btn'])) : ?>
      <button type="button"
              id="wwcc-floating-btn"
              class="wwcc-floating-btn"
              aria-label="<?php esc_attr_e('Cookie settings', 'ww-cookie-consent'); ?>"
              title="<?php esc_attr_e('Cookie settings', 'ww-cookie-consent'); ?>"
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

    <script>
      window.wwccConfig = <?php echo wp_json_encode(array(
        'consentMode' => !empty($settings['consent_mode']),
      )); ?>;
    </script>
    <?php
  }

  private function darken_color($hex, $percent) {
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) {
      $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    $r = max(0, hexdec(substr($hex, 0, 2)) - (int)(255 * $percent / 100));
    $g = max(0, hexdec(substr($hex, 2, 2)) - (int)(255 * $percent / 100));
    $b = max(0, hexdec(substr($hex, 4, 2)) - (int)(255 * $percent / 100));
    return sprintf('#%02x%02x%02x', $r, $g, $b);
  }
}
