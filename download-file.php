<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

if (!isLoggedIn()) {
    header('HTTP/1.0 403 Forbidden');
    exit('دسترسی غیرمجاز');
}

if (!isset($_GET['type']) || !isset($_GET['file'])) {
    header('HTTP/1.0 400 Bad Request');
    exit('پارامترهای لازم ارسال نشده است');
}

$type = $_GET['type'];
$filename = $_GET['file'];
$original_name = $_GET['original_name'] ?? $filename;

// تعیین مسیر بر اساس نوع فایل
$base_path = '';
switch ($type) {
    case 'tax-system':
        $base_path = UPLOAD_DIR . 'tax-system/';
        break;
    case 'invoice':
        $base_path = UPLOAD_DIR . 'invoices/';
        break;
    case 'chat':
        $base_path = UPLOAD_DIR . 'chat-files/';
        break;
    case 'profile':
        $base_path = UPLOAD_DIR . 'profile-pics/';
        break;
    default:
        header('HTTP/1.0 400 Bad Request');
        exit('نوع فایل نامعتبر است');
}

$file_path = $base_path . $filename;

// بررسی وجود فایل
if (!file_exists($file_path)) {
    header('HTTP/1.0 404 Not Found');
    exit('فایل یافت نشد');
}

// تنظیم هدرهای دانلود
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $original_name . '"');
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($file_path));

// پاک کردن بافر خروجی
ob_clean();
flush();
readfile($file_path);
exit;
?>