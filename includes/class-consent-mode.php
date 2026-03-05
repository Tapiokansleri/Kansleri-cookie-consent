<?php

if (!defined('ABSPATH')) {
  exit;
}

class KCC_Consent_Mode {

  public function __construct() {
    $settings = kcc_get_settings();
    if (!empty($settings['consent_mode']) || self::site_kit_handles_consent()) {
      add_action('wp_head', array($this, 'output_defaults'), 1);
    }
  }

  /**
   * Whether Google Site Kit is active and managing consent mode,
   * meaning we should skip our own consent defaults output.
   */
  public static function site_kit_handles_consent() {
    if (!defined('GOOGLESITEKIT_VERSION')) {
      return false;
    }
    $sitekit_consent = get_option('googlesitekit_consent_mode', array());
    if (!empty($sitekit_consent['enabled'])) {
      return true;
    }
    return apply_filters('googlesitekit_consent_mode_status', false) !== false;
  }

  /**
   * Outputs Consent Mode v2 defaults before any other scripts (GTM, analytics, etc.)
   * and restores previously saved consent if the cookie exists.
   * Skips default output if Site Kit is already handling consent mode.
   */
  public function output_defaults() {
    if (is_admin()) {
      return;
    }

    if (self::site_kit_handles_consent()) {
      $js = "(function(){"
        . "var m=document.cookie.match(/(?:^|; )kcc_consent=([^;]*)/);"
        . "if(!m)return;"
        . "window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}"
        . "try{var c=JSON.parse(decodeURIComponent(m[1]));"
        . "gtag('consent','update',{"
        . "analytics_storage:c.analytics?'granted':'denied',"
        . "ad_storage:c.marketing?'granted':'denied',"
        . "ad_user_data:c.marketing?'granted':'denied',"
        . "ad_personalization:c.marketing?'granted':'denied',"
        . "functionality_storage:c.preferences?'granted':'denied',"
        . "personalization_storage:c.preferences?'granted':'denied',"
        . "security_storage:'granted'"
        . "});"
        . "}catch(e){}"
        . "})();";
      wp_print_inline_script_tag($js);
      return;
    }

    $js = "window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}"
      . "gtag('consent','default',{"
      . "ad_storage:'denied',"
      . "ad_user_data:'denied',"
      . "ad_personalization:'denied',"
      . "analytics_storage:'denied',"
      . "functionality_storage:'denied',"
      . "personalization_storage:'denied',"
      . "security_storage:'granted',"
      . "wait_for_update:500"
      . "});"
      . "(function(){"
      . "var m=document.cookie.match(/(?:^|; )kcc_consent=([^;]*)/);"
      . "if(!m)return;"
      . "try{var c=JSON.parse(decodeURIComponent(m[1]));"
      . "gtag('consent','update',{"
      . "analytics_storage:c.analytics?'granted':'denied',"
      . "ad_storage:c.marketing?'granted':'denied',"
      . "ad_user_data:c.marketing?'granted':'denied',"
      . "ad_personalization:c.marketing?'granted':'denied',"
      . "functionality_storage:c.preferences?'granted':'denied',"
      . "personalization_storage:c.preferences?'granted':'denied',"
      . "security_storage:'granted'"
      . "});"
      . "}catch(e){}"
      . "})();";

    wp_print_inline_script_tag($js);
  }
}
