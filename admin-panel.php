<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Location: dashboard.php');
    exit();
}

$users = loadData('users');
$companies = loadData('companies');
$stores = loadData('stores');
$workshops = loadData('workshops');
$invoices = loadData('invoices');
$message = '';

// مدیریت شرکت‌ها
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_company'])) {
    $company_name = trim($_POST['company_name']);
    
    if (!empty($company_name)) {
        // بررسی تکراری نبودن
        $is_duplicate = false;
        foreach ($companies as $company) {
            if ($company['name'] === $company_name) {
                $is_duplicate = true;
                break;
            }
        }
        
        if ($is_duplicate) {
            $message = '<div class="error-highlight">⚠️ این شرکت قبلاً ثبت شده است</div>';
        } else {
            $companies[] = [
                'id' => uniqid(),
                'name' => $company_name,
                'created_by' => $_SESSION['user_id'],
                'created_at' => time()
            ];
            saveData('companies', $companies);
            $message = '<div class="success-highlight">✅ شرکت با موفقیت اضافه شد</div>';
        }
    }
}

// مدیریت فروشگاه‌ها/فروشندگان
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_store'])) {
    $store_name = trim($_POST['store_name']);
    
    if (!empty($store_name)) {
        // بررسی تکراری نبودن
        $is_duplicate = false;
        foreach ($stores as $store) {
            if ($store['name'] === $store_name) {
                $is_duplicate = true;
                break;
            }
        }
        
        if ($is_duplicate) {
            $message = '<div class="error-highlight">⚠️ این فروشگاه/فروشنده قبلاً ثبت شده است</div>';
        } else {
            $stores[] = [
                'id' => uniqid(),
                'name' => $store_name,
                'created_by' => $_SESSION['user_id'],
                'created_at' => time()
            ];
            saveData('stores', $stores);
            $message = '<div class="success-highlight">✅ فروشگاه/فروشنده با موفقیت اضافه شد</div>';
        }
    }
}

// مدیریت کارگاه‌ها/دفاتر
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_workshop'])) {
    $workshop_name = trim($_POST['workshop_name']);
    
    if (!empty($workshop_name)) {
        // بررسی تکراری نبودن
        $is_duplicate = false;
        foreach ($workshops as $workshop) {
            if ($workshop['name'] === $workshop_name) {
                $is_duplicate = true;
                break;
            }
        }
        
        if ($is_duplicate) {
            $message = '<div class="error-highlight">⚠️ این کارگاه/دفتر قبلاً ثبت شده است</div>';
        } else {
            $workshops[] = [
                'id' => uniqid(),
                'name' => $workshop_name,
                'created_by' => $_SESSION['user_id'],
                'created_at' => time()
            ];
            saveData('workshops', $workshops);
            $message = '<div class="success-highlight">✅ کارگاه/دفتر با موفقیت اضافه شد</div>';
        }
    }
}

// حذف شرکت
if (isset($_GET['delete_company'])) {
    $company_id = $_GET['delete_company'];
    
    // پیدا کردن شرکت
    $company_to_delete = null;
    foreach ($companies as $company) {
        if ($company['id'] === $company_id) {
            $company_to_delete = $company;
            break;
        }
    }
    
    if ($company_to_delete) {
        // بررسی اینکه آیا شرکت در فاکتورها استفاده شده
        $company_used = false;
        foreach ($invoices as $invoice) {
            if ($invoice['company_name'] === $company_to_delete['name']) {
                $company_used = true;
                break;
            }
        }
        
        if ($company_used) {
            $message = '<div class="error-highlight">⚠️ این شرکت در فاکتورها استفاده شده و قابل حذف نیست</div>';
        } else {
            $companies = array_filter($companies, function($company) use ($company_id) {
                return $company['id'] !== $company_id;
            });
            saveData('companies', $companies);
            $message = '<div class="success-highlight">✅ شرکت با موفقیت حذف شد</div>';
        }
    }
}

// حذف فروشگاه
if (isset($_GET['delete_store'])) {
    $store_id = $_GET['delete_store'];
    
    // پیدا کردن فروشگاه
    $store_to_delete = null;
    foreach ($stores as $store) {
        if ($store['id'] === $store_id) {
            $store_to_delete = $store;
            break;
        }
    }
    
    if ($store_to_delete) {
        // بررسی اینکه آیا فروشگاه در فاکتورها استفاده شده
        $store_used = false;
        foreach ($invoices as $invoice) {
            if ($invoice['store_name'] === $store_to_delete['name']) {
                $store_used = true;
                break;
            }
        }
        
        if ($store_used) {
            $message = '<div class="error-highlight">⚠️ این فروشگاه/فروشنده در فاکتورها استفاده شده و قابل حذف نیست</div>';
        } else {
            $stores = array_filter($stores, function($store) use ($store_id) {
                return $store['id'] !== $store_id;
            });
            saveData('stores', $stores);
            $message = '<div class="success-highlight">✅ فروشگاه/فروشنده با موفقیت حذف شد</div>';
        }
    }
}

// حذف کارگاه
if (isset($_GET['delete_workshop'])) {
    $workshop_id = $_GET['delete_workshop'];
    
    // پیدا کردن کارگاه
    $workshop_to_delete = null;
    foreach ($workshops as $workshop) {
        if ($workshop['id'] === $workshop_id) {
            $workshop_to_delete = $workshop;
            break;
        }
    }
    
    if ($workshop_to_delete) {
        // بررسی اینکه آیا کارگاه در فاکتورها استفاده شده
        $workshop_used = false;
        foreach ($invoices as $invoice) {
            if ($invoice['workshop_name'] === $workshop_to_delete['name']) {
                $workshop_used = true;
                break;
            }
        }
        
        if ($workshop_used) {
            $message = '<div class="error-highlight">⚠️ این کارگاه/دفتر در فاکتورها استفاده شده و قابل حذف نیست</div>';
        } else {
            $workshops = array_filter($workshops, function($workshop) use ($workshop_id) {
                return $workshop['id'] !== $workshop_id;
            });
            saveData('workshops', $workshops);
            $message = '<div class="success-highlight">✅ کارگاه/دفتر با موفقیت حذف شد</div>';
        }
    }
}

// مدیریت کاربران
if (isset($_GET['toggle_user'])) {
    $user_id = $_GET['toggle_user'];
    foreach ($users as &$user) {
        if ($user['id'] === $user_id) {
            $user['is_active'] = !$user['is_active'];
            break;
        }
    }
    saveData('users', $users);
    $message = '<div class="success-highlight">✅ وضعیت کاربر با موفقیت تغییر کرد</div>';
}

// مدیریت دسترسی ثبت فاکتور
if (isset($_GET['toggle_create_invoice'])) {
    $user_id = $_GET['toggle_create_invoice'];
    foreach ($users as &$user) {
        if ($user['id'] === $user_id) {
            $user['can_create_invoice'] = !(isset($user['can_create_invoice']) ? $user['can_create_invoice'] : false);
            break;
        }
    }
    saveData('users', $users);
    $message = '<div class="success-highlight">✅ دسترسی ثبت فاکتور با موفقیت تغییر کرد</div>';
}

// مدیریت دسترسی دریافت ارجاع
if (isset($_GET['toggle_receive_referral'])) {
    $user_id = $_GET['toggle_receive_referral'];
    foreach ($users as &$user) {
        if ($user['id'] === $user_id) {
            $user['can_receive_referral'] = !(isset($user['can_receive_referral']) ? $user['can_receive_referral'] : true);
            break;
        }
    }
    saveData('users', $users);
    $message = '<div class="success-highlight">✅ دسترسی دریافت ارجاع با موفقیت تغییر کرد</div>';
}

// آمارهای داشبورد
$total_invoices = count($invoices);
$pending_invoices = count(array_filter($invoices, function($invoice) {
    return $invoice['status'] === 'pending';
}));
$completed_invoices = count(array_filter($invoices, function($invoice) {
    return $invoice['status'] === 'completed';
}));
$active_users = count(array_filter($users, function($user) {
    return $user['is_active'];
}));
$admin_users = count(array_filter($users, function($user) {
    return $user['role'] === 'admin';
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
    <title>پنل مدیریت - سیستم پیگیری فاکتور</title>
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

        /* ========== ADMIN DASHBOARD CARDS ========== */
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

        .status-completed {
            background: rgba(52, 199, 89, 0.2);
            color: #34C759;
            border: 1px solid rgba(52, 199, 89, 0.3);
        }

        .status-active {
            background: rgba(74, 158, 255, 0.2);
            color: #4a9eff;
            border: 1px solid rgba(74, 158, 255, 0.3);
        }

        .status-inactive {
            background: rgba(108, 117, 125, 0.2);
            color: #6c757d;
            border: 1px solid rgba(108, 117, 125, 0.3);
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
            .table-container {
                padding: 20px;
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

        /* ========== STATS GRID ========== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }

        .stats-grid p {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 16px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            margin: 0;
            font-size: 14px;
        }

        .stats-grid p strong {
            color: white;
            font-size: 16px;
        }

/* ========== استایل‌های جدید برای تب‌ها و مودال‌ها ========== */

.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 1000;
    align-items: center;
    justify-content: center;
    padding: 20px;
    box-sizing: border-box;
}

.modal.active {
    display: flex;
}

.modal-content {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(80px) saturate(180%);
    -webkit-backdrop-filter: blur(80px) saturate(180%);
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: var(--radius);
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    animation: modalSlideIn 0.3s ease;
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 24px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.modal-header h3 {
    color: white;
    font-size: 20px;
    font-weight: 700;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.close-modal {
    background: none;
    border: none;
    color: rgba(255, 255, 255, 0.7);
    font-size: 28px;
    cursor: pointer;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 20px;
    transition: all 0.3s ease;
}

.close-modal:hover {
    background: rgba(255, 255, 255, 0.1);
    color: white;
}

/* استایل‌های خاص برای مدیریت زنجیره‌ها */
.chain-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
    margin: 20px 0;
}

.chain-stat-card {
    background: rgba(255,255,255,0.05);
    border-radius: 10px;
    padding: 15px;
    text-align: center;
    transition: all 0.3s ease;
}

.chain-stat-card:hover {
    transform: translateY(-5px);
    background: rgba(255,255,255,0.08);
}

.chain-stat-value {
    font-size: 28px;
    font-weight: 800;
    color: white;
    margin-bottom: 5px;
}

.chain-stat-label {
    color: rgba(255,255,255,0.7);
    font-size: 13px;
}

/* استایل برای جدول زنجیره‌ها */
.chains-table {
    width: 100%;
    border-collapse: collapse;
    background: rgba(255,255,255,0.03);
    border-radius: 8px;
    overflow: hidden;
}

.chains-table th {
    background: rgba(74,158,255,0.15);
    color: white;
    font-weight: 600;
    padding: 14px 16px;
    text-align: right;
    font-size: 14px;
}

.chains-table td {
    padding: 12px 16px;
    border-bottom: 1px solid rgba(255,255,255,0.05);
    color: rgba(255,255,255,0.9);
    font-size: 14px;
}

.chains-table tr:hover {
    background: rgba(255,255,255,0.05);
}

/* استایل برای مراحل زنجیره */
.chain-stage-item {
    background: rgba(255,255,255,0.05);
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 10px;
    border: 1px solid rgba(255,255,255,0.1);
}

.chain-stage-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
}

.chain-stage-number {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: linear-gradient(135deg, #4a9eff, #6f42c1);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
}

.chain-stage-users {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.chain-user-tag {
    background: rgba(255,255,255,0.1);
    border-radius: 15px;
    padding: 6px 12px;
    font-size: 13px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.chain-user-avatar {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: linear-gradient(135deg, #4a9eff, #6f42c1);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 10px;
    font-weight: bold;
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
        
        /* ========== TAB STYLES ========== */
        .tab-btn {
            padding: 12px 24px;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 8px;
            color: rgba(255,255,255,0.7);
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            flex: 1;
            min-width: 150px;
            justify-content: center;
        }
        
        .tab-btn:hover {
            background: rgba(255,255,255,0.1);
            color: white;
            transform: translateY(-2px);
        }
        
        .tab-btn.active {
            background: linear-gradient(135deg, #4a9eff 0%, #357abd 100%);
            color: white;
            border-color: rgba(74, 158, 255, 0.3);
            box-shadow: 0 4px 12px rgba(74, 158, 255, 0.2);
        }
        
        .tab-content {
            display: none;
            animation: fadeIn 0.3s ease;
        }
        
        .tab-content.active {
            display: block;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* ========== MODAL STYLES ========== */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            backdrop-filter: blur(5px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(40px) saturate(180%);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: var(--radius);
            padding: 25px;
            max-width: 90%;
            max-height: 90%;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .modal-header h3 {
            color: white;
            font-size: 20px;
            margin: 0;
        }
        
        .close-modal {
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <img src="assets/logo/company-logo.png" alt="لوگو شرکت">
                <span>پنل مدیریت</span>
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

                <li class="active">
                    <a href="admin-panel.php">
                        <i class="fas fa-cog"></i>
                        <span>پنل مدیریت</span>
                    </a>
                </li>

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
                    <p>ادمین - <?php echo $_SESSION['department']; ?></p>
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
                <input type="text" placeholder="جستجو در پنل مدیریت...">
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

        <!-- Admin Dashboard Content -->
        <div class="dashboard-content">
            <div class="page-title" style="margin-bottom: 30px;">
                <h1 style="font-size: 32px; font-weight: 800; color: white; margin-bottom: 10px;">پنل مدیریت</h1>
                <p style="color: rgba(255, 255, 255, 0.7); font-size: 16px;">مدیریت کامل سیستم - <?php echo $_SESSION['username']; ?></p>
            </div>

            <!-- Messages -->
            <?php echo $message; ?>

            <!-- Quick Stats -->
            <div class="dashboard-cards">
                <div class="stat-card fade-in">
                    <div class="stat-icon blue">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo count($users); ?></h3>
                        <p>کاربران سیستم</p>
                    </div>
                </div>

                <div class="stat-card fade-in">
                    <div class="stat-icon green">
                        <i class="fas fa-file-invoice"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $total_invoices; ?></h3>
                        <p>کل فاکتورها</p>
                    </div>
                </div>

                <div class="stat-card fade-in">
                    <div class="stat-icon orange">
                        <i class="fas fa-building"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo count($companies); ?></h3>
                        <p>شرکت‌ها</p>
                    </div>
                </div>

                <div class="stat-card fade-in">
                    <div class="stat-icon purple">
                        <i class="fas fa-store"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo count($stores); ?></h3>
                        <p>فروشگاه‌ها</p>
                    </div>
                </div>
            </div>
            
            <!-- ========== تب‌های مدیریت ========== -->
            <div style="margin: 30px 0;">
                <div class="tabs-container" style="background: rgba(255,255,255,0.05); border-radius: 12px; padding: 10px; display: flex; gap: 5px; flex-wrap: wrap; margin-bottom: 30px;">
                    <button class="tab-btn active" data-tab="companies" onclick="switchTab('companies')">
                        <i class="fas fa-building"></i> شرکت‌ها
                    </button>
                    <button class="tab-btn" data-tab="stores" onclick="switchTab('stores')">
                        <i class="fas fa-store"></i> فروشگاه‌ها
                    </button>
                    <button class="tab-btn" data-tab="workshops" onclick="switchTab('workshops')">
                        <i class="fas fa-industry"></i> کارگاه‌ها
                    </button>
                    <button class="tab-btn" data-tab="users" onclick="switchTab('users')">
                        <i class="fas fa-users-cog"></i> کاربران
                    </button>
                    <button class="tab-btn" data-tab="approval-chains" onclick="switchTab('approval-chains')">
                        <i class="fas fa-sitemap"></i> زنجیره‌های تأیید
                    </button>
                    <button class="tab-btn" data-tab="approval-options" onclick="switchTab('approval-options')">
                        <i class="fas fa-check-circle"></i> گزینه‌های تأیید
                    </button>
                    <button class="tab-btn" data-tab="reports" onclick="switchTab('reports')">
                        <i class="fas fa-chart-pie"></i> گزارشات
                    </button>
                </div>
            </div>

            <!-- ========== تب: مدیریت شرکت‌ها ========== -->
            <div id="tab-companies" class="tab-content active">
                <!-- مدیریت شرکت‌ها -->
                <div class="form-container fade-in">
                    <h2><i class="fas fa-building"></i> مدیریت شرکت‌ها</h2>
                    <form method="POST" class="form-row">
                        <div class="form-group">
                            <label for="company_name">نام شرکت جدید</label>
                            <input type="text" id="company_name" name="company_name" class="form-control" required placeholder="نام شرکت را وارد کنید">
                        </div>
                        <button type="submit" name="add_company" class="btn btn-primary">
                            <i class="fas fa-plus"></i> افزودن شرکت
                        </button>
                    </form>
                </div>

                <!-- لیست شرکت‌ها -->
                <div class="table-container fade-in">
                    <div class="table-header">
                        <h2><i class="fas fa-list"></i> لیست شرکت‌ها</h2>
                        <span class="btn-secondary" style="cursor: default; background: rgba(74,158,255,0.1);">
                            <?php echo count($companies); ?> شرکت
                        </span>
                    </div>
                    <div style="overflow-x: auto;">
                        <table>
                            <thead>
                                <tr>
                                    <th>نام شرکت</th>
                                    <th>تاریخ ایجاد</th>
                                    <th>ایجاد کننده</th>
                                    <th>وضعیت</th>
                                    <th>عملیات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($companies as $company): 
                                    $creator = getUser($company['created_by']);
                                    
                                    // بررسی اینکه آیا شرکت در فاکتورها استفاده شده
                                    $company_used = false;
                                    foreach ($invoices as $invoice) {
                                        if ($invoice['company_name'] === $company['name']) {
                                            $company_used = true;
                                            break;
                                        }
                                    }
                                ?>
                                <tr>
                                    <td>
                                        <strong><?php echo $company['name']; ?></strong>
                                    </td>
                                    <td><?php echo convertToJalali($company['created_at']); ?></td>
                                    <td>
                                        <?php if ($creator): ?>
                                            <div style="display: flex; align-items: center; gap: 10px;">
                                                <div style="width: 30px; height: 30px; border-radius: 50%; background: linear-gradient(135deg, #4a9eff, #6f42c1); display: flex; align-items: center; justify-content: center; color: white; font-size: 12px;">
                                                    <?php echo strtoupper(substr($creator['username'], 0, 1)); ?>
                                                </div>
                                                <?php echo $creator['username']; ?>
                                            </div>
                                        <?php else: ?>
                                            نامشخص
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($company_used): ?>
                                            <span class="status-badge status-active">
                                                <i class="fas fa-check-circle"></i> فعال
                                            </span>
                                        <?php else: ?>
                                            <span class="status-badge status-pending">
                                                <i class="fas fa-clock"></i> آزاد
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <?php if (!$company_used): ?>
                                                <a href="?delete_company=<?php echo $company['id']; ?>" 
                                                   class="btn btn-danger btn-small"
                                                   onclick="return confirm('آیا از حذف شرکت <?php echo $company['name']; ?> اطمینان دارید؟')">
                                                    <i class="fas fa-trash"></i> حذف
                                                </a>
                                            <?php else: ?>
                                                <button class="btn btn-outline btn-small" disabled title="این شرکت در فاکتورها استفاده شده">
                                                    <i class="fas fa-lock"></i> قفل شده
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ========== تب: مدیریت فروشگاه‌ها ========== -->
            <div id="tab-stores" class="tab-content">
                <!-- مدیریت فروشگاه‌ها/فروشندگان -->
                <div class="form-container fade-in">
                    <h2><i class="fas fa-store"></i> مدیریت فروشگاه‌ها/فروشندگان</h2>
                    <form method="POST" class="form-row">
                        <div class="form-group">
                            <label for="store_name">نام فروشگاه/فروشنده جدید</label>
                            <input type="text" id="store_name" name="store_name" class="form-control" required placeholder="نام فروشگاه یا فروشنده را وارد کنید">
                        </div>
                        <button type="submit" name="add_store" class="btn btn-primary">
                            <i class="fas fa-plus"></i> افزودن فروشگاه
                        </button>
                    </form>
                </div>

                <!-- لیست فروشگاه‌ها/فروشندگان -->
                <div class="table-container fade-in">
                    <div class="table-header">
                        <h2><i class="fas fa-list"></i> لیست فروشگاه‌ها/فروشندگان</h2>
                        <span class="btn-secondary" style="cursor: default; background: rgba(74,158,255,0.1);">
                            <?php echo count($stores); ?> فروشگاه
                        </span>
                    </div>
                    <div style="overflow-x: auto;">
                        <table>
                            <thead>
                                <tr>
                                    <th>نام فروشگاه/فروشنده</th>
                                    <th>تاریخ ایجاد</th>
                                    <th>ایجاد کننده</th>
                                    <th>وضعیت</th>
                                    <th>عملیات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stores as $store): 
                                    $creator = getUser($store['created_by']);
                                    
                                    // بررسی اینکه آیا فروشگاه در فاکتورها استفاده شده
                                    $store_used = false;
                                    foreach ($invoices as $invoice) {
                                        if ($invoice['store_name'] === $store['name']) {
                                            $store_used = true;
                                            break;
                                        }
                                    }
                                ?>
                                <tr>
                                    <td>
                                        <strong><?php echo $store['name']; ?></strong>
                                    </td>
                                    <td><?php echo convertToJalali($store['created_at']); ?></td>
                                    <td>
                                        <?php if ($creator): ?>
                                            <div style="display: flex; align-items: center; gap: 10px;">
                                                <div style="width: 30px; height: 30px; border-radius: 50%; background: linear-gradient(135deg, #4a9eff, #6f42c1); display: flex; align-items: center; justify-content: center; color: white; font-size: 12px;">
                                                    <?php echo strtoupper(substr($creator['username'], 0, 1)); ?>
                                                </div>
                                                <?php echo $creator['username']; ?>
                                            </div>
                                        <?php else: ?>
                                            نامشخص
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($store_used): ?>
                                            <span class="status-badge status-active">
                                                <i class="fas fa-check-circle"></i> فعال
                                            </span>
                                        <?php else: ?>
                                            <span class="status-badge status-pending">
                                                <i class="fas fa-clock"></i> آزاد
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <?php if (!$store_used): ?>
                                                <a href="?delete_store=<?php echo $store['id']; ?>" 
                                                   class="btn btn-danger btn-small"
                                                   onclick="return confirm('آیا از حذف فروشگاه <?php echo $store['name']; ?> اطمینان دارید؟')">
                                                    <i class="fas fa-trash"></i> حذف
                                                </a>
                                            <?php else: ?>
                                                <button class="btn btn-outline btn-small" disabled title="این فروشگاه در فاکتورها استفاده شده">
                                                    <i class="fas fa-lock"></i> قفل شده
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ========== تب: مدیریت کارگاه‌ها ========== -->
            <div id="tab-workshops" class="tab-content">
                <!-- مدیریت کارگاه‌ها/دفاتر -->
                <div class="form-container fade-in">
                    <h2><i class="fas fa-industry"></i> مدیریت کارگاه‌ها/دفاتر</h2>
                    <form method="POST" class="form-row">
                        <div class="form-group">
                            <label for="workshop_name">نام کارگاه/دفتر جدید</label>
                            <input type="text" id="workshop_name" name="workshop_name" class="form-control" required placeholder="نام کارگاه یا دفتر را وارد کنید">
                        </div>
                        <button type="submit" name="add_workshop" class="btn btn-primary">
                            <i class="fas fa-plus"></i> افزودن کارگاه
                        </button>
                    </form>
                </div>

                <!-- لیست کارگاه‌ها/دفاتر -->
                <div class="table-container fade-in">
                    <div class="table-header">
                        <h2><i class="fas fa-list"></i> لیست کارگاه‌ها/دفاتر</h2>
                        <span class="btn-secondary" style="cursor: default; background: rgba(74,158,255,0.1);">
                            <?php echo count($workshops); ?> کارگاه
                        </span>
                    </div>
                    <div style="overflow-x: auto;">
                        <table>
                            <thead>
                                <tr>
                                    <th>نام کارگاه/دفتر</th>
                                    <th>تاریخ ایجاد</th>
                                    <th>ایجاد کننده</th>
                                    <th>وضعیت</th>
                                    <th>عملیات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($workshops as $workshop): 
                                    $creator = getUser($workshop['created_by']);
                                    
                                    // بررسی اینکه آیا کارگاه در فاکتورها استفاده شده
                                    $workshop_used = false;
                                    foreach ($invoices as $invoice) {
                                        if ($invoice['workshop_name'] === $workshop['name']) {
                                            $workshop_used = true;
                                            break;
                                        }
                                    }
                                ?>
                                <tr>
                                    <td>
                                        <strong><?php echo $workshop['name']; ?></strong>
                                    </td>
                                    <td><?php echo convertToJalali($workshop['created_at']); ?></td>
                                    <td>
                                        <?php if ($creator): ?>
                                            <div style="display: flex; align-items: center; gap: 10px;">
                                                <div style="width: 30px; height: 30px; border-radius: 50%; background: linear-gradient(135deg, #4a9eff, #6f42c1); display: flex; align-items: center; justify-content: center; color: white; font-size: 12px;">
                                                    <?php echo strtoupper(substr($creator['username'], 0, 1)); ?>
                                                </div>
                                                <?php echo $creator['username']; ?>
                                            </div>
                                        <?php else: ?>
                                            نامشخص
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($workshop_used): ?>
                                            <span class="status-badge status-active">
                                                <i class="fas fa-check-circle"></i> فعال
                                            </span>
                                        <?php else: ?>
                                            <span class="status-badge status-pending">
                                                <i class="fas fa-clock"></i> آزاد
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <?php if (!$workshop_used): ?>
                                                <a href="?delete_workshop=<?php echo $workshop['id']; ?>" 
                                                   class="btn btn-danger btn-small"
                                                   onclick="return confirm('آیا از حذف کارگاه <?php echo $workshop['name']; ?> اطمینان دارید؟')">
                                                    <i class="fas fa-trash"></i> حذف
                                                </a>
                                            <?php else: ?>
                                                <button class="btn btn-outline btn-small" disabled title="این کارگاه در فاکتورها استفاده شده">
                                                    <i class="fas fa-lock"></i> قفل شده
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ========== تب: مدیریت کاربران ========== -->
            <div id="tab-users" class="tab-content">
                <!-- مدیریت کاربران و دسترسی‌ها -->
                <div class="table-container fade-in">
                    <div class="table-header">
                        <h2><i class="fas fa-users-cog"></i> مدیریت کاربران و دسترسی‌ها</h2>
                        <span class="btn-secondary" style="cursor: default; background: rgba(74,158,255,0.1);">
                            <?php echo count($users); ?> کاربر
                        </span>
                    </div>
                    <div style="overflow-x: auto;">
                        <table>
                            <thead>
                                <tr>
                                    <th>نام کاربری</th>
                                    <th>بخش</th>
                                    <th>نقش</th>
                                    <th>وضعیت</th>
                                    <th>ثبت فاکتور</th>
                                    <th>دریافت ارجاع</th>
                                    <th>عملیات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                <tr>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <div style="width: 35px; height: 35px; border-radius: 50%; background: linear-gradient(135deg, <?php echo $user['is_active'] ? '#4a9eff' : '#6c757d'; ?>, <?php echo $user['is_active'] ? '#6f42c1' : '#495057'; ?>); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;">
                                                <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <strong><?php echo $user['username']; ?></strong>
                                                <?php if ($user['role'] === 'admin'): ?>
                                                    <div style="font-size: 11px; color: #4a9eff;">ادمین سیستم</div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo $user['department']; ?></td>
                                    <td>
                                        <span class="status-badge <?php echo $user['role'] === 'admin' ? 'status-active' : 'status-pending'; ?>">
                                            <?php echo $user['role'] === 'admin' ? 'ادمین' : 'کاربر'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $user['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                            <?php echo $user['is_active'] ? 'فعال' : 'غیرفعال'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo (isset($user['can_create_invoice']) && $user['can_create_invoice']) ? 'status-active' : 'status-inactive'; ?>">
                                            <?php echo (isset($user['can_create_invoice']) && $user['can_create_invoice']) ? 'فعال' : 'غیرفعال'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo (!isset($user['can_receive_referral']) || $user['can_receive_referral']) ? 'status-active' : 'status-inactive'; ?>">
                                            <?php echo (!isset($user['can_receive_referral']) || $user['can_receive_referral']) ? 'فعال' : 'غیرفعال'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="?toggle_user=<?php echo $user['id']; ?>" 
                                               class="btn <?php echo $user['is_active'] ? 'btn-warning' : 'btn-success'; ?> btn-small">
                                                <?php echo $user['is_active'] ? 'غیرفعال' : 'فعال'; ?>
                                            </a>
                                            <a href="?toggle_create_invoice=<?php echo $user['id']; ?>" 
                                               class="btn <?php echo (isset($user['can_create_invoice']) && $user['can_create_invoice']) ? 'btn-warning' : 'btn-success'; ?> btn-small">
                                                <?php echo (isset($user['can_create_invoice']) && $user['can_create_invoice']) ? 'لغو دسترسی' : 'اعطای دسترسی'; ?>
                                            </a>
                                            <a href="?toggle_receive_referral=<?php echo $user['id']; ?>" 
                                               class="btn <?php echo (!isset($user['can_receive_referral']) || $user['can_receive_referral']) ? 'btn-warning' : 'btn-success'; ?> btn-small">
                                                <?php echo (!isset($user['can_receive_referral']) || $user['can_receive_referral']) ? 'لغو ارجاع' : 'اجازه ارجاع'; ?>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ========== تب: مدیریت زنجیره‌های تأیید ========== -->
            <div id="tab-approval-chains" class="tab-content">
                <div class="form-container fade-in">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h2><i class="fas fa-sitemap"></i> مدیریت زنجیره‌های تأیید سلسله‌مراتبی</h2>
                        <div style="display: flex; gap: 10px;">
                            <button onclick="refreshChains()" class="btn btn-outline">
                                <i class="fas fa-sync-alt"></i> به‌روزرسانی
                            </button>
                            <button onclick="exportChainsData()" class="btn btn-success">
                                <i class="fas fa-download"></i> خروجی Excel
                            </button>
                        </div>
                    </div>
                    
                    <!-- آمار زنجیره‌ها -->
                    <?php
                    require_once 'includes/approval-system.php';
                    $chains_stats = ApprovalSystem::getChainStatistics();
                    ?>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 25px;">
                        <div style="background: rgba(74,158,255,0.1); padding: 15px; border-radius: 10px; text-align: center;">
                            <div style="font-size: 24px; color: white; font-weight: 800;"><?php echo $chains_stats['total_chains']; ?></div>
                            <div style="color: rgba(255,255,255,0.7); font-size: 13px;">کل زنجیره‌ها</div>
                        </div>
                        <div style="background: rgba(52,199,89,0.1); padding: 15px; border-radius: 10px; text-align: center;">
                            <div style="font-size: 24px; color: white; font-weight: 800;"><?php echo $chains_stats['completed_chains']; ?></div>
                            <div style="color: rgba(255,255,255,0.7); font-size: 13px;">تکمیل شده</div>
                        </div>
                        <div style="background: rgba(255,193,7,0.1); padding: 15px; border-radius: 10px; text-align: center;">
                            <div style="font-size: 24px; color: white; font-weight: 800;"><?php echo $chains_stats['active_chains']; ?></div>
                            <div style="color: rgba(255,255,255,0.7); font-size: 13px;">فعال</div>
                        </div>
                        <div style="background: rgba(255,107,107,0.1); padding: 15px; border-radius: 10px; text-align: center;">
                            <div style="font-size: 24px; color: white; font-weight: 800;"><?php echo $chains_stats['delayed_chains']; ?></div>
                            <div style="color: rgba(255,255,255,0.7); font-size: 13px;">دارای تأخیر</div>
                        </div>
                    </div>
                    
                    <!-- فیلترها -->
                    <div style="background: rgba(255,255,255,0.05); border-radius: 10px; padding: 20px; margin-bottom: 25px;">
                        <h4 style="color: white; margin-bottom: 15px; font-size: 16px;">
                            <i class="fas fa-filter"></i> فیلتر زنجیره‌ها
                        </h4>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                            <div>
                                <label style="display: block; color: rgba(255,255,255,0.8); margin-bottom: 8px; font-size: 14px;">وضعیت:</label>
                                <select id="filterStatus" class="form-control" style="font-size: 14px;">
                                    <option value="all">همه وضعیت‌ها</option>
                                    <option value="active">فعال</option>
                                    <option value="completed">تکمیل شده</option>
                                    <option value="delayed">دارای تأخیر</option>
                                </select>
                            </div>
                            <div>
                                <label style="display: block; color: rgba(255,255,255,0.8); margin-bottom: 8px; font-size: 14px;">سرپرست:</label>
                                <select id="filterSupervisor" class="form-control" style="font-size: 14px;">
                                    <option value="all">همه سرپرستان</option>
                                    <?php 
                                    $supervisors = array_filter($users, function($user) {
                                        return isset($user['is_supervisor']) && $user['is_supervisor'];
                                    });
                                    foreach ($supervisors as $supervisor): ?>
                                        <option value="<?php echo $supervisor['id']; ?>">
                                            <?php echo $supervisor['username']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label style="display: block; color: rgba(255,255,255,0.8); margin-bottom: 8px; font-size: 14px;">مرحله:</label>
                                <select id="filterStage" class="form-control" style="font-size: 14px;">
                                    <option value="all">همه مراحل</option>
                                    <?php for($i = 1; $i <= 10; $i++): ?>
                                        <option value="<?php echo $i; ?>">مرحله <?php echo $i; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div style="display: flex; align-items: flex-end;">
                                <button onclick="applyFilters()" class="btn btn-primary" style="height: 42px; width: 100%;">
                                    <i class="fas fa-search"></i> اعمال فیلتر
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- لیست زنجیره‌ها -->
                    <div class="table-container fade-in">
                        <div class="table-header">
                            <h2><i class="fas fa-list"></i> لیست زنجیره‌های تأیید</h2>
                            <span id="chainsCount" class="btn-secondary" style="cursor: default; background: rgba(74,158,255,0.1);">
                                <?php echo $chains_stats['total_chains']; ?> زنجیره
                            </span>
                        </div>
                        <div id="chainsList" style="min-height: 300px;">
                            <!-- محتوای زنجیره‌ها اینجا لود می‌شود -->
                            <div style="text-align: center; padding: 40px; color: rgba(255,255,255,0.7);">
                                <div style="font-size: 48px; margin-bottom: 20px; opacity: 0.5;">
                                    ⏳
                                </div>
                                <p>در حال بارگذاری زنجیره‌ها...</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- مدیریت سرپرستان -->
                    <div class="table-container fade-in">
                        <div class="table-header">
                            <h2><i class="fas fa-user-tie"></i> مدیریت سرپرستان</h2>
                            <button onclick="openAddSupervisorModal()" class="btn btn-primary">
                                <i class="fas fa-plus"></i> افزودن سرپرست
                            </button>
                        </div>
                        <div style="overflow-x: auto;">
                            <table>
                                <thead>
                                    <tr>
                                        <th>نام کاربری</th>
                                        <th>بخش</th>
                                        <th>تعداد زنجیره‌ها</th>
                                        <th>تکمیل شده</th>
                                        <th>در حال بررسی</th>
                                        <th>دارای تأخیر</th>
                                        <th>عملیات</th>
                                    </tr>
                                </thead>
                                <tbody id="supervisorsList">
                                    <!-- لیست سرپرستان -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========== تب: مدیریت گزینه‌های تأیید ========== -->
            <div id="tab-approval-options" class="tab-content">
                <!-- مدیریت گزینه‌های تأییدیه فاکتور -->
                <div class="form-container fade-in">
                    <div class="table-header" style="margin-bottom: 20px;">
                        <h2><i class="fas fa-check-circle"></i> مدیریت گزینه‌های تأییدیه</h2>
                        <span style="color: rgba(255,255,255,0.7); font-size: 14px; background: rgba(52, 199, 89, 0.1); padding: 6px 12px; border-radius: 20px;">
                            <?php 
                            $settings = loadData('approval-settings');
                            if ($settings) {
                                $total_options = 0;
                                foreach ($settings as $dept) {
                                    $total_options += count($dept['options'] ?? []);
                                }
                                echo $total_options . ' گزینه فعال';
                            } else {
                                echo '0 گزینه';
                            }
                            ?>
                        </span>
                    </div>
                    
                    <?php
                    // پردازش فرم اگر ارسال شده
                    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_approval_settings'])) {
                        $dept = $_POST['department'];
                        $options_data = $_POST['options'] ?? [];
                        
                        // پردازش گزینه‌ها
                        $new_options = [];
                        foreach ($options_data as $option) {
                            if (!empty(trim($option['text']))) {
                                $id = $option['id'] ?? $dept . '_' . uniqid();
                                $new_options[] = [
                                    'id' => $id,
                                    'text' => trim($option['text']),
                                    'mandatory' => isset($option['mandatory']) && $option['mandatory'] == '1'
                                ];
                            }
                        }
                        
                        // ذخیره
                        $approval_settings[$dept]['options'] = $new_options;
                        if (saveData('approval-settings', $approval_settings)) {
                            $message = '<div class="success-highlight" style="margin: 15px 0;">✅ تنظیمات تأییدیه با موفقیت ذخیره شد</div>';
                            echo $message;
                        }
                    }
                    ?>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-top: 20px;">
                        <?php 
                        $approval_settings = loadData('approval-settings');
                        if (!$approval_settings) {
                            $approval_settings = [
                                'finance' => [
                                    'name' => 'مالی',
                                    'options' => [
                                        ['id' => 'f1', 'text' => 'مبلغ صحیح است', 'mandatory' => true],
                                        ['id' => 'f2', 'text' => 'کسر مالیات محاسبه شده', 'mandatory' => true],
                                        ['id' => 'f3', 'text' => 'تخفیف‌ها اعمال شده', 'mandatory' => false],
                                        ['id' => 'f4', 'text' => 'مبلغ با قرارداد مطابقت دارد', 'mandatory' => true],
                                        ['id' => 'f5', 'text' => 'برای پرداخت تأیید می‌شود', 'mandatory' => true]
                                    ]
                                ],
                                'warehouse' => [
                                    'name' => 'انبار',
                                    'options' => [
                                        ['id' => 'w1', 'text' => 'کالا/خدمت دریافت شد', 'mandatory' => true],
                                        ['id' => 'w2', 'text' => 'مشخصات فنی مطابقت دارد', 'mandatory' => true],
                                        ['id' => 'w3', 'text' => 'تعداد و مقدار صحیح است', 'mandatory' => true],
                                        ['id' => 'w4', 'text' => 'کنترل کیفیت انجام شد', 'mandatory' => true]
                                    ]
                                ]
                            ];
                        }
                        
                        foreach ($approval_settings as $dept_key => $department):
                        ?>
                        <div style="background: rgba(255,255,255,0.05); border-radius: 12px; padding: 20px; border: 1px solid rgba(255,255,255,0.1);">
                            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 20px;">
                                <div style="width: 40px; height: 40px; border-radius: 50%; background: <?php echo $dept_key == 'finance' ? 'linear-gradient(135deg, #34C759, #28a745)' : 'linear-gradient(135deg, #007AFF, #5856D6)'; ?>; display: flex; align-items: center; justify-content: center; color: white;">
                                    <i class="fas fa-<?php echo $dept_key == 'finance' ? 'money-bill-wave' : 'warehouse'; ?>"></i>
                                </div>
                                <h3 style="color: white; margin: 0; font-size: 18px;"><?php echo $department['name']; ?></h3>
                            </div>
                            
                            <form method="POST" action="save-approval-settings.php">
                                <input type="hidden" name="department" value="<?php echo $dept_key; ?>">
                                <input type="hidden" name="save_settings" value="1">
                                
                                <div id="options_<?php echo $dept_key; ?>" style="margin-bottom: 20px; max-height: 300px; overflow-y: auto; padding-right: 5px;">
                                    <?php foreach ($department['options'] as $index => $option): ?>
                                    <div style="display: flex; gap: 12px; margin-bottom: 12px; align-items: center; padding: 12px; background: rgba(255,255,255,0.03); border-radius: 8px; border: 1px solid rgba(255,255,255,0.05);">
                                        <input type="text" name="options[<?php echo $index; ?>][text]" 
                                               value="<?php echo htmlspecialchars($option['text']); ?>"
                                               class="form-control" required
                                               style="flex: 1; padding: 10px 14px; font-size: 14px;"
                                               placeholder="متن گزینه...">
                                        
                                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                            <input type="checkbox" name="options[<?php echo $index; ?>][mandatory]" 
                                                   value="1" <?php echo $option['mandatory'] ? 'checked' : ''; ?>
                                                   style="transform: scale(1.3);">
                                            <span style="color: <?php echo $option['mandatory'] ? '#FF6B6B' : '#51CF66'; ?>; font-size: 13px; font-weight: 600;">
                                                <?php echo $option['mandatory'] ? 'اجباری' : 'اختیاری'; ?>
                                            </span>
                                        </label>
                                        
                                        <input type="hidden" name="options[<?php echo $index; ?>][id]" value="<?php echo $option['id']; ?>">
                                        
                                        <button type="button" class="btn btn-danger btn-small" 
                                                onclick="this.parentElement.remove()"
                                                style="padding: 8px 12px; min-width: 40px;">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <div style="display: flex; gap: 10px; margin-top: 15px;">
                                    <button type="button" class="btn btn-outline" 
                                            onclick="addApprovalOption('<?php echo $dept_key; ?>')"
                                            style="flex: 1;">
                                        <i class="fas fa-plus"></i> افزودن گزینه جدید
                                    </button>
                                    <button type="submit" class="btn btn-success" style="flex: 1;">
                                        <i class="fas fa-save"></i> ذخیره تغییرات
                                    </button>
                                </div>
                            </form>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- آمار تأییدیه‌ها -->
                    <div style="margin-top: 30px; padding: 20px; background: rgba(255,255,255,0.03); border-radius: 10px; border: 1px solid rgba(255,255,255,0.05);">
                        <h4 style="color: white; margin-bottom: 15px; display: flex; align-items: center; gap: 10px;">
                            <i class="fas fa-chart-line"></i>
                            آمار تأییدیه‌های ثبت شده
                        </h4>
                        
                        <?php
                        $all_approvals = loadData('invoice-approvals');
                        if ($all_approvals && count($all_approvals) > 0):
                            $total_approvals = count($all_approvals);
                            $recent_approvals = array_slice(array_reverse($all_approvals), 0, 5);
                            
                            // آمار بر اساس واحد
                            $stats = [];
                            foreach ($all_approvals as $approval) {
                                $dept = $approval['department'] ?? 'نامشخص';
                                if (!isset($stats[$dept])) {
                                    $stats[$dept] = 0;
                                }
                                $stats[$dept]++;
                            }
                        ?>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                            <div>
                                <h5 style="color: rgba(255,255,255,0.8); font-size: 14px; margin-bottom: 10px;">آمار کلی:</h5>
                                <div style="background: rgba(255,255,255,0.05); border-radius: 8px; padding: 15px;">
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                                        <span style="color: rgba(255,255,255,0.7);">کل تأییدیه‌ها:</span>
                                        <strong style="color: white; font-size: 18px;"><?php echo $total_approvals; ?></strong>
                                    </div>
                                    <?php foreach ($stats as $dept => $count): ?>
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px;">
                                        <span style="color: rgba(255,255,255,0.7);"><?php echo $dept; ?>:</span>
                                        <span style="color: #4a9eff;"><?php echo $count; ?></span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <div>
                                <h5 style="color: rgba(255,255,255,0.8); font-size: 14px; margin-bottom: 10px;">آخرین تأییدیه‌ها:</h5>
                                <div style="max-height: 200px; overflow-y: auto;">
                                    <?php foreach ($recent_approvals as $approval): 
                                        $user = getUser($approval['user_id']);
                                    ?>
                                    <div style="background: rgba(255,255,255,0.03); border-radius: 6px; padding: 10px; margin-bottom: 8px; border-left: 3px solid #4a9eff;">
                                        <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                            <strong style="color: white; font-size: 13px;"><?php echo $user ? $user['username'] : 'کاربر'; ?></strong>
                                            <span style="color: rgba(255,255,255,0.5); font-size: 11px;">
                                                <?php echo convertToJalali($approval['timestamp']); ?>
                                            </span>
                                        </div>
                                        <div style="color: rgba(255,255,255,0.7); font-size: 12px;">
                                            <?php echo $approval['department']; ?>
                                            <?php if (!empty($approval['notes'])): ?>
                                            - "<?php echo substr($approval['notes'], 0, 30); ?>..."
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <?php else: ?>
                        <div style="text-align: center; padding: 20px; color: rgba(255,255,255,0.5);">
                            <i class="fas fa-inbox" style="font-size: 40px; margin-bottom: 10px; opacity: 0.5;"></i>
                            <p>هنوز تأییدیه‌ای ثبت نشده است</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- ========== تب: گزارشات ========== -->
            <div id="tab-reports" class="tab-content">
                <!-- گزارشات سیستم -->
                <div class="table-container fade-in">
                    <div class="table-header">
                        <h2><i class="fas fa-chart-pie"></i> گزارشات سیستم</h2>
                    </div>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 30px;">
                        <div>
                            <h4 style="color: white; margin-bottom: 20px; font-size: 18px;">📊 آمار فاکتورها</h4>
                            <div class="stats-grid">
                                <p>کل فاکتورها: <strong><?php echo $total_invoices; ?></strong></p>
                                <p>در انتظار: <strong><?php echo $pending_invoices; ?></strong></p>
                                <p>تکمیل شده: <strong><?php echo $completed_invoices; ?></strong></p>
                                <?php
                                $in_progress_invoices = count(array_filter($invoices, function($inv) {
                                    return $inv['status'] === 'in-progress';
                                }));
                                $referred_invoices = count(array_filter($invoices, function($inv) {
                                    return $inv['status'] === 'referred';
                                }));
                                ?>
                                <p>در حال پیگیری: <strong><?php echo $in_progress_invoices; ?></strong></p>
                                <p>ارجاع شده: <strong><?php echo $referred_invoices; ?></strong></p>
                            </div>
                        </div>
                        
                        <div>
                            <h4 style="color: white; margin-bottom: 20px; font-size: 18px;">👥 آمار کاربران</h4>
                            <div class="stats-grid">
                                <p>کاربران فعال: <strong><?php echo $active_users; ?></strong></p>
                                <p>کاربران غیرفعال: <strong><?php echo count($users) - $active_users; ?></strong></p>
                                <p>کاربران ادمین: <strong><?php echo $admin_users; ?></strong></p>
                                <?php
                                $users_with_create_access = count(array_filter($users, function($user) {
                                    return isset($user['can_create_invoice']) && $user['can_create_invoice'];
                                }));
                                $users_with_referral_access = count(array_filter($users, function($user) {
                                    return !isset($user['can_receive_referral']) || $user['can_receive_referral'];
                                }));
                                ?>
                                <p>دسترسی ثبت فاکتور: <strong><?php echo $users_with_create_access; ?></strong></p>
                                <p>دسترسی دریافت ارجاع: <strong><?php echo $users_with_referral_access; ?></strong></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- آمار سریع -->
                <div class="dashboard-cards">
                    <div class="stat-card fade-in">
                        <div class="stat-icon blue">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $active_users; ?></h3>
                            <p>کاربران فعال</p>
                        </div>
                    </div>
                    
                    <div class="stat-card fade-in">
                        <div class="stat-icon green">
                            <i class="fas fa-file-invoice"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $total_invoices; ?></h3>
                            <p>کل فاکتورها</p>
                        </div>
                    </div>
                    
                    <div class="stat-card fade-in">
                        <div class="stat-icon orange">
                            <i class="fas fa-building"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo count($companies); ?></h3>
                            <p>شرکت‌ها</p>
                        </div>
                    </div>
                    
                    <div class="stat-card fade-in">
                        <div class="stat-icon purple">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $completed_invoices; ?></h3>
                            <p>تکمیل شده</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Overlay for mobile -->
    <div class="overlay" id="overlay"></div>

    <!-- ========== مودال مدیریت زنجیره‌ها ========== -->

    <!-- مودال افزودن سرپرست -->
    <div id="addSupervisorModal" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h3><i class="fas fa-user-plus"></i> افزودن سرپرست جدید</h3>
                <button class="close-modal" onclick="closeModal('addSupervisorModal')">×</button>
            </div>
            <form id="addSupervisorForm" onsubmit="return addSupervisor()">
                <div class="form-group" style="margin-bottom: 20px;">
                    <label>انتخاب کاربر:</label>
                    <select id="newSupervisorId" class="form-control" required>
                        <option value="">انتخاب کاربر</option>
                        <?php 
                        $eligible_supervisors = array_filter($users, function($user) {
                            return $user['is_active'] && 
                                   $user['role'] !== 'admin' && 
                                   (!isset($user['is_supervisor']) || !$user['is_supervisor']);
                        });
                        foreach ($eligible_supervisors as $user): ?>
                            <option value="<?php echo $user['id']; ?>">
                                <?php echo $user['username']; ?> (<?php echo $user['department']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group" style="margin-bottom: 20px;">
                    <label>دسترسی‌ها:</label>
                    <div style="display: grid; gap: 10px; margin-top: 10px;">
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                            <input type="checkbox" name="permissions[]" value="view_all" checked style="transform: scale(1.3);">
                            <span style="color: white; font-size: 14px;">مشاهده همه زنجیره‌ها</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                            <input type="checkbox" name="permissions[]" value="force_approve" checked style="transform: scale(1.3);">
                            <span style="color: white; font-size: 14px;">اجبار تأیید مرحله</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                            <input type="checkbox" name="permissions[]" value="skip_stage" checked style="transform: scale(1.3);">
                            <span style="color: white; font-size: 14px;">رد کردن مرحله</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                            <input type="checkbox" name="permissions[]" value="delegate" checked style="transform: scale(1.3);">
                            <span style="color: white; font-size: 14px;">تفویض اختیار</span>
                        </label>
                    </div>
                </div>
                
                <div style="display: flex; gap: 15px; margin-top: 30px;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> ذخیره سرپرست
                    </button>
                    <button type="button" class="btn btn-outline" onclick="closeModal('addSupervisorModal')">
                        <i class="fas fa-times"></i> انصراف
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- مودال جزئیات زنجیره -->
    <div id="chainDetailsModal" class="modal">
        <div class="modal-content" style="max-width: 800px;">
            <div class="modal-header">
                <h3><i class="fas fa-info-circle"></i> جزئیات زنجیره تأیید</h3>
                <button class="close-modal" onclick="closeModal('chainDetailsModal')">×</button>
            </div>
            <div style="padding: 20px;">
                <div id="chainDetailsContent">
                    <!-- محتوای جزئیات -->
                </div>
            </div>
        </div>
    </div>

    <!-- مودال ویرایش زنجیره -->
    <div id="editChainModal" class="modal">
        <div class="modal-content" style="max-width: 700px;">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> ویرایش زنجیره تأیید</h3>
                <button class="close-modal" onclick="closeModal('editChainModal')">×</button>
            </div>
            <form id="editChainForm" onsubmit="return updateChain()">
                <input type="hidden" id="editChainId">
                
                <div style="padding: 15px; background: rgba(255,193,7,0.1); border-radius: 10px; margin-bottom: 20px;">
                    <div style="color: white; font-size: 14px; margin-bottom: 5px; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>توجه:</strong>
                    </div>
                    <div style="color: rgba(255,255,255,0.8); font-size: 12px;">
                        ویرایش زنجیره‌های فعال ممکن است فرآیند تأیید را مختل کند. این کار را تنها در صورت لزوم انجام دهید.
                    </div>
                </div>
                
                <div id="editChainContent">
                    <!-- فرم ویرایش اینجا لود می‌شود -->
                </div>
                
                <div style="display: flex; gap: 15px; margin-top: 30px;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> ذخیره تغییرات
                    </button>
                    <button type="button" class="btn btn-outline" onclick="closeModal('editChainModal')">
                        <i class="fas fa-times"></i> انصراف
                    </button>
                </div>
            </form>
        </div>
    </div>

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

        // Search functionality
        const searchInput = document.querySelector('.search-box input');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                const searchTerm = e.target.value.toLowerCase();
                console.log('جستجو در پنل مدیریت:', searchTerm);
                // Add your search logic here
            });
        }

        // Menu items click handler
        const menuItems = document.querySelectorAll('.sidebar-nav a');
        if (menuItems.length > 0) {
            menuItems.forEach(item => {
                item.addEventListener('click', () => {
                    // Close sidebar on mobile after clicking
                    if (window.innerWidth <= 768) {
                        sidebar.classList.remove('active');
                        overlay.classList.remove('active');
                    }
                });
            });
        }

        // Confirm delete actions
document.addEventListener('DOMContentLoaded', function() {
    const deleteLinks = document.querySelectorAll('a[href*="delete_"]');
    deleteLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            const itemType = this.href.includes('delete_company') ? 'شرکت' : 
                           this.href.includes('delete_store') ? 'فروشگاه' : 'کارگاه';
            if (!confirm(`آیا از حذف این ${itemType} اطمینان دارید؟\nاین عمل غیرقابل بازگشت است!`)) {
                e.preventDefault();
            }
        });
    });

    // Initialize animations
    const cards = document.querySelectorAll('.stat-card, .form-container, .table-container');
    cards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
    });

    // مقداردهی اولیه تب‌ها
    const defaultTab = window.location.hash.substring(1) || 'companies';
    switchTab(defaultTab);
    
    // اسکرول به تب در صورت وجود hash
    if (window.location.hash) {
        setTimeout(() => {
            document.querySelector(`[data-tab="${defaultTab}"]`).scrollIntoView({
                behavior: 'smooth',
                block: 'center'
            });
        }, 500);
    }

    console.log('🎉 پنل مدیریت با موفقیت بارگذاری شد');
});
        
        // تابع برای افزودن گزینه تأییدیه جدید
        function addApprovalOption(department) {
            const container = document.getElementById(`options_${department}`);
            const index = container.children.length;
            
            const newOption = document.createElement('div');
            newOption.style.cssText = 'display: flex; gap: 12px; margin-bottom: 12px; align-items: center; padding: 12px; background: rgba(255,255,255,0.03); border-radius: 8px; border: 1px solid rgba(255,255,255,0.05);';
            newOption.innerHTML = `
                <input type="text" name="options[${index}][text]" 
                       class="form-control" required
                       style="flex: 1; padding: 10px 14px; font-size: 14px;"
                       placeholder="متن گزینه جدید...">
                
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" name="options[${index}][mandatory]" 
                           value="1"
                           style="transform: scale(1.3);">
                    <span style="color: #51CF66; font-size: 13px; font-weight: 600;">
                        اختیاری
                    </span>
                </label>
                
                <input type="hidden" name="options[${index}][id]" value="${department}_${Date.now()}">
                
                <button type="button" class="btn btn-danger btn-small" 
                        onclick="this.parentElement.remove()"
                        style="padding: 8px 12px; min-width: 40px;">
                    <i class="fas fa-trash"></i>
                </button>
            `;
            container.appendChild(newOption);
        }
        
        /***************************************************************
         * بخش جدید: مدیریت تب‌ها و زنجیره‌های تأیید
         ***************************************************************/

        // متغیرهای مدیریت تب
        let currentTab = 'companies';

        // تغییر تب
        function switchTab(tabName) {
            // غیرفعال کردن تب قبلی
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // فعال کردن تب جدید
            document.querySelector(`[data-tab="${tabName}"]`).classList.add('active');
            document.getElementById(`tab-${tabName}`).classList.add('active');
            currentTab = tabName;
            
            // اگر تب زنجیره‌ها فعال شد، لیست را بارگذاری کن
            if (tabName === 'approval-chains') {
                loadChainsList();
                loadSupervisorsList();
            }
        }

        // بارگذاری لیست زنجیره‌ها
        function loadChainsList() {
            fetch('get-chains-list.php')
                .then(response => response.json())
                .then(chains => {
                    const container = document.getElementById('chainsList');
                    const countSpan = document.getElementById('chainsCount');
                    
                    if (!chains || chains.length === 0) {
                        container.innerHTML = `
                            <div style="text-align: center; padding: 40px; color: rgba(255,255,255,0.7);">
                                <div style="font-size: 48px; margin-bottom: 20px; opacity: 0.5;">
                                    ⛓️
                                </div>
                                <h3 style="color: white; margin-bottom: 10px;">هنوز زنجیره‌ای ایجاد نشده است</h3>
                                <p style="margin-bottom: 25px;">برای ایجاد زنجیره از صفحه مدیریت فاکتورها اقدام کنید</p>
                                <button onclick="window.location.href='invoice-management.php'" class="btn btn-primary">
                                    <i class="fas fa-external-link-alt"></i> برو به فاکتورها
                                </button>
                            </div>
                        `;
                        countSpan.textContent = '0 زنجیره';
                        return;
                    }
                    
                    countSpan.textContent = chains.length + ' زنجیره';
                    
                    let html = `
                        <div style="overflow-x: auto;">
                            <table style="width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr style="background: rgba(255,255,255,0.1);">
                                        <th style="padding: 12px; text-align: right; color: white; font-size: 14px;">فاکتور</th>
                                        <th style="padding: 12px; text-align: right; color: white; font-size: 14px;">مراحل</th>
                                        <th style="padding: 12px; text-align: right; color: white; font-size: 14px;">پیشرفت</th>
                                        <th style="padding: 12px; text-align: right; color: white; font-size: 14px;">وضعیت</th>
                                        <th style="padding: 12px; text-align: right; color: white; font-size: 14px;">سرپرست</th>
                                        <th style="padding: 12px; text-align: right; color: white; font-size: 14px;">زمان ایجاد</th>
                                        <th style="padding: 12px; text-align: right; color: white; font-size: 14px;">عملیات</th>
                                    </tr>
                                </thead>
                                <tbody>
                    `;
                    
                    chains.forEach(chain => {
                        const progress = chain.progress || 0;
                        const supervisor = chain.supervisor_id ? getUserById(chain.supervisor_id) : null;
                        
                        html += `
                            <tr style="border-bottom: 1px solid rgba(255,255,255,0.1);">
                                <td style="padding: 12px;">
                                    <div style="font-weight: 600; color: white;">${chain.invoice_number}</div>
                                    <div style="font-size: 12px; color: rgba(255,255,255,0.6);">${chain.company_name}</div>
                                </td>
                                <td style="padding: 12px; color: white; text-align: center;">
                                    ${chain.total_stages} مرحله
                                </td>
                                <td style="padding: 12px;">
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <div style="width: 80px; height: 8px; background: rgba(255,255,255,0.1); border-radius: 4px; overflow: hidden;">
                                            <div style="height: 100%; width: ${progress}%; background: linear-gradient(90deg, #4a9eff, #6f42c1);"></div>
                                        </div>
                                        <span style="color: white; font-weight: 600; font-size: 14px;">${progress}%</span>
                                    </div>
                                </td>
                                <td style="padding: 12px;">
                                    <span style="background: ${chain.status === 'completed' ? 'rgba(52,199,89,0.2)' : 'rgba(74,158,255,0.2)'}; 
                                          color: ${chain.status === 'completed' ? '#34C759' : '#4a9eff'}; 
                                          padding: 6px 12px; border-radius: 20px; font-size: 12px; border: 1px solid ${chain.status === 'completed' ? 'rgba(52,199,89,0.3)' : 'rgba(74,158,255,0.3)'};">
                                        ${chain.status === 'completed' ? '✅ تکمیل' : '⏳ فعال'}
                                    </span>
                                </td>
                                <td style="padding: 12px;">
                                    ${supervisor ? `
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <div style="width: 28px; height: 28px; border-radius: 50%; background: linear-gradient(135deg, #4a9eff, #6f42c1); display: flex; align-items: center; justify-content: center; color: white; font-size: 11px; font-weight: bold;">
                                                ${supervisor.username.charAt(0).toUpperCase()}
                                            </div>
                                            <span style="color: white; font-size: 13px;">${supervisor.username}</span>
                                        </div>
                                    ` : '<span style="color: rgba(255,255,255,0.5); font-size: 12px;">بدون سرپرست</span>'}
                                </td>
                                <td style="padding: 12px; font-size: 12px; color: rgba(255,255,255,0.7);">
                                    ${convertTimestampToJalali(chain.created_at)}
                                </td>
                                <td style="padding: 12px;">
                                    <div style="display: flex; gap: 6px;">
                                        <button onclick="viewChainDetails('${chain.id}')" class="btn btn-outline btn-small">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button onclick="editChain('${chain.id}')" class="btn btn-outline btn-small">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button onclick="deleteChain('${chain.id}', '${chain.invoice_number}')" class="btn btn-danger btn-small">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        `;
                    });
                    
                    html += `
                                </tbody>
                            </table>
                        </div>
                    `;
                    
                    container.innerHTML = html;
                })
                .catch(error => {
                    console.error('Error loading chains:', error);
                });
        }

        // بارگذاری لیست سرپرستان
        function loadSupervisorsList() {
            fetch('get-supervisors-list.php')
                .then(response => response.json())
                .then(supervisors => {
                    const container = document.getElementById('supervisorsList');
                    
                    if (!supervisors || supervisors.length === 0) {
                        container.innerHTML = `
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 30px; color: rgba(255,255,255,0.7);">
                                    <i class="fas fa-user-tie" style="font-size: 24px; margin-bottom: 10px; display: block; opacity: 0.5;"></i>
                                    هنوز سرپرستی تعریف نشده است
                                </td>
                            </tr>
                        `;
                        return;
                    }
                    
                    let html = '';
                    supervisors.forEach(supervisor => {
                        html += `
                            <tr>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <div style="width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg, #4a9eff, #6f42c1); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 14px;">
                                            ${supervisor.username.charAt(0).toUpperCase()}
                                        </div>
                                        <div>
                                            <strong style="color: white; font-size: 14px;">${supervisor.username}</strong>
                                            <div style="font-size: 12px; color: rgba(255,255,255,0.6);">${supervisor.email || 'بدون ایمیل'}</div>
                                        </div>
                                    </div>
                                </td>
                                <td style="color: white; font-size: 14px;">${supervisor.department}</td>
                                <td style="text-align: center; color: white; font-weight: 600;">${supervisor.chain_count || 0}</td>
                                <td style="text-align: center;">
                                    <span style="background: rgba(52,199,89,0.2); color: #34C759; padding: 4px 8px; border-radius: 12px; font-size: 12px;">
                                        ${supervisor.completed_chains || 0}
                                    </span>
                                </td>
                                <td style="text-align: center;">
                                    <span style="background: rgba(74,158,255,0.2); color: #4a9eff; padding: 4px 8px; border-radius: 12px; font-size: 12px;">
                                        ${supervisor.active_chains || 0}
                                    </span>
                                </td>
                                <td style="text-align: center;">
                                    <span style="background: rgba(255,107,107,0.2); color: #ff6b6b; padding: 4px 8px; border-radius: 12px; font-size: 12px;">
                                        ${supervisor.delayed_chains || 0}
                                    </span>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 6px;">
                                        <button onclick="viewSupervisorChains('${supervisor.id}')" class="btn btn-outline btn-small">
                                            <i class="fas fa-list"></i>
                                        </button>
                                        <button onclick="removeSupervisor('${supervisor.id}', '${supervisor.username}')" class="btn btn-danger btn-small">
                                            <i class="fas fa-user-minus"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        `;
                    });
                    
                    container.innerHTML = html;
                })
                .catch(error => {
                    console.error('Error loading supervisors:', error);
                });
        }

        // مشاهده جزئیات زنجیره
        function viewChainDetails(chainId) {
            fetch(`get-chain-details-admin.php?id=${chainId}`)
                .then(response => response.json())
                .then(chain => {
                    const container = document.getElementById('chainDetailsContent');
                    
                    let html = `
                        <div style="margin-bottom: 25px;">
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                                <div style="background: rgba(255,255,255,0.05); padding: 15px; border-radius: 10px;">
                                    <div style="color: rgba(255,255,255,0.7); font-size: 13px; margin-bottom: 5px;">فاکتور</div>
                                    <div style="color: white; font-size: 18px; font-weight: 600;">${chain.invoice_number}</div>
                                </div>
                                <div style="background: rgba(255,255,255,0.05); padding: 15px; border-radius: 10px;">
                                    <div style="color: rgba(255,255,255,0.7); font-size: 13px; margin-bottom: 5px;">وضعیت</div>
                                    <div>
                                        <span style="background: ${chain.status === 'completed' ? 'rgba(52,199,89,0.2)' : 'rgba(74,158,255,0.2)'}; 
                                              color: ${chain.status === 'completed' ? '#34C759' : '#4a9eff'}; 
                                              padding: 6px 12px; border-radius: 20px; font-size: 13px; border: 1px solid ${chain.status === 'completed' ? 'rgba(52,199,89,0.3)' : 'rgba(74,158,255,0.3)'};">
                                            ${chain.status === 'completed' ? '✅ تکمیل شده' : '⏳ در حال بررسی'}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div style="margin-bottom: 25px;">
                            <h4 style="color: white; margin-bottom: 15px; font-size: 16px;">📊 پیشرفت زنجیره</h4>
                            <div style="background: rgba(255,255,255,0.05); border-radius: 10px; padding: 20px;">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                                    <span style="color: rgba(255,255,255,0.8);">پیشرفت کلی:</span>
                                    <span style="color: #4a9eff; font-weight: 600; font-size: 16px;">${chain.progress_percentage}%</span>
                                </div>
                                <div style="height: 10px; background: rgba(255,255,255,0.1); border-radius: 5px; overflow: hidden; margin-bottom: 20px;">
                                    <div style="height: 100%; width: ${chain.progress_percentage}%; background: linear-gradient(90deg, #4a9eff, #6f42c1);"></div>
                                </div>
                                
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 15px;">
                                    <div style="text-align: center;">
                                        <div style="font-size: 24px; color: white; font-weight: 800;">${chain.current_stage}</div>
                                        <div style="color: rgba(255,255,255,0.7); font-size: 12px;">مرحله جاری</div>
                                    </div>
                                    <div style="text-align: center;">
                                        <div style="font-size: 24px; color: white; font-weight: 800;">${chain.total_stages}</div>
                                        <div style="color: rgba(255,255,255,0.7); font-size: 12px;">کل مراحل</div>
                                    </div>
                                    <div style="text-align: center;">
                                        <div style="font-size: 24px; color: white; font-weight: 800;">${chain.completed_stages}</div>
                                        <div style="color: rgba(255,255,255,0.7); font-size: 12px;">تکمیل شده</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div style="margin-bottom: 25px;">
                            <h4 style="color: white; margin-bottom: 15px; font-size: 16px;">👥 مراحل زنجیره</h4>
                    `;
                    
                    chain.stages.forEach((stage, index) => {
                        const isCurrent = index === chain.current_stage;
                        const isCompleted = index < chain.current_stage;
                        
                        html += `
                            <div style="margin-bottom: 12px; padding: 15px; background: ${isCurrent ? 'rgba(74,158,255,0.1)' : (isCompleted ? 'rgba(52,199,89,0.1)' : 'rgba(255,255,255,0.05)')}; 
                                 border: 1px solid ${isCurrent ? 'rgba(74,158,255,0.3)' : (isCompleted ? 'rgba(52,199,89,0.3)' : 'rgba(255,255,255,0.1)')}; 
                                 border-radius: 8px;">
                                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <div style="width: 30px; height: 30px; border-radius: 50%; 
                                             background: ${isCurrent ? '#4a9eff' : (isCompleted ? '#34C759' : 'rgba(255,255,255,0.2)')}; 
                                             display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;">
                                            ${index + 1}
                                        </div>
                                        <div>
                                            <div style="font-weight: 600; color: white;">${stage.name || `مرحله ${index + 1}`}</div>
                                            ${isCurrent ? '<span style="color: #4a9eff; font-size: 12px;">(مرحله فعلی)</span>' : 
                                              isCompleted ? '<span style="color: #34C759; font-size: 12px;">(تکمیل شده)</span>' : ''}
                                        </div>
                                    </div>
                                    <div style="color: rgba(255,255,255,0.7); font-size: 13px;">
                                        ${stage.deadline_hours} ساعت مهلت
                                    </div>
                                </div>
                                <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                        `;
                        
                        stage.users.forEach(userId => {
                            const user = getUserById(userId);
                            if (user) {
                                html += `
                                    <div style="display: flex; align-items: center; gap: 6px; padding: 6px 10px; 
                                         background: rgba(255,255,255,0.1); border-radius: 15px; font-size: 13px;">
                                        <div style="width: 20px; height: 20px; border-radius: 50%; 
                                             background: linear-gradient(135deg, #4a9eff, #6f42c1); 
                                             display: flex; align-items: center; justify-content: center; color: white; font-size: 10px;">
                                            ${user.username.charAt(0).toUpperCase()}
                                        </div>
                                        ${user.username}
                                    </div>
                                `;
                            }
                        });
                        
                        html += `
                                </div>
                            </div>
                        `;
                    });
                    
                    html += `
                        </div>
                        
                        <div style="display: flex; gap: 10px; margin-top: 30px;">
                            <button onclick="window.open('get-invoice-details.php?id=${chain.invoice_id}', '_blank')" 
                                    class="btn btn-primary">
                                <i class="fas fa-external-link-alt"></i> مشاهده فاکتور
                            </button>
                            <button onclick="closeModal('chainDetailsModal')" class="btn btn-outline">
                                <i class="fas fa-times"></i> بستن
                            </button>
                        </div>
                    `;
                    
                    container.innerHTML = html;
                    document.getElementById('chainDetailsModal').classList.add('active');
                    document.getElementById('overlay').classList.add('active');
                })
                .catch(error => {
                    console.error('Error loading chain details:', error);
                    alert('خطا در بارگذاری جزئیات زنجیره');
                });
        }

        // باز کردن مودال افزودن سرپرست
        function openAddSupervisorModal() {
            document.getElementById('addSupervisorModal').classList.add('active');
            document.getElementById('overlay').classList.add('active');
        }

        // افزودن سرپرست
        function addSupervisor() {
            const userId = document.getElementById('newSupervisorId').value;
            const permissions = Array.from(document.querySelectorAll('input[name="permissions[]"]:checked'))
                .map(input => input.value);
            
            if (!userId) {
                alert('لطفاً کاربری را انتخاب کنید');
                return false;
            }
            
            fetch('add-supervisor.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    user_id: userId,
                    permissions: permissions
                })
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    alert('✅ سرپرست با موفقیت اضافه شد');
                    closeModal('addSupervisorModal');
                    loadSupervisorsList();
                } else {
                    alert('❌ ' + (result.message || 'خطا در افزودن سرپرست'));
                }
            })
            .catch(error => {
                alert('خطا در ارتباط با سرور');
            });
            
            return false;
        }

        // حذف سرپرست
        function removeSupervisor(userId, username) {
            if (confirm(`آیا از حذف سرپرستی ${username} اطمینان دارید؟\nاین عمل دسترسی‌های سرپرستی را حذف می‌کند.`)) {
                fetch(`remove-supervisor.php?id=${userId}`)
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        alert('✅ سرپرست با موفقیت حذف شد');
                        loadSupervisorsList();
                    } else {
                        alert('❌ ' + result.message);
                    }
                })
                .catch(error => {
                    alert('خطا در حذف سرپرست');
                });
            }
        }

        // حذف زنجیره
        function deleteChain(chainId, invoiceNumber) {
            if (confirm(`⚠️ آیا از حذف زنجیره فاکتور ${invoiceNumber} اطمینان دارید؟\nاین عمل غیرقابل بازگشت است و تمام تاریخچه تأییدیه‌ها حذف می‌شود.`)) {
                fetch(`delete-chain.php?id=${chainId}`, {
                    method: 'DELETE'
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        alert('✅ زنجیره با موفقیت حذف شد');
                        loadChainsList();
                    } else {
                        alert('❌ ' + result.message);
                    }
                })
                .catch(error => {
                    alert('خطا در حذف زنجیره');
                });
            }
        }

        // ویرایش زنجیره
        function editChain(chainId) {
            document.getElementById('editChainId').value = chainId;
            
            fetch(`get-chain-edit.php?id=${chainId}`)
                .then(response => response.json())
                .then(chain => {
                    const container = document.getElementById('editChainContent');
                    
                    let html = `
                        <div class="form-group" style="margin-bottom: 15px;">
                            <label>سرپرست:</label>
                            <select id="editSupervisorId" class="form-control">
                                <option value="">بدون سرپرست</option>
                    `;
                    
                    // لیست کاربران
                    <?php 
                    $all_users = array_filter($users, function($user) {
                        return $user['is_active'];
                    });
                    foreach ($all_users as $user): ?>
                        html += `<option value="<?php echo $user['id']; ?>"><?php echo $user['username']; ?> (<?php echo $user['department']; ?>)</option>`;
                    <?php endforeach; ?>
                    
                    html += `
                            </select>
                        </div>
                        
                        <div style="margin-top: 20px;">
                            <h4 style="color: white; margin-bottom: 15px; font-size: 16px;">مراحل زنجیره</h4>
                    `;
                    
                    chain.stages.forEach((stage, index) => {
                        html += `
                            <div style="margin-bottom: 15px; padding: 15px; background: rgba(255,255,255,0.05); border-radius: 8px;">
                                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
                                    <h5 style="color: white; margin: 0; font-size: 15px;">${stage.name || `مرحله ${index + 1}`}</h5>
                                    ${chain.status !== 'completed' ? `<button type="button" onclick="removeStage(${index})" class="btn btn-danger btn-small"><i class="fas fa-trash"></i></button>` : ''}
                                </div>
                                
                                <div class="form-group" style="margin-bottom: 10px;">
                                    <label style="font-size: 13px;">نام مرحله:</label>
                                    <input type="text" id="stage_name_${index}" value="${stage.name || ''}" class="form-control" style="font-size: 14px;">
                                </div>
                                
                                <div class="form-group" style="margin-bottom: 10px;">
                                    <label style="font-size: 13px;">مهلت (ساعت):</label>
                                    <input type="number" id="stage_deadline_${index}" value="${stage.deadline_hours || 72}" class="form-control" style="font-size: 14px;">
                                </div>
                                
                                <div class="form-group">
                                    <label style="font-size: 13px;">کاربران:</label>
                                    <select id="stage_users_${index}" multiple class="form-control" style="font-size: 14px; height: 100px;">
                        `;
                        
                        // لیست کاربران برای انتخاب
                        <?php foreach ($all_users as $user): ?>
                            html += `<option value="<?php echo $user['id']; ?>"><?php echo $user['username']; ?> (<?php echo $user['department']; ?>)</option>`;
                        <?php endforeach; ?>
                        
                        html += `
                                    </select>
                                </div>
                            </div>
                        `;
                    });
                    
                    html += `
                        </div>
                    `;
                    
                    container.innerHTML = html;
                    
                    // تنظیم مقادیر فعلی
                    document.getElementById('editSupervisorId').value = chain.supervisor_id || '';
                    
                    chain.stages.forEach((stage, index) => {
                        stage.users.forEach(userId => {
                            const option = document.getElementById(`stage_users_${index}`).querySelector(`option[value="${userId}"]`);
                            if (option) option.selected = true;
                        });
                    });
                })
                .catch(error => {
                    console.error('Error loading chain for edit:', error);
                    alert('خطا در بارگذاری زنجیره برای ویرایش');
                });
            
            document.getElementById('editChainModal').classList.add('active');
            document.getElementById('overlay').classList.add('active');
            return false;
        }

        // حذف مرحله از ویرایش
        function removeStage(stageIndex) {
            if (confirm('آیا از حذف این مرحله اطمینان دارید؟')) {
                // حذف از DOM
                const stageDiv = document.querySelector(`#editChainContent > div > div:nth-child(${stageIndex + 2})`);
                if (stageDiv) stageDiv.remove();
            }
        }

        // بروزرسانی زنجیره
        function updateChain() {
            const chainId = document.getElementById('editChainId').value;
            const supervisorId = document.getElementById('editSupervisorId').value;
            
            // جمع‌آوری اطلاعات مراحل
            const stages = [];
            const stageElements = document.querySelectorAll('#editChainContent > div > div:not(:first-child)');
            
            stageElements.forEach((stageDiv, index) => {
                const name = stageDiv.querySelector(`#stage_name_${index}`)?.value || `مرحله ${index + 1}`;
                const deadline = stageDiv.querySelector(`#stage_deadline_${index}`)?.value || 72;
                const usersSelect = stageDiv.querySelector(`#stage_users_${index}`);
                
                const users = Array.from(usersSelect?.selectedOptions || []).map(option => option.value);
                
                if (users.length > 0) {
                    stages.push({
                        name: name,
                        deadline_hours: parseInt(deadline),
                        users: users
                    });
                }
            });
            
            if (stages.length === 0) {
                alert('⚠️ لطفاً حداقل یک مرحله با کاربران معتبر تعریف کنید');
                return false;
            }
            
            fetch('update-chain.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    chain_id: chainId,
                    supervisor_id: supervisorId,
                    stages: stages
                })
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    alert('✅ زنجیره با موفقیت به‌روزرسانی شد');
                    closeModal('editChainModal');
                    loadChainsList();
                } else {
                    alert('❌ ' + (result.message || 'خطا در به‌روزرسانی زنجیره'));
                }
            })
            .catch(error => {
                alert('خطا در ارتباط با سرور');
            });
            
            return false;
        }

        // توابع کمکی
        function getUserById(userId) {
            // این تابع باید با AJAX کاربر را از سرور دریافت کند
            // در اینجا یک شبیه‌سازی ساده انجام می‌دهیم
            return {
                id: userId,
                username: 'کاربر ' + userId.substring(0, 4)
            };
        }

        function convertTimestampToJalali(timestamp) {
            // تابع تبدیل timestamp به تاریخ شمسی
            const date = new Date(timestamp * 1000);
            return date.toLocaleDateString('fa-IR');
        }

        function refreshChains() {
            loadChainsList();
            loadSupervisorsList();
            alert('✅ لیست‌ها به‌روزرسانی شدند');
        }

        function exportChainsData() {
            alert('📊 در حال آماده‌سازی خروجی Excel...');
            window.open('export-chains.php', '_blank');
        }

        function applyFilters() {
            const status = document.getElementById('filterStatus').value;
            const supervisor = document.getElementById('filterSupervisor').value;
            const stage = document.getElementById('filterStage').value;
            
            // اعمال فیلترها بر روی لیست
            alert(`فیلترها اعمال شدند:\nوضعیت: ${status}\nسرپرست: ${supervisor}\nمرحله: ${stage}`);
            loadChainsList();
        }

        function viewSupervisorChains(supervisorId) {
            window.open(`supervisor-chains.php?id=${supervisorId}`, '_blank');
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
            document.getElementById('overlay').classList.remove('active');
        }
    </script>
</body>
</html>