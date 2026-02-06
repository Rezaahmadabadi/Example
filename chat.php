<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$users = loadData('users');
$chat_messages = loadData('chat-messages');

// API برای دریافت پیام‌ها
if (isset($_GET['get_messages']) && isset($_GET['user_id'])) {
    $user_id = $_GET['user_id'];
    
    // فیلتر پیام‌های بین کاربر جاری و کاربر انتخاب شده
    $filtered_messages = array_filter($chat_messages, function($msg) use ($user_id) {
        return ($msg['from_user_id'] === $user_id && $msg['to_user_id'] === $_SESSION['user_id']) ||
               ($msg['from_user_id'] === $_SESSION['user_id'] && $msg['to_user_id'] === $user_id);
    });
    
    header('Content-Type: application/json');
    echo json_encode(array_values($filtered_messages));
    exit();
}

// ارسال پیام جدید
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $message_text = trim($_POST['message_text']);
    $to_user_id = $_POST['to_user_id'];
    
    if (!empty($message_text)) {
        // آپلود فایل اگر وجود دارد
        $file_path = '';
        if (isset($_FILES['chat_file']) && $_FILES['chat_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['chat_file'];
            $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = uniqid() . '.' . $file_extension;
            $upload_path = UPLOAD_DIR . 'chat-files/' . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                $file_path = $filename;
            }
        }
        
        $new_message = [
            'id' => uniqid(),
            'from_user_id' => $_SESSION['user_id'],
            'to_user_id' => $to_user_id,
            'message_text' => $message_text,
            'file_path' => $file_path,
            'timestamp' => time(),
            'read' => false
        ];
        
        $chat_messages[] = $new_message;
        saveData('chat-messages', $chat_messages);
        
        // ارسال نوتیفیکیشن
        sendNotification(
            $to_user_id,
            "پیام جدید از {$_SESSION['username']}",
            null
        );
        
        // اگر درخواست AJAX است، پاسخ JSON برگردان
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => $new_message]);
            exit();
        }
    }
}

// علامت گذاری پیام‌ها به عنوان خوانده شده
if (isset($_GET['mark_read'])) {
    $user_id = $_GET['user'] ?? '';
    foreach ($chat_messages as &$message) {
        if ($message['to_user_id'] === $_SESSION['user_id'] && $message['from_user_id'] === $user_id && !$message['read']) {
            $message['read'] = true;
        }
    }
    saveData('chat-messages', $chat_messages);
    
    // اگر درخواست AJAX است، پاسخ برگردان
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
        echo json_encode(['success' => true]);
        exit();
    }
}

// فیلتر پیام‌های کاربر جاری
$user_messages = array_filter($chat_messages, function($message) {
    return $message['from_user_id'] === $_SESSION['user_id'] || 
           $message['to_user_id'] === $_SESSION['user_id'];
});

// گروه‌بندی پیام‌ها بر اساس کاربر
$grouped_messages = [];
foreach ($user_messages as $message) {
    $other_user_id = $message['from_user_id'] === $_SESSION['user_id'] ? 
                     $message['to_user_id'] : $message['from_user_id'];
    $grouped_messages[$other_user_id][] = $message;
}

// برای نمایش در هدر
$current_user_header = getUser($_SESSION['user_id']);

// محاسبه اعلان‌ها
$tax_notifications = getUnreadTaxTransactionsCount($_SESSION['user_id']);
$invoice_notifications = getUnreadInvoicesCount($_SESSION['user_id']);
$chat_notifications = getUnreadChatMessagesCount($_SESSION['user_id']);

$avatar_path_header = '';
if (isset($current_user_header['avatar'])) {
    $avatar_path_header = 'uploads/profile-pics/' . $current_user_header['avatar'];
}

// آماده‌سازی اطلاعات کاربر برای سایدبار
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
    <title>چت - سیستم پیگیری فاکتور</title>
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

        .sidebar-nav::-webkit-scrollbar {
            width: 4px;
        }

        .sidebar-nav::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 10px;
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

        .sidebar-nav > ul > li.active > a i {
            opacity: 1;
            color: var(--primary);
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

        .search-box input::placeholder {
            color: rgba(255, 255, 255, 0.5);
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

        .header-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
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

        /* ========== CHAT CONTAINER ========== */
        .chat-container {
            display: flex;
            height: calc(100vh - 140px);
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(40px) saturate(180%);
            border-radius: var(--radius);
            border: 1px solid rgba(255, 255, 255, 0.15);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        /* ========== USERS LIST ========== */
        .users-list {
            width: 300px;
            background: rgba(255, 255, 255, 0.05);
            border-left: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            flex-direction: column;
        }

        .users-list h3 {
            padding: 20px;
            margin: 0;
            color: white;
            font-size: 18px;
            font-weight: 700;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(74, 158, 255, 0.1);
        }

        .users {
            flex: 1;
            overflow-y: auto;
            padding: 10px;
        }

        .user-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 15px;
            margin-bottom: 8px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: var(--radius-sm);
            cursor: pointer;
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .user-item:hover {
            background: rgba(74, 158, 255, 0.15);
            border-color: rgba(74, 158, 255, 0.3);
            transform: translateX(-5px);
        }

        .user-item.active {
            background: rgba(74, 158, 255, 0.2);
            border-color: rgba(74, 158, 255, 0.4);
        }

        .user-avatar-small {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #4a9eff, #6f42c1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 16px;
            overflow: hidden;
        }

        .user-avatar-small img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .user-info {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .username {
            font-weight: 600;
            color: white;
            font-size: 14px;
        }

        .department {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.7);
        }

        .unread-badge {
            background: var(--danger);
            color: white;
            font-size: 11px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 10px;
            min-width: 20px;
            text-align: center;
        }

        /* ========== CHAT AREA ========== */
        .chat-area {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .chat-header {
            padding: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(74, 158, 255, 0.1);
        }

        .chat-header h3 {
            margin: 0;
            color: white;
            font-size: 18px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .chat-header h3 i {
            color: #4a9eff;
        }

        .messages-container {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 15px;
            background: rgba(255, 255, 255, 0.02);
        }

        .no-chat-selected,
        .no-messages {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: rgba(255, 255, 255, 0.5);
            text-align: center;
            gap: 15px;
        }

        .no-chat-selected i,
        .no-messages i {
            font-size: 48px;
            opacity: 0.3;
        }

        .message {
            max-width: 70%;
            display: flex;
            margin-bottom: 10px;
            animation: messageAppear 0.3s ease;
        }

        @keyframes messageAppear {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .message.sent {
            align-self: flex-end;
        }

        .message.received {
            align-self: flex-start;
        }

        .message-content {
            padding: 12px 16px;
            border-radius: var(--radius-sm);
            position: relative;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .message.sent .message-content {
            background: linear-gradient(135deg, #4a9eff, #357abd);
            color: white;
            border-bottom-right-radius: 4px;
        }

        .message.received .message-content {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            border-bottom-left-radius: 4px;
        }

        .message-text {
            font-size: 14px;
            line-height: 1.5;
            margin-bottom: 8px;
            word-wrap: break-word;
        }

        .message-file {
            margin-top: 8px;
            padding: 8px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .file-link {
            color: #4a9eff;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            transition: color 0.3s;
        }

        .file-link:hover {
            color: #2d8bff;
            text-decoration: underline;
        }

        .message-time {
            font-size: 11px;
            opacity: 0.7;
            text-align: left;
            margin-top: 5px;
        }

        .message.sent .message-time {
            color: rgba(255, 255, 255, 0.8);
        }

        .message.received .message-time {
            color: rgba(255, 255, 255, 0.6);
        }

        /* ========== MESSAGE FORM ========== */
        .message-form-container {
            padding: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(255, 255, 255, 0.05);
        }

        .message-input-group {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .message-input {
            flex: 1;
            padding: 12px 20px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            background: rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-sm);
            color: white;
            font-family: 'Vazirmatn', sans-serif;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .message-input:focus {
            outline: none;
            border-color: rgba(74, 158, 255, 0.5);
            background: rgba(255, 255, 255, 0.15);
            box-shadow: 0 0 0 4px rgba(74, 158, 255, 0.1);
        }

        .message-input::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }

        .file-upload-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 50px;
            height: 50px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: var(--radius-sm);
            cursor: pointer;
            font-size: 20px;
            color: white;
            transition: all 0.3s ease;
        }

        .file-upload-btn:hover {
            background: rgba(74, 158, 255, 0.2);
            border-color: rgba(74, 158, 255, 0.3);
            transform: translateY(-2px);
        }

        .send-btn {
            padding: 12px 24px;
            background: linear-gradient(135deg, #4a9eff, #357abd);
            border: none;
            border-radius: var(--radius-sm);
            color: white;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'Vazirmatn', sans-serif;
        }

        .send-btn:hover {
            background: linear-gradient(135deg, #357abd, #2a63a4);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(74, 158, 255, 0.3);
        }

        .file-preview {
            margin-top: 10px;
            padding: 10px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: var(--radius-sm);
            border: 1px solid rgba(255, 255, 255, 0.1);
            font-size: 13px;
            color: rgba(255, 255, 255, 0.7);
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

        /* ========== RESPONSIVE DESIGN ========== */
        @media (max-width: 1200px) {
            .chat-container {
                height: calc(100vh - 120px);
            }
        }

        @media (max-width: 992px) {
            .users-list {
                width: 250px;
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

            .search-box {
                display: none;
            }

            .chat-container {
                flex-direction: column;
                height: calc(100vh - 110px);
            }

            .users-list {
                width: 100%;
                height: 200px;
                border-left: none;
                border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            }

            .users {
                display: flex;
                overflow-x: auto;
                flex-wrap: nowrap;
                padding: 10px;
                gap: 10px;
            }

            .user-item {
                flex-shrink: 0;
                width: 150px;
            }

            .message {
                max-width: 85%;
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

            .users-list {
                height: 180px;
            }

            .user-item {
                width: 130px;
            }

            .message {
                max-width: 90%;
            }

            .message-input-group {
                flex-direction: column;
                gap: 10px;
            }

            .message-input {
                width: 100%;
            }

            .file-upload-btn {
                width: 100%;
                height: 44px;
            }

            .send-btn {
                width: 100%;
                padding: 12px;
            }
        }

        /* ========== CUSTOM SCROLLBAR ========== */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb {
            background: rgba(74, 158, 255, 0.3);
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: rgba(74, 158, 255, 0.5);
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
    </style>
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <img src="assets/logo/company-logo.png" alt="لوگو شرکت">
                <span>سیستم چت</span>
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

                <li>
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

                <li class="active">
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
                <input type="text" placeholder="جستجو در پیام‌ها...">
            </div>

            <div class="header-actions">
                <button class="header-btn">
                    <i class="fas fa-bell"></i>
                </button>
                <button class="header-btn" onclick="window.location.href='profile.php'">
                    <i class="fas fa-user-circle"></i>
                </button>
            </div>
        </header>

        <!-- Chat Content -->
        <div class="page-title" style="margin-bottom: 30px;">
            <h1 style="font-size: 32px; font-weight: 800; color: white; margin-bottom: 10px;">سیستم چت</h1>
            <p style="color: rgba(255, 255, 255, 0.7); font-size: 16px;">ارتباط آنلاین با همکاران - <?php echo $_SESSION['username']; ?></p>
        </div>

        <div class="chat-container fade-in">
            <!-- لیست کاربران -->
            <div class="users-list">
                <h3><i class="fas fa-users"></i> لیست همکاران</h3>
                <div class="users" id="usersList">
                    <?php 
                    $active_users = array_filter($users, function($user) {
                        return $user['id'] !== $_SESSION['user_id'] && $user['is_active'];
                    });
                    
                    if (count($active_users) === 0): ?>
                        <div style="text-align: center; padding: 40px; color: rgba(255,255,255,0.5);">
                            <i class="fas fa-users-slash" style="font-size: 36px; margin-bottom: 15px;"></i>
                            <p>هیچ کاربر فعالی یافت نشد</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($active_users as $user): 
                            $unread_count = count(array_filter($user_messages, function($msg) use ($user) {
                                return $msg['from_user_id'] === $user['id'] && 
                                       $msg['to_user_id'] === $_SESSION['user_id'] && 
                                       !$msg['read'];
                            }));
                            
                            $user_avatar_path = '';
                            if (isset($user['avatar'])) {
                                $user_avatar_path = 'uploads/profile-pics/' . $user['avatar'];
                            }
                        ?>
                        <div class="user-item" onclick="selectUser('<?php echo $user['id']; ?>', '<?php echo $user['username']; ?>')" id="user-<?php echo $user['id']; ?>">
                            <div class="user-avatar-small">
                                <?php if ($user_avatar_path && file_exists($user_avatar_path)): ?>
                                    <img src="<?php echo $user_avatar_path; ?>" alt="<?php echo $user['username']; ?>">
                                <?php else: ?>
                                    <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                <?php endif; ?>
                            </div>
                            <div class="user-info">
                                <span class="username"><?php echo $user['username']; ?></span>
                                <span class="department"><?php echo $user['department']; ?></span>
                            </div>
                            <?php if ($unread_count > 0): ?>
                                <span class="unread-badge" id="unread-<?php echo $user['id']; ?>">
                                    <?php echo $unread_count; ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- area چت -->
            <div class="chat-area">
                <div class="chat-header">
                    <h3 id="current-chat-user">
                        <i class="fas fa-comment-dots"></i> 
                        <span>برای شروع گفتگو یک کاربر را انتخاب کنید</span>
                    </h3>
                </div>
                
                <div class="messages-container" id="messagesContainer">
                    <div class="no-chat-selected">
                        <i class="fas fa-comments"></i>
                        <p style="font-size: 16px; color: rgba(255, 255, 255, 0.7);">هنوز کاربری انتخاب نشده است</p>
                        <p style="font-size: 14px; color: rgba(255, 255, 255, 0.5);">از لیست سمت چپ یک همکار را انتخاب کنید</p>
                    </div>
                </div>

                <!-- فرم ارسال پیام -->
                <div class="message-form-container">
                    <form id="messageForm" method="POST" enctype="multipart/form-data" style="display: none;">
                        <input type="hidden" name="to_user_id" id="to_user_id">
                        <div class="message-input-group">
                            <input type="text" name="message_text" id="messageText" 
                                   placeholder="پیام خود را بنویسید..." class="message-input" required>
                            <label for="chatFile" class="file-upload-btn" title="افزودن فایل">
                                <i class="fas fa-paperclip"></i>
                                <input type="file" id="chatFile" name="chat_file" style="display: none;" 
                                       accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.xls,.xlsx,.zip,.rar">
                            </label>
                            <button type="submit" name="send_message" class="send-btn">
                                <i class="fas fa-paper-plane"></i> ارسال
                            </button>
                        </div>
                        <div id="filePreview" class="file-preview"></div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <!-- Overlay for mobile -->
    <div class="overlay" id="overlay"></div>

    <!-- فوتر -->
    <footer class="footer">
        <p>توسعه دهنده: رضااحمدآبادی (پرسنل بخش مالی)</p>
    </footer>

    <script>
    // Sidebar Toggle for Mobile
    const menuToggle = document.getElementById('menuToggle');
    const closeSidebar = document.getElementById('closeSidebar');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');

    // Open Sidebar
    if (menuToggle) {
        menuToggle.addEventListener('click', () => {
            sidebar.classList.add('active');
            overlay.classList.add('active');
        });
    }

    // Close Sidebar
    if (closeSidebar) {
        closeSidebar.addEventListener('click', () => {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
        });
    }

    // Close Sidebar when clicking overlay
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
            // Close sidebar on desktop view
            if (window.innerWidth > 768) {
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
            }
        }, 250);
    });

    // Chat Functions
    let currentUserId = '';
    let refreshInterval = null;
    let currentActiveUserItem = null;
    
    function selectUser(userId, username) {
        // Remove active class from previous user
        if (currentActiveUserItem) {
            currentActiveUserItem.classList.remove('active');
        }
        
        // Set active class for new user
        currentActiveUserItem = document.getElementById(`user-${userId}`);
        if (currentActiveUserItem) {
            currentActiveUserItem.classList.add('active');
        }
        
        currentUserId = userId;
        document.getElementById('current-chat-user').innerHTML = `
            <i class="fas fa-comment-dots"></i> گفتگو با ${username}
        `;
        document.getElementById('to_user_id').value = userId;
        document.getElementById('messageForm').style.display = 'block';
        
        // لود پیام‌ها
        loadMessages(userId);
        
        // علامت گذاری به عنوان خوانده شده
        markAsRead(userId);
        
        // شروع رفرش اتوماتیک
        startAutoRefresh();
    }
    
    function startAutoRefresh() {
        // پاک کردن interval قبلی
        if (refreshInterval) {
            clearInterval(refreshInterval);
        }
        
        // رفرش هر 3 ثانیه
        refreshInterval = setInterval(() => {
            if (currentUserId) {
                loadMessages(currentUserId);
            }
        }, 3000);
    }
    
    function markAsRead(userId) {
        fetch(`chat.php?mark_read=1&user=${userId}`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // حذف badge خوانده نشده
                const unreadBadge = document.getElementById(`unread-${userId}`);
                if (unreadBadge) {
                    unreadBadge.style.display = 'none';
                }
            }
        })
        .catch(error => {
            console.error('Error marking as read:', error);
        });
    }
    
    function loadMessages(userId) {
        const messagesContainer = document.getElementById('messagesContainer');
        
        fetch(`chat.php?get_messages=1&user_id=${userId}`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(messages => {
            displayMessages(messages);
        })
        .catch(error => {
            console.error('Error loading messages:', error);
        });
    }
    
    function displayMessages(messages) {
        const messagesContainer = document.getElementById('messagesContainer');
        
        if (!messages || messages.length === 0) {
            messagesContainer.innerHTML = `
                <div class="no-messages">
                    <i class="fas fa-envelope-open-text"></i>
                    <p style="font-size: 16px; color: rgba(255, 255, 255, 0.7);">هنوز پیامی مبادله نشده است</p>
                    <p style="font-size: 14px; color: rgba(255, 255, 255, 0.5);">اولین پیام را ارسال کنید</p>
                </div>
            `;
            return;
        }
        
        messages.sort((a, b) => a.timestamp - b.timestamp);
        
        // Group messages by day
        const groupedByDay = {};
        messages.forEach(msg => {
            const date = new Date(msg.timestamp * 1000);
            const dayKey = date.toLocaleDateString('fa-IR');
            
            if (!groupedByDay[dayKey]) {
                groupedByDay[dayKey] = [];
            }
            groupedByDay[dayKey].push(msg);
        });
        
        let messagesHTML = '';
        
        Object.keys(groupedByDay).forEach(dayKey => {
            // Add day separator
            messagesHTML += `
                <div class="day-separator">
                    <span>${dayKey}</span>
                </div>
            `;
            
            // Add messages for this day
            groupedByDay[dayKey].forEach(msg => {
                const isSent = msg.from_user_id === '<?php echo $_SESSION['user_id']; ?>';
                const time = new Date(msg.timestamp * 1000).toLocaleTimeString('fa-IR', {
                    hour: '2-digit',
                    minute: '2-digit'
                });
                
                let fileHTML = '';
                if (msg.file_path) {
                    const fileName = msg.file_path.split('/').pop();
                    fileHTML = `
                        <div class="message-file">
                            <a href="uploads/chat-files/${msg.file_path}" download="${fileName}" class="file-link">
                                <i class="fas fa-paperclip"></i> ${fileName}
                            </a>
                        </div>
                    `;
                }
                
                messagesHTML += `
                    <div class="message ${isSent ? 'sent' : 'received'}">
                        <div class="message-content">
                            <div class="message-text">${msg.message_text}</div>
                            ${fileHTML}
                            <div class="message-time">
                                ${time}
                                ${!isSent && !msg.read ? '<span class="unread-indicator">●</span>' : ''}
                            </div>
                        </div>
                    </div>
                `;
            });
        });
        
        messagesContainer.innerHTML = messagesHTML;
        
        // Scroll to bottom
        setTimeout(() => {
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }, 100);
    }
    
    // ارسال پیام با AJAX
    document.getElementById('messageForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const messageText = document.getElementById('messageText').value.trim();
        const toUserId = document.getElementById('to_user_id').value;
        const chatFile = document.getElementById('chatFile').files[0];
        
        if (!messageText && !chatFile) {
            alert('لطفا پیام یا فایل وارد کنید');
            return;
        }
        
        const formData = new FormData();
        formData.append('send_message', '1');
        formData.append('to_user_id', toUserId);
        formData.append('message_text', messageText);
        if (chatFile) {
            formData.append('chat_file', chatFile);
        }
        
        fetch('chat.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                // پاک کردن فیلدها
                document.getElementById('messageText').value = '';
                document.getElementById('chatFile').value = '';
                document.getElementById('filePreview').innerHTML = '';
                
                // رفرش پیام‌ها
                loadMessages(currentUserId);
                
                // Focus back to input
                document.getElementById('messageText').focus();
            }
        })
        .catch(error => {
            console.error('Error sending message:', error);
            alert('خطا در ارسال پیام');
        });
    });
    
    // پیش‌نمایش فایل
    document.getElementById('chatFile').addEventListener('change', function(e) {
        const preview = document.getElementById('filePreview');
        if (this.files.length > 0) {
            const file = this.files[0];
            const fileSize = (file.size / 1024 / 1024).toFixed(2);
            preview.innerHTML = `
                <div style="display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-file" style="color: #4a9eff;"></i>
                    <div>
                        <div style="color: white; font-weight: 500;">${file.name}</div>
                        <div style="font-size: 12px;">${fileSize} MB</div>
                    </div>
                </div>
            `;
        } else {
            preview.innerHTML = '';
        }
    });
    
    // Enable sending message with Enter key (Ctrl+Enter for new line)
    document.getElementById('messageText').addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey && !e.ctrlKey) {
            e.preventDefault();
            document.getElementById('messageForm').dispatchEvent(new Event('submit'));
        }
    });
    
    // Add day separator styles
    const style = document.createElement('style');
    style.textContent = `
        .day-separator {
            text-align: center;
            margin: 20px 0;
            position: relative;
        }
        
        .day-separator span {
            background: rgba(74, 158, 255, 0.2);
            color: rgba(255, 255, 255, 0.7);
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            border: 1px solid rgba(74, 158, 255, 0.3);
        }
        
        .day-separator::before,
        .day-separator::after {
            content: '';
            position: absolute;
            top: 50%;
            width: 30%;
            height: 1px;
            background: rgba(255, 255, 255, 0.1);
        }
        
        .day-separator::before {
            right: 0;
        }
        
        .day-separator::after {
            left: 0;
        }
        
        .unread-indicator {
            color: #4a9eff;
            font-size: 10px;
            margin-right: 5px;
        }
    `;
    document.head.appendChild(style);
    
    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        console.log('💬 سیستم چت بارگذاری شد');
    });
    </script>
</body>
</html>
[file content end]