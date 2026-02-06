<?php
require_once 'config.php';

require_once 'invoice-functions.php';

// تابع برای ذخیره داده در فایل JSON
function saveData($filename, $data) {
    $file_path = DATA_DIR . $filename . '.json';
    file_put_contents($file_path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    return true;
}

// تابع برای خواندن داده از فایل JSON
function loadData($filename) {
    $file_path = DATA_DIR . $filename . '.json';
    if (file_exists($file_path)) {
        $data = file_get_contents($file_path);
        return json_decode($data, true) ?: [];
    }
    return [];
}

// تابع برای تبدیل تاریخ میلادی به شمسی
function gregorianToJalali($gy, $gm, $gd) {
    $g_d_m = array(0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334);
    $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
    $days = 355666 + (365 * $gy) + ((int)(($gy2 + 3) / 4)) - ((int)(($gy2 + 99) / 100)) + ((int)(($gy2 + 399) / 400)) + $gd + $g_d_m[$gm - 1];
    $jy = -1595 + (33 * ((int)($days / 12053)));
    $days %= 12053;
    $jy += 4 * ((int)($days / 1461));
    $days %= 1461;
    if ($days > 365) {
        $jy += (int)(($days - 1) / 365);
        $days = ($days - 1) % 365;
    }
    $jm = ($days < 186) ? 1 + (int)($days / 31) : 7 + (int)(($days - 186) / 30);
    $jd = 1 + (($days < 186) ? ($days % 31) : (($days - 186) % 30));
    return array($jy, $jm, $jd);
}

// تابع برای تبدیل timestamp به تاریخ شمسی با ساعت ایران
function convertToJalali($timestamp) {
    // ✅ تغییر: تنظیم منطقه زمانی به تهران
    date_default_timezone_set('Asia/Tehran');
    
    $date = getdate($timestamp);
    $jalali = gregorianToJalali($date['year'], $date['mon'], $date['mday']);
    
    // فرمت ساعت به صورت ۱۲ ساعتی
    $hour = $date['hours'];
    $minute = sprintf('%02d', $date['minutes']);
    $ampm = $hour < 12 ? 'ق.ظ' : 'ب.ظ';
    $hour_12 = $hour > 12 ? $hour - 12 : $hour;
    $hour_12 = $hour_12 == 0 ? 12 : $hour_12;
    
    return $jalali[0] . '/' . sprintf('%02d', $jalali[1]) . '/' . sprintf('%02d', $jalali[2]) . 
           ' - ' . sprintf('%02d', $hour_12) . ':' . $minute . ' ' . $ampm;
}

// تابع برای فرمت کردن مبلغ
function formatPrice($price) {
    return number_format($price) . ' ریال';
}

// تابع برای دریافت تاریخ شمسی فعلی
function getCurrentJalaliDate() {
    $current_gregorian = getdate();
    $jalali = gregorianToJalali($current_gregorian['year'], $current_gregorian['mon'], $current_gregorian['mday']);
    return $jalali[0] . '/' . sprintf('%02d', $jalali[1]) . '/' . sprintf('%02d', $jalali[2]);
}

// تابع برای ارسال نوتیفیکیشن
function sendNotification($user_id, $message, $invoice_id = null) {
    $notifications = loadData('notifications');
    $notification = [
        'id' => uniqid(),
        'user_id' => $user_id,
        'message' => $message,
        'invoice_id' => $invoice_id,
        'timestamp' => time(),
        'read' => false
    ];
    $notifications[] = $notification;
    saveData('notifications', $notifications);
    return true;
}

// تابع برای بررسی شماره فاکتور تکراری
function isDuplicateInvoice($invoice_number, $store_name) {
    $invoices = loadData('invoices');
    foreach ($invoices as $invoice) {
        if ($invoice['invoice_number'] === $invoice_number && $invoice['store_name'] === $store_name) {
            return $invoice;
        }
    }
    return false;
}

// ========== توابع جدید برای سامانه مودیان ==========

// تابع برای دریافت یک تراکنش سامانه مودیان
function getTaxTransaction($transaction_id) {
    $transactions = loadData('tax-transactions');
    foreach ($transactions as $transaction) {
        if ($transaction['id'] === $transaction_id) {
            return $transaction;
        }
    }
    return null;
}

// تابع برای به‌روزرسانی تراکنش سامانه مودیان
function updateTaxTransaction($transaction_id, $updated_data) {
    $transactions = loadData('tax-transactions');
    foreach ($transactions as &$transaction) {
        if ($transaction['id'] === $transaction_id) {
            $transaction = array_merge($transaction, $updated_data);
            $transaction['updated_at'] = time();
            break;
        }
    }
    return saveData('tax-transactions', $transactions);
}

// تابع برای دریافت تراکنش‌های سامانه مودیان یک کاربر
function getUserTaxTransactions($user_id, $filter = 'all') {
    $transactions = loadData('tax-transactions');
    $filtered = [];
    
    foreach ($transactions as $transaction) {
        $is_creator = $transaction['created_by'] === $user_id;
        $is_assigned = in_array($user_id, $transaction['assigned_to']);
        
        switch ($filter) {
            case 'sent':
                if ($is_creator) $filtered[] = $transaction;
                break;
            case 'received':
                if ($is_assigned) $filtered[] = $transaction;
                break;
            case 'my':
                if ($is_creator || $is_assigned) $filtered[] = $transaction;
                break;
            case 'urgent':
                $remaining_days = ceil(($transaction['deadline_timestamp'] - time()) / (24 * 60 * 60));
                if (($is_creator || $is_assigned) && $remaining_days <= 3) {
                    $filtered[] = $transaction;
                }
                break;
            default:
                if ($is_creator || $is_assigned) $filtered[] = $transaction;
                break;
        }
    }
    
    return $filtered;
}

// تابع برای بررسی و ارسال هشدارهای مهلت
function checkTaxDeadlines() {
    $transactions = loadData('tax-transactions');
    $now = time();
    $notifications_sent = 0;
    
    foreach ($transactions as $transaction) {
        $remaining_days = ceil(($transaction['deadline_timestamp'] - $now) / (24 * 60 * 60));
        
        // اگر مهلت کمتر از 3 روز باشد و وضعیت تکمیل شده نباشد
        if ($remaining_days <= 3 && $transaction['status'] !== 'completed') {
            $assigned_users = $transaction['assigned_to'];
            
            foreach ($assigned_users as $user_id) {
                $message = "🚨 هشدار: مهلت درخواست'{$transaction['title']}' کمتر از {$remaining_days} روز باقی مانده است!";
                sendNotification($user_id, $message, null);
                $notifications_sent++;
            }
        }
    }
    
    return $notifications_sent;
}

// تابع برای ایجاد تراکنش جدید در سامانه مودیان
function createTaxTransaction($data, $main_file, $attachments = []) {
    $transactions = loadData('tax-transactions');
    
    $new_transaction = [
        'id' => uniqid(),
        'title' => $data['title'],
        'company' => $data['company'],
        'description' => $data['description'],
        'main_file' => $main_file,
        'attachments' => $attachments,
        'deadline_days' => $data['deadline_days'],
        'deadline_timestamp' => time() + ($data['deadline_days'] * 24 * 60 * 60),
        'priority' => $data['priority'],
        'status' => 'new',
        'created_by' => $data['created_by'],
        'assigned_to' => $data['assigned_to'],
        'viewed_by' => [],
        'created_at' => time(),
        'updated_at' => time(),
        'history' => [
            [
                'action' => 'create',
                'user_id' => $data['created_by'],
                'timestamp' => time(),
                'description' => 'ایجاد درخواست جدید'
            ]
        ]
    ];
    
    $transactions[] = $new_transaction;
    $result = saveData('tax-transactions', $transactions);
    
    if ($result) {
        return $new_transaction['id'];
    }
    
    return false;
}

// تابع برای افزودن پاسخ به تراکنش
function addTaxTransactionReply($transaction_id, $reply_data) {
    $transactions = loadData('tax-transactions');
    
    foreach ($transactions as &$transaction) {
        if ($transaction['id'] === $transaction_id) {
            if (!isset($transaction['replies'])) {
                $transaction['replies'] = [];
            }
            $transaction['replies'][] = $reply_data;
            $transaction['updated_at'] = time();
            $transaction['status'] = 'in-progress';
            
            $transaction['history'][] = [
                'action' => 'reply',
                'user_id' => $reply_data['user_id'],
                'timestamp' => time(),
                'description' => 'ارسال پاسخ'
            ];
            
            break;
        }
    }
    
    return saveData('tax-transactions', $transactions);
}

// تابع برای علامت‌گذاری مشاهده
function markTaxTransactionAsViewed($transaction_id, $user_id) {
    $transactions = loadData('tax-transactions');
    $marked = false;
    
    foreach ($transactions as &$transaction) {
        if ($transaction['id'] === $transaction_id) {
            if (!isset($transaction['viewed_by'][$user_id])) {
                $transaction['viewed_by'][$user_id] = time();
                $transaction['history'][] = [
                    'action' => 'view',
                    'user_id' => $user_id,
                    'timestamp' => time(),
                    'description' => 'مشاهده فایل'
                ];
                $marked = true;
            }
            break;
        }
    }
    
    if ($marked) {
        saveData('tax-transactions', $transactions);
    }
    
    return $marked;
}

// ========== توابع جدید برای گزارش‌گیری پیشرفته ==========

// تابع برای فیلتر کردن فاکتورها بر اساس معیارهای مختلف
function filterInvoices($invoices, $filters) {
    $filtered = $invoices;
    
    if (!empty($filters['company'])) {
        $filtered = array_filter($filtered, function($invoice) use ($filters) {
            return $invoice['company_name'] === $filters['company'];
        });
    }
    
    if (!empty($filters['workshop'])) {
        $filtered = array_filter($filtered, function($invoice) use ($filters) {
            return $invoice['workshop_name'] === $filters['workshop'];
        });
    }
    
    if (!empty($filters['store'])) {
        $filtered = array_filter($filtered, function($invoice) use ($filters) {
            return $invoice['store_name'] === $filters['store'];
        });
    }
    
    if (!empty($filters['status'])) {
        $filtered = array_filter($filtered, function($invoice) use ($filters) {
            return $invoice['status'] === $filters['status'];
        });
    }
    
    if (!empty($filters['from_date'])) {
        $filtered = array_filter($filtered, function($invoice) use ($filters) {
            return $invoice['date'] >= $filters['from_date'];
        });
    }
    
    if (!empty($filters['to_date'])) {
        $filtered = array_filter($filtered, function($invoice) use ($filters) {
            return $invoice['date'] <= $filters['to_date'];
        });
    }
    
    return $filtered;
}

// تابع برای گرفتن آمار فاکتورها
function getInvoiceStats($invoices) {
    $stats = [
        'total' => count($invoices),
        'total_amount' => array_sum(array_column($invoices, 'amount')),
        'completed' => count(array_filter($invoices, function($inv) { 
            return $inv['status'] === 'completed'; 
        })),
        'pending' => count(array_filter($invoices, function($inv) { 
            return $inv['status'] === 'pending'; 
        })),
        'in_progress' => count(array_filter($invoices, function($inv) { 
            return $inv['status'] === 'in-progress'; 
        })),
        'referred' => count(array_filter($invoices, function($inv) { 
            return $inv['status'] === 'referred'; 
        }))
    ];
    
    $stats['average_amount'] = $stats['total'] > 0 ? $stats['total_amount'] / $stats['total'] : 0;
    $stats['completion_rate'] = $stats['total'] > 0 ? ($stats['completed'] / $stats['total']) * 100 : 0;
    
    return $stats;
}

// تابع برای بررسی پوشه آپلود
function checkUploadDir() {
    $dirs = [
        'uploads/invoices',
        'uploads/profile-pics', 
        'uploads/chat-files',
        'uploads/tax-system'
    ];
    
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}

// تابع برای تولید شناسه یکتا
function generateId() {
    return uniqid();
}

// تابع برای فیلتر کردن متن
function sanitizeText($text) {
    return htmlspecialchars(trim($text), ENT_QUOTES, 'UTF-8');
}

// تابع برای گرفتن زمان باقیمانده
function getRemainingTime($timestamp) {
    $diff = $timestamp - time();
    
    if ($diff <= 0) {
        return 'منقضی شده';
    } elseif ($diff < 3600) {
        return ceil($diff / 60) . ' دقیقه';
    } elseif ($diff < 86400) {
        return ceil($diff / 3600) . ' ساعت';
    } else {
        return ceil($diff / 86400) . ' روز';
    }
}

// تابع برای جستجو در فاکتورها
function searchInvoices($filters) {
    $invoices = loadData('invoices');
    $results = [];
    
    foreach ($invoices as $invoice) {
        $match = true;
        
        if (!empty($filters['invoice_number']) && 
            stripos($invoice['invoice_number'], $filters['invoice_number']) === false) {
            $match = false;
        }
        
        if (!empty($filters['company_name']) && 
            stripos($invoice['company_name'], $filters['company_name']) === false) {
            $match = false;
        }
        
        if (!empty($filters['status']) && $invoice['status'] !== $filters['status']) {
            $match = false;
        }
        
        if (!empty($filters['from_date']) && $invoice['date'] < $filters['from_date']) {
            $match = false;
        }
        
        if (!empty($filters['to_date']) && $invoice['date'] > $filters['to_date']) {
            $match = false;
        }
        
        if ($match) {
            $results[] = $invoice;
        }
    }
    
    return $results;
}

// تابع برای بررسی وجود فایل
function fileExists($filename, $type = 'invoice') {
    $base_path = '';
    switch ($type) {
        case 'tax-system':
            $base_path = UPLOAD_DIR . 'tax-system/';
            break;
        case 'invoice':
            $base_path = UPLOAD_DIR . 'invoices/';
            break;
        case 'chat':
            $base_path = UPLOAD_DIR . 'chat-files/';
            break;
        case 'profile':
            $base_path = UPLOAD_DIR . 'profile-pics/';
            break;
        default:
            return false;
    }
    
    return file_exists($base_path . $filename);
}


// تابع برای شمارش درخواست‌های خوانده نشده سامانه مودیان
function getUnreadTaxTransactionsCount($user_id) {
    $tax_transactions = loadData('tax-transactions');
    $unread_count = 0;
    $urgent_count = 0;
    
    foreach ($tax_transactions as $transaction) {
        if (in_array($user_id, $transaction['assigned_to'])) {
            $is_viewed = isset($transaction['viewed_by'][$user_id]);
            $remaining_days = ceil(($transaction['deadline_timestamp'] - time()) / (24 * 60 * 60));
            
            if (!$is_viewed) {
                $unread_count++;
                if ($remaining_days <= 3) {
                    $urgent_count++;
                }
            }
        }
    }
    
    return ['unread' => $unread_count, 'urgent' => $urgent_count];
}

// تابع برای شمارش فاکتورهای خوانده نشده
function getUnreadInvoicesCount($user_id) {
    $invoices = loadData('invoices');
    $unread_count = 0;
    $urgent_count = 0;
    
    foreach ($invoices as $invoice) {
        if ($invoice['current_user_id'] === $user_id && $invoice['status'] !== 'completed') {
            $unread_count++;
            $remaining_days = getRemainingDays($invoice);
            if ($remaining_days <= 3) {
                $urgent_count++;
            }
        }
    }
    
    return ['unread' => $unread_count, 'urgent' => $urgent_count];
}


// تابع برای شمارش پیام‌های خوانده نشده چت
function getUnreadChatMessagesCount($user_id) {
    $chat_messages = loadData('chat-messages');
    $unread_count = 0;
    
    foreach ($chat_messages as $message) {
        if ($message['to_user_id'] === $user_id && !$message['read']) {
            $unread_count++;
        }
    }
    
    return $unread_count;
}

/**
 * دریافت گزینه‌های تأیید یک بخش (بدون اجباری/اختیاری)
 */
function getDepartmentApprovalOptions($department) {
    $settings = loadData('approval-settings');
    
    $department_map = [
        'مالی' => 'finance',
        'انبار' => 'warehouse',
        'حسابداری' => 'finance',
        'خرید' => 'warehouse'
    ];
    
    $dept_key = $department_map[$department] ?? $department;
    return $settings[$dept_key]['options'] ?? [];
}

/**
 * دریافت گزینه‌های تأیید برای کاربر جاری
 */
function getApprovalOptions($department = null) {
    if (!$department) {
        $user = getUser($_SESSION['user_id']);
        $department = $user['department'] ?? 'مالی';
    }
    
    return getDepartmentApprovalOptions($department);
}

/**
 * ثبت تأییدیه برای فاکتور (بدون اعتبارسنجی اجباری)
 */
function addInvoiceApproval($invoice_id, $user_id, $selected_options, $notes = '') {
    $user = getUser($user_id);
    
    // تبدیل ID گزینه‌ها به متن
    $settings = loadData('approval-settings');
    $selected_texts = [];
    
    foreach ($selected_options as $option_id) {
        foreach ($settings as $dept) {
            foreach ($dept['options'] as $option) {
                if ($option['id'] === $option_id) {
                    $selected_texts[] = $option['text'];
                    break;
                }
            }
        }
    }
    
    $approval = [
        'id' => uniqid('app_'),
        'invoice_id' => $invoice_id,
        'user_id' => $user_id,
        'user_name' => $user['username'],
        'user_department' => $user['department'],
        'user_role' => $user['role'],
        'timestamp' => time(),
        'selected_option_ids' => $selected_options,
        'selected_option_texts' => $selected_texts,
        'notes' => $notes
    ];
    
    // ذخیره
    $approvals = loadData('invoice-approvals');
    if (!is_array($approvals)) $approvals = [];
    $approvals[] = $approval;
    saveData('invoice-approvals', $approvals);
    
    // ثبت لاگ
    if (function_exists('logActivity')) {
        logActivity($user_id, 'invoice.approval', 
            "تأییدیه فاکتور ثبت شد - " . count($selected_options) . " گزینه", 
            $invoice_id);
    }
    
    return true;
}

/**
 * دریافت تأییدیه‌های یک فاکتور به تفکیک کاربران
 */
function getInvoiceApprovalHistory($invoice_id) {
    $all_approvals = loadData('invoice-approvals');
    if (!is_array($all_approvals)) return [];
    
    $invoice_approvals = array_filter($all_approvals, function($approval) use ($invoice_id) {
        return $approval['invoice_id'] === $invoice_id;
    });
    
    // مرتب بر اساس تاریخ
    usort($invoice_approvals, function($a, $b) {
        return $b['timestamp'] - $a['timestamp'];
    });
    
    return $invoice_approvals;
}

/**
 * بررسی آیا کاربر قبلاً برای این فاکتور تأییدیه ثبت کرده
 */
function hasUserApprovedInvoice($user_id, $invoice_id) {
    $approvals = getInvoiceApprovalHistory($invoice_id);
    foreach ($approvals as $approval) {
        if ($approval['user_id'] === $user_id) {
            return true;
        }
    }
    return false;
}

/**
 * دریافت تأییدیه‌های یک کاربر برای فاکتورهای مختلف
 */
function getUserInvoiceApprovals($user_id) {
    $all_approvals = loadData('invoice-approvals');
    if (!is_array($all_approvals)) return [];
    
    return array_filter($all_approvals, function($approval) use ($user_id) {
        return $approval['user_id'] === $user_id;
    });
}

/**
 * دریافت آمار تأییدیه‌ها
 */
function getApprovalStats() {
    $all_approvals = loadData('invoice-approvals');
    if (!is_array($all_approvals)) return [
        'total' => 0,
        'by_department' => [],
        'by_user' => [],
        'recent' => []
    ];
    
    $stats = [
        'total' => count($all_approvals),
        'by_department' => [],
        'by_user' => [],
        'recent' => array_slice(array_reverse($all_approvals), 0, 10)
    ];
    
    foreach ($all_approvals as $approval) {
        // آمار بر اساس واحد
        $dept = $approval['user_department'] ?? 'نامشخص';
        if (!isset($stats['by_department'][$dept])) {
            $stats['by_department'][$dept] = 0;
        }
        $stats['by_department'][$dept]++;
        
        // آمار بر اساس کاربر
        $user_id = $approval['user_id'];
        if (!isset($stats['by_user'][$user_id])) {
            $stats['by_user'][$user_id] = 0;
        }
        $stats['by_user'][$user_id]++;
    }
    
    return $stats;
}
// ========== توابع جدید برای سیستم تأیید سلسله‌مراتبی ==========

/**
 * دریافت کاربران قابل تنظیم در زنجیره تأیید
 */
function getChainEligibleUsers($exclude_user_id = null) {
    $users = loadData('users');
    $eligible_users = [];
    
    foreach ($users as $user) {
        if ($user['is_active'] && $user['id'] !== $exclude_user_id) {
            $eligible_users[] = [
                'id' => $user['id'],
                'username' => $user['username'],
                'department' => $user['department'],
                'role' => $user['role'],
                'is_supervisor' => isset($user['is_supervisor']) ? $user['is_supervisor'] : false
            ];
        }
    }
    
    return $eligible_users;
}

/**
 * دریافت زنجیره‌های تأیید یک فاکتور (کش شده)
 */
function getCachedInvoiceChain($invoice_id) {
    require_once 'approval-system.php';
    return ApprovalSystem::getInvoiceChain($invoice_id);
}

/**
 * دریافت پیشرفت زنجیره تأیید
 */
function getChainProgress($invoice_id) {
    $chain = getCachedInvoiceChain($invoice_id);
    if (!$chain) return null;
    
    require_once 'approval-system.php';
    return ApprovalSystem::getChainProgress($chain['id']);
}

/**
 * ارسال نوتیفیکیشن گروهی
 */
function sendGroupNotification($user_ids, $message, $related_id = null) {
    if (!is_array($user_ids)) {
        $user_ids = [$user_ids];
    }
    
    $success_count = 0;
    foreach ($user_ids as $user_id) {
        if (sendNotification($user_id, $message, $related_id)) {
            $success_count++;
        }
    }
    
    return $success_count;
}

/**
 * بررسی مهلت تأیید
 */
function checkApprovalDeadlines() {
    require_once 'approval-system.php';
    return ApprovalSystem::checkDelayAlerts();
}

/**
 * دریافت آمار سیستم
 */
function getSystemStats() {
    $stats = [];
    
    // آمار فاکتورها
    $invoices = loadData('invoices');
    $stats['invoices'] = [
        'total' => count($invoices),
        'completed' => count(array_filter($invoices, function($inv) {
            return $inv['status'] === 'completed';
        })),
        'pending' => count(array_filter($invoices, function($inv) {
            return $inv['status'] === 'pending';
        }))
    ];
    
    // آمار کاربران
    $users = loadData('users');
    $stats['users'] = [
        'total' => count($users),
        'active' => count(array_filter($users, function($user) {
            return $user['is_active'];
        })),
        'supervisors' => count(array_filter($users, function($user) {
            return isset($user['is_supervisor']) && $user['is_supervisor'];
        }))
    ];
    
    // آمار تأییدیه‌ها
    require_once 'approval-system.php';
    $stats['chains'] = ApprovalSystem::getChainStatistics();
    
    // آمار کش
    $cache_system = CacheSystem::getInstance();
    $stats['cache'] = $cache_system->getStats();
    
    return $stats;
}

/**
 * خروجی گرفتن از داده‌ها به فرمت مختلف
 */
function exportData($data, $format = 'json') {
    switch ($format) {
        case 'json':
            header('Content-Type: application/json');
            return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            
        case 'csv':
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="export_' . date('Y-m-d') . '.csv"');
            
            $output = fopen('php://output', 'w');
            
            // نوشتن هدر
            if (!empty($data)) {
                fputcsv($output, array_keys($data[0]));
                
                // نوشتن داده‌ها
                foreach ($data as $row) {
                    fputcsv($output, $row);
                }
            }
            
            fclose($output);
            return true;
            
        default:
            return false;
    }
}
?>