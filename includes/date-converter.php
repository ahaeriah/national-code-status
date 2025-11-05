<?php

/**
 * تبدیل تاریخ میلادی به شمسی
 */

if (!defined('ABSPATH')) {
  exit;
}

// تنظیم منطقه زمانی به تهران
if (!function_exists('ncs_set_tehran_timezone')) {
  function ncs_set_tehran_timezone()
  {
    date_default_timezone_set('Asia/Tehran');
  }
  ncs_set_tehran_timezone();
}

/**
 * تبدیل تاریخ میلادی به شمسی
 */
function ncs_gregorian_to_jalali($g_y, $g_m = null, $g_d = null)
{
  // اگر پارامترها به صورت رشته تاریخ باشند
  if ($g_m === null && $g_d === null) {
    $timestamp = is_numeric($g_y) ? $g_y : strtotime($g_y);
    $g_y = date('Y', $timestamp);
    $g_m = date('m', $timestamp);
    $g_d = date('d', $timestamp);
  }

  $g_d = intval($g_d);
  $g_m = intval($g_m);
  $g_y = intval($g_y);

  $gy = $g_y - 1600;
  $gm = $g_m - 1;
  $gd = $g_d - 1;

  $g_day_no = (365 * $gy) + (int)(($gy + 3) / 4) - (int)(($gy + 99) / 100) + (int)(($gy + 399) / 400);

  for ($i = 0; $i < $gm; ++$i) {
    $g_day_no += ncs_gregorian_days_in_month($i, $gy);
  }

  $g_day_no += $gd;
  $j_day_no = $g_day_no - 79;
  $j_np = (int)($j_day_no / 12053);
  $j_day_no %= 12053;
  $jy = 979 + (33 * $j_np) + (4 * (int)($j_day_no / 1461));
  $j_day_no %= 1461;

  if ($j_day_no >= 366) {
    $jy += (int)(($j_day_no - 1) / 365);
    $j_day_no = ($j_day_no - 1) % 365;
  }

  for ($i = 0; $i < 11 && $j_day_no >= ncs_jalali_days_in_month($i, $jy); ++$i) {
    $j_day_no -= ncs_jalali_days_in_month($i, $jy);
  }

  $jm = $i + 1;
  $jd = $j_day_no + 1;

  return array($jy, $jm, $jd);
}

/**
 * تعداد روزهای ماه میلادی
 */
function ncs_gregorian_days_in_month($month, $year)
{
  $days_in_month = array(31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31);
  if ($month == 1 && (($year % 4 == 0 && $year % 100 != 0) || ($year % 400 == 0))) {
    return 29;
  }
  return $days_in_month[$month];
}

/**
 * تعداد روزهای ماه شمسی
 */
function ncs_jalali_days_in_month($month, $year)
{
  if ($month < 6) {
    return 31;
  } elseif ($month < 11) {
    return 30;
  } else {
    $mod = $year % 33;
    if (in_array($mod, array(1, 5, 9, 13, 17, 22, 26, 30))) {
      return 30;
    } else {
      return 29;
    }
  }
}

/**
 * فرمت‌بندی تاریخ شمسی
 */
function ncs_format_jalali_date($gregorian_date, $format = 'Y/m/d H:i')
{
  if (empty($gregorian_date)) {
    return '-';
  }

  $timestamp = is_numeric($gregorian_date) ? $gregorian_date : strtotime($gregorian_date);

  // استفاده از زمان سرور که باید روی تهران تنظیم شده باشد
  list($j_y, $j_m, $j_d) = ncs_gregorian_to_jalali(date('Y', $timestamp), date('m', $timestamp), date('d', $timestamp));
  $j_h = date('H', $timestamp);
  $j_i = date('i', $timestamp);
  $j_s = date('s', $timestamp);

  $formatted = $format;
  $formatted = str_replace('Y', $j_y, $formatted);
  $formatted = str_replace('m', str_pad($j_m, 2, '0', STR_PAD_LEFT), $formatted);
  $formatted = str_replace('d', str_pad($j_d, 2, '0', STR_PAD_LEFT), $formatted);
  $formatted = str_replace('H', $j_h, $formatted);
  $formatted = str_replace('i', $j_i, $formatted);
  $formatted = str_replace('s', $j_s, $formatted);

  return $formatted;
}

/**
 * تبدیل timestamp به تاریخ شمسی
 */
function ncs_timestamp_to_jalali($timestamp, $include_time = true)
{
  if (empty($timestamp)) {
    return '-';
  }

  if ($include_time) {
    return ncs_format_jalali_date($timestamp, 'Y/m/d H:i');
  } else {
    return ncs_format_jalali_date($timestamp, 'Y/m/d');
  }
}
