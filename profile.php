<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$message = '';

// تغییر رمز عبور
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if ($new_password !== $confirm_password) {
        $message = '<div class="error-highlight">⚠️ رمز عبور جدید و تکرار آن مطابقت ندارند</div>';
    } elseif (strlen($new_password) < 6) {
        $message = '<div class="error-highlight">⚠️ رمز عبور باید حداقل 6 کاراکتر باشد</div>';
    } else {
        if (changePassword($_SESSION['user_id'], $current_password, $new_password)) {
            $message = '<div class="success-highlight">✅ رمز عبور با موفقیت تغییر یافت</div>';
        } else {
            $message = '<div class="error-highlight">⚠️ رمز عبور فعلی اشتباه است</div>';
        }
    }
}

// آپلود عکس پروفایل
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_avatar'])) {
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['avatar'];
        
        // بررسی نوع فایل
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($file['type'], $allowed_types)) {
            $message = '<div class="error-highlight">⚠️ فقط فایل‌های تصویری مجاز هستند</div>';
        } else {
            $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = $_SESSION['user_id'] . '.' . $file_extension;
            $upload_path = UPLOAD_DIR . 'profile-pics/' . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                // ذخیره مسیر عکس در اطلاعات کاربر
                $users = loadData('users');
                foreach ($users as &$user) {
                    if ($user['id'] === $_SESSION['user_id']) {
                        $user['avatar'] = $filename;
                        break;
                    }
                }
                saveData('users', $users);
                $message = '<div class="success-highlight">✅ عکس پروفایل با موفقیت آپلود شد</div>';
            } else {
                $message = '<div class="error-highlight">⚠️ خطا در آپلود عکس</div>';
            }
        }
    }
}

$current_user = getUser($_SESSION['user_id']);
$avatar_path = '';
if (isset($current_user['avatar'])) {
    $avatar_path = 'uploads/profile-pics/' . $current_user['avatar'];
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
    <title>پروفایل - سیستم پیگیری فاکتور</title>
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

        /* ========== DASHBOARD CARDS ========== */
        .dashboard-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(40px) saturate(180%);
            -webkit-backdrop-filter: blur(40px) saturate(180%);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: var(--radius);
            padding: 24px;
            display: flex;
            gap: 20px;
            align-items: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
            transform: translateY(-5px);
            background: rgba(255, 255, 255, 0.15);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .stat-icon.blue {
            background: linear-gradient(135deg, #007AFF 0%, #5856D6 100%);
        }

        .stat-icon.green {
            background: linear-gradient(135deg, #34C759 0%, #30D158 100%);
        }

        .stat-icon.orange {
            background: linear-gradient(135deg, #FF9500 0%, #FF6B35 100%);
        }

        .stat-icon.purple {
            background: linear-gradient(135deg, #5856D6 0%, #AF52DE 100%);
        }

        .stat-icon.red {
            background: linear-gradient(135deg, #FF3B30 0%, #C82333 100%);
        }

        .stat-info {
            flex: 1;
        }

        .stat-info h3 {
            font-size: 28px;
            font-weight: 700;
            color: white;
            margin-bottom: 5px;
            letter-spacing: -0.5px;
        }

        .stat-info p {
            color: rgba(255, 255, 255, 0.8);
            font-size: 14px;
            margin-bottom: 10px;
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

        .form-container h2 {
            color: white;
            font-size: 22px;
            margin-bottom: 20px;
            font-weight: 700;
        }

        .form-row {
            display: flex;
            gap: 20px;
            align-items: flex-end;
        }

        .form-group {
            margin-bottom: 20px;
            flex: 1;
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

        /* ========== BUTTON STYLES ========== */
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

        .btn-danger {
            background: linear-gradient(135deg, #FF3B30 0%, #c82333 100%);
            color: white;
            border: 1px solid rgba(255, 59, 48, 0.3);
        }

        .btn-danger:hover {
            background: linear-gradient(135deg, #c82333 0%, #a71e2a 100%);
            transform: translateY(-2px);
        }

        .btn-success {
            background: linear-gradient(135deg, #34C759 0%, #28a745 100%);
            color: white;
            border: 1px solid rgba(52, 199, 89, 0.3);
        }

        .btn-success:hover {
            background: linear-gradient(135deg, #28a745 0%, #218838 100%);
            transform: translateY(-2px);
        }

        .btn-warning {
            background: linear-gradient(135deg, #FF9500 0%, #fd7e14 100%);
            color: white;
            border: 1px solid rgba(255, 149, 0, 0.3);
        }

        .btn-warning:hover {
            background: linear-gradient(135deg, #fd7e14 0%, #e66a00 100%);
            transform: translateY(-2px);
        }

        .btn-outline {
            background: transparent;
            border: 1px solid #4a9eff;
            color: #4a9eff;
        }

        .btn-outline:hover {
            background: rgba(74, 158, 255, 0.1);
        }

        .btn-small {
            padding: 8px 16px;
            font-size: 13px;
        }

        .btn-group {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        /* ========== MESSAGES ========== */
        .error-highlight {
            background: linear-gradient(135deg, rgba(255, 59, 48, 0.2), rgba(220, 53, 69, 0.2));
            color: #ff6b6b;
            padding: 15px 20px;
            border-radius: var(--radius-sm);
            border: 1px solid rgba(255, 59, 48, 0.3);
            margin: 15px 0;
            font-weight: 600;
            text-align: center;
        }

        .success-highlight {
            background: linear-gradient(135deg, rgba(52, 199, 89, 0.2), rgba(40, 167, 69, 0.2));
            color: #51cf66;
            padding: 15px 20px;
            border-radius: var(--radius-sm);
            border: 1px solid rgba(52, 199, 89, 0.3);
            margin: 15px 0;
            font-weight: 600;
            text-align: center;
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
            .content-grid {
                grid-template-columns: 1fr;
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
                max-width: none;
            }

            .dashboard-cards {
                grid-template-columns: 1fr;
            }

            .stat-card {
                flex-direction: column;
                text-align: center;
            }

            .stat-icon {
                width: 50px;
                height: 50px;
                font-size: 20px;
            }

            .btn {
                padding: 10px 18px;
                font-size: 14px;
            }

            .form-row {
                flex-direction: column;
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

            .card-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }

            .btn-group {
                flex-direction: column;
                width: 100%;
            }

            .btn-group .btn {
                width: 100%;
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

        /* ========== PROFILE STYLES ========== */
        .profile-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 30px;
            padding: 25px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(40px) saturate(180%);
            border-radius: var(--radius);
            border: 1px solid rgba(255, 255, 255, 0.15);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 4px solid rgba(74, 158, 255, 0.3);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            object-fit: cover;
            transition: all 0.3s ease;
        }

        .profile-avatar:hover {
            transform: scale(1.05);
            border-color: rgba(74, 158, 255, 0.5);
            box-shadow: 0 6px 20px rgba(74, 158, 255, 0.3);
        }

        .profile-info {
            flex: 1;
        }

        .profile-info h1 {
            color: white;
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 10px;
            letter-spacing: -0.5px;
        }

        .profile-info p {
            color: rgba(255, 255, 255, 0.7);
            font-size: 16px;
            margin-bottom: 5px;
        }

        .profile-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .profile-stat-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(40px) saturate(180%);
            border-radius: var(--radius);
            padding: 20px;
            border: 1px solid rgba(255, 255, 255, 0.15);
            text-align: center;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .profile-stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
            background: rgba(255, 255, 255, 0.15);
        }

        .profile-stat-card h3 {
            color: rgba(255, 255, 255, 0.7);
            font-size: 14px;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .profile-stat-card .number {
            color: white;
            font-size: 32px;
            font-weight: 800;
            letter-spacing: -0.5px;
        }

        .profile-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        @media (max-width: 768px) {
            .profile-grid {
                grid-template-columns: 1fr;
            }
        }

        .user-info-display {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
        }

        .info-item:hover {
            background: rgba(74, 158, 255, 0.05);
            border-color: rgba(74, 158, 255, 0.2);
            transform: translateX(-5px);
        }

        .info-label {
            color: white;
            font-weight: 600;
            font-size: 14px;
        }

        .info-value {
            color: rgba(255, 255, 255, 0.8);
            font-size: 14px;
        }

        .status-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            display: inline-block;
            text-align: center;
            min-width: 80px;
        }

        .status-pending {
            background: rgba(255, 149, 0, 0.2);
            color: #FF9500;
            border: 1px solid rgba(255, 149, 0, 0.3);
        }

        .status-completed {
            background: rgba(52, 199, 89, 0.2);
            color: #34C759;
            border: 1px solid rgba(52, 199, 89, 0.3);
        }

        .avatar-upload {
            text-align: center;
        }

        .avatar-preview {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            border: 3px solid rgba(74, 158, 255, 0.3);
            margin: 0 auto 20px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.05);
            transition: all 0.3s ease;
        }

        .avatar-preview:hover {
            border-color: rgba(74, 158, 255, 0.5);
            transform: scale(1.05);
        }

        .avatar-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .avatar-preview i {
            font-size: 48px;
            color: rgba(255, 255, 255, 0.3);
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <img src="assets/logo/company-logo.png" alt="لوگو شرکت">
                <span>پروفایل</span>
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

                <li>
                    <a href="chat.php">
                        <i class="fas fa-comments"></i>
                        <span>چت</span>
                        <?php if ($chat_notifications > 0): ?>
                            <span class="badge"><?php echo $chat_notifications; ?></span>
                        <?php endif; ?>
                    </a>
                </li>

                <li class="active">
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
                <input type="text" placeholder="جستجو...">
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

        <!-- Profile Content -->
        <div class="dashboard-content">
            <!-- Page Title -->
            <div class="page-title" style="margin-bottom: 30px;">
                <h1 style="font-size: 32px; font-weight: 800; color: white; margin-bottom: 10px;">پروفایل کاربری</h1>
                <p style="color: rgba(255, 255, 255, 0.7); font-size: 16px;">مدیریت اطلاعات شخصی و امنیت حساب - <?php echo $_SESSION['username']; ?></p>
            </div>

            <!-- Messages -->
            <?php echo $message; ?>

            <!-- Profile Header -->
            <div class="profile-header fade-in">
                <div class="avatar-preview">
                    <?php if ($avatar_path && file_exists($avatar_path)): ?>
                        <img src="<?php echo $avatar_path; ?>" alt="عکس پروفایل" class="profile-avatar">
                    <?php else: ?>
                        <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #4a9eff, #6f42c1); color: white; font-size: 48px; font-weight: bold;">
                            <?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="profile-info">
                    <h1><?php echo $_SESSION['username']; ?></h1>
                    <p><i class="fas fa-building"></i> <?php echo $_SESSION['department']; ?></p>
                    <p><i class="fas fa-calendar-alt"></i> عضویت از <?php echo convertToJalali($current_user['created_at']); ?></p>
                    <div style="display: flex; gap: 10px; margin-top: 15px;">
                        <span class="status-badge <?php echo $current_user['role'] === 'admin' ? 'status-completed' : 'status-pending'; ?>">
                            <i class="fas fa-crown"></i> <?php echo $current_user['role'] === 'admin' ? 'ادمین سیستم' : 'کاربر عادی'; ?>
                        </span>
                        <span class="status-badge <?php echo (isAdmin() || (isset($current_user['can_create_invoice']) && $current_user['can_create_invoice'])) ? 'status-completed' : 'status-pending'; ?>">
                            <i class="fas fa-file-invoice"></i> <?php echo (isAdmin() || (isset($current_user['can_create_invoice']) && $current_user['can_create_invoice'])) ? 'مجوز ثبت فاکتور' : 'بدون مجوز ثبت'; ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Stats Cards -->
            <?php
            $invoices = loadData('invoices');
            $user_invoices = array_filter($invoices, function($invoice) {
                return $invoice['created_by'] === $_SESSION['user_id'];
            });
            $received_invoices = array_filter($invoices, function($invoice) {
                return in_array($_SESSION['user_id'], array_column($invoice['tracking_history'], 'user_id'));
            });
            $completed_user_invoices = array_filter($user_invoices, function($invoice) {
                return $invoice['status'] === 'completed';
            });
            ?>

            <div class="profile-stats fade-in">
                <div class="profile-stat-card">
                    <h3><i class="fas fa-plus-circle"></i> فاکتورهای ایجاد شده</h3>
                    <div class="number" style="color: #4a9eff;"><?php echo count($user_invoices); ?></div>
                </div>
                <div class="profile-stat-card">
                    <h3><i class="fas fa-exchange-alt"></i> فاکتورهای پیگیری شده</h3>
                    <div class="number" style="color: #ffc107;"><?php echo count($received_invoices); ?></div>
                </div>
                <div class="profile-stat-card">
                    <h3><i class="fas fa-check-double"></i> فاکتورهای تکمیل شده</h3>
                    <div class="number" style="color: #34C759;"><?php echo count($completed_user_invoices); ?></div>
                </div>
                <div class="profile-stat-card">
                    <h3><i class="fas fa-history"></i> مدت عضویت</h3>
                    <div class="number" style="color: #6f42c1; font-size: 20px;">
                        <?php 
                        $days_diff = floor((time() - $current_user['created_at']) / (60 * 60 * 24));
                        echo $days_diff . ' روز';
                        ?>
                    </div>
                </div>
            </div>

            <!-- Profile Grid -->
            <div class="profile-grid">
                <!-- اطلاعات کاربری -->
                <div class="form-container fade-in">
                    <h2><i class="fas fa-user-circle"></i> اطلاعات کاربری</h2>
                    <div class="user-info-display">
                        <div class="info-item">
                            <span class="info-label">نام کاربری:</span>
                            <span class="info-value"><?php echo $current_user['username']; ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">بخش سازمانی:</span>
                            <span class="info-value"><?php echo $current_user['department']; ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">نقش کاربری:</span>
                            <span class="info-value">
                                <span class="status-badge <?php echo $current_user['role'] === 'admin' ? 'status-completed' : 'status-pending'; ?>">
                                    <?php echo $current_user['role'] === 'admin' ? 'ادمین' : 'کاربر'; ?>
                                </span>
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">تاریخ عضویت:</span>
                            <span class="info-value"><?php echo convertToJalali($current_user['created_at']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">مجوز ثبت فاکتور:</span>
                            <span class="info-value">
                                <span class="status-badge <?php echo (isAdmin() || (isset($current_user['can_create_invoice']) && $current_user['can_create_invoice'])) ? 'status-completed' : 'status-pending'; ?>">
                                    <?php echo (isAdmin() || (isset($current_user['can_create_invoice']) && $current_user['can_create_invoice'])) ? 'فعال ✓' : 'غیرفعال ✗'; ?>
                                </span>
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">مجوز دریافت ارجاع:</span>
                            <span class="info-value">
                                <span class="status-badge <?php echo (!isset($current_user['can_receive_referral']) || $current_user['can_receive_referral']) ? 'status-completed' : 'status-pending'; ?>">
                                    <?php echo (!isset($current_user['can_receive_referral']) || $current_user['can_receive_referral']) ? 'فعال ✓' : 'غیرفعال ✗'; ?>
                                </span>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- آپلود عکس پروفایل -->
                <div class="form-container fade-in">
                    <h2><i class="fas fa-camera"></i> تغییر عکس پروفایل</h2>
                    <form method="POST" enctype="multipart/form-data" class="avatar-upload">
                        <div class="form-group">
                            <label for="avatar">انتخاب تصویر جدید</label>
                            <input type="file" id="avatar" name="avatar" class="form-control" accept="image/*" onchange="previewImage(this, 'avatarPreview')">
                            <small style="color: rgba(255,255,255,0.7); display: block; margin-top: 5px;">
                                فرمت‌های مجاز: JPG, PNG, GIF (حداکثر 5MB)
                            </small>
                        </div>
                        
                        <div id="avatarPreview" class="avatar-preview" style="margin-bottom: 20px;">
                            <?php if ($avatar_path && file_exists($avatar_path)): ?>
                                <img src="<?php echo $avatar_path; ?>" alt="عکس پروفایل فعلی">
                            <?php else: ?>
                                <i class="fas fa-user"></i>
                            <?php endif; ?>
                        </div>
                        
                        <button type="submit" name="upload_avatar" class="btn btn-primary" style="width: 100%;">
                            <i class="fas fa-upload"></i> آپلود و ذخیره عکس
                        </button>
                    </form>
                </div>
            </div>

            <!-- تغییر رمز عبور -->
            <div class="form-container fade-in">
                <h2><i class="fas fa-key"></i> تغییر رمز عبور</h2>
                <form method="POST">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                        <div class="form-group">
                            <label for="current_password">رمز عبور فعلی *</label>
                            <input type="password" id="current_password" name="current_password" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="new_password">رمز عبور جدید *</label>
                            <input type="password" id="new_password" name="new_password" class="form-control" required minlength="6">
                            <small style="color: rgba(255,255,255,0.7); display: block; margin-top: 5px;">
                                رمز عبور باید حداقل 6 کاراکتر باشد
                            </small>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">تکرار رمز عبور جدید *</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" required minlength="6">
                    </div>
                    <button type="submit" name="change_password" class="btn btn-primary">
                        <i class="fas fa-sync-alt"></i> تغییر رمز عبور
                    </button>
                </form>
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

        // Preview image function
        function previewImage(input, previewId) {
            const preview = document.getElementById(previewId);
            const file = input.files[0];
            
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.innerHTML = `<img src="${e.target.result}" style="width: 100%; height: 100%; object-fit: cover;">`;
                };
                reader.readAsDataURL(file);
            } else {
                // Reset to default
                preview.innerHTML = '<i class="fas fa-user"></i>';
            }
        }

        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            const changePasswordForm = document.querySelector('form[method="POST"]');
            if (changePasswordForm && changePasswordForm.querySelector('[name="change_password"]')) {
                changePasswordForm.addEventListener('submit', function(e) {
                    const newPassword = document.getElementById('new_password').value;
                    const confirmPassword = document.getElementById('confirm_password').value;
                    
                    if (newPassword !== confirmPassword) {
                        e.preventDefault();
                        alert('رمز عبور جدید و تکرار آن مطابقت ندارند.');
                        return false;
                    }
                    
                    if (newPassword.length < 6) {
                        e.preventDefault();
                        alert('رمز عبور باید حداقل 6 کاراکتر باشد.');
                        return false;
                    }
                });
            }
        });
    </script>
</body>
</html>
