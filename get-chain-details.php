<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'includes/approval-system.php';

if (!isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'دسترسی غیرمجاز']);
    exit();
}

$invoice_id = $_GET['invoice_id'] ?? null;
if (!$invoice_id) {
    echo json_encode(['error' => 'شناسه فاکتور مشخص نشده']);
    exit();
}

$chain = ApprovalSystem::getInvoiceChain($invoice_id);
if (!$chain) {
    echo json_encode(['error' => 'زنجیره یافت نشد']);
    exit();
}

$progress = ApprovalSystem::getChainProgress($chain['id']);

// دریافت کاربران تأیید کرده
$approved_users = [];
$all_approvals = loadData('invoice-approvals');
if (is_array($all_approvals)) {
    foreach ($all_approvals as $approval) {
        if ($approval['invoice_id'] === $invoice_id) {
            $approved_users[] = $approval['user_id'];
        }
    }
}

// اطلاعات کاربران مرحله فعلی
$current_stage_users = [];
$current_stage = $progress['current_stage'] ?? 0;
if (isset($chain['stages'][$current_stage]['users'])) {
    foreach ($chain['stages'][$current_stage]['users'] as $user_id) {
        $user = getUser($user_id);
        if ($user) {
            $current_stage_users[] = [
                'id' => $user['id'],
                'username' => $user['username'],
                'department' => $user['department'],
                'has_approved' => in_array($user_id, $approved_users)
            ];
        }
    }
}

$response = [
    'success' => true,
    'chain_id' => $chain['id'],
    'invoice_id' => $chain['invoice_id'],
    'current_stage' => $current_stage,
    'total_stages' => count($chain['stages']),
    'status' => $chain['status'],
    'progress_percentage' => $progress['progress_percentage'] ?? 0,
    'current_stage_users' => $current_stage_users,
    'approved_users' => $approved_users,
    'is_overdue' => $progress['is_overdue'] ?? false,
    'overdue_hours' => $progress['is_overdue'] ? ceil((time() - ($progress['current_stage_deadline'] ?? time())) / 3600) : 0,
    'supervisor_id' => $chain['supervisor_id']
];

header('Content-Type: application/json');
echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>