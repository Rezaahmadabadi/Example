<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

if (!isset($_GET['id'])) {
    header('Location: tax-system.php');
    exit();
}

$transaction_id = $_GET['id'];
$tax_transactions = loadData('tax-transactions');
$users = loadData('users');

// پیدا کردن تراکنش
$transaction = null;
foreach ($tax_transactions as $tx) {
    if ($tx['id'] === $transaction_id) {
        $transaction = $tx;
        break;
    }
}

if (!$transaction) {
    header('Location: tax-system.php');
    exit();
}

$hasAccess = in_array($_SESSION['user_id'], $transaction['assigned_to']) || 
             $_SESSION['user_id'] === $transaction['created_by'];

if (!$hasAccess) {
    header('Location: tax-system.php?error=no_access');
    exit();
}

// 1. منطق اصلاح شده مشاهده (View Logic)
if ($_SESSION['user_id'] != $transaction['created_by']) {
    if (!isset($transaction['viewed_by'][$_SESSION['user_id']])) {
        foreach ($tax_transactions as &$tx) {
            if ($tx['id'] === $transaction_id) {
                $tx['viewed_by'][$_SESSION['user_id']] = time();
                $tx['history'][] = [
                    'action' => 'view',
                    'user_id' => $_SESSION['user_id'],
                    'timestamp' => time(),
                    'description' => 'مشاهده فایل'
                ];
                
                // اطلاع به ارسال کننده اصلی
                sendNotification(
                    $transaction['created_by'],
                    "فایل توسط " . $_SESSION['username'] . " مشاهده شد: " . $transaction['title'],
                    null
                );
                break;
            }
        }
        saveData('tax-transactions', $tax_transactions);
        $transaction['viewed_by'][$_SESSION['user_id']] = time();
    }
}

// 2. منطق افزودن کاربر جدید به زنجیره
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user_to_chain'])) {
    $new_user_id = $_POST['new_user_id'];
    $referral_note = trim($_POST['referral_note']);
    
    if ($new_user_id && $new_user_id !== $_SESSION['user_id']) {
        foreach ($tax_transactions as &$tx) {
            if ($tx['id'] === $transaction_id) {
                if (!in_array($new_user_id, $tx['assigned_to'])) {
                    $tx['assigned_to'][] = $new_user_id;
                    $tx['updated_at'] = time();
                    
                    $new_user_info = getUser($new_user_id);
                    $tx['history'][] = [
                        'action' => 'refer',
                        'user_id' => $_SESSION['user_id'],
                        'timestamp' => time(),
                        'description' => 'افزودن ' . ($new_user_info['username'] ?? 'کاربر') . ' به زنجیره' . ($referral_note ? ": $referral_note" : '')
                    ];
                    
                    sendNotification(
                        $new_user_id,
                        "شما به پرونده '" . $tx['title'] . "' اضافه شدید توسط " . $_SESSION['username'],
                        null
                    );

                    saveData('tax-transactions', $tax_transactions);
                    header('Location: tax-system-view.php?id=' . $transaction_id . '&message=user_added');
                    exit();
                }
            }
        }
    }
}

// ارسال پاسخ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_reply'])) {
    $reply_data = [
        'id' => uniqid(),
        'user_id' => $_SESSION['user_id'],
        'message' => trim($_POST['reply_message']),
        'timestamp' => time(),
        'files' => []
    ];
    
    if (isset($_FILES['reply_files']) && is_array($_FILES['reply_files']['name'])) {
        foreach ($_FILES['reply_files']['name'] as $key => $name) {
            if ($_FILES['reply_files']['error'][$key] === UPLOAD_ERR_OK) {
                $file = [
                    'name' => $_FILES['reply_files']['name'][$key],
                    'type' => $_FILES['reply_files']['type'][$key],
                    'tmp_name' => $_FILES['reply_files']['tmp_name'][$key],
                    'error' => $_FILES['reply_files']['error'][$key],
                    'size' => $_FILES['reply_files']['size'][$key]
                ];
                
                $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = uniqid() . '_reply.' . $file_extension;
                $upload_path = UPLOAD_DIR . 'tax-system/' . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                    $reply_data['files'][] = [
                        'filename' => $filename,
                        'original_name' => $file['name']
                    ];
                }
            }
        }
    }
    
    foreach ($tax_transactions as &$tx) {
        if ($tx['id'] === $transaction_id) {
            if (!isset($tx['replies'])) {
                $tx['replies'] = [];
            }
            $tx['replies'][] = $reply_data;
            $tx['updated_at'] = time();
            
            if ($tx['status'] === 'new') {
                $tx['status'] = 'in-progress';
            }
            
            $tx['history'][] = [
                'action' => 'reply',
                'user_id' => $_SESSION['user_id'],
                'timestamp' => time(),
                'description' => 'ارسال پاسخ'
            ];
            break;
        }
    }
    saveData('tax-transactions', $tax_transactions);
    
    $chain_users = array_unique(array_merge([$transaction['created_by']], $transaction['assigned_to']));
    foreach ($chain_users as $uid) {
        if ($uid != $_SESSION['user_id']) {
            sendNotification(
                $uid,
                "پاسخ جدید در پرونده: " . $transaction['title'],
                null
            );
        }
    }
    
    header('Location: tax-system-view.php?id=' . $transaction_id . '&message=reply_sent');
    exit();
}

// تغییر وضعیت
if (isset($_GET['change_status']) && $_SESSION['user_id'] == $transaction['created_by']) {
    $new_status = $_GET['change_status'];
    $status_names = [
        'new' => 'جدید',
        'in-progress' => 'در حال بررسی',
        'completed' => 'تکمیل شده',
        'cancelled' => 'لغو شده'
    ];
    
    if ($new_status === 'completed' || $new_status === 'cancelled') {
        $confirm_message = $new_status === 'completed' ? 
            "آیا از تکمیل و بستن این پرونده اطمینان دارید؟ پس از بستن، هیچ کاربری نمی‌تواند پاسخ جدید ارسال کند." :
            "آیا از لغو این پرونده اطمینان دارید؟ پرونده لغو شده از لیست کاربران حذف خواهد شد.";
        
        if (!isset($_GET['confirm']) || $_GET['confirm'] !== 'yes') {
            echo "<script>
                if(confirm('$confirm_message')) {
                    window.location.href = 'tax-system-view.php?id=$transaction_id&change_status=$new_status&confirm=yes';
                } else {
                    window.location.href = 'tax-system-view.php?id=$transaction_id';
                }
            </script>";
            exit();
        }
    }
    
    foreach ($tax_transactions as &$tx) {
        if ($tx['id'] === $transaction_id) {
            $tx['status'] = $new_status;
            $tx['updated_at'] = time();
            
            $tx['history'][] = [
                'action' => 'status_change',
                'user_id' => $_SESSION['user_id'],
                'timestamp' => time(),
                'description' => 'تغییر وضعیت به ' . ($status_names[$new_status] ?? $new_status)
            ];
            
            if ($new_status === 'cancelled') {
                $chain_users = array_unique(array_merge([$transaction['created_by']], $transaction['assigned_to']));
                foreach ($chain_users as $uid) {
                    if ($uid != $_SESSION['user_id']) {
                        sendNotification(
                            $uid,
                            "پرونده لغو شد: " . $transaction['title'],
                            null
                        );
                    }
                }
            }
            break;
        }
    }
    saveData('tax-transactions', $tax_transactions);
    header('Location: tax-system-view.php?id=' . $transaction_id);
    exit();
}

$creator = getUser($transaction['created_by']);
$current_user = getUser($_SESSION['user_id']);
$remaining_days = ceil(($transaction['deadline_timestamp'] - time()) / (24 * 60 * 60));

// هدر
$current_user_header = getUser($_SESSION['user_id']);
$tax_notifications = getUnreadTaxTransactionsCount($_SESSION['user_id']);
$invoice_notifications = getUnreadInvoicesCount($_SESSION['user_id']);
$chat_notifications = getUnreadChatMessagesCount($_SESSION['user_id']);

$avatar_path_header = isset($current_user_header['avatar']) ? 'uploads/profile-pics/' . $current_user_header['avatar'] : '';

$current_user_sidebar = getUser($_SESSION['user_id']);
$sidebar_avatar = '';
if ($current_user_sidebar && isset($current_user_sidebar['avatar']) && file_exists('uploads/profile-pics/' . $current_user_sidebar['avatar'])) {
    $sidebar_avatar = 'uploads/profile-pics/' . $current_user_sidebar['avatar'];
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مشاهده پرونده - سامانه مودیان</title>
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" href="assets/icons/favicon.ico">
    <style>
        :root {
            --primary: #007AFF;
            --success: #34C759;
            --warning: #FF9500;
            --danger: #FF3B30;
            --text-primary: #1D1D1F;
            --text-secondary: #86868B;
            --glass-bg: rgba(255, 255, 255, 0.4);
            --glass-border: rgba(255, 255, 255, 0.5);
            --glass-hover: rgba(255, 255, 255, 0.6);
            --shadow-soft: 0 4px 20px rgba(0, 0, 0, 0.08);
            --shadow-card: 0 2px 12px rgba(0, 0, 0, 0.06);
            --sidebar-width: 280px;
            --header-height: 70px;
            --radius: 20px;
            --radius-sm: 14px;
            --dark-bg: #1a1a2e;
            --dark-secondary: #16213e;
        }

        body {
            font-family: 'Vazirmatn', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, var(--dark-bg) 0%, var(--dark-secondary) 100%);
            min-height: 100vh;
            direction: rtl;
            overflow-x: hidden;
            margin: 0;
        }

        /* ========== SIDEBAR ========== */
        .sidebar {
            position: fixed;
            right: 0;
            top: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(80px) saturate(180%);
            -webkit-backdrop-filter: blur(80px) saturate(180%);
            border-left: 0.5px solid rgba(255, 255, 255, 0.3);
            box-shadow: -4px 0 24px rgba(0, 0, 0, 0.2);
            display: flex;
            flex-direction: column;
            z-index: 1000;
            transition: transform 0.3s ease;
        }

        .sidebar-header {
            padding: 32px 24px;
            border-bottom: 0.5px solid rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 14px;
            font-size: 22px;
            font-weight: 700;
            color: white;
            letter-spacing: -0.5px;
        }

        .logo img {
            height: 36px;
            width: auto;
            border-radius: 8px;
        }

        .close-btn {
            display: none;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(20px);
            border: 0.5px solid rgba(255, 255, 255, 0.3);
            width: 36px;
            height: 36px;
            border-radius: 18px;
            cursor: pointer;
            font-size: 16px;
            color: white;
            align-items: center;
            justify-content: center;
        }

        .close-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .sidebar-nav {
            flex: 1;
            overflow-y: auto;
            padding: 16px 12px;
        }

        .sidebar-nav ul {
            list-style: none;
        }

        .sidebar-nav > ul > li {
            margin: 4px 8px;
        }

        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px 18px;
            color: white;
            text-decoration: none;
            border-radius: var(--radius-sm);
            font-weight: 500;
            font-size: 15px;
            position: relative;
            transition: all 0.3s ease;
        }

        .sidebar-nav > ul > li.active > a {
            background: rgba(74, 158, 255, 0.25);
            backdrop-filter: blur(20px);
            border: 0.5px solid rgba(74, 158, 255, 0.3);
            box-shadow: 0 4px 12px rgba(74, 158, 255, 0.2);
        }

        .sidebar-nav a:hover {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(20px);
            transform: translateX(-5px);
        }

        .sidebar-nav a i {
            font-size: 20px;
            width: 24px;
            opacity: 0.8;
        }

        .badge {
            background: var(--danger);
            color: white;
            padding: 3px 9px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            margin-right: auto;
            box-shadow: 0 2px 8px rgba(255, 59, 48, 0.3);
        }

        .sidebar-footer {
            padding: 20px 16px;
            border-top: 0.5px solid rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 14px;
            flex: 1;
        }

        .user-profile img {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            border: 2px solid rgba(255, 255, 255, 0.4);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            object-fit: cover;
        }

        .user-info h4 {
            font-size: 15px;
            font-weight: 600;
            color: white;
            margin-bottom: 3px;
            letter-spacing: -0.3px;
        }

        .user-info p {
            font-size: 13px;
            color: rgba(255, 255, 255, 0.7);
        }

        .logout-btn {
            background: rgba(255, 59, 48, 0.15);
            backdrop-filter: blur(20px);
            border: 0.5px solid rgba(255, 59, 48, 0.2);
            width: 44px;
            height: 44px;
            border-radius: 22px;
            cursor: pointer;
            color: var(--danger);
            font-size: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .logout-btn:hover {
            background: rgba(255, 59, 48, 0.25);
        }

        /* ========== MAIN CONTENT ========== */
        .main-content {
            margin-right: var(--sidebar-width);
            min-height: 100vh;
            position: relative;
            z-index: 1;
            padding: 20px;
        }

        /* ========== HEADER ========== */
        .top-header {
            height: 70px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(40px) saturate(180%);
            -webkit-backdrop-filter: blur(40px) saturate(180%);
            border-bottom: 1px solid rgba(255, 255, 255, 0.15);
            display: flex;
            align-items: center;
            padding: 0 24px;
            gap: 20px;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.1);
            border-radius: var(--radius);
            margin-bottom: 24px;
        }

        .menu-toggle {
            display: none;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(20px);
            border: 0.5px solid rgba(255, 255, 255, 0.3);
            width: 40px;
            height: 40px;
            border-radius: var(--radius-sm);
            cursor: pointer;
            font-size: 18px;
            color: white;
            align-items: center;
            justify-content: center;
        }

        .menu-toggle:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .search-box {
            flex: 1;
            max-width: 500px;
            position: relative;
        }

        .search-box i {
            position: absolute;
            right: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255, 255, 255, 0.6);
        }

        .search-box input {
            width: 100%;
            padding: 12px 40px 12px 18px;
            border: 0.5px solid rgba(255, 255, 255, 0.2);
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border-radius: var(--radius-sm);
            font-family: 'Vazirmatn', sans-serif;
            font-size: 14px;
            color: white;
            transition: all 0.3s ease;
        }

        .search-box input:focus {
            outline: none;
            background: rgba(255, 255, 255, 0.15);
            border-color: rgba(74, 158, 255, 0.3);
            box-shadow: 0 0 0 4px rgba(74, 158, 255, 0.1);
        }

        .header-actions {
            display: flex;
            gap: 12px;
            margin-right: auto;
        }

        .header-btn {
            position: relative;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(20px);
            border: 0.5px solid rgba(255, 255, 255, 0.3);
            width: 44px;
            height: 44px;
            border-radius: var(--radius-sm);
            cursor: pointer;
            font-size: 18px;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .notification-dot {
            position: absolute;
            top: 10px;
            left: 10px;
            width: 9px;
            height: 9px;
            background: var(--danger);
            border-radius: 50%;
            border: 2px solid rgba(255, 255, 255, 0.9);
            box-shadow: 0 2px 4px rgba(255, 59, 48, 0.4);
        }

        /* ========== FORM CONTAINER ========== */
        .form-container {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(40px) saturate(180%);
            border-radius: var(--radius);
            padding: 30px;
            border: 1px solid rgba(255, 255, 255, 0.15);
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .form-container h2, .form-container h3 {
            color: white;
            margin-bottom: 25px;
            font-size: 22px;
            font-weight: 700;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 10px;
            color: white;
            font-weight: 600;
            font-size: 15px;
        }

        .form-control {
            width: 100%;
            padding: 14px 20px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: var(--radius-sm);
            background: rgba(255, 255, 255, 0.1);
            color: white;
            font-size: 15px;
            font-family: 'Vazirmatn', sans-serif;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: rgba(74, 158, 255, 0.5);
            background: rgba(255, 255, 255, 0.15);
            box-shadow: 0 0 0 4px rgba(74, 158, 255, 0.1);
        }

        select.form-control option {
            background: #1a1a2e;
            color: white;
        }

        /* ========== BUTTONS ========== */
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: var(--radius-sm);
            cursor: pointer;
            font-weight: 600;
            font-size: 15px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-family: 'Vazirmatn', sans-serif;
        }

        .btn-primary {
            background: linear-gradient(135deg, #4a9eff 0%, #357abd 100%);
            color: white;
            border: 1px solid rgba(74, 158, 255, 0.3);
            box-shadow: 0 4px 15px rgba(74, 158, 255, 0.2);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(74, 158, 255, 0.3);
        }

        .btn-outline {
            background: transparent;
            border: 1px solid #4a9eff;
            color: #4a9eff;
        }

        .btn-outline:hover {
            background: rgba(74, 158, 255, 0.1);
        }

        .btn-success {
            background: linear-gradient(135deg, #34C759 0%, #28a745 100%);
            color: white;
            border: 1px solid rgba(52, 199, 89, 0.3);
        }

        .btn-danger {
            background: linear-gradient(135deg, #FF3B30 0%, #c82333 100%);
            color: white;
            border: 1px solid rgba(255, 59, 48, 0.3);
        }

        /* ========== STATUS BADGES ========== */
        .status-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            display: inline-block;
            text-align: center;
            min-width: 100px;
        }

        .status-new {
            background: rgba(0, 122, 255, 0.2);
            color: #007AFF;
            border: 1px solid rgba(0, 122, 255, 0.3);
        }

        .status-in-progress {
            background: rgba(255, 149, 0, 0.2);
            color: #FF9500;
            border: 1px solid rgba(255, 149, 0, 0.3);
        }

        .status-completed {
            background: rgba(52, 199, 89, 0.2);
            color: #34C759;
            border: 1px solid rgba(52, 199, 89, 0.3);
        }

        .status-cancelled {
            background: rgba(255, 59, 48, 0.2);
            color: #FF3B30;
            border: 1px solid rgba(255, 59, 48, 0.3);
        }

        /* ========== TRANSACTION HEADER ========== */
        .transaction-header {
            background: linear-gradient(135deg, rgba(26, 26, 46, 0.9), rgba(22, 33, 62, 0.9));
            backdrop-filter: blur(40px);
            padding: 30px;
            border-radius: var(--radius);
            margin-bottom: 30px;
            border: 1px solid rgba(74, 158, 255, 0.3);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
        }

        .transaction-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .transaction-title h2 {
            color: white;
            margin: 0;
            font-size: 28px;
            font-weight: 700;
            flex: 1;
        }

        .transaction-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            color: rgba(255, 255, 255, 0.9);
        }

        .transaction-meta div {
            background: rgba(255, 255, 255, 0.05);
            padding: 12px 16px;
            border-radius: var(--radius-sm);
        }

        .transaction-meta strong {
            color: #4a9eff;
            display: block;
            margin-bottom: 5px;
        }

        /* ========== FILE LIST ========== */
        .file-list {
            background: rgba(255, 255, 255, 0.05);
            padding: 20px;
            border-radius: var(--radius-sm);
            margin: 20px 0;
        }

        .file-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            margin: 10px 0;
            background: rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-sm);
            transition: all 0.3s ease;
        }

        .file-item:hover {
            background: rgba(255, 255, 255, 0.15);
        }

        .file-icon {
            font-size: 32px;
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(74, 158, 255, 0.1);
            border-radius: 12px;
        }

        .file-info {
            flex: 1;
        }

        .file-name {
            font-weight: bold;
            color: white;
            margin-bottom: 5px;
        }

        .file-meta {
            font-size: 13px;
            color: rgba(255, 255, 255, 0.7);
        }

        /* ========== REPLY SECTION ========== */
        .reply-section {
            background: rgba(255, 255, 255, 0.05);
            padding: 20px;
            border-radius: var(--radius-sm);
            margin: 20px 0;
            border-right: 4px solid #4a9eff;
        }

        .reply-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .reply-user {
            color: #4a9eff;
            font-weight: 600;
            font-size: 16px;
        }

        .reply-time {
            color: rgba(255, 255, 255, 0.6);
            font-size: 14px;
        }

        .reply-content {
            color: rgba(255, 255, 255, 0.9);
            line-height: 1.6;
            margin-bottom: 15px;
        }

        /* ========== HISTORY ========== */
        .history-item {
            background: rgba(255, 255, 255, 0.05);
            padding: 20px;
            margin: 15px 0;
            border-radius: var(--radius-sm);
            border-right: 3px solid #4a9eff;
        }

        .history-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .history-user {
            color: #4a9eff;
            font-weight: 600;
        }

        .history-time {
            color: rgba(255, 255, 255, 0.6);
            font-size: 14px;
        }

        .history-action {
            color: rgba(255, 255, 255, 0.9);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* ========== VIEWERS ========== */
        .viewers-list {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 10px;
        }

        .viewer-badge {
            background: rgba(74, 158, 255, 0.2);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            border: 1px solid rgba(74, 158, 255, 0.3);
            color: rgba(255, 255, 255, 0.9);
        }

        /* ========== CHAIN ADD BOX ========== */
        .chain-add-box {
            background: rgba(40, 167, 69, 0.1);
            border: 1px solid rgba(40, 167, 69, 0.3);
            padding: 20px;
            border-radius: var(--radius-sm);
            margin-bottom: 20px;
        }

        /* ========== DEADLINE ========== */
        .deadline-critical {
            animation: blink 2s infinite;
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            padding: 10px 20px;
            border-radius: 20px;
            font-weight: bold;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        @keyframes blink {
            0%, 50% { opacity: 1; }
            51%, 100% { opacity: 0.7; }
        }

        /* ========== MESSAGES ========== */
        .success-message {
            background: rgba(40, 167, 69, 0.2);
            border: 1px solid rgba(40, 167, 69, 0.3);
            color: #28a745;
            padding: 15px 20px;
            border-radius: var(--radius-sm);
            margin: 15px 0;
            font-weight: 600;
        }

        .warning-message {
            background: rgba(255, 193, 7, 0.2);
            border: 1px solid rgba(255, 193, 7, 0.3);
            color: #ffc107;
            padding: 15px 20px;
            border-radius: var(--radius-sm);
            margin: 15px 0;
            font-weight: 600;
        }

        /* ========== FOOTER ========== */
        .footer {
            text-align: center;
            padding: 20px;
            margin-top: 40px;
            color: rgba(255, 255, 255, 0.5);
            font-size: 13px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        /* ========== OVERLAY ========== */
        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(10px);
            z-index: 999;
        }

        .overlay.active {
            display: block;
        }

        /* ========== MODAL ========== */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(10px);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
            animation: fadeIn 0.3s ease;
        }

        .modal-content {
            background: linear-gradient(135deg, rgba(26, 26, 46, 0.95), rgba(22, 33, 62, 0.95));
            border-radius: var(--radius);
            padding: 30px;
            border: 1px solid rgba(74, 158, 255, 0.3);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4);
            max-width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            backdrop-filter: blur(40px);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .modal-header h3 {
            color: white;
            margin: 0;
            font-size: 22px;
        }

        .close-modal {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            color: white;
            font-size: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .close-modal:hover {
            background: rgba(255, 59, 48, 0.2);
            border-color: rgba(255, 59, 48, 0.3);
        }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 1200px) {
            .transaction-meta {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            :root {
                --sidebar-width: 280px;
            }

            .sidebar {
                transform: translateX(100%);
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .close-btn {
                display: flex;
            }

            .main-content {
                margin-right: 0;
                padding: 15px;
            }

            .menu-toggle {
                display: flex;
            }

            .top-header {
                padding: 0 20px;
            }

            .transaction-title {
                flex-direction: column;
                align-items: flex-start;
            }

            .transaction-meta {
                grid-template-columns: 1fr;
            }

            .form-container {
                padding: 20px;
            }

            .file-item {
                flex-direction: column;
                text-align: center;
            }

            .file-icon {
                width: 50px;
                height: 50px;
                font-size: 24px;
            }
        }

        @media (max-width: 480px) {
            .header-actions {
                gap: 8px;
            }

            .header-btn {
                width: 40px;
                height: 40px;
                font-size: 16px;
            }

            .search-box {
                display: none;
            }

            .modal-content {
                margin: 15px;
                width: calc(100% - 30px);
                padding: 20px;
            }
        }

        /* ========== ANIMATIONS ========== */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in {
            animation: fadeInUp 0.5s ease;
        }

        /* ========== NOTIFICATION BADGES ========== */
        .notification-badge,
        .urgent-badge {
            background: var(--danger);
            color: white;
            border-radius: 50%;
            padding: 3px 8px;
            font-size: 11px;
            margin-left: 8px;
            position: relative;
            top: -1px;
            display: inline-block;
            min-width: 20px;
            text-align: center;
        }

        .urgent-badge {
            background: var(--warning);
            color: black;
            animation: pulse 1.5s infinite;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <img src="assets/logo/company-logo.png" alt="لوگو شرکت">
                <span>سامانه مودیان</span>
            </div>
            <button class="close-btn" id="closeSidebar">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <nav class="sidebar-nav">
            <ul>
                <li>
                    <a href="dashboard.php">
                        <i class="fas fa-home"></i>
                        <span>داشبورد</span>
                    </a>
                </li>
                
                <li>
                    <a href="invoice-management.php">
                        <i class="fas fa-file-invoice"></i>
                        <span>فاکتورها</span>
                        <?php if ($invoice_notifications['urgent'] > 0): ?>
                            <span class="badge">❗<?php echo $invoice_notifications['urgent']; ?></span>
                        <?php elseif ($invoice_notifications['unread'] > 0): ?>
                            <span class="badge"><?php echo $invoice_notifications['unread']; ?></span>
                        <?php endif; ?>
                    </a>
                </li>

                <li class="active">
                    <a href="tax-system.php">
                        <i class="fas fa-landmark"></i>
                        <span>سامانه مودیان</span>
                        <?php if ($tax_notifications['urgent'] > 0): ?>
                            <span class="badge">❗<?php echo $tax_notifications['urgent']; ?></span>
                        <?php elseif ($tax_notifications['unread'] > 0): ?>
                            <span class="badge"><?php echo $tax_notifications['unread']; ?></span>
                        <?php endif; ?>
                    </a>
                </li>

                <li>
                    <a href="reports.php">
                        <i class="fas fa-chart-bar"></i>
                        <span>گزارشات</span>
                    </a>
                </li>

                <li>
                    <a href="search.php">
                        <i class="fas fa-search"></i>
                        <span>جستجو</span>
                    </a>
                </li>

                <?php if (isAdmin()): ?>
                <li>
                    <a href="admin-panel.php">
                        <i class="fas fa-cog"></i>
                        <span>پنل مدیریت</span>
                    </a>
                </li>
                <?php endif; ?>

                <li>
                    <a href="chat.php">
                        <i class="fas fa-comments"></i>
                        <span>چت</span>
                        <?php if ($chat_notifications > 0): ?>
                            <span class="badge"><?php echo $chat_notifications; ?></span>
                        <?php endif; ?>
                    </a>
                </li>

                <li>
                    <a href="profile.php">
                        <i class="fas fa-user"></i>
                        <span>پروفایل</span>
                    </a>
                </li>
            </ul>
        </nav>

        <div class="sidebar-footer">
            <div class="user-profile">
                <?php if ($sidebar_avatar): ?>
                    <img src="<?php echo $sidebar_avatar; ?>" alt="پروفایل">
                <?php else: ?>
                    <div style="width: 48px; height: 48px; border-radius: 50%; background: linear-gradient(135deg, #4a9eff, #6f42c1); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 18px;">
                        <?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?>
                    </div>
                <?php endif; ?>
                <div class="user-info">
                    <h4><?php echo $_SESSION['username']; ?></h4>
                    <p><?php echo $_SESSION['department']; ?></p>
                </div>
            </div>
            <a href="logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Top Header -->
        <header class="top-header">
            <button class="menu-toggle" id="menuToggle">
                <i class="fas fa-bars"></i>
            </button>
            
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="جستجو در پرونده‌ها...">
            </div>

            <div class="header-actions">
                <button class="header-btn">
                    <i class="fas fa-bell"></i>
                </button>
                <button class="header-btn" onclick="window.location.href='chat.php'">
                    <i class="fas fa-envelope"></i>
                </button>
                <button class="header-btn" onclick="window.location.href='profile.php'">
                    <i class="fas fa-user-circle"></i>
                </button>
            </div>
        </header>

        <!-- Page Content -->
        <div class="page-title" style="margin-bottom: 30px;">
            <h1 style="font-size: 32px; font-weight: 800; color: white; margin-bottom: 10px;">مشاهده پرونده مالیاتی</h1>
            <p style="color: rgba(255, 255, 255, 0.7); font-size: 16px;">جزئیات و پیگیری پرونده</p>
        </div>

        <?php if (isset($_GET['message'])): ?>
            <?php if ($_GET['message'] === 'reply_sent'): ?>
                <div class="success-message">پاسخ شما با موفقیت ارسال شد</div>
            <?php elseif ($_GET['message'] === 'user_added'): ?>
                <div class="success-message">کاربر جدید با موفقیت به زنجیره اضافه شد</div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="transaction-header fade-in">
            <div class="transaction-title">
                <h2><?php echo $transaction['title']; ?></h2>
                <div style="display: flex; gap: 15px; align-items: center;">
                    <?php if ($remaining_days <= 3): ?>
                        <span class="deadline-critical">
                            <i class="fas fa-clock"></i> ⏰ <?php echo $remaining_days; ?> روز باقیمانده
                        </span>
                    <?php else: ?>
                        <span style="color: #51cf66; font-weight: bold;">
                            <i class="fas fa-clock"></i> ⏰ <?php echo $remaining_days; ?> روز باقیمانده
                        </span>
                    <?php endif; ?>
                    
                    <span class="status-badge status-<?php echo $transaction['status']; ?>">
                        <?php 
                        $status_text = [
                            'new' => 'جدید', 
                            'in-progress' => 'در حال بررسی', 
                            'completed' => 'تکمیل شده', 
                            'cancelled' => 'لغو شده'
                        ];
                        echo $status_text[$transaction['status']];
                        ?>
                    </span>
                </div>
            </div>

            <div class="transaction-meta">
                <div>
                    <strong>شرکت:</strong>
                    <span><?php echo $transaction['company']; ?></span>
                </div>
                <div>
                    <strong>ایجاد کننده:</strong>
                    <span><?php echo $creator ? $creator['username'] : 'نامشخص'; ?></span>
                </div>
                <div>
                    <strong>اولویت:</strong>
                    <span class="<?php echo $transaction['priority'] === 'urgent' ? 'status-referred' : 'status-pending'; ?>">
                        <?php echo $transaction['priority'] === 'urgent' ? 'فوری' : 'عادی'; ?>
                    </span>
                </div>
                <div>
                    <strong>تاریخ ایجاد:</strong>
                    <span><?php echo convertToJalali($transaction['created_at']); ?></span>
                </div>
            </div>

            <?php if (!empty($transaction['viewed_by'])): ?>
            <div style="margin-top: 20px;">
                <strong style="color: #4a9eff; display: block; margin-bottom: 10px;">👁️ مشاهده شده توسط:</strong>
                <div class="viewers-list">
                    <?php foreach ($transaction['viewed_by'] as $user_id => $view_time): 
                        $viewer = getUser($user_id);
                        if ($viewer):
                    ?>
                        <span class="viewer-badge">
                            <i class="fas fa-user"></i> <?php echo $viewer['username']; ?> 
                            <span style="color: rgba(255,255,255,0.6);">(<?php echo convertToJalali($view_time); ?>)</span>
                        </span>
                    <?php endif; endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- توضیحات -->
        <div class="form-container fade-in">
            <h3><i class="fas fa-file-alt"></i> توضیحات اولیه</h3>
            <div style="background: rgba(255,255,255,0.05); padding: 20px; border-radius: var(--radius-sm); border-right: 3px solid #4a9eff; line-height: 1.6;">
                <?php echo nl2br(htmlspecialchars($transaction['description'])); ?>
            </div>
        </div>

        <!-- فایل اصلی -->
        <div class="form-container fade-in">
            <h3><i class="fas fa-paperclip"></i> فایل اصلی</h3>
            <div class="file-list">
                <div class="file-item">
                    <div class="file-icon">
                        <?php
                        $file_extension = pathinfo($transaction['main_file']['original_name'], PATHINFO_EXTENSION);
                        $file_icon = '📄';
                        if (in_array(strtolower($file_extension), ['jpg', 'jpeg', 'png', 'gif'])) $file_icon = '🖼️';
                        elseif (strtolower($file_extension) === 'pdf') $file_icon = '📕';
                        elseif (in_array(strtolower($file_extension), ['doc', 'docx'])) $file_icon = '📝';
                        elseif (in_array(strtolower($file_extension), ['xls', 'xlsx'])) $file_icon = '📊';
                        elseif (in_array(strtolower($file_extension), ['zip', 'rar'])) $file_icon = '📦';
                        echo $file_icon;
                        ?>
                    </div>
                    <div class="file-info">
                        <div class="file-name"><?php echo $transaction['main_file']['original_name']; ?></div>
                        <div class="file-meta">فرمت: <?php echo strtoupper($file_extension); ?></div>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <button onclick="previewFile('<?php echo $transaction['main_file']['filename']; ?>', '<?php echo $transaction['main_file']['original_name']; ?>')" 
                                class="btn btn-outline">
                            <i class="fas fa-eye"></i> پیش‌نمایش
                        </button>
                        <a href="download-file.php?type=tax-system&file=<?php echo $transaction['main_file']['filename']; ?>&original_name=<?php echo urlencode($transaction['main_file']['original_name']); ?>" 
                           class="btn btn-primary">
                            <i class="fas fa-download"></i> دانلود
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- تاریخچه -->
        <div class="form-container fade-in">
            <h3><i class="fas fa-history"></i> تاریخچه عملیات</h3>
            <div style="max-height: 400px; overflow-y: auto; padding-right: 10px;">
                <?php foreach (array_reverse($transaction['history']) as $history): 
                    $history_user = getUser($history['user_id']);
                ?>
                <div class="history-item">
                    <div class="history-header">
                        <strong class="history-user"><?php echo $history_user ? $history_user['username'] : 'نامشخص'; ?></strong>
                        <span class="history-time"><?php echo convertToJalali($history['timestamp']); ?></span>
                    </div>
                    <div class="history-action">
                        <?php 
                        $action_icons = [
                            'create' => '📝 ایجاد درخواست',
                            'view' => '👁️ مشاهده فایل',
                            'reply' => '💬 ارسال پاسخ',
                            'status_change' => '🔄 تغییر وضعیت',
                            'refer' => '🔗 ارجاع به کاربر جدید'
                        ];
                        
                        $description = $history['description'];
                        if ($history['action'] === 'status_change') {
                            $description = str_replace(
                                ['new', 'in-progress', 'completed', 'cancelled'],
                                ['جدید', 'در حال بررسی', 'تکمیل شده', 'لغو شده'],
                                $description
                            );
                        }
                        
                        echo ($action_icons[$history['action']] ?? '📌 اقدام') . ' - ' . $description;
                        ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- پاسخ‌ها -->
        <?php if (!empty($transaction['replies'])): ?>
        <div class="form-container fade-in">
            <h3><i class="fas fa-comments"></i> پاسخ‌ها و مکاتبات</h3>
            <?php foreach (array_reverse($transaction['replies']) as $reply): 
                $reply_user = getUser($reply['user_id']);
            ?>
            <div class="reply-section">
                <div class="reply-header">
                    <strong class="reply-user"><?php echo $reply_user ? $reply_user['username'] : 'نامشخص'; ?></strong>
                    <span class="reply-time"><?php echo convertToJalali($reply['timestamp']); ?></span>
                </div>
                
                <div class="reply-content">
                    <?php echo nl2br(htmlspecialchars($reply['message'])); ?>
                </div>

                <?php if (!empty($reply['files'])): ?>
                <div class="file-list">
                    <strong style="color: #4a9eff; display: block; margin-bottom: 10px;">فایل‌های پیوست:</strong>
                    <?php foreach ($reply['files'] as $file): ?>
                    <div class="file-item">
                        <div class="file-icon">📎</div>
                        <div class="file-info">
                            <div class="file-name"><?php echo $file['original_name']; ?></div>
                        </div>
                        <div style="display: flex; gap: 10px;">
                            <button onclick="previewFile('<?php echo $file['filename']; ?>', '<?php echo $file['original_name']; ?>')" 
                                    class="btn btn-outline" style="padding: 8px 12px;">
                                <i class="fas fa-eye"></i>
                            </button>
                            <a href="download-file.php?type=tax-system&file=<?php echo $file['filename']; ?>&original_name=<?php echo urlencode($file['original_name']); ?>" 
                               class="btn btn-primary" style="padding: 8px 12px;">
                                <i class="fas fa-download"></i>
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- افزودن همکار -->
        <?php if ($transaction['status'] !== 'completed' && $transaction['status'] !== 'cancelled'): ?>
        <div class="form-container fade-in chain-add-box">
            <h3><i class="fas fa-user-plus"></i> افزودن همکار به زنجیره</h3>
            <form method="POST">
                <div style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 15px; align-items: end;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>انتخاب کاربر جدید:</label>
                        <select name="new_user_id" class="form-control" required>
                            <option value="">انتخاب کنید...</option>
                            <?php 
                            $existing_users = array_merge([$transaction['created_by']], $transaction['assigned_to']);
                            foreach ($users as $u): 
                                if ($u['id'] != $_SESSION['user_id'] && !in_array($u['id'], $existing_users)):
                            ?>
                                <option value="<?php echo $u['id']; ?>"><?php echo $u['username']; ?> (<?php echo $u['department']; ?>)</option>
                            <?php endif; endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>یادداشت کوتاه (اختیاری):</label>
                        <input type="text" name="referral_note" class="form-control" placeholder="مثلاً: جهت بررسی بخش مالی">
                    </div>
                    <button type="submit" name="add_user_to_chain" class="btn btn-success" style="height: 48px;">
                        <i class="fas fa-plus"></i> افزودن
                    </button>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <!-- ارسال پاسخ -->
        <?php if ($transaction['status'] !== 'completed' && $transaction['status'] !== 'cancelled'): ?>
        <div class="form-container fade-in">
            <h3><i class="fas fa-reply"></i> ارسال پاسخ جدید</h3>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="reply_message">پیام پاسخ *</label>
                    <textarea id="reply_message" name="reply_message" class="form-control" rows="4" required placeholder="پاسخ خود را وارد کنید..."></textarea>
                </div>
                
                <div class="form-group">
                    <label for="reply_files">افزودن فایل‌های پیوست (اختیاری)</label>
                    <input type="file" id="reply_files" name="reply_files[]" class="form-control" multiple accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.xls,.xlsx,.zip,.rar">
                    <small style="color: rgba(255,255,255,0.7); display: block; margin-top: 5px;">
                        می‌توانید چند فایل انتخاب کنید. حداکثر حجم هر فایل: 5MB
                    </small>
                </div>
                
                <button type="submit" name="send_reply" class="btn btn-primary">
                    <i class="fas fa-paper-plane"></i> ارسال پاسخ
                </button>
            </form>
        </div>
        <?php endif; ?>

        <!-- مدیریت وضعیت -->
        <?php if ($_SESSION['user_id'] == $transaction['created_by']): ?>
        <div class="form-container fade-in">
            <h3><i class="fas fa-cog"></i> مدیریت وضعیت (مخصوص ایجاد کننده)</h3>
            <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                <?php if ($transaction['status'] !== 'in-progress'): ?>
                <a href="tax-system-view.php?id=<?php echo $transaction_id; ?>&change_status=in-progress" 
                   class="btn <?php echo $transaction['status'] === 'in-progress' ? 'btn-primary' : 'btn-outline'; ?>"
                   onclick="return confirm('آیا می‌خواهید وضعیت پرونده به \"در حال بررسی\" تغییر کند؟')">
                    <i class="fas fa-sync-alt"></i> در حال بررسی
                </a>
                <?php endif; ?>
                
                <?php if ($transaction['status'] !== 'completed'): ?>
                <a href="tax-system-view.php?id=<?php echo $transaction_id; ?>&change_status=completed" 
                   class="btn <?php echo $transaction['status'] === 'completed' ? 'btn-success' : 'btn-outline'; ?>"
                   onclick="return confirm('آیا از تکمیل و بستن این پرونده اطمینان دارید؟\nپس از بستن، هیچ کاربری نمی‌تواند پاسخ جدید ارسال کند.')">
                    <i class="fas fa-check"></i> تکمیل شده
                </a>
                <?php endif; ?>
                
                <?php if ($transaction['status'] !== 'cancelled'): ?>
                <a href="tax-system-view.php?id=<?php echo $transaction_id; ?>&change_status=cancelled" 
                   class="btn <?php echo $transaction['status'] === 'cancelled' ? 'btn-danger' : 'btn-outline'; ?>"
                   onclick="return confirm('آیا از لغو این پرونده اطمینان دارید؟\nپرونده لغو شده از لیست کاربران حذف خواهد شد.')">
                    <i class="fas fa-times"></i> لغو شده
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </main>

    <!-- Overlay for mobile -->
    <div class="overlay" id="overlay"></div>

    <!-- Modal Preview -->
    <div id="filePreviewModal" class="modal">
        <div class="modal-content" style="max-width: 90%; max-height: 90%;">
            <div class="modal-header">
                <h3>پیش‌نمایش فایل</h3>
                <button class="close-modal" onclick="closeModal('filePreviewModal')">×</button>
            </div>
            <div style="text-align: center; padding: 20px;">
                <div id="filePreviewContent"></div>
                <div style="margin-top: 20px; display: flex; gap: 15px; justify-content: center;">
                    <a id="downloadFile" href="" download class="btn btn-primary">
                        <i class="fas fa-download"></i> دانلود فایل
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <p>سیستم مدیریت مالی - نسخه سامانه مودیان</p>
    </footer>

    <script>
        // Sidebar Toggle
        const menuToggle = document.getElementById('menuToggle');
        const closeSidebar = document.getElementById('closeSidebar');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');

        if (menuToggle) {
            menuToggle.addEventListener('click', () => {
                sidebar.classList.add('active');
                overlay.classList.add('active');
            });
        }

        if (closeSidebar) {
            closeSidebar.addEventListener('click', () => {
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
            });
        }

        if (overlay) {
            overlay.addEventListener('click', () => {
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
            });
        }

        // Handle window resize
        let resizeTimer;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(() => {
                if (window.innerWidth > 768) {
                    sidebar.classList.remove('active');
                    overlay.classList.remove('active');
                }
            }, 250);
        });

        // File Preview Function
        function previewFile(filename, originalName) {
            const fileExtension = filename.split('.').pop().toLowerCase();
            const isImage = ['jpg', 'jpeg', 'png', 'gif'].includes(fileExtension);
            const isPDF = fileExtension === 'pdf';
            
            if (!isImage && !isPDF) {
                alert('این نوع فایل قابل پیش‌نمایش نیست. لطفاً فایل را دانلود کنید.');
                return false;
            }
            
            const fileUrl = 'uploads/tax-system/' + filename;
            const previewContent = document.getElementById('filePreviewContent');
            const downloadLink = document.getElementById('downloadFile');
            
            if (isImage) {
                previewContent.innerHTML = `
                    <img src="${fileUrl}" 
                         style="max-width: 100%; max-height: 70vh; border-radius: 10px; box-shadow: 0 8px 32px rgba(0,0,0,0.3);"
                         onerror="alert('خطا در بارگذاری عکس')"
                         alt="${originalName}">
                `;
            } else if (isPDF) {
                previewContent.innerHTML = `
                    <iframe src="${fileUrl}" 
                            style="width: 100%; height: 70vh; border: none; border-radius: 10px;"
                            frameborder="0">
                    </iframe>
                `;
            }
            
            downloadLink.href = fileUrl;
            downloadLink.download = originalName;
            
            document.getElementById('filePreviewModal').classList.add('active');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
            }
        });

        // Disable forms for closed/cancelled cases
        document.addEventListener('DOMContentLoaded', function() {
            const status = '<?php echo $transaction['status']; ?>';
            
            if (status === 'completed' || status === 'cancelled') {
                const replyForm = document.querySelector('form[action*="send_reply"]');
                if (replyForm) {
                    replyForm.querySelectorAll('input, textarea, button, select').forEach(element => {
                        element.disabled = true;
                    });
                    replyForm.style.opacity = '0.6';
                }
                
                const addUserForm = document.querySelector('form[action*="add_user_to_chain"]');
                if (addUserForm) {
                    addUserForm.querySelectorAll('input, textarea, button, select').forEach(element => {
                        element.disabled = true;
                    });
                    addUserForm.style.opacity = '0.6';
                }
                
                const message = status === 'completed' ? 
                    '🚫 این پرونده تکمیل شده و بسته است. امکان ارسال پاسخ جدید وجود ندارد.' :
                    '🚫 این پرونده لغو شده است. امکان ارسال پاسخ جدید وجود ندارد.';
                
                const statusMessage = document.createElement('div');
                statusMessage.className = 'warning-message';
                statusMessage.innerHTML = message;
                
                const forms = document.querySelectorAll('form');
                if (forms.length > 0) {
                    forms[0].parentNode.insertBefore(statusMessage, forms[0]);
                }
            }
        });
    </script>
</body>
</html>