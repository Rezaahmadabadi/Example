<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

if (!isAdmin()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز']);
    exit();
}

$chain_id = $_GET['id'] ?? null;
if (!$chain_id) {
    echo json_encode(['success' => false, 'message' => 'شناسه زنجیره مشخص نشده']);
    exit();
}

$chains_data = loadData('approval-chains');

if (!isset($chains_data['chains'][$chain_id])) {
    echo json_encode(['success' => false, 'message' => 'زنجیره یافت نشد']);
    exit();
}

// حذف زنجیره
unset($chains_data['chains'][$chain_id]);

// حذف از فهرست invoice_chains
foreach ($chains_data['invoice_chains'] as $invoice_id => $cid) {
    if ($cid === $chain_id) {
        unset($chains_data['invoice_chains'][$invoice_id]);
        break;
    }
}

// حذف گزینه‌های سفارشی
unset($chains_data['custom_options'][$chain_id]);

// حذف لاگ‌ها
unset($chains_data['chain_logs'][$chain_id]);

// حذف از سرپرستان
unset($chains_data['supervisors'][$chain_id]);

if (saveData('approval-chains', $chains_data)) {
    echo json_encode([
        'success' => true,
        'message' => 'زنجیره با موفقیت حذف شد'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'خطا در حذف زنجیره'
    ]);
}
?>