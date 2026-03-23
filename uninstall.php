<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package Kansleri_Cookie_Consent
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
  exit;
}

delete_option('kncc_settings');
delete_option('kncc_cookies');
delete_option('kncc_consent_stats');
delete_option('kncc_incomplete_cookies');
delete_option('kncc_show_activation_notice');
delete_option('kncc_defaults_localized');
delete_option('kncc_cron_error');
delete_option('kncc_blocked_scripts');
delete_option('kncc_custom_blocked_scripts');
delete_option('kncc_floating_btn_migrated');
delete_option('kncc_db_version');

// Remove per-language text and cookie options.
global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- cleanup on uninstall.
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'kncc\_texts\_%' OR option_name LIKE 'kncc\_cookies\_%' OR option_name LIKE 'kncc\_cookie\_descriptions\_%'");

wp_clear_scheduled_hook('kncc_daily_cookie_check');

global $wpdb;
$kncc_table = $wpdb->prefix . 'kncc_consent_log';
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name from $wpdb->prefix, cannot be parameterized.
$wpdb->query("DROP TABLE IF EXISTS {$kncc_table}");
