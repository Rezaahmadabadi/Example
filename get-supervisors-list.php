<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'includes/approval-system.php';

if (!isAdmin()) {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit();
}

$users = loadData('users');
$chains_data = loadData('approval-chains');
$chains = $chains_data['chains'] ?? [];

// فیلتر کردن سرپرستان
$supervisors = array_filter($users, function($user) {
    return isset($user['is_supervisor']) && $user['is_supervisor'];
});

$result = [];
foreach ($supervisors as $supervisor) {
    // آمار زنجیره‌های این سرپرست
    $supervisor_chains = [];
    foreach ($chains as $chain) {
        if ($chain['supervisor_id'] === $supervisor['id']) {
            $supervisor_chains[] = $chain;
        }
    }
    
    $completed_chains = count(array_filter($supervisor_chains, function($chain) {
        return $chain['status'] === 'completed';
    }));
    
    $active_chains = count(array_filter($supervisor_chains, function($chain) {
        return $chain['status'] !== 'completed';
    }));
    
    // بررسی تأخیر
    $delayed_chains = 0;
    foreach ($supervisor_chains as $chain) {
        if ($chain['status'] !== 'completed') {
            $progress = ApprovalSystem::getChainProgress($chain['id']);
            if ($progress && $progress['is_overdue']) {
                $delayed_chains++;
            }
        }
    }
    
    $result[] = [
        'id' => $supervisor['id'],
        'username' => $supervisor['username'],
        'email' => $supervisor['email'] ?? '',
        'department' => $supervisor['department'],
        'chain_count' => count($supervisor_chains),
        'completed_chains' => $completed_chains,
        'active_chains' => $active_chains,
        'delayed_chains' => $delayed_chains
    ];
}

header('Content-Type: application/json');
echo json_encode($result, JSON_UNESCAPED_UNICODE);
?>