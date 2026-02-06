<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

if (!isAdmin()) {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $department = $_POST['department'];
    $options = $_POST['options'] ?? [];
    
    // پردازش داده‌ها
    $processed_options = [];
    foreach ($options as $option) {
        if (!empty($option['text'])) {
            $processed_options[] = [
                'id' => uniqid(substr($department, 0, 1)),
                'text' => trim($option['text']),
                'mandatory' => isset($option['mandatory']) && $option['mandatory'] == '1'
            ];
        }
    }
    
    // ذخیره تنظیمات
    $settings = loadData('approval-settings');
    $settings[$department]['options'] = $processed_options;
    
    if (saveData('approval-settings', $settings)) {
        $_SESSION['success_message'] = '✅ تنظیمات با موفقیت ذخیره شد';
    } else {
        $_SESSION['error_message'] = '❌ خطا در ذخیره تنظیمات';
    }
    
    header('Location: admin-panel.php#approval-settings');
    exit();
}

header('Location: admin-panel.php');
?>