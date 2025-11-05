<?php
// امنیت - جلوگیری از دسترسی مستقیم
if (!defined('ABSPATH')) {
    exit;
}

// دریافت وضعیت جاب
$job_status = get_option('ncs_job_status', 'idle');
$job_counter = get_option('ncs_check_job_counter', 0);
$last_update = get_option('ncs_last_update', '');
$status_labels = array(
    'idle' => 'آماده',
    'running' => 'در حال اجرا',
    'completed' => 'تکمیل شده'
);
$status_colors = array(
    'idle' => '#28a745',
    'running' => '#ffc107',
    'completed' => '#17a2b8'
);
?>

<div class="wrap ncs-dashboard">
    <h1>داشبورد وضعیت کد ملی</h1>
    
    <!-- بخش کنترل دستی -->
    <div class="ncs-manual-controls">
        <div class="ncs-control-card">
            <h3>کنترل دستی جاب‌ها</h3>
            <div class="ncs-control-buttons">
                <button type="button" id="ncs-manual-fetch-btn" class="button button-primary">
                    اجرای دستی جاب اول
                </button>
                <button type="button" id="ncs-manual-check-btn" class="button button-secondary">
                    اجرای دستی جاب دوم
                </button>
                <span id="ncs-manual-fetch-result" style="margin-right: 15px;"></span>
                <span id="ncs-manual-check-result" style="margin-right: 15px;"></span>
            </div>
            <div class="ncs-job-status">
                <div class="ncs-status-indicator">
                    <span class="ncs-status-label">وضعیت جاب دوم:</span>
                    <span class="ncs-status-badge" id="ncs-job-status-badge" 
                          style="background-color: <?php echo $status_colors[$job_status]; ?>">
                        <?php echo $status_labels[$job_status]; ?>
                    </span>
                    <span class="ncs-job-counter">(اجرا شده: <?php echo $job_counter; ?> بار)</span>
                </div>
                <div class="ncs-status-details">
                    <div><strong>جاب اول:</strong> دریافت کدهای ملی از وب سرویس - 
                        <?php if ($next_fetch_job): ?>
                            اجرای بعدی: <?php echo NationalCodeStatus::format_jalali_date($next_fetch_job, 'Y/m/d H:i'); ?>
                        <?php else: ?>
                            زمان‌بندی نشده
                        <?php endif; ?>
                    </div>
                    <div><strong>جاب دوم:</strong> بررسی وضعیت کدها - 
                        <?php if ($next_check_job): ?>
                            اجرای بعدی: <span id="ncs-next-run"><?php echo NationalCodeStatus::format_jalali_date($next_check_job, 'Y/m/d H:i'); ?></span>
                        <?php else: ?>
                            <span style="color: #dc3232;">زمان‌بندی نشده - لطفا جاب اول را اجرا کنید</span>
                        <?php endif; ?>
                        <?php if ($last_check_job_run): ?>
                            | آخرین اجرا: <span id="ncs-last-run"><?php echo NationalCodeStatus::format_jalali_date($last_check_job_run, 'Y/m/d H:i'); ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($last_update): ?>
                    <div><strong>آخرین بروزرسانی داده‌ها:</strong> <span id="ncs-last-update"><?php echo NationalCodeStatus::format_jalali_date($last_update, 'Y/m/d H:i'); ?></span></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- بخش خروجی Excel -->
    <div class="ncs-export-section">
        <h3>خروجی داده‌ها</h3>
        <button type="button" id="ncs-export-excel-btn" class="button button-excel">
            📊 خروجی Excel
        </button>
        <span id="ncs-export-result" style="margin-right: 15px;"></span>
        <p class="description">تمامی داده‌های موجود در جدول را در قالب فایل Excel دریافت کنید</p>
    </div>
    
    <!-- بخش آمار -->
    <div class="ncs-stats">
        <div class="ncs-stat-card">
            <h3>تعداد کل کد ملی</h3>
            <span class="stat-number"><?php echo $stats['total_codes']; ?></span>
        </div>
        <div class="ncs-stat-card">
            <h3>کد ملی امروز</h3>
            <span class="stat-number"><?php echo $stats['today_codes']; ?></span>
        </div>
        <div class="ncs-stat-card">
            <h3>کارت صادر شده</h3>
            <span class="stat-number"><?php echo $stats['has_card']; ?></span>
        </div>
        <div class="ncs-stat-card">
            <h3>حساب ایجاد شده</h3>
            <span class="stat-number"><?php echo $stats['has_account']; ?></span>
        </div>
        <div class="ncs-stat-card">
            <h3>بدون کارت</h3>
            <span class="stat-number"><?php echo $stats['without_card']; ?></span>
        </div>
        <div class="ncs-stat-card">
            <h3>ثبت نام نکرده</h3>
            <span class="stat-number"><?php echo $stats['not_registered']; ?></span>
        </div>
        <div class="ncs-stat-card">
            <h3>بررسی نشده</h3>
            <span class="stat-number"><?php echo $stats['not_checked']; ?></span>
        </div>
    </div>
    
    <!-- بخش بررسی تک کد ملی -->
    <div class="ncs-single-check">
        <h2>بررسی تک کد ملی</h2>
        <div class="ncs-check-form">
            <input type="text" id="ncs-single-code" placeholder="کد ملی را وارد کنید" maxlength="10" pattern="\d{10}">
            <button type="button" id="ncs-check-btn" class="button button-primary">بررسی</button>
        </div>
        <div id="ncs-check-result" style="display: none;"></div>
    </div>
    
    <!-- جدول کدهای ملی -->
    <div class="ncs-table-section">
        <h2>لیست کدهای ملی</h2>
        
        <!-- کنترل‌های صفحه‌بندی -->
        <div class="ncs-table-controls">
            <form method="get">
                <input type="hidden" name="page" value="national-code-status">
                <label for="per_page">تعداد در صفحه:</label>
                <select name="per_page" id="per_page" onchange="this.form.submit()">
                    <option value="50" <?php selected($per_page, 50); ?>>50</option>
                    <option value="100" <?php selected($per_page, 100); ?>>100</option>
                    <option value="200" <?php selected($per_page, 200); ?>>200</option>
                    <option value="500" <?php selected($per_page, 500); ?>>500</option>
                </select>
            </form>
        </div>
        
        <table class="wp-list-table widefat fixed striped ncs-colored-table">
            <thead>
                <tr>
                    <th>کد ملی</th>
                    <th>Hash ID</th>
                    <th>وضعیت</th>
                    <th>حساب دارد</th>
                    <th>کارت دارد</th>
                    <th>تاریخ صدور کارت</th>
                    <th>آخرین بررسی</th>
                    <th>تاریخ ایجاد</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($national_codes)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center;">هیچ داده‌ای یافت نشد</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($national_codes as $code): ?>
                        <?php
                        $row_class = '';
                        if ($code->has_card) {
                            $row_class = 'ncs-row-has-card';
                        } elseif ($code->status === 'not_registered') {
                            $row_class = 'ncs-row-not-registered';
                        } elseif ($code->status === 'error') {
                            $row_class = 'ncs-row-error';
                        } elseif ($code->status === 'without_card') {
                            $row_class = 'ncs-row-without-card';
                        }
                        
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
                        ?>
                        <tr class="<?php echo $row_class; ?>">
                            <td><?php echo esc_html($code->national_code); ?></td>
                            <td><?php echo esc_html($code->hash_id); ?></td>
                            <td><?php echo $status_text; ?></td>
                            <td><?php echo $code->has_success_sayyah ? '✅' : '❌'; ?></td>
                            <td><?php echo $code->has_card ? '✅' : '❌'; ?></td>
                            <td>
                                <?php 
                                if ($code->card_issuance_date) {
                                    echo NationalCodeStatus::timestamp_to_jalali($code->card_issuance_date);
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                            <td><?php echo NationalCodeStatus::format_jalali_date($code->last_checked); ?></td>
                            <td><?php echo NationalCodeStatus::format_jalali_date($code->created_date); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        
        <!-- صفحه‌بندی -->
        <div class="ncs-pagination">
            <?php
            echo paginate_links(array(
                'base' => add_query_arg('paged', '%#%'),
                'format' => '',
                'prev_text' => '&laquo; قبلی',
                'next_text' => 'بعدی &raquo;',
                'total' => $total_pages,
                'current' => $current_page
            ));
            ?>
        </div>
    </div>
</div>