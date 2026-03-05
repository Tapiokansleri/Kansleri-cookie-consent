<?php

if (!defined('ABSPATH')) {
  exit;
}

class KCC_Stats {

  const OPTION_KEY = 'kcc_consent_stats';
  const MAX_DAYS = 90;

  public function __construct() {
    add_action('wp_ajax_kcc_record_consent', array($this, 'record_consent'));
    add_action('wp_ajax_nopriv_kcc_record_consent', array($this, 'record_consent'));
  }

  public function record_consent() {
    $choice = isset($_POST['choice']) ? sanitize_key($_POST['choice']) : '';
    if (!in_array($choice, array('accept', 'reject', 'custom'), true)) {
      wp_send_json_error('Invalid choice');
    }

    $stats = get_option(self::OPTION_KEY, array());
    $today = wp_date('Y-m-d');

    if (!isset($stats[$today])) {
      $stats[$today] = array('accept' => 0, 'reject' => 0, 'custom' => 0);
    }
    $stats[$today][$choice]++;

    $cutoff = wp_date('Y-m-d', strtotime('-' . self::MAX_DAYS . ' days'));
    foreach (array_keys($stats) as $date) {
      if ($date < $cutoff) {
        unset($stats[$date]);
      }
    }

    update_option(self::OPTION_KEY, $stats, false);
    wp_send_json_success();
  }

  public static function get_stats($days = 30) {
    $stats = get_option(self::OPTION_KEY, array());
    $result = array();

    for ($i = $days - 1; $i >= 0; $i--) {
      $date = wp_date('Y-m-d', strtotime("-{$i} days"));
      $result[$date] = isset($stats[$date])
        ? $stats[$date]
        : array('accept' => 0, 'reject' => 0, 'custom' => 0);
    }

    return $result;
  }

  public static function get_totals($days = 30) {
    $stats = self::get_stats($days);
    $totals = array('accept' => 0, 'reject' => 0, 'custom' => 0, 'total' => 0);

    foreach ($stats as $day) {
      $totals['accept'] += $day['accept'];
      $totals['reject'] += $day['reject'];
      $totals['custom'] += $day['custom'];
    }
    $totals['total'] = $totals['accept'] + $totals['reject'] + $totals['custom'];

    return $totals;
  }
}
