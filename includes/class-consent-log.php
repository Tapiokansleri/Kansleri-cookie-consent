<?php

if (!defined('ABSPATH')) {
  exit;
}

class KNCC_Consent_Log {

  const TABLE_SUFFIX = 'kncc_consent_log';
  const RETENTION_MONTHS = 13;

  public function __construct() {
    add_action('kncc_daily_cookie_check', array($this, 'purge_expired'));
  }

  public static function get_table_name() {
    global $wpdb;
    return $wpdb->prefix . self::TABLE_SUFFIX;
  }

  /**
   * Create the consent log table. Called on plugin activation.
   */
  public static function create_table() {
    global $wpdb;
    $table = self::get_table_name();
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS {$table} (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      visitor_hash VARCHAR(64) NOT NULL DEFAULT '',
      choice VARCHAR(10) NOT NULL DEFAULT '',
      categories TEXT NOT NULL,
      policy_version VARCHAR(20) NOT NULL DEFAULT '',
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      KEY idx_created (created_at),
      KEY idx_visitor (visitor_hash)
    ) {$charset};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
  }

  /**
   * Record a consent decision. Called from the stats AJAX handler.
   */
  public static function record($choice, $categories_json) {
    global $wpdb;

    $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
    $visitor_hash = hash('sha256', $ip . '|' . $ua);

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table insert for consent logging.
    $wpdb->insert(
      self::get_table_name(),
      array(
        'visitor_hash'   => $visitor_hash,
        'choice'         => $choice,
        'categories'     => $categories_json,
        'policy_version' => KNCC_VERSION,
        'created_at'     => current_time('mysql'),
      ),
      array('%s', '%s', '%s', '%s', '%s')
    );
  }

  /**
   * Purge records older than the retention period.
   */
  public function purge_expired() {
    global $wpdb;
    $table = self::get_table_name();
    $cutoff = gmdate('Y-m-d H:i:s', strtotime('-' . self::RETENTION_MONTHS . ' months'));
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name from $wpdb->prefix, cannot be parameterized.
    $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE created_at < %s", $cutoff));
  }

  /**
   * Delete all records.
   */
  public static function purge_all() {
    global $wpdb;
    $table = self::get_table_name();
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name from $wpdb->prefix, cannot be parameterized.
    $wpdb->query("TRUNCATE TABLE {$table}");
  }

  /**
   * Drop the table entirely. Called on uninstall.
   */
  public static function drop_table() {
    global $wpdb;
    $table = self::get_table_name();
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name from $wpdb->prefix, cannot be parameterized.
    $wpdb->query("DROP TABLE IF EXISTS {$table}");
  }

  public static function get_total_records() {
    global $wpdb;
    $table = self::get_table_name();
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name from $wpdb->prefix, cannot be parameterized.
    return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
  }

  public static function get_records($page = 1, $per_page = 25) {
    global $wpdb;
    $table = self::get_table_name();
    $offset = max(0, ($page - 1) * $per_page);
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Reads from plugin-owned custom table; no persistent caching needed for paginated admin view.
    return $wpdb->get_results(
      $wpdb->prepare(
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix cannot be parameterized.
        "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d OFFSET %d",
        $per_page,
        $offset
      )
    );
  }

  public static function get_all_records() {
    global $wpdb;
    $table = self::get_table_name();
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name from $wpdb->prefix, cannot be parameterized.
    return $wpdb->get_results("SELECT * FROM {$table} ORDER BY created_at DESC");
  }

  public static function get_meta() {
    global $wpdb;
    $table = self::get_table_name();
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name from $wpdb->prefix, cannot be parameterized.
    $row = $wpdb->get_row("SELECT MIN(created_at) AS oldest, MAX(created_at) AS newest FROM {$table}");
    return array(
      'oldest' => $row ? $row->oldest : null,
      'newest' => $row ? $row->newest : null,
    );
  }
}
