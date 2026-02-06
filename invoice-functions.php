<?php
require_once 'config.php';
require_once 'functions.php';

// تابع برای ایجاد فاکتور جدید
function createInvoice($data, $invoice_file) {
    $invoices = loadData('invoices');
    
    // آپلود فایل فاکتور
    $file_extension = pathinfo($invoice_file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '.' . $file_extension;
    $upload_path = UPLOAD_DIR . 'invoices/' . $filename;
    
    if (move_uploaded_file($invoice_file['tmp_name'], $upload_path)) {
        $invoice_id = uniqid();
        
        $new_invoice = [
            'id' => $invoice_id,
            'invoice_number' => $data['invoice_number'],
            'company_name' => $data['company_name'],
            'amount' => $data['amount'],
            'date' => $data['date'],
            'store_name' => $data['store_name'],
            'workshop_name' => $data['workshop_name'],
            'description' => $data['description'] ?? '',
            'image_path' => $filename,
            'additional_file' => '',
            'status' => 'pending',
            'created_by' => $_SESSION['user_id'],
            'current_user_id' => $_SESSION['user_id'],
            'created_at' => time(),
            'tracking_history' => [
                [
                    'action' => 'create',
                    'user_id' => $_SESSION['user_id'],
                    'timestamp' => time(),
                    'description' => 'ایجاد فاکتور جدید'
                ]
            ]
        ];
        
        // آپلود فایل پیوست اگر وجود دارد
        if (isset($_FILES['additional_file']) && $_FILES['additional_file']['error'] === UPLOAD_ERR_OK) {
            $additional_file = $_FILES['additional_file'];
            $additional_extension = pathinfo($additional_file['name'], PATHINFO_EXTENSION);
            $additional_filename = time() . '_' . $additional_file['name'];
            $additional_upload_path = UPLOAD_DIR . 'invoices/' . $additional_filename;
            
            if (move_uploaded_file($additional_file['tmp_name'], $additional_upload_path)) {
                $new_invoice['additional_file'] = $additional_filename;
            }
        }
        
        $invoices[] = $new_invoice;
        saveData('invoices', $invoices);
        
        return $invoice_id;
    }
    
    return false;
}

// تابع برای ارجاع فاکتور
function referInvoice($invoice_id, $to_user_id, $description, $attachment = null) {
    $invoices = loadData('invoices');
    $users = loadData('users');
    
    foreach ($invoices as &$invoice) {
        if ($invoice['id'] === $invoice_id) {
            $invoice['current_user_id'] = $to_user_id;
            $invoice['status'] = 'referred';
            $invoice['tracking_history'][] = [
                'action' => 'refer',
                'user_id' => $_SESSION['user_id'],
                'to_user_id' => $to_user_id,
                'timestamp' => time(),
                'description' => $description,
                'attachment' => $attachment
            ];
            
            // ارسال نوتیفیکیشن
            sendNotification(
                $to_user_id,
                "فاکتور جدید به شما ارجاع داده شد: {$invoice['invoice_number']}",
                $invoice_id
            );
            
            break;
        }
    }
    
    return saveData('invoices', $invoices);
}

// تابع برای دریافت فاکتور
function receiveInvoice($invoice_id, $description) {
    $invoices = loadData('invoices');
    
    foreach ($invoices as &$invoice) {
        if ($invoice['id'] === $invoice_id) {
            $invoice['status'] = 'in-progress';
            $invoice['tracking_history'][] = [
                'action' => 'receive',
                'user_id' => $_SESSION['user_id'],
                'timestamp' => time(),
                'description' => $description
            ];
            break;
        }
    }
    
    return saveData('invoices', $invoices);
}

// تابع برای تکمیل فاکتور
function completeInvoice($invoice_id) {
    $invoices = loadData('invoices');
    
    foreach ($invoices as &$invoice) {
        if ($invoice['id'] === $invoice_id) {
            $invoice['status'] = 'completed';
            $invoice['tracking_history'][] = [
                'action' => 'complete',
                'user_id' => $_SESSION['user_id'],
                'timestamp' => time(),
                'description' => 'تکمیل فاکتور'
            ];
            break;
        }
    }
    
    return saveData('invoices', $invoices);
}

// تابع برای محاسبه روزهای باقیمانده
function getRemainingDays($invoice) {
    $created_timestamp = $invoice['created_at'];
    $deadline_timestamp = $created_timestamp + (10 * 24 * 60 * 60); // 10 روز مهلت
    $remaining_seconds = $deadline_timestamp - time();
    $remaining_days = ceil($remaining_seconds / (24 * 60 * 60));
    
    return max(0, $remaining_days);
}

// این تابع حذف شده چون در functions.php تعریف شده است
// function getInvoiceApprovalHistory($invoice_id) { ... }

// این تابع حذف شده چون در functions.php تعریف شده است  
// function hasUserApprovedInvoice($user_id, $invoice_id) { ... }

// تابع برای دریافت فاکتورهای یک کاربر
function getUserInvoices($user_id, $filter = 'all') {
    $invoices = loadData('invoices');
    $filtered = [];
    
    foreach ($invoices as $invoice) {
        $is_creator = $invoice['created_by'] === $user_id;
        $is_current = $invoice['current_user_id'] === $user_id;
        
        switch ($filter) {
            case 'created':
                if ($is_creator) $filtered[] = $invoice;
                break;
            case 'assigned':
                if ($is_current) $filtered[] = $invoice;
                break;
            case 'my':
                if ($is_creator || $is_current) $filtered[] = $invoice;
                break;
            case 'pending':
                if (($is_creator || $is_current) && $invoice['status'] === 'pending') {
                    $filtered[] = $invoice;
                }
                break;
            case 'completed':
                if (($is_creator || $is_current) && $invoice['status'] === 'completed') {
                    $filtered[] = $invoice;
                }
                break;
            default:
                if ($is_creator || $is_current) $filtered[] = $invoice;
                break;
        }
    }
    
    return $filtered;
}

// تابع برای جستجو در فاکتورها بر اساس شماره یا شرکت
function searchInvoicesByNumberOrCompany($search_term) {
    $invoices = loadData('invoices');
    $results = [];
    
    foreach ($invoices as $invoice) {
        if (stripos($invoice['invoice_number'], $search_term) !== false ||
            stripos($invoice['company_name'], $search_term) !== false ||
            stripos($invoice['store_name'], $search_term) !== false) {
            $results[] = $invoice;
        }
    }
    
    return $results;
}

// ========== توابع جدید برای ارتباط با سیستم تأیید سلسله‌مراتبی ==========

/**
 * بررسی آیا فاکتور در سیستم زنجیره تأیید است
 */
function isInvoiceInApprovalChain($invoice_id) {
    require_once 'approval-system.php';
    $chain = ApprovalSystem::getInvoiceChain($invoice_id);
    return $chain !== null;
}

/**
 * دریافت وضعیت زنجیره تأیید فاکتور
 */
function getInvoiceChainStatus($invoice_id) {
    require_once 'approval-system.php';
    $chain = ApprovalSystem::getInvoiceChain($invoice_id);
    
    if (!$chain) {
        return [
            'in_chain' => false,
            'status' => 'no_chain',
            'message' => 'فاقد زنجیره تأیید'
        ];
    }
    
    $progress = ApprovalSystem::getChainProgress($chain['id']);
    
    return [
        'in_chain' => true,
        'chain_id' => $chain['id'],
        'status' => $chain['status'],
        'current_stage' => $chain['current_stage'],
        'total_stages' => count($chain['stages']),
        'progress' => $progress,
        'supervisor_id' => $chain['supervisor_id'],
        'deadlines' => $chain['deadlines']
    ];
}

/**
 * ارجاع فاکتور به زنجیره تأیید
 */
function referToApprovalChain($invoice_id, $chain_data) {
    require_once 'approval-system.php';
    
    // بررسی آیا فاکتور قبلاً در زنجیره است
    if (isInvoiceInApprovalChain($invoice_id)) {
        return ['success' => false, 'message' => 'فاکتور قبلاً در زنجیره تأیید است'];
    }
    
    // ایجاد زنجیره جدید
    $chain_id = ApprovalSystem::createApprovalChain($invoice_id, $chain_data);
    
    if ($chain_id) {
        // به‌روزرسانی وضعیت فاکتور
        $invoices = loadData('invoices');
        foreach ($invoices as &$invoice) {
            if ($invoice['id'] === $invoice_id) {
                $invoice['status'] = 'in-approval-chain';
                $invoice['approval_chain_id'] = $chain_id;
                break;
            }
        }
        saveData('invoices', $invoices);
        
        // ارسال نوتیفیکیشن به کاربران اولین مرحله
        $chain = ApprovalSystem::getInvoiceChain($invoice_id);
        $first_stage_users = $chain['stages'][0]['users'] ?? [];
        
        foreach ($first_stage_users as $user_id) {
            sendNotification(
                $user_id,
                "📋 فاکتور جدید برای تأیید به شما اختصاص داده شد",
                $invoice_id
            );
        }
        
        return ['success' => true, 'chain_id' => $chain_id, 'message' => 'فاکتور با موفقیت به زنجیره تأیید ارجاع شد'];
    }
    
    return ['success' => false, 'message' => 'خطا در ایجاد زنجیره تأیید'];
}

/**
 * دریافت لیست فاکتورهای در انتظار تأیید کاربر
 */
function getPendingApprovalInvoices($user_id) {
    $all_invoices = loadData('invoices');
    $pending_invoices = [];
    
    foreach ($all_invoices as $invoice) {
        if ($invoice['status'] === 'in-approval-chain') {
            $chain_status = getInvoiceChainStatus($invoice['id']);
            
            if ($chain_status['in_chain'] && 
                $chain_status['status'] === 'pending' &&
                isset($chain_status['progress']['current_stage_users'])) {
                
                // بررسی آیا کاربر در مرحله فعلی است
                if (in_array($user_id, $chain_status['progress']['current_stage_users'])) {
                    // بررسی آیا کاربر قبلاً تأیید کرده
                    $has_approved = hasUserApprovedInvoice($user_id, $invoice['id']);
                    
                    if (!$has_approved) {
                        $invoice['chain_info'] = $chain_status;
                        $pending_invoices[] = $invoice;
                    }
                }
            }
        }
    }
    
    return $pending_invoices;
}

/**
 * تأیید فاکتور در سیستم زنجیره‌ای
 */
function approveInvoiceInChain($invoice_id, $user_id, $selected_options, $custom_options = [], $notes = '') {
    require_once 'approval-system.php';
    
    // بررسی دسترسی کاربر
    $chain_status = getInvoiceChainStatus($invoice_id);
    if (!$chain_status['in_chain']) {
        return ['success' => false, 'message' => 'فاکتور در زنجیره تأیید نیست'];
    }
    
    // بررسی آیا کاربر در مرحله فعلی است
    if (!in_array($user_id, $chain_status['progress']['current_stage_users'])) {
        return ['success' => false, 'message' => 'شما مجاز به تأیید این فاکتور نیستید'];
    }
    
    // ثبت تأییدیه
    $result = ApprovalSystem::submitApproval(
        $invoice_id, 
        $user_id, 
        $selected_options, 
        $custom_options, 
        $notes
    );
    
    return $result;
}

?>