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
?>
<style>
    :root {
        --primary: #007AFF;
        --success: #34C759;
        --warning: #FF9500;
        --danger: #FF3B30;
        --radius: 20px;
        --radius-sm: 14px;
        --dark-bg: #1a1a2e;
        --dark-secondary: #16213e;
    }

    .glass-card {
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(40px) saturate(180%);
        -webkit-backdrop-filter: blur(40px) saturate(180%);
        border: 1px solid rgba(255, 255, 255, 0.15);
        border-radius: var(--radius);
        padding: 24px;
        margin-bottom: 24px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        color: white;
    }

    .info-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 2rem;
        margin-bottom: 2rem;
    }

    @media (max-width: 768px) {
        .info-grid {
            grid-template-columns: 1fr;
        }
    }

    .info-section h4 {
        color: white;
        margin-bottom: 20px;
        font-size: 18px;
        font-weight: 700;
        border-bottom: 2px solid rgba(74, 158, 255, 0.3);
        padding-bottom: 10px;
    }

    .info-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 16px;
        background: rgba(255, 255, 255, 0.05);
        border-radius: var(--radius-sm);
        margin-bottom: 10px;
        transition: all 0.3s ease;
    }

    .info-item:hover {
        background: rgba(255, 255, 255, 0.08);
    }

    .info-item strong {
        color: #4a9eff;
        font-weight: 600;
    }

    .info-item span {
        color: rgba(255, 255, 255, 0.9);
        font-weight: 500;
    }

    .status-badge {
        padding: 8px 16px;
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
        background: rgba(74, 158, 255, 0.2);
        color: #4a9eff;
        border: 1px solid rgba(74, 158, 255, 0.3);
    }

    .status-completed {
        background: rgba(52, 199, 89, 0.2);
        color: #34C759;
        border: 1px solid rgba(52, 199, 89, 0.3);
    }

    .status-referred {
        background: rgba(111, 66, 193, 0.2);
        color: #6f42c1;
        border: 1px solid rgba(111, 66, 193, 0.3);
    }

    .history-container {
        max-height: 500px;
        overflow-y: auto;
        padding-right: 10px;
    }

    .history-container::-webkit-scrollbar {
        width: 6px;
    }

    .history-container::-webkit-scrollbar-thumb {
        background: rgba(74, 158, 255, 0.3);
        border-radius: 10px;
    }

    .history-item {
        border-right: 3px solid #4a9eff;
        padding: 20px;
        margin-bottom: 20px;
        background: rgba(255, 255, 255, 0.05);
        border-radius: var(--radius-sm);
        position: relative;
        transition: all 0.3s ease;
    }

    .history-item:hover {
        background: rgba(255, 255, 255, 0.08);
        transform: translateX(-5px);
    }

    .history-item::before {
        content: '';
        position: absolute;
        right: -3px;
        top: 0;
        bottom: 0;
        width: 3px;
        background: linear-gradient(to bottom, #4a9eff, transparent);
        border-radius: 3px;
    }

    .history-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 15px;
    }

    .history-user {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .history-user strong {
        color: #4a9eff;
        font-size: 16px;
    }

    .history-user span {
        color: rgba(255, 255, 255, 0.7);
        font-size: 14px;
    }

    .history-time {
        color: rgba(255, 255, 255, 0.6);
        font-size: 13px;
    }

    .history-action {
        margin-bottom: 15px;
        font-size: 15px;
        color: white;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .history-description {
        background: rgba(255, 255, 255, 0.08);
        padding: 15px;
        border-radius: var(--radius-sm);
        margin-bottom: 15px;
    }

    .history-description p {
        margin: 10px 0 0 0;
        color: rgba(255, 255, 255, 0.9);
        line-height: 1.6;
    }

    .attachment-box {
        background: rgba(74, 158, 255, 0.1);
        padding: 15px;
        border-radius: var(--radius-sm);
        border: 1px solid rgba(74, 158, 255, 0.2);
    }

    .attachment-box strong {
        color: #4a9eff;
        display: block;
        margin-bottom: 10px;
    }

    .file-item {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 12px;
        background: rgba(255, 255, 255, 0.05);
        border-radius: var(--radius-sm);
        margin-top: 10px;
        transition: all 0.3s ease;
    }

    .file-item:hover {
        background: rgba(255, 255, 255, 0.1);
    }

    .file-icon {
        font-size: 28px;
        width: 50px;
        height: 50px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(74, 158, 255, 0.1);
        border-radius: 12px;
    }

    .file-info {
        flex: 1;
    }

    .file-name {
        font-weight: bold;
        color: white;
        margin-bottom: 5px;
    }

    .file-meta {
        font-size: 12px;
        color: rgba(255, 255, 255, 0.7);
    }

    .btn {
        padding: 8px 16px;
        border: none;
        border-radius: var(--radius-sm);
        cursor: pointer;
        font-weight: 600;
        font-size: 14px;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        font-family: 'Vazirmatn', sans-serif;
    }

    .btn-primary {
        background: linear-gradient(135deg, #4a9eff 0%, #357abd 100%);
        color: white;
        border: 1px solid rgba(74, 158, 255, 0.3);
        box-shadow: 0 4px 15px rgba(74, 158, 255, 0.2);
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
    }

    .section-title {
        color: white;
        margin-bottom: 20px;
        font-size: 22px;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .section-title::before {
        content: '';
        width: 4px;
        height: 24px;
        background: #4a9eff;
        border-radius: 2px;
    }
</style>

<div style="padding: 1rem;">
    <!-- اطلاعات اصلی فاکتور -->
    <div class="glass-card">
        <div class="info-grid">
            <!-- اطلاعات فاکتور -->
            <div class="info-section">
                <h4>📄 اطلاعات فاکتور</h4>
                <div class="info-item">
                    <strong>شماره فاکتور:</strong>
                    <span><?php echo $invoice['invoice_number']; ?></span>
                </div>
                <div class="info-item">
                    <strong>شرکت:</strong>
                    <span><?php echo $invoice['company_name']; ?></span>
                </div>
                <div class="info-item">
                    <strong>مبلغ:</strong>
                    <span><?php echo formatPrice($invoice['amount']); ?></span>
                </div>
                <div class="info-item">
                    <strong>تاریخ ایجاد:</strong>
                    <span><?php echo convertToJalali($invoice['created_at']); ?></span>
                </div>
            </div>

            <!-- اطلاعات پیگیری -->
            <div class="info-section">
                <h4>📊 اطلاعات پیگیری</h4>
                <div class="info-item">
                    <strong>فروشگاه:</strong>
                    <span><?php echo $invoice['store_name']; ?></span>
                </div>
                <div class="info-item">
                    <strong>کارگاه:</strong>
                    <span><?php echo $invoice['workshop_name']; ?></span>
                </div>
                <div class="info-item">
                    <strong>وضعیت:</strong>
                    <span class="status-badge status-<?php echo $invoice['status']; ?>">
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
                <div class="info-item">
                    <strong>ایجاد کننده:</strong>
                    <span><?php echo $created_by ? $created_by['username'] : 'نامشخص'; ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- تاریخچه پیگیری -->
    <div class="glass-card">
        <h4 class="section-title">📋 تاریخچه کامل پیگیری</h4>
        <div class="history-container">
            <?php foreach (array_reverse($invoice['tracking_history']) as $index => $history): 
                $history_user = getUser($history['user_id']);
                $to_user = isset($history['to_user_id']) ? getUser($history['to_user_id']) : null;
            ?>
            <div class="history-item">
                <div class="history-header">
                    <div class="history-user">
                        <div>
                            <strong><?php echo $history_user ? $history_user['username'] : 'نامشخص'; ?></strong>
                            <span>(<?php echo $history_user ? $history_user['department'] : 'نامشخص'; ?>)</span>
                        </div>
                    </div>
                    <span class="history-time">
                        <?php echo convertToJalali($history['timestamp']); ?>
                    </span>
                </div>
                
                <div class="history-action">
                    <?php 
                    $action_text = [
                        'create' => '📝 ایجاد فاکتور',
                        'refer' => '🔄 ارجاع فاکتور',
                        'receive' => '✅ دریافت فاکتور', 
                        'complete' => '🏁 تکمیل فاکتور'
                    ];
                    echo $action_text[$history['action']];
                    
                    if ($history['action'] === 'refer' && $to_user) {
                        echo ' به <strong style="color: #ffc107;">' . $to_user['username'] . '</strong>';
                    }
                    ?>
                </div>
                
                <?php if (!empty($history['description'])): ?>
                <div class="history-description">
                    <strong>توضیحات:</strong>
                    <p><?php echo nl2br(htmlspecialchars($history['description'])); ?></p>
                </div>
                <?php endif; ?>
                
                <?php if (isset($history['attachment'])): ?>
                <div class="attachment-box">
                    <strong>📎 فایل پیوست:</strong>
                    <div class="file-item">
                        <div class="file-icon">
                            <?php
                            $file_extension = pathinfo($history['attachment']['file_path'], PATHINFO_EXTENSION);
                            $file_icon = '📄';
                            if (strtolower($file_extension) === 'pdf') $file_icon = '📕';
                            elseif (in_array(strtolower($file_extension), ['doc', 'docx'])) $file_icon = '📝';
                            elseif (in_array(strtolower($file_extension), ['xls', 'xlsx'])) $file_icon = '📊';
                            elseif (in_array(strtolower($file_extension), ['zip', 'rar'])) $file_icon = '📦';
                            elseif (in_array(strtolower($file_extension), ['jpg', 'jpeg', 'png', 'gif'])) $file_icon = '🖼️';
                            echo $file_icon;
                            ?>
                        </div>
                        <div class="file-info">
                            <div class="file-name"><?php echo $history['attachment']['file_name']; ?></div>
                            <div class="file-meta">فرمت: <?php echo strtoupper($file_extension); ?></div>
                        </div>
                        <a href="uploads/invoices/<?php echo $history['attachment']['file_path']; ?>" download class="btn btn-primary">
                            <i class="fas fa-download"></i> دانلود
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>