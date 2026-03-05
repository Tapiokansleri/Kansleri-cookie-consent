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

    $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'general';
    if ($active_tab === 'statistics') {
      wp_enqueue_script(
        'kcc-chartjs',
        'https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js',
        array(),
        '4',
        true
      );
    }

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
        'scanBtn'      => __('Scan Cookies', 'kansleri-cookie-consent'),
        'invalidJson'  => __('Invalid JSON format. Please import a valid cookie array.', 'kansleri-cookie-consent'),
        'importDone'   => __('Cookies imported into the table. Save cookies to persist changes.', 'kansleri-cookie-consent'),
        'importEmpty'  => __('No valid cookies found in imported JSON.', 'kansleri-cookie-consent'),
        'exportEmpty'  => __('No cookie rows available to export.', 'kansleri-cookie-consent'),
        'promptCopied' => __('AI prompt copied to clipboard.', 'kansleri-cookie-consent'),
        'promptFailed' => __('Could not copy prompt automatically. Copy it manually from the prompt dialog.', 'kansleri-cookie-consent'),
        'allDone'      => __('All cookies are done! Every cookie has a description.', 'kansleri-cookie-consent'),
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

      <?php $this->render_status_label(); ?>

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
        <a href="?page=kansleri-cookie-consent&tab=statistics" class="nav-tab <?php echo $active_tab === 'statistics' ? 'nav-tab-active' : ''; ?>">
          <?php esc_html_e('Statistics', 'kansleri-cookie-consent'); ?>
        </a>
        <a href="?page=kansleri-cookie-consent&tab=documentations" class="nav-tab <?php echo $active_tab === 'documentations' ? 'nav-tab-active' : ''; ?>">
          <?php esc_html_e('Documentations', 'kansleri-cookie-consent'); ?>
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
        case 'statistics':
          $this->render_statistics_tab();
          break;
        case 'documentations':
          $this->render_documentations_tab();
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
          <th scope="row"><label for="kcc-text-style-mode"><?php esc_html_e('Text and font style', 'kansleri-cookie-consent'); ?></label></th>
          <td>
            <select id="kcc-text-style-mode" name="text_style_mode">
              <option value="theme" <?php selected($settings['text_style_mode'] ?? 'theme', 'theme'); ?>><?php esc_html_e('Theme style (inherit site typography)', 'kansleri-cookie-consent'); ?></option>
              <option value="plugin" <?php selected($settings['text_style_mode'] ?? 'theme', 'plugin'); ?>><?php esc_html_e('Plugin style (strict font sizes/colors)', 'kansleri-cookie-consent'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('Use Theme style by default. Switch to Plugin style if your theme typography makes the banner hard to read.', 'kansleri-cookie-consent'); ?></p>
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
    $has_incomplete = false;
    foreach ($cookies as $c) {
      if (empty($c['description'])) {
        $has_incomplete = true;
        break;
      }
    }
    ?>
    <div class="kcc-cookies-tab">
      <div class="kcc-cookies-toolbar">
        <button type="button" class="button" id="kcc-scan-btn"><?php esc_html_e('Scan Cookies', 'kansleri-cookie-consent'); ?></button>
        <button type="button" class="button" id="kcc-add-cookie-btn"><?php esc_html_e('Add Cookie', 'kansleri-cookie-consent'); ?></button>
      </div>

      <div id="kcc-ai-helper" class="kcc-ai-helper" <?php if (!$has_incomplete) echo 'style="display:none;"'; ?>>
        <h4><?php esc_html_e('Need help describing these cookies?', 'kansleri-cookie-consent'); ?></h4>
        <ol class="kcc-ai-helper__steps">
          <li><?php esc_html_e('Click the button below to copy a ready-made AI prompt with your cookie data.', 'kansleri-cookie-consent'); ?></li>
          <li><?php esc_html_e('Paste the prompt into an AI tool (ChatGPT, Claude, Gemini, etc.).', 'kansleri-cookie-consent'); ?></li>
          <li><?php esc_html_e('The AI will return improved JSON with clear cookie descriptions.', 'kansleri-cookie-consent'); ?></li>
          <li><?php esc_html_e('Copy the AI response and paste it into the text box that appears below.', 'kansleri-cookie-consent'); ?></li>
          <li><?php esc_html_e('Click Import — cookies are saved automatically.', 'kansleri-cookie-consent'); ?></li>
        </ol>
        <button type="button" class="button button-primary" id="kcc-copy-ai-prompt-btn">
          <span class="dashicons dashicons-clipboard" style="margin-right:4px;vertical-align:text-bottom;"></span>
          <?php esc_html_e('Copy AI Prompt to Clipboard', 'kansleri-cookie-consent'); ?>
        </button>

        <div id="kcc-import-json-area" class="kcc-import-json-area" style="display:none;">
          <label for="kcc-import-json-textarea"><strong><?php esc_html_e('Paste the AI response here:', 'kansleri-cookie-consent'); ?></strong></label>
          <textarea id="kcc-import-json-textarea" rows="8" class="large-text" placeholder="<?php esc_attr_e('[{"name":"_ga","category":"analytics", ...}]', 'kansleri-cookie-consent'); ?>"></textarea>
          <button type="button" class="button button-primary" id="kcc-import-json-btn">
            <?php esc_html_e('Import', 'kansleri-cookie-consent'); ?>
          </button>
        </div>
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
    $sitekit_active = defined('GOOGLESITEKIT_VERSION');
    $sitekit_handles_consent = KCC_Consent_Mode::site_kit_handles_consent();
    $wp_consent_api_active = KCC_WP_Consent_API::is_active();
    ?>

    <h3><?php esc_html_e('Detected integrations', 'kansleri-cookie-consent'); ?></h3>
    <div class="kcc-integrations-status">
      <div class="kcc-integration-badge <?php echo $sitekit_active ? 'kcc-integration-badge--active' : 'kcc-integration-badge--inactive'; ?>">
        <span class="dashicons <?php echo $sitekit_active ? 'dashicons-yes-alt' : 'dashicons-minus'; ?>"></span>
        <div>
          <strong>Google Site Kit</strong><br>
          <?php if ($sitekit_active) : ?>
            <?php if ($sitekit_handles_consent) : ?>
              <span class="kcc-integration-detail"><?php esc_html_e('Active — Site Kit manages consent defaults. This plugin sends consent updates only.', 'kansleri-cookie-consent'); ?></span>
            <?php else : ?>
              <span class="kcc-integration-detail"><?php esc_html_e('Active — Site Kit consent mode not enabled. This plugin handles all consent signals.', 'kansleri-cookie-consent'); ?></span>
            <?php endif; ?>
          <?php else : ?>
            <span class="kcc-integration-detail"><?php esc_html_e('Not installed. No action needed — this plugin handles consent signals independently.', 'kansleri-cookie-consent'); ?></span>
          <?php endif; ?>
        </div>
      </div>

      <div class="kcc-integration-badge <?php echo $wp_consent_api_active ? 'kcc-integration-badge--active' : 'kcc-integration-badge--inactive'; ?>">
        <span class="dashicons <?php echo $wp_consent_api_active ? 'dashicons-yes-alt' : 'dashicons-minus'; ?>"></span>
        <div>
          <strong>WP Consent API</strong><br>
          <?php if ($wp_consent_api_active) : ?>
            <span class="kcc-integration-detail"><?php esc_html_e('Active — consent status is shared with other plugins via the WP Consent API.', 'kansleri-cookie-consent'); ?></span>
          <?php else : ?>
            <span class="kcc-integration-detail"><?php esc_html_e('Not installed. Optional — install it if other plugins need to read consent status.', 'kansleri-cookie-consent'); ?></span>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <hr>

    <form id="kcc-integration-form" class="kcc-form">
      <table class="form-table">
        <tr>
          <th scope="row"><?php esc_html_e('Google Consent Mode v2', 'kansleri-cookie-consent'); ?></th>
          <td>
            <label>
              <input type="checkbox" name="consent_mode" value="1" <?php checked($settings['consent_mode'] || $sitekit_active); ?> <?php if ($sitekit_active) echo 'disabled'; ?> />
              <?php esc_html_e('Enable Consent Mode v2 (outputs consent defaults in &lt;head&gt; before GTM)', 'kansleri-cookie-consent'); ?>
              <?php if ($sitekit_active) : ?>
                <input type="hidden" name="consent_mode" value="1" />
              <?php endif; ?>
            </label>
            <?php if ($sitekit_active) : ?>
              <p class="description" style="color:#065f46;">
                <span class="dashicons dashicons-lock" style="font-size:16px;width:16px;height:16px;vertical-align:text-bottom;"></span>
                <?php esc_html_e('Forced on because Google Site Kit is active. Consent Mode is required for Site Kit to receive consent signals.', 'kansleri-cookie-consent'); ?>
              </p>
            <?php endif; ?>
            <p class="description">
              <?php esc_html_e('Consent Mode tells Google tags what the visitor has allowed (for example analytics or ads). This plugin sends a denied-by-default signal before tags run, and sends an updated signal after the visitor chooses. It controls consent signals only: you still need to install GTM/GA scripts separately.', 'kansleri-cookie-consent'); ?>
            </p>
            <?php if ($sitekit_handles_consent) : ?>
              <p class="description" style="color:#b45309;">
                <span class="dashicons dashicons-info" style="font-size:16px;width:16px;height:16px;vertical-align:text-bottom;"></span>
                <?php esc_html_e('Site Kit is handling consent defaults. This plugin will only send consent updates after user choice — no duplicate defaults.', 'kansleri-cookie-consent'); ?>
              </p>
            <?php endif; ?>
          </td>
        </tr>
        <tr>
          <th scope="row"><label for="kcc-gtm-id"><?php esc_html_e('GTM Container ID', 'kansleri-cookie-consent'); ?></label></th>
          <td>
            <input type="text" id="kcc-gtm-id" name="gtm_container_id" value="<?php echo esc_attr($settings['gtm_container_id']); ?>" placeholder="GTM-XXXXXXX" class="regular-text" />
            <p class="description"><?php esc_html_e('The Container ID is your Google Tag Manager workspace identifier (format: GTM-XXXXXXX), found in GTM top bar or Admin. In this plugin it is a reference field to keep configuration documented; it does not automatically embed the GTM code snippet.', 'kansleri-cookie-consent'); ?></p>
          </td>
        </tr>
      </table>

      <?php if ($settings['consent_mode'] && !$sitekit_handles_consent) : ?>
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

  private function render_documentations_tab() {
    ?>
    <div class="kcc-docs">
      <h2><?php esc_html_e('Installation guide', 'kansleri-cookie-consent'); ?></h2>
      <ol>
        <li><?php esc_html_e('Activate the plugin and open Settings > Cookie Consent.', 'kansleri-cookie-consent'); ?></li>
        <li><?php esc_html_e('Configure banner texts, style, and button labels in the General tab.', 'kansleri-cookie-consent'); ?></li>
        <li><?php esc_html_e('Open the Integration tab and enable Google Consent Mode v2 if you use GTM/Google tags.', 'kansleri-cookie-consent'); ?></li>
        <li><?php esc_html_e('Open the Cookies tab, run Scan Cookies, then review and categorize unknown cookies.', 'kansleri-cookie-consent'); ?></li>
        <li><?php esc_html_e('If unknown cookies are found, an AI helper appears: copy the prompt, paste it into an AI tool, paste the response back into the text box, click Import, then Save Cookies.', 'kansleri-cookie-consent'); ?></li>
        <li><?php esc_html_e('Assign or create your Cookie Policy page in the Policy Page tab.', 'kansleri-cookie-consent'); ?></li>
      </ol>

      <h2><?php esc_html_e('What each option means', 'kansleri-cookie-consent'); ?></h2>

      <h3><?php esc_html_e('General tab', 'kansleri-cookie-consent'); ?></h3>
      <ul>
        <li><strong><?php esc_html_e('Banner style', 'kansleri-cookie-consent'); ?>:</strong> <?php esc_html_e('Choose visual placement of the banner (bar, modal, corner).', 'kansleri-cookie-consent'); ?></li>
        <li><strong><?php esc_html_e('Primary color', 'kansleri-cookie-consent'); ?>:</strong> <?php esc_html_e('Controls the main accent color used by buttons and links.', 'kansleri-cookie-consent'); ?></li>
        <li><strong><?php esc_html_e('Text and font style', 'kansleri-cookie-consent'); ?>:</strong> <?php esc_html_e('Theme style inherits site typography. Plugin style uses strict sizes/colors for consistent readability.', 'kansleri-cookie-consent'); ?></li>
        <li><strong><?php esc_html_e('Heading/Description/Button texts', 'kansleri-cookie-consent'); ?>:</strong> <?php esc_html_e('Customize visible wording in the consent banner.', 'kansleri-cookie-consent'); ?></li>
        <li><strong><?php esc_html_e('Floating settings button', 'kansleri-cookie-consent'); ?>:</strong> <?php esc_html_e('Lets visitors re-open cookie settings after they close the banner.', 'kansleri-cookie-consent'); ?></li>
      </ul>

      <h3><?php esc_html_e('Cookies tab', 'kansleri-cookie-consent'); ?></h3>
      <ul>
        <li><strong><?php esc_html_e('Scan Cookies', 'kansleri-cookie-consent'); ?>:</strong> <?php esc_html_e('Scans detected cookies and auto-maps known cookies to categories when possible.', 'kansleri-cookie-consent'); ?></li>
        <li><strong><?php esc_html_e('AI Helper', 'kansleri-cookie-consent'); ?>:</strong> <?php esc_html_e('Appears after scanning when unknown cookies are found. Copies a ready prompt to paste into an AI tool. After the AI responds, paste the JSON back into the text box and click Import.', 'kansleri-cookie-consent'); ?></li>
        <li><strong><?php esc_html_e('Save Cookies', 'kansleri-cookie-consent'); ?>:</strong> <?php esc_html_e('Persists current cookie table values to be used in policy output and consent details.', 'kansleri-cookie-consent'); ?></li>
      </ul>

      <h3><?php esc_html_e('Policy Page tab', 'kansleri-cookie-consent'); ?></h3>
      <ul>
        <li><strong><?php esc_html_e('Cookie policy page', 'kansleri-cookie-consent'); ?>:</strong> <?php esc_html_e('Selects which page is used for cookie policy content generated by shortcode.', 'kansleri-cookie-consent'); ?></li>
      </ul>

      <h3><?php esc_html_e('Integration tab', 'kansleri-cookie-consent'); ?></h3>
      <ul>
        <li><strong><?php esc_html_e('Google Consent Mode v2', 'kansleri-cookie-consent'); ?>:</strong> <?php esc_html_e('A Google framework for privacy-safe tag behavior. Before consent, storage signals are denied; after user choice, signals are updated (for example analytics_storage=granted). This helps GTM/Google tags respect user consent state.', 'kansleri-cookie-consent'); ?></li>
        <li><strong><?php esc_html_e('GTM Container ID', 'kansleri-cookie-consent'); ?>:</strong> <?php esc_html_e('Your GTM container identifier (for example GTM-N6828NL). It identifies which tag container your site uses. This field is informational in this plugin and does not inject GTM scripts.', 'kansleri-cookie-consent'); ?></li>
      </ul>

      <h2><?php esc_html_e('Troubleshooting', 'kansleri-cookie-consent'); ?></h2>
      <ul>
        <li><?php esc_html_e('If GTM Preview does not show consent updates, verify Consent Mode v2 is enabled and test in a fresh browser session without existing consent cookie.', 'kansleri-cookie-consent'); ?></li>
        <li><?php esc_html_e('If banner typography looks odd with your theme, switch General > Text and font style to Plugin style.', 'kansleri-cookie-consent'); ?></li>
      </ul>
    </div>
    <?php
  }

  private function render_statistics_tab() {
    $period = isset($_GET['period']) ? absint($_GET['period']) : 30;
    if (!in_array($period, array(7, 30, 90), true)) {
      $period = 30;
    }

    $stats = KCC_Stats::get_stats($period);
    $totals = KCC_Stats::get_totals($period);
    $accept_pct = $totals['total'] ? round($totals['accept'] / $totals['total'] * 100) : 0;
    $reject_pct = $totals['total'] ? round($totals['reject'] / $totals['total'] * 100) : 0;
    $custom_pct = $totals['total'] ? round($totals['custom'] / $totals['total'] * 100) : 0;

    $chart_labels = array();
    $chart_accept = array();
    $chart_reject = array();
    $chart_custom = array();
    foreach ($stats as $date => $day) {
      $chart_labels[] = wp_date('j.n.', strtotime($date));
      $chart_accept[] = $day['accept'];
      $chart_reject[] = $day['reject'];
      $chart_custom[] = $day['custom'];
    }
    ?>
    <div class="kcc-stats">
      <div class="kcc-stats__period">
        <?php
        $base = '?page=kansleri-cookie-consent&tab=statistics&period=';
        foreach (array(7, 30, 90) as $p) :
        ?>
          <a href="<?php echo esc_url(admin_url('options-general.php' . $base . $p)); ?>"
             class="button <?php echo $period === $p ? 'button-primary' : ''; ?>">
            <?php
            printf(
              /* translators: %d: number of days */
              esc_html__('%d days', 'kansleri-cookie-consent'),
              $p
            );
            ?>
          </a>
        <?php endforeach; ?>
      </div>

      <div class="kcc-stats__cards">
        <div class="kcc-stats__card kcc-stats__card--total">
          <div class="kcc-stats__card-value"><?php echo esc_html($totals['total']); ?></div>
          <div class="kcc-stats__card-label"><?php esc_html_e('Total responses', 'kansleri-cookie-consent'); ?></div>
        </div>
        <div class="kcc-stats__card kcc-stats__card--accept">
          <div class="kcc-stats__card-value"><?php echo esc_html($totals['accept']); ?></div>
          <div class="kcc-stats__card-label"><?php esc_html_e('Accepted all', 'kansleri-cookie-consent'); ?> <span class="kcc-stats__pct"><?php echo esc_html($accept_pct); ?>%</span></div>
        </div>
        <div class="kcc-stats__card kcc-stats__card--reject">
          <div class="kcc-stats__card-value"><?php echo esc_html($totals['reject']); ?></div>
          <div class="kcc-stats__card-label"><?php esc_html_e('Rejected all', 'kansleri-cookie-consent'); ?> <span class="kcc-stats__pct"><?php echo esc_html($reject_pct); ?>%</span></div>
        </div>
        <div class="kcc-stats__card kcc-stats__card--custom">
          <div class="kcc-stats__card-value"><?php echo esc_html($totals['custom']); ?></div>
          <div class="kcc-stats__card-label"><?php esc_html_e('Custom selection', 'kansleri-cookie-consent'); ?> <span class="kcc-stats__pct"><?php echo esc_html($custom_pct); ?>%</span></div>
        </div>
      </div>

      <?php if ($totals['total'] > 0) : ?>
      <div class="kcc-stats__bar">
        <?php if ($accept_pct > 0) : ?>
          <div class="kcc-stats__bar-seg kcc-stats__bar-seg--accept" style="width:<?php echo $accept_pct; ?>%;" title="<?php esc_attr_e('Accepted all', 'kansleri-cookie-consent'); ?>: <?php echo $accept_pct; ?>%"><?php echo $accept_pct; ?>%</div>
        <?php endif; ?>
        <?php if ($custom_pct > 0) : ?>
          <div class="kcc-stats__bar-seg kcc-stats__bar-seg--custom" style="width:<?php echo $custom_pct; ?>%;" title="<?php esc_attr_e('Custom selection', 'kansleri-cookie-consent'); ?>: <?php echo $custom_pct; ?>%"><?php echo $custom_pct; ?>%</div>
        <?php endif; ?>
        <?php if ($reject_pct > 0) : ?>
          <div class="kcc-stats__bar-seg kcc-stats__bar-seg--reject" style="width:<?php echo $reject_pct; ?>%;" title="<?php esc_attr_e('Rejected all', 'kansleri-cookie-consent'); ?>: <?php echo $reject_pct; ?>%"><?php echo $reject_pct; ?>%</div>
        <?php endif; ?>
      </div>
      <div class="kcc-stats__legend">
        <span class="kcc-stats__legend-item"><span class="kcc-stats__dot kcc-stats__dot--accept"></span> <?php esc_html_e('Accepted all', 'kansleri-cookie-consent'); ?></span>
        <span class="kcc-stats__legend-item"><span class="kcc-stats__dot kcc-stats__dot--custom"></span> <?php esc_html_e('Custom selection', 'kansleri-cookie-consent'); ?></span>
        <span class="kcc-stats__legend-item"><span class="kcc-stats__dot kcc-stats__dot--reject"></span> <?php esc_html_e('Rejected all', 'kansleri-cookie-consent'); ?></span>
      </div>
      <?php endif; ?>

      <h3><?php esc_html_e('Daily breakdown', 'kansleri-cookie-consent'); ?></h3>
      <canvas id="kcc-stats-chart" height="250" style="max-width:100%;"></canvas>
      <script>
      (function(){
        var ctx = document.getElementById('kcc-stats-chart');
        if (!ctx || typeof Chart === 'undefined') return;
        new Chart(ctx, {
          type: 'bar',
          data: {
            labels: <?php echo wp_json_encode($chart_labels); ?>,
            datasets: [
              { label: <?php echo wp_json_encode(__('Accepted all', 'kansleri-cookie-consent')); ?>, data: <?php echo wp_json_encode($chart_accept); ?>, backgroundColor: '#10b981', borderRadius: 3 },
              { label: <?php echo wp_json_encode(__('Custom selection', 'kansleri-cookie-consent')); ?>, data: <?php echo wp_json_encode($chart_custom); ?>, backgroundColor: '#f59e0b', borderRadius: 3 },
              { label: <?php echo wp_json_encode(__('Rejected all', 'kansleri-cookie-consent')); ?>, data: <?php echo wp_json_encode($chart_reject); ?>, backgroundColor: '#ef4444', borderRadius: 3 }
            ]
          },
          options: {
            responsive: true,
            plugins: { legend: { position: 'bottom' } },
            scales: {
              x: { stacked: true },
              y: { stacked: true, beginAtZero: true, ticks: { stepSize: 1 } }
            }
          }
        });
      })();
      </script>

      <?php if (!$totals['total']) : ?>
        <p class="kcc-stats__empty"><?php esc_html_e('No consent data recorded yet. Statistics will appear after visitors start interacting with the cookie banner.', 'kansleri-cookie-consent'); ?></p>
      <?php endif; ?>
    </div>
    <?php
  }

  private function render_status_label() {
    $cron_error = get_option('kcc_cron_error');
    if ($cron_error) {
      ?>
      <div class="kcc-status-label kcc-status-label--error">
        <span class="dashicons dashicons-warning"></span>
        <?php
        printf(
          /* translators: %s: error message */
          esc_html__('Cron error: %s', 'kansleri-cookie-consent'),
          esc_html($cron_error)
        );
        ?>
      </div>
      <?php
      return;
    }

    $cookies = kcc_get_cookies();
    $incomplete = get_option('kcc_incomplete_cookies');
    $all_good = !empty($cookies) && (empty($incomplete) || !is_array($incomplete));

    if ($all_good) {
      $next_run = wp_next_scheduled('kcc_daily_cookie_check');
      ?>
      <div class="kcc-status-label kcc-status-label--good">
        <span class="dashicons dashicons-yes-alt"></span>
        <?php esc_html_e('All systems are good!', 'kansleri-cookie-consent'); ?>
        <span class="kcc-status-label__sub">
          &mdash; <?php esc_html_e('The plugin re-checks cookies every day via cron.', 'kansleri-cookie-consent'); ?>
          <?php if ($next_run) : ?>
            <?php
            printf(
              /* translators: %s: date/time of next cron run */
              esc_html__('Next check: %s', 'kansleri-cookie-consent'),
              esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), $next_run))
            );
            ?>
          <?php endif; ?>
        </span>
      </div>
      <?php
    }
  }

  public function ajax_save_settings() {
    @ini_set('display_errors', 0);
    check_ajax_referer('kcc_admin', 'nonce');

    if (!current_user_can('manage_options')) {
      wp_send_json_error('Unauthorized');
    }

    $current = kcc_get_settings();
    $text_fields = array(
      'banner_position',
      'heading_text', 'description_text', 'accept_text',
      'reject_text', 'customize_text', 'save_text',
      'gtm_container_id',
    );

    foreach ($text_fields as $field) {
      if (isset($_POST[$field])) {
        $current[$field] = sanitize_text_field(wp_unslash($_POST[$field]));
      }
    }

    if (isset($_POST['banner_style'])) {
      $style = sanitize_text_field(wp_unslash($_POST['banner_style']));
      $current['banner_style'] = in_array($style, array('bar', 'modal', 'corner'), true) ? $style : 'bar';
    }

    if (isset($_POST['primary_color'])) {
      $color = sanitize_hex_color(wp_unslash($_POST['primary_color']));
      $current['primary_color'] = $color ? $color : '#3D4872';
    }

    if (isset($_POST['text_style_mode'])) {
      $text_style_mode = sanitize_key(wp_unslash($_POST['text_style_mode']));
      $current['text_style_mode'] = in_array($text_style_mode, array('theme', 'plugin'), true) ? $text_style_mode : 'theme';
    }

    if (isset($_POST['policy_page_id'])) {
      $current['policy_page_id'] = absint($_POST['policy_page_id']);
    }

    $current['consent_mode'] = !empty($_POST['consent_mode']);
    $current['show_floating_btn'] = !empty($_POST['show_floating_btn']);

    update_option('kcc_settings', $current, false);
    wp_send_json_success(array('message' => __('Settings saved.', 'kansleri-cookie-consent')));
  }

  public function ajax_save_cookies() {
    @ini_set('display_errors', 0);
    check_ajax_referer('kcc_admin', 'nonce');

    if (!current_user_can('manage_options')) {
      wp_send_json_error('Unauthorized');
    }

    $raw = isset($_POST['cookies']) ? wp_unslash($_POST['cookies']) : array();
    $cookies = array();
    $valid_categories = array('necessary', 'analytics', 'marketing', 'preferences');

    if (is_array($raw)) {
      foreach ($raw as $entry) {
        $name = isset($entry['name']) ? sanitize_text_field($entry['name']) : '';
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
          'provider'    => isset($entry['provider']) ? sanitize_text_field($entry['provider']) : '',
          'duration'    => isset($entry['duration']) ? sanitize_text_field($entry['duration']) : '',
          'description' => isset($entry['description']) ? sanitize_text_field($entry['description']) : '',
        );
      }
    }

    update_option('kcc_cookies', $cookies, false);

    $incomplete = array();
    foreach ($cookies as $c) {
      if (empty($c['description'])) {
        $incomplete[] = $c['name'];
      }
    }
    if (!empty($incomplete)) {
      update_option('kcc_incomplete_cookies', $incomplete, false);
    } else {
      delete_option('kcc_incomplete_cookies');
    }

    wp_send_json_success(array('message' => __('Cookies saved.', 'kansleri-cookie-consent')));
  }
}
