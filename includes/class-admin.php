<?php

if (!defined('ABSPATH')) {
  exit;
}

class KNCC_Admin {

  public function __construct() {
    add_action('admin_menu', array($this, 'add_menu'));
    add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    add_action('wp_ajax_kncc_save_settings', array($this, 'ajax_save_settings'));
    add_action('wp_ajax_kncc_save_cookies', array($this, 'ajax_save_cookies'));
    add_action('wp_ajax_kncc_export_consent_log', array($this, 'ajax_export_consent_log'));
    add_action('wp_ajax_kncc_purge_consent_log', array($this, 'ajax_purge_consent_log'));
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
      'kncc-admin',
      KNCC_PLUGIN_URL . 'assets/admin.css',
      array(),
      KNCC_VERSION
    );

    wp_enqueue_script(
      'kncc-admin',
      KNCC_PLUGIN_URL . 'assets/admin.js',
      array(),
      KNCC_VERSION,
      true
    );

    $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- navigational tab parameter

    wp_localize_script('kncc-admin', 'knccAdmin', array(
      'ajaxUrl'  => admin_url('admin-ajax.php'),
      'nonce'    => wp_create_nonce('kncc_admin'),
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
        'noCookies'    => __('No cookies registered yet.', 'kansleri-cookie-consent'),
        'removeTitle'  => __('Remove', 'kansleri-cookie-consent'),
        'confirmPurge' => __('This will permanently delete all consent log records and statistics. Continue?', 'kansleri-cookie-consent'),
        'purged'       => __('All consent log records and statistics have been purged.', 'kansleri-cookie-consent'),
        'policyCopied' => __('AI prompt for policy page copied to clipboard.', 'kansleri-cookie-consent'),
        /* translators: %d: number of known cookies added by the scanner */
        'knownAdded'   => __('%d known cookie(s) added automatically:', 'kansleri-cookie-consent'),
        /* translators: %d: number of unknown cookies found by the scanner */
        'unknownAdded' => __('%d unknown cookie(s) added — please set their category and description below.', 'kansleri-cookie-consent'),
        'unknownLabel' => __('Unknown', 'kansleri-cookie-consent'),
        'unknownDesc'  => __('Added — categorize and describe in the table below', 'kansleri-cookie-consent'),
        'locale'       => get_locale(),
        'knccLang'     => kncc_get_current_language(),
      ),
    ));

    if ($active_tab === 'policy') {
      $categories = kncc_get_categories();
      $cat_summary = array();
      foreach ($categories as $key => $data) {
        $cat_summary[] = $key . ': ' . $data['description'];
      }
      wp_add_inline_script('kncc-admin', 'window.knccPolicyPromptData=' . wp_json_encode(array(
        'siteName'   => get_bloginfo('name'),
        'siteUrl'    => home_url('/'),
        'locale'     => get_locale(),
        'adminEmail' => get_option('admin_email'),
        'categories' => $cat_summary,
      )) . ';', 'before');
    }
  }

  public function render_page() {
    // Load settings and cookies with per-language overrides for the admin UI.
    $settings = kncc_get_localized_settings();
    $cookies = kncc_get_localized_cookies();
    $pages = get_pages(array('post_status' => 'publish,draft'));
    $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- navigational tab parameter
    ?>
    <div class="wrap kncc-wrap">
      <h1><?php esc_html_e('Cookie Consent', 'kansleri-cookie-consent'); ?></h1>

      <?php $this->render_status_label(); ?>

      <nav class="nav-tab-wrapper kncc-tabs">
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
        <a href="?page=kansleri-cookie-consent&tab=blocking" class="nav-tab <?php echo $active_tab === 'blocking' ? 'nav-tab-active' : ''; ?>">
          <?php esc_html_e('Script Blocking', 'kansleri-cookie-consent'); ?>
        </a>
        <a href="?page=kansleri-cookie-consent&tab=statistics" class="nav-tab <?php echo $active_tab === 'statistics' ? 'nav-tab-active' : ''; ?>">
          <?php esc_html_e('Statistics', 'kansleri-cookie-consent'); ?>
        </a>
        <a href="?page=kansleri-cookie-consent&tab=documentations" class="nav-tab <?php echo $active_tab === 'documentations' ? 'nav-tab-active' : ''; ?>">
          <?php esc_html_e('Documentations', 'kansleri-cookie-consent'); ?>
        </a>
      </nav>

      <?php
      $kncc_current_lang = kncc_get_current_language();
      if ($kncc_current_lang) :
      ?>
      <div class="notice notice-info inline" style="margin:16px 0 16px;padding:10px 14px;">
        <?php
        printf(
          /* translators: %s: language code */
          esc_html__('You are editing settings for language: %s. Switch the admin language to edit other translations. Settings not configured for this language will fall back to the default language.', 'kansleri-cookie-consent'),
          '<strong>' . esc_html(strtoupper($kncc_current_lang)) . '</strong>'
        );
        ?>
      </div>
      <?php endif; ?>

      <div class="kncc-notice" id="kncc-notice" style="display:none;"></div>

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
        case 'blocking':
          $this->render_blocking_tab();
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
    <form id="kncc-general-form" class="kncc-form">
      <table class="form-table">
        <tr>
          <th scope="row"><label for="kncc-banner-style"><?php esc_html_e('Banner style', 'kansleri-cookie-consent'); ?></label></th>
          <td>
            <select id="kncc-banner-style" name="banner_style">
              <option value="bar" <?php selected($settings['banner_style'], 'bar'); ?>><?php esc_html_e('Bottom bar', 'kansleri-cookie-consent'); ?></option>
              <option value="modal" <?php selected($settings['banner_style'], 'modal'); ?>><?php esc_html_e('Centered modal', 'kansleri-cookie-consent'); ?></option>
              <option value="corner" <?php selected($settings['banner_style'], 'corner'); ?>><?php esc_html_e('Corner popup', 'kansleri-cookie-consent'); ?></option>
            </select>
          </td>
        </tr>
        <tr>
          <th scope="row"><label for="kncc-primary-color"><?php esc_html_e('Primary color', 'kansleri-cookie-consent'); ?></label></th>
          <td>
            <input type="color" id="kncc-primary-color" name="primary_color" value="<?php echo esc_attr($settings['primary_color']); ?>" />
          </td>
        </tr>
        <tr>
          <th scope="row"><label for="kncc-text-style-mode"><?php esc_html_e('Text and font style', 'kansleri-cookie-consent'); ?></label></th>
          <td>
            <select id="kncc-text-style-mode" name="text_style_mode">
              <option value="theme" <?php selected($settings['text_style_mode'] ?? 'theme', 'theme'); ?>><?php esc_html_e('Theme style (inherit site typography)', 'kansleri-cookie-consent'); ?></option>
              <option value="plugin" <?php selected($settings['text_style_mode'] ?? 'theme', 'plugin'); ?>><?php esc_html_e('Plugin style (strict font sizes/colors)', 'kansleri-cookie-consent'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('Use Theme style by default. Switch to Plugin style if your theme typography makes the banner hard to read.', 'kansleri-cookie-consent'); ?></p>
          </td>
        </tr>
        <tr>
          <th scope="row"><label for="kncc-heading"><?php esc_html_e('Heading text', 'kansleri-cookie-consent'); ?></label></th>
          <td>
            <input type="text" id="kncc-heading" name="heading_text" value="<?php echo esc_attr($settings['heading_text']); ?>" class="regular-text" />
          </td>
        </tr>
        <tr>
          <th scope="row"><label for="kncc-description"><?php esc_html_e('Description text', 'kansleri-cookie-consent'); ?></label></th>
          <td>
            <textarea id="kncc-description" name="description_text" rows="3" class="large-text"><?php echo esc_textarea($settings['description_text']); ?></textarea>
          </td>
        </tr>
        <tr>
          <th scope="row"><label for="kncc-accept"><?php esc_html_e('Accept button text', 'kansleri-cookie-consent'); ?></label></th>
          <td><input type="text" id="kncc-accept" name="accept_text" value="<?php echo esc_attr($settings['accept_text']); ?>" class="regular-text" /></td>
        </tr>
        <tr>
          <th scope="row"><label for="kncc-reject"><?php esc_html_e('Reject button text', 'kansleri-cookie-consent'); ?></label></th>
          <td><input type="text" id="kncc-reject" name="reject_text" value="<?php echo esc_attr($settings['reject_text']); ?>" class="regular-text" /></td>
        </tr>
        <tr>
          <th scope="row"><label for="kncc-customize"><?php esc_html_e('Customize button text', 'kansleri-cookie-consent'); ?></label></th>
          <td><input type="text" id="kncc-customize" name="customize_text" value="<?php echo esc_attr($settings['customize_text']); ?>" class="regular-text" /></td>
        </tr>
        <tr>
          <th scope="row"><label for="kncc-save"><?php esc_html_e('Save preferences button text', 'kansleri-cookie-consent'); ?></label></th>
          <td><input type="text" id="kncc-save" name="save_text" value="<?php echo esc_attr($settings['save_text']); ?>" class="regular-text" /></td>
        </tr>
        <tr>
          <th scope="row"><?php esc_html_e('Floating settings button', 'kansleri-cookie-consent'); ?></th>
          <td>
            <label>
              <input type="checkbox" name="show_floating_btn" value="1" <?php checked($settings['show_floating_btn']); ?> />
              <?php esc_html_e('Show a small floating button so visitors can re-open their cookie preferences', 'kansleri-cookie-consent'); ?>
            </label>
            <div class="kncc-shortcode-tip" style="margin-top:12px;padding:12px 16px;background:#f0f6fc;border:1px solid #c3d9ed;border-radius:4px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
              <span style="flex:1;min-width:200px;">
                <?php esc_html_e('You can also add a link to open cookie settings anywhere (e.g. footer) using this shortcode:', 'kansleri-cookie-consent'); ?>
                <code id="kncc-shortcode-code" style="margin-left:4px;user-select:all;">[kncc_cookie_settings]</code>
              </span>
              <button type="button" class="button button-small" onclick="navigator.clipboard.writeText('[kncc_cookie_settings]').then(function(){var b=event.target;b.textContent='<?php echo esc_js(__('Copied!', 'kansleri-cookie-consent')); ?>';setTimeout(function(){b.textContent='<?php echo esc_js(__('Copy', 'kansleri-cookie-consent')); ?>';},2000);});">
                <?php esc_html_e('Copy', 'kansleri-cookie-consent'); ?>
              </button>
              <p style="width:100%;margin:4px 0 0;color:#646970;font-size:13px;">
                <?php
                printf(
                  /* translators: %s: shortcode example */
                  esc_html__('Use the text attribute to customize the link text: %s', 'kansleri-cookie-consent'),
                  '<code style="user-select:all;">[kncc_cookie_settings text="' . esc_html__('Manage cookies', 'kansleri-cookie-consent') . '"]</code>'
                );
                ?>
              </p>
            </div>
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
    <div class="kncc-cookies-tab">
      <div class="kncc-cookies-toolbar">
        <button type="button" class="button" id="kncc-scan-btn"><?php esc_html_e('Scan Cookies', 'kansleri-cookie-consent'); ?></button>
        <button type="button" class="button" id="kncc-add-cookie-btn"><?php esc_html_e('Add Cookie', 'kansleri-cookie-consent'); ?></button>
      </div>

      <div id="kncc-ai-helper" class="kncc-ai-helper" <?php if (!$has_incomplete) echo 'style="display:none;"'; ?>>
        <h4><?php esc_html_e('Need help describing these cookies?', 'kansleri-cookie-consent'); ?></h4>
        <ol class="kncc-ai-helper__steps">
          <li><?php esc_html_e('Click the button below to copy a ready-made AI prompt with your cookie data.', 'kansleri-cookie-consent'); ?></li>
          <li><?php esc_html_e('Paste the prompt into an AI tool (ChatGPT, Claude, Gemini, etc.).', 'kansleri-cookie-consent'); ?></li>
          <li><?php esc_html_e('The AI will return improved JSON with clear cookie descriptions.', 'kansleri-cookie-consent'); ?></li>
          <li><?php esc_html_e('Copy the AI response and paste it into the text box that appears below.', 'kansleri-cookie-consent'); ?></li>
          <li><?php esc_html_e('Click Import — cookies are saved automatically.', 'kansleri-cookie-consent'); ?></li>
        </ol>
        <button type="button" class="button button-primary" id="kncc-copy-ai-prompt-btn">
          <span class="dashicons dashicons-clipboard" style="margin-right:4px;vertical-align:text-bottom;"></span>
          <?php esc_html_e('Copy AI Prompt to Clipboard', 'kansleri-cookie-consent'); ?>
        </button>

        <div id="kncc-import-json-area" class="kncc-import-json-area" style="display:none;">
          <label for="kncc-import-json-textarea"><strong><?php esc_html_e('Paste the AI response here:', 'kansleri-cookie-consent'); ?></strong></label>
          <textarea id="kncc-import-json-textarea" rows="8" class="large-text" placeholder="<?php esc_attr_e('[{"name":"_ga","category":"analytics", ...}]', 'kansleri-cookie-consent'); ?>"></textarea>
          <button type="button" class="button button-primary" id="kncc-import-json-btn">
            <?php esc_html_e('Import', 'kansleri-cookie-consent'); ?>
          </button>
        </div>
      </div>

      <div id="kncc-scan-results" style="display:none;" class="kncc-scan-results"></div>

      <form id="kncc-cookies-form">
        <table class="wp-list-table widefat striped kncc-cookie-table" id="kncc-cookie-table">
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
          <tbody id="kncc-cookie-tbody">
            <?php if (empty($cookies)) : ?>
              <tr class="kncc-no-cookies"><td colspan="6"><?php esc_html_e('No cookies registered yet.', 'kansleri-cookie-consent'); ?></td></tr>
            <?php else : ?>
              <?php foreach ($cookies as $i => $cookie) : ?>
                <tr data-index="<?php echo esc_attr( $i ); ?>">
                  <td><input type="text" name="cookies[<?php echo esc_attr( $i ); ?>][name]" value="<?php echo esc_attr($cookie['name']); ?>" class="regular-text" /></td>
                  <td>
                    <select name="cookies[<?php echo esc_attr( $i ); ?>][category]">
                      <option value="necessary" <?php selected($cookie['category'], 'necessary'); ?>><?php esc_html_e('Necessary', 'kansleri-cookie-consent'); ?></option>
                      <option value="analytics" <?php selected($cookie['category'], 'analytics'); ?>><?php esc_html_e('Analytics', 'kansleri-cookie-consent'); ?></option>
                      <option value="marketing" <?php selected($cookie['category'], 'marketing'); ?>><?php esc_html_e('Marketing', 'kansleri-cookie-consent'); ?></option>
                      <option value="preferences" <?php selected($cookie['category'], 'preferences'); ?>><?php esc_html_e('Preferences', 'kansleri-cookie-consent'); ?></option>
                    </select>
                  </td>
                  <td><input type="text" name="cookies[<?php echo esc_attr( $i ); ?>][provider]" value="<?php echo esc_attr($cookie['provider'] ?? ''); ?>" /></td>
                  <td><input type="text" name="cookies[<?php echo esc_attr( $i ); ?>][duration]" value="<?php echo esc_attr($cookie['duration'] ?? ''); ?>" style="width:100px;" /></td>
                  <td><input type="text" name="cookies[<?php echo esc_attr( $i ); ?>][description]" value="<?php echo esc_attr($cookie['description'] ?? ''); ?>" class="large-text" /></td>
                  <td><button type="button" class="button kncc-remove-cookie" title="<?php esc_attr_e('Remove', 'kansleri-cookie-consent'); ?>">&times;</button></td>
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
    <iframe id="kncc-scan-iframe" style="display:none;width:0;height:0;border:0;" sandbox="allow-same-origin allow-scripts"></iframe>
    <?php
  }

  private function render_policy_tab($settings, $pages) {
    $policy_page_id = $settings['policy_page_id'];
    $missing_langs = $this->get_missing_policy_languages($policy_page_id);

    if (!$policy_page_id) : ?>
      <div class="notice notice-warning inline" style="margin:1rem 0;">
        <p><span class="dashicons dashicons-warning" style="color:#d97706;"></span>
          <?php esc_html_e('No cookie policy page has been selected. Please select or create one below.', 'kansleri-cookie-consent'); ?>
        </p>
      </div>
    <?php elseif (!empty($missing_langs)) : ?>
      <div class="notice notice-warning inline" style="margin:1rem 0;">
        <p><span class="dashicons dashicons-warning" style="color:#d97706;"></span>
          <?php
          printf(
            /* translators: %s: comma-separated list of language names */
            esc_html__('Cookie policy page is missing a translation for: %s. Create the translated page and add the [kncc_cookie_policy] shortcode.', 'kansleri-cookie-consent'),
            '<strong>' . esc_html(implode(', ', $missing_langs)) . '</strong>'
          );
          ?>
        </p>
      </div>
    <?php endif; ?>

    <form id="kncc-policy-form" class="kncc-form">
      <table class="form-table">
        <tr>
          <th scope="row"><label for="kncc-policy-page"><?php esc_html_e('Cookie policy page', 'kansleri-cookie-consent'); ?></label></th>
          <td>
            <select id="kncc-policy-page" name="policy_page_id">
              <option value="0"><?php esc_html_e('— Select a page —', 'kansleri-cookie-consent'); ?></option>
              <?php foreach ($pages as $page) : ?>
                <option value="<?php echo esc_attr( $page->ID ); ?>" <?php selected($settings['policy_page_id'], $page->ID); ?>>
                  <?php echo esc_html($page->post_title); ?>
                </option>
              <?php endforeach; ?>
            </select>
            <p class="description">
              <?php esc_html_e('Select a page or use the button below to create one. Add the shortcode [kncc_cookie_policy] to its content.', 'kansleri-cookie-consent'); ?>
            </p>
          </td>
        </tr>
        <tr>
          <th scope="row"><label for="kncc-policy-style-mode"><?php esc_html_e('Policy page style', 'kansleri-cookie-consent'); ?></label></th>
          <td>
            <select id="kncc-policy-style-mode" name="policy_style_mode">
              <option value="theme" <?php selected($settings['policy_style_mode'] ?? 'theme', 'theme'); ?>><?php esc_html_e('Theme style (inherit site typography)', 'kansleri-cookie-consent'); ?></option>
              <option value="plugin" <?php selected($settings['policy_style_mode'] ?? 'theme', 'plugin'); ?>><?php esc_html_e('Plugin style (strict font sizes/colors)', 'kansleri-cookie-consent'); ?></option>
            </select>
            <p class="description">
              <?php esc_html_e('Controls the styling of the [kncc_cookie_policy] cookie declaration table.', 'kansleri-cookie-consent'); ?>
            </p>
          </td>
        </tr>
      </table>
      <p class="submit">
        <button type="submit" class="button button-primary"><?php esc_html_e('Save Settings', 'kansleri-cookie-consent'); ?></button>
      </p>
    </form>
    <?php
    if ($policy_page_id) :
      $link = get_permalink($policy_page_id);
      if ($link) :
    ?>
      <p><a href="<?php echo esc_url($link); ?>" target="_blank" class="button"><?php esc_html_e('Preview Policy Page', 'kansleri-cookie-consent'); ?></a></p>
    <?php
      endif;
    endif;
    ?>

    <div class="kncc-ai-helper" style="margin-top:1.5rem;">
      <h4><?php esc_html_e('Generate cookie policy page content with AI', 'kansleri-cookie-consent'); ?></h4>
      <p style="font-size:13px;color:#1e3a5f;margin:0 0 0.75rem;">
        <?php esc_html_e('Copy the prompt below and paste it into an AI tool. It will generate a complete cookie and privacy policy page in your site language. The cookie declaration will be handled automatically by the [kncc_cookie_policy] shortcode. Paste the generated HTML into your policy page.', 'kansleri-cookie-consent'); ?>
      </p>
      <button type="button" class="button button-primary" id="kncc-copy-policy-prompt-btn">
        <span class="dashicons dashicons-clipboard" style="margin-right:4px;vertical-align:text-bottom;"></span>
        <?php esc_html_e('Copy AI Prompt for Policy Page', 'kansleri-cookie-consent'); ?>
      </button>
    </div>
    <?php
  }

  /**
   * Check if the policy page exists in all active languages (Polylang/WPML).
   * Returns an array of language names that are missing a translated policy page.
   */
  private function get_missing_policy_languages($policy_page_id) {
    if (!$policy_page_id) {
      return array();
    }

    $post = get_post($policy_page_id);
    if (!$post || $post->post_status === 'trash') {
      return array();
    }

    $missing = array();

    if (function_exists('pll_languages_list') && function_exists('pll_get_post')) {
      $languages = pll_languages_list(array('fields' => 'slug'));
      $lang_names = pll_languages_list(array('fields' => 'name'));
      foreach ($languages as $i => $slug) {
        $translated_id = pll_get_post($policy_page_id, $slug);
        if (!$translated_id || get_post_status($translated_id) === 'trash') {
          $missing[] = isset($lang_names[$i]) ? $lang_names[$i] : $slug;
        }
      }
    } elseif (defined('ICL_SITEPRESS_VERSION') && function_exists('icl_object_id')) {
      $langs = apply_filters('wpml_active_languages', null, array('skip_missing' => 0)); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML API hook.
      if (is_array($langs)) {
        foreach ($langs as $lang) {
          $translated_id = apply_filters('wpml_object_id', $policy_page_id, 'page', false, $lang['language_code']); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML API hook.
          if (!$translated_id || get_post_status($translated_id) === 'trash') {
            $missing[] = $lang['translated_name'];
          }
        }
      }
    }

    return $missing;
  }

  private function render_integration_tab($settings) {
    $sitekit_active = defined('GOOGLESITEKIT_VERSION');
    $sitekit_handles_consent = KNCC_Consent_Mode::site_kit_handles_consent();
    $wp_consent_api_active = KNCC_WP_Consent_API::is_active();
    ?>

    <?php if ($sitekit_active || $wp_consent_api_active) : ?>
    <h3><?php esc_html_e('Detected integrations', 'kansleri-cookie-consent'); ?></h3>
    <div class="kncc-integrations-status">
      <?php if ($sitekit_active) : ?>
      <div class="kncc-integration-badge kncc-integration-badge--active">
        <span class="dashicons dashicons-yes-alt"></span>
        <div>
          <strong>Google Site Kit</strong><br>
          <?php if ($sitekit_handles_consent) : ?>
            <span class="kncc-integration-detail"><?php esc_html_e('Active — Site Kit manages consent defaults. This plugin sends consent updates only.', 'kansleri-cookie-consent'); ?></span>
          <?php else : ?>
            <span class="kncc-integration-detail"><?php esc_html_e('Active — Site Kit consent mode not enabled. This plugin handles all consent signals.', 'kansleri-cookie-consent'); ?></span>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>

      <?php if ($wp_consent_api_active) : ?>
      <div class="kncc-integration-badge kncc-integration-badge--active">
        <span class="dashicons dashicons-yes-alt"></span>
        <div>
          <strong>WP Consent API</strong><br>
          <span class="kncc-integration-detail"><?php esc_html_e('Active — consent status is shared with other plugins via the WP Consent API.', 'kansleri-cookie-consent'); ?></span>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <hr>
    <?php endif; ?>

    <form id="kncc-integration-form" class="kncc-form">
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
          <th scope="row"><label for="kncc-gtm-id"><?php esc_html_e('GTM Container ID', 'kansleri-cookie-consent'); ?></label></th>
          <td>
            <input type="text" id="kncc-gtm-id" name="gtm_container_id" value="<?php echo esc_attr($settings['gtm_container_id']); ?>" placeholder="GTM-XXXXXXX" class="regular-text" />
            <p class="description"><?php esc_html_e('The Container ID is your Google Tag Manager workspace identifier (format: GTM-XXXXXXX), found in GTM top bar or Admin. In this plugin it is a reference field to keep configuration documented; it does not automatically embed the GTM code snippet.', 'kansleri-cookie-consent'); ?></p>
          </td>
        </tr>
      </table>

      <?php if ($settings['consent_mode'] && !$sitekit_handles_consent) : ?>
      <div class="kncc-code-preview">
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
    <div class="kncc-docs">
      <h2><?php esc_html_e('Installation guide', 'kansleri-cookie-consent'); ?></h2>
      <ol>
        <li><?php esc_html_e('Activate the plugin and open Settings > Cookie Consent.', 'kansleri-cookie-consent'); ?></li>
        <li><?php esc_html_e('Configure banner texts, style, and button labels in the General tab.', 'kansleri-cookie-consent'); ?></li>
        <li><?php esc_html_e('Open the Integration tab and enable Google Consent Mode v2 if you use GTM/Google tags.', 'kansleri-cookie-consent'); ?></li>
        <li><?php esc_html_e('Open the Cookies tab, run Scan Cookies, then review and categorize unknown cookies.', 'kansleri-cookie-consent'); ?></li>
        <li><?php esc_html_e('If unknown cookies are found, an AI helper appears: copy the prompt, paste it into an AI tool, paste the response back into the text box, click Import, then Save Cookies.', 'kansleri-cookie-consent'); ?></li>
        <li><?php esc_html_e('Assign or create your Cookie Policy page in the Policy Page tab.', 'kansleri-cookie-consent'); ?></li>
        <li><?php esc_html_e('Use the AI prompt on the Policy Page tab to generate the full cookie policy page content. Paste the generated HTML into your policy page.', 'kansleri-cookie-consent'); ?></li>
        <li><?php esc_html_e('Review the Script Blocking tab to see which scripts are auto-blocked.', 'kansleri-cookie-consent'); ?></li>
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
        <li><strong><?php esc_html_e('AI Prompt for Policy Page', 'kansleri-cookie-consent'); ?>:</strong> <?php esc_html_e('Generates a prompt you can paste into an AI tool to create a full privacy and cookie policy. The generated text will include the [kncc_cookie_policy] shortcode for the cookie declaration section.', 'kansleri-cookie-consent'); ?></li>
        <li><strong><?php esc_html_e('Multilingual support', 'kansleri-cookie-consent'); ?>:</strong> <?php esc_html_e('If Polylang or WPML is active, the plugin checks that the policy page has a translation in every active language and warns you if any are missing.', 'kansleri-cookie-consent'); ?></li>
      </ul>

      <h3><?php esc_html_e('Integration tab', 'kansleri-cookie-consent'); ?></h3>
      <ul>
        <li><strong><?php esc_html_e('Google Consent Mode v2', 'kansleri-cookie-consent'); ?>:</strong> <?php esc_html_e('A Google framework for privacy-safe tag behavior. Before consent, storage signals are denied; after user choice, signals are updated (for example analytics_storage=granted). This helps GTM/Google tags respect user consent state.', 'kansleri-cookie-consent'); ?></li>
        <li><strong><?php esc_html_e('GTM Container ID', 'kansleri-cookie-consent'); ?>:</strong> <?php esc_html_e('Your GTM container identifier (for example GTM-N6828NL). It identifies which tag container your site uses. This field is informational in this plugin and does not inject GTM scripts.', 'kansleri-cookie-consent'); ?></li>
      </ul>

      <h3><?php esc_html_e('Script Blocking tab', 'kansleri-cookie-consent'); ?></h3>
      <ul>
        <li><strong><?php esc_html_e('Auto-detected scripts', 'kansleri-cookie-consent'); ?>:</strong> <?php esc_html_e('Shows WordPress-enqueued scripts that the plugin automatically recognizes and blocks (e.g. Google Analytics, Meta Pixel). These are blocked before consent and activated after the visitor accepts the relevant category.', 'kansleri-cookie-consent'); ?></li>
        <li><strong><?php esc_html_e('YouTube & Vimeo iframe blocking', 'kansleri-cookie-consent'); ?>:</strong> <?php esc_html_e('Video embeds from YouTube and Vimeo are automatically blocked until the visitor accepts marketing cookies. A placeholder with an accept button is shown instead. When the visitor has already consented, videos load normally.', 'kansleri-cookie-consent'); ?></li>
      </ul>

      <h3><?php esc_html_e('Statistics tab', 'kansleri-cookie-consent'); ?></h3>
      <ul>
        <li><strong><?php esc_html_e('Consent statistics', 'kansleri-cookie-consent'); ?>:</strong> <?php esc_html_e('Shows how many visitors accepted, rejected, or customized cookies over 7, 30, or 90 day periods. Data is aggregated and anonymous — no personal data is stored.', 'kansleri-cookie-consent'); ?></li>
        <li><strong><?php esc_html_e('Consent Log', 'kansleri-cookie-consent'); ?>:</strong> <?php esc_html_e('Individual anonymized consent records for GDPR audit compliance. Records contain a hashed visitor identifier, the consent choice, accepted categories, and the policy version. Records are automatically purged after 13 months.', 'kansleri-cookie-consent'); ?></li>
        <li><strong><?php esc_html_e('Export CSV', 'kansleri-cookie-consent'); ?>:</strong> <?php esc_html_e('Downloads all consent log records as a CSV file for auditing.', 'kansleri-cookie-consent'); ?></li>
        <li><strong><?php esc_html_e('Purge All Records', 'kansleri-cookie-consent'); ?>:</strong> <?php esc_html_e('Permanently deletes all consent log records and aggregate statistics.', 'kansleri-cookie-consent'); ?></li>
      </ul>

      <h3><?php esc_html_e('Multilingual support (WPML & Polylang)', 'kansleri-cookie-consent'); ?></h3>
      <ul>
        <li><strong><?php esc_html_e('Per-language banner texts', 'kansleri-cookie-consent'); ?>:</strong> <?php esc_html_e('When WPML or Polylang is active, switch to a secondary language in the admin bar and edit General tab settings. Each language can have its own heading, description, and button labels. The default language values are used as fallback.', 'kansleri-cookie-consent'); ?></li>
        <li><strong><?php esc_html_e('Per-language cookie descriptions', 'kansleri-cookie-consent'); ?>:</strong> <?php esc_html_e('Cookie names, categories, providers, and durations are shared across all languages. Only the description field can be translated per language. Switch language and edit descriptions in the Cookies tab.', 'kansleri-cookie-consent'); ?></li>
        <li><strong><?php esc_html_e('Per-language settings', 'kansleri-cookie-consent'); ?>:</strong> <?php esc_html_e('Banner style, colors, and policy page can be set per language. Settings not customized for a language fall back to the default language values.', 'kansleri-cookie-consent'); ?></li>
        <li><strong><?php esc_html_e('AI prompts', 'kansleri-cookie-consent'); ?>:</strong> <?php esc_html_e('AI prompts for cookie descriptions and policy pages are automatically generated in the language matching the current admin locale.', 'kansleri-cookie-consent'); ?></li>
      </ul>

      <h3><?php esc_html_e('Shortcodes', 'kansleri-cookie-consent'); ?></h3>
      <ul>
        <li><strong><code>[kncc_cookie_policy]</code>:</strong> <?php esc_html_e('Displays the full cookie declaration table on your policy page.', 'kansleri-cookie-consent'); ?></li>
        <li><strong><code>[kncc_cookie_settings]</code>:</strong> <?php esc_html_e('Renders a link that opens the cookie settings dialog. Use in footers, menus, or widgets. Customize the link text with the text attribute, e.g. [kncc_cookie_settings text="Manage cookies"].', 'kansleri-cookie-consent'); ?></li>
      </ul>

      <h2><?php esc_html_e('Troubleshooting', 'kansleri-cookie-consent'); ?></h2>
      <ul>
        <li><?php esc_html_e('If GTM Preview does not show consent updates, verify Consent Mode v2 is enabled and test in a fresh browser session without existing consent cookie.', 'kansleri-cookie-consent'); ?></li>
        <li><?php esc_html_e('If banner typography looks odd with your theme, switch General > Text and font style to Plugin style.', 'kansleri-cookie-consent'); ?></li>
        <li><?php esc_html_e('If scripts still load before consent, check that they appear in the Script Blocking tab with a recognized handle.', 'kansleri-cookie-consent'); ?></li>
        <li><?php esc_html_e('If the statistics are empty, make sure visitors have interacted with the banner. Data starts recording after the first consent choice.', 'kansleri-cookie-consent'); ?></li>
        <li><?php esc_html_e('If the policy page warning shows missing translations, create the translated page in each language and add the [kncc_cookie_policy] shortcode.', 'kansleri-cookie-consent'); ?></li>
      </ul>

      <hr style="margin: 2em 0;" />
      <p><?php esc_html_e('I made this plugin for free because I got frustrated by the existing cookie consent plugins — too much bloat and useless features. If you like it and want to buy me a coffee, you can do it here:', 'kansleri-cookie-consent'); ?> <a href="https://ko-fi.com/tapiok" target="_blank" rel="noopener noreferrer">ko-fi.com/tapiok</a></p>
    </div>
    <?php
  }

  private function render_statistics_tab() {
    $period = isset($_GET['period']) ? absint($_GET['period']) : 30; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- navigational period parameter
    if (!in_array($period, array(7, 30, 90), true)) {
      $period = 30;
    }

    $totals = KNCC_Stats::get_totals($period);
    $accept_pct = $totals['total'] ? round($totals['accept'] / $totals['total'] * 100) : 0;
    $reject_pct = $totals['total'] ? round($totals['reject'] / $totals['total'] * 100) : 0;
    $custom_pct = $totals['total'] ? round($totals['custom'] / $totals['total'] * 100) : 0;

    ?>
    <div class="kncc-stats">
      <div class="kncc-stats__period">
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
              (int) $p
            );
            ?>
          </a>
        <?php endforeach; ?>
      </div>

      <div class="kncc-stats__cards">
        <div class="kncc-stats__card kncc-stats__card--total">
          <div class="kncc-stats__card-value"><?php echo esc_html($totals['total']); ?></div>
          <div class="kncc-stats__card-label"><?php esc_html_e('Total responses', 'kansleri-cookie-consent'); ?></div>
        </div>
        <div class="kncc-stats__card kncc-stats__card--accept">
          <div class="kncc-stats__card-value"><?php echo esc_html($totals['accept']); ?></div>
          <div class="kncc-stats__card-label"><?php esc_html_e('Accepted all', 'kansleri-cookie-consent'); ?> <span class="kncc-stats__pct"><?php echo esc_html($accept_pct); ?>%</span></div>
        </div>
        <div class="kncc-stats__card kncc-stats__card--reject">
          <div class="kncc-stats__card-value"><?php echo esc_html($totals['reject']); ?></div>
          <div class="kncc-stats__card-label"><?php esc_html_e('Rejected all', 'kansleri-cookie-consent'); ?> <span class="kncc-stats__pct"><?php echo esc_html($reject_pct); ?>%</span></div>
        </div>
        <div class="kncc-stats__card kncc-stats__card--custom">
          <div class="kncc-stats__card-value"><?php echo esc_html($totals['custom']); ?></div>
          <div class="kncc-stats__card-label"><?php esc_html_e('Custom selection', 'kansleri-cookie-consent'); ?> <span class="kncc-stats__pct"><?php echo esc_html($custom_pct); ?>%</span></div>
        </div>
      </div>

      <?php if ($totals['total'] > 0) : ?>
      <div class="kncc-stats__bar">
        <?php if ($accept_pct > 0) : ?>
          <div class="kncc-stats__bar-seg kncc-stats__bar-seg--accept" style="width:<?php echo esc_attr( (int) $accept_pct ); ?>%;" title="<?php esc_attr_e('Accepted all', 'kansleri-cookie-consent'); ?>: <?php echo esc_attr( (int) $accept_pct ); ?>%"><?php echo esc_html( (int) $accept_pct ); ?>%</div>
        <?php endif; ?>
        <?php if ($custom_pct > 0) : ?>
          <div class="kncc-stats__bar-seg kncc-stats__bar-seg--custom" style="width:<?php echo esc_attr( (int) $custom_pct ); ?>%;" title="<?php esc_attr_e('Custom selection', 'kansleri-cookie-consent'); ?>: <?php echo esc_attr( (int) $custom_pct ); ?>%"><?php echo esc_html( (int) $custom_pct ); ?>%</div>
        <?php endif; ?>
        <?php if ($reject_pct > 0) : ?>
          <div class="kncc-stats__bar-seg kncc-stats__bar-seg--reject" style="width:<?php echo esc_attr( (int) $reject_pct ); ?>%;" title="<?php esc_attr_e('Rejected all', 'kansleri-cookie-consent'); ?>: <?php echo esc_attr( (int) $reject_pct ); ?>%"><?php echo esc_html( (int) $reject_pct ); ?>%</div>
        <?php endif; ?>
      </div>
      <div class="kncc-stats__legend">
        <span class="kncc-stats__legend-item"><span class="kncc-stats__dot kncc-stats__dot--accept"></span> <?php esc_html_e('Accepted all', 'kansleri-cookie-consent'); ?></span>
        <span class="kncc-stats__legend-item"><span class="kncc-stats__dot kncc-stats__dot--custom"></span> <?php esc_html_e('Custom selection', 'kansleri-cookie-consent'); ?></span>
        <span class="kncc-stats__legend-item"><span class="kncc-stats__dot kncc-stats__dot--reject"></span> <?php esc_html_e('Rejected all', 'kansleri-cookie-consent'); ?></span>
      </div>
      <?php endif; ?>

      <?php if (!$totals['total']) : ?>
        <p class="kncc-stats__empty"><?php esc_html_e('No consent data recorded yet. Statistics will appear after visitors start interacting with the cookie banner.', 'kansleri-cookie-consent'); ?></p>
      <?php endif; ?>
    </div>

    <?php $this->render_consent_log_section(); ?>
    <?php
  }

  private function render_status_label() {
    $cron_error = get_option('kncc_cron_error');
    if ($cron_error) {
      ?>
      <div class="kncc-status-label kncc-status-label--error">
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

    $cookies = kncc_get_cookies();
    $incomplete = get_option('kncc_incomplete_cookies');
    $all_good = !empty($cookies) && (empty($incomplete) || !is_array($incomplete));

    if ($all_good) {
      $next_run = wp_next_scheduled('kncc_daily_cookie_check');
      ?>
      <div class="kncc-status-label kncc-status-label--good">
        <span class="dashicons dashicons-yes-alt"></span>
        <?php esc_html_e('All systems are good!', 'kansleri-cookie-consent'); ?>
        <span class="kncc-status-label__sub">
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

  private function render_blocking_tab() {
    $blocked_scripts = KNCC_Script_Blocker::get_blocked_scripts();
    ?>
    <div class="kncc-blocking-tab">
      <p class="description" style="margin-bottom:1rem;">
        <?php esc_html_e('Script blocking prevents third-party scripts from running until the visitor gives consent for that category. WordPress-enqueued scripts with known handles (Google Analytics, Meta Pixel, etc.) are auto-blocked.', 'kansleri-cookie-consent'); ?>
      </p>

      <h3><?php esc_html_e('Auto-detected scripts', 'kansleri-cookie-consent'); ?></h3>
      <p class="description">
        <?php esc_html_e('These script handles are automatically recognized and blocked before consent. No configuration needed — they are blocked by default when detected on your site.', 'kansleri-cookie-consent'); ?>
      </p>
      <table class="wp-list-table widefat striped" style="margin-bottom:2rem;">
        <thead>
          <tr>
            <th><?php esc_html_e('Script Handle', 'kansleri-cookie-consent'); ?></th>
            <th><?php esc_html_e('Category', 'kansleri-cookie-consent'); ?></th>
          </tr>
        </thead>
        <tbody>
          <?php
          $known = KNCC_Script_Blocker::detect_registered_scripts();
          foreach ($known as $handle => $info) :
          ?>
            <tr>
              <td><code><?php echo esc_html($handle); ?></code></td>
              <td class="kncc-scan-cat kncc-scan-cat--<?php echo esc_attr($info['category']); ?>"><?php echo esc_html(ucfirst($info['category'])); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php
  }

  private function render_consent_log_section() {
    if (!class_exists('KNCC_Consent_Log')) {
      return;
    }

    $page = isset($_GET['log_page']) ? max(1, absint($_GET['log_page'])) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $per_page = 25;
    $total = KNCC_Consent_Log::get_total_records();
    $total_pages = max(1, (int) ceil($total / $per_page));
    $page = min($page, $total_pages);
    $records = KNCC_Consent_Log::get_records($page, $per_page);
    $meta = KNCC_Consent_Log::get_meta();
    ?>
    <hr>
    <h3><?php esc_html_e('Consent Log', 'kansleri-cookie-consent'); ?></h3>
    <p class="description">
      <?php esc_html_e('Individual consent records for GDPR audit compliance. Records are automatically purged after the retention period (13 months).', 'kansleri-cookie-consent'); ?>
    </p>

    <div class="kncc-consent-log-meta" style="display:flex;gap:18px;margin:1rem 0;font-size:13px;color:#6b7280;">
      <span><strong><?php esc_html_e('Total records:', 'kansleri-cookie-consent'); ?></strong> <?php echo esc_html($total); ?></span>
      <?php if ($meta['oldest']) : ?>
        <span><strong><?php esc_html_e('Oldest:', 'kansleri-cookie-consent'); ?></strong> <?php echo esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($meta['oldest']))); ?></span>
      <?php endif; ?>
      <?php if ($meta['newest']) : ?>
        <span><strong><?php esc_html_e('Newest:', 'kansleri-cookie-consent'); ?></strong> <?php echo esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($meta['newest']))); ?></span>
      <?php endif; ?>
    </div>

    <div style="display:flex;gap:8px;margin-bottom:1rem;">
      <button type="button" class="button" id="kncc-export-log-btn"><?php esc_html_e('Export CSV', 'kansleri-cookie-consent'); ?></button>
      <button type="button" class="button" id="kncc-purge-log-btn" style="color:#dc2626;"><?php esc_html_e('Purge All Records', 'kansleri-cookie-consent'); ?></button>
    </div>

    <?php if (empty($records)) : ?>
      <p style="color:#9ca3af;font-style:italic;"><?php esc_html_e('No consent log records yet.', 'kansleri-cookie-consent'); ?></p>
    <?php else : ?>
      <table class="wp-list-table widefat striped">
        <thead>
          <tr>
            <th><?php esc_html_e('Date/Time', 'kansleri-cookie-consent'); ?></th>
            <th><?php esc_html_e('Visitor', 'kansleri-cookie-consent'); ?></th>
            <th><?php esc_html_e('Choice', 'kansleri-cookie-consent'); ?></th>
            <th><?php esc_html_e('Categories', 'kansleri-cookie-consent'); ?></th>
            <th><?php esc_html_e('Policy Version', 'kansleri-cookie-consent'); ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($records as $row) : ?>
            <?php
            $cats = json_decode($row->categories, true);
            $cats_display = array();
            if (is_array($cats)) {
              foreach ($cats as $k => $v) {
                if ($v) {
                  $cats_display[] = ucfirst($k);
                }
              }
            }
            ?>
            <tr>
              <td><?php echo esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($row->created_at))); ?></td>
              <td><code title="<?php echo esc_attr($row->visitor_hash); ?>"><?php echo esc_html(substr($row->visitor_hash, 0, 12)); ?>&hellip;</code></td>
              <td>
                <span class="kncc-scan-cat kncc-scan-cat--<?php echo $row->choice === 'accept' ? 'necessary' : ($row->choice === 'reject' ? 'marketing' : 'preferences'); ?>">
                  <?php echo esc_html(ucfirst($row->choice)); ?>
                </span>
              </td>
              <td><?php echo esc_html(implode(', ', $cats_display) ?: '—'); ?></td>
              <td><?php echo esc_html($row->policy_version); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <?php if ($total_pages > 1) : ?>
        <div class="tablenav" style="margin-top:0.5rem;">
          <div class="tablenav-pages">
            <?php
            $period = isset($_GET['period']) ? absint($_GET['period']) : 30; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $base_url = admin_url('options-general.php?page=kansleri-cookie-consent&tab=statistics&period=' . $period);
            for ($i = 1; $i <= $total_pages; $i++) :
              $url = add_query_arg('log_page', $i, $base_url);
            ?>
              <?php if ($i === $page) : ?>
                <span class="tablenav-pages-navspan button disabled"><?php echo esc_html($i); ?></span>
              <?php else : ?>
                <a class="button" href="<?php echo esc_url($url); ?>"><?php echo esc_html($i); ?></a>
              <?php endif; ?>
            <?php endfor; ?>
          </div>
        </div>
      <?php endif; ?>
    <?php endif; ?>
    <?php
  }

  public function ajax_export_consent_log() {
    check_ajax_referer('kncc_admin', 'nonce');

    if (!current_user_can('manage_options')) {
      wp_send_json_error('Unauthorized');
    }

    $records = KNCC_Consent_Log::get_all_records();

    $csv_lines = array();
    $csv_lines[] = 'Date,Visitor Hash,Choice,Categories,Policy Version';
    foreach ($records as $row) {
      $cats = json_decode($row->categories, true);
      $cats_str = '';
      if (is_array($cats)) {
        $accepted = array();
        foreach ($cats as $k => $v) {
          if ($v) {
            $accepted[] = $k;
          }
        }
        $cats_str = implode('; ', $accepted);
      }
      $csv_lines[] = sprintf(
        '"%s","%s","%s","%s","%s"',
        $row->created_at,
        $row->visitor_hash,
        $row->choice,
        $cats_str,
        $row->policy_version
      );
    }

    wp_send_json_success(array('csv' => implode("\n", $csv_lines)));
  }

  public function ajax_purge_consent_log() {
    check_ajax_referer('kncc_admin', 'nonce');

    if (!current_user_can('manage_options')) {
      wp_send_json_error('Unauthorized');
    }

    KNCC_Consent_Log::purge_all();
    delete_option(KNCC_Stats::OPTION_KEY);
    wp_send_json_success(array('message' => __('All consent log records and statistics have been purged.', 'kansleri-cookie-consent')));
  }

  public function ajax_save_settings() {
    check_ajax_referer('kncc_admin', 'nonce');

    if (!current_user_can('manage_options')) {
      wp_send_json_error('Unauthorized');
    }

    $lang = kncc_get_current_language();

    // Collect all settings from the form.
    $values = array();

    $text_fields = array(
      'banner_position', 'gtm_container_id',
      'heading_text', 'description_text', 'accept_text',
      'reject_text', 'customize_text', 'save_text',
    );
    foreach ($text_fields as $field) {
      if (isset($_POST[$field])) {
        $values[$field] = sanitize_text_field(wp_unslash($_POST[$field]));
      }
    }

    if (isset($_POST['banner_style'])) {
      $style = sanitize_text_field(wp_unslash($_POST['banner_style']));
      $values['banner_style'] = in_array($style, array('bar', 'modal', 'corner'), true) ? $style : 'bar';
    }

    if (isset($_POST['primary_color'])) {
      $color = sanitize_hex_color(wp_unslash($_POST['primary_color']));
      $values['primary_color'] = $color ? $color : '#3D4872';
    }

    if (isset($_POST['text_style_mode'])) {
      $text_style_mode = sanitize_key(wp_unslash($_POST['text_style_mode']));
      $values['text_style_mode'] = in_array($text_style_mode, array('theme', 'plugin'), true) ? $text_style_mode : 'theme';
    }

    if (isset($_POST['policy_page_id'])) {
      $values['policy_page_id'] = absint($_POST['policy_page_id']);
    }

    if (isset($_POST['policy_style_mode'])) {
      $policy_style_mode = sanitize_key(wp_unslash($_POST['policy_style_mode']));
      $values['policy_style_mode'] = in_array($policy_style_mode, array('theme', 'plugin'), true) ? $policy_style_mode : 'theme';
    }

    $values['consent_mode'] = !empty($_POST['consent_mode']);
    $values['show_floating_btn'] = !empty($_POST['show_floating_btn']);

    if ($lang) {
      // Secondary language: save all form values to language-specific option.
      $existing = get_option('kncc_texts_' . $lang, array());
      update_option('kncc_texts_' . $lang, array_merge($existing, $values), false);
    } else {
      // Default language: save to base settings.
      $current = kncc_get_settings();
      update_option('kncc_settings', array_merge($current, $values), false);
    }

    wp_send_json_success(array('message' => __('Settings saved.', 'kansleri-cookie-consent')));
  }

  public function ajax_save_cookies() {
    check_ajax_referer('kncc_admin', 'nonce');

    if (!current_user_can('manage_options')) {
      wp_send_json_error('Unauthorized');
    }

    $raw = isset($_POST['cookies']) ? wp_unslash($_POST['cookies']) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each field is sanitized individually below
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

    // Cookie structure (names, categories, providers, durations) is always shared.
    // Descriptions can differ per language.
    $lang = kncc_get_current_language();

    // Build the base cookie list using default-language descriptions.
    $base_cookies = array();
    $default_descriptions = array();
    $existing_base = kncc_get_cookies();
    foreach ($existing_base as $c) {
      $default_descriptions[$c['name']] = $c['description'];
    }

    foreach ($cookies as $c) {
      $base_cookie = $c;
      if ($lang) {
        // Keep the default-language description in the base option.
        $base_cookie['description'] = isset($default_descriptions[$c['name']]) ? $default_descriptions[$c['name']] : '';
      }
      $base_cookies[] = $base_cookie;
    }

    // Always save structure to the base option.
    update_option('kncc_cookies', $base_cookies, false);

    if ($lang) {
      // Save this language's descriptions separately.
      $desc_map = array();
      foreach ($cookies as $c) {
        if (!empty($c['description'])) {
          $desc_map[$c['name']] = $c['description'];
        }
      }
      update_option('kncc_cookie_descriptions_' . $lang, $desc_map, false);
    }

    $incomplete = array();
    foreach ($cookies as $c) {
      if (empty($c['description'])) {
        $incomplete[] = $c['name'];
      }
    }
    if (!empty($incomplete)) {
      update_option('kncc_incomplete_cookies', $incomplete, false);
    } else {
      delete_option('kncc_incomplete_cookies');
    }

    wp_send_json_success(array('message' => __('Cookies saved.', 'kansleri-cookie-consent')));
  }
}
