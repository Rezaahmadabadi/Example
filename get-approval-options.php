<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit();
}

$user_id = $_GET['user_id'] ?? $_SESSION['user_id'];
$user = getUser($user_id);

if (!$user) {
    echo json_encode([]);
    exit();
}

$options = getApprovalOptions($user['department']);

header('Content-Type: application/json');
echo json_encode($options, JSON_UNESCAPED_UNICODE);
?>