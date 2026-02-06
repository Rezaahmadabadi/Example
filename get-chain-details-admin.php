<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'includes/approval-system.php';

if (!isAdmin()) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'دسترسی غیرمجاز']);
    exit();
}

$chain_id = $_GET['id'] ?? null;
if (!$chain_id) {
    echo json_encode(['error' => 'شناسه زنجیره مشخص نشده']);
    exit();
}

$chains_data = loadData('approval-chains');
$chain = $chains_data['chains'][$chain_id] ?? null;

if (!$chain) {
    echo json_encode(['error' => 'زنجیره یافت نشد']);
    exit();
}

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

$response = [
    'success' => true,
    'chain_id' => $chain_id,
    'invoice_id' => $chain['invoice_id'],
    'invoice_number' => $invoice ? $invoice['invoice_number'] : 'نامشخص',
    'company_name' => $invoice ? $invoice['company_name'] : 'نامشخص',
    'status' => $chain['status'],
    'current_stage' => $chain['current_stage'],
    'total_stages' => count($chain['stages']),
    'completed_stages' => count($chain['completed_stages'] ?? []),
    'progress_percentage' => $progress['progress_percentage'] ?? 0,
    'supervisor_id' => $chain['supervisor_id'],
    'stages' => $chain['stages'],
    'created_at' => $chain['created_at']
];

header('Content-Type: application/json');
echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>