<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'includes/approval-system.php';

if (!isAdmin() && !isSupervisor()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['invoice_id']) || !isset($data['stages'])) {
    echo json_encode(['success' => false, 'message' => 'داده‌های ناقص']);
    exit();
}

$chain_data = [
    'stages' => $data['stages'],
    'supervisor_id' => $data['supervisor_id'] ?? null
];

$result = ApprovalSystem::createApprovalChain($data['invoice_id'], $chain_data);

if ($result) {
    echo json_encode([
        'success' => true,
        'message' => 'زنجیره تأیید با موفقیت ایجاد شد',
        'chain_id' => $result
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'خطا در ایجاد زنجیره تأیید'
    ]);
}
?>