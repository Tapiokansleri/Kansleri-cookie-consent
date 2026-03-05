<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package Kansleri_Cookie_Consent
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
  exit;
}

delete_option('kcc_settings');
delete_option('kcc_cookies');
