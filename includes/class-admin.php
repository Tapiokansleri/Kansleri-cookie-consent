<?php

if (!defined('ABSPATH')) {
  exit;
}

class KCC_Admin {

  public function __construct() {
    add_action('admin_menu', array($this, 'add_menu'));
    add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    add_action('wp_ajax_kcc_save_settings', array($this, 'ajax_save_settings'));
    add_action('wp_ajax_kcc_save_cookies', array($this, 'ajax_save_cookies'));
  }

  public function add_menu() {
    add_options_page(
      __('Cookie Consent', 'kansleri-cookie-consent'),
      __('Cookie Consent', 'kansleri-cookie-consent'),
      'manage_options',
      'kansleri-cookie-consent',
      array($this, 'render_page')
    );
  }

  public function enqueue_assets($hook) {
    if ($hook !== 'settings_page_kansleri-cookie-consent') {
      return;
    }

    wp_enqueue_style(
      'kcc-admin',
      KCC_PLUGIN_URL . 'assets/admin.css',
      array(),
      KCC_VERSION
    );

    wp_enqueue_script(
      'kcc-admin',
      KCC_PLUGIN_URL . 'assets/admin.js',
      array(),
      KCC_VERSION,
      true
    );

    wp_localize_script('kcc-admin', 'kccAdmin', array(
      'ajaxUrl'  => admin_url('admin-ajax.php'),
      'nonce'    => wp_create_nonce('kcc_admin'),
      'homeUrl'  => home_url('/'),
      'strings'  => array(
        'saved'        => __('Settings saved.', 'kansleri-cookie-consent'),
        'error'        => __('An error occurred.', 'kansleri-cookie-consent'),
        'scanning'     => __('Scanning...', 'kansleri-cookie-consent'),
        'scanDone'     => __('Scan complete.', 'kansleri-cookie-consent'),
        'noNew'        => __('No new cookies found.', 'kansleri-cookie-consent'),
        'confirmDelete'=> __('Remove this cookie?', 'kansleri-cookie-consent'),
      ),
    ));
  }

  public function render_page() {
    $settings = kcc_get_settings();
    $cookies = kcc_get_cookies();
    $pages = get_pages(array('post_status' => 'publish,draft'));
    $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'general';
    ?>
    <div class="wrap kcc-wrap">
      <h1><?php esc_html_e('Cookie Consent', 'kansleri-cookie-consent'); ?></h1>

      <nav class="nav-tab-wrapper kcc-tabs">
        <a href="?page=kansleri-cookie-consent&tab=general" class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">
          <?php esc_html_e('General', 'kansleri-cookie-consent'); ?>
        </a>
        <a href="?page=kansleri-cookie-consent&tab=cookies" class="nav-tab <?php echo $active_tab === 'cookies' ? 'nav-tab-active' : ''; ?>">
          <?php esc_html_e('Cookies', 'kansleri-cookie-consent'); ?>
        </a>
        <a href="?page=kansleri-cookie-consent&tab=policy" class="nav-tab <?php echo $active_tab === 'policy' ? 'nav-tab-active' : ''; ?>">
          <?php esc_html_e('Policy Page', 'kansleri-cookie-consent'); ?>
        </a>
        <a href="?page=kansleri-cookie-consent&tab=integration" class="nav-tab <?php echo $active_tab === 'integration' ? 'nav-tab-active' : ''; ?>">
          <?php esc_html_e('Integration', 'kansleri-cookie-consent'); ?>
        </a>
      </nav>

      <div class="kcc-notice" id="kcc-notice" style="display:none;"></div>

      <?php
      switch ($active_tab) {
        case 'cookies':
          $this->render_cookies_tab($cookies);
          break;
        case 'policy':
          $this->render_policy_tab($settings, $pages);
          break;
        case 'integration':
          $this->render_integration_tab($settings);
          break;
        default:
          $this->render_general_tab($settings);
          break;
      }
      ?>
    </div>
    <?php
  }

  private function render_general_tab($settings) {
    ?>
    <form id="kcc-general-form" class="kcc-form">
      <table class="form-table">
        <tr>
          <th scope="row"><label for="kcc-banner-style"><?php esc_html_e('Banner style', 'kansleri-cookie-consent'); ?></label></th>
          <td>
            <select id="kcc-banner-style" name="banner_style">
              <option value="bar" <?php selected($settings['banner_style'], 'bar'); ?>><?php esc_html_e('Bottom bar', 'kansleri-cookie-consent'); ?></option>
              <option value="modal" <?php selected($settings['banner_style'], 'modal'); ?>><?php esc_html_e('Centered modal', 'kansleri-cookie-consent'); ?></option>
              <option value="corner" <?php selected($settings['banner_style'], 'corner'); ?>><?php esc_html_e('Corner popup', 'kansleri-cookie-consent'); ?></option>
            </select>
          </td>
        </tr>
        <tr>
          <th scope="row"><label for="kcc-primary-color"><?php esc_html_e('Primary color', 'kansleri-cookie-consent'); ?></label></th>
          <td>
            <input type="color" id="kcc-primary-color" name="primary_color" value="<?php echo esc_attr($settings['primary_color']); ?>" />
          </td>
        </tr>
        <tr>
          <th scope="row"><label for="kcc-heading"><?php esc_html_e('Heading text', 'kansleri-cookie-consent'); ?></label></th>
          <td>
            <input type="text" id="kcc-heading" name="heading_text" value="<?php echo esc_attr($settings['heading_text']); ?>" class="regular-text" />
          </td>
        </tr>
        <tr>
          <th scope="row"><label for="kcc-description"><?php esc_html_e('Description text', 'kansleri-cookie-consent'); ?></label></th>
          <td>
            <textarea id="kcc-description" name="description_text" rows="3" class="large-text"><?php echo esc_textarea($settings['description_text']); ?></textarea>
          </td>
        </tr>
        <tr>
          <th scope="row"><label for="kcc-accept"><?php esc_html_e('Accept button text', 'kansleri-cookie-consent'); ?></label></th>
          <td><input type="text" id="kcc-accept" name="accept_text" value="<?php echo esc_attr($settings['accept_text']); ?>" class="regular-text" /></td>
        </tr>
        <tr>
          <th scope="row"><label for="kcc-reject"><?php esc_html_e('Reject button text', 'kansleri-cookie-consent'); ?></label></th>
          <td><input type="text" id="kcc-reject" name="reject_text" value="<?php echo esc_attr($settings['reject_text']); ?>" class="regular-text" /></td>
        </tr>
        <tr>
          <th scope="row"><label for="kcc-customize"><?php esc_html_e('Customize button text', 'kansleri-cookie-consent'); ?></label></th>
          <td><input type="text" id="kcc-customize" name="customize_text" value="<?php echo esc_attr($settings['customize_text']); ?>" class="regular-text" /></td>
        </tr>
        <tr>
          <th scope="row"><label for="kcc-save"><?php esc_html_e('Save preferences button text', 'kansleri-cookie-consent'); ?></label></th>
          <td><input type="text" id="kcc-save" name="save_text" value="<?php echo esc_attr($settings['save_text']); ?>" class="regular-text" /></td>
        </tr>
        <tr>
          <th scope="row"><?php esc_html_e('Floating settings button', 'kansleri-cookie-consent'); ?></th>
          <td>
            <label>
              <input type="checkbox" name="show_floating_btn" value="1" <?php checked($settings['show_floating_btn']); ?> />
              <?php esc_html_e('Show a small floating button so visitors can re-open their cookie preferences', 'kansleri-cookie-consent'); ?>
            </label>
          </td>
        </tr>
      </table>
      <p class="submit">
        <button type="submit" class="button button-primary"><?php esc_html_e('Save Settings', 'kansleri-cookie-consent'); ?></button>
      </p>
    </form>
    <?php
  }

  private function render_cookies_tab($cookies) {
    ?>
    <div class="kcc-cookies-tab">
      <div class="kcc-cookies-toolbar">
        <button type="button" class="button" id="kcc-scan-btn"><?php esc_html_e('Scan Cookies', 'kansleri-cookie-consent'); ?></button>
        <button type="button" class="button" id="kcc-add-cookie-btn"><?php esc_html_e('Add Cookie', 'kansleri-cookie-consent'); ?></button>
      </div>

      <div id="kcc-scan-results" style="display:none;" class="kcc-scan-results"></div>

      <form id="kcc-cookies-form">
        <table class="wp-list-table widefat striped kcc-cookie-table" id="kcc-cookie-table">
          <thead>
            <tr>
              <th><?php esc_html_e('Name', 'kansleri-cookie-consent'); ?></th>
              <th><?php esc_html_e('Category', 'kansleri-cookie-consent'); ?></th>
              <th><?php esc_html_e('Provider', 'kansleri-cookie-consent'); ?></th>
              <th><?php esc_html_e('Duration', 'kansleri-cookie-consent'); ?></th>
              <th><?php esc_html_e('Description', 'kansleri-cookie-consent'); ?></th>
              <th style="width:60px;"></th>
            </tr>
          </thead>
          <tbody id="kcc-cookie-tbody">
            <?php if (empty($cookies)) : ?>
              <tr class="kcc-no-cookies"><td colspan="6"><?php esc_html_e('No cookies registered yet.', 'kansleri-cookie-consent'); ?></td></tr>
            <?php else : ?>
              <?php foreach ($cookies as $i => $cookie) : ?>
                <tr data-index="<?php echo $i; ?>">
                  <td><input type="text" name="cookies[<?php echo $i; ?>][name]" value="<?php echo esc_attr($cookie['name']); ?>" class="regular-text" /></td>
                  <td>
                    <select name="cookies[<?php echo $i; ?>][category]">
                      <option value="necessary" <?php selected($cookie['category'], 'necessary'); ?>><?php esc_html_e('Necessary', 'kansleri-cookie-consent'); ?></option>
                      <option value="analytics" <?php selected($cookie['category'], 'analytics'); ?>><?php esc_html_e('Analytics', 'kansleri-cookie-consent'); ?></option>
                      <option value="marketing" <?php selected($cookie['category'], 'marketing'); ?>><?php esc_html_e('Marketing', 'kansleri-cookie-consent'); ?></option>
                      <option value="preferences" <?php selected($cookie['category'], 'preferences'); ?>><?php esc_html_e('Preferences', 'kansleri-cookie-consent'); ?></option>
                    </select>
                  </td>
                  <td><input type="text" name="cookies[<?php echo $i; ?>][provider]" value="<?php echo esc_attr($cookie['provider'] ?? ''); ?>" /></td>
                  <td><input type="text" name="cookies[<?php echo $i; ?>][duration]" value="<?php echo esc_attr($cookie['duration'] ?? ''); ?>" style="width:100px;" /></td>
                  <td><input type="text" name="cookies[<?php echo $i; ?>][description]" value="<?php echo esc_attr($cookie['description'] ?? ''); ?>" class="large-text" /></td>
                  <td><button type="button" class="button kcc-remove-cookie" title="<?php esc_attr_e('Remove', 'kansleri-cookie-consent'); ?>">&times;</button></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
        <p class="submit">
          <button type="submit" class="button button-primary"><?php esc_html_e('Save Cookies', 'kansleri-cookie-consent'); ?></button>
        </p>
      </form>
    </div>
    <iframe id="kcc-scan-iframe" style="display:none;width:0;height:0;border:0;" sandbox="allow-same-origin allow-scripts"></iframe>
    <?php
  }

  private function render_policy_tab($settings, $pages) {
    ?>
    <form id="kcc-policy-form" class="kcc-form">
      <table class="form-table">
        <tr>
          <th scope="row"><label for="kcc-policy-page"><?php esc_html_e('Cookie policy page', 'kansleri-cookie-consent'); ?></label></th>
          <td>
            <select id="kcc-policy-page" name="policy_page_id">
              <option value="0"><?php esc_html_e('— Select a page —', 'kansleri-cookie-consent'); ?></option>
              <?php foreach ($pages as $page) : ?>
                <option value="<?php echo $page->ID; ?>" <?php selected($settings['policy_page_id'], $page->ID); ?>>
                  <?php echo esc_html($page->post_title); ?>
                </option>
              <?php endforeach; ?>
            </select>
            <p class="description">
              <?php esc_html_e('Select a page or use the button below to create one. Add the shortcode [kcc_cookie_policy] to its content.', 'kansleri-cookie-consent'); ?>
            </p>
          </td>
        </tr>
      </table>
      <p class="submit">
        <button type="submit" class="button button-primary"><?php esc_html_e('Save Settings', 'kansleri-cookie-consent'); ?></button>
      </p>
    </form>
    <?php
    if ($settings['policy_page_id']) :
      $link = get_permalink($settings['policy_page_id']);
      if ($link) :
    ?>
      <p><a href="<?php echo esc_url($link); ?>" target="_blank" class="button"><?php esc_html_e('Preview Policy Page', 'kansleri-cookie-consent'); ?></a></p>
    <?php
      endif;
    endif;
  }

  private function render_integration_tab($settings) {
    ?>
    <form id="kcc-integration-form" class="kcc-form">
      <table class="form-table">
        <tr>
          <th scope="row"><?php esc_html_e('Google Consent Mode v2', 'kansleri-cookie-consent'); ?></th>
          <td>
            <label>
              <input type="checkbox" name="consent_mode" value="1" <?php checked($settings['consent_mode']); ?> />
              <?php esc_html_e('Enable Consent Mode v2 (outputs consent defaults in &lt;head&gt; before GTM)', 'kansleri-cookie-consent'); ?>
            </label>
          </td>
        </tr>
        <tr>
          <th scope="row"><label for="kcc-gtm-id"><?php esc_html_e('GTM Container ID', 'kansleri-cookie-consent'); ?></label></th>
          <td>
            <input type="text" id="kcc-gtm-id" name="gtm_container_id" value="<?php echo esc_attr($settings['gtm_container_id']); ?>" placeholder="GTM-XXXXXXX" class="regular-text" />
            <p class="description"><?php esc_html_e('For reference only — this plugin does not inject the GTM snippet.', 'kansleri-cookie-consent'); ?></p>
          </td>
        </tr>
      </table>

      <?php if ($settings['consent_mode']) : ?>
      <div class="kcc-code-preview">
        <h3><?php esc_html_e('Consent Mode defaults (auto-injected in &lt;head&gt;)', 'kansleri-cookie-consent'); ?></h3>
        <pre><code>gtag('consent', 'default', {
  ad_storage: 'denied',
  ad_user_data: 'denied',
  ad_personalization: 'denied',
  analytics_storage: 'denied',
  functionality_storage: 'denied',
  personalization_storage: 'denied',
  security_storage: 'granted',
  wait_for_update: 500,
});</code></pre>
      </div>
      <?php endif; ?>

      <p class="submit">
        <button type="submit" class="button button-primary"><?php esc_html_e('Save Settings', 'kansleri-cookie-consent'); ?></button>
      </p>
    </form>
    <?php
  }

  public function ajax_save_settings() {
    check_ajax_referer('kcc_admin', 'nonce');

    if (!current_user_can('manage_options')) {
      wp_send_json_error('Unauthorized');
    }

    $current = kcc_get_settings();
    $fields = array(
      'banner_style', 'banner_position', 'primary_color',
      'heading_text', 'description_text', 'accept_text',
      'reject_text', 'customize_text', 'save_text',
      'gtm_container_id',
    );

    foreach ($fields as $field) {
      if (isset($_POST[$field])) {
        $current[$field] = sanitize_text_field(wp_unslash($_POST[$field]));
      }
    }

    if (isset($_POST['policy_page_id'])) {
      $current['policy_page_id'] = absint($_POST['policy_page_id']);
    }

    $current['consent_mode'] = !empty($_POST['consent_mode']);
    $current['show_floating_btn'] = !empty($_POST['show_floating_btn']);

    update_option('kcc_settings', $current);
    wp_send_json_success(array('message' => __('Settings saved.', 'kansleri-cookie-consent')));
  }

  public function ajax_save_cookies() {
    check_ajax_referer('kcc_admin', 'nonce');

    if (!current_user_can('manage_options')) {
      wp_send_json_error('Unauthorized');
    }

    $raw = isset($_POST['cookies']) ? $_POST['cookies'] : array();
    $cookies = array();
    $valid_categories = array('necessary', 'analytics', 'marketing', 'preferences');

    if (is_array($raw)) {
      foreach ($raw as $entry) {
        $name = isset($entry['name']) ? sanitize_text_field(wp_unslash($entry['name'])) : '';
        if ($name === '') {
          continue;
        }

        $category = isset($entry['category']) ? sanitize_key($entry['category']) : 'necessary';
        if (!in_array($category, $valid_categories, true)) {
          $category = 'necessary';
        }

        $cookies[] = array(
          'name'        => $name,
          'category'    => $category,
          'provider'    => isset($entry['provider']) ? sanitize_text_field(wp_unslash($entry['provider'])) : '',
          'duration'    => isset($entry['duration']) ? sanitize_text_field(wp_unslash($entry['duration'])) : '',
          'description' => isset($entry['description']) ? sanitize_text_field(wp_unslash($entry['description'])) : '',
        );
      }
    }

    update_option('kcc_cookies', $cookies);
    wp_send_json_success(array('message' => __('Cookies saved.', 'kansleri-cookie-consent')));
  }
}
