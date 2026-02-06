<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'includes/approval-system.php';

if (!isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit();
}

$chains_data = loadData('approval-chains');
$chains = $chains_data['chains'] ?? [];

$result = [];
foreach ($chains as $chain_id => $chain) {
    // دریافت اطلاعات فاکتور
    $invoices = loadData('invoices');
    $invoice = null;
    foreach ($invoices as $inv) {
        if ($inv['id'] === $chain['invoice_id']) {
            $invoice = $inv;
            break;
        }
    }
    
    // محاسبه پیشرفت
    $progress = ApprovalSystem::getChainProgress($chain_id);
    
    $result[] = [
        'id' => $chain_id,
        'invoice_id' => $chain['invoice_id'],
        'invoice_number' => $invoice ? $invoice['invoice_number'] : 'نامشخص',
        'company_name' => $invoice ? $invoice['company_name'] : 'نامشخص',
        'total_stages' => count($chain['stages']),
        'current_stage' => $chain['current_stage'],
        'progress' => $progress['progress_percentage'] ?? 0,
        'status' => $chain['status'],
        'created_at' => $chain['created_at'],
        'supervisor_id' => $chain['supervisor_id']
    ];
}

// مرتب‌سازی بر اساس تاریخ ایجاد (جدیدترین اول)
usort($result, function($a, $b) {
    return $b['created_at'] - $a['created_at'];
});

header('Content-Type: application/json');
echo json_encode($result, JSON_UNESCAPED_UNICODE);
?>