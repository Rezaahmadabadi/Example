<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/invoice-functions.php';

if (!isLoggedIn()) {
    exit('دسترسی غیرمجاز');
}

if (!isset($_GET['id'])) {
    exit('شناسه فاکتور مشخص نشده');
}

$invoice_id = $_GET['id'];
$invoices = loadData('invoices');
$users = loadData('users');

$invoice = null;
foreach ($invoices as $inv) {
    if ($inv['id'] === $invoice_id) {
        $invoice = $inv;
        break;
    }
}

if (!$invoice) {
    exit('فاکتور یافت نشد');
}

$created_by = getUser($invoice['created_by']);
$current_user = getUser($invoice['current_user_id']);
$remaining_days = getRemainingDays($invoice);

// جمع‌آوری همه پیوست‌های ارجاع از تاریخچه
$refer_attachments = [];
foreach ($invoice['tracking_history'] as $history) {
    if ($history['action'] === 'refer' && isset($history['attachment'])) {
        $refer_attachments[] = $history['attachment'];
    }
}

// دریافت تأییدیه‌های فاکتور
$approvals = [];
if (function_exists('getInvoiceApprovalHistory')) {
    $approvals = getInvoiceApprovalHistory($invoice_id);
} else {
    // تابع جایگزین اگر وجود نداشت
    $all_approvals = loadData('invoice-approvals');
    if (is_array($all_approvals)) {
        $approvals = array_filter($all_approvals, function($approval) use ($invoice_id) {
            return $approval['invoice_id'] === $invoice_id;
        });
        usort($approvals, function($a, $b) {
            return $b['timestamp'] - $a['timestamp'];
        });
    }
}
?>
<div style="padding: 20px;">
    <!-- هدر -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid rgba(255,255,255,0.1);">
        <div>
            <h2 style="color: white; font-weight: 700; margin: 0 0 10px 0;">مشاهده جزئیات فاکتور</h2>
            <p style="color: rgba(255,255,255,0.7); margin: 0; font-size: 14px;">شماره فاکتور: <strong style="color: #4a9eff;"><?php echo $invoice['invoice_number']; ?></strong></p>
        </div>
        <div>
            <span class="status-badge status-<?php echo $invoice['status']; ?>" style="font-size: 14px; padding: 8px 20px;">
                <?php 
                $status_text = [
                    'pending' => 'در انتظار',
                    'in-progress' => 'در حال پیگیری', 
                    'referred' => 'ارجاع شده',
                    'completed' => 'تکمیل شده'
                ];
                echo $status_text[$invoice['status']];
                ?>
            </span>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px;">
        <!-- اطلاعات اصلی -->
        <div style="background: rgba(255,255,255,0.05); border-radius: 15px; padding: 20px; border: 1px solid rgba(255,255,255,0.1);">
            <h4 style="color: white; margin-top: 0; margin-bottom: 20px; font-size: 18px; display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-info-circle" style="color: #4a9eff;"></i> اطلاعات فاکتور
            </h4>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">شماره فاکتور:</span>
                    <span class="info-value"><?php echo $invoice['invoice_number']; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">نام شرکت:</span>
                    <span class="info-value"><?php echo $invoice['company_name']; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">مبلغ فاکتور:</span>
                    <span class="info-value" style="color: #51cf66; font-weight: bold;"><?php echo formatPrice($invoice['amount']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">تاریخ فاکتور:</span>
                    <span class="info-value"><?php echo $invoice['date']; ?></span>
                </div>
            </div>
        </div>

        <!-- اطلاعات پیگیری -->
        <div style="background: rgba(255,255,255,0.05); border-radius: 15px; padding: 20px; border: 1px solid rgba(255,255,255,0.1);">
            <h4 style="color: white; margin-top: 0; margin-bottom: 20px; font-size: 18px; display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-chart-line" style="color: #4a9eff;"></i> اطلاعات پیگیری
            </h4>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">فروشگاه/فروشنده:</span>
                    <span class="info-value"><?php echo $invoice['store_name']; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">کارگاه/دفتر:</span>
                    <span class="info-value"><?php echo $invoice['workshop_name']; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">ایجاد کننده:</span>
                    <span class="info-value">
                        <?php if ($created_by): ?>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <div style="width: 24px; height: 24px; border-radius: 50%; background: linear-gradient(135deg, #4a9eff, #6f42c1); display: flex; align-items: center; justify-content: center; color: white; font-size: 12px;">
                                    <?php echo strtoupper(substr($created_by['username'], 0, 1)); ?>
                                </div>
                                <?php echo $created_by['username']; ?>
                            </div>
                        <?php else: ?>
                            نامشخص
                        <?php endif; ?>
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label">کاربر فعلی:</span>
                    <span class="info-value">
                        <?php if ($current_user): ?>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <div style="width: 24px; height: 24px; border-radius: 50%; background: linear-gradient(135deg, <?php echo $current_user['id'] === $_SESSION['user_id'] ? '#4a9eff' : '#ffc107'; ?>, #6f42c1); display: flex; align-items: center; justify-content: center; color: white; font-size: 12px;">
                                    <?php echo strtoupper(substr($current_user['username'], 0, 1)); ?>
                                </div>
                                <?php echo $current_user['username']; ?>
                                <?php if ($current_user['id'] !== $invoice['created_by']): ?>
                                    <span style="background: rgba(255,193,7,0.2); color: #ffc107; padding: 2px 8px; border-radius: 10px; font-size: 11px; border: 1px solid rgba(255,193,7,0.3);">
                                        ارجاعی
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            نامشخص
                        <?php endif; ?>
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label">مهلت باقیمانده:</span>
                    <span class="info-value">
                        <span style="color: <?php 
                            if ($remaining_days <= 2) echo '#ff6b6b';
                            elseif ($remaining_days <= 5) echo '#ffc107';
                            else echo '#51cf66';
                        ?>; font-weight: bold; padding: 4px 12px; border-radius: 20px; background: <?php 
                            if ($remaining_days <= 2) echo 'rgba(255,107,107,0.2)';
                            elseif ($remaining_days <= 5) echo 'rgba(255,193,7,0.2)';
                            else echo 'rgba(81,207,102,0.2)';
                        ?>; border: 1px solid <?php 
                            if ($remaining_days <= 2) echo 'rgba(255,107,107,0.3)';
                            elseif ($remaining_days <= 5) echo 'rgba(255,193,7,0.3)';
                            else echo 'rgba(81,207,102,0.3)';
                        ?>;">
                            <?php echo $remaining_days; ?> روز
                        </span>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- ========== بخش جدید: وضعیت زنجیره تأیید ========== -->
    <?php if (function_exists('getInvoiceChainStatus')): 
        $chain_status = getInvoiceChainStatus($invoice['id']);
        if ($chain_status['in_chain']): ?>
        <div style="margin-bottom: 30px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h4 style="color: white; margin: 0; font-size: 18px; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-link" style="color: #4a9eff;"></i> وضعیت زنجیره تأیید سلسله‌مراتبی
                </h4>
                <span style="background: <?php echo $chain_status['status'] === 'completed' ? 'rgba(52,199,89,0.2)' : 'rgba(74,158,255,0.2)'; ?>; 
                      color: <?php echo $chain_status['status'] === 'completed' ? '#34C759' : '#4a9eff'; ?>; 
                      padding: 6px 16px; border-radius: 20px; font-size: 13px; font-weight: 600; border: 1px solid <?php echo $chain_status['status'] === 'completed' ? 'rgba(52,199,89,0.3)' : 'rgba(74,158,255,0.3)'; ?>;">
                    <?php echo $chain_status['status'] === 'completed' ? '✅ تکمیل شده' : '⏳ در حال بررسی'; ?>
                </span>
            </div>
            
            <div style="background: rgba(255,255,255,0.05); border-radius: 15px; padding: 25px; border: 1px solid rgba(255,255,255,0.1);">
                <!-- نوار پیشرفت -->
                <div style="margin-bottom: 25px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                        <span style="color: rgba(255,255,255,0.8); font-size: 14px;">پیشرفت زنجیره تأیید</span>
                        <span style="color: #4a9eff; font-weight: 600; font-size: 14px;">
                            <?php echo $chain_status['progress']['progress_percentage'] ?? 0; ?>%
                        </span>
                    </div>
                    <div style="height: 12px; background: rgba(255,255,255,0.1); border-radius: 6px; overflow: hidden;">
                        <div style="height: 100%; width: <?php echo $chain_status['progress']['progress_percentage'] ?? 0; ?>%; 
                             background: linear-gradient(90deg, #4a9eff, #6f42c1); border-radius: 6px; transition: width 0.5s ease;">
                        </div>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-top: 8px; font-size: 12px; color: rgba(255,255,255,0.6);">
                        <span>مرحله <?php echo ($chain_status['current_stage'] ?? 0) + 1; ?> از <?php echo $chain_status['total_stages'] ?? 0; ?></span>
                        <?php if (isset($chain_status['progress']['remaining_days'])): ?>
                            <span><?php echo $chain_status['progress']['remaining_days']; ?> روز باقیمانده</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- مراحل زنجیره -->
                <div style="margin-bottom: 20px;">
                    <h5 style="color: white; margin-bottom: 15px; font-size: 16px; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-list-ol"></i> مراحل زنجیره تأیید
                    </h5>
                    <div style="display: grid; gap: 12px;">
                        <?php 
                        $chain = getCachedInvoiceChain($invoice['id']);
                        if ($chain && isset($chain['stages'])): 
                            foreach ($chain['stages'] as $index => $stage): 
                                $is_current = $index === $chain_status['current_stage'];
                                $is_completed = in_array($index, $chain_status['progress']['completed_stages'] ?? []);
                                $stage_users = $stage['users'] ?? [];
                        ?>
                        <div style="display: flex; align-items: center; gap: 15px; padding: 15px; 
                             background: <?php echo $is_current ? 'rgba(74,158,255,0.1)' : ($is_completed ? 'rgba(52,199,89,0.1)' : 'rgba(255,255,255,0.05)'); ?>; 
                             border: 1px solid <?php echo $is_current ? 'rgba(74,158,255,0.3)' : ($is_completed ? 'rgba(52,199,89,0.3)' : 'rgba(255,255,255,0.1)'); ?>; 
                             border-radius: 10px; transition: all 0.3s;">
                            <div style="width: 30px; height: 30px; border-radius: 50%; 
                                 background: <?php echo $is_current ? '#4a9eff' : ($is_completed ? '#34C759' : 'rgba(255,255,255,0.2)'); ?>; 
                                 display: flex; align-items: center; justify-content: center; 
                                 color: white; font-weight: bold; font-size: 14px;">
                                <?php echo $index + 1; ?>
                            </div>
                            <div style="flex: 1;">
                                <div style="font-weight: 600; color: white; margin-bottom: 5px;">
                                    <?php echo $stage['name'] ?? "مرحله " . ($index + 1); ?>
                                    <?php if ($is_current): ?>
                                        <span style="background: rgba(74,158,255,0.2); color: #4a9eff; padding: 2px 8px; border-radius: 10px; font-size: 11px; margin-right: 10px;">
                                            <i class="fas fa-play"></i> جاری
                                        </span>
                                    <?php elseif ($is_completed): ?>
                                        <span style="background: rgba(52,199,89,0.2); color: #34C759; padding: 2px 8px; border-radius: 10px; font-size: 11px; margin-right: 10px;">
                                            <i class="fas fa-check"></i> تکمیل
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                                    <?php foreach ($stage_users as $user_id): 
                                        $stage_user = getUser($user_id);
                                        if ($stage_user): 
                                    ?>
                                    <div style="display: flex; align-items: center; gap: 6px; padding: 4px 10px; 
                                         background: rgba(255,255,255,0.1); border-radius: 15px; font-size: 12px;">
                                        <div style="width: 20px; height: 20px; border-radius: 50%; 
                                             background: <?php echo $user_id === $_SESSION['user_id'] ? 'linear-gradient(135deg, #4a9eff, #6f42c1)' : 'linear-gradient(135deg, #86868B, #6c757d)'; ?>; 
                                             display: flex; align-items: center; justify-content: center; color: white; font-size: 10px; font-weight: bold;">
                                            <?php echo strtoupper(substr($stage_user['username'], 0, 1)); ?>
                                        </div>
                                        <span style="color: <?php echo $user_id === $_SESSION['user_id'] ? '#4a9eff' : 'rgba(255,255,255,0.8)'; ?>;">
                                            <?php echo $stage_user['username']; ?>
                                        </span>
                                        <?php 
                                        // بررسی آیا کاربر تأیید کرده
                                        if ($is_current || $is_completed) {
                                            $has_approved = hasUserApprovedInvoice($user_id, $invoice['id']);
                                            if ($has_approved): ?>
                                            <span style="color: #34C759; font-size: 11px;">
                                                <i class="fas fa-check-circle"></i>
                                            </span>
                                            <?php endif;
                                        } ?>
                                    </div>
                                    <?php endif; endforeach; ?>
                                </div>
                            </div>
                            <?php if ($is_current && isset($chain_status['progress']['current_stage_deadline'])): 
                                $deadline_time = $chain_status['progress']['current_stage_deadline'];
                                $remaining_seconds = $deadline_time - time();
                                $remaining_days = floor($remaining_seconds / 86400);
                            ?>
                            <div style="padding: 8px 12px; background: <?php echo $remaining_days <= 2 ? 'rgba(255,107,107,0.2)' : 'rgba(255,193,7,0.2)'; ?>; 
                                 border-radius: 8px; border: 1px solid <?php echo $remaining_days <= 2 ? 'rgba(255,107,107,0.3)' : 'rgba(255,193,7,0.3)'; ?>;">
                                <div style="font-size: 12px; color: <?php echo $remaining_days <= 2 ? '#ff6b6b' : '#ffc107'; ?>; font-weight: 600; white-space: nowrap;">
                                    <i class="fas fa-clock"></i> 
                                    <?php echo $remaining_days > 0 ? $remaining_days . ' روز' : 'امروز'; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>
                
                <!-- گزینه‌های سفارشی -->
                <?php if ($chain && isset($chain['custom_options']) && !empty($chain['custom_options'])): ?>
                <div style="margin-bottom: 20px;">
                    <h5 style="color: white; margin-bottom: 15px; font-size: 16px; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-star"></i> گزینه‌های سفارشی اضافه شده
                    </h5>
                    <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                        <?php foreach ($chain['custom_options'] as $custom_option): 
                            $creator = getUser($custom_option['created_by']);
                        ?>
                        <div style="background: rgba(255,193,7,0.1); border: 1px solid rgba(255,193,7,0.3); 
                             border-radius: 10px; padding: 10px 15px; position: relative;">
                            <div style="font-weight: 600; color: #ffc107; margin-bottom: 5px;">
                                <i class="fas fa-plus-circle"></i> <?php echo $custom_option['text']; ?>
                            </div>
                            <div style="display: flex; align-items: center; gap: 6px; font-size: 11px; color: rgba(255,255,255,0.6);">
                                <i class="fas fa-user"></i>
                                <span><?php echo $creator ? $creator['username'] : 'نامشخص'; ?></span>
                                <span style="margin: 0 5px;">•</span>
                                <i class="fas fa-clock"></i>
                                <span><?php echo convertToJalali($custom_option['created_at']); ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- دکمه‌های اقدام -->
                <div style="display: flex; gap: 15px; flex-wrap: wrap; margin-top: 25px;">
                    <?php 
                    // بررسی آیا کاربر در مرحله فعلی است و هنوز تأیید نکرده
                    $user_in_current_stage = false;
                    if (isset($chain_status['progress']['current_stage_users'])) {
                        $user_in_current_stage = in_array($_SESSION['user_id'], $chain_status['progress']['current_stage_users']);
                    }
                    
                    $has_approved = hasUserApprovedInvoice($_SESSION['user_id'], $invoice['id']);
                    ?>
                    
                    <?php if ($user_in_current_stage && !$has_approved): ?>
                    <button onclick="openChainApprovalModal('<?php echo $invoice['id']; ?>', '<?php echo $invoice['invoice_number']; ?>')" 
                            class="btn btn-success" style="padding: 12px 20px;">
                        <i class="fas fa-check-circle"></i> تأیید در زنجیره
                    </button>
                    <?php endif; ?>
                    
                    <?php if (isSupervisor() && $chain_status['supervisor_id'] === $_SESSION['user_id']): ?>
                    <button onclick="openSupervisorActions('<?php echo $invoice['id']; ?>')" 
                            class="btn btn-primary" style="padding: 12px 20px;">
                        <i class="fas fa-user-tie"></i> اقدامات سرپرستی
                    </button>
                    <?php endif; ?>
                    
                    <?php if (isAdmin() && !$chain_status['in_chain']): ?>
                    <button onclick="openCreateChainModal('<?php echo $invoice['id']; ?>')" 
                            class="btn btn-outline" style="padding: 12px 20px;">
                        <i class="fas fa-link"></i> ایجاد زنجیره تأیید
                    </button>
                    <?php endif; ?>
                    
                    <button onclick="viewChainLogs('<?php echo $invoice['id']; ?>')" 
                            class="btn btn-outline" style="padding: 12px 20px;">
                        <i class="fas fa-history"></i> مشاهده لاگ زنجیره
                    </button>
                </div>
            </div>
        </div>
        <?php endif; endif; ?>

    <!-- تأییدیه‌های ثبت شده -->
    <?php if (!empty($approvals)): ?>
    <div style="margin-bottom: 30px;">
        <h4 style="color: white; margin-bottom: 20px; font-size: 18px; display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-check-circle" style="color: #34C759;"></i> تأییدیه‌های ثبت شده
        </h4>
        
        <div style="background: rgba(255,255,255,0.05); border-radius: 15px; padding: 25px; border: 1px solid rgba(255,255,255,0.1);">
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px;">
                <?php foreach ($approvals as $approval): 
                    $approval_user = getUser($approval['user_id']);
                ?>
                <div style="background: rgba(255,255,255,0.05); border-radius: 15px; padding: 20px; border: 1px solid rgba(255,255,255,0.1); transition: all 0.3s;"
                     onmouseover="this.style.transform='translateY(-5px)'; this.style.borderColor='rgba(52,199,89,0.3)';"
                     onmouseout="this.style.transform='translateY(0)'; this.style.borderColor='rgba(255,255,255,0.1)';">
                    <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 15px;">
                        <div style="width: 50px; height: 50px; border-radius: 50%; background: linear-gradient(135deg, #34C759, #28a745); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 18px;">
                            <?php echo $approval_user ? strtoupper(substr($approval_user['username'], 0, 1)) : '?'; ?>
                        </div>
                        <div style="flex: 1;">
                            <div style="font-weight: 600; color: white; margin-bottom: 5px; font-size: 16px;">
                                <?php echo $approval_user ? $approval_user['username'] : 'نامشخص'; ?>
                            </div>
                            <div style="color: rgba(255,255,255,0.7); font-size: 13px;">
                                <?php echo $approval['user_department'] ?? 'بدون بخش'; ?>
                                <span style="margin: 0 8px;">•</span>
                                <?php echo convertToJalali($approval['timestamp']); ?>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!empty($approval['selected_option_texts'])): ?>
                    <div style="margin-top: 15px;">
                        <div style="color: rgba(255,255,255,0.8); font-size: 14px; margin-bottom: 10px;">گزینه‌های انتخاب شده:</div>
                        <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                            <?php foreach ($approval['selected_option_texts'] as $text): ?>
                            <span style="background: rgba(52,199,89,0.2); color: #34C759; padding: 5px 10px; border-radius: 20px; font-size: 12px; border: 1px solid rgba(52,199,89,0.3);">
                                <?php echo htmlspecialchars($text); ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($approval['notes'])): ?>
                    <div style="margin-top: 15px; padding: 12px; background: rgba(255,255,255,0.05); border-radius: 8px; border-right: 3px solid #4a9eff;">
                        <div style="color: rgba(255,255,255,0.8); font-size: 13px; margin-bottom: 5px;">توضیحات:</div>
                        <div style="color: rgba(255,255,255,0.7); font-size: 14px; line-height: 1.5;">
                            <?php echo nl2br(htmlspecialchars($approval['notes'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

<!-- دکمه ارجاع فاکتور - بدون تداخل JavaScript -->
<?php if ($invoice['current_user_id'] === $_SESSION['user_id'] && $invoice['status'] !== 'completed'): ?>
<div style="margin-bottom: 20px; text-align: center;">
    <a href="refer-invoice.php?id=<?php echo $invoice['id']; ?>" 
       class="btn-refer" 
       style="display: inline-block; padding: 12px 25px; background: linear-gradient(135deg, #4a9eff 0%, #357abd 100%); 
              color: white; border: none; border-radius: 12px; font-size: 16px; font-weight: 600; 
              text-decoration: none; cursor: pointer; transition: all 0.3s;"
       onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 20px rgba(74, 158, 255, 0.3)';"
       onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
        <i class="fas fa-share-alt"></i> ارجاع فاکتور
    </a>
</div>
<?php endif; ?>

    <!-- فایل اصلی فاکتور -->
    <?php if ($invoice['image_path']): ?>
    <div style="margin-bottom: 30px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h4 style="color: white; margin: 0; font-size: 18px; display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-file-invoice" style="color: #4a9eff;"></i> فایل اصلی فاکتور
            </h4>
        </div>
        
        <div style="background: rgba(255,255,255,0.05); border-radius: 15px; padding: 25px; border: 1px solid rgba(255,255,255,0.1); text-align: center;">
            <?php
            $file_extension = pathinfo($invoice['image_path'], PATHINFO_EXTENSION);
            $is_image = in_array(strtolower($file_extension), ['jpg', 'jpeg', 'png', 'gif', 'bmp']);
            ?>
            
            <?php if ($is_image): ?>
                <img src="uploads/invoices/<?php echo $invoice['image_path']; ?>" 
                     style="max-width: 300px; max-height: 200px; border-radius: 12px; border: 2px solid rgba(74, 158, 255, 0.5); cursor: pointer; box-shadow: 0 4px 12px rgba(0,0,0,0.2);"
                     onclick="previewInvoiceFile('<?php echo $invoice['image_path']; ?>')"
                     title="کلیک برای پیش‌نمایش بزرگ">
            <?php else: ?>
                <div style="background: rgba(74,158,255,0.1); padding: 30px; border-radius: 15px; border: 2px solid rgba(74,158,255,0.3); display: inline-block; cursor: pointer; transition: all 0.3s;"
                     onclick="previewInvoiceFile('<?php echo $invoice['image_path']; ?>')"
                     onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 8px 25px rgba(0,0,0,0.2)';"
                     onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                    <div style="font-size: 48px; margin-bottom: 15px;">
                        <?php
                        $file_icon = '📄';
                        if (strtolower($file_extension) === 'pdf') $file_icon = '📕';
                        elseif (in_array(strtolower($file_extension), ['doc', 'docx'])) $file_icon = '📝';
                        elseif (in_array(strtolower($file_extension), ['xls', 'xlsx'])) $file_icon = '📊';
                        elseif (in_array(strtolower($file_extension), ['zip', 'rar'])) $file_icon = '📦';
                        echo $file_icon;
                        ?>
                    </div>
                    <div style="font-weight: 600; color: white; margin-bottom: 5px;">فایل فاکتور</div>
                    <div style="color: rgba(255,255,255,0.7); font-size: 14px;">فرمت: <?php echo strtoupper($file_extension); ?></div>
                    <div style="color: rgba(255,255,255,0.5); font-size: 12px; margin-top: 8px;">
                        <i class="fas fa-mouse-pointer"></i> کلیک برای پیش‌نمایش
                    </div>
                </div>
            <?php endif; ?>
            
            <div style="margin-top: 25px; display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
                <button onclick="previewInvoiceFile('<?php echo $invoice['image_path']; ?>')" 
                        class="btn-outline" style="padding: 10px 20px;">
                    <i class="fas fa-eye"></i> پیش‌نمایش فایل
                </button>
                <a href="download-file.php?type=invoice&file=<?php echo $invoice['image_path']; ?>&original_name=فاکتور_<?php echo $invoice['invoice_number']; ?>.<?php echo pathinfo($invoice['image_path'], PATHINFO_EXTENSION); ?>"
                   class="btn-primary" style="padding: 10px 20px; text-decoration: none;">
                    <i class="fas fa-download"></i> دانلود فایل
                </a>
                <button onclick="printFile('<?php echo $invoice['image_path']; ?>')" 
                        class="btn-success" style="padding: 10px 20px;">
                    <i class="fas fa-print"></i> پرینت
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- پیوست فاکتور -->
    <?php if (!empty($invoice['additional_file'])): ?>
    <div style="margin-bottom: 30px;">
        <h4 style="color: white; margin-bottom: 20px; font-size: 18px; display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-paperclip" style="color: #28a745;"></i> پیوست فاکتور
        </h4>
        
        <div style="background: rgba(255,255,255,0.05); border-radius: 15px; padding: 25px; border: 1px solid rgba(255,255,255,0.1); text-align: center;">
            <?php
            $additional_extension = pathinfo($invoice['additional_file'], PATHINFO_EXTENSION);
            $additional_is_image = in_array(strtolower($additional_extension), ['jpg', 'jpeg', 'png', 'gif', 'bmp']);
            ?>
            
            <?php if ($additional_is_image): ?>
                <img src="uploads/invoices/<?php echo $invoice['additional_file']; ?>" 
                     style="max-width: 300px; max-height: 200px; border-radius: 12px; border: 2px solid rgba(40,167,69,0.5); cursor: pointer; box-shadow: 0 4px 12px rgba(0,0,0,0.2);"
                     onclick="previewInvoiceFile('<?php echo $invoice['additional_file']; ?>', true)"
                     title="کلیک برای پیش‌نمایش بزرگ">
            <?php else: ?>
                <div style="background: rgba(40,167,69,0.1); padding: 30px; border-radius: 15px; border: 2px solid rgba(40,167,69,0.3); display: inline-block; cursor: pointer; transition: all 0.3s;"
                     onclick="previewInvoiceFile('<?php echo $invoice['additional_file']; ?>', true)"
                     onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 8px 25px rgba(0,0,0,0.2)';"
                     onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                    <div style="font-size: 48px; margin-bottom: 15px; color: #28a745;">
                        <?php
                        $file_icon = '📎';
                        if (strtolower($additional_extension) === 'pdf') $file_icon = '📕';
                        elseif (in_array(strtolower($additional_extension), ['doc', 'docx'])) $file_icon = '📝';
                        elseif (in_array(strtolower($additional_extension), ['xls', 'xlsx'])) $file_icon = '📊';
                        elseif (in_array(strtolower($additional_extension), ['zip', 'rar'])) $file_icon = '📦';
                        echo $file_icon;
                        ?>
                    </div>
                    <div style="font-weight: 600; color: white; margin-bottom: 5px;">پیوست فاکتور</div>
                    <div style="color: rgba(255,255,255,0.7); font-size: 14px;">فرمت: <?php echo strtoupper($additional_extension); ?></div>
                    <div style="color: rgba(255,255,255,0.5); font-size: 12px; margin-top: 8px;">
                        <i class="fas fa-mouse-pointer"></i> کلیک برای پیش‌نمایش
                    </div>
                </div>
            <?php endif; ?>
            
            <div style="margin-top: 25px; display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
                <button onclick="previewInvoiceFile('<?php echo $invoice['additional_file']; ?>', true)" 
                        class="btn-outline" style="padding: 10px 20px; border-color: #28a745; color: #28a745;">
                    <i class="fas fa-eye"></i> پیش‌نمایش پیوست
                </button>
                <a href="download-file.php?type=invoice&file=<?php echo $invoice['additional_file']; ?>&original_name=پیوست_<?php echo $invoice['invoice_number']; ?>.<?php echo pathinfo($invoice['additional_file'], PATHINFO_EXTENSION); ?>" 
                   class="btn-success" style="padding: 10px 20px; text-decoration: none;">
                    <i class="fas fa-download"></i> دانلود پیوست
                </a>
                <button onclick="printFile('<?php echo $invoice['additional_file']; ?>')" 
                        class="btn-success" style="padding: 10px 20px;">
                    <i class="fas fa-print"></i> پرینت
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- پیوست‌های ارجاع -->
    <?php if (!empty($refer_attachments)): ?>
    <div style="margin-bottom: 30px;">
        <h4 style="color: white; margin-bottom: 20px; font-size: 18px; display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-share-alt" style="color: #ffc107;"></i> پیوست‌های ارجاع
        </h4>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px;">
            <?php foreach ($refer_attachments as $attachment): 
                $attachment_user = getUser($attachment['user_id']);
                $file_extension = pathinfo($attachment['file_path'], PATHINFO_EXTENSION);
            ?>
            <div style="background: rgba(255,255,255,0.05); border-radius: 15px; padding: 20px; border: 1px solid rgba(255,255,255,0.1); transition: all 0.3s;"
                 onmouseover="this.style.transform='translateY(-5px)'; this.style.borderColor='rgba(255,193,7,0.3)';"
                 onmouseout="this.style.transform='translateY(0)'; this.style.borderColor='rgba(255,255,255,0.1)';">
                <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 15px;">
                    <div style="width: 50px; height: 50px; border-radius: 12px; background: rgba(255,193,7,0.2); display: flex; align-items: center; justify-content: center; color: #ffc107; font-size: 20px;">
                        <?php
                        $file_icon = '📄';
                        if (strtolower($file_extension) === 'pdf') $file_icon = '📕';
                        elseif (in_array(strtolower($file_extension), ['doc', 'docx'])) $file_icon = '📝';
                        elseif (in_array(strtolower($file_extension), ['xls', 'xlsx'])) $file_icon = '📊';
                        elseif (in_array(strtolower($file_extension), ['zip', 'rar'])) $file_icon = '📦';
                        elseif (in_array(strtolower($file_extension), ['jpg', 'jpeg', 'png', 'gif'])) $file_icon = '🖼️';
                        echo $file_icon;
                        ?>
                    </div>
                    <div style="flex: 1;">
                        <div style="font-weight: 600; color: white; margin-bottom: 5px; font-size: 15px;"><?php echo $attachment['file_name']; ?></div>
                        <div style="display: flex; align-items: center; gap: 8px; font-size: 12px; color: rgba(255,255,255,0.7);">
                            <div style="display: flex; align-items: center; gap: 5px;">
                                <i class="fas fa-user" style="font-size: 10px;"></i>
                                <?php echo $attachment_user ? $attachment_user['username'] : 'نامشخص'; ?>
                            </div>
                            <span>•</span>
                            <div>
                                <?php echo strtoupper($file_extension); ?> فایل
                            </div>
                        </div>
                    </div>
                </div>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <button onclick="previewInvoiceFile('<?php echo $attachment['file_path']; ?>', true)" 
                            class="btn-outline" style="padding: 8px 16px; font-size: 13px;">
                        <i class="fas fa-eye"></i> پیش‌نمایش
                    </button>
                    <a href="download-file.php?type=invoice&file=<?php echo $attachment['file_path']; ?>&original_name=<?php echo urlencode($attachment['file_name']); ?>" 
                       class="btn-primary" style="padding: 8px 16px; font-size: 13px; text-decoration: none;">
                        <i class="fas fa-download"></i> دانلود
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- توضیحات -->
    <?php if (!empty($invoice['description'])): ?>
    <div style="margin-bottom: 30px;">
        <h4 style="color: white; margin-bottom: 20px; font-size: 18px; display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-align-left" style="color: #6f42c1;"></i> توضیحات
        </h4>
        <div style="background: rgba(255,255,255,0.05); border-radius: 15px; padding: 25px; border: 1px solid rgba(255,255,255,0.1); border-right: 4px solid #6f42c1;">
            <p style="color: rgba(255,255,255,0.9); line-height: 1.8; margin: 0; font-size: 15px;">
                <?php echo nl2br(htmlspecialchars($invoice['description'])); ?>
            </p>
        </div>
    </div>
    <?php endif; ?>

    <!-- دکمه تکمیل فاکتور (برای ادمین) -->
    <?php if (isAdmin() && $invoice['current_user_id'] === $_SESSION['user_id'] && $invoice['status'] !== 'completed'): ?>
    <div style="margin-bottom: 30px; text-align: center; padding: 20px; background: rgba(52,199,89,0.1); border-radius: 15px; border: 1px solid rgba(52,199,89,0.3);">
        <h5 style="color: #51cf66; margin: 0 0 15px 0; font-size: 16px; display: flex; align-items: center; justify-content: center; gap: 10px;">
            <i class="fas fa-check-circle"></i> اقدام مدیریتی
        </h5>
        <form method="POST" action="invoice-management.php" style="display: inline;">
            <input type="hidden" name="invoice_id" value="<?php echo $invoice['id']; ?>">
            <button type="submit" name="complete_invoice" class="btn-success" style="padding: 12px 30px;"
                    onclick="return confirm('آیا از تکمیل این فاکتور اطمینان دارید؟ این عمل قابل بازگشت نیست.')">
                <i class="fas fa-check-double"></i> تکمیل نهایی فاکتور
            </button>
        </form>
    </div>
    <?php endif; ?>

    <!-- تاریخچه پیگیری -->
    <div>
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h4 style="color: white; margin: 0; font-size: 18px; display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-history" style="color: #4a9eff;"></i> تاریخچه پیگیری
            </h4>
            <span style="color: rgba(255,255,255,0.7); font-size: 14px;">
                <?php echo count($invoice['tracking_history']); ?> رخداد
            </span>
        </div>
        
        <div style="background: rgba(255,255,255,0.05); border-radius: 15px; padding: 20px; border: 1px solid rgba(255,255,255,0.1); max-height: 400px; overflow-y: auto;">
            <?php foreach (array_reverse($invoice['tracking_history']) as $history): 
                $history_user = getUser($history['user_id']);
                $is_current_user = $history_user && $history_user['id'] === $_SESSION['user_id'];
            ?>
            <div style="display: flex; gap: 15px; padding: 15px; margin-bottom: 12px; background: rgba(255,255,255,0.02); border-radius: 10px; border-right: 3px solid <?php 
                switch($history['action']) {
                    case 'create': echo '#4a9eff'; break;
                    case 'refer': echo '#ffc107'; break;
                    case 'receive': echo '#28a745'; break;
                    case 'complete': echo '#6f42c1'; break;
                    default: echo '#86868B';
                }
            ?>; transition: all 0.3s;"
                onmouseover="this.style.background='rgba(255,255,255,0.05)';">
                <div style="width: 36px; height: 36px; border-radius: 50%; background: <?php 
                    echo $is_current_user ? 'linear-gradient(135deg, #4a9eff, #6f42c1)' : 'linear-gradient(135deg, #86868B, #6c757d)';
                ?>; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 14px; flex-shrink: 0;">
                    <?php echo $history_user ? strtoupper(substr($history_user['username'], 0, 1)) : '?'; ?>
                </div>
                <div style="flex: 1;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                        <div>
                            <span style="font-weight: 600; color: white;"><?php echo $history_user ? $history_user['username'] : 'نامشخص'; ?></span>
                            <span style="background: <?php 
                                switch($history['action']) {
                                    case 'create': echo 'rgba(74, 158, 255, 0.2)'; break;
                                    case 'refer': echo 'rgba(255, 193, 7, 0.2)'; break;
                                    case 'receive': echo 'rgba(40, 167, 69, 0.2)'; break;
                                    case 'complete': echo 'rgba(111, 66, 193, 0.2)'; break;
                                    default: echo 'rgba(134, 134, 139, 0.2)';
                                }
                            ?>; color: <?php 
                                switch($history['action']) {
                                    case 'create': echo '#4a9eff'; break;
                                    case 'refer': echo '#ffc107'; break;
                                    case 'receive': echo '#28a745'; break;
                                    case 'complete': echo '#6f42c1'; break;
                                    default: echo '#86868B';
                                }
                            ?>; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; margin-right: 10px; border: 1px solid <?php 
                                switch($history['action']) {
                                    case 'create': echo 'rgba(74, 158, 255, 0.3)'; break;
                                    case 'refer': echo 'rgba(255, 193, 7, 0.3)'; break;
                                    case 'receive': echo 'rgba(40, 167, 69, 0.3)'; break;
                                    case 'complete': echo 'rgba(111, 66, 193, 0.3)'; break;
                                    default: echo 'rgba(134, 134, 139, 0.3)';
                                }
                            ?>;">
                                <?php 
                                $action_text = [
                                    'create' => 'ایجاد فاکتور',
                                    'refer' => 'ارجاع فاکتور',
                                    'receive' => 'دریافت فاکتور',
                                    'complete' => 'تکمیل فاکتور'
                                ];
                                echo $action_text[$history['action']];
                                
                                if ($history['action'] === 'refer' && isset($history['to_user_id'])) {
                                    $to_user = getUser($history['to_user_id']);
                                    echo ' → ';
                                    if ($to_user) {
                                        echo '<span style="font-weight: bold; color: #ffc107;">' . $to_user['username'] . '</span>';
                                    }
                                }
                                ?>
                            </span>
                        </div>
                        <span style="color: rgba(255,255,255,0.5); font-size: 12px; white-space: nowrap;">
                            <?php echo date('Y/m/d H:i', $history['timestamp']); ?>
                        </span>
                    </div>
                    
                    <?php if (!empty($history['description'])): ?>
                        <div style="color: rgba(255,255,255,0.8); font-size: 14px; line-height: 1.5; background: rgba(255,255,255,0.03); padding: 10px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.05);">
                            <i class="fas fa-comment-alt" style="margin-left: 5px; color: rgba(255,255,255,0.5); font-size: 12px;"></i>
                            <?php echo htmlspecialchars($history['description']); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            
            <?php if (count($invoice['tracking_history']) === 0): ?>
                <div style="text-align: center; padding: 40px; color: rgba(255,255,255,0.5);">
                    <i class="fas fa-history" style="font-size: 36px; margin-bottom: 15px; opacity: 0.3;"></i>
                    <p style="margin: 0;">هنوز هیچ فعالیتی ثبت نشده است</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- مودال ارجاع فاکتور -->
<div id="referInvoiceModal" class="modal">
    <div class="modal-content" style="max-width: 700px;">
        <div class="modal-header">
            <h3><i class="fas fa-exchange-alt"></i> ارجاع فاکتور با تأیید</h3>
            <button class="close-modal" onclick="closeModal('referInvoiceModal')">×</button>
        </div>
        <form id="referForm" method="POST" enctype="multipart/form-data" onsubmit="return validateReferForm()">
            <input type="hidden" name="invoice_id" id="refer_invoice_id">
            <input type="hidden" name="refer_invoice" value="1">
            
            <div style="padding: 15px; background: rgba(74,158,255,0.1); border-radius: 10px; margin-bottom: 20px;">
                <div style="color: white; font-size: 14px; margin-bottom: 5px; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-info-circle"></i>
                    <strong>راهنمای ارجاع همراه با تأیید:</strong>
                </div>
                <div style="color: rgba(255,255,255,0.8); font-size: 12px;">
                    • می‌توانید فاکتور را فقط ارجاع دهید<br>
                    • یا می‌توانید گزینه‌های تأیید را انتخاب و همزمان تأیید کنید<br>
                    • تأیید همزمان باعث ثبت تأییدیه شما قبل از ارجاع می‌شود
                </div>
            </div>
            
            <div class="form-group">
                <label for="to_user_id">ارجاع به کاربر:</label>
                <select id="to_user_id" name="to_user_id" class="form-control" required>
                    <option value="">انتخاب کاربر</option>
                    <?php 
                    // فیلتر کردن کاربران بر اساس تنظیمات مجوز ارجاع
                    $eligible_users = array_filter($users, function($user) {
                        return $user['id'] !== $_SESSION['user_id'] && 
                               $user['is_active'] && 
                               (isset($user['can_receive_referral']) ? $user['can_receive_referral'] : true);
                    });
                    
                    foreach ($eligible_users as $user): ?>
                        <option value="<?php echo $user['id']; ?>">
                            <?php echo $user['username']; ?> (<?php echo $user['department']; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- بخش جدید: گزینه‌های تأیید -->
            <div class="form-group" style="margin-top: 20px;">
                <label style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
                    <span style="color: white; font-weight: 600;">
                        <i class="fas fa-check-circle"></i> گزینه‌های تأیید (اختیاری)
                    </span>
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 13px;">
                        <input type="checkbox" id="toggleApprovalOptions" onchange="toggleApprovalSection()">
                        <span style="color: rgba(255,255,255,0.7);">افزودن تأیید همزمان</span>
                    </label>
                </label>
                
                <div id="approvalOptionsSection" style="display: none; margin-top: 15px;">
                    <div style="background: rgba(255,255,255,0.05); border-radius: 8px; padding: 15px; border: 1px solid rgba(255,255,255,0.1);">
                        <div id="approvalOptionsList" style="max-height: 200px; overflow-y: auto; margin-bottom: 15px;">
                            <!-- گزینه‌های تأیید اینجا لود می‌شود -->
                            <div style="text-align: center; padding: 20px; color: rgba(255,255,255,0.5);">
                                <i class="fas fa-spinner fa-spin"></i>
                                در حال بارگذاری گزینه‌ها...
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label style="font-size: 13px;">توضیحات تأیید (اختیاری):</label>
                            <textarea id="approval_notes" name="approval_notes" class="form-control" rows="2" 
                                      placeholder="توضیحات مربوط به تأیید..."></textarea>
                        </div>
                        
                        <div style="background: rgba(52,199,89,0.1); border-radius: 6px; padding: 10px; margin-top: 10px;">
                            <div style="color: #34C759; font-size: 12px; display: flex; align-items: center; gap: 8px;">
                                <i class="fas fa-lightbulb"></i>
                                <span>در صورت انتخاب گزینه‌ها، تأییدیه شما قبل از ارجاع ثبت می‌شود</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="refer_description">توضیحات ارجاع:</label>
                <textarea id="refer_description" name="refer_description" class="form-control" rows="3" 
                          placeholder="لطفاً دلیل و توضیحات ارجاع فاکتور را وارد کنید..." required></textarea>
            </div>

            <div class="form-group">
                <label for="refer_attachment">فایل پیوست ارجاع (اختیاری):</label>
                <input type="file" id="refer_attachment" name="refer_attachment" class="form-control" 
                       accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.zip,.rar" 
                       onchange="previewFile(this, 'referAttachmentPreview')">
                <div id="referAttachmentPreview" class="file-preview" style="margin-top: 10px;"></div>
            </div>
            
            <div style="display: flex; gap: 15px; margin-top: 25px;">
                <button type="submit" class="btn btn-primary" id="submitReferBtn">
                    <i class="fas fa-paper-plane"></i> ارسال ارجاع
                </button>
                <button type="button" class="btn btn-outline" onclick="closeModal('referInvoiceModal')">
                    <i class="fas fa-times"></i> انصراف
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Overlay برای مودال‌ها -->
<div id="overlay" class="overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 999;"></div>

<style>
.info-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 12px;
}

.info-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 16px;
    background: rgba(255,255,255,0.02);
    border-radius: 10px;
    border: 1px solid rgba(255,255,255,0.05);
    transition: all 0.3s;
}

.info-item:hover {
    background: rgba(74, 158, 255, 0.05);
    border-color: rgba(74, 158, 255, 0.1);
}

.info-label {
    color: rgba(255,255,255,0.7);
    font-size: 14px;
}

.info-value {
    color: white;
    font-weight: 500;
    font-size: 14px;
}

.status-badge {
    padding: 8px 20px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    display: inline-block;
    text-align: center;
    min-width: 100px;
}

.status-pending {
    background: rgba(255, 149, 0, 0.2);
    color: #FF9500;
    border: 1px solid rgba(255, 149, 0, 0.3);
}

.status-in-progress {
    background: rgba(52, 199, 89, 0.2);
    color: #34C759;
    border: 1px solid rgba(52, 199, 89, 0.3);
}

.status-referred {
    background: rgba(88, 86, 214, 0.2);
    color: #5856D6;
    border: 1px solid rgba(88, 86, 214, 0.3);
}

.status-completed {
    background: rgba(52, 199, 89, 0.2);
    color: #34C759;
    border: 1px solid rgba(52, 199, 89, 0.3);
}

.btn-primary {
    background: linear-gradient(135deg, #4a9eff 0%, #357abd 100%);
    color: white;
    border: none;
    border-radius: 12px;
    padding: 10px 20px;
    cursor: pointer;
    font-weight: 600;
    font-size: 14px;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(74, 158, 255, 0.3);
}

.btn-success {
    background: linear-gradient(135deg, #34C759 0%, #28a745 100%);
    color: white;
    border: none;
    border-radius: 12px;
    padding: 10px 20px;
    cursor: pointer;
    font-weight: 600;
    font-size: 14px;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-success:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(52, 199, 89, 0.3);
}

.btn-outline {
    background: transparent;
    border: 1px solid #4a9eff;
    color: #4a9eff;
    border-radius: 12px;
    padding: 10px 20px;
    cursor: pointer;
    font-weight: 600;
    font-size: 14px;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-outline:hover {
    background: rgba(74, 158, 255, 0.1);
    transform: translateY(-2px);
}

/* Custom scrollbar */
::-webkit-scrollbar {
    width: 8px;
}

::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 10px;
}

::-webkit-scrollbar-thumb {
    background: rgba(74, 158, 255, 0.3);
    border-radius: 10px;
}

::-webkit-scrollbar-thumb:hover {
    background: rgba(74, 158, 255, 0.5);
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.8);
    z-index: 1000;
    justify-content: center;
    align-items: center;
}

.modal.active {
    display: flex;
}

.modal-content {
    background: #1c1c1e;
    border-radius: 20px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
    max-width: 90%;
    max-height: 90%;
    overflow: auto;
    position: relative;
}

.modal-header {
    padding: 20px 25px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    color: white;
    margin: 0;
    font-size: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.close-modal {
    background: none;
    border: none;
    color: rgba(255, 255, 255, 0.7);
    font-size: 24px;
    cursor: pointer;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: all 0.3s;
}

.close-modal:hover {
    background: rgba(255, 255, 255, 0.1);
    color: white;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    color: rgba(255, 255, 255, 0.8);
    margin-bottom: 8px;
    font-size: 14px;
    font-weight: 500;
}

.form-control {
    width: 100%;
    padding: 12px 15px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 10px;
    color: white;
    font-size: 14px;
    transition: all 0.3s;
}

.form-control:focus {
    outline: none;
    border-color: #4a9eff;
    box-shadow: 0 0 0 2px rgba(74, 158, 255, 0.2);
}

.btn {
    padding: 12px 24px;
    border-radius: 12px;
    border: none;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-success {
    background: linear-gradient(135deg, #34C759 0%, #28a745 100%);
    color: white;
}

.btn-success:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(52, 199, 89, 0.3);
}

.btn-primary {
    background: linear-gradient(135deg, #4a9eff 0%, #357abd 100%);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(74, 158, 255, 0.3);
}

.btn-outline {
    background: transparent;
    border: 1px solid #4a9eff;
    color: #4a9eff;
}

.btn-outline:hover {
    background: rgba(74, 158, 255, 0.1);
    transform: translateY(-2px);
}

.btn-danger {
    background: linear-gradient(135deg, #ff3b30 0%, #c62828 100%);
    color: white;
}

.btn-danger:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(255, 59, 48, 0.3);
}

.file-preview {
    border: 1px dashed rgba(255, 255, 255, 0.2);
    border-radius: 8px;
    padding: 10px;
    min-height: 50px;
}

.file-preview img {
    max-width: 100px;
    max-height: 100px;
    border-radius: 6px;
}
</style>

<script>
// تابع ساده برای باز کردن مودال ارجاع
function openReferModal(invoiceId, invoiceNumber) {
    console.log('Opening refer modal:', invoiceId, invoiceNumber);
    
    // تنظیم شناسه فاکتور
    document.getElementById('refer_invoice_id').value = invoiceId;
    
    // تنظیم توضیحات پیش‌فرض
    document.getElementById('refer_description').value = `ارجاع فاکتور شماره ${invoiceNumber}`;
    
    // ریست کردن گزینه‌ها
    document.getElementById('toggleApprovalOptions').checked = false;
    document.getElementById('approvalOptionsSection').style.display = 'none';
    
    // نمایش مودال
    document.getElementById('referInvoiceModal').style.display = 'flex';
    document.getElementById('overlay').style.display = 'block';
}

// تابع ساده برای بستن مودال
function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
    document.getElementById('overlay').style.display = 'none';
}

// مدیریت overlay
document.getElementById('overlay').addEventListener('click', function() {
    closeModal('referInvoiceModal');
});

// نمایش/پنهان کردن بخش گزینه‌های تأیید
function toggleApprovalSection() {
    const section = document.getElementById('approvalOptionsSection');
    const toggle = document.getElementById('toggleApprovalOptions');
    
    if (toggle.checked) {
        section.style.display = 'block';
        loadApprovalOptionsForRefer();
    } else {
        section.style.display = 'none';
    }
}

// بارگذاری گزینه‌های تأیید برای مودال ارجاع
function loadApprovalOptionsForRefer() {
    const container = document.getElementById('approvalOptionsList');
    
    // شبیه‌سازی داده‌های تست
    const testOptions = [
        {id: 1, text: 'مبلغ فاکتور صحیح است', mandatory: true},
        {id: 2, text: 'تاریخ فاکتور معتبر است', mandatory: true},
        {id: 3, text: 'مشخصات فروشنده صحیح است', mandatory: false},
        {id: 4, text: 'کالاها/خدمات دریافت شده است', mandatory: false},
        {id: 5, text: 'مطابقت با قرارداد دارد', mandatory: false}
    ];
    
    let html = `
        <div style="color: rgba(255,255,255,0.8); font-size: 13px; margin-bottom: 10px;">
            گزینه‌های مربوط به بررسی خود را انتخاب کنید:
        </div>
    `;
    
    testOptions.forEach((option, index) => {
        html += `
            <div style="margin-bottom: 8px; padding: 10px; background: rgba(255,255,255,0.03); 
                 border-radius: 6px; border: 1px solid rgba(255,255,255,0.1); transition: all 0.3s;">
                <label style="display: flex; align-items: center; cursor: pointer; font-size: 14px;">
                    <input type="checkbox" name="approval_options[]" 
                           value="${option.id}"
                           style="margin-left: 10px; transform: scale(1.2); cursor: pointer;"
                           ${option.mandatory ? 'data-mandatory="true"' : ''}
                           onchange="toggleOptionInRefer(this)">
                    <div style="flex: 1; color: white;">
                        ${option.text}
                        ${option.mandatory ? 
                            '<span style="color: #ff6b6b; font-size: 11px; margin-right: 8px;">(الزامی)</span>' : 
                            ''}
                    </div>
                </label>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

// تغییر رنگ گزینه انتخاب شده
function toggleOptionInRefer(checkbox) {
    const optionDiv = checkbox.closest('div');
    if (checkbox.checked) {
        optionDiv.style.background = 'rgba(52, 199, 89, 0.15)';
        optionDiv.style.borderColor = 'rgba(52, 199, 89, 0.4)';
    } else {
        optionDiv.style.background = 'rgba(255,255,255,0.03)';
        optionDiv.style.borderColor = 'rgba(255,255,255,0.1)';
    }
}

// پیش‌نمایش فایل آپلود شده
function previewFile(input, previewId) {
    const preview = document.getElementById(previewId);
    preview.innerHTML = '';
    
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const fileType = file.type;
        
        if (fileType.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.innerHTML = `<img src="${e.target.result}" style="max-width: 100px; max-height: 100px; border-radius: 6px;">`;
            };
            reader.readAsDataURL(file);
        } else {
            preview.innerHTML = `
                <div style="display: flex; align-items: center; gap: 10px; color: rgba(255,255,255,0.8);">
                    <i class="fas fa-file" style="font-size: 20px;"></i>
                    <div>
                        <div style="font-weight: 500;">${file.name}</div>
                        <div style="font-size: 12px; color: rgba(255,255,255,0.6);">
                            ${(file.size / 1024).toFixed(2)} KB
                        </div>
                    </div>
                </div>
            `;
        }
    }
}

// اعتبارسنجی فرم ارجاع
function validateReferForm() {
    const toUserId = document.getElementById('to_user_id').value;
    const description = document.getElementById('refer_description').value.trim();
    const hasApprovalOptions = document.getElementById('toggleApprovalOptions').checked;
    
    // بررسی کاربر
    if (!toUserId) {
        alert('⚠️ لطفاً کاربر مقصد را انتخاب کنید');
        return false;
    }
    
    // بررسی توضیحات
    if (!description || description.length < 10) {
        alert('⚠️ لطفاً توضیحات ارجاع را وارد کنید (حداقل 10 کاراکتر)');
        document.getElementById('refer_description').focus();
        return false;
    }
    
    // اگر گزینه تأیید انتخاب شده، بررسی کن
    if (hasApprovalOptions) {
        const selectedOptions = document.querySelectorAll('input[name="approval_options[]"]:checked');
        
        if (selectedOptions.length === 0) {
            alert('⚠️ لطفاً حداقل یک گزینه تأیید را انتخاب کنید');
            return false;
        }
        
        // بررسی گزینه‌های الزامی
        const mandatoryOptions = document.querySelectorAll('input[name="approval_options[]"][data-mandatory="true"]');
        for (let option of mandatoryOptions) {
            if (!option.checked) {
                const optionText = option.closest('label').querySelector('div').textContent.trim();
                alert(`⚠️ گزینه "${optionText}" الزامی است`);
                return false;
            }
        }
    }
    
    // نمایش تأیید نهایی
    let confirmMessage = 'آیا از ارجاع این فاکتور اطمینان دارید؟\n\n';
    
    if (hasApprovalOptions) {
        const selectedCount = document.querySelectorAll('input[name="approval_options[]"]:checked').length;
        confirmMessage += `📝 تأییدیه شما نیز ثبت خواهد شد (${selectedCount} گزینه)\n`;
    }
    
    confirmMessage += '\nاین عمل در تاریخچه پیگیری ثبت خواهد شد.';
    
    if (!confirm(confirmMessage)) {
        return false;
    }
    
    // نمایش در حال پردازش
    const submitBtn = document.getElementById('submitReferBtn');
    if (submitBtn) {
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> در حال ارسال...';
        submitBtn.disabled = true;
    }
    
    return true;
}

// توابع موجود دیگر
function referInvoiceFromView(invoiceId) {
    openReferModal(invoiceId, '');
}

function previewInvoiceFile(filePath, isAdditional = false) {
    if (!filePath) return;
    
    const fileExtension = filePath.split('.').pop().toLowerCase();
    const isImage = ['jpg', 'jpeg', 'png', 'gif', 'bmp'].includes(fileExtension);
    
    if (!isImage) {
        alert('📄 این نوع فایل قابل پیش‌نمایش در مرورگر نیست. لطفاً برای مشاهده آن را دانلود کنید.');
        return false;
    }
    
    const fileUrl = 'uploads/invoices/' + filePath;
    const previewModal = document.getElementById('filePreviewModal');
    const previewContent = document.getElementById('filePreviewContent');
    const downloadLink = document.getElementById('downloadFile');
    
    if (!previewModal || !previewContent || !downloadLink) {
        alert('مودال پیش‌نمایش یافت نشد');
        return;
    }
    
    downloadLink.href = 'download-file.php?type=invoice&file=' + filePath + '&original_name=' + encodeURIComponent(filePath);
    downloadLink.download = filePath;
    previewContent.innerHTML = `<img src="${fileUrl}" style="max-width: 100%; max-height: 70vh; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">`;
    
    previewModal.style.display = 'flex';
    document.getElementById('overlay').style.display = 'block';
}

function printFile(filePath) {
    if (!filePath) {
        alert('مسیر فایل مشخص نشده است');
        return;
    }
    
    const fileExtension = filePath.split('.').pop().toLowerCase();
    const isImage = ['jpg', 'jpeg', 'png', 'gif', 'bmp'].includes(fileExtension);
    
    if (isImage) {
        const fileUrl = 'uploads/invoices/' + filePath;
        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <html>
            <head>
                <title>پرینت فایل</title>
                <style>
                    body { text-align: center; padding: 20px; }
                    img { max-width: 100%; max-height: 90vh; }
                </style>
            </head>
            <body>
                <img src="${fileUrl}">
                <script>
                    window.onload = function() {
                        window.print();
                        setTimeout(function() {
                            window.close();
                        }, 1000);
                    }
                <\/script>
            </body>
            </html>
        `);
        printWindow.document.close();
    } else {
        alert('📄 امکان پرینت این نوع فایل وجود ندارد');
    }
}
</script>