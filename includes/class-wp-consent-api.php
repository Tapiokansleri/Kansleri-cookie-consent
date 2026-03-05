<?php

if (!defined('ABSPATH')) {
  exit;
}

class KCC_WP_Consent_API {

  public function __construct() {
    add_filter('wp_consent_api_registered_kansleri-cookie-consent', '__return_true');
    add_action('wp_enqueue_scripts', array($this, 'enqueue_bridge'));
  }

  /**
   * Whether the WP Consent API plugin is active and functional.
   */
  public static function is_active() {
    return function_exists('wp_has_consent');
  }

  /**
   * Enqueue a small bridge script that syncs our consent cookie
   * with the WP Consent API whenever consent changes.
   */
  public function enqueue_bridge() {
    if (is_admin() || !self::is_active()) {
      return;
    }

    $category_map = wp_json_encode(array(
      'analytics'   => 'statistics',
      'marketing'   => 'marketing',
      'preferences' => 'preferences',
    ));

    $js = <<<JS
(function(){
  if(typeof wp_set_consent!=='function')return;
  var map={$category_map};
  function sync(){
    var m=document.cookie.match(/(?:^|; )kcc_consent=([^;]*)/);
    if(!m)return;
    try{
      var c=JSON.parse(decodeURIComponent(m[1]));
      wp_set_consent('functional','allow');
      for(var k in map){
        if(map.hasOwnProperty(k)){
          wp_set_consent(map[k],c[k]?'allow':'deny');
        }
      }
    }catch(e){}
  }
  sync();
  document.addEventListener('click',function(e){
    var t=e.target.closest&&e.target.closest('[data-kcc]');
    if(t){setTimeout(sync,100);}
  });
})();
JS;

    wp_add_inline_script('kcc-consent', $js, 'after');
  }
}
