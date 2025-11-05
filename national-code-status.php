<?php

/**
 * Plugin Name: National Code Status Dashboard
 * Description: نمایش وضعیت کد ملی کاربران با استفاده از وب سرویس‌های اختصاصی
 * Version: 1.2.0
 * Author: Your Name
 * Text Domain: national-code-status
 */

// امنیت - جلوگیری از دسترسی مستقیم
if (!defined('ABSPATH')) {
    exit;
}

// تعریف ثابت‌های مورد نیاز
define('NCS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('NCS_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('NCS_PLUGIN_VERSION', '1.2.0');

// اضافه کردن فایل تبدیل تاریخ
require_once NCS_PLUGIN_PATH . 'includes/date-converter.php';

// کلاس اصلی پلاگین
class NationalCodeStatus
{

    private $is_job_running = false;

    public function __construct()
    {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_ajax_ncs_check_single_code', array($this, 'check_single_national_code'));
        add_action('wp_ajax_ncs_manual_fetch', array($this, 'manual_fetch_national_codes'));
        add_action('wp_ajax_ncs_truncate_table', array($this, 'truncate_table'));
        add_action('wp_ajax_ncs_get_job_status', array($this, 'get_job_status'));
        add_action('wp_ajax_ncs_manual_check', array($this, 'manual_check_national_codes'));
        add_action('wp_ajax_ncs_export_to_excel', array($this, 'export_to_excel'));

        // ثبت جاب‌ها
        add_action('ncs_daily_fetch_job', array($this, 'daily_fetch_national_codes'));
        add_action('ncs_daily_check_job', array($this, 'daily_check_national_codes'));

        // اضافه کردن استایل و اسکریپت‌ها
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

        // ثبت اینتروال دقیقه‌ای
        add_filter('cron_schedules', array($this, 'add_minute_schedule'));
    }

    public function activate()
    {
        $this->create_tables();
        $this->schedule_jobs();

        // ذخیره توکن و hashId پیش‌فرض
        if (!get_option('ncs_token')) {
            update_option('ncs_token', '');
        }
        if (!get_option('ncs_hashid')) {
            update_option('ncs_hashid', 'shabahang');
        }

        // ذخیره زمان آخرین اجرای جاب دوم
        if (!get_option('ncs_last_check_job_run')) {
            update_option('ncs_last_check_job_run', '');
        }

        // وضعیت اجرای جاب
        if (!get_option('ncs_job_status')) {
            update_option('ncs_job_status', 'idle');
        }

        // شمارنده برای ردیابی اجراهای جاب دوم
        if (!get_option('ncs_check_job_counter')) {
            update_option('ncs_check_job_counter', 0);
        }

        // ذخیره آخرین بروزرسانی
        if (!get_option('ncs_last_update')) {
            update_option('ncs_last_update', '');
        }
    }

    public function deactivate()
    {
        $this->unschedule_jobs();
    }

    public function init()
    {
        load_plugin_textdomain('national-code-status', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    // اضافه کردن اینتروال دقیقه‌ای
    public function add_minute_schedule($schedules)
    {
        $schedules['ncs_minutely'] = array(
            'interval' => 60, // 60 ثانیه = 1 دقیقه
            'display' => __('هر دقیقه', 'national-code-status')
        );
        return $schedules;
    }

    public function create_tables()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'national_codes';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            national_code varchar(10) NOT NULL,
            hash_id varchar(50) NOT NULL,
            expire_date bigint(20),
            is_user_exists tinyint(1) DEFAULT 0,
            has_success_sayyah tinyint(1) DEFAULT 0,
            has_card tinyint(1) DEFAULT 0,
            card_issuance_date bigint(20),
            last_checked datetime DEFAULT CURRENT_TIMESTAMP,
            status varchar(50) DEFAULT 'not_checked',
            error_code varchar(10) DEFAULT NULL,
            created_date datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY national_code (national_code)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function schedule_jobs()
    {
        if (!wp_next_scheduled('ncs_daily_fetch_job')) {
            // زمان‌بندی جاب اول برای ساعت 00:01 به وقت تهران
            $tehran_time = strtotime('00:01') + (3.5 * 3600); // +3:30 GMT
            wp_schedule_event($tehran_time, 'daily', 'ncs_daily_fetch_job');
        }

        if (!wp_next_scheduled('ncs_daily_check_job')) {
            // جاب دوم هر دقیقه اجرا می‌شود - شروع بلافاصله
            wp_schedule_event(time(), 'ncs_minutely', 'ncs_daily_check_job');
        }
    }

    public function unschedule_jobs()
    {
        wp_clear_scheduled_hook('ncs_daily_fetch_job');
        wp_clear_scheduled_hook('ncs_daily_check_job');
    }

    public function add_admin_menu()
    {
        add_menu_page(
            'وضعیت کد ملی',
            'وضعیت کد ملی',
            'manage_options',
            'national-code-status',
            array($this, 'admin_dashboard_page'),
            'dashicons-id',
            30
        );

        add_submenu_page(
            'national-code-status',
            'تنظیمات',
            'تنظیمات',
            'manage_options',
            'national-code-settings',
            array($this, 'admin_settings_page')
        );
    }

    public function enqueue_admin_scripts($hook)
    {
        if ('toplevel_page_national-code-status' !== $hook && 'national-code-status_page_national-code-settings' !== $hook) {
            return;
        }

        wp_enqueue_style('ncs-admin-style', NCS_PLUGIN_URL . 'assets/admin.css', array(), NCS_PLUGIN_VERSION);
        wp_enqueue_script('ncs-admin-script', NCS_PLUGIN_URL . 'assets/admin.js', array('jquery'), NCS_PLUGIN_VERSION, true);

        // انتقال داده‌ها به جاوااسکریپت
        wp_localize_script('ncs-admin-script', 'ncs_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ncs_nonce')
        ));
    }

    public function admin_dashboard_page()
    {
        global $wpdb;

        // دریافت آمار
        $stats = $this->get_dashboard_stats();

        // مدیریت صفحه‌بندی
        $per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : 50;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($current_page - 1) * $per_page;

        // دریافت داده‌ها
        $table_name = $wpdb->prefix . 'national_codes';
        $national_codes = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name ORDER BY id DESC LIMIT %d OFFSET %d",
                $per_page,
                $offset
            )
        );

        // تعداد کل رکوردها
        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        $total_pages = ceil($total_items / $per_page);

        // بررسی زمان اجرای جاب بعدی
        $next_fetch_job = wp_next_scheduled('ncs_daily_fetch_job');
        $next_check_job = wp_next_scheduled('ncs_daily_check_job');
        $last_check_job_run = get_option('ncs_last_check_job_run');
        $job_status = get_option('ncs_job_status', 'idle');
        $job_counter = get_option('ncs_check_job_counter', 0);
        $last_update = get_option('ncs_last_update', '');

        include NCS_PLUGIN_PATH . 'templates/dashboard.php';
    }

    public function admin_settings_page()
    {
        if (isset($_POST['submit'])) {
            // بررسی nonce برای امنیت
            if (!wp_verify_nonce($_POST['_wpnonce'], 'ncs_settings')) {
                wp_die('خطای امنیتی');
            }

            // ذخیره تنظیمات
            update_option('ncs_token', sanitize_text_field($_POST['ncs_token']));
            update_option('ncs_hashid', sanitize_text_field($_POST['ncs_hashid']));

            echo '<div class="notice notice-success is-dismissible"><p>تنظیمات با موفقیت ذخیره شد.</p></div>';
        }

        $token = get_option('ncs_token');
        $hashid = get_option('ncs_hashid');

        include NCS_PLUGIN_PATH . 'templates/settings.php';
    }

    public function get_dashboard_stats()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'national_codes';
        $today = date('Y-m-d 00:00:00');

        return array(
            'total_codes' => $wpdb->get_var("SELECT COUNT(*) FROM $table_name"),
            'today_codes' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE created_date >= %s",
                $today
            )),
            'has_card' => $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE has_card = 1"),
            'has_account' => $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE has_success_sayyah = 1"),
            'without_card' => $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE has_success_sayyah = 1 AND has_card = 0"),
            'not_registered' => $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'not_registered'"),
            'not_checked' => $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'not_checked'")
        );
    }

    public function daily_fetch_national_codes()
    {
        $token = get_option('ncs_token');
        $hashid = get_option('ncs_hashid');

        if (empty($token)) {
            error_log('NCS Error: Token is empty');
            return false;
        }

        $url = "https://webapi.bakidz.ir/api/Referrer/GetReplaceReferrers?page=1&pageSize=10000&hashId=" . urlencode($hashid);

        $response = wp_remote_get($url, array(
            'headers' => array(
                'accept' => 'text/plain',
                'X-Version' => '.1.0',
                'Authorization' => 'bearer ' . $token,
                'Cookie' => 'cookiesession1=678B28B8597D9B8595C2F64692E2A933'
            ),
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            error_log('NCS Error: ' . $response->get_error_message());
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($data && $data['isSuccess']) {
            $result = $this->process_national_codes($data['entities']);

            // پس از دریافت کدهای جدید، جاب دوم را فعال کن
            $this->activate_check_job();

            return $result;
        }

        return false;
    }

    // فعال‌سازی جاب دوم پس از اجرای جاب اول
    private function activate_check_job()
    {
        // اگر جاب دوم زمان‌بندی نشده، آن را زمان‌بندی کن
        if (!wp_next_scheduled('ncs_daily_check_job')) {
            wp_schedule_event(time(), 'ncs_minutely', 'ncs_daily_check_job');
        }

        // وضعیت جاب را به آماده تغییر بده
        update_option('ncs_job_status', 'idle');
        update_option('ncs_check_job_counter', 0);

        // تمام کدهای ملی که کارت ندارند را برای بررسی مجدد علامت بزن
        $this->reset_check_status();
    }

    // بازنشانی وضعیت بررسی برای کدهایی که کارت ندارند
    private function reset_check_status()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'national_codes';

        $wpdb->query(
            "UPDATE $table_name 
             SET status = 'not_checked', 
                 last_checked = CURRENT_TIMESTAMP,
                 error_code = NULL
             WHERE has_card = 0 
             AND status != 'not_registered'"
        );

        error_log('NCS: Reset check status for codes without cards');
    }

    public function manual_fetch_national_codes()
    {
        // بررسی nonce
        if (!wp_verify_nonce($_POST['nonce'], 'ncs_nonce')) {
            wp_send_json_error('خطای امنیتی: Nonce نامعتبر');
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error('دسترسی غیرمجاز');
        }

        $result = $this->daily_fetch_national_codes();

        if ($result !== false) {
            wp_send_json_success(array(
                'message' => 'جاب اول با موفقیت اجرا شد و ' . $result . ' کد ملی جدید اضافه شد.',
                'new_codes' => $result
            ));
        } else {
            wp_send_json_error('خطا در اجرای جاب اول');
        }
    }

    public function manual_check_national_codes()
    {
        // بررسی nonce
        if (!wp_verify_nonce($_POST['nonce'], 'ncs_nonce')) {
            wp_send_json_error('خطای امنیتی: Nonce نامعتبر');
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error('دسترسی غیرمجاز');
        }

        $result = $this->daily_check_national_codes(true);

        if ($result !== false) {
            wp_send_json_success(array(
                'message' => 'جاب دوم با موفقیت اجرا شد. ' . $result . ' کد ملی بررسی شد.',
                'processed' => $result
            ));
        } else {
            wp_send_json_error('خطا در اجرای جاب دوم');
        }
    }

    public function truncate_table()
    {
        // بررسی nonce
        if (!wp_verify_nonce($_POST['nonce'], 'ncs_nonce')) {
            wp_send_json_error('خطای امنیتی: Nonce نامعتبر');
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error('دسترسی غیرمجاز');
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'national_codes';

        // ابتدا بررسی می‌کنیم جدول وجود دارد یا نه
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;

        if (!$table_exists) {
            wp_send_json_error('جدول مورد نظر وجود ندارد');
        }

        $result = $wpdb->query("TRUNCATE TABLE $table_name");

        if ($result !== false) {
            // پس از خالی کردن دیتابیس، وضعیت جاب‌ها را ریست کن
            update_option('ncs_job_status', 'idle');
            update_option('ncs_check_job_counter', 0);
            delete_option('ncs_last_check_job_run');
            delete_option('ncs_last_update');

            wp_send_json_success('دیتابیس با موفقیت خالی شد. تمام رکوردها حذف شدند.');
        } else {
            $error = $wpdb->last_error;
            wp_send_json_error('خطا در خالی کردن دیتابیس: ' . $error);
        }
    }

    public function export_to_excel()
    {
        // بررسی nonce
        if (!wp_verify_nonce($_POST['nonce'], 'ncs_nonce')) {
            wp_send_json_error('خطای امنیتی: Nonce نامعتبر');
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error('دسترسی غیرمجاز');
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'national_codes';

        // دریافت تمام داده‌ها
        $national_codes = $wpdb->get_results("SELECT * FROM $table_name ORDER BY id DESC");

        if (empty($national_codes)) {
            wp_send_json_error('هیچ داده‌ای برای صادرات وجود ندارد');
        }

        // ایجاد فایل CSV
        $filename = 'national_codes_export_' . date('Y-m-d_H-i-s') . '.csv';
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['path'] . '/' . $filename;

        $file = fopen($file_path, 'w');

        // هدرهای فایل CSV
        $headers = array(
            'کد ملی',
            'Hash ID',
            'وضعیت',
            'حساب دارد',
            'کارت دارد',
            'تاریخ صدور کارت',
            'آخرین بررسی',
            'تاریخ ایجاد',
            'کد خطا'
        );

        // اضافه کردن BOM برای نمایش صحیح فارسی در Excel
        fwrite($file, "\xEF\xBB\xBF");

        // نوشتن هدرها
        fputcsv($file, $headers);

        // نوشتن داده‌ها
        foreach ($national_codes as $code) {
            $status_text = '';
            switch ($code->status) {
                case 'not_checked':
                    $status_text = 'بررسی نشده';
                    break;
                case 'not_registered':
                    $status_text = 'ثبت نام نشده';
                    break;
                case 'has_card':
                    $status_text = 'کارت صادر شده';
                    break;
                case 'without_card':
                    $status_text = 'بدون کارت';
                    break;
                case 'no_account':
                    $status_text = 'حساب ندارد';
                    break;
                case 'error':
                    if ($code->error_code === '400') {
                        $status_text = 'ثبت نام نشده';
                    } elseif ($code->error_code === '429') {
                        $status_text = 'خطای 429 - تعداد درخواست زیاد';
                    } elseif ($code->error_code === '500') {
                        $status_text = 'خطای 500 - سرور';
                    } elseif ($code->error_code) {
                        $status_text = 'خطای ' . $code->error_code;
                    } else {
                        $status_text = 'خطا در بررسی';
                    }
                    break;
                default:
                    $status_text = $code->status;
            }

            $row = array(
                $code->national_code,
                $code->hash_id,
                $status_text,
                $code->has_success_sayyah ? 'بله' : 'خیر',
                $code->has_card ? 'بله' : 'خیر',
                $code->card_issuance_date ? NationalCodeStatus::timestamp_to_jalali($code->card_issuance_date) : '-',
                NationalCodeStatus::format_jalali_date($code->last_checked),
                NationalCodeStatus::format_jalali_date($code->created_date),
                $code->error_code ?: '-'
            );

            fputcsv($file, $row);
        }

        fclose($file);

        $file_url = $upload_dir['url'] . '/' . $filename;

        wp_send_json_success(array(
            'message' => 'فایل Excel با موفقیت ایجاد شد.',
            'file_url' => $file_url,
            'filename' => $filename
        ));
    }

    public function get_job_status()
    {
        // بررسی nonce
        if (!wp_verify_nonce($_POST['nonce'], 'ncs_nonce')) {
            wp_send_json_error('خطای امنیتی: Nonce نامعتبر');
        }

        $job_status = get_option('ncs_job_status', 'idle');
        $last_run = get_option('ncs_last_check_job_run', '');
        $next_run = wp_next_scheduled('ncs_daily_check_job');
        $job_counter = get_option('ncs_check_job_counter', 0);
        $last_update = get_option('ncs_last_update', '');

        wp_send_json_success(array(
            'status' => $job_status,
            'last_run' => $last_run ? NationalCodeStatus::format_jalali_date($last_run, 'Y/m/d H:i') : 'هنوز اجرا نشده',
            'next_run' => $next_run ? NationalCodeStatus::format_jalali_date($next_run, 'Y/m/d H:i') : 'زمان‌بندی نشده',
            'counter' => $job_counter,
            'last_update' => $last_update ? NationalCodeStatus::format_jalali_date($last_update, 'Y/m/d H:i') : 'هنوز بروزرسانی نشده'
        ));
    }

    private function process_national_codes($entities)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'national_codes';
        $new_codes = 0;

        foreach ($entities as $entity) {
            $national_code = $entity['nationalCode'];
            $hash_id = $entity['hashId'];
            $expire_date = $entity['expireDate']['seconds'];

            // بررسی وجود کد ملی در دیتابیس
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table_name WHERE national_code = %s",
                $national_code
            ));

            if (!$exists) {
                $wpdb->insert(
                    $table_name,
                    array(
                        'national_code' => $national_code,
                        'hash_id' => $hash_id,
                        'expire_date' => $expire_date,
                        'status' => 'not_checked'
                    ),
                    array('%s', '%s', '%d', '%s')
                );

                $new_codes++;
            }
        }

        // ذخیره تعداد کدهای جدید برای امروز
        update_option('ncs_today_new_codes', $new_codes);

        return $new_codes;
    }

    public function daily_check_national_codes($manual = false)
    {
        // اگر جاب در حال اجراست، از اجرای مجدد جلوگیری کن
        if ($this->is_job_running && !$manual) {
            error_log('NCS: Job is already running, skipping...');
            return 0;
        }

        $this->is_job_running = true;

        global $wpdb;

        // افزایش شمارنده اجرا
        $job_counter = get_option('ncs_check_job_counter', 0) + 1;
        update_option('ncs_check_job_counter', $job_counter);

        // به‌روزرسانی وضعیت جاب به "در حال اجرا"
        update_option('ncs_job_status', 'running');

        // ذخیره زمان اجرای جاب
        $current_time = current_time('mysql');
        update_option('ncs_last_check_job_run', $current_time);

        $table_name = $wpdb->prefix . 'national_codes';
        $token = get_option('ncs_token');

        if (empty($token)) {
            error_log('NCS: Token is empty, skipping daily check');
            update_option('ncs_job_status', 'idle');
            $this->is_job_running = false;
            return 0;
        }

        // دریافت حداکثر 13 کد ملی که وضعیت آن‌ها بررسی نشده یا خطا داشته‌اند و کارت ندارند
        $codes_to_check = $wpdb->get_results(
            "SELECT * FROM $table_name 
             WHERE (status = 'not_checked' OR status = 'error') 
             AND has_card = 0 
             ORDER BY last_checked ASC 
             LIMIT 13"
        );

        if (empty($codes_to_check)) {
            error_log('NCS: No codes to check, all are processed or have cards');
            update_option('ncs_job_status', 'completed');

            // اگر هیچ کدی برای بررسی نیست، وضعیت را برای دور بعدی بازنشانی کن
            $this->reset_check_status();

            $this->is_job_running = false;
            return 0;
        }

        $processed = 0;
        foreach ($codes_to_check as $code) {
            // فقط 13 درخواست در هر اجرا
            if ($processed >= 13) {
                break;
            }

            $result = $this->check_single_national_code_api($code->national_code);
            $processed++;

            if ($result !== false) {
                error_log("NCS: Successfully checked national code: {$code->national_code} (Status: {$result['status']})");
            } else {
                error_log("NCS: Failed to check national code: {$code->national_code}");
            }

            // تاخیر 4.6 ثانیه بین هر درخواست برای رعایت محدودیت (13 درخواست در دقیقه)
            if (!$manual) {
                sleep(5); // تقریباً 5 ثانیه تاخیر
            }
        }

        error_log("NCS: Daily check completed. Processed {$processed} national codes. Total executions: {$job_counter}");

        // ذخیره زمان آخرین بروزرسانی
        update_option('ncs_last_update', $current_time);

        // به‌روزرسانی وضعیت جاب
        if ($processed > 0) {
            update_option('ncs_job_status', 'idle');
        } else {
            update_option('ncs_job_status', 'completed');
        }

        $this->is_job_running = false;
        return $processed;
    }

    public function check_single_national_code()
    {
        // بررسی nonce
        if (!wp_verify_nonce($_POST['nonce'], 'ncs_nonce')) {
            wp_send_json_error('خطای امنیتی: Nonce نامعتبر');
        }

        $national_code = sanitize_text_field($_POST['national_code']);

        if (empty($national_code)) {
            wp_send_json_error('کد ملی الزامی است');
        }

        // اعتبارسنجی کد ملی
        if (!preg_match('/^\d{10}$/', $national_code)) {
            wp_send_json_error('کد ملی باید دقیقاً 10 رقم باشد');
        }

        $result = $this->check_single_national_code_api($national_code);

        if ($result) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error('خطا در بررسی کد ملی. ممکن است سرویس در دسترس نباشد.');
        }
    }

    private function check_single_national_code_api($national_code)
    {
        global $wpdb;

        $token = get_option('ncs_token');

        if (empty($token)) {
            return array('error' => 'توکن تنظیم نشده است');
        }

        $url = "https://webapi.bakidz.ir/api/FamilyManagement/GetChildSummary?nationalCode=" . urlencode($national_code);

        $response = wp_remote_get($url, array(
            'headers' => array(
                'accept' => 'text/plain',
                'X-Version' => '.1.0',
                'Authorization' => 'bearer ' . $token,
                'Cookie' => 'cookiesession1=678B28B8597D9B8595C2F64692E2A933'
            ),
            'timeout' => 30
        ));

        $table_name = $wpdb->prefix . 'national_codes';
        $current_time = current_time('mysql');

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            // بروزرسانی وضعیت به خطا
            $wpdb->update(
                $table_name,
                array(
                    'last_checked' => $current_time,
                    'status' => 'error',
                    'error_code' => 'NETWORK_ERROR'
                ),
                array('national_code' => $national_code),
                array('%s', '%s', '%s'),
                array('%s')
            );

            return false;
        }

        $status_code = wp_remote_retrieve_response_code($response);

        // مدیریت خطاهای مختلف
        if ($status_code === 400) {
            // کاربر ثبت نام نکرده
            $wpdb->update(
                $table_name,
                array(
                    'is_user_exists' => 0,
                    'has_success_sayyah' => 0,
                    'has_card' => 0,
                    'last_checked' => $current_time,
                    'status' => 'not_registered',
                    'error_code' => '400'
                ),
                array('national_code' => $national_code),
                array('%d', '%d', '%d', '%s', '%s', '%s'),
                array('%s')
            );

            return array('status' => 'not_registered', 'error_code' => '400');
        } elseif ($status_code === 429) {
            // تعداد درخواست زیاد
            $wpdb->update(
                $table_name,
                array(
                    'last_checked' => $current_time,
                    'status' => 'error',
                    'error_code' => '429'
                ),
                array('national_code' => $national_code),
                array('%s', '%s', '%s'),
                array('%s')
            );

            return array('status' => 'error', 'error_code' => '429');
        } elseif ($status_code >= 500) {
            // خطای سرور
            $wpdb->update(
                $table_name,
                array(
                    'last_checked' => $current_time,
                    'status' => 'error',
                    'error_code' => '500'
                ),
                array('national_code' => $national_code),
                array('%s', '%s', '%s'),
                array('%s')
            );

            return array('status' => 'error', 'error_code' => '500');
        } elseif ($status_code !== 200) {
            // سایر خطاها
            $wpdb->update(
                $table_name,
                array(
                    'last_checked' => $current_time,
                    'status' => 'error',
                    'error_code' => (string)$status_code
                ),
                array('national_code' => $national_code),
                array('%s', '%s', '%s'),
                array('%s')
            );

            return array('status' => 'error', 'error_code' => (string)$status_code);
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($data && $data['isSuccess']) {
            $entity = $data['entity'];

            $update_data = array(
                'is_user_exists' => $entity['isUserExists'] ? 1 : 0,
                'has_success_sayyah' => $entity['hasSuccessSayyah'] ? 1 : 0,
                'has_card' => $entity['hasCard'] ? 1 : 0,
                'last_checked' => $current_time,
                'error_code' => NULL
            );

            if ($entity['hasCard'] && isset($entity['cardIssuanceDate']['seconds'])) {
                $update_data['card_issuance_date'] = $entity['cardIssuanceDate']['seconds'];
            }

            // تعیین وضعیت
            if ($entity['hasCard']) {
                $update_data['status'] = 'has_card';
            } elseif ($entity['hasSuccessSayyah']) {
                $update_data['status'] = 'without_card';
            } else {
                $update_data['status'] = 'no_account';
            }

            $wpdb->update(
                $table_name,
                $update_data,
                array('national_code' => $national_code),
                array('%d', '%d', '%d', '%s', '%s', '%d', '%s'),
                array('%s')
            );

            return $update_data;
        }

        return false;
    }

    // تابع کمکی برای تبدیل تاریخ به شمسی
    public static function gregorian_to_jalali($gregorian_date)
    {
        return ncs_gregorian_to_jalali($gregorian_date);
    }

    // تابع کمکی برای فرمت‌بندی تاریخ شمسی
    public static function format_jalali_date($gregorian_date, $format = 'Y/m/d H:i')
    {
        return ncs_format_jalali_date($gregorian_date, $format);
    }

    // تابع کمکی برای تبدیل timestamp به تاریخ شمسی
    public static function timestamp_to_jalali($timestamp, $include_time = true)
    {
        return ncs_timestamp_to_jalali($timestamp, $include_time);
    }
}

// راه‌اندازی پلاگین
new NationalCodeStatus();
