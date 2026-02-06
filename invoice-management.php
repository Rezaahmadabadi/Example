<?php
/***************************************************************
 * بخش 1: بارگذاری اولیه و احراز هویت
 ***************************************************************/
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/invoice-functions.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

/***************************************************************
 * بخش 2: بارگذاری داده‌ها
 ***************************************************************/
$users = loadData('users');
$companies = loadData('companies');
$stores = loadData('stores');
$workshops = loadData('workshops');
$all_invoices = loadData('invoices');
$message = '';

/***************************************************************
 * بخش 3: فیلتر فاکتورها برای کاربر جاری
 ***************************************************************/
function filterInvoicesForUser($all_invoices, $user_id) {
    $current_user = getUser($user_id);
    
    return array_filter($all_invoices, function($invoice) use ($user_id, $current_user) {
        
        // 1. کاربر ایجادکننده
        if ($invoice['created_by'] === $user_id) {
            return true;
        }
        
        // 2. کاربر فعلی که فاکتور به او ارجاع شده
        if ($invoice['current_user_id'] === $user_id) {
            return true;
        }
        
        // 3. کاربران قبلی که در تاریخچه پیگیری هستند
        if (isset($invoice['tracking_history']) && is_array($invoice['tracking_history'])) {
            foreach ($invoice['tracking_history'] as $history) {
                if (isset($history['user_id']) && $history['user_id'] === $user_id) {
                    return true;
                }
            }
        }
        
        // 4. ادمین‌ها همه فاکتورها را می‌بینند
        if (isAdmin()) {
            return true;
        }
        
        // 5. سرپرستان فاکتورهای زیردستان را می‌بینند
        if ($current_user && isset($current_user['is_supervisor']) && $current_user['is_supervisor']) {
            $creator = getUser($invoice['created_by']);
            if ($creator && isset($creator['supervisor_id']) && $creator['supervisor_id'] === $user_id) {
                return true;
            }
        }
        
        return false;
    });
}

// اعمال فیلتر اولیه
$invoices = filterInvoicesForUser($all_invoices, $_SESSION['user_id']);

/***************************************************************
 * بخش 4: عملیات ایجاد فاکتور جدید
 ***************************************************************/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_invoice'])) {
    // بررسی آیا کاربر مجاز به ایجاد فاکتور است
    $current_user = getUser($_SESSION['user_id']);
    $can_create_invoice = isAdmin() || 
                         (isset($current_user['can_create_invoice']) && $current_user['can_create_invoice']);
    
    if ($can_create_invoice && isset($_POST['invoice_number']) && isset($_POST['store_name']) && isset($_POST['amount'])) {
        // بررسی شماره فاکتور تکراری برای همان فروشگاه
        $invoice_number = $_POST['invoice_number'];
        $store_name = $_POST['store_name'];
        
        // برای بررسی تکراری بودن باید از $all_invoices استفاده کنیم، نه $invoices
        $duplicate_invoice = null;
        foreach ($all_invoices as $invoice) {
            if ($invoice['invoice_number'] === $invoice_number && $invoice['store_name'] === $store_name) {
                $duplicate_invoice = $invoice;
                break;
            }
        }
        
        if ($duplicate_invoice) {
            // نمایش اخطار برای شماره فاکتور تکراری
            $duplicate_message = "شماره فاکتور {$invoice_number} قبلاً برای فروشگاه {$store_name} ثبت شده است.\n";
            $duplicate_message .= "تاریخ ثبت: {$duplicate_invoice['date']}\n";
            $duplicate_message .= "مبلغ: " . formatPrice($duplicate_invoice['amount']) . "\n";
            $duplicate_message .= "آیا مطمئن هستید که می‌خواهید این شماره فاکتور تکراری را ثبت کنید؟";
            
            // اگر کاربر تایید کرد، ادامه دهد
            if (isset($_POST['confirm_duplicate']) && $_POST['confirm_duplicate'] === 'yes') {
                // ادامه فرآیند ثبت
                $amount = $_POST['amount'];
                $amount_numeric = preg_replace('/[^\d]/', '', $amount);
                
                if (empty($amount_numeric) || $amount_numeric === '0') {
                    $message = '<div class="error-highlight">⚠️ لطفا مبلغ فاکتور را وارد کنید</div>';
                } else {
                    $_POST['amount'] = $amount_numeric;
                    
                    $invoice_id = createInvoice($_POST, $_FILES['invoice_file']);
                    if ($invoice_id) {
                        $message = '<div class="success-highlight">✅ فاکتور با موفقیت ثبت شد</div>';
                        
                        // اگر کاربر برای ارجاع انتخاب شده بود
                        if (!empty($_POST['assign_to_user'])) {
                            $to_user_id = $_POST['assign_to_user'];
                            $description = "ارجاع خودکار پس از ثبت فاکتور";
                            
                            if (referInvoice($invoice_id, $to_user_id, $description)) {
                                $message = '<div class="success-highlight">✅ فاکتور با موفقیت ثبت و به کاربر مورد نظر ارجاع داده شد</div>';
                            }
                        }
                        
                        // بارگذاری مجدد و اعمال فیلتر صحیح
                        $all_invoices = loadData('invoices');
                        $invoices = filterInvoicesForUser($all_invoices, $_SESSION['user_id']);
                        
                    } else {
                        $message = '<div class="error-highlight">❌ خطا در ثبت فاکتور</div>';
                    }
                }
            } else {
                $message = '<div class="error-highlight">⚠️ ثبت فاکتور لغو شد. شماره فاکتور تکراری است.</div>';
            }
        } else {
            // اگر شماره فاکتور تکراری نبود، ادامه فرآیند ثبت
            $amount = $_POST['amount'];
            $amount_numeric = preg_replace('/[^\d]/', '', $amount);
            
            if (empty($amount_numeric) || $amount_numeric === '0') {
                $message = '<div class="error-highlight">⚠️ لطفا مبلغ فاکتور را وارد کنید</div>';
            } else {
                $_POST['amount'] = $amount_numeric;
                
                $invoice_id = createInvoice($_POST, $_FILES['invoice_file']);
                if ($invoice_id) {
                    $message = '<div class="success-highlight">✅ فاکتور با موفقیت ثبت شد</div>';
                    
                    // اگر کاربر برای ارجاع انتخاب شده بود
                    if (!empty($_POST['assign_to_user'])) {
                        $to_user_id = $_POST['assign_to_user'];
                        $description = "ارجاع خودکار پس از ثبت فاکتور";
                        
                        if (referInvoice($invoice_id, $to_user_id, $description)) {
                            $message = '<div class="success-highlight">✅ فاکتور با موفقیت ثبت و به کاربر مورد نظر ارجاع داده شد</div>';
                        }
                    }
                    
                    // بارگذاری مجدد و اعمال فیلتر صحیح
                    $all_invoices = loadData('invoices');
                    $invoices = filterInvoicesForUser($all_invoices, $_SESSION['user_id']);
                    
                } else {
                    $message = '<div class="error-highlight">❌ خطا در ثبت فاکتور</div>';
                }
            }
        }
    } else {
        $message = '<div class="error-highlight">⚠️ شما مجوز ثبت فاکتور را ندارید</div>';
    }
}

/***************************************************************
 * بخش 5: عملیات ارجاع فاکتور با تأیید همزمان
 ***************************************************************/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['refer_invoice'])) {
    $invoice_id = $_POST['invoice_id'];
    $to_user_id = $_POST['to_user_id'];
    $description = $_POST['refer_description'];
    
    // بررسی اینکه آیا کاربر مجاز به ارجاع این فاکتور است
    $can_refer = false;
    foreach ($invoices as $inv) {
        if ($inv['id'] === $invoice_id && $inv['current_user_id'] === $_SESSION['user_id']) {
            $can_refer = true;
            break;
        }
    }
    
    if (!$can_refer && !isAdmin()) {
        $message = '<div class="error-highlight">⚠️ شما مجاز به ارجاع این فاکتور نیستید</div>';
    } else {
        // اگر گزینه‌های تأیید انتخاب شده، ابتدا تأیید را ثبت کن
        if (isset($_POST['approval_options']) && !empty($_POST['approval_options'])) {
            $selected_options = $_POST['approval_options'];
            $approval_notes = $_POST['approval_notes'] ?? '';
            
            // ثبت تأییدیه
            require_once 'includes/functions.php';
            if (function_exists('addInvoiceApproval')) {
                addInvoiceApproval($invoice_id, $_SESSION['user_id'], $selected_options, $approval_notes);
            }
        }
        
        // سپس فاکتور را ارجاع بده
        if (referInvoice($invoice_id, $to_user_id, $description)) {
            $message = '<div class="success-highlight">✅ فاکتور با موفقیت ارجاع داده شد';
            
            if (isset($_POST['approval_options']) && !empty($_POST['approval_options'])) {
                $message .= ' و تأییدیه شما ثبت شد';
            }
            
            $message .= '</div>';
            
            // به‌روزرسانی لیست
            $all_invoices = loadData('invoices');
            $invoices = filterInvoicesForUser($all_invoices, $_SESSION['user_id']);
        } else {
            $message = '<div class="error-highlight">❌ خطا در ارجاع فاکتور</div>';
        }
    }
}

/***************************************************************
 * بخش 6: عملیات دریافت فاکتور
 ***************************************************************/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['receive_invoice'])) {
    $invoice_id = $_POST['invoice_id'];
    $description = $_POST['receive_description'];
    
    if (receiveInvoice($invoice_id, $description)) {
        $message = '<div class="success-highlight">✅ فاکتور با موفقیت دریافت شد</div>';
        // به‌روزرسانی لیست
        $all_invoices = loadData('invoices');
        $invoices = filterInvoicesForUser($all_invoices, $_SESSION['user_id']);
    } else {
        $message = '<div class="error-highlight">❌ خطا در دریافت فاکتور</div>';
    }
}

/***************************************************************
 * بخش 7: عملیات تکمیل فاکتور
 ***************************************************************/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_invoice'])) {
    $invoice_id = $_POST['invoice_id'];
    
    if (isAdmin() && completeInvoice($invoice_id)) {
        $message = '<div class="success-highlight">✅ فاکتور با موفقیت تکمیل شد</div>';
        // به‌روزرسانی لیست
        $all_invoices = loadData('invoices');
        $invoices = filterInvoicesForUser($all_invoices, $_SESSION['user_id']);
    } else {
        $message = '<div class="error-highlight">❌ خطا در تکمیل فاکتور</div>';
    }
}

/***************************************************************
 * بخش 8: عملیات ثبت تأییدیه فاکتور (ساده شده)
 ***************************************************************/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_approval'])) {
    $invoice_id = $_POST['invoice_id'];
    $selected_options = $_POST['approval_options'] ?? [];
    $notes = $_POST['approval_notes'] ?? '';
    
    // بررسی دسترسی
    $can_approve = false;
    foreach ($invoices as $inv) {
        if ($inv['id'] === $invoice_id && $inv['current_user_id'] === $_SESSION['user_id']) {
            $can_approve = true;
            break;
        }
    }
    
    if (!$can_approve && !isAdmin()) {
        $message = '<div class="error-highlight">⚠️ شما مجاز به تأیید این فاکتور نیستید</div>';
    } else {
        // بررسی آیا قبلاً تأیید کرده
        // روش مستقیم بدون فراخوانی تابع
        $has_approved = false;
        $all_approvals = loadData('invoice-approvals');
        if (is_array($all_approvals)) {
            foreach ($all_approvals as $approval) {
                if ($approval['invoice_id'] === $invoice_id && 
                    $approval['user_id'] === $_SESSION['user_id']) {
                    $has_approved = true;
                    break;
                }
            }
        }
        
        if ($has_approved) {
            $message = '<div class="error-highlight">⚠️ شما قبلاً برای این فاکتور تأییدیه ثبت کرده‌اید</div>';
        } else {
            // ثبت تأییدیه
            if (empty($selected_options)) {
                $message = '<div class="error-highlight">⚠️ لطفاً حداقل یک گزینه را انتخاب کنید</div>';
            } else {
                // فراخوانی تابع addInvoiceApproval
                if (function_exists('addInvoiceApproval')) {
                    if (addInvoiceApproval($invoice_id, $_SESSION['user_id'], $selected_options, $notes)) {
                        $message = '<div class="success-highlight">✅ تأییدیه شما با موفقیت ثبت شد</div>';
                        
                        // بارگذاری مجدد
                        $all_invoices = loadData('invoices');
                        $invoices = filterInvoicesForUser($all_invoices, $_SESSION['user_id']);
                    } else {
                        $message = '<div class="error-highlight">❌ خطا در ثبت تأییدیه</div>';
                    }
                } else {
                    $message = '<div class="error-highlight">⚠️ سیستم تأییدیه در دسترس نیست</div>';
                }
            }
        }
    }
}

/***************************************************************
 * بخش 9: عملیات حذف فاکتور
 ***************************************************************/
if (isset($_GET['delete_invoice'])) {
    $invoice_id = $_GET['delete_invoice'];
    if (isAdmin()) {
        // پیدا کردن فاکتور از بین همه فاکتورها
        $invoice_to_delete = null;
        foreach ($all_invoices as $invoice) {
            if ($invoice['id'] === $invoice_id) {
                $invoice_to_delete = $invoice;
                break;
            }
        }
        
        if ($invoice_to_delete) {
            // بررسی اینکه آیا فاکتور ارجاع داده شده
            if ($invoice_to_delete['status'] === 'referred' || $invoice_to_delete['status'] === 'completed') {
                $message = '<div class="error-highlight">⚠️ فاکتورهای ارجاع داده شده یا تکمیل شده قابل حذف نیستند</div>';
            } else {
                // حذف از آرایه
                $all_invoices = array_filter($all_invoices, function($invoice) use ($invoice_id) {
                    return $invoice['id'] !== $invoice_id;
                });
                saveData('invoices', $all_invoices);
                $message = '<div class="success-highlight">✅ فاکتور با موفقیت حذف شد</div>';
                
                // به‌روزرسانی لیست نمایشی
                $invoices = filterInvoicesForUser($all_invoices, $_SESSION['user_id']);
            }
        }
    }
}

/***************************************************************
 * بخش 10: مشاهده فاکتور خاص
 ***************************************************************/
$current_invoice = null;
if (isset($_GET['action']) && $_GET['action'] === 'view' && isset($_GET['id'])) {
    // فقط اگر کاربر دسترسی داشته باشد
    foreach ($invoices as $invoice) {
        if ($invoice['id'] === $_GET['id']) {
            $current_invoice = $invoice;
            break;
        }
    }
    
    // اگر فاکتور پیدا نشد (کاربر دسترسی ندارد)
    if (!$current_invoice) {
        $message = '<div class="error-highlight">⚠️ شما مجوز مشاهده این فاکتور را ندارید</div>';
    }
}

/***************************************************************
 * بخش 11: آماده‌سازی داده‌های نمایشی
 ***************************************************************/
// بررسی آیا کاربر می‌تواند فاکتور ایجاد کند
$current_user = getUser($_SESSION['user_id']);
$can_create_invoice = isAdmin() || 
                     (isset($current_user['can_create_invoice']) && $current_user['can_create_invoice']);

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
    <title>مدیریت فاکتورها - سیستم پیگیری فاکتور</title>
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" href="assets/icons/favicon.ico">
    
    <style>
        /* استایل‌های شما (همان استایل‌های قبلی) */
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

        /* ... بقیه استایل‌ها (همانند فایل اصلی شما) ... */

        /* استایل‌های جدید برای سیستم تأییدیه */
        .approval-badge {
            display: inline-block;
            background: linear-gradient(135deg, #34C759, #28a745);
            color: white;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            margin-right: 5px;
        }

        .approval-badge.pending {
            background: linear-gradient(135deg, #FF9500, #fd7e14);
        }

        .approval-modal .approval-option {
            transition: all 0.3s ease;
        }

        .approval-modal .approval-option:hover {
            background: rgba(74, 158, 255, 0.1);
            transform: translateX(-5px);
        }

        .approval-history-item {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 10px;
        }

        .approval-options-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 8px;
        }

        .approval-option-tag {
            background: rgba(52, 199, 89, 0.2);
            color: #34C759;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 11px;
            border: 1px solid rgba(52, 199, 89, 0.3);
        }

        .approval-option-tag.mandatory {
            background: rgba(255, 59, 48, 0.2);
            color: #FF3B30;
            border-color: rgba(255, 59, 48, 0.3);
        }

        .btn-tiny {
            padding: 2px 6px !important;
            font-size: 10px !important;
        }

        .chain-progress-bar {
            width: 60px;
            height: 6px;
            background: rgba(255,255,255,0.1);
            border-radius: 3px;
            overflow: hidden;
        }

        .chain-progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #4a9eff, #6f42c1);
            border-radius: 3px;
            transition: width 0.3s ease;
        }
    </style>
</head>
<body>
    <!-- ========== بخش سایدبار ========== -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <img src="assets/logo/company-logo.png" alt="لوگو شرکت">
                <span>سیستم فاکتور</span>
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
                
                <li class="active">
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

    <!-- ========== بخش محتوای اصلی ========== -->
    <main class="main-content">
        <!-- ========== هدر اصلی ========== -->
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
                </button>
                <button class="header-btn" onclick="window.location.href='chat.php'">
                    <i class="fas fa-envelope"></i>
                </button>
                <button class="header-btn" onclick="window.location.href='profile.php'">
                    <i class="fas fa-user-circle"></i>
                </button>
            </div>
        </header>

        <!-- ========== محتوای داشبورد ========== -->
        <div class="dashboard-content">
            <div class="page-title" style="margin-bottom: 30px;">
                <h1 style="font-size: 32px; font-weight: 800; color: white; margin-bottom: 10px;">مدیریت فاکتورها</h1>
                <p style="color: rgba(255, 255, 255, 0.7); font-size: 16px;">ایجاد، مشاهده و پیگیری فاکتورها - <?php echo $_SESSION['username']; ?></p>
            </div>

            <!-- =========- بخش پیام‌ها ========== -->
            <?php echo $message; ?>

            <!-- ========== بخش کارت‌های آماری ========== -->
            <?php
            $total_invoices = count($invoices);
            $pending_invoices = count(array_filter($invoices, function($invoice) {
                return $invoice['status'] === 'pending';
            }));
            $in_progress_invoices = count(array_filter($invoices, function($invoice) {
                return $invoice['status'] === 'in-progress';
            }));
            $completed_invoices = count(array_filter($invoices, function($invoice) {
                return $invoice['status'] === 'completed';
            }));
            ?>

            <div class="dashboard-cards">
                <div class="stat-card fade-in">
                    <div class="stat-icon blue">
                        <i class="fas fa-file-invoice"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $total_invoices; ?></h3>
                        <p>کل فاکتورها</p>
                    </div>
                </div>

                <div class="stat-card fade-in">
                    <div class="stat-icon orange">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $pending_invoices; ?></h3>
                        <p>در انتظار</p>
                    </div>
                </div>

                <div class="stat-card fade-in">
                    <div class="stat-icon green">
                        <i class="fas fa-sync-alt"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $in_progress_invoices; ?></h3>
                        <p>در حال پیگیری</p>
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

            <!-- ========== بخش فرم ثبت فاکتور جدید ========== -->
            <?php if ($can_create_invoice): ?>
            <div class="form-container fade-in">
                <div class="form-header" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 25px;">
                    <h2><i class="fas fa-plus-circle"></i> ثبت فاکتور جدید</h2>
                    <span class="btn-success" style="cursor: default; padding: 8px 16px; background: rgba(52, 199, 89, 0.1); border: 1px solid rgba(52, 199, 89, 0.3); border-radius: var(--radius-sm);">
                        مجوز ثبت فعال ✓
                    </span>
                </div>
                <form action="" method="POST" enctype="multipart/form-data" id="invoiceForm">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label for="invoice_number">شماره فاکتور *</label>
                            <input type="text" id="invoice_number" name="invoice_number" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="company_name">نام شرکت *</label>
                            <select id="company_name" name="company_name" class="form-control" required>
                                <option value="">انتخاب شرکت</option>
                                <?php foreach ($companies as $company): ?>
                                    <option value="<?php echo $company['name']; ?>"><?php echo $company['name']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="amount">مبلغ فاکتور (ریال) *</label>
                            <input type="text" id="amount" name="amount" class="form-control" placeholder="1,000,000" required
                                   oninput="formatAmountLive(this)">
                        </div>
                        
                        <div class="form-group">
                            <label for="date">تاریخ فاکتور (شمسی)</label>
                            <input type="text" id="date" name="date" class="form-control" placeholder="1404/01/01">
                        </div>
                        
                        <div class="form-group">
                            <label for="store_name">نام فروشگاه/فروشنده *</label>
                            <select id="store_name" name="store_name" class="form-control" required>
                                <option value="">انتخاب فروشگاه/فروشنده</option>
                                <?php foreach ($stores as $store): ?>
                                    <option value="<?php echo $store['name']; ?>"><?php echo $store['name']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="workshop_name">نام کارگاه/دفتر</label>
                            <select id="workshop_name" name="workshop_name" class="form-control">
                                <option value="">انتخاب کارگاه/دفتر</option>
                                <?php foreach ($workshops as $workshop): ?>
                                    <option value="<?php echo $workshop['name']; ?>"><?php echo $workshop['name']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">توضیحات</label>
                        <textarea id="description" name="description" class="form-control" rows="3" placeholder="توضیحات اضافی درباره فاکتور..."></textarea>
                    </div>

                    <div class="form-group">
                        <label for="assign_to_user">ارجاع خودکار به کاربر (اختیاری)</label>
                        <select id="assign_to_user" name="assign_to_user" class="form-control">
                            <option value="">انتخاب کاربر برای ارجاع خودکار</option>
                            <?php 
                            $eligible_users = array_filter($users, function($user) {
                                return $user['id'] !== $_SESSION['user_id'] && 
                                       $user['is_active'] && 
                                       (isset($user['can_receive_referral']) ? $user['can_receive_referral'] : true);
                            });
                            foreach ($eligible_users as $user): ?>
                                <option value="<?php echo $user['id']; ?>">
                                    <?php echo $user['username']; ?> (<?php echo $user['department']; ?>)
                                    <?php if (isset($user['can_receive_referral']) && !$user['can_receive_referral']): ?>
                                        - غیرفعال برای ارجاع
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small style="color: rgba(255,255,255,0.7); display: block; margin-top: 5px;">
                            در صورت انتخاب، فاکتور بلافاصله پس از ثبت به کاربر انتخاب شده ارجاع داده می‌شود
                        </small>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label for="invoice_file">فایل فاکتور (الزامی) *</label>
                            <input type="file" id="invoice_file" name="invoice_file" class="form-control" accept="image/*,.pdf,.doc,.docx" required onchange="previewFile(this, 'filePreview')">
                            <div id="filePreview" class="file-preview" style="margin-top: 10px;"></div>
                            <small style="color: rgba(255,255,255,0.7); display: block; margin-top: 5px;">
                                فرمت‌های مجاز: JPG, PNG, PDF, DOC (حداکثر 5MB)
                            </small>
                        </div>
                        
                        <div class="form-group">
                            <label for="additional_file">پیوست فاکتور (اختیاری)</label>
                            <input type="file" id="additional_file" name="additional_file" class="form-control" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.zip,.rar" onchange="previewFile(this, 'additionalPreview')">
                            <div id="additionalPreview" class="file-preview" style="margin-top: 10px;"></div>
                            <small style="color: rgba(255,255,255,0.7); display: block; margin-top: 5px;">
                                فایل‌های تکمیلی مانند قرارداد، مشخصات فنی و...
                            </small>
                        </div>
                    </div>
                    
                    <input type="hidden" name="confirm_duplicate" id="confirm_duplicate" value="no">
                    
                    <div style="display: flex; gap: 15px; margin-top: 25px;">
                        <button type="submit" name="create_invoice" class="btn btn-primary" id="submitButton">
                            <i class="fas fa-paper-plane"></i> ثبت فاکتور
                        </button>
                        <button type="reset" class="btn btn-outline">
                            <i class="fas fa-redo"></i> پاک کردن فرم
                        </button>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <!-- ========== بخش لیست فاکتورها ========== -->
            <div class="table-container fade-in">
                <div class="table-header">
                    <h2><i class="fas fa-list"></i> لیست فاکتورها</h2>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <a href="search.php" class="btn btn-outline">
                            <i class="fas fa-search"></i> جستجوی پیشرفته
                        </a>
                        <?php if (isAdmin()): ?>
                            <button onclick="openChainManagement()" class="btn btn-primary">
                                <i class="fas fa-sitemap"></i> مدیریت زنجیره‌ها
                            </button>
                        <?php endif; ?>
                        <span class="btn-success" style="cursor: default; padding: 8px 16px; background: rgba(52, 199, 89, 0.1); border: 1px solid rgba(52, 199, 89, 0.3); border-radius: var(--radius-sm);">
                            <?php echo count($invoices); ?> فاکتور
                        </span>
                    </div>
                </div>
                
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>شماره فاکتور</th>
                                <th>نام شرکت</th>
                                <th>مبلغ</th>
                                <th>تاریخ</th>
                                <th>فروشگاه</th>
                                <th>کارگاه/دفتر</th>
                                <th>وضعیت</th>
                                <th>زنجیره تأیید</th>
                                <th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody id="invoicesTableBody">
                            <?php foreach (array_reverse($invoices) as $invoice): 
                                $current_user_obj = getUser($invoice['current_user_id']);
                                
                                // بررسی اینکه آیا فاکتور قابل حذف/ویرایش هست
                                $can_edit_delete = ($invoice['status'] === 'pending' || $invoice['status'] === 'in-progress');
                                
                                // بررسی آیا کاربر نیاز به تأیید دارد
                                $needs_approval = ($invoice['current_user_id'] === $_SESSION['user_id']);
                                
                                // بررسی آیا قبلاً تأیید کرده - روش مستقیم بدون فراخوانی تابع
                                $has_approved = false;
                                if ($needs_approval) {
                                    $all_approvals = loadData('invoice-approvals');
                                    if (is_array($all_approvals)) {
                                        foreach ($all_approvals as $approval) {
                                            if ($approval['invoice_id'] === $invoice['id'] && 
                                                $approval['user_id'] === $_SESSION['user_id']) {
                                                $has_approved = true;
                                                break;
                                            }
                                        }
                                    }
                                }
                            ?>
                            <tr data-invoice-id="<?php echo $invoice['id']; ?>">
                                <td>
                                    <?php 
                                    // بررسی تکراری بودن فاکتور
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
                                    ?>
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <strong style="font-size: 11px;"><?php echo $invoice['invoice_number']; ?></strong>
                                        <?php if ($is_duplicate): ?>
                                            <span style="color: #ff6b6b; font-size: 10px;" title="این شماره فاکتور تکراری است!">
                                                <i class="fas fa-exclamation-triangle"></i>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td style="font-size: 11px;"><?php echo $invoice['company_name']; ?></td>
                                <td style="font-size: 11px;"><?php echo formatPrice($invoice['amount']); ?></td>
                                <td style="font-size: 11px;"><?php echo convertToJalali($invoice['created_at']); ?></td>
                                <td style="font-size: 11px;"><?php echo $invoice['store_name']; ?></td>
                                <td style="font-size: 11px;"><?php echo $invoice['workshop_name']; ?></td>
                                <td style="font-size: 11px; white-space: nowrap;">
                                    <?php 
                                    $status_text = [
                                        'pending' => 'در انتظار',
                                        'in-progress' => 'در حال پیگیری', 
                                        'referred' => 'ارجاع شده',
                                        'completed' => 'تکمیل شده'
                                    ];
                                    ?>
                                    <span class="status-badge status-<?php echo $invoice['status']; ?>">
                                        <?php echo $status_text[$invoice['status']]; ?>
                                    </span>
                                    <?php if ($needs_approval && !$has_approved): ?>
                                        <span class="approval-badge pending" title="نیازمند تأیید شما">
                                            <i class="fas fa-clock"></i> نیاز به تأیید
                                        </span>
                                    <?php elseif ($has_approved): ?>
                                        <span class="approval-badge" title="تأیید شده توسط شما">
                                            <i class="fas fa-check"></i> تأیید شده
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size: 11px;">
                                    <?php 
                                    if (function_exists('getInvoiceChainStatus')) {
                                        $chain_status = getInvoiceChainStatus($invoice['id']);
                                        if ($chain_status['in_chain']): 
                                            $progress = $chain_status['progress']['progress_percentage'] ?? 0;
                                    ?>
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <!-- نوار پیشرفت کوچک -->
                                            <div style="width: 60px; height: 6px; background: rgba(255,255,255,0.1); border-radius: 3px; overflow: hidden;">
                                                <div style="height: 100%; width: <?php echo $progress; ?>%; 
                                                     background: linear-gradient(90deg, #4a9eff, #6f42c1); border-radius: 3px;"></div>
                                            </div>
                                            <!-- اطلاعات -->
                                            <div style="display: flex; flex-direction: column;">
                                                <span style="color: white; font-size: 10px; font-weight: 600;">
                                                    <?php echo $progress; ?>%
                                                </span>
                                                <span style="color: rgba(255,255,255,0.6); font-size: 9px;">
                                                    مرحله <?php echo ($chain_status['current_stage'] ?? 0) + 1; ?>/<?php echo $chain_status['total_stages'] ?? 0; ?>
                                                </span>
                                            </div>
                                            <!-- آیکون وضعیت -->
                                            <?php if ($chain_status['status'] === 'completed'): ?>
                                                <span title="تکمیل شده" style="color: #34C759; font-size: 14px;">
                                                    <i class="fas fa-check-circle"></i>
                                                </span>
                                            <?php elseif (isset($chain_status['progress']['is_overdue']) && $chain_status['progress']['is_overdue']): ?>
                                                <span title="تأخیر دارد" style="color: #ff6b6b; font-size: 14px;">
                                                    <i class="fas fa-exclamation-triangle"></i>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    <?php 
                                        else: 
                                            // اگر فاکتور در زنجیره نیست
                                            if (isAdmin() && $invoice['status'] === 'in-progress'): 
                                    ?>
                                        <button onclick="createChainForInvoice('<?php echo $invoice['id']; ?>')" 
                                                class="btn btn-outline btn-tiny" style="padding: 4px 8px; font-size: 10px;">
                                            <i class="fas fa-plus"></i> ایجاد زنجیره
                                        </button>
                                    <?php 
                                            else: 
                                                echo '<span style="color: rgba(255,255,255,0.4); font-size: 10px;">-</span>';
                                            endif;
                                        endif;
                                    } else {
                                        echo '<span style="color: rgba(255,255,255,0.4); font-size: 10px;">سیستم غیرفعال</span>';
                                    }
                                    ?>
                                </td>
                                <td style="font-size: 11px;">
                                    <div class="btn-group">
                                        <button onclick="viewInvoice('<?php echo $invoice['id']; ?>')" 
                                                class="btn btn-outline btn-small">
                                            <i class="fas fa-eye"></i> مشاهده
                                        </button>
                                        <?php if (isAdmin()): ?>
                                            <?php if ($can_edit_delete): ?>
                                                <button onclick="editInvoice('<?php echo $invoice['id']; ?>')" 
                                                        class="btn btn-outline btn-small">
                                                    <i class="fas fa-edit"></i> ویرایش
                                                </button>
                                                <button onclick="deleteInvoice('<?php echo $invoice['id']; ?>', '<?php echo $invoice['invoice_number']; ?>')" 
                                                        class="btn btn-danger btn-small">
                                                    <i class="fas fa-trash"></i> حذف
                                                </button>
                                            <?php else: ?>
                                                <button class="btn btn-outline btn-small" disabled title="فاکتور ارجاع داده شده">
                                                    <i class="fas fa-lock"></i> قفل شده
                                                </button>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if (count($invoices) === 0): ?>
                    <div style="text-align: center; padding: 40px; color: rgba(255,255,255,0.7);">
                        <div style="font-size: 48px; margin-bottom: 20px; opacity: 0.5;">
                            📄
                        </div>
                        <h3 style="color: white; margin-bottom: 10px;">هنوز فاکتوری ثبت نشده است</h3>
                        <p style="margin-bottom: 25px;">شما می‌توانید اولین فاکتور را ثبت کنید</p>
                        <?php if ($can_create_invoice): ?>
                            <a href="#create-invoice" class="btn btn-primary">
                                <i class="fas fa-plus-circle"></i> ثبت اولین فاکتور
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- ========== بخش مودال‌ها ========== -->
    
    <!-- مودال مشاهده فاکتور -->
    <div id="viewInvoiceModal" class="modal">
        <div class="modal-content" style="max-width: 800px;">
            <div class="modal-header">
                <h3><i class="fas fa-eye"></i> مشاهده فاکتور</h3>
                <button class="close-modal" onclick="closeModal('viewInvoiceModal')">×</button>
            </div>
            <div id="invoiceDetails" style="padding: 1rem;">
                <!-- محتوای فاکتور اینجا لود می‌شود -->
            </div>
        </div>
    </div>

<!-- مودال ثبت تأییدیه -->
<div id="approvalModal" class="modal approval-modal">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h3><i class="fas fa-check-circle"></i> ثبت تأییدیه شما</h3>
            <button class="close-modal" onclick="closeModal('approvalModal')">×</button>
        </div>
        <form method="POST" id="approvalForm">
            <input type="hidden" name="invoice_id" id="approvalInvoiceId">
            <input type="hidden" name="submit_approval" value="1">
            
            <div style="padding: 15px; background: rgba(74, 158, 255, 0.1); border-radius: 10px; margin-bottom: 15px;">
                <div style="color: white; font-size: 14px; margin-bottom: 5px;">
                    <i class="fas fa-info-circle"></i>
                    <strong>راهنما:</strong>
                </div>
                <div style="color: rgba(255,255,255,0.8); font-size: 12px;">
                    • هر تعداد گزینه که مربوط به بررسی شماست را انتخاب کنید<br>
                    • این تأییدیه به نام شما ثبت و برای همه کاربران درگیر قابل مشاهده خواهد بود
                </div>
            </div>
            
            <div id="approvalOptions" style="max-height: 350px; overflow-y: auto; padding: 10px;">
                <!-- گزینه‌ها اینجا لود می‌شوند -->
            </div>
            
            <div class="form-group" style="margin-top: 15px;">
                <label>توضیحات اضافی (اختیاری):</label>
                <textarea name="approval_notes" class="form-control" rows="3" 
                          placeholder="نکات یا توضیحات اضافی..."></textarea>
            </div>
            
            <div style="display: flex; gap: 15px; margin-top: 25px;">
                <button type="submit" class="btn btn-success" id="approvalSubmitBtn">
                    <i class="fas fa-check"></i> ثبت تأییدیه من
                </button>
                <button type="button" class="btn btn-outline" onclick="closeModal('approvalModal')">
                    <i class="fas fa-times"></i> انصراف
                </button>
            </div>
        </form>
    </div>
</div>

    <!-- مودال ارجاع فاکتور -->
    <div id="referInvoiceModal" class="modal">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h3><i class="fas fa-exchange-alt"></i> ارجاع فاکتور</h3>
                <button class="close-modal" onclick="closeModal('referInvoiceModal')">×</button>
            </div>
            <form id="referForm" method="POST" enctype="multipart/form-data" onsubmit="return validateReferForm()">
                <input type="hidden" name="invoice_id" id="refer_invoice_id">
                <input type="hidden" name="refer_invoice" value="1">
                
                <div class="form-group">
                    <label for="to_user_id">ارجاع به کاربر:</label>
                    <select id="to_user_id" name="to_user_id" class="form-control" required>
                        <option value="">انتخاب کاربر</option>
                        <?php 
                        // فیلتر کردن کاربران بر اساس تنظیمات مجوز ارجاع
                        $eligible_users = array_filter($users, function($user) {
                            return $user['id'] !== $_SESSION['user_id'] && 
                                   $user['is_active'] && 
                                   (isset($user['can_receive_referral']) ? $user['can_receive_referral'] : true);
                        });
                        
                        $admin_users = array_filter($eligible_users, function($user) {
                            return $user['role'] === 'admin';
                        });
                        
                        $regular_users = array_filter($eligible_users, function($user) {
                            return $user['role'] !== 'admin';
                        });
                        
                        if (!empty($regular_users)): ?>
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
                    <small style="color: rgba(255,255,255,0.7); display: block; margin-top: 5px;">
                        توجه: فاکتور فقط به کاربرانی که قابلیت دریافت ارجاع دارند ارجاع داده می‌شود
                    </small>
                </div>
                
                <div class="form-group">
                    <label for="refer_description">توضیحات ارجاع:</label>
                    <textarea id="refer_description" name="refer_description" class="form-control" rows="3" 
                              placeholder="لطفاً دلیل و توضیحات ارجاع فاکتور را وارد کنید..." required></textarea>
                    <small style="color: rgba(255,255,255,0.7); display: block; margin-top: 5px;">
                        این توضیحات در تاریخچه پیگیری ثبت می‌شود
                    </small>
                </div>

                <div class="form-group">
                    <label for="refer_attachment">فایل پیوست ارجاع (اختیاری):</label>
                    <input type="file" id="refer_attachment" name="refer_attachment" class="form-control" 
                           accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.zip,.rar" 
                           onchange="previewFile(this, 'referAttachmentPreview')">
                    <div id="referAttachmentPreview" class="file-preview" style="margin-top: 10px;"></div>
                    <small style="color: rgba(255,255,255,0.7); display: block; margin-top: 5px;">
                        فرمت‌های مجاز: JPG, PNG, PDF, DOC, XLS, ZIP (حداکثر 5MB)
                    </small>
                </div>
                
                <div style="display: flex; gap: 15px; margin-top: 25px;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> ارسال ارجاع
                    </button>
                    <button type="button" class="btn btn-outline" onclick="closeModal('referInvoiceModal')">
                        <i class="fas fa-times"></i> انصراف
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- مودال پیش‌نمایش فایل -->
    <div id="filePreviewModal" class="modal">
        <div class="modal-content" style="max-width: 90%; max-height: 90%;">
            <div class="modal-header">
                <h3><i class="fas fa-file-image"></i> پیش‌نمایش فایل</h3>
                <button class="close-modal" onclick="closeModal('filePreviewModal')">×</button>
            </div>
            <div style="text-align: center; padding: 1rem;">
                <div id="filePreviewContent"></div>
                <div style="margin-top: 1rem; display: flex; gap: 1rem; justify-content: center;">
                    <a id="downloadFile" href="" download class="btn btn-primary">
                        <i class="fas fa-download"></i> دانلود فایل
                    </a>
                    <button onclick="printFile()" class="btn btn-success">
                        <i class="fas fa-print"></i> پرینت
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- مودال مدیریت زنجیره‌ها -->
    <div id="chainManagementModal" class="modal">
        <div class="modal-content" style="max-width: 800px;">
            <div class="modal-header">
                <h3><i class="fas fa-sitemap"></i> مدیریت زنجیره‌های تأیید</h3>
                <button class="close-modal" onclick="closeModal('chainManagementModal')">×</button>
            </div>
            <div style="padding: 20px;">
                <div id="chainManagementContent">
                    <div style="text-align: center; padding: 40px; color: rgba(255,255,255,0.7);">
                        <i class="fas fa-spinner fa-spin" style="font-size: 24px; margin-bottom: 15px;"></i>
                        <div>در حال بارگذاری زنجیره‌ها...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- مودال ایجاد زنجیره جدید -->
    <div id="createChainModal" class="modal">
        <div class="modal-content" style="max-width: 700px;">
            <div class="modal-header">
                <h3><i class="fas fa-link"></i> ایجاد زنجیره تأیید جدید</h3>
                <button class="close-modal" onclick="closeModal('createChainModal')">×</button>
            </div>
            <form id="createChainForm" onsubmit="return createNewChain()">
                <input type="hidden" name="invoice_id" id="createChainInvoiceId">
                
                <div style="padding: 15px; background: rgba(74,158,255,0.1); border-radius: 10px; margin-bottom: 20px;">
                    <div style="color: white; font-size: 14px; margin-bottom: 5px;">
                        <i class="fas fa-info-circle"></i>
                        <strong>راهنمای ایجاد زنجیره:</strong>
                    </div>
                    <div style="color: rgba(255,255,255,0.8); font-size: 12px;">
                        1. مراحل تأیید را به ترتیب اضافه کنید<br>
                        2. برای هر مرحله کاربران مجاز را انتخاب کنید<br>
                        3. سرپرست نهایی را تعیین کنید<br>
                        4. مهلت هر مرحله را تنظیم کنید
                    </div>
                </div>
                
                <!-- مراحل زنجیره -->
                <div id="chainStagesContainer" style="margin-bottom: 25px;">
                    <h4 style="color: white; margin-bottom: 15px; font-size: 16px; display: flex; align-items: center; justify-content: space-between;">
                        <span><i class="fas fa-layer-group"></i> مراحل زنجیره</span>
                        <button type="button" onclick="addChainStage()" class="btn btn-outline btn-small">
                            <i class="fas fa-plus"></i> افزودن مرحله
                        </button>
                    </h4>
                    <div id="chainStagesList">
                        <!-- مراحل اینجا اضافه می‌شوند -->
                    </div>
                </div>
                
                <!-- سرپرست نهایی -->
                <div class="form-group" style="margin-bottom: 25px;">
                    <label>سرپرست نهایی (اختیاری):</label>
                    <select name="supervisor_id" class="form-control">
                        <option value="">بدون سرپرست</option>
                        <?php 
                        $supervisors = array_filter($users, function($user) {
                            return $user['is_active'] && isset($user['is_supervisor']) && $user['is_supervisor'];
                        });
                        foreach ($supervisors as $supervisor): ?>
                            <option value="<?php echo $supervisor['id']; ?>">
                                <?php echo $supervisor['username']; ?> (سرپرست - <?php echo $supervisor['department']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small style="color: rgba(255,255,255,0.7); display: block; margin-top: 5px;">
                        سرپرست می‌تواند بر پیشرفت نظارت و در صورت لزوم اقدامات مدیریتی انجام دهد
                    </small>
                </div>
                
                <div style="display: flex; gap: 15px; margin-top: 30px;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> ذخیره زنجیره
                    </button>
                    <button type="button" class="btn btn-outline" onclick="closeModal('createChainModal')">
                        <i class="fas fa-times"></i> انصراف
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ========== بخش Overlay برای موبایل ========== -->
    <div class="overlay" id="overlay"></div>

    <!-- ========== بخش فوتر ========== -->
    <footer class="footer">
        <p>توسعه دهنده: رضااحمدآبادی (پرسنل بخش مالی)</p>
    </footer>

    <!-- ========== بخش JavaScript ========== -->
    <script>
    /***************************************************************
     * بخش 1: مدیریت سایدبار برای موبایل
     ***************************************************************/
    const menuToggle = document.getElementById('menuToggle');
    const closeSidebar = document.getElementById('closeSidebar');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');

    // باز کردن سایدبار
    if (menuToggle) {
        menuToggle.addEventListener('click', () => {
            sidebar.classList.add('active');
            overlay.classList.add('active');
        });
    }

    // بستن سایدبار
    if (closeSidebar) {
        closeSidebar.addEventListener('click', () => {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
        });
    }

    // بستن سایدبار هنگام کلیک روی overlay
    if (overlay) {
        overlay.addEventListener('click', () => {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
        });
    }

    // مدیریت تغییر اندازه پنجره
    let resizeTimer;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(() => {
            // بستن سایدبار در حالت دسکتاپ
            if (window.innerWidth > 768) {
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
            }
        }, 250);
    });

    /***************************************************************
     * بخش 2: توابع اصلی عملیات فاکتور
     ***************************************************************/
    
    // تابع حذف فاکتور
    function deleteInvoice(invoiceId, invoiceNumber) {
        if (confirm(`آیا از حذف فاکتور شماره ${invoiceNumber} اطمینان دارید؟\nاین عمل غیرقابل بازگشت است!`)) {
            window.location.href = `invoice-management.php?delete_invoice=${invoiceId}`;
        }
    }

    // تابع ویرایش فاکتور
    function editInvoice(invoiceId) {
        alert(`ویرایش فاکتور ${invoiceId}\nاین قابلیت در نسخه بعدی اضافه خواهد شد.`);
    }

    // تابع مشاهده فاکتور
    function viewInvoice(invoiceId) {
        // بارگذاری اطلاعات فاکتور و نمایش در مودال
        fetch(`get-invoice-details.php?id=${invoiceId}`)
            .then(response => response.text())
            .then(html => {
                document.getElementById('invoiceDetails').innerHTML = html;
                document.getElementById('viewInvoiceModal').classList.add('active');
                overlay.classList.add('active');
            })
            .catch(error => {
                alert('خطا در بارگذاری اطلاعات فاکتور');
            });
    }

    /***************************************************************
     * بخش 3: توابع فرمت‌سازی
     ***************************************************************/
    
    // فرمت کردن مبلغ به صورت زنده
    function formatAmountLive(input) {
        let value = input.value.replace(/[^\d]/g, '');
        
        if (value) {
            input.dataset.numericValue = value;
            input.value = parseInt(value).toLocaleString('en-US'); 
        } else {
            input.dataset.numericValue = '';
            input.value = '';
        }
    }

    // آیکون فایل بر اساس نوع و نام فایل
    function getFileIcon(fileType, fileName) {
        const extension = fileName.split('.').pop().toLowerCase();
        
        if (fileType.includes('pdf') || extension === 'pdf') return '📕';
        if (fileType.includes('word') || fileType.includes('document') || extension === 'doc' || extension === 'docx') return '📝';
        if (fileType.includes('excel') || fileType.includes('spreadsheet') || extension === 'xls' || extension === 'xlsx') return '📊';
        if (fileType.includes('zip') || fileType.includes('rar') || extension === 'zip' || extension === 'rar') return '📦';
        if (fileType.includes('image') || ['jpg', 'jpeg', 'png', 'gif', 'bmp'].includes(extension)) return '🖼️';
        return '📄';
    }

    /***************************************************************
     * بخش 4: توابع پیش‌نمایش فایل
     ***************************************************************/
    
    // پیش‌نمایش فایل
    function previewFile(input, previewId) {
        const preview = document.getElementById(previewId);
        const file = input.files[0];
        
        if (!file) {
            preview.innerHTML = '';
            return;
        }
        
        // بررسی حجم فایل (حداکثر 5MB)
        const maxSize = 5 * 1024 * 1024;
        if (file.size > maxSize) {
            preview.innerHTML = `
                <div style="color: #ff6b6b; padding: 10px; background: rgba(255,107,107,0.1); border-radius: 8px; border: 1px solid rgba(255,107,107,0.3);">
                    <i class="fas fa-exclamation-triangle"></i>
                    حجم فایل (${(file.size/1024/1024).toFixed(2)}MB) بیش از حد مجاز (5MB) است
                </div>
            `;
            input.value = '';
            return;
        }
        
        const fileSize = (file.size / 1024 / 1024).toFixed(2);
        const fileType = file.type;
        const fileName = file.name;
        const fileExtension = fileName.split('.').pop().toLowerCase();
        
        // ایجاد پیش‌نمایش
        const reader = new FileReader();
        reader.onload = function(e) {
            let previewHTML = '';
            const fileIcon = getFileIcon(fileType, fileName);
            
            if (fileType.startsWith('image/')) {
                previewHTML = `
                    <div class="file-preview-item" onclick="openFilePreview('${e.target.result}', '${fileName}')">
                        <img src="${e.target.result}" alt="${fileName}">
                        <div class="file-preview-info">
                            <div class="file-preview-name">${fileName}</div>
                            <div class="file-preview-details">${fileExtension.toUpperCase()} - ${fileSize} MB</div>
                        </div>
                        <div style="color: #4a9eff;">
                            <i class="fas fa-expand"></i>
                        </div>
                    </div>
                `;
            } else {
                previewHTML = `
                    <div class="file-preview-item" onclick="openFilePreview('${e.target.result}', '${fileName}')">
                        <div class="file-preview-icon">${fileIcon}</div>
                        <div class="file-preview-info">
                            <div class="file-preview-name">${fileName}</div>
                            <div class="file-preview-details">${fileExtension.toUpperCase()} فایل - ${fileSize} MB</div>
                        </div>
                        <div style="color: #4a9eff;">
                            <i class="fas fa-eye"></i>
                        </div>
                    </div>
                `;
            }
            preview.innerHTML = previewHTML;
        };
        
        reader.onerror = function() {
            preview.innerHTML = `
                <div style="color: #ff6b6b; padding: 10px; background: rgba(255,107,107,0.1); border-radius: 8px;">
                    <i class="fas fa-exclamation-circle"></i>
                    خطا در خواندن فایل
                </div>
            `;
        };
        
        reader.readAsDataURL(file);
    }

    // باز کردن پیش‌نمایش بزرگ فایل
    function openFilePreview(fileData, fileName) {
        const modal = document.getElementById('filePreviewModal');
        const content = document.getElementById('filePreviewContent');
        const downloadLink = document.getElementById('downloadFile');
        
        // تشخیص نوع فایل
        const extension = fileName.split('.').pop().toLowerCase();
        const isImage = ['jpg', 'jpeg', 'png', 'gif', 'bmp'].includes(extension);
        const isPDF = extension === 'pdf';
        
        if (isImage) {
            content.innerHTML = `
                <img src="${fileData}" 
                     style="max-width: 100%; max-height: 60vh; border-radius: 8px; 
                     box-shadow: 0 4px 20px rgba(0,0,0,0.3); object-fit: contain;">
                <div style="margin-top: 10px; color: white; font-weight: bold; font-size: 14px;">${fileName}</div>
            `;
        } else if (isPDF) {
            content.innerHTML = `
                <div style="padding: 20px; background: white; border-radius: 8px;">
                    <embed src="${fileData}" 
                           type="application/pdf" 
                           style="width: 100%; height: 60vh; border: none;">
                </div>
                <div style="margin-top: 10px; color: white; font-weight: bold; font-size: 14px;">${fileName}</div>
            `;
        } else {
            content.innerHTML = `
                <div style="text-align: center; padding: 40px;">
                    <div style="font-size: 64px; margin-bottom: 20px; color: #4a9eff;">
                        ${getFileIcon('', fileName)}
                    </div>
                    <div style="color: white; font-weight: bold; font-size: 18px; margin-bottom: 10px;">
                        ${fileName}
                    </div>
                    <div style="color: rgba(255,255,255,0.7); font-size: 14px;">
                        این نوع فایل قابلیت پیش‌نمایش ندارد
                    </div>
                </div>
            `;
        }
        
        // ایجاد لینک دانلود
        downloadLink.href = fileData;
        downloadLink.download = fileName;
        
        modal.classList.add('active');
        overlay.classList.add('active');
    }

    // پرینت فایل
    function printFile() {
        const content = document.getElementById('filePreviewContent');
        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <html>
                <head>
                    <title>پرینت فایل</title>
                    <style>
                        body { font-family: 'Vazirmatn', sans-serif; padding: 20px; }
                        img { max-width: 100%; }
                    </style>
                </head>
                <body>
                    ${content.innerHTML}
                </body>
            </html>
        `);
        printWindow.document.close();
        printWindow.print();
    }

    /***************************************************************
     * بخش 5: توابع مدیریت مودال‌ها
     ***************************************************************/
    
    // بستن مودال
    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('active');
            overlay.classList.remove('active');
            
            // ریست کردن فرم اگر مودال تأییدیه بود
            if (modalId === 'approvalModal') {
                document.getElementById('approvalForm').reset();
                document.getElementById('approvalOptions').innerHTML = '';
                document.getElementById('approvalSubmitBtn').disabled = false;
                document.getElementById('approvalSubmitBtn').innerHTML = '<i class="fas fa-check"></i> ثبت تأییدیه';
            }
            
            // ریست کردن فرم اگر مودال ارجاع بود
            if (modalId === 'referInvoiceModal') {
                document.getElementById('referForm').reset();
                document.getElementById('referAttachmentPreview').innerHTML = '';
            }
        }
    }

    // تابع ارجاع فاکتور از صفحه مشاهده
    function referInvoiceFromView(invoiceId, invoiceNumber) {
        // بستن مودال مشاهده
        closeModal('viewInvoiceModal');
        
        // باز کردن مودال ارجاع
        const referModal = document.getElementById('referInvoiceModal');
        if (referModal) {
            document.getElementById('refer_invoice_id').value = invoiceId;
            
            // تنظیم شماره فاکتور در توضیحات
            const descriptionField = document.getElementById('refer_description');
            if (invoiceNumber) {
                descriptionField.value = `ارجاع فاکتور شماره ${invoiceNumber}`;
            }
            
            referModal.classList.add('active');
            overlay.classList.add('active');
        } else {
            alert('❌ مودال ارجاع یافت نشد');
        }
    }

    // اعتبارسنجی فرم ارجاع
    function validateReferForm() {
        const toUserId = document.getElementById('to_user_id').value;
        const description = document.getElementById('refer_description').value.trim();
        const fileInput = document.getElementById('refer_attachment');
        
        // بررسی کاربر
        if (!toUserId || toUserId === '') {
            alert('⚠️ لطفاً کاربر مقصد را انتخاب کنید');
            return false;
        }
        
        // بررسی توضیحات
        if (!description || description.length < 10) {
            alert('⚠️ لطفاً توضیحات ارجاع را وارد کنید (حداقل 10 کاراکتر)');
            document.getElementById('refer_description').focus();
            return false;
        }
        
        // بررسی حجم فایل
        if (fileInput.files.length > 0) {
            const file = fileInput.files[0];
            const maxSize = 5 * 1024 * 1024; // 5MB
            
            if (file.size > maxSize) {
                alert(`⚠️ حجم فایل پیوست (${(file.size/1024/1024).toFixed(2)}MB) بیش از حد مجاز (5MB) است`);
                fileInput.value = '';
                return false;
            }
        }
        
        // نمایش پیام تأیید
        return confirm('آیا از ارجاع این فاکتور اطمینان دارید؟\n\nاین عمل در تاریخچه پیگیری ثبت خواهد شد.');
    }

    /***************************************************************
     * بخش 6: سیستم تأییدیه فاکتور
     ***************************************************************/

// تابع باز کردن مودال تأییدیه
function openApprovalModal(invoiceId, invoiceNumber = '') {
    // ذخیره invoiceId
    document.getElementById('approvalInvoiceId').value = invoiceId;
    
    // نمایش مودال ابتدا
    document.getElementById('approvalModal').classList.add('active');
    document.getElementById('overlay').classList.add('active');
    
    // نمایش پیام در حال بارگذاری
    document.getElementById('approvalOptions').innerHTML = `
        <div style="text-align: center; padding: 30px; color: rgba(255,255,255,0.7);">
            <div style="font-size: 28px; margin-bottom: 10px;">
                <i class="fas fa-spinner fa-spin"></i>
            </div>
            در حال بارگذاری گزینه‌ها...
        </div>
    `;
    
    // بارگذاری گزینه‌های کاربر
    fetch(`get-approval-options.php`)
        .then(response => response.json())
        .then(options => {
            const container = document.getElementById('approvalOptions');
            
            if (!options || options.length === 0) {
                container.innerHTML = `
                    <div style="padding: 15px; background: rgba(255,193,7,0.1); border: 1px solid rgba(255,193,7,0.3); border-radius: 5px; color: #856404; text-align: center;">
                        <i class="fas fa-exclamation-triangle"></i>
                        هیچ گزینه‌ای برای تأیید تعریف نشده است.
                    </div>
                `;
                return;
            }
            
            let html = '';
            html += '<div style="margin-bottom: 15px; color: white; font-size: 14px;">';
            html += '<strong>لطفاً گزینه‌های مربوط به بررسی خود را انتخاب کنید:</strong>';
            html += '</div>';
            
            options.forEach((option, index) => {
                html += `
                    <div class="approval-option" style="margin-bottom: 12px; padding: 14px; background: rgba(255,255,255,0.05); border-radius: 10px; border: 1px solid rgba(255,255,255,0.1); transition: all 0.3s ease;">
                        <label style="display: flex; align-items: center; cursor: pointer; font-size: 15px;">
                            <input type="checkbox" name="approval_options[]" 
                                   value="${option.id}"
                                   style="margin-left: 12px; transform: scale(1.4); cursor: pointer;"
                                   onchange="toggleOptionSelection(this)">
                            <div style="flex: 1;">
                                <span style="color: white; font-weight: 500;">${option.text}</span>
                            </div>
                        </label>
                    </div>
                `;
            });
            
            html += `
                <div style="margin-top: 20px; padding: 10px; background: rgba(52, 199, 89, 0.1); border-radius: 8px; border: 1px solid rgba(52, 199, 89, 0.2);">
                    <div style="color: #51cf66; font-size: 13px; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-lightbulb"></i>
                        <span>می‌توانید یک، چند یا همه گزینه‌ها را انتخاب کنید</span>
                    </div>
                </div>
            `;
            
            container.innerHTML = html;
            
            // فعال کردن دکمه
            document.getElementById('approvalSubmitBtn').disabled = false;
        })
        .catch(error => {
            console.error('Error loading approval options:', error);
            const container = document.getElementById('approvalOptions');
            container.innerHTML = `
                <div style="padding: 20px; background: rgba(220,53,69,0.1); border: 1px solid rgba(220,53,69,0.3); border-radius: 8px; color: #dc3545; text-align: center;">
                    <i class="fas fa-exclamation-circle"></i>
                    خطا در بارگذاری گزینه‌ها
                </div>
            `;
        });
}

// تغییر رنگ گزینه انتخاب شده
function toggleOptionSelection(checkbox) {
    const optionDiv = checkbox.closest('.approval-option');
    if (checkbox.checked) {
        optionDiv.style.background = 'rgba(52, 199, 89, 0.15)';
        optionDiv.style.borderColor = 'rgba(52, 199, 89, 0.4)';
        optionDiv.style.transform = 'translateX(-5px)';
    } else {
        optionDiv.style.background = 'rgba(255,255,255,0.05)';
        optionDiv.style.borderColor = 'rgba(255,255,255,0.1)';
        optionDiv.style.transform = 'translateX(0)';
    }
}

// اعتبارسنجی فرم
function validateApprovalForm() {
    const form = document.getElementById('approvalForm');
    const checkboxes = form.querySelectorAll('input[name="approval_options[]"]:checked');
    
    if (checkboxes.length === 0) {
        alert('⚠️ لطفاً حداقل یک گزینه را انتخاب کنید');
        return false;
    }
    
    // نمایش تأیید نهایی
    let confirmMessage = `آیا از ثبت تأییدیه اطمینان دارید؟\n\n`;
    confirmMessage += `تعداد گزینه‌های انتخاب شده: ${checkboxes.length}\n`;
    
    const notes = form.querySelector('[name="approval_notes"]')?.value || '';
    if (notes.trim()) {
        confirmMessage += `\nتوضیحات: ${notes}`;
    }
    
    confirmMessage += `\n\nاین تأییدیه به نام شما ثبت خواهد شد.`;
    
    return confirm(confirmMessage);
}

// اضافه کردن event listener
document.addEventListener('DOMContentLoaded', function() {
    const approvalForm = document.getElementById('approvalForm');
    if (approvalForm) {
        approvalForm.addEventListener('submit', function(e) {
            if (!validateApprovalForm()) {
                e.preventDefault();
                return false;
            }
            
            // نمایش در حال پردازش
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> در حال ثبت...';
                submitBtn.disabled = true;
            }
            
            return true;
        });
    }
});

    /***************************************************************
     * بخش 7: اعتبارسنجی فرم‌ها
     ***************************************************************/
    
    // اعتبارسنجی فرم ثبت فاکتور
    document.addEventListener('DOMContentLoaded', function() {
        const invoiceForm = document.getElementById('invoiceForm');
        if (invoiceForm) {
            invoiceForm.addEventListener('submit', function(e) {
                // بررسی شماره فاکتور تکراری
                const invoiceNumber = document.getElementById('invoice_number').value.trim();
                const storeName = document.getElementById('store_name').value;
                
                if (invoiceNumber && storeName) {
                    const duplicateInvoices = <?php echo json_encode($all_invoices); ?>;
                    const duplicate = duplicateInvoices.find(inv => 
                        inv.invoice_number === invoiceNumber && inv.store_name === storeName
                    );
                    
                    if (duplicate) {
                        e.preventDefault();
                        const confirmMessage = `شماره فاکتور ${invoiceNumber} قبلاً برای فروشگاه ${storeName} ثبت شده است.\n\n` +
                                              `تاریخ ثبت: ${duplicate.date}\n` +
                                              `مبلغ: ${duplicate.amount.toLocaleString()} ریال\n\n` +
                                              `آیا مطمئن هستید که می‌خواهید این شماره فاکتور تکراری را ثبت کنید؟`;
                        
                        if (confirm(confirmMessage)) {
                            document.getElementById('confirm_duplicate').value = 'yes';
                            invoiceForm.submit();
                        } else {
                            document.getElementById('confirm_duplicate').value = 'no';
                        }
                        return;
                    }
                }
                
                // اعتبارسنجی معمول
                let isValid = true;
                let errorMessage = '';
                
                const amountInput = document.getElementById('amount');
                const numericValue = amountInput.value.replace(/[^\d]/g, '');
                
                if (!numericValue || numericValue === '0') {
                    isValid = false;
                    errorMessage = 'لطفا مبلغ فاکتور را وارد کنید';
                    amountInput.focus();
                }
                
                const invoiceFile = document.getElementById('invoice_file');
                if (!invoiceFile || !invoiceFile.files[0]) {
                    if (isValid) {
                        isValid = false;
                        errorMessage = 'لطفا فایل فاکتور را انتخاب کنید';
                    }
                } else if (invoiceFile.files[0].size > 5 * 1024 * 1024) {
                    if (isValid) {
                        isValid = false;
                        errorMessage = 'حجم فایل فاکتور نباید بیشتر از 5 مگابایت باشد';
                    }
                }
                
                const requiredFields = invoiceForm.querySelectorAll('[required]');
                requiredFields.forEach(field => {
                    if (field.id !== 'amount' && field.id !== 'invoice_file' && !field.value.trim()) {
                        isValid = false;
                        errorMessage = 'لطفا تمام فیلدهای الزامی را پر کنید';
                        field.focus();
                    }
                });
                
                if (!isValid) {
                    e.preventDefault();
                    alert(errorMessage);
                }
            });
        }
    });

    /***************************************************************
     * بخش 8: توابع مدیریت زنجیره تأیید
     ***************************************************************/

    // باز کردن مدیریت زنجیره‌ها
    function openChainManagement() {
        // بارگذاری لیست زنجیره‌ها
        fetch('get-chains-list.php')
            .then(response => response.json())
            .then(chains => {
                const container = document.getElementById('chainManagementContent');
                
                if (!chains || chains.length === 0) {
                    container.innerHTML = `
                        <div style="text-align: center; padding: 40px; color: rgba(255,255,255,0.7);">
                            <div style="font-size: 48px; margin-bottom: 20px; opacity: 0.5;">
                                ⛓️
                            </div>
                            <h3 style="color: white; margin-bottom: 10px;">هنوز زنجیره‌ای ایجاد نشده است</h3>
                            <p style="margin-bottom: 25px;">شما می‌توانید اولین زنجیره تأیید را ایجاد کنید</p>
                        </div>
                    `;
                } else {
                    let html = `
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
                            <div style="background: rgba(74,158,255,0.1); padding: 15px; border-radius: 10px;">
                                <div style="color: white; font-size: 14px; margin-bottom: 5px;">کل زنجیره‌ها</div>
                                <div style="color: white; font-size: 28px; font-weight: bold;">${chains.length}</div>
                            </div>
                            <div style="background: rgba(52,199,89,0.1); padding: 15px; border-radius: 10px;">
                                <div style="color: white; font-size: 14px; margin-bottom: 5px;">تکمیل شده</div>
                                <div style="color: white; font-size: 28px; font-weight: bold;">
                                    ${chains.filter(c => c.status === 'completed').length}
                                </div>
                            </div>
                        </div>
                        
                        <h4 style="color: white; margin-bottom: 15px; font-size: 16px;">لیست زنجیره‌ها</h4>
                        <div style="overflow-x: auto;">
                            <table style="width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr style="background: rgba(255,255,255,0.1);">
                                        <th style="padding: 12px; text-align: right; color: white; font-size: 14px;">فاکتور</th>
                                        <th style="padding: 12px; text-align: right; color: white; font-size: 14px;">مراحل</th>
                                        <th style="padding: 12px; text-align: right; color: white; font-size: 14px;">پیشرفت</th>
                                        <th style="padding: 12px; text-align: right; color: white; font-size: 14px;">وضعیت</th>
                                        <th style="padding: 12px; text-align: right; color: white; font-size: 14px;">عملیات</th>
                                    </tr>
                                </thead>
                                <tbody>
                    `;
                    
                    chains.forEach(chain => {
                        const progress = chain.progress || 0;
                        html += `
                            <tr style="border-bottom: 1px solid rgba(255,255,255,0.1);">
                                <td style="padding: 12px;">
                                    <div style="font-weight: 600; color: white;">${chain.invoice_number || 'بدون شماره'}</div>
                                    <div style="font-size: 12px; color: rgba(255,255,255,0.6);">${chain.company_name || ''}</div>
                                </td>
                                <td style="padding: 12px; color: white;">${chain.total_stages} مرحله</td>
                                <td style="padding: 12px;">
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <div style="width: 80px; height: 8px; background: rgba(255,255,255,0.1); border-radius: 4px; overflow: hidden;">
                                            <div style="height: 100%; width: ${progress}%; background: linear-gradient(90deg, #4a9eff, #6f42c1);"></div>
                                        </div>
                                        <span style="color: white; font-weight: 600;">${progress}%</span>
                                    </div>
                                </td>
                                <td style="padding: 12px;">
                                    <span style="background: ${chain.status === 'completed' ? 'rgba(52,199,89,0.2)' : 'rgba(74,158,255,0.2)'}; 
                                          color: ${chain.status === 'completed' ? '#34C759' : '#4a9eff'}; 
                                          padding: 6px 12px; border-radius: 20px; font-size: 12px; border: 1px solid ${chain.status === 'completed' ? 'rgba(52,199,89,0.3)' : 'rgba(74,158,255,0.3)'};">
                                        ${chain.status === 'completed' ? '✅ تکمیل' : '⏳ در حال بررسی'}
                                    </span>
                                </td>
                                <td style="padding: 12px;">
                                    <div style="display: flex; gap: 8px;">
                                        <button onclick="viewChainDetails('${chain.id}')" class="btn btn-outline btn-small">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button onclick="editChain('${chain.id}')" class="btn btn-outline btn-small">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button onclick="deleteChain('${chain.id}')" class="btn btn-danger btn-small">
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
                }
                
                container.innerHTML = html;
            })
            .catch(error => {
                console.error('Error loading chains:', error);
            });
        
        // نمایش مودال
        document.getElementById('chainManagementModal').classList.add('active');
        document.getElementById('overlay').classList.add('active');
    }

    // ایجاد زنجیره برای فاکتور
    function createChainForInvoice(invoiceId) {
        document.getElementById('createChainInvoiceId').value = invoiceId;
        
        // ریست کردن مراحل
        document.getElementById('chainStagesList').innerHTML = '';
        
        // اضافه کردن اولین مرحله
        addChainStage();
        
        // نمایش مودال
        document.getElementById('createChainModal').classList.add('active');
        document.getElementById('overlay').classList.add('active');
    }

    // اضافه کردن مرحله به زنجیره
    function addChainStage() {
        const container = document.getElementById('chainStagesList');
        const stageNumber = container.children.length + 1;
        
        const stageDiv = document.createElement('div');
        stageDiv.className = 'chain-stage';
        stageDiv.style.padding = '15px';
        stageDiv.style.marginBottom = '15px';
        stageDiv.style.background = 'rgba(255,255,255,0.05)';
        stageDiv.style.borderRadius = '8px';
        stageDiv.style.border = '1px solid rgba(255,255,255,0.1)';
        
        stageDiv.innerHTML = `
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 15px;">
                <h5 style="color: white; margin: 0; font-size: 15px; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-step-forward"></i> مرحله ${stageNumber}
                </h5>
                ${stageNumber > 1 ? `
                    <button type="button" onclick="removeChainStage(this)" class="btn btn-danger btn-small">
                        <i class="fas fa-trash"></i>
                    </button>
                ` : ''}
            </div>
            
            <div class="form-group" style="margin-bottom: 12px;">
                <label style="font-size: 13px;">نام مرحله:</label>
                <input type="text" name="stage_names[]" class="form-control" 
                       placeholder="مثلاً: تأیید مالی" style="font-size: 13px;">
            </div>
            
<div class="form-group" style="margin-bottom: 12px;">
    <label style="font-size: 13px;">کاربران این مرحله:</label>
    <select name="stage_users[${stageNumber - 1}][]" class="form-control" multiple 
            style="font-size: 13px; height: 100px;">
        <?php 
        /*
        // تعریف تابع getChainEligibleUsers اگر وجود ندارد
        function getChainEligibleUsers($current_user_id) {
            global $users;
            return array_filter($users, function($user) use ($current_user_id) {
                return $user['id'] !== $current_user_id && 
                       $user['is_active'] && 
                       (isset($user['can_receive_referral']) ? $user['can_receive_referral'] : true);
            });
        }
        */
        
        // استفاده مستقیم از متغیر $users
        $eligible_users = array_filter($users, function($user) {
            return $user['id'] !== $_SESSION['user_id'] && 
                   $user['is_active'] && 
                   (isset($user['can_receive_referral']) ? $user['can_receive_referral'] : true);
        });
        
        foreach ($eligible_users as $user): ?>
            <option value="<?php echo $user['id']; ?>">
                <?php echo $user['username']; ?> (<?php echo $user['department']; ?>)
            </option>
        <?php endforeach; ?>
    </select>
    <small style="color: rgba(255,255,255,0.6); font-size: 11px;">
        برای انتخاب چند کاربر، کلید Ctrl را نگه دارید
    </small>
</div>
            
            <div class="form-group">
                <label style="font-size: 13px;">مهلت (ساعت):</label>
                <input type="number" name="stage_deadlines[]" class="form-control" 
                       value="72" min="1" max="720" style="font-size: 13px;">
                <small style="color: rgba(255,255,255,0.6); font-size: 11px;">
                    مدت زمان مجاز برای تأیید این مرحله
                </small>
            </div>
        `;
        
        container.appendChild(stageDiv);
    }

    // حذف مرحله از زنجیره
    function removeChainStage(button) {
        if (confirm('آیا از حذف این مرحله اطمینان دارید؟')) {
            button.closest('.chain-stage').remove();
            
            // به‌روزرسانی شماره مراحل
            const stages = document.querySelectorAll('.chain-stage');
            stages.forEach((stage, index) => {
                const title = stage.querySelector('h5');
                if (title) {
                    title.innerHTML = `<i class="fas fa-step-forward"></i> مرحله ${index + 1}`;
                }
            });
        }
    }

    // ایجاد زنجیره جدید
    function createNewChain() {
        const form = document.getElementById('createChainForm');
        const formData = new FormData(form);
        
        // تبدیل داده‌ها به JSON
        const data = {
            invoice_id: formData.get('invoice_id'),
            supervisor_id: formData.get('supervisor_id'),
            stages: []
        };
        
        // جمع‌آوری مراحل
        const stageNames = formData.getAll('stage_names[]');
        const stageDeadlines = formData.getAll('stage_deadlines[]');
        
        for (let i = 0; i < stageNames.length; i++) {
            const stageUsers = formData.getAll(`stage_users[${i}][]`);
            
            if (stageUsers.length === 0) {
                alert(`⚠️ لطفاً حداقل یک کاربر برای مرحله ${i + 1} انتخاب کنید`);
                return false;
            }
            
            data.stages.push({
                name: stageNames[i] || `مرحله ${i + 1}`,
                users: stageUsers,
                deadline_hours: parseInt(stageDeadlines[i]) || 72
            });
        }
        
        if (data.stages.length === 0) {
            alert('⚠️ لطفاً حداقل یک مرحله تعریف کنید');
            return false;
        }
        
        // ارسال درخواست
        fetch('create-approval-chain.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                alert('✅ زنجیره تأیید با موفقیت ایجاد شد');
                closeModal('createChainModal');
                location.reload();
            } else {
                alert('❌ ' + (result.message || 'خطا در ایجاد زنجیره'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('خطا در ارتباط با سرور');
        });
        
        return false;
    }

    // مشاهده جزئیات زنجیره
    function viewChainDetails(chainId) {
        window.open(`chain-details.php?id=${chainId}`, '_blank');
    }

    // حذف زنجیره
    function deleteChain(chainId) {
        if (confirm('⚠️ آیا از حذف این زنجیره تأیید اطمینان دارید؟\nاین عمل غیرقابل بازگشت است و تمام تاریخچه آن حذف می‌شود.')) {
            fetch(`delete-chain.php?id=${chainId}`, {
                method: 'DELETE'
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    alert('✅ زنجیره با موفقیت حذف شد');
                    openChainManagement(); // به‌روزرسانی لیست
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
        alert(`ویرایش زنجیره ${chainId}\nاین قابلیت در نسخه بعدی اضافه خواهد شد.`);
    }
    </script>
</body>
</html>