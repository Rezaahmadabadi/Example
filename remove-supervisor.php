<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

if (!isAdmin()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز']);
    exit();
}

$user_id = $_GET['id'] ?? null;
if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'شناسه کاربر مشخص نشده']);
    exit();
}

$users = loadData('users');
$updated = false;

foreach ($users as &$user) {
    if ($user['id'] === $user_id) {
        unset($user['is_supervisor']);
        unset($user['supervisor_permissions']);
        unset($user['supervisor_since']);
        $updated = true;
        break;
    }
}

if ($updated) {
    saveData('users', $users);
    echo json_encode([
        'success' => true,
        'message' => 'سرپرست با موفقیت حذف شد'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'کاربر یافت نشد'
    ]);
}
?>