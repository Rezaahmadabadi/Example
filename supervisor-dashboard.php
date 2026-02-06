<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'includes/approval-system.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// فقط سرپرستان و ادمین‌ها می‌توانند وارد شوند
if (!isSupervisor() && !isAdmin()) {
    header('Location: dashboard.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// دریافت زنجیره‌های تحت نظارت
$supervisor_chains = ApprovalSystem::getSupervisorChains($user_id);

// آمار
$active_chains = 0;
$delayed_chains = 0;
$completed_chains = 0;

foreach ($supervisor_chains as $chain) {
    if ($chain['status'] === 'completed') {
        $completed_chains++;
    } else {
        $active_chains++;
        
        // بررسی تأخیر
        $progress = ApprovalSystem::getChainProgress($chain['id']);
        if ($progress && $progress['is_overdue']) {
            $delayed_chains++;
        }
    }
}

// دریافت هشدارها
$alerts = ApprovalSystem::checkDelayAlerts();
$user_alerts = array_filter($alerts, function($alert) use ($user_id) {
    $chain = ApprovalSystem::getInvoiceChainByChainId($alert['chain_id']);
    return $chain && $chain['supervisor_id'] === $user_id;
});
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>داشبورد سرپرست - سیستم تأیید سلسله‌مراتبی</title>
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .supervisor-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: rgba(255,255,255,0.05);
            border-radius: 15px;
            padding: 25px;
            border: 1px solid rgba(255,255,255,0.1);
            transition: all 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            border-color: rgba(74,158,255,0.3);
        }
        
        .alert-card {
            background: rgba(255,107,107,0.1);
            border: 1px solid rgba(255,107,107,0.3);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .chain-item {
            background: rgba(255,255,255,0.05);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            border: 1px solid rgba(255,255,255,0.1);
            transition: all 0.3s;
        }
        
        .chain-item:hover {
            background: rgba(255,255,255,0.08);
            border-color: rgba(74,158,255,0.3);
        }
    </style>
</head>
<body>
    <!-- محتوای داشبورد -->
    <div style="padding: 30px;">
        <div style="margin-bottom: 30px;">
            <h1 style="color: white; margin-bottom: 10px; font-size: 32px; font-weight: 800;">
                <i class="fas fa-user-tie"></i> داشبورد سرپرست
            </h1>
            <p style="color: rgba(255,255,255,0.7); font-size: 16px;">
                مدیریت و نظارت بر زنجیره‌های تأیید سلسله‌مراتبی
            </p>
        </div>
        
        <!-- کارت‌های آماری -->
        <div class="supervisor-stats">
            <div class="stat-card">
                <div style="font-size: 36px; color: #4a9eff; margin-bottom: 15px;">
                    <i class="fas fa-link"></i>
                </div>
                <div style="font-size: 32px; color: white; font-weight: 800; margin-bottom: 10px;">
                    <?php echo count($supervisor_chains); ?>
                </div>
                <div style="color: rgba(255,255,255,0.7);">کل زنجیره‌ها</div>
            </div>
            
            <div class="stat-card">
                <div style="font-size: 36px; color: #34C759; margin-bottom: 15px;">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div style="font-size: 32px; color: white; font-weight: 800; margin-bottom: 10px;">
                    <?php echo $completed_chains; ?>
                </div>
                <div style="color: rgba(255,255,255,0.7);">تکمیل شده</div>
            </div>
            
            <div class="stat-card">
                <div style="font-size: 36px; color: #ffc107; margin-bottom: 15px;">
                    <i class="fas fa-sync-alt"></i>
                </div>
                <div style="font-size: 32px; color: white; font-weight: 800; margin-bottom: 10px;">
                    <?php echo $active_chains; ?>
                </div>
                <div style="color: rgba(255,255,255,0.7);">در حال بررسی</div>
            </div>
            
            <div class="stat-card">
                <div style="font-size: 36px; color: #ff6b6b; margin-bottom: 15px;">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div style="font-size: 32px; color: white; font-weight: 800; margin-bottom: 10px;">
                    <?php echo $delayed_chains; ?>
                </div>
                <div style="color: rgba(255,255,255,0.7);">دارای تأخیر</div>
            </div>
        </div>
        
        <!-- هشدارها -->
        <?php if (!empty($user_alerts)): ?>
        <div style="margin-bottom: 30px;">
            <h2 style="color: white; margin-bottom: 20px; font-size: 24px; display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-bell"></i> هشدارهای تأخیر
            </h2>
            <?php foreach ($user_alerts as $alert): ?>
            <div class="alert-card">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px;">
                    <div>
                        <h3 style="color: white; margin: 0 0 10px 0; font-size: 18px;">
                            <i class="fas fa-exclamation-circle"></i> زنجیره تأخیردار
                        </h3>
                        <p style="color: rgba(255,255,255,0.8); margin: 0; font-size: 14px;">
                            شناسه فاکتور: <?php echo $alert['invoice_id']; ?> | 
                            بیش از <?php echo $alert['overdue_hours']; ?> ساعت تأخیر
                        </p>
                    </div>
                    <button onclick="viewChainDetails('<?php echo $alert['chain_id']; ?>')" 
                            class="btn btn-primary" style="padding: 8px 16px;">
                        <i class="fas fa-eye"></i> مشاهده
                    </button>
                </div>
                <div style="color: rgba(255,255,255,0.7); font-size: 13px;">
                    <i class="fas fa-users"></i> کاربران مرحله فعلی: 
                    <?php 
                    $users = array_map(function($user_id) {
                        $user = getUser($user_id);
                        return $user ? $user['username'] : 'نامشخص';
                    }, $alert['stage_users']);
                    echo implode('، ', $users);
                    ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <!-- لیست زنجیره‌ها -->
        <div style="margin-bottom: 30px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="color: white; margin: 0; font-size: 24px; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-list"></i> زنجیره‌های تحت نظارت
                </h2>
                <span style="color: rgba(255,255,255,0.7); font-size: 14px;">
                    <?php echo count($supervisor_chains); ?> مورد
                </span>
            </div>
            
            <?php if (empty($supervisor_chains)): ?>
                <div style="text-align: center; padding: 40px; color: rgba(255,255,255,0.7);">
                    <div style="font-size: 48px; margin-bottom: 20px; opacity: 0.5;">
                        ⛓️
                    </div>
                    <h3 style="color: white; margin-bottom: 10px;">هیچ زنجیره‌ای تحت نظارت شما نیست</h3>
                    <p style="margin-bottom: 25px;">زنجیره‌ها توسط ادمین به شما اختصاص داده می‌شوند</p>
                </div>
            <?php else: ?>
                <div>
                    <?php foreach ($supervisor_chains as $chain_id => $chain): 
                        $progress = ApprovalSystem::getChainProgress($chain_id);
                        $invoice = null;
                        $invoices = loadData('invoices');
                        foreach ($invoices as $inv) {
                            if ($inv['id'] === $chain['invoice_id']) {
                                $invoice = $inv;
                                break;
                            }
                        }
                    ?>
                    <div class="chain-item">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px;">
                            <div>
                                <h3 style="color: white; margin: 0 0 10px 0; font-size: 18px;">
                                    <?php echo $invoice ? $invoice['invoice_number'] : 'بدون شماره'; ?>
                                    <span style="font-size: 14px; color: rgba(255,255,255,0.6); margin-right: 10px;">
                                        - <?php echo $invoice ? $invoice['company_name'] : ''; ?>
                                    </span>
                                </h3>
                                <p style="color: rgba(255,255,255,0.7); margin: 0; font-size: 14px;">
                                    <i class="fas fa-layer-group"></i> 
                                    مرحله <?php echo $progress['current_stage'] + 1; ?> از <?php echo $progress['total_stages']; ?> |
                                    <i class="fas fa-calendar"></i> 
                                    ایجاد: <?php echo convertToJalali($chain['created_at']); ?>
                                </p>
                            </div>
                            <div>
                                <span style="background: <?php echo $chain['status'] === 'completed' ? 'rgba(52,199,89,0.2)' : 'rgba(74,158,255,0.2)'; ?>; 
                                      color: <?php echo $chain['status'] === 'completed' ? '#34C759' : '#4a9eff'; ?>; 
                                      padding: 6px 16px; border-radius: 20px; font-size: 13px; font-weight: 600; border: 1px solid <?php echo $chain['status'] === 'completed' ? 'rgba(52,199,89,0.3)' : 'rgba(74,158,255,0.3)'; ?>;">
                                    <?php echo $chain['status'] === 'completed' ? '✅ تکمیل شده' : '⏳ در حال بررسی'; ?>
                                </span>
                            </div>
                        </div>
                        
                        <!-- نوار پیشرفت -->
                        <div style="margin-bottom: 15px;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                <span style="color: rgba(255,255,255,0.8); font-size: 13px;">پیشرفت</span>
                                <span style="color: #4a9eff; font-weight: 600; font-size: 13px;">
                                    <?php echo $progress['progress_percentage'] ?? 0; ?>%
                                </span>
                            </div>
                            <div style="height: 8px; background: rgba(255,255,255,0.1); border-radius: 4px; overflow: hidden;">
                                <div style="height: 100%; width: <?php echo $progress['progress_percentage'] ?? 0; ?>%; 
                                     background: linear-gradient(90deg, #4a9eff, #6f42c1); border-radius: 4px;"></div>
                            </div>
                        </div>
                        
                        <!-- اطلاعات مرحله فعلی -->
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                            <div>
                                <div style="color: rgba(255,255,255,0.7); font-size: 12px; margin-bottom: 5px;">کاربران مرحله فعلی:</div>
                                <div style="display: flex; flex-wrap: wrap; gap: 6px;">
                                    <?php 
                                    $current_stage_users = $progress['current_stage_users'] ?? [];
                                    foreach ($current_stage_users as $user_id): 
                                        $user = getUser($user_id);
                                        if ($user): 
                                    ?>
                                    <span style="background: rgba(74,158,255,0.2); color: #4a9eff; padding: 4px 8px; 
                                          border-radius: 15px; font-size: 11px; border: 1px solid rgba(74,158,255,0.3);">
                                        <?php echo $user['username']; ?>
                                    </span>
                                    <?php endif; endforeach; ?>
                                </div>
                            </div>
                            <div>
                                <div style="color: rgba(255,255,255,0.7); font-size: 12px; margin-bottom: 5px;">مهلت باقیمانده:</div>
                                <div style="color: <?php echo $progress['is_overdue'] ? '#ff6b6b' : '#ffc107'; ?>; 
                                     font-weight: 600; padding: 6px 12px; background: <?php echo $progress['is_overdue'] ? 'rgba(255,107,107,0.2)' : 'rgba(255,193,7,0.2)'; ?>; 
                                     border-radius: 8px; border: 1px solid <?php echo $progress['is_overdue'] ? 'rgba(255,107,107,0.3)' : 'rgba(255,193,7,0.3)'; ?>; display: inline-block;">
                                    <?php 
                                    if ($progress['is_overdue']) {
                                        echo 'تأخیر: ' . abs($progress['remaining_days']) . ' روز';
                                    } elseif (isset($progress['remaining_days'])) {
                                        echo $progress['remaining_days'] . ' روز باقیمانده';
                                    } else {
                                        echo 'نامشخص';
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- دکمه‌های اقدام -->
                        <div style="display: flex; gap: 10px;">
                            <button onclick="window.location.href='get-invoice-details.php?id=<?php echo $chain['invoice_id']; ?>'" 
                                    class="btn btn-outline" style="padding: 8px 16px;">
                                <i class="fas fa-eye"></i> مشاهده جزئیات
                            </button>
                            <button onclick="openSupervisorActions('<?php echo $chain['invoice_id']; ?>')" 
                                    class="btn btn-primary" style="padding: 8px 16px;">
                                <i class="fas fa-user-tie"></i> اقدامات سرپرستی
                            </button>
                            <?php if ($progress['is_overdue']): ?>
                            <button onclick="sendReminder('<?php echo $chain['invoice_id']; ?>')" 
                                    class="btn btn-success" style="padding: 8px 16px;">
                                <i class="fas fa-bell"></i> ارسال یادآوری
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
    function viewChainDetails(chainId) {
        window.open(`chain-details.php?id=${chainId}`, '_blank');
    }
    
    function openSupervisorActions(invoiceId) {
        const url = `get-invoice-details.php?id=${invoiceId}#supervisor-section`;
        window.open(url, '_blank');
    }
    
    function sendReminder(invoiceId) {
        if (confirm('آیا می‌خواهید یادآوری برای کاربران مرحله فعلی ارسال شود؟')) {
            fetch(`send-reminder.php?invoice_id=${invoiceId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(`✅ یادآوری به ${data.sent_count} کاربر ارسال شد`);
                } else {
                    alert('❌ خطا در ارسال یادآوری');
                }
            });
        }
    }
    </script>
</body>
</html>