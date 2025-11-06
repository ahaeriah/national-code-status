<?php
// templates/settings.php

// جلوگیری از دسترسی مستقیم
if (!defined('ABSPATH')) {
    exit;
}

// دریافت تنظیمات
$token = isset($token) ? $token : '';
$hashid = isset($hashid) ? $hashid : '';

// نمایش پیام‌های خطا یا موفقیت
if (isset($_GET['message'])) {
    $message_type = isset($_GET['message_type']) ? $_GET['message_type'] : 'success';
    $message = sanitize_text_field($_GET['message']);
    
    $class = ($message_type === 'error') ? 'notice-error' : 'notice-success';
    echo '<div class="notice ' . $class . ' is-dismissible"><p>' . esc_html($message) . '</p></div>';
}
?>

<div class="wrap">
    <h1>تنظیمات وضعیت کد ملی</h1>
    
    <div class="ncs-settings-container">
        <div class="ncs-settings-card">
            <h2>تنظیمات API</h2>
            
            <form method="post" action="">
                <?php wp_nonce_field('ncs_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="ncs_token">توکن (Token)</label>
                        </th>
                        <td>
                            <input type="password" 
                                   id="ncs_token" 
                                   name="ncs_token" 
                                   value="<?php echo esc_attr($token); ?>" 
                                   class="regular-text"
                                   placeholder="توکن API را وارد کنید" />
                            <p class="description">
                                توکن دسترسی به API را در اینجا وارد کنید.
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="ncs_hashid">Hash ID</label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="ncs_hashid" 
                                   name="ncs_hashid" 
                                   value="<?php echo esc_attr($hashid); ?>" 
                                   class="regular-text"
                                   placeholder="sa" />
                            <p class="description">
                                شناسه هش را وارد کنید (پیش‌فرض: sa)
                            </p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" 
                           name="submit" 
                           id="submit" 
                           class="button button-primary" 
                           value="ذخیره تنظیمات" />
                </p>
            </form>
        </div>
        
        <?php
        // نمایش بخش مدیریت کاربران فقط برای مدیران
        if (current_user_can('manage_options')) {
            $this->display_user_management_section();
        }
        ?>
        
        <div class="ncs-settings-card">
            <h2>راهنما</h2>
            
            <div class="ncs-help-section">
                <h3>نحوه دریافت توکن</h3>
                <ol>
                    <li>به پنل مدیریت وب سرویس مراجعه کنید</li>
                    <li>در بخش API Keys، توکن جدید ایجاد کنید</li>
                    <li>توکن ایجاد شده را در فیلد بالا وارد کنید</li>
                </ol>
            </div>
            
            <div class="ncs-help-section">
                <h3>محدودیت‌ها</h3>
                <ul>
                    <li>حداکثر ۱۳ درخواست در دقیقه</li>
                    <li>پویش کامل روزانه یکبار انجام می‌شود</li>
                    <li>در صورت دریافت خطای ۴۲۹، جاب متوقف می‌شود</li>
                </ul>
            </div>
        </div>
        
        <div class="ncs-settings-card">
            <h2>وضعیت سیستم</h2>
            
            <div class="ncs-status-items">
                <div class="ncs-status-item">
                    <span class="ncs-status-label">جاب اول (دریافت کدها):</span>
                    <span class="ncs-status-value">
                        <?php
                        $next_fetch = wp_next_scheduled('ncs_daily_fetch_job');
                        if ($next_fetch) {
                            echo 'فعال - اجرای بعدی: ' . NationalCodeStatus::format_jalali_date($next_fetch, 'Y/m/d H:i');
                        } else {
                            echo '<span style="color: #dc3232;">غیرفعال</span>';
                        }
                        ?>
                    </span>
                </div>
                
                <div class="ncs-status-item">
                    <span class="ncs-status-label">جاب دوم (بررسی کدها):</span>
                    <span class="ncs-status-value">
                        <?php
                        $next_check = wp_next_scheduled('ncs_daily_check_job');
                        $last_complete_scan = get_option('ncs_last_complete_scan_date', '');
                        
                        if ($next_check) {
                            if ($last_complete_scan === date('Y-m-d')) {
                                echo '<span style="color: #46b450;">پویش کامل امروز انجام شده</span>';
                            } else {
                                echo 'فعال - اجرای بعدی: ' . NationalCodeStatus::format_jalali_date($next_check, 'Y/m/d H:i');
                            }
                        } else {
                            echo '<span style="color: #dc3232;">غیرفعال</span>';
                        }
                        ?>
                    </span>
                </div>
                
                <div class="ncs-status-item">
                    <span class="ncs-status-label">آخرین پویش کامل:</span>
                    <span class="ncs-status-value">
                        <?php
                        if ($last_complete_scan) {
                            echo NationalCodeStatus::format_jalali_date($last_complete_scan, 'Y/m/d');
                        } else {
                            echo 'هنوز انجام نشده';
                        }
                        ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.ncs-settings-container {
    display: grid;
    grid-template-columns: 1fr;
    gap: 20px;
    margin-top: 20px;
}

.ncs-settings-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
}

.ncs-settings-card h2 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #ccd0d4;
}

.ncs-help-section {
    margin-bottom: 20px;
}

.ncs-help-section h3 {
    margin-top: 0;
    color: #23282d;
}

.ncs-help-section ol,
.ncs-help-section ul {
    margin-left: 20px;
}

.ncs-status-items {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.ncs-status-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #f0f0f0;
}

.ncs-status-label {
    font-weight: 600;
    color: #23282d;
}

.ncs-status-value {
    color: #0073aa;
}

@media (max-width: 1200px) {
    .ncs-settings-container {
        grid-template-columns: 1fr;
    }
}
</style>