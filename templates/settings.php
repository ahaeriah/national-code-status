<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1>تنظیمات وضعیت کد ملی - <?php echo $_SESSION['ncs_business_name']; ?></h1>
    
    <form method="post" action="">
        <?php wp_nonce_field('ncs_settings'); ?>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="ncs_token">توکن (Token)</label>
                </th>
                <td>
                    <input type="password" id="ncs_token" name="ncs_token" value="<?php echo esc_attr($token); ?>" class="regular-text" required>
                    <p class="description">توکن احراز هویت برای دسترسی به وب سرویس</p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="ncs_hashid">Hash ID</label>
                </th>
                <td>
                    <input type="text" id="ncs_hashid" name="ncs_hashid" value="<?php echo esc_attr($hashid); ?>" class="regular-text" required>
                    <p class="description">شناسه هش برای دریافت کدهای ملی</p>
                </td>
            </tr>
        </table>
        
        <?php submit_button('ذخیره تنظیمات'); ?>
    </form>
    
    <div class="ncs-danger-zone">
        <h3>منطقه خطر</h3>
        <div class="ncs-danger-content">
            <p><strong>⚠️ هشدار:</strong> این عمل تمام داده‌های جدول کدهای ملی را پاک می‌کند و غیرقابل بازگشت است.</p>
            <button type="button" id="ncs-truncate-btn" class="button button-danger">خالی کردن کامل دیتابیس</button>
            <span id="ncs-truncate-result" style="margin-right: 15px;"></span>
        </div>
    </div>
</div>