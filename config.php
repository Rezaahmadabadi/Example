<?php
// تنظیمات سیستم
define('SITE_NAME', 'سیستم پیگیری فاکتور');
define('SITE_URL', 'http://localhost/invoice-system/');
define('DATA_DIR', __DIR__ . '/../data/');
define('UPLOAD_DIR', __DIR__ . '/../uploads/');

// ایجاد پوشه‌های مورد نیاز
if (!file_exists(DATA_DIR)) mkdir(DATA_DIR, 0777, true);
if (!file_exists(UPLOAD_DIR . 'invoices/')) mkdir(UPLOAD_DIR . 'invoices/', 0777, true);
if (!file_exists(UPLOAD_DIR . 'profile-pics/')) mkdir(UPLOAD_DIR . 'profile-pics/', 0777, true);
if (!file_exists(UPLOAD_DIR . 'chat-files/')) mkdir(UPLOAD_DIR . 'chat-files/', 0777, true);

// شروع session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?> 
