
<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// بارگذاری داده‌ها
$tax_transactions = loadData('tax-transactions');
$users = loadData('users');
$companies = loadData('companies');
$message = '';

// ایجاد تراکنش جدید
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_tax_transaction'])) {
    $deadline_days = intval($_POST['deadline_days']);
    $deadline_timestamp = time() + ($deadline_days * 24 * 60 * 60);
    
    // آپلود فایل اصلی
    $main_file_path = '';
    if (isset($_FILES['main_file']) && $_FILES['main_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['main_file'];
        $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '_main.' . $file_extension;
        $upload_path = UPLOAD_DIR . 'tax-system/' . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
            $main_file_path = $filename;
        }
    }
    
    // آپلود پیوست‌ها
    $attachments = [];
    if (isset($_FILES['attachments']) && is_array($_FILES['attachments']['name'])) {
        foreach ($_FILES['attachments']['name'] as $key => $name) {
            if ($_FILES['attachments']['error'][$key] === UPLOAD_ERR_OK) {
                $file = [
                    'name' => $_FILES['attachments']['name'][$key],
                    'type' => $_FILES['attachments']['type'][$key],
                    'tmp_name' => $_FILES['attachments']['tmp_name'][$key],
                    'error' => $_FILES['attachments']['error'][$key],
                    'size' => $_FILES['attachments']['size'][$key]
                ];
                
                $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = uniqid() . '_attachment.' . $file_extension;
                $upload_path = UPLOAD_DIR . 'tax-system/' . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                    $attachments[] = [
                        'filename' => $filename,
                        'original_name' => $file['name'],
                        'uploaded_at' => time()
                    ];
                }
            }
        }
    }
    
    $assigned_users = is_array($_POST['assigned_to']) ? $_POST['assigned_to'] : [$_POST['assigned_to']];
    
    $new_transaction = [
        'id' => uniqid(),
        'title' => trim($_POST['title']),
        'company' => $_POST['company'],
        'description' => trim($_POST['description']),
        'main_file' => [
            'filename' => $main_file_path,
            'original_name' => $_FILES['main_file']['name']
        ],
        'attachments' => $attachments,
        'deadline_days' => $deadline_days,
        'deadline_timestamp' => $deadline_timestamp,
        'priority' => $_POST['priority'],
        'status' => 'new',
        'created_by' => $_SESSION['user_id'],
        'assigned_to' => $assigned_users,
        'viewed_by' => [],
        'created_at' => time(),
        'updated_at' => time(),
        'history' => [
            [
                'action' => 'create',
                'user_id' => $_SESSION['user_id'],
                'timestamp' => time(),
                'description' => 'ایجاد درخواست جدید'
            ]
        ]
    ];
    
    $tax_transactions[] = $new_transaction;
    saveData('tax-transactions', $tax_transactions);
    
    // ارسال نوتیفیکیشن به کاربران
    foreach ($assigned_users as $user_id) {
        sendNotification(
            $user_id,
            "درخواست جدید سامانه مودیان: {$_POST['title']}",
            null
        );
    }
    
    $message = '<div class="success-highlight">✅ درخواست با موفقیت ایجاد و ارسال شد</div>';
    
    // بستن مودال و ریدایرکت
    echo "<script>
        setTimeout(function() {
            window.location.href = 'tax-system.php';
        }, 1500);
    </script>";
}

// علامت‌گذاری به عنوان مشاهده شده
if (isset($_GET['mark_viewed']) && isset($_GET['id'])) {
    $transaction_id = $_GET['id'];
    foreach ($tax_transactions as &$transaction) {
        if ($transaction['id'] === $transaction_id) {
            if (!in_array($_SESSION['user_id'], array_keys($transaction['viewed_by']))) {
                $transaction['viewed_by'][$_SESSION['user_id']] = time();
                $transaction['history'][] = [
                    'action' => 'view',
                    'user_id' => $_SESSION['user_id'],
                    'timestamp' => time(),
                    'description' => 'مشاهده فایل'
                ];
                
                // اطلاع به ارسال کننده
                sendNotification(
                    $transaction['created_by'],
                    "فایل شما توسط " . $_SESSION['username'] . " مشاهده شد",
                    null
                );
            }
            break;
        }
    }
    saveData('tax-transactions', $tax_transactions);
}

// فیلترها
$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';

// فیلتر کردن تراکنش‌ها - فقط نمایش به کاربران مجاز
$filtered_transactions = [];
foreach ($tax_transactions as $transaction) {
    $is_creator = $transaction['created_by'] === $_SESSION['user_id'];
    $is_assigned = in_array($_SESSION['user_id'], $transaction['assigned_to']);
    
    // اگر کاربر به این فایل دسترسی ندارد، اصلاً نمایش نده
    if (!$is_creator && !$is_assigned) {
        continue;
    }    

    // پرونده‌های لغو شده را حذف کن
    if ($transaction['status'] === 'cancelled') {
        continue;
    }

    // سپس فیلترهای دیگر را اعمال کن
    if ($filter === 'sent' && !$is_creator) continue;
    if ($filter === 'received' && !$is_assigned) continue;
    if ($filter === 'my' && !$is_creator && !$is_assigned) continue;
    
    // فیلتر بر اساس وضعیت فوری
    if ($filter === 'urgent') {
        $remaining_days = ceil(($transaction['deadline_timestamp'] - time()) / (24 * 60 * 60));
        if ($remaining_days > 3) continue;
    }
    
    // جستجو
    if ($search && !str_contains(strtolower($transaction['title']), strtolower($search)) && 
        !str_contains(strtolower($transaction['description']), strtolower($search))) {
        continue;
    }
    
    $filtered_transactions[] = $transaction;
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
    <title>سامانه مودیان - سیستم پیگیری فاکتور</title>
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

        .status-new {
            background: rgba(255, 149, 0, 0.2);
            color: #FF9500;
            border: 1px solid rgba(255, 149, 0, 0.3);
        }

        .status-in-progress {
            background: rgba(52, 199, 89, 0.2);
            color: #34C759;
            border: 1px solid rgba(52, 199, 89, 0.3);
        }

        .status-completed {
            background: rgba(52, 199, 89, 0.2);
            color: #34C759;
            border: 1px solid rgba(52, 199, 89, 0.3);
        }

        .status-cancelled {
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

        /* ========== MODAL ========== */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(10px);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(40px) saturate(180%);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: var(--radius);
            padding: 24px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            animation: modalAppear 0.3s ease;
            max-width: 90%;
            max-height: 90%;
            overflow-y: auto;
        }

        @keyframes modalAppear {
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
            margin-bottom: 20px;
        }

        .modal-header h3 {
            color: white;
            font-size: 20px;
            font-weight: 700;
        }

        .close-modal {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(20px);
            border: 0.5px solid rgba(255, 255, 255, 0.3);
            width: 36px;
            height: 36px;
            border-radius: 18px;
            cursor: pointer;
            font-size: 18px;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .close-modal:hover {
            background: rgba(255, 255, 255, 0.3);
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

        /* ========== TAX SYSTEM STYLES ========== */
        .deadline-warning {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
        }
        .deadline-normal { background: #28a745; color: white; }
        .deadline-warning { background: #ffc107; color: black; }
        .deadline-danger { background: #dc3545; color: white; }
        .file-list {
            background: rgba(255,255,255,0.05);
            padding: 10px;
            border-radius: 8px;
            margin: 10px 0;
        }
        .file-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px;
            margin: 5px 0;
            background: rgba(255,255,255,0.1);
            border-radius: 5px;
        }
        .user-checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px;
            margin: 4px 0;
            border-radius: 5px;
            transition: background 0.3s;
            cursor: pointer;
        }
        .user-checkbox:hover {
            background: rgba(74, 158, 255, 0.1);
        }
        .user-checkbox.selected {
            background: rgba(74, 158, 255, 0.2);
            border: 1px solid rgba(74, 158, 255, 0.5);
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
                <input type="text" placeholder="جستجو در سامانه مودیان...">
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

        <!-- Dashboard Content -->
        <div class="dashboard-content">
            <div class="page-title" style="margin-bottom: 30px;">
                <h1 style="font-size: 32px; font-weight: 800; color: white; margin-bottom: 10px;">سامانه مودیان</h1>
                <p style="color: rgba(255, 255, 255, 0.7); font-size: 16px;">مدیریت درخواست‌های مالیاتی - <?php echo $_SESSION['username']; ?></p>
            </div>

            <!-- Messages -->
            <?php echo $message; ?>

            <!-- آمار فوری -->
            <?php
            $total_transactions = count($filtered_transactions);
            $new_transactions = count(array_filter($filtered_transactions, function($transaction) {
                return $transaction['status'] === 'new';
            }));
            $in_progress_transactions = count(array_filter($filtered_transactions, function($transaction) {
                return $transaction['status'] === 'in-progress';
            }));
            $completed_transactions = count(array_filter($filtered_transactions, function($transaction) {
                return $transaction['status'] === 'completed';
            }));
            
            // محاسبه درخواست‌های فوری (با مهلت کمتر از 3 روز)
            $urgent_transactions = 0;
            foreach ($filtered_transactions as $transaction) {
                $remaining_days = ceil(($transaction['deadline_timestamp'] - time()) / (24 * 60 * 60));
                if ($remaining_days <= 3 && $transaction['status'] !== 'completed') {
                    $urgent_transactions++;
                }
            }
            ?>

            <div class="dashboard-cards">
                <div class="stat-card fade-in">
                    <div class="stat-icon blue">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $total_transactions; ?></h3>
                        <p>کل درخواست‌ها</p>
                    </div>
                </div>

                <div class="stat-card fade-in">
                    <div class="stat-icon orange">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $new_transactions; ?></h3>
                        <p>جدید</p>
                    </div>
                </div>

                <div class="stat-card fade-in">
                    <div class="stat-icon green">
                        <i class="fas fa-sync-alt"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $in_progress_transactions; ?></h3>
                        <p>در حال بررسی</p>
                    </div>
                </div>

                <div class="stat-card fade-in">
                    <div class="stat-icon red">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $urgent_transactions; ?></h3>
                        <p>فوری</p>
                    </div>
                </div>
            </div>

            <!-- منوی سامانه مودیان -->
            <div class="table-container fade-in">
                <div class="table-header">
                    <h2><i class="fas fa-tasks"></i> مدیریت درخواست‌های سامانه مودیان</h2>
                    <a href="tax-system.php?action=new" class="btn btn-primary">
                        <i class="fas fa-plus-circle"></i> ارسال جدید
                    </a>
                </div>
                
                <!-- فیلترها -->
                <div style="display: flex; gap: 1rem; margin-bottom: 2rem; flex-wrap: wrap;">
                    <a href="tax-system.php?filter=all" class="btn <?php echo $filter === 'all' ? 'btn-primary' : 'btn-outline'; ?>">
                        <i class="fas fa-list"></i> همه درخواست‌ها
                    </a>
                    <a href="tax-system.php?filter=received" class="btn <?php echo $filter === 'received' ? 'btn-primary' : 'btn-outline'; ?>">
                        <i class="fas fa-download"></i> دریافتی‌های من
                    </a>
                    <a href="tax-system.php?filter=sent" class="btn <?php echo $filter === 'sent' ? 'btn-primary' : 'btn-outline'; ?>">
                        <i class="fas fa-upload"></i> ارسالی‌های من
                    </a>
                    <a href="tax-system.php?filter=urgent" class="btn <?php echo $filter === 'urgent' ? 'btn-primary' : 'btn-outline'; ?>">
                        <i class="fas fa-exclamation-circle"></i> فوری‌ها
                    </a>
                </div>

                <!-- فرم جستجو -->
                <form method="GET" style="margin-bottom: 1rem;">
                    <div style="display: flex; gap: 1rem;">
                        <input type="text" name="search" class="form-control" placeholder="جستجو در عنوان و توضیحات..." value="<?php echo htmlspecialchars($search); ?>">
                        <input type="hidden" name="filter" value="<?php echo $filter; ?>">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> جستجو
                        </button>
                        <?php if ($search): ?>
                            <a href="tax-system.php?filter=<?php echo $filter; ?>" class="btn btn-outline">
                                <i class="fas fa-times"></i> پاک کردن
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- لیست درخواست‌ها -->
            <div class="table-container fade-in">
                <div class="table-header">
                    <h2>
                        <?php 
                        $filter_titles = [
                            'all' => 'همه درخواست‌ها',
                            'received' => 'دریافتی‌های من',
                            'sent' => 'ارسالی‌های من',
                            'urgent' => 'درخواست‌های فوری'
                        ];
                        echo '<i class="fas fa-file-alt"></i> ' . $filter_titles[$filter];
                        ?>
                    </h2>
                    <span class="btn-success" style="cursor: default; padding: 8px 16px; background: rgba(52, 199, 89, 0.1); border: 1px solid rgba(52, 199, 89, 0.3); border-radius: var(--radius-sm);">
                        <?php echo count($filtered_transactions); ?> مورد
                    </span>
                </div>
                
                <?php if (count($filtered_transactions) > 0): ?>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>عنوان</th>
                                <th>شرکت</th>
                                <th>ارسال کننده</th>
                                <th>دریافت کنندگان</th>
                                <th>اولویت</th>
                                <th>مهلت</th>
                                <th>وضعیت</th>
                                <th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_reverse($filtered_transactions) as $transaction): 
                                $creator = getUser($transaction['created_by']);
                                $remaining_days = ceil(($transaction['deadline_timestamp'] - time()) / (24 * 60 * 60));
                                $is_viewed = in_array($_SESSION['user_id'], array_keys($transaction['viewed_by']));
                            ?>
                            <tr>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <strong><?php echo $transaction['title']; ?></strong>
                                        <?php if (!$is_viewed && in_array($_SESSION['user_id'], $transaction['assigned_to'])): ?>
                                            <span class="badge" style="background: #dc3545; color: white; padding: 2px 6px; border-radius: 10px; font-size: 10px;">
                                                <i class="fas fa-circle"></i> جدید
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><?php echo $transaction['company']; ?></td>
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
                                    <?php 
                                    $assigned_users = [];
                                    foreach ($transaction['assigned_to'] as $user_id) {
                                        $user = getUser($user_id);
                                        if ($user) $assigned_users[] = $user['username'];
                                    }
                                    echo implode('، ', array_slice($assigned_users, 0, 2));
                                    if (count($assigned_users) > 2) echo '...';
                                    ?>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $transaction['priority'] === 'urgent' ? 'status-in-progress' : 'status-new'; ?>">
                                        <?php echo $transaction['priority'] === 'urgent' ? 'فوری' : 'عادی'; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $deadline_class = 'deadline-normal';
                                    if ($remaining_days <= 3) $deadline_class = 'deadline-danger';
                                    elseif ($remaining_days <= 5) $deadline_class = 'deadline-warning';
                                    ?>
                                    <span class="deadline-warning <?php echo $deadline_class; ?>">
                                        <?php echo $remaining_days > 0 ? $remaining_days . ' روز' : 'منقضی شده'; ?>
                                    </span>
                                </td>
                                <td>
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
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <button onclick="viewTransaction('<?php echo $transaction['id']; ?>')" 
                                                class="btn btn-outline btn-small">
                                            <i class="fas fa-eye"></i> مشاهده
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px; color: rgba(255,255,255,0.7);">
                        <div style="font-size: 48px; margin-bottom: 20px; opacity: 0.5;">
                            📋
                        </div>
                        <h3 style="color: white; margin-bottom: 10px;">موردی یافت نشد</h3>
                        <?php if ($filter === 'received'): ?>
                            <p style="margin-bottom: 25px;">هیچ درخواستی برای شما ارسال نشده است</p>
                        <?php elseif ($filter === 'sent'): ?>
                            <p style="margin-bottom: 25px;">شما هیچ درخواستی ارسال نکرده‌اید</p>
                        <?php elseif ($filter === 'urgent'): ?>
                            <p style="margin-bottom: 25px;">هیچ درخواست فوری وجود ندارد</p>
                        <?php else: ?>
                            <p style="margin-bottom: 25px;">هنوز درخواستی ثبت نشده است</p>
                        <?php endif; ?>
                        <a href="tax-system.php?action=new" class="btn btn-primary">
                            <i class="fas fa-plus-circle"></i> ایجاد اولین درخواست
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- مودال مشاهده درخواست -->
    <div id="viewTransactionModal" class="modal">
        <div class="modal-content" style="max-width: 800px;">
            <div class="modal-header">
                <h3><i class="fas fa-eye"></i> مشاهده درخواست</h3>
                <button class="close-modal" onclick="closeModal('viewTransactionModal')">×</button>
            </div>
            <div id="transactionDetails" style="padding: 1rem;">
                <!-- محتوای درخواست اینجا لود می‌شود -->
            </div>
        </div>
    </div>

    <!-- مودال ارسال جدید -->
    <?php if (isset($_GET['action']) && $_GET['action'] === 'new'): ?>
    <div id="newTransactionModal" class="modal active">
        <div class="modal-content" style="max-width: 700px;">
            <div class="modal-header">
                <h3><i class="fas fa-plus-circle"></i> ارسال درخواست جدید</h3>
                <button class="close-modal" onclick="window.location.href='tax-system.php'">×</button>
            </div>
            <form method="POST" enctype="multipart/form-data" id="taxTransactionForm">
                <div class="form-group">
                    <label for="title">عنوان درخواست *</label>
                    <input type="text" id="title" name="title" class="form-control" required>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label for="company">شرکت مرتبط *</label>
                        <select id="company" name="company" class="form-control" required>
                            <option value="">انتخاب شرکت</option>
                            <?php foreach ($companies as $company): ?>
                                <option value="<?php echo $company['name']; ?>"><?php echo $company['name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="deadline_days">مهلت پیگیری (روز) *</label>
                        <input type="number" id="deadline_days" name="deadline_days" class="form-control"
                            min="1" max="30" value="20" required
                            placeholder="تعداد روز مهلت را وارد کنید">
                        <small style="color: rgba(255,255,255,0.7);">
                          تعداد روزهای مهلت پاسخگویی را وارد کنید (حداقل ۱ روز، حداکثر ۳۰ روز)
                        </small>    
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label for="priority">اولویت</label>
                        <select id="priority" name="priority" class="form-control">
                            <option value="normal">عادی</option>
                            <option value="urgent">فوری</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="assigned_to">ارسال به کاربران *</label>
                        <div id="users-checklist" style="max-height: 120px; overflow-y: auto; background: rgba(255,255,255,0.05); padding: 10px; border-radius: 5px; border: 1px solid rgba(255,255,255,0.2);">
                            <?php 
                            $eligible_users = array_filter($users, function($user) {
                                return $user['id'] !== $_SESSION['user_id'] && $user['is_active'];
                            });
                            foreach ($eligible_users as $user): ?>
                                <div class="user-checkbox" onclick="toggleUserSelection(this, '<?php echo $user['id']; ?>')">
                                    <input type="checkbox" name="assigned_to[]" value="<?php echo $user['id']; ?>" 
                                           style="margin: 0; display: none;" id="user_<?php echo $user['id']; ?>">
                                    <span style="flex: 1;"><?php echo $user['username']; ?> (<?php echo $user['department']; ?>)</span>
                                    <span style="color: #4a9eff; font-size: 18px; display: none;">✓</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <small style="color: rgba(255,255,255,0.7);">کاربران انتخاب شده: <span id="selected-users-count">0</span> - برای انتخاب روی کاربر کلیک کنید</small>
                    </div>
                </div>

                <div class="form-group">
                    <label for="description">توضیحات *</label>
                    <textarea id="description" name="description" class="form-control" rows="4" required placeholder="شرح کامل درخواست..."></textarea>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label for="main_file">فایل اصلی *</label>
                        <input type="file" id="main_file" name="main_file" class="form-control" required accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.xls,.xlsx,.zip,.rar">
                        <small style="color: rgba(255,255,255,0.7);">فرمت‌های مجاز: JPG, PNG, PDF, DOC, XLS, ZIP</small>
                    </div>

                    <div class="form-group">
                        <label for="attachments">پیوست‌های اختیاری</label>
                        <input type="file" id="attachments" name="attachments[]" class="form-control" multiple accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.xls,.xlsx,.zip,.rar">
                        <small style="color: rgba(255,255,255,0.7);">می‌توانید چند فایل انتخاب کنید</small>
                    </div>
                </div>

                <div style="display: flex; gap: 15px; margin-top: 25px;">
                    <button type="submit" name="create_tax_transaction" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> ارسال درخواست
                    </button>
                    <a href="tax-system.php" class="btn btn-outline">
                        <i class="fas fa-times"></i> انصراف
                    </a>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

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

        // توابع اصلی
        function viewTransaction(transactionId) {
            // اول علامت‌گذاری مشاهده
            fetch(`tax-system.php?mark_viewed=1&id=${transactionId}`)
                .then(() => {
                    // سپس باز کردن در همان تب
                    window.location.href = `tax-system-view.php?id=${transactionId}`;
                    
                })
                .catch(error => {
                    // اگر خطا اتفاق افتاد، باز هم صفحه رو باز کن
                    window.location.href = `tax-system-view.php?id=${transactionId}`;
                    
                });
            
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        // کنترل انتخاب کاربران با تیک
        function toggleUserSelection(element, userId) {
            const checkbox = document.getElementById(`user_${userId}`);
            const checkmark = element.querySelector('span:last-child');
            
            if (checkbox.checked) {
                // حذف انتخاب
                checkbox.checked = false;
                element.classList.remove('selected');
                checkmark.style.display = 'none';
            } else {
                // اضافه کردن انتخاب
                checkbox.checked = true;
                element.classList.add('selected');
                checkmark.style.display = 'inline';
            }
            
            updateSelectedUsersCount();
        }

        function updateSelectedUsersCount() {
            const selectedCount = document.querySelectorAll('input[name="assigned_to[]"]:checked').length;
            document.getElementById('selected-users-count').textContent = selectedCount;
        }

        // اعتبارسنجی فرم
        document.getElementById('taxTransactionForm')?.addEventListener('submit', function(e) {
            const selectedUsers = document.querySelectorAll('input[name="assigned_to[]"]:checked').length;
            if (selectedUsers === 0) {
                e.preventDefault();
                alert('لطفاً حداقل یک کاربر برای ارسال انتخاب کنید.');
                return false;
            }
            
            // بستن مودال پس از ارسال موفق
            setTimeout(() => {
                if (document.querySelector('.success-message')) {
                    window.location.href = 'tax-system.php';
                }
            }, 1500);
        });

        // بستن مودال با کلیک خارج از آن
        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
                window.location.href = 'tax-system.php';
            }
        });

        // بارگذاری اولیه
        document.addEventListener('DOMContentLoaded', function() {
            updateSelectedUsersCount();
        });
    </script>

</body>
</html>
[file content end]