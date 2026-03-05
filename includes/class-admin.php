<?php

if (!defined('ABSPATH')) {
  exit;
}

class WWCC_Admin {

  public function __construct() {
    add_action('admin_menu', array($this, 'add_menu'));
    add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    add_action('wp_ajax_wwcc_save_settings', array($this, 'ajax_save_settings'));
    add_action('wp_ajax_wwcc_save_cookies', array($this, 'ajax_save_cookies'));
  }

  public function add_menu() {
    add_options_page(
      __('Cookie Consent', 'ww-cookie-consent'),
      __('Cookie Consent', 'ww-cookie-consent'),
      'manage_options',
      'ww-cookie-consent',
      array($this, 'render_page')
    );
  }

  public function enqueue_assets($hook) {
    if ($hook !== 'settings_page_ww-cookie-consent') {
      return;
    }

    wp_enqueue_style(
      'wwcc-admin',
      WWCC_PLUGIN_URL . 'assets/admin.css',
      array(),
      WWCC_VERSION
    );

    wp_enqueue_script(
      'wwcc-admin',
      WWCC_PLUGIN_URL . 'assets/admin.js',
      array(),
      WWCC_VERSION,
      true
    );

    wp_localize_script('wwcc-admin', 'wwccAdmin', array(
      'ajaxUrl'  => admin_url('admin-ajax.php'),
      'nonce'    => wp_create_nonce('wwcc_admin'),
      'homeUrl'  => home_url('/'),
      'strings'  => array(
        'saved'        => __('Settings saved.', 'ww-cookie-consent'),
        'error'        => __('An error occurred.', 'ww-cookie-consent'),
        'scanning'     => __('Scanning...', 'ww-cookie-consent'),
        'scanDone'     => __('Scan complete.', 'ww-cookie-consent'),
        'noNew'        => __('No new cookies found.', 'ww-cookie-consent'),
        'confirmDelete'=> __('Remove this cookie?', 'ww-cookie-consent'),
      ),
    ));
  }

  public function render_page() {
    $settings = wwcc_get_settings();
    $cookies = wwcc_get_cookies();
    $pages = get_pages(array('post_status' => 'publish,draft'));
    $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'general';
    ?>
    <div class="wrap wwcc-wrap">
      <h1><?php esc_html_e('Cookie Consent', 'ww-cookie-consent'); ?></h1>

      <nav class="nav-tab-wrapper wwcc-tabs">
        <a href="?page=ww-cookie-consent&tab=general" class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">
          <?php esc_html_e('General', 'ww-cookie-consent'); ?>
        </a>
        <a href="?page=ww-cookie-consent&tab=cookies" class="nav-tab <?php echo $active_tab === 'cookies' ? 'nav-tab-active' : ''; ?>">
          <?php esc_html_e('Cookies', 'ww-cookie-consent'); ?>
        </a>
        <a href="?page=ww-cookie-consent&tab=policy" class="nav-tab <?php echo $active_tab === 'policy' ? 'nav-tab-active' : ''; ?>">
          <?php esc_html_e('Policy Page', 'ww-cookie-consent'); ?>
        </a>
        <a href="?page=ww-cookie-consent&tab=integration" class="nav-tab <?php echo $active_tab === 'integration' ? 'nav-tab-active' : ''; ?>">
          <?php esc_html_e('Integration', 'ww-cookie-consent'); ?>
        </a>
      </nav>

      <div class="wwcc-notice" id="wwcc-notice" style="display:none;"></div>

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
    <form id="wwcc-general-form" class="wwcc-form">
      <table class="form-table">
        <tr>
          <th scope="row"><label for="wwcc-banner-style"><?php esc_html_e('Banner style', 'ww-cookie-consent'); ?></label></th>
          <td>
            <select id="wwcc-banner-style" name="banner_style">
              <option value="bar" <?php selected($settings['banner_style'], 'bar'); ?>><?php esc_html_e('Bottom bar', 'ww-cookie-consent'); ?></option>
              <option value="modal" <?php selected($settings['banner_style'], 'modal'); ?>><?php esc_html_e('Centered modal', 'ww-cookie-consent'); ?></option>
              <option value="corner" <?php selected($settings['banner_style'], 'corner'); ?>><?php esc_html_e('Corner popup', 'ww-cookie-consent'); ?></option>
            </select>
          </td>
        </tr>
        <tr>
          <th scope="row"><label for="wwcc-primary-color"><?php esc_html_e('Primary color', 'ww-cookie-consent'); ?></label></th>
          <td>
            <input type="color" id="wwcc-primary-color" name="primary_color" value="<?php echo esc_attr($settings['primary_color']); ?>" />
          </td>
        </tr>
        <tr>
          <th scope="row"><label for="wwcc-heading"><?php esc_html_e('Heading text', 'ww-cookie-consent'); ?></label></th>
          <td>
            <input type="text" id="wwcc-heading" name="heading_text" value="<?php echo esc_attr($settings['heading_text']); ?>" class="regular-text" />
          </td>
        </tr>
        <tr>
          <th scope="row"><label for="wwcc-description"><?php esc_html_e('Description text', 'ww-cookie-consent'); ?></label></th>
          <td>
            <textarea id="wwcc-description" name="description_text" rows="3" class="large-text"><?php echo esc_textarea($settings['description_text']); ?></textarea>
          </td>
        </tr>
        <tr>
          <th scope="row"><label for="wwcc-accept"><?php esc_html_e('Accept button text', 'ww-cookie-consent'); ?></label></th>
          <td><input type="text" id="wwcc-accept" name="accept_text" value="<?php echo esc_attr($settings['accept_text']); ?>" class="regular-text" /></td>
        </tr>
        <tr>
          <th scope="row"><label for="wwcc-reject"><?php esc_html_e('Reject button text', 'ww-cookie-consent'); ?></label></th>
          <td><input type="text" id="wwcc-reject" name="reject_text" value="<?php echo esc_attr($settings['reject_text']); ?>" class="regular-text" /></td>
        </tr>
        <tr>
          <th scope="row"><label for="wwcc-customize"><?php esc_html_e('Customize button text', 'ww-cookie-consent'); ?></label></th>
          <td><input type="text" id="wwcc-customize" name="customize_text" value="<?php echo esc_attr($settings['customize_text']); ?>" class="regular-text" /></td>
        </tr>
        <tr>
          <th scope="row"><label for="wwcc-save"><?php esc_html_e('Save preferences button text', 'ww-cookie-consent'); ?></label></th>
          <td><input type="text" id="wwcc-save" name="save_text" value="<?php echo esc_attr($settings['save_text']); ?>" class="regular-text" /></td>
        </tr>
        <tr>
          <th scope="row"><?php esc_html_e('Floating settings button', 'ww-cookie-consent'); ?></th>
          <td>
            <label>
              <input type="checkbox" name="show_floating_btn" value="1" <?php checked($settings['show_floating_btn']); ?> />
              <?php esc_html_e('Show a small floating button so visitors can re-open their cookie preferences', 'ww-cookie-consent'); ?>
            </label>
          </td>
        </tr>
      </table>
      <p class="submit">
        <button type="submit" class="button button-primary"><?php esc_html_e('Save Settings', 'ww-cookie-consent'); ?></button>
      </p>
    </form>
    <?php
  }

  private function render_cookies_tab($cookies) {
    ?>
    <div class="wwcc-cookies-tab">
      <div class="wwcc-cookies-toolbar">
        <button type="button" class="button" id="wwcc-scan-btn"><?php esc_html_e('Scan Cookies', 'ww-cookie-consent'); ?></button>
        <button type="button" class="button" id="wwcc-add-cookie-btn"><?php esc_html_e('Add Cookie', 'ww-cookie-consent'); ?></button>
      </div>

      <div id="wwcc-scan-results" style="display:none;" class="wwcc-scan-results"></div>

      <form id="wwcc-cookies-form">
        <table class="wp-list-table widefat striped wwcc-cookie-table" id="wwcc-cookie-table">
          <thead>
            <tr>
              <th><?php esc_html_e('Name', 'ww-cookie-consent'); ?></th>
              <th><?php esc_html_e('Category', 'ww-cookie-consent'); ?></th>
              <th><?php esc_html_e('Provider', 'ww-cookie-consent'); ?></th>
              <th><?php esc_html_e('Duration', 'ww-cookie-consent'); ?></th>
              <th><?php esc_html_e('Description', 'ww-cookie-consent'); ?></th>
              <th style="width:60px;"></th>
            </tr>
          </thead>
          <tbody id="wwcc-cookie-tbody">
            <?php if (empty($cookies)) : ?>
              <tr class="wwcc-no-cookies"><td colspan="6"><?php esc_html_e('No cookies registered yet.', 'ww-cookie-consent'); ?></td></tr>
            <?php else : ?>
              <?php foreach ($cookies as $i => $cookie) : ?>
                <tr data-index="<?php echo $i; ?>">
                  <td><input type="text" name="cookies[<?php echo $i; ?>][name]" value="<?php echo esc_attr($cookie['name']); ?>" class="regular-text" /></td>
                  <td>
                    <select name="cookies[<?php echo $i; ?>][category]">
                      <option value="necessary" <?php selected($cookie['category'], 'necessary'); ?>><?php esc_html_e('Necessary', 'ww-cookie-consent'); ?></option>
                      <option value="analytics" <?php selected($cookie['category'], 'analytics'); ?>><?php esc_html_e('Analytics', 'ww-cookie-consent'); ?></option>
                      <option value="marketing" <?php selected($cookie['category'], 'marketing'); ?>><?php esc_html_e('Marketing', 'ww-cookie-consent'); ?></option>
                      <option value="preferences" <?php selected($cookie['category'], 'preferences'); ?>><?php esc_html_e('Preferences', 'ww-cookie-consent'); ?></option>
                    </select>
                  </td>
                  <td><input type="text" name="cookies[<?php echo $i; ?>][provider]" value="<?php echo esc_attr($cookie['provider'] ?? ''); ?>" /></td>
                  <td><input type="text" name="cookies[<?php echo $i; ?>][duration]" value="<?php echo esc_attr($cookie['duration'] ?? ''); ?>" style="width:100px;" /></td>
                  <td><input type="text" name="cookies[<?php echo $i; ?>][description]" value="<?php echo esc_attr($cookie['description'] ?? ''); ?>" class="large-text" /></td>
                  <td><button type="button" class="button wwcc-remove-cookie" title="<?php esc_attr_e('Remove', 'ww-cookie-consent'); ?>">&times;</button></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
        <p class="submit">
          <button type="submit" class="button button-primary"><?php esc_html_e('Save Cookies', 'ww-cookie-consent'); ?></button>
        </p>
      </form>
    </div>
    <iframe id="wwcc-scan-iframe" style="display:none;width:0;height:0;border:0;" sandbox="allow-same-origin allow-scripts"></iframe>
    <?php
  }

  private function render_policy_tab($settings, $pages) {
    ?>
    <form id="wwcc-policy-form" class="wwcc-form">
      <table class="form-table">
        <tr>
          <th scope="row"><label for="wwcc-policy-page"><?php esc_html_e('Cookie policy page', 'ww-cookie-consent'); ?></label></th>
          <td>
            <select id="wwcc-policy-page" name="policy_page_id">
              <option value="0"><?php esc_html_e('— Select a page —', 'ww-cookie-consent'); ?></option>
              <?php foreach ($pages as $page) : ?>
                <option value="<?php echo $page->ID; ?>" <?php selected($settings['policy_page_id'], $page->ID); ?>>
                  <?php echo esc_html($page->post_title); ?>
                </option>
              <?php endforeach; ?>
            </select>
            <p class="description">
              <?php esc_html_e('Select a page or use the button below to create one. Add the shortcode [ww_cookie_policy] to its content.', 'ww-cookie-consent'); ?>
            </p>
          </td>
        </tr>
      </table>
      <p class="submit">
        <button type="submit" class="button button-primary"><?php esc_html_e('Save Settings', 'ww-cookie-consent'); ?></button>
      </p>
    </form>
    <?php
    if ($settings['policy_page_id']) :
      $link = get_permalink($settings['policy_page_id']);
      if ($link) :
    ?>
      <p><a href="<?php echo esc_url($link); ?>" target="_blank" class="button"><?php esc_html_e('Preview Policy Page', 'ww-cookie-consent'); ?></a></p>
    <?php
      endif;
    endif;
  }

  private function render_integration_tab($settings) {
    ?>
    <form id="wwcc-integration-form" class="wwcc-form">
      <table class="form-table">
        <tr>
          <th scope="row"><?php esc_html_e('Google Consent Mode v2', 'ww-cookie-consent'); ?></th>
          <td>
            <label>
              <input type="checkbox" name="consent_mode" value="1" <?php checked($settings['consent_mode']); ?> />
              <?php esc_html_e('Enable Consent Mode v2 (outputs consent defaults in &lt;head&gt; before GTM)', 'ww-cookie-consent'); ?>
            </label>
          </td>
        </tr>
        <tr>
          <th scope="row"><label for="wwcc-gtm-id"><?php esc_html_e('GTM Container ID', 'ww-cookie-consent'); ?></label></th>
          <td>
            <input type="text" id="wwcc-gtm-id" name="gtm_container_id" value="<?php echo esc_attr($settings['gtm_container_id']); ?>" placeholder="GTM-XXXXXXX" class="regular-text" />
            <p class="description"><?php esc_html_e('For reference only — this plugin does not inject the GTM snippet.', 'ww-cookie-consent'); ?></p>
          </td>
        </tr>
      </table>

      <?php if ($settings['consent_mode']) : ?>
      <div class="wwcc-code-preview">
        <h3><?php esc_html_e('Consent Mode defaults (auto-injected in &lt;head&gt;)', 'ww-cookie-consent'); ?></h3>
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
        <button type="submit" class="button button-primary"><?php esc_html_e('Save Settings', 'ww-cookie-consent'); ?></button>
      </p>
    </form>
    <?php
  }

  public function ajax_save_settings() {
    check_ajax_referer('wwcc_admin', 'nonce');

    if (!current_user_can('manage_options')) {
      wp_send_json_error('Unauthorized');
    }

    $current = wwcc_get_settings();
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

    update_option('wwcc_settings', $current);
    wp_send_json_success(array('message' => __('Settings saved.', 'ww-cookie-consent')));
  }

  public function ajax_save_cookies() {
    check_ajax_referer('wwcc_admin', 'nonce');

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

    update_option('wwcc_cookies', $cookies);
    wp_send_json_success(array('message' => __('Cookies saved.', 'ww-cookie-consent')));
  }
}
