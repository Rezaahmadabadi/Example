<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

if (!isAdmin()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'داده‌های ناقص']);
    exit();
}

$users = loadData('users');
$updated = false;

foreach ($users as &$user) {
    if ($user['id'] === $data['user_id']) {
        $user['is_supervisor'] = true;
        $user['supervisor_permissions'] = $data['permissions'] ?? [];
        $user['supervisor_since'] = time();
        $updated = true;
        break;
    }
}

if ($updated) {
    saveData('users', $users);
    echo json_encode([
        'success' => true,
        'message' => 'سرپرست با موفقیت اضافه شد'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'کاربر یافت نشد'
    ]);
}
?>