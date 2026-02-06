<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// بارگذاری داده‌ها
$users = loadData('users');
$invoices = loadData('invoices');
$companies = loadData('companies');
$notifications = loadData('notifications');

// فیلتر نوتیفیکیشن‌های کاربر جاری
$user_notifications = array_filter($notifications, function($notification) {
    return $notification['user_id'] === $_SESSION['user_id'] && !$notification['read'];
});

// آمارهای داشبورد
$total_invoices = count($invoices);
$pending_invoices = count(array_filter($invoices, function($invoice) {
    return $invoice['status'] === 'pending';
}));
$my_invoices = count(array_filter($invoices, function($invoice) {
    return $invoice['current_user_id'] === $_SESSION['user_id'];
}));
$completed_invoices = count(array_filter($invoices, function($invoice) {
    return $invoice['status'] === 'completed';
}));

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
    <title>داشبورد - سیستم پیگیری فاکتور</title>
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

        .stat-change {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 12px;
            font-weight: 600;
            padding: 5px 12px;
            border-radius: 10px;
        }

        .stat-change.positive {
            background: rgba(52, 199, 89, 0.2);
            color: var(--success);
        }

        .stat-change.negative {
            background: rgba(255, 59, 48, 0.2);
            color: var(--danger);
        }

        /* ========== CONTENT GRID ========== */
        .content-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(380px, 1fr));
            gap: 20px;
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(40px) saturate(180%);
            -webkit-backdrop-filter: blur(40px) saturate(180%);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: var(--radius);
            padding: 24px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .glass-card:hover {
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
            background: rgba(255, 255, 255, 0.12);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .card-header h2 {
            font-size: 20px;
            font-weight: 700;
            color: white;
            letter-spacing: -0.3px;
        }

        .btn-secondary {
            background: rgba(74, 158, 255, 0.15);
            backdrop-filter: blur(20px);
            color: #4a9eff;
            border: 0.5px solid rgba(74, 158, 255, 0.3);
            padding: 10px 18px;
            border-radius: var(--radius-sm);
            font-family: 'Vazirmatn', sans-serif;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-secondary:hover {
            background: rgba(74, 158, 255, 0.25);
            transform: translateY(-2px);
        }

        /* ========== TABLE STYLES ========== */
        .table-container {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(40px) saturate(180%);
            border-radius: var(--radius);
            padding: 24px;
            border: 1px solid rgba(255, 255, 255, 0.15);
            margin-bottom: 24px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .table-header h2 {
            font-size: 22px;
            font-weight: 700;
            color: white;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            overflow: hidden;
        }

        th, td {
            padding: 16px 20px;
            text-align: right;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            color: rgba(255, 255, 255, 0.9);
        }

        th {
            background: rgba(74, 158, 255, 0.15);
            font-weight: 600;
            color: white;
            font-size: 14px;
        }

        tr:hover {
            background: rgba(255, 255, 255, 0.08);
        }

        .status-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            display: inline-block;
            text-align: center;
            min-width: 100px;
        }

        .status-pending {
            background: rgba(255, 149, 0, 0.2);
            color: #FF9500;
            border: 1px solid rgba(255, 149, 0, 0.3);
        }

        .status-in-progress {
            background: rgba(74, 158, 255, 0.2);
            color: #4a9eff;
            border: 1px solid rgba(74, 158, 255, 0.3);
        }

        .status-completed {
            background: rgba(52, 199, 89, 0.2);
            color: #34C759;
            border: 1px solid rgba(52, 199, 89, 0.3);
        }

        .status-referred {
            background: rgba(111, 66, 193, 0.2);
            color: #6f42c1;
            border: 1px solid rgba(111, 66, 193, 0.3);
        }

        /* ========== MODAL STYLES ========== */
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

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-content {
            background: linear-gradient(135deg, rgba(26, 26, 46, 0.95), rgba(22, 33, 62, 0.95));
            border-radius: var(--radius);
            padding: 30px;
            border: 1px solid rgba(74, 158, 255, 0.3);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4);
            max-width: 500px;
            width: 90%;
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

        /* ========== FORM STYLES ========== */
        .form-container {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(40px) saturate(180%);
            border-radius: var(--radius);
            padding: 30px;
            border: 1px solid rgba(255, 255, 255, 0.15);
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
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

        .btn-outline {
            background: transparent;
            border: 1px solid #4a9eff;
            color: #4a9eff;
        }

        .btn-outline:hover {
            background: rgba(74, 158, 255, 0.1);
        }

        .btn-danger {
            background: linear-gradient(135deg, #FF3B30 0%, #c82333 100%);
            color: white;
            border: 1px solid rgba(255, 59, 48, 0.3);
        }

        .btn-success {
            background: linear-gradient(135deg, #34C759 0%, #28a745 100%);
            color: white;
            border: 1px solid rgba(52, 199, 89, 0.3);
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

            .table-container {
                overflow-x: auto;
            }

            table {
                font-size: 13px;
            }

            th, td {
                padding: 12px 15px;
            }

            .form-container,
            .table-container,
            .glass-card {
                padding: 20px;
            }

            .btn {
                padding: 10px 18px;
                font-size: 14px;
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

            .modal-content {
                margin: 15px;
                width: calc(100% - 30px);
                padding: 20px;
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

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
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

        /* ========== UTILITY CLASSES ========== */
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .mt-1 { margin-top: 10px; }
        .mt-2 { margin-top: 20px; }
        .mt-3 { margin-top: 30px; }
        .mb-1 { margin-bottom: 10px; }
        .mb-2 { margin-bottom: 20px; }
        .mb-3 { margin-bottom: 30px; }
        .p-1 { padding: 10px; }
        .p-2 { padding: 20px; }
        .p-3 { padding: 30px; }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <img src="assets/logo/company-logo.png" alt="لوگو شرکت">
                <span>سیستم پیگیری فاکتور</span>
            </div>
            <button class="close-btn" id="closeSidebar">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <nav class="sidebar-nav">
            <ul>
                <li class="active">
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
                <input type="text" placeholder="جستجو در فاکتورها...">
            </div>

            <div class="header-actions">
                <button class="header-btn">
                    <i class="fas fa-bell"></i>
                    <?php if (count($user_notifications) > 0): ?>
                        <span class="notification-dot"></span>
                    <?php endif; ?>
                </button>
                <button class="header-btn" onclick="window.location.href='chat.php'">
                    <i class="fas fa-envelope"></i>
                </button>
                <button class="header-btn" onclick="window.location.href='profile.php'">
                    <i class="fas fa-user-circle"></i>
                </button>
            </div>
        </header>

        <!-- Dashboard Content -->
        <div class="dashboard-content">
            <div class="page-title" style="margin-bottom: 30px;">
                <h1 style="font-size: 32px; font-weight: 800; color: white; margin-bottom: 10px;">داشبورد</h1>
                <p style="color: rgba(255, 255, 255, 0.7); font-size: 16px;">خوش آمدید <?php echo $_SESSION['username']; ?>! آخرین آمار سیستم</p>
            </div>

            <!-- Stats Cards -->
            <div class="dashboard-cards">
                <div class="stat-card">
                    <div class="stat-icon blue">
                        <i class="fas fa-file-invoice"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $total_invoices; ?></h3>
                        <p>کل فاکتورها</p>
                        <span class="stat-change positive">
                            <i class="fas fa-arrow-up"></i> ۱۲٪
                        </span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon orange">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $pending_invoices; ?></h3>
                        <p>در انتظار</p>
                        <span class="stat-change negative">
                            <i class="fas fa-arrow-down"></i> ۳٪
                        </span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon green">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $my_invoices; ?></h3>
                        <p>فاکتورهای من</p>
                        <span class="stat-change positive">
                            <i class="fas fa-arrow-up"></i> ۸٪
                        </span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon purple">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $completed_invoices; ?></h3>
                        <p>تکمیل شده</p>
                        <span class="stat-change positive">
                            <i class="fas fa-arrow-up"></i> ۱۵٪
                        </span>
                    </div>
                </div>
            </div>

            <!-- Recent Invoices -->
            <div class="glass-card fade-in">
                <div class="card-header">
                    <h2>فاکتورهای اخیر</h2>
                    <a href="invoice-management.php" class="btn-secondary">مشاهده همه</a>
                </div>
                <div class="table-container" style="background: transparent; padding: 0; border: none; box-shadow: none;">
                    <table>
                        <thead>
                            <tr>
                                <th>شماره فاکتور</th>
                                <th>نام شرکت</th>
                                <th>فروشگاه</th>
                                <th>مبلغ</th>
                                <th>تاریخ</th>
                                <th>وضعیت</th>
                                <th>کاربر فعلی</th>
                                <th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $user_invoices = array_filter($invoices, function($invoice) {
                                return $invoice['created_by'] === $_SESSION['user_id'] || 
                                       $invoice['current_user_id'] === $_SESSION['user_id'] ||
                                       in_array($_SESSION['user_id'], array_column($invoice['tracking_history'], 'user_id'));
                            });

                            $recent_invoices = array_slice($user_invoices, -5, 5, true);
                            foreach (array_reverse($recent_invoices) as $invoice): 
                                $current_user = getUser($invoice['current_user_id']);
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo $invoice['invoice_number']; ?></strong>
                                    <?php 
                                    $is_duplicate = false;
                                    foreach ($invoices as $inv) {
                                        if ($inv['invoice_number'] === $invoice['invoice_number'] && 
                                            $inv['store_name'] === $invoice['store_name'] && 
                                            $inv['id'] !== $invoice['id'] &&
                                            $invoice['created_at'] > $inv['created_at']) {
                                            $is_duplicate = true;
                                            break;
                                        }
                                    }
                                    if ($is_duplicate): ?>
                                        <br><small style="color: #ff6b6b; font-size: 11px;">⚠️ تکراری</small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $invoice['company_name']; ?></td>
                                <td><?php echo $invoice['store_name']; ?></td>
                                <td><strong><?php echo formatPrice($invoice['amount']); ?></strong></td>
                                <td><?php echo $invoice['date']; ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $invoice['status']; ?>">
                                        <?php 
                                        $status_text = [
                                            'pending' => 'در انتظار',
                                            'in-progress' => 'در حال پیگیری', 
                                            'referred' => 'ارجاع شده',
                                            'completed' => 'تکمیل شده'
                                        ];
                                        echo $status_text[$invoice['status']];
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo $current_user ? $current_user['username'] : 'نامشخص'; ?>
                                    <?php if ($invoice['current_user_id'] !== $invoice['created_by']): ?>
                                        <br><small style="color: #ffc107; font-size: 11px;">ارجاعی</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button onclick="viewInvoice('<?php echo $invoice['id']; ?>')" 
                                            class="btn btn-outline" style="padding: 8px 15px; font-size: 13px;">
                                        <i class="fas fa-eye"></i> مشاهده
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Notifications -->
            <?php if (count($user_notifications) > 0): ?>
            <div class="glass-card fade-in">
                <div class="card-header">
                    <h2>اعلان‌های جدید</h2>
                </div>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <?php foreach (array_slice($user_notifications, 0, 5) as $notification): ?>
                    <div class="notification-item" style="display: flex; align-items: center; gap: 15px; padding: 15px; background: rgba(255, 255, 255, 0.05); border-radius: 12px; border: 1px solid rgba(255, 255, 255, 0.1);">
                        <div style="width: 40px; height: 40px; border-radius: 10px; background: linear-gradient(135deg, #4a9eff, #6f42c1); display: flex; align-items: center; justify-content: center; color: white;">
                            <i class="fas fa-bell"></i>
                        </div>
                        <div style="flex: 1;">
                            <div style="font-weight: 600; color: white;"><?php echo $notification['message']; ?></div>
                            <div style="font-size: 13px; color: rgba(255, 255, 255, 0.6);"><?php echo convertToJalali($notification['timestamp']); ?></div>
                        </div>
                        <?php if ($notification['invoice_id']): ?>
                            <a href="invoice-management.php?action=view&id=<?php echo $notification['invoice_id']; ?>" 
                               class="btn btn-primary" style="padding: 8px 16px; font-size: 13px;">
                                مشاهده فاکتور
                            </a>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Overlay for mobile -->
    <div class="overlay" id="overlay"></div>

    <!-- مودال‌ها -->
    <div id="viewInvoiceModal" class="modal">
        <div class="modal-content" style="max-width: 800px;">
            <div class="modal-header">
                <h3>مشاهده فاکتور</h3>
                <button class="close-modal" onclick="closeModal('viewInvoiceModal')">×</button>
            </div>
            <div id="invoiceDetails" style="padding: 1.5rem;">
                <!-- محتوای فاکتور اینجا لود می‌شود -->
            </div>
        </div>
    </div>

    <div id="referInvoiceModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>ارجاع فاکتور</h3>
                <button class="close-modal" onclick="closeModal('referInvoiceModal')">×</button>
            </div>
            <form id="referForm" method="POST" action="invoice-management.php" enctype="multipart/form-data">
                <input type="hidden" name="invoice_id" id="refer_invoice_id">
                <div class="form-group">
                    <label for="to_user_id">ارجاع به کاربر:</label>
                    <select id="to_user_id" name="to_user_id" class="form-control" required>
                        <option value="">انتخاب کاربر</option>
                        <?php 
                        $users = loadData('users');
                        $admin_users = array_filter($users, function($user) {
                            return $user['role'] === 'admin' && $user['is_active'];
                        });
                        $regular_users = array_filter($users, function($user) {
                            return $user['role'] !== 'admin' && $user['id'] !== $_SESSION['user_id'] && $user['is_active'];
                        });
                        ?>
                        
                        <?php if (!empty($regular_users)): ?>
                            <optgroup label="کاربران عادی">
                                <?php foreach ($regular_users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>">
                                        <?php echo $user['username']; ?> (<?php echo $user['department']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endif; ?>
                        
                        <?php if (!empty($admin_users)): ?>
                            <optgroup label="مدیران سیستم">
                                <?php foreach ($admin_users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>">
                                        <?php echo $user['username']; ?> (ادمین - <?php echo $user['department']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="refer_description">توضیحات ارجاع:</label>
                    <textarea id="refer_description" name="refer_description" class="form-control" rows="3" required></textarea>
                </div>
                <div class="form-group">
                    <label for="refer_attachment">فایل پیوست ارجاع (اختیاری):</label>
                    <input type="file" id="refer_attachment" name="refer_attachment" class="form-control" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.zip,.rar">
                    <small style="color: rgba(255,255,255,0.7); display: block; margin-top: 5px;">
                        فرمت‌های مجاز: JPG, PNG, PDF, DOC, XLS, ZIP (حداکثر 5MB)
                    </small>
                </div>
                <button type="submit" name="refer_invoice" class="btn btn-primary">ارجاع فاکتور</button>
            </form>
        </div>
    </div>

    <div id="filePreviewModal" class="modal">
        <div class="modal-content" style="max-width: 90%; max-height: 90%;">
            <div class="modal-header">
                <h3>پیش‌نمایش فایل</h3>
                <button class="close-modal" onclick="closeModal('filePreviewModal')">×</button>
            </div>
            <div style="text-align: center; padding: 1.5rem;">
                <div id="filePreviewContent"></div>
                <div style="margin-top: 1.5rem; display: flex; gap: 1rem; justify-content: center;">
                    <a id="downloadFile" href="" download class="btn btn-primary">دانلود فایل</a>
                    <button onclick="printFile()" class="btn btn-success">پرینت</button>
                </div>
            </div>
        </div>
    </div>

    <!-- فوتر -->
    <footer class="footer">
        <p>توسعه دهنده: رضااحمدآبادی (پرسنل بخش مالی)</p>
    </footer>

    <script src="js/main.js"></script>
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

        // Search functionality
        const searchInput = document.querySelector('.search-box input');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                const searchTerm = e.target.value.toLowerCase();
                console.log('جستجو برای:', searchTerm);
                // Add your search logic here
            });
        }

        // Menu items click handler
        const menuItems = document.querySelectorAll('.sidebar-nav a');
        menuItems.forEach(item => {
            item.addEventListener('click', () => {
                // Close sidebar on mobile after clicking
                if (window.innerWidth <= 768) {
                    sidebar.classList.remove('active');
                    overlay.classList.remove('active');
                }
            });
        });

        console.log('🎉 پنل مدیریت با موفقیت بارگذاری شد');
    </script>
</body>
</html>