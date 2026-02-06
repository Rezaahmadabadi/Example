<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'includes/invoice-functions.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// دریافت شناسه فاکتور از GET
$invoice_id = $_GET['id'] ?? null;
if (!$invoice_id) {
    echo 'شناسه فاکتور مشخص نشده';
    exit();
}

// بارگذاری داده‌ها
$invoices = loadData('invoices');
$users = loadData('users');

// پیدا کردن فاکتور
$invoice = null;
foreach ($invoices as $inv) {
    if ($inv['id'] === $invoice_id) {
        $invoice = $inv;
        break;
    }
}

if (!$invoice) {
    echo 'فاکتور یافت نشد';
    exit();
}

// بررسی دسترسی کاربر به ارجاع این فاکتور
$can_refer = false;
if ($invoice['current_user_id'] === $_SESSION['user_id'] || isAdmin()) {
    $can_refer = true;
}

if (!$can_refer) {
    echo 'شما مجاز به ارجاع این فاکتور نیستید';
    exit();
}

// بارگذاری گزینه‌های تأیید
$approval_options = include 'approval-options.php';
$categories = $approval_options['categories'];

// ایجاد mapping از option_id به category برای JavaScript
$option_to_category = [];
foreach ($categories as $cat_key => $category) {
    foreach ($category['options'] as $option) {
        $option_to_category[$option['id']] = $cat_key;
    }
}

// دریافت تأییدیه‌های قبلی کاربر برای این فاکتور
$has_approved = false;
$user_approvals = [];
$all_approvals = loadData('invoice-approvals');
if (is_array($all_approvals)) {
    foreach ($all_approvals as $approval) {
        if ($approval['invoice_id'] === $invoice_id && 
            $approval['user_id'] === $_SESSION['user_id']) {
            $has_approved = true;
            $user_approvals = $approval['selected_option_ids'] ?? [];
            break;
        }
    }
}

// پردازش فرم ارجاع
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_refer'])) {
    $to_user_id = $_POST['to_user_id'] ?? '';
    $description = $_POST['description'] ?? '';
    $selected_options = $_POST['approval_options'] ?? [];
    $approval_notes = $_POST['approval_notes'] ?? '';
    
    // اعتبارسنجی ساده
    $error = null;
    
    if (empty($to_user_id)) {
        $error = 'لطفاً کاربر مقصد را انتخاب کنید';
    } elseif (empty($description) || strlen($description) < 10) {
        $error = 'لطفاً توضیحات ارجاع را وارد کنید (حداقل 10 کاراکتر)';
    } elseif (empty($selected_options) && !$has_approved) {
        $error = 'لطفاً حداقل یک گزینه تأیید را انتخاب کنید';
    } else {
        // ۱. اگر گزینه تأیید انتخاب شده، تأییدیه ثبت شود
        $approval_registered = false;
        if (!empty($selected_options) && !$has_approved) {
            // ثبت تأییدیه
            $user = getUser($_SESSION['user_id']);
            $selected_texts = [];
            
            // تبدیل ID به متن
            foreach ($selected_options as $option_id) {
                foreach ($categories as $category) {
                    foreach ($category['options'] as $option) {
                        if ($option['id'] === $option_id) {
                            $selected_texts[] = $option['text'];
                            break 2;
                        }
                    }
                }
            }
            
            $approval = [
                'id' => uniqid('app_'),
                'invoice_id' => $invoice_id,
                'user_id' => $_SESSION['user_id'],
                'user_name' => $user['username'],
                'user_department' => $user['department'],
                'user_role' => $user['role'],
                'timestamp' => time(),
                'selected_option_ids' => $selected_options,
                'selected_option_texts' => $selected_texts,
                'notes' => $approval_notes
            ];
            
            // ذخیره تأییدیه
            $all_approvals = loadData('invoice-approvals');
            if (!is_array($all_approvals)) $all_approvals = [];
            $all_approvals[] = $approval;
            $save_result = saveData('invoice-approvals', $all_approvals);
            
            if ($save_result) {
                $approval_registered = true;
            }
        }
        
        // ۲. پردازش فایل پیوست
        $attachment = null;
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['attachment'];
            $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'rar'];
            
            if (in_array(strtolower($file_extension), $allowed_extensions)) {
                $max_size = 5 * 1024 * 1024; // 5MB
                if ($file['size'] <= $max_size) {
                    $filename = time() . '_' . uniqid() . '.' . $file_extension;
                    $upload_path = UPLOAD_DIR . 'invoices/' . $filename;
                    
                    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                        $attachment = [
                            'file_path' => $filename,
                            'file_name' => $file['name'],
                            'file_size' => $file['size'],
                            'user_id' => $_SESSION['user_id']
                        ];
                    }
                }
            }
        }
        
        // ۳. ارجاع فاکتور
        if (referInvoice($invoice_id, $to_user_id, $description, $attachment)) {
            // ارسال نوتیفیکیشن
            $current_user = getUser($_SESSION['user_id']);
            $to_user = getUser($to_user_id);
            
            if ($to_user) {
                $message = "📤 فاکتور شماره {$invoice['invoice_number']} توسط {$current_user['username']} به شما ارجاع داده شد";
                if ($approval_registered) {
                    $message .= "\n📝 (همراه با تأییدیه)";
                }
                sendNotification($to_user_id, $message, $invoice_id);
            }
            
            $success_message = '✅ فاکتور با موفقیت ارجاع شد';
            
            // اگر تأییدیه هم ثبت شد
            if ($approval_registered) {
                $success_message .= ' و تأییدیه شما ثبت گردید';
            }
            
            $success = $success_message;
            
        } else {
            $error = '❌ خطا در ارجاع فاکتور';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ارجاع فاکتور - سیستم پیگیری فاکتور</title>
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #007AFF;
            --success: #34C759;
            --warning: #FF9500;
            --danger: #FF3B30;
            --radius: 20px;
            --radius-sm: 14px;
            --dark-bg: #1a1a2e;
            --dark-secondary: #16213e;
        }

        body {
            font-family: 'Vazirmatn', sans-serif;
            background: linear-gradient(135deg, var(--dark-bg) 0%, var(--dark-secondary) 100%);
            min-height: 100vh;
            margin: 0;
            padding: 20px;
            color: white;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid rgba(255,255,255,0.1);
        }

        .header h1 {
            color: white;
            margin: 0 0 10px 0;
            font-size: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .invoice-info {
            background: rgba(255,255,255,0.05);
            border-radius: var(--radius-sm);
            padding: 20px;
            margin-bottom: 30px;
            border: 1px solid rgba(255,255,255,0.1);
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 15px;
            background: rgba(255,255,255,0.03);
            border-radius: 10px;
            border: 1px solid rgba(255,255,255,0.05);
        }

        .info-label {
            color: rgba(255,255,255,0.7);
            font-size: 14px;
        }

        .info-value {
            color: white;
            font-weight: 600;
            font-size: 14px;
        }

        .form-container {
            background: rgba(255,255,255,0.05);
            border-radius: var(--radius);
            padding: 30px;
            border: 1px solid rgba(255,255,255,0.1);
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            color: white;
            margin-bottom: 8px;
            font-weight: 600;
            font-size: 15px;
        }

        /* استایل جدید برای select با رنگ سفید و دیده شدن گزینه‌ها */
        .form-control, select.form-control {
            width: 100%;
            padding: 12px 15px;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: var(--radius-sm);
            color: white !important;
            font-size: 14px;
            font-family: 'Vazirmatn', sans-serif;
            transition: all 0.3s;
            cursor: pointer;
        }

        /* استایل گزینه‌های داخل select */
        .form-control option {
            background: #2c2c3e !important;
            color: white !important;
            padding: 10px !important;
            font-size: 14px !important;
        }

        /* استایل hover برای option */
        .form-control option:hover,
        .form-control option:focus,
        .form-control option:checked {
            background: #4a9eff !important;
            color: white !important;
        }

        /* استایل برای select باز شده */
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            background: rgba(255,255,255,0.15);
            box-shadow: 0 0 0 3px rgba(0, 122, 255, 0.1);
        }

        /* استایل کشویی دسته‌بندی */
        .category-select {
            width: 100%;
            padding: 12px 15px;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: var(--radius-sm);
            color: white !important;
            font-size: 14px;
            font-weight: 600;
            font-family: 'Vazirmatn', sans-serif;
            cursor: pointer;
            transition: all 0.3s;
        }

        .category-select option {
            background: #2c2c3e !important;
            color: white !important;
            padding: 12px !important;
            font-weight: 500 !important;
        }

        .category-select option:hover {
            background: #4a9eff !important;
        }

        .category-select option:checked {
            background: #4a9eff !important;
            color: white !important;
        }

        /* استایل گزینه‌های هر دسته */
        .options-container {
            background: rgba(255,255,255,0.05);
            border-radius: var(--radius-sm);
            padding: 20px;
            border: 1px solid rgba(255,255,255,0.1);
            margin-top: 15px;
            max-height: 400px;
            overflow-y: auto;
        }

        .option-item {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            margin-bottom: 10px;
            background: rgba(255,255,255,0.03);
            border-radius: 10px;
            border: 1px solid rgba(255,255,255,0.1);
            transition: all 0.3s;
        }

        .option-item:hover {
            background: rgba(255,255,255,0.07);
            border-color: rgba(255,255,255,0.2);
            transform: translateX(-5px);
        }

        .option-item input[type="checkbox"] {
            margin-left: 12px;
            transform: scale(1.3);
            cursor: pointer;
            accent-color: #4a9eff;
        }

        .option-item label {
            flex: 1;
            margin: 0;
            cursor: pointer;
            color: white;
            font-size: 14px;
            font-weight: 500;
        }

        .already-approved {
            background: rgba(52, 199, 89, 0.1) !important;
            border-color: rgba(52, 199, 89, 0.3) !important;
        }

        .already-approved label {
            color: #34C759 !important;
        }

        .selected-summary {
            background: linear-gradient(135deg, rgba(52, 199, 89, 0.1), rgba(0, 122, 255, 0.1));
            border: 1px solid rgba(52, 199, 89, 0.3);
            border-radius: var(--radius-sm);
            padding: 15px;
            margin-bottom: 20px;
        }

        .summary-header {
            color: white;
            font-weight: 600;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .summary-stats {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        .stat-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .stat-value {
            color: #34C759;
            font-weight: 700;
            font-size: 18px;
        }

        .stat-label {
            color: rgba(255,255,255,0.8);
            font-size: 14px;
        }

        .alert {
            padding: 15px;
            border-radius: var(--radius-sm);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: rgba(52, 199, 89, 0.1);
            border: 1px solid rgba(52, 199, 89, 0.3);
            color: #34C759;
        }

        .alert-danger {
            background: rgba(255, 59, 48, 0.1);
            border: 1px solid rgba(255, 59, 48, 0.3);
            color: #FF3B30;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: var(--radius-sm);
            font-family: 'Vazirmatn', sans-serif;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #007AFF, #0056CC);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 122, 255, 0.3);
        }

        .btn-secondary {
            background: rgba(255,255,255,0.1);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
        }

        .btn-secondary:hover {
            background: rgba(255,255,255,0.15);
        }

        .btn-success {
            background: linear-gradient(135deg, #34C759, #28A745);
            color: white;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(52, 199, 89, 0.3);
        }

        .btn-container {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            flex-wrap: wrap;
        }

        .file-preview {
            margin-top: 10px;
            padding: 10px;
            background: rgba(255,255,255,0.05);
            border-radius: 8px;
            border: 1px dashed rgba(255,255,255,0.2);
        }

        .help-text {
            color: rgba(255,255,255,0.6);
            font-size: 13px;
            margin-top: 5px;
        }

        /* استایل برای اسکرولبار */
        .options-container::-webkit-scrollbar {
            width: 8px;
        }

        .options-container::-webkit-scrollbar-track {
            background: rgba(255,255,255,0.1);
            border-radius: 4px;
        }

        .options-container::-webkit-scrollbar-thumb {
            background: rgba(74, 158, 255, 0.3);
            border-radius: 4px;
        }

        .options-container::-webkit-scrollbar-thumb:hover {
            background: rgba(74, 158, 255, 0.5);
        }

        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            
            .form-container {
                padding: 20px;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
    
    <!-- JavaScript در head -->
    <script>
        // داده‌های مپینگ از PHP به JavaScript
        const optionToCategory = <?php echo json_encode($option_to_category); ?>;
        const categories = <?php echo json_encode($categories); ?>;
    </script>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-share-alt"></i> ارجاع فاکتور</h1>
            <p style="color: rgba(255,255,255,0.7);">ارجاع فاکتور به کاربر دیگر با امکان ثبت تأییدیه همزمان</p>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $success; ?>
                <div style="margin-top: 10px;">
                    <a href="invoice-management.php" class="btn btn-secondary" style="margin-right: 10px;">
                        <i class="fas fa-arrow-right"></i> بازگشت به لیست فاکتورها
                    </a>
                    <a href="get-invoice-details.php?id=<?php echo $invoice_id; ?>" class="btn btn-primary">
                        <i class="fas fa-eye"></i> مشاهده فاکتور
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!isset($success)): ?>
            <!-- اطلاعات فاکتور -->
            <div class="invoice-info">
                <h3 style="color: white; margin: 0 0 15px 0; font-size: 18px;">
                    <i class="fas fa-file-invoice"></i> اطلاعات فاکتور مورد نظر
                </h3>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">شماره فاکتور:</span>
                        <span class="info-value"><?php echo $invoice['invoice_number']; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">نام شرکت:</span>
                        <span class="info-value"><?php echo $invoice['company_name']; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">مبلغ:</span>
                        <span class="info-value" style="color: #34C759;"><?php echo formatPrice($invoice['amount']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">وضعیت:</span>
                        <span class="info-value">
                            <?php 
                            $status_text = [
                                'pending' => 'در انتظار',
                                'in-progress' => 'در حال پیگیری', 
                                'referred' => 'ارجاع شده',
                                'completed' => 'تکمیل شده'
                            ];
                            echo $status_text[$invoice['status']] ?? $invoice['status'];
                            ?>
                        </span>
                    </div>
                </div>
            </div>

            <form action="" method="POST" enctype="multipart/form-data" id="referForm">
                <div class="form-container">
                    <!-- بخش انتخاب کاربر -->
                    <div class="form-group">
                        <label for="to_user_id"><i class="fas fa-user"></i> انتخاب کاربر مقصد:</label>
                        <select id="to_user_id" name="to_user_id" class="form-control" required>
                            <option value="">-- انتخاب کاربر --</option>
                            <?php 
                            // فیلتر کاربران قابل ارجاع
                            foreach ($users as $user): 
                                if ($user['id'] === $_SESSION['user_id']) continue;
                                if (!$user['is_active']) continue;
                                
                                // بررسی آیا کاربر می‌تواند ارجاع دریافت کند
                                if (isset($user['can_receive_referral']) && !$user['can_receive_referral']) continue;
                            ?>
                                <option value="<?php echo $user['id']; ?>">
                                    <?php echo $user['username']; ?> (<?php echo $user['department']; ?>)
                                    <?php echo $user['id'] === $invoice['created_by'] ? ' - ایجادکننده' : ''; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="help-text">فاکتور به کاربر انتخاب شده ارجاع داده می‌شود</div>
                    </div>

                    <!-- بخش توضیحات ارجاع -->
                    <div class="form-group">
                        <label for="description"><i class="fas fa-comment-alt"></i> توضیحات ارجاع:</label>
                        <textarea id="description" name="description" class="form-control" rows="4" 
                                  placeholder="دلیل و توضیحات ارجاع فاکتور را شرح دهید..." 
                                  required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                        <div class="help-text">حداقل ۱۰ کاراکتر - این توضیحات در تاریخچه پیگیری ثبت می‌شود</div>
                    </div>

                    <!-- بخش کشویی انتخاب دسته‌بندی -->
                    <div class="form-group">
                        <label><i class="fas fa-filter"></i> انتخاب دسته‌بندی گزینه‌های تأیید:</label>
                        <select id="categorySelect" class="category-select">
                            <option value="">-- همه دسته‌بندی‌ها --</option>
                            <?php foreach ($categories as $category_key => $category): ?>
                                <option value="<?php echo $category_key; ?>"><?php echo $category['title']; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="help-text">می‌توانید دسته خاصی را انتخاب کنید یا همه گزینه‌ها را ببینید</div>
                    </div>

                    <!-- بخش گزینه‌های تأیید -->
                    <div class="form-group">
                        <label><i class="fas fa-check-circle"></i> گزینه‌های تأیید (حداقل یک گزینه انتخاب شود):</label>
                        
                        <?php if ($has_approved): ?>
                            <div class="alert alert-success" style="margin-bottom: 15px;">
                                <i class="fas fa-info-circle"></i>
                                شما قبلاً برای این فاکتور تأییدیه ثبت کرده‌اید. گزینه‌های انتخاب شده قبلی:
                                <ul style="margin: 10px 0 0 20px;">
                                    <?php 
                                    foreach ($user_approvals as $option_id) {
                                        foreach ($categories as $category) {
                                            foreach ($category['options'] as $option) {
                                                if ($option['id'] === $option_id) {
                                                    echo '<li>' . $option['text'] . '</li>';
                                                    break 2;
                                                }
                                            }
                                        }
                                    }
                                    ?>
                                </ul>
                            </div>
                        <?php else: ?>
                            <div class="options-container" id="optionsContainer">
                                <?php foreach ($categories as $category_key => $category): ?>
                                    <div class="category-section" id="category_<?php echo $category_key; ?>">
                                        <h4 style="color: white; margin: 0 0 15px 0; font-size: 16px; padding-bottom: 10px; border-bottom: 1px solid rgba(255,255,255,0.1);">
                                            <?php echo $category['title']; ?>
                                        </h4>
                                        
                                        <?php foreach ($category['options'] as $option): 
                                            $is_selected = in_array($option['id'], $_POST['approval_options'] ?? []);
                                            $is_previously_selected = in_array($option['id'], $user_approvals);
                                        ?>
                                            <div class="option-item <?php echo $is_previously_selected ? 'already-approved' : ''; ?>" data-category="<?php echo $category_key; ?>">
                                                <input type="checkbox" 
                                                       name="approval_options[]" 
                                                       value="<?php echo $option['id']; ?>" 
                                                       id="opt_<?php echo $option['id']; ?>"
                                                       <?php echo $is_selected ? 'checked' : ''; ?>
                                                       <?php echo $is_previously_selected ? 'disabled' : ''; ?>
                                                       class="approval-checkbox">
                                                <label for="opt_<?php echo $option['id']; ?>">
                                                    <?php echo $option['text']; ?>
                                                    <?php if ($is_previously_selected): ?>
                                                        <span style="color: #34C759; margin-right: 10px; font-size: 12px;">
                                                            <i class="fas fa-check"></i> تأیید شده قبلی
                                                        </span>
                                                    <?php endif; ?>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="selected-summary" style="display: none;" id="summaryPanel">
                                <div class="summary-header">
                                    <i class="fas fa-chart-pie"></i> خلاصه انتخاب‌ها
                                </div>
                                <div class="summary-stats">
                                    <div class="stat-item">
                                        <span class="stat-value" id="selectedCount">0</span>
                                        <span class="stat-label">گزینه انتخاب شده</span>
                                    </div>
                                </div>
                            </div>

                            <!-- توضیحات تأیید -->
                            <div class="form-group" style="margin-top: 20px;">
                                <label for="approval_notes"><i class="fas fa-sticky-note"></i> توضیحات تأیید (اختیاری):</label>
                                <textarea id="approval_notes" name="approval_notes" class="form-control" rows="3" 
                                          placeholder="توضیحات اضافی درباره تأییدیه..."><?php echo htmlspecialchars($_POST['approval_notes'] ?? ''); ?></textarea>
                                <div class="help-text">این توضیحات در تأییدیه شما ثبت می‌شود</div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- بخش پیوست -->
                    <div class="form-group">
                        <label for="attachment"><i class="fas fa-paperclip"></i> فایل پیوست (اختیاری):</label>
                        <input type="file" id="attachment" name="attachment" class="form-control" 
                               accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.xls,.xlsx,.zip,.rar">
                        <div class="help-text">فرمت‌های مجاز: JPG, PNG, PDF, DOC, XLS, ZIP (حداکثر 5MB)</div>
                        <div id="attachmentPreview" class="file-preview"></div>
                    </div>

                    <!-- دکمه‌های اقدام -->
                    <div class="btn-container">
                        <button type="submit" name="submit_refer" class="btn btn-success">
                            <i class="fas fa-paper-plane"></i> ارسال ارجاع
                        </button>
                        <a href="get-invoice-details.php?id=<?php echo $invoice_id; ?>" class="btn btn-secondary">
                            <i class="fas fa-times"></i> انصراف
                        </a>
                        <button type="button" class="btn btn-primary" onclick="window.history.back()">
                            <i class="fas fa-arrow-right"></i> بازگشت
                        </button>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <!-- JavaScript کامل در انتهای body -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // عناصر DOM
        const categorySelect = document.getElementById('categorySelect');
        const allOptionItems = document.querySelectorAll('.option-item');
        const checkboxes = document.querySelectorAll('.approval-checkbox:not([disabled])');
        const summaryPanel = document.getElementById('summaryPanel');
        
        // 1. فیلتر دسته‌بندی - نسخه ساده و کارآمد
        if (categorySelect) {
            categorySelect.addEventListener('change', function() {
                const selectedCategory = this.value;
                
                console.log('دسته انتخاب شده:', selectedCategory);
                
                allOptionItems.forEach(item => {
                    const itemCategory = item.getAttribute('data-category');
                    
                    if (!selectedCategory || selectedCategory === itemCategory) {
                        // نمایش گزینه
                        item.style.display = 'flex';
                    } else {
                        // مخفی کردن گزینه
                        item.style.display = 'none';
                    }
                });
                
                // نمایش تیتر دسته‌ها
                const categorySections = document.querySelectorAll('.category-section');
                categorySections.forEach(section => {
                    if (!selectedCategory || section.id === 'category_' + selectedCategory) {
                        section.querySelector('h4').style.display = 'block';
                    } else {
                        section.querySelector('h4').style.display = 'none';
                    }
                });
                
                // لاگ برای دیباگ
                const visibleItems = document.querySelectorAll('.option-item[style*="display: flex"]').length;
                console.log(`گزینه‌های نمایش داده شده: ${visibleItems} از ${allOptionItems.length}`);
            });
            
            // اجرای اولیه
            setTimeout(() => {
                categorySelect.dispatchEvent(new Event('change'));
            }, 100);
        }
        
        // 2. آپدیت خلاصه انتخاب‌ها
        function updateSummary() {
            const selected = document.querySelectorAll('.approval-checkbox:checked').length;
            
            if (selected > 0 && summaryPanel) {
                summaryPanel.style.display = 'block';
                const selectedCountElement = document.getElementById('selectedCount');
                if (selectedCountElement) {
                    selectedCountElement.textContent = selected;
                }
            } else if (summaryPanel) {
                summaryPanel.style.display = 'none';
            }
        }
        
        // 3. رویداد تغییر برای چک‌باکس‌ها
        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', updateSummary);
            
            // تغییر رنگ آیتم انتخاب شده
            checkbox.addEventListener('change', function() {
                const item = this.closest('.option-item');
                if (item) {
                    if (this.checked) {
                        item.style.background = 'rgba(52, 199, 89, 0.1)';
                        item.style.borderColor = 'rgba(52, 199, 89, 0.3)';
                    } else {
                        item.style.background = '';
                        item.style.borderColor = '';
                    }
                }
            });
        });
        
        // 4. مقداردهی اولیه خلاصه
        updateSummary();
        
        // 5. پیش‌نمایش فایل پیوست
        const attachmentInput = document.getElementById('attachment');
        const previewDiv = document.getElementById('attachmentPreview');
        
        if (attachmentInput && previewDiv) {
            attachmentInput.addEventListener('change', function() {
                previewDiv.innerHTML = '';
                
                if (this.files && this.files[0]) {
                    const file = this.files[0];
                    const maxSize = 5 * 1024 * 1024; // 5MB
                    
                    // بررسی حجم فایل
                    if (file.size > maxSize) {
                        previewDiv.innerHTML = `
                            <div style="color: #FF3B30; padding: 10px; background: rgba(255,59,48,0.1); border-radius: 8px;">
                                <i class="fas fa-exclamation-triangle"></i>
                                حجم فایل (${(file.size/1024/1024).toFixed(2)}MB) بیش از حد مجاز است
                            </div>
                        `;
                        this.value = '';
                        return;
                    }
                    
                    const fileSize = (file.size / 1024 / 1024).toFixed(2);
                    const fileName = file.name;
                    const fileExtension = fileName.split('.').pop().toLowerCase();
                    
                    let fileIcon = '📄';
                    if (['jpg', 'jpeg', 'png', 'gif'].includes(fileExtension)) fileIcon = '🖼️';
                    if (fileExtension === 'pdf') fileIcon = '📕';
                    if (['doc', 'docx'].includes(fileExtension)) fileIcon = '📝';
                    if (['xls', 'xlsx'].includes(fileExtension)) fileIcon = '📊';
                    if (['zip', 'rar'].includes(fileExtension)) fileIcon = '📦';
                    
                    previewDiv.innerHTML = `
                        <div style="display: flex; align-items: center; gap: 15px; padding: 10px; background: rgba(255,255,255,0.05); border-radius: 8px;">
                            <div style="font-size: 24px;">${fileIcon}</div>
                            <div style="flex: 1;">
                                <div style="font-weight: 600; color: white;">${fileName}</div>
                                <div style="color: rgba(255,255,255,0.7); font-size: 12px;">
                                    ${fileExtension.toUpperCase()} فایل - ${fileSize} MB
                                </div>
                            </div>
                        </div>
                    `;
                }
            });
        }
        
        // 6. اعتبارسنجی فرم
        const form = document.getElementById('referForm');
        if (form) {
            form.addEventListener('submit', function(e) {
                // بررسی توضیحات
                const description = document.getElementById('description');
                if (description && description.value.trim().length < 10) {
                    e.preventDefault();
                    alert('⚠️ لطفاً توضیحات ارجاع را وارد کنید (حداقل ۱۰ کاراکتر)');
                    description.focus();
                    return false;
                }
                
                // بررسی حداقل یک گزینه انتخاب شده (اگر کاربر قبلاً تأیید نکرده باشد)
                <?php if (!$has_approved): ?>
                const selectedCount = document.querySelectorAll('.approval-checkbox:checked').length;
                if (selectedCount === 0) {
                    e.preventDefault();
                    alert('⚠️ لطفاً حداقل یک گزینه تأیید را انتخاب کنید');
                    return false;
                }
                <?php endif; ?>
                
                // تأیید نهایی
                const selectedCount = document.querySelectorAll('.approval-checkbox:checked').length;
                const hasApproval = <?php echo $has_approved ? 'true' : 'false'; ?>;
                
                let confirmMessage = 'آیا از ارجاع این فاکتور اطمینان دارید؟\n\n';
                
                if (!hasApproval && selectedCount > 0) {
                    confirmMessage += `📝 تأییدیه شما نیز ثبت خواهد شد (${selectedCount} گزینه)\n`;
                }
                
                confirmMessage += '\nاین عمل در تاریخچه پیگیری ثبت خواهد شد.';
                
                if (!confirm(confirmMessage)) {
                    e.preventDefault();
                    return false;
                }
                
                return true;
            });
        }
    });
    </script>
</body>
</html>