jQuery(document).ready(function ($) {
  console.log('NCS Admin JS loaded successfully');

  // بررسی تک کد ملی
  $('#ncs-check-btn').on('click', function () {
    var nationalCode = $('#ncs-single-code').val().trim();
    var $button = $(this);
    var $result = $('#ncs-check-result');

    if (!nationalCode) {
      alert('لطفا کد ملی را وارد کنید');
      return;
    }

    // اعتبارسنجی ساده کد ملی
    if (nationalCode.length !== 10 || !/^\d+$/.test(nationalCode)) {
      alert('کد ملی باید 10 رقم باشد');
      return;
    }

    $button.prop('disabled', true).text('در حال بررسی...');
    $result.hide();

    console.log('Sending AJAX request for single code check');

    $.ajax({
      url: ncs_ajax.ajax_url,
      type: 'POST',
      data: {
        action: 'ncs_check_single_code',
        national_code: nationalCode,
        nonce: ncs_ajax.nonce
      },
      success: function (response) {
        console.log('Single code check response:', response);
        if (response.success) {
          var data = response.data;
          var statusText = '';

          if (data.status === 'has_card') {
            statusText = '✅ کارت صادر شده';
          } else if (data.status === 'without_card') {
            statusText = '⚠️ حساب دارد اما کارت ندارد';
          } else if (data.status === 'not_registered') {
            statusText = '❌ ثبت نام نکرده';
          } else if (data.status === 'no_account') {
            statusText = '❌ حساب ندارد';
          } else if (data.status === 'error') {
            if (data.error_code === '400') {
              statusText = '❌ ثبت نام نشده';
            } else if (data.error_code === '429') {
              statusText = '❌ خطای 429 - تعداد درخواست زیاد';
            } else if (data.error_code === '500') {
              statusText = '❌ خطای 500 - سرور';
            } else {
              statusText = '❌ خطا در بررسی';
            }
          } else {
            statusText = '🔍 وضعیت نامشخص';
          }

          var resultHtml = '<strong>نتیجه بررسی:</strong> ' + statusText + '<br>' +
            '<strong>حساب دارد:</strong> ' + (data.has_success_sayyah ? '✅' : '❌') + '<br>' +
            '<strong>کارت دارد:</strong> ' + (data.has_card ? '✅' : '❌');

          if (data.card_issuance_date) {
            resultHtml += '<br><strong>تاریخ صدور کارت:</strong> ' + data.card_issuance_date;
          }

          $result.html(resultHtml).removeClass('ncs-check-error').addClass('ncs-check-success').show();
        } else {
          $result.html('❌ خطا: ' + response.data).removeClass('ncs-check-success').addClass('ncs-check-error').show();
        }
      },
      error: function (xhr, status, error) {
        console.log('Single code check error:', error);
        var errorMessage = 'خطا در ارتباط با سرور';
        if (xhr.responseJSON && xhr.responseJSON.data) {
          errorMessage = xhr.responseJSON.data;
        }
        $result.html('❌ ' + errorMessage).removeClass('ncs-check-success').addClass('ncs-check-error').show();
      },
      complete: function () {
        $button.prop('disabled', false).text('بررسی');
      }
    });
  });

  // فعالسازی ارسال با Enter
  $('#ncs-single-code').on('keypress', function (e) {
    if (e.which === 13) {
      $('#ncs-check-btn').click();
    }
  });

  // اجرای دستی جاب اول
  $('#ncs-manual-fetch-btn').on('click', function () {
    var $button = $(this);
    var $result = $('#ncs-manual-fetch-result');

    if (!confirm('آیا مطمئن هستید که می‌خواهید جاب اول را به صورت دستی اجرا کنید؟')) {
      return;
    }

    $button.prop('disabled', true).text('در حال اجرا...');
    $result.html('<span class="spinner is-active" style="float: none; margin: 0 5px;"></span> در حال اجرا...');

    console.log('Sending AJAX request for manual fetch');

    $.ajax({
      url: ncs_ajax.ajax_url,
      type: 'POST',
      data: {
        action: 'ncs_manual_fetch',
        nonce: ncs_ajax.nonce
      },
      success: function (response) {
        console.log('Manual fetch response:', response);
        if (response.success) {
          $result.html('✅ ' + response.data.message);
          // رفرش صفحه بعد از 3 ثانیه برای بروزرسانی آمار
          setTimeout(function () {
            location.reload();
          }, 3000);
        } else {
          $result.html('❌ ' + response.data);
        }
      },
      error: function (xhr, status, error) {
        console.log('Manual fetch error:', error);
        $result.html('❌ خطا در ارتباط با سرور: ' + error);
      },
      complete: function () {
        $button.prop('disabled', false).text('اجرای دستی جاب اول');
      }
    });
  });

  // اجرای دستی جاب دوم
  $('#ncs-manual-check-btn').on('click', function () {
    var $button = $(this);
    var $result = $('#ncs-manual-check-result');

    if (!confirm('آیا مطمئن هستید که می‌خواهید جاب دوم را به صورت دستی اجرا کنید؟')) {
      return;
    }

    $button.prop('disabled', true).text('در حال اجرا...');
    $result.html('<span class="spinner is-active" style="float: none; margin: 0 5px;"></span> در حال اجرای جاب دوم...');

    console.log('Sending AJAX request for manual check');

    $.ajax({
      url: ncs_ajax.ajax_url,
      type: 'POST',
      data: {
        action: 'ncs_manual_check',
        nonce: ncs_ajax.nonce
      },
      success: function (response) {
        console.log('Manual check response:', response);
        if (response.success) {
          $result.html('✅ ' + response.data.message);
          // رفرش صفحه بعد از 2 ثانیه برای نمایش نتایج جدید
          setTimeout(function () {
            location.reload();
          }, 2000);
        } else {
          $result.html('❌ ' + response.data);
        }
      },
      error: function (xhr, status, error) {
        console.log('Manual check error:', error);
        $result.html('❌ خطا در ارتباط با سرور: ' + error);
      },
      complete: function () {
        $button.prop('disabled', false).text('اجرای دستی جاب دوم');
      }
    });
  });

  // خالی کردن دیتابیس
  $('#ncs-truncate-btn').on('click', function () {
    var $button = $(this);
    var $result = $('#ncs-truncate-result');

    if (!confirm('⚠️ هشدار: این عمل تمام داده‌های جدول کدهای ملی را پاک می‌کند و غیرقابل بازگشت است.\n\nآیا مطمئن هستید؟')) {
      return;
    }

    var confirmText = prompt('برای تایید، لطفا عبارت "خالی کردن دیتابیس" را تایپ کنید:');
    if (confirmText !== 'خالی کردن دیتابیس') {
      alert('عبارت تایید نادرست است. عملیات لغو شد.');
      return;
    }

    $button.prop('disabled', true).text('در حال خالی کردن...');
    $result.html('<span class="spinner is-active" style="float: none; margin: 0 5px;"></span> در حال خالی کردن دیتابیس...');

    console.log('Sending AJAX request for truncate table');

    $.ajax({
      url: ncs_ajax.ajax_url,
      type: 'POST',
      data: {
        action: 'ncs_truncate_table',
        nonce: ncs_ajax.nonce
      },
      success: function (response) {
        console.log('Truncate table response:', response);
        if (response.success) {
          $result.html('✅ ' + response.data);
          // رفرش صفحه بعد از 3 ثانیه برای بروزرسانی آمار
          setTimeout(function () {
            location.reload();
          }, 3000);
        } else {
          $result.html('❌ ' + response.data);
        }
      },
      error: function (xhr, status, error) {
        console.log('Truncate table error:', error);
        $result.html('❌ خطا در ارتباط با سرور: ' + error);
      },
      complete: function () {
        $button.prop('disabled', false).text('خالی کردن کامل دیتابیس');
      }
    });
  });

  // صادرات به اکسل
  $('#ncs-export-excel-btn').on('click', function () {
    var $button = $(this);
    var $result = $('#ncs-export-result');

    if (!confirm('آیا می‌خواهید تمام داده‌ها را به صورت فایل Excel ذخیره کنید؟')) {
      return;
    }

    $button.prop('disabled', true).text('در حال تولید فایل...');
    $result.html('<span class="spinner is-active" style="float: none; margin: 0 5px;"></span> در حال تولید فایل Excel...');

    console.log('Sending AJAX request for Excel export');

    $.ajax({
      url: ncs_ajax.ajax_url,
      type: 'POST',
      data: {
        action: 'ncs_export_to_excel',
        nonce: ncs_ajax.nonce
      },
      success: function (response) {
        console.log('Excel export response:', response);
        if (response.success) {
          $result.html('✅ ' + response.data.message);

          // دانلود فایل
          if (response.data.file_url) {
            window.open(response.data.file_url, '_blank');
          }

          // پاک کردن پیام بعد از 5 ثانیه
          setTimeout(function () {
            $result.html('');
          }, 5000);
        } else {
          $result.html('❌ ' + response.data);
        }
      },
      error: function (xhr, status, error) {
        console.log('Excel export error:', error);
        $result.html('❌ خطا در تولید فایل Excel: ' + error);
      },
      complete: function () {
        $button.prop('disabled', false).text('خروجی Excel');
      }
    });
  });

  // بررسی وضعیت جاب به صورت دوره‌ای
  function updateJobStatus() {
    $.ajax({
      url: ncs_ajax.ajax_url,
      type: 'POST',
      data: {
        action: 'ncs_get_job_status',
        nonce: ncs_ajax.nonce
      },
      success: function (response) {
        if (response.success) {
          var data = response.data;
          var statusColors = {
            'idle': '#28a745',
            'running': '#ffc107',
            'completed': '#17a2b8'
          };
          var statusLabels = {
            'idle': 'آماده',
            'running': 'در حال اجرا',
            'completed': 'تکمیل شده'
          };

          $('#ncs-job-status-badge')
            .text(statusLabels[data.status])
            .css('background-color', statusColors[data.status]);

          // به‌روزرسانی شمارنده اجراها
          $('.ncs-job-counter').text('(اجرا شده: ' + data.counter + ' بار)');

          if (data.last_run) {
            $('#ncs-last-run').text(data.last_run);
          }
          if (data.next_run) {
            $('#ncs-next-run').text(data.next_run);
          }
          if (data.last_update) {
            $('#ncs-last-update').text(data.last_update);
          }

          // اگر جاب دوم در حال اجراست، هر 5 ثانیه وضعیت را چک کن
          if (data.status === 'running') {
            setTimeout(updateJobStatus, 5000);
          } else {
            // اگر جاب تمام شده یا آماده است، هر 30 ثانیه وضعیت را چک کن
            setTimeout(updateJobStatus, 30000);
          }
        }
      },
      error: function () {
        // در صورت خطا، بعد از 30 ثانیه دوباره تلاش کن
        setTimeout(updateJobStatus, 30000);
      }
    });
  }

  // شروع به‌روزرسانی وضعیت
  updateJobStatus();

  // رفرش خودکار صفحه زمانی که جاب دوم در حال اجراست
  function checkForAutoRefresh() {
    $.ajax({
      url: ncs_ajax.ajax_url,
      type: 'POST',
      data: {
        action: 'ncs_get_job_status',
        nonce: ncs_ajax.nonce
      },
      success: function (response) {
        if (response.success) {
          var data = response.data;

          // اگر جاب دوم در حال اجراست، هر 10 ثانیه صفحه را رفرش کن
          if (data.status === 'running') {
            setTimeout(function () {
              location.reload();
            }, 10000);
          } else {
            // اگر جاب تمام شده، 3 ثانیه صبر کن و سپس صفحه را رفرش کن
            if (data.status === 'completed') {
              setTimeout(function () {
                location.reload();
              }, 3000);
            } else {
              // در غیر این صورت بعد از 30 ثانیه چک کن
              setTimeout(checkForAutoRefresh, 30000);
            }
          }
        }
      },
      error: function () {
        setTimeout(checkForAutoRefresh, 30000);
      }
    });
  }

  // شروع چک برای رفرش خودکار
  checkForAutoRefresh();
});