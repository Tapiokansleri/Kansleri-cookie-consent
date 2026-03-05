<?php

if (!defined('ABSPATH')) {
  exit;
}

class KCC_Consent_Mode {

  public function __construct() {
    $settings = kcc_get_settings();
    if (!empty($settings['consent_mode'])) {
      add_action('wp_head', array($this, 'output_defaults'), 1);
    }
  }

  /**
   * Outputs Consent Mode v2 defaults before any other scripts (GTM, analytics, etc.)
   * and restores previously saved consent if the cookie exists.
   */
  public function output_defaults() {
    if (is_admin()) {
      return;
    }
    ?>
<script>
window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}
gtag('consent','default',{
  ad_storage:'denied',
  ad_user_data:'denied',
  ad_personalization:'denied',
  analytics_storage:'denied',
  functionality_storage:'denied',
  personalization_storage:'denied',
  security_storage:'granted',
  wait_for_update:500
});
(function(){
  var m=document.cookie.match(/(?:^|; )kcc_consent=([^;]*)/);
  if(!m)return;
  try{var c=JSON.parse(decodeURIComponent(m[1]));
    gtag('consent','update',{
      analytics_storage:c.analytics?'granted':'denied',
      ad_storage:c.marketing?'granted':'denied',
      ad_user_data:c.marketing?'granted':'denied',
      ad_personalization:c.marketing?'granted':'denied',
      functionality_storage:c.preferences?'granted':'denied',
      personalization_storage:c.preferences?'granted':'denied'
    });
  }catch(e){}
})();
</script>
    <?php
  }
}
