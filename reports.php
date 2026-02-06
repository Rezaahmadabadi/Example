<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$invoices = loadData('invoices');
$users = loadData('users');
$companies = loadData('companies');
$stores = loadData('stores');
$workshops = loadData('workshops');

// فیلترها
$filters = [
    'company' => $_GET['company'] ?? '',
    'workshop' => $_GET['workshop'] ?? '',
    'store' => $_GET['store'] ?? '',
    'status' => $_GET['status'] ?? '',
    'from_date' => $_GET['from_date'] ?? '',
    'to_date' => $_GET['to_date'] ?? ''
];

// فیلتر فاکتورها برای کاربر جاری
$user_invoices = array_filter($invoices, function($invoice) {
    return $invoice['created_by'] === $_SESSION['user_id'] || 
           $invoice['current_user_id'] === $_SESSION['user_id'] ||
           in_array($_SESSION['user_id'], array_column($invoice['tracking_history'], 'user_id'));
});

// اعمال فیلترها
$filtered_invoices = $user_invoices;

if (!empty($filters['company'])) {
    $filtered_invoices = array_filter($filtered_invoices, function($invoice) use ($filters) {
        return $invoice['company_name'] === $filters['company'];
    });
}

if (!empty($filters['workshop'])) {
    $filtered_invoices = array_filter($filtered_invoices, function($invoice) use ($filters) {
        return $invoice['workshop_name'] === $filters['workshop'];
    });
}

if (!empty($filters['store'])) {
    $filtered_invoices = array_filter($filtered_invoices, function($invoice) use ($filters) {
        return $invoice['store_name'] === $filters['store'];
    });
}

if (!empty($filters['status'])) {
    $filtered_invoices = array_filter($filtered_invoices, function($invoice) use ($filters) {
        return $invoice['status'] === $filters['status'];
    });
}

if (!empty($filters['from_date'])) {
    $filtered_invoices = array_filter($filtered_invoices, function($invoice) use ($filters) {
        return $invoice['date'] >= $filters['from_date'];
    });
}

if (!empty($filters['to_date'])) {
    $filtered_invoices = array_filter($filtered_invoices, function($invoice) use ($filters) {
        return $invoice['date'] <= $filters['to_date'];
    });
}

$current_user_header = getUser($_SESSION['user_id']);
$tax_notifications = getUnreadTaxTransactionsCount($_SESSION['user_id']);
$invoice_notifications = getUnreadInvoicesCount($_SESSION['user_id']);
$chat_notifications = getUnreadChatMessagesCount($_SESSION['user_id']);

$avatar_path_header = '';
if (isset($current_user_header['avatar'])) {
    $avatar_path_header = 'uploads/profile-pics/' . $current_user_header['avatar'];
}

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
    <title>گزارشات پیشرفته - سیستم پیگیری فاکتور</title>
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

        .form-container h2 {
            color: white;
            margin-bottom: 25px;
            font-size: 24px;
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

        /* ========== DASHBOARD CARDS ========== */
        .dashboard-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(40px) saturate(180%);
            -webkit-backdrop-filter: blur(40px) saturate(180%);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: var(--radius);
            padding: 24px;
            text-align: center;
            transition: all 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
        }

        .card h3 {
            color: rgba(255, 255, 255, 0.8);
            font-size: 16px;
            margin-bottom: 15px;
        }

        .card .number {
            color: white;
            font-size: 32px;
            font-weight: 700;
        }

        /* ========== TABLE ========== */
        .table-container {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(40px) saturate(180%);
            border-radius: var(--radius);
            padding: 24px;
            border: 1px solid rgba(255, 255, 255, 0.15);
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .table-header h2 {
            color: white;
            font-size: 22px;
            font-weight: 700;
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
            max-width: 800px;
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

        /* ========== RESPONSIVE ========== */
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

            .table-container {
                overflow-x: auto;
            }

            table {
                font-size: 13px;
            }

            th, td {
                padding: 12px 15px;
            }

            .form-container {
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
                <span>گزارشات</span>
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

                <li class="active">
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
                <input type="text" placeholder="جستجو در گزارشات...">
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
            <h1 style="font-size: 32px; font-weight: 800; color: white; margin-bottom: 10px;">گزارشات پیشرفته</h1>
            <p style="color: rgba(255, 255, 255, 0.7); font-size: 16px;">آنالیز و گزارش کامل فاکتورهای سیستم</p>
        </div>

        <!-- فرم فیلتر پیشرفته -->
        <div class="form-container fade-in">
            <h2>فیلتر پیشرفته گزارشات</h2>
            <form method="GET">
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label for="company">شرکت</label>
                        <select id="company" name="company" class="form-control">
                            <option value="">همه شرکت‌ها</option>
                            <?php foreach ($companies as $company): ?>
                                <option value="<?php echo $company['name']; ?>" <?php echo $filters['company'] === $company['name'] ? 'selected' : ''; ?>>
                                    <?php echo $company['name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="workshop">کارگاه/دفتر</label>
                        <select id="workshop" name="workshop" class="form-control">
                            <option value="">همه کارگاه‌ها/دفاتر</option>
                            <?php foreach ($workshops as $workshop): ?>
                                <option value="<?php echo $workshop['name']; ?>" <?php echo $filters['workshop'] === $workshop['name'] ? 'selected' : ''; ?>>
                                    <?php echo $workshop['name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="store">فروشگاه/فروشنده</label>
                        <select id="store" name="store" class="form-control">
                            <option value="">همه فروشگاه‌ها/فروشندگان</option>
                            <?php foreach ($stores as $store): ?>
                                <option value="<?php echo $store['name']; ?>" <?php echo $filters['store'] === $store['name'] ? 'selected' : ''; ?>>
                                    <?php echo $store['name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 1rem; align-items: end;">
                    <div class="form-group">
                        <label for="status">وضعیت</label>
                        <select id="status" name="status" class="form-control">
                            <option value="">همه وضعیت‌ها</option>
                            <option value="pending" <?php echo $filters['status'] === 'pending' ? 'selected' : ''; ?>>در انتظار</option>
                            <option value="in-progress" <?php echo $filters['status'] === 'in-progress' ? 'selected' : ''; ?>>در حال پیگیری</option>
                            <option value="referred" <?php echo $filters['status'] === 'referred' ? 'selected' : ''; ?>>ارجاع شده</option>
                            <option value="completed" <?php echo $filters['status'] === 'completed' ? 'selected' : ''; ?>>تکمیل شده</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="from_date">از تاریخ (شمسی)</label>
                        <input type="text" id="from_date" name="from_date" class="form-control" 
                               value="<?php echo $filters['from_date']; ?>" placeholder="1403/01/01">
                    </div>
                    
                    <div class="form-group">
                        <label for="to_date">تا تاریخ (شمسی)</label>
                        <input type="text" id="to_date" name="to_date" class="form-control" 
                               value="<?php echo $filters['to_date']; ?>" placeholder="1403/12/29">
                    </div>
                    
                    <div>
                        <button type="submit" class="btn btn-primary">اعمال فیلتر</button>
                        <a href="reports.php" class="btn btn-outline">حذف فیلتر</a>
                    </div>
                </div>
            </form>
        </div>

        <!-- آمار کلی -->
        <div class="dashboard-cards">
            <div class="card fade-in">
                <h3>تعداد فاکتورها</h3>
                <div class="number"><?php echo count($filtered_invoices); ?></div>
            </div>
            
            <div class="card fade-in">
                <h3>کل مبلغ</h3>
                <div class="number"><?php echo formatPrice(array_sum(array_column($filtered_invoices, 'amount'))); ?></div>
            </div>
            
            <div class="card fade-in">
                <h3>تکمیل شده</h3>
                <div class="number"><?php echo count(array_filter($filtered_invoices, function($inv) { return $inv['status'] === 'completed'; })); ?></div>
            </div>
            
            <div class="card fade-in">
                <h3>در جریان</h3>
                <div class="number"><?php echo count(array_filter($filtered_invoices, function($inv) { return $inv['status'] !== 'completed'; })); ?></div>
            </div>
        </div>

        <!-- گزارش فاکتورها -->
        <div class="table-container fade-in">
            <div class="table-header">
                <h2>گزارش فاکتورها (<?php echo count($filtered_invoices); ?> مورد)</h2>
                <div style="display: flex; gap: 1rem; align-items: center;">
                    <a href="search.php" class="btn btn-outline">جستجوی پیشرفته</a>
                </div>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>شماره فاکتور</th>
                        <th>شرکت</th>
                        <th>فروشگاه</th>
                        <th>کارگاه</th>
                        <th>مبلغ</th>
                        <th>تاریخ</th>
                        <th>وضعیت</th>
                        <th>کاربر فعلی</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_reverse($filtered_invoices) as $invoice): 
                        $current_user = getUser($invoice['current_user_id']);
                    ?>
                    <tr>
                        <td><?php echo $invoice['invoice_number']; ?></td>
                        <td><?php echo $invoice['company_name']; ?></td>
                        <td style="font-size: 13px;"><?php echo $invoice['store_name']; ?></td>
                        <td style="font-size: 13px;"><?php echo $invoice['workshop_name']; ?></td>
                        <td><?php echo formatPrice($invoice['amount']); ?></td>
                        <td><?php echo $invoice['date']; ?></td>
                        <td style="white-space: nowrap;">
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
                                <small style="color: #ffc107; font-size: 10px;">(ارجاعی)</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button onclick="viewInvoiceTracking('<?php echo $invoice['id']; ?>')" 
                                    class="btn btn-outline" style="padding: 6px 12px; font-size: 12px;">
                                تاریخچه پیگیری
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>

    <!-- Overlay for mobile -->
    <div class="overlay" id="overlay"></div>

    <!-- مودال تاریخچه پیگیری -->
    <div id="trackingModal" class="modal">
        <div class="modal-content" style="max-width: 800px;">
            <div class="modal-header">
                <h3>تاریخچه پیگیری فاکتور</h3>
                <button class="close-modal" onclick="closeModal('trackingModal')">×</button>
            </div>
            <div id="trackingDetails" style="padding: 1rem;">
                <!-- محتوای تاریخچه اینجا لود می‌شود -->
            </div>
        </div>
    </div>

    <!-- فوتر -->
    <footer class="footer">
        <p>توسعه دهنده: رضااحمدآبادی (پرسنل بخش مالی)</p>
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

        function viewInvoiceTracking(invoiceId) {
            fetch(`get-invoice-tracking.php?id=${invoiceId}`)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('trackingDetails').innerHTML = data;
                    document.getElementById('trackingModal').classList.add('active');
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('trackingDetails').innerHTML = '<div style="text-align: center; padding: 2rem; color: #ff6b6b;">خطا در بارگذاری تاریخچه</div>';
                    document.getElementById('trackingModal').classList.add('active');
                });
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }
    </script>
</body>
</html>