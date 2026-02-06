<?php
require_once 'config.php';
require_once 'functions.php';

/**
 * سیستم تأیید سلسله‌مراتبی پیشرفته
 */

class ApprovalSystem {
    
    // ذخیره داده‌های کش شده در حافظه
    private static $memory_cache = [];
    
    /**
     * ایجاد زنجیره تأیید جدید برای فاکتور
     */
    public static function createApprovalChain($invoice_id, $chain_data) {
        $chains = loadData('approval-chains');
        
        $chain = [
            'id' => uniqid('chain_'),
            'invoice_id' => $invoice_id,
            'stages' => $chain_data['stages'],
            'supervisor_id' => $chain_data['supervisor_id'],
            'created_at' => time(),
            'created_by' => $_SESSION['user_id'],
            'current_stage' => 0,
            'completed_stages' => [],
            'status' => 'pending',
            'deadlines' => [],
            'custom_options' => [],
            'logs' => [],
            'assigned_delegates' => []
        ];
        
        // تنظیم مهلت برای هر مرحله
        foreach ($chain_data['stages'] as $index => $stage) {
            $deadline_hours = $stage['deadline_hours'] ?? 72; // پیش‌فرض 72 ساعت
            $chain['deadlines'][$index] = time() + ($deadline_hours * 3600);
        }
        
        $chains['chains'][$chain['id']] = $chain;
        $chains['invoice_chains'][$invoice_id] = $chain['id'];
        
        // ثبت لاگ
        self::logChainAction($chain['id'], 'create', 'ایجاد زنجیره تأیید جدید');
        
        if (saveData('approval-chains', $chains)) {
            self::clearCache("invoice_chain_{$invoice_id}");
            return $chain['id'];
        }
        
        return false;
    }
    
    /**
     * دریافت زنجیره تأیید یک فاکتور
     */
    public static function getInvoiceChain($invoice_id, $use_cache = true) {
        $cache_key = "invoice_chain_{$invoice_id}";
        
        // بررسی کش
        if ($use_cache) {
            $cached = self::getCache($cache_key);
            if ($cached !== null) {
                return $cached;
            }
        }
        
        $chains = loadData('approval-chains');
        $chain_id = $chains['invoice_chains'][$invoice_id] ?? null;
        
        if (!$chain_id || !isset($chains['chains'][$chain_id])) {
            return null;
        }
        
        $chain = $chains['chains'][$chain_id];
        
        // ذخیره در کش
        if ($use_cache) {
            self::setCache($cache_key, $chain, 300); // 5 دقیقه
        }
        
        return $chain;
    }
    
    /**
     * ثبت تأییدیه کاربر در مرحله فعلی
     */
    public static function submitApproval($invoice_id, $user_id, $selected_options, $custom_options = [], $notes = '') {
        $chain = self::getInvoiceChain($invoice_id);
        if (!$chain) {
            return ['success' => false, 'message' => 'زنجیره تأیید یافت نشد'];
        }
        
        // بررسی آیا کاربر در مرحله فعلی قرار دارد
        $current_stage = $chain['current_stage'];
        if (!isset($chain['stages'][$current_stage])) {
            return ['success' => false, 'message' => 'مرحله جاری معتبر نیست'];
        }
        
        $current_stage_data = $chain['stages'][$current_stage];
        if (!in_array($user_id, $current_stage_data['users'])) {
            return ['success' => false, 'message' => 'شما مجاز به تأیید در این مرحله نیستید'];
        }
        
        // ثبت تأییدیه در سیستم اصلی
        require_once 'functions.php';
        if (!function_exists('addInvoiceApproval')) {
            return ['success' => false, 'message' => 'سیستم تأییدیه در دسترس نیست'];
        }
        
        $approval_result = addInvoiceApproval($invoice_id, $user_id, $selected_options, $notes);
        if (!$approval_result) {
            return ['success' => false, 'message' => 'خطا در ثبت تأییدیه'];
        }
        
        // اضافه کردن گزینه‌های سفارشی
        if (!empty($custom_options)) {
            self::addCustomOptions($chain['id'], $user_id, $custom_options);
        }
        
        // بررسی آیا همه کاربران این مرحله تأیید کرده‌اند
        if (self::isStageCompleted($chain['id'], $current_stage)) {
            // انتقال به مرحله بعدی
            $next_stage = $current_stage + 1;
            if (isset($chain['stages'][$next_stage])) {
                self::advanceToNextStage($chain['id'], $next_stage);
            } else {
                // اگر مرحله آخر بود، تکمیل زنجیره
                self::completeChain($chain['id']);
            }
        }
        
        // ثبت لاگ
        $user = getUser($user_id);
        self::logChainAction(
            $chain['id'], 
            'approval', 
            "تأییدیه توسط {$user['username']} ثبت شد",
            $user_id,
            $selected_options
        );
        
        self::clearCache("invoice_chain_{$invoice_id}");
        
        return ['success' => true, 'message' => 'تأییدیه با موفقیت ثبت شد'];
    }
    
    /**
     * اضافه کردن گزینه سفارشی
     */
    private static function addCustomOptions($chain_id, $user_id, $custom_options) {
        $chains = loadData('approval-chains');
        
        if (!isset($chains['custom_options'][$chain_id])) {
            $chains['custom_options'][$chain_id] = [];
        }
        
        foreach ($custom_options as $option_text) {
            $option_id = uniqid('custom_');
            $chains['custom_options'][$chain_id][] = [
                'id' => $option_id,
                'text' => $option_text,
                'created_by' => $user_id,
                'created_at' => time(),
                'approved_by' => [$user_id]
            ];
        }
        
        saveData('approval-chains', $chains);
    }
    
    /**
     * بررسی تکمیل شدن یک مرحله
     */
    private static function isStageCompleted($chain_id, $stage_index) {
        $chains = loadData('approval-chains');
        $chain = $chains['chains'][$chain_id] ?? null;
        
        if (!$chain) return false;
        
        $stage_users = $chain['stages'][$stage_index]['users'] ?? [];
        if (empty($stage_users)) return false;
        
        // بررسی تأییدیه‌های این فاکتور
        $approvals = loadData('invoice-approvals');
        $invoice_id = $chain['invoice_id'];
        
        $approved_users = [];
        foreach ($approvals as $approval) {
            if ($approval['invoice_id'] === $invoice_id) {
                $approved_users[] = $approval['user_id'];
            }
        }
        
        // بررسی آیا همه کاربران مرحله تأیید کرده‌اند
        foreach ($stage_users as $user_id) {
            if (!in_array($user_id, $approved_users)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * پیشروی به مرحله بعدی
     */
    private static function advanceToNextStage($chain_id, $next_stage_index) {
        $chains = loadData('approval-chains');
        
        if (!isset($chains['chains'][$chain_id])) {
            return false;
        }
        
        $chains['chains'][$chain_id]['current_stage'] = $next_stage_index;
        $chains['chains'][$chain_id]['completed_stages'][] = $next_stage_index - 1;
        
        // ارسال نوتیفیکیشن به کاربران مرحله جدید
        $next_stage_users = $chains['chains'][$chain_id]['stages'][$next_stage_index]['users'] ?? [];
        $invoice_id = $chains['chains'][$chain_id]['invoice_id'];
        
        foreach ($next_stage_users as $user_id) {
            sendNotification(
                $user_id,
                "🔔 نوبت تأیید شما برای فاکتور #{$invoice_id} رسیده است",
                $invoice_id
            );
        }
        
        // ثبت لاگ
        self::logChainAction($chain_id, 'advance', "پیشروی به مرحله {$next_stage_index}");
        
        return saveData('approval-chains', $chains);
    }
    
    /**
     * تکمیل زنجیره تأیید
     */
    private static function completeChain($chain_id) {
        $chains = loadData('approval-chains');
        
        if (!isset($chains['chains'][$chain_id])) {
            return false;
        }
        
        $chains['chains'][$chain_id]['status'] = 'completed';
        $chains['chains'][$chain_id]['completed_at'] = time();
        
        // ارسال نوتیفیکیشن به سرپرست
        $supervisor_id = $chains['chains'][$chain_id]['supervisor_id'];
        $invoice_id = $chains['chains'][$chain_id]['invoice_id'];
        
        if ($supervisor_id) {
            sendNotification(
                $supervisor_id,
                "✅ زنجیره تأیید فاکتور #{$invoice_id} تکمیل شد",
                $invoice_id
            );
        }
        
        // ثبت لاگ
        self::logChainAction($chain_id, 'complete', 'تکمیل زنجیره تأیید');
        
        return saveData('approval-chains', $chains);
    }
    
    /**
     * ثبت لاگ برای اقدامات زنجیره
     */
    private static function logChainAction($chain_id, $action, $description, $user_id = null, $data = null) {
        $chains = loadData('approval-chains');
        
        if (!isset($chains['chain_logs'][$chain_id])) {
            $chains['chain_logs'][$chain_id] = [];
        }
        
        $log_entry = [
            'id' => uniqid('log_'),
            'chain_id' => $chain_id,
            'action' => $action,
            'user_id' => $user_id ?? $_SESSION['user_id'],
            'description' => $description,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'timestamp' => time(),
            'data' => $data
        ];
        
        array_unshift($chains['chain_logs'][$chain_id], $log_entry);
        
        // محدود کردن تعداد لاگ‌ها به 100 مورد آخر
        if (count($chains['chain_logs'][$chain_id]) > 100) {
            array_pop($chains['chain_logs'][$chain_id]);
        }
        
        saveData('approval-chains', $chains);
    }
    
    /**
     * دریافت پیشرفت زنجیره تأیید
     */
    public static function getChainProgress($chain_id) {
        $chain = self::getInvoiceChainByChainId($chain_id);
        if (!$chain) {
            return null;
        }
        
        $total_stages = count($chain['stages']);
        $completed_stages = count($chain['completed_stages']);
        $current_stage = $chain['current_stage'];
        
        $progress = [
            'total_stages' => $total_stages,
            'completed_stages' => $completed_stages,
            'current_stage' => $current_stage,
            'progress_percentage' => $total_stages > 0 ? round(($completed_stages / $total_stages) * 100) : 0,
            'current_stage_users' => $chain['stages'][$current_stage]['users'] ?? [],
            'current_stage_deadline' => $chain['deadlines'][$current_stage] ?? null,
            'stage_names' => array_column($chain['stages'], 'name'),
            'status' => $chain['status']
        ];
        
        // محاسبه زمان باقیمانده
        if ($progress['current_stage_deadline']) {
            $remaining_seconds = $progress['current_stage_deadline'] - time();
            $progress['remaining_days'] = max(0, floor($remaining_seconds / 86400));
            $progress['remaining_hours'] = max(0, floor(($remaining_seconds % 86400) / 3600));
            $progress['is_overdue'] = $remaining_seconds < 0;
        }
        
        return $progress;
    }
    
    /**
     * دریافت زنجیره با شناسه زنجیره
     */
    private static function getInvoiceChainByChainId($chain_id) {
        $chains = loadData('approval-chains');
        return $chains['chains'][$chain_id] ?? null;
    }
    
    /**
     * تعیین سرپرست برای زنجیره
     */
    public static function setChainSupervisor($chain_id, $supervisor_id) {
        $chains = loadData('approval-chains');
        
        if (!isset($chains['chains'][$chain_id])) {
            return false;
        }
        
        $chains['chains'][$chain_id]['supervisor_id'] = $supervisor_id;
        $chains['supervisors'][$chain_id] = $supervisor_id;
        
        // ثبت لاگ
        $supervisor = getUser($supervisor_id);
        self::logChainAction($chain_id, 'set_supervisor', 
            "تعیین سرپرست: {$supervisor['username']}");
        
        return saveData('approval-chains', $chains);
    }
    
    /**
     * تفویض اختیار توسط سرپرست
     */
    public static function delegateAuthority($chain_id, $delegate_user_id, $duration_hours = 24) {
        $chains = loadData('approval-chains');
        
        if (!isset($chains['chains'][$chain_id])) {
            return false;
        }
        
        $supervisor_id = $chains['chains'][$chain_id]['supervisor_id'];
        if ($supervisor_id !== $_SESSION['user_id']) {
            return false;
        }
        
        $delegation = [
            'delegate_id' => $delegate_user_id,
            'supervisor_id' => $supervisor_id,
            'chain_id' => $chain_id,
            'start_time' => time(),
            'end_time' => time() + ($duration_hours * 3600),
            'permissions' => ['view_all', 'force_approve', 'skip_stage']
        ];
        
        $chains['chains'][$chain_id]['assigned_delegates'][] = $delegation;
        
        // ثبت لاگ
        $delegate = getUser($delegate_user_id);
        self::logChainAction($chain_id, 'delegate', 
            "تفویض اختیار به {$delegate['username']} برای {$duration_hours} ساعت");
        
        return saveData('approval-chains', $chains);
    }
    
    /**
     * مدیریت کش - دریافت از کش
     */
    public static function getCache($key) {
        // اول بررسی کش حافظه
        if (isset(self::$memory_cache[$key])) {
            $data = self::$memory_cache[$key];
            if ($data['expire'] > time()) {
                return $data['value'];
            }
            unset(self::$memory_cache[$key]);
        }
        
        // سپس بررسی کش فایل
        $cache_data = loadData('system-cache');
        if (isset($cache_data['cache'][$key])) {
            $data = $cache_data['cache'][$key];
            if ($data['expire'] > time()) {
                // ذخیره در کش حافظه
                self::$memory_cache[$key] = $data;
                return $data['value'];
            } else {
                // حذف از کش اگر منقضی شده
                unset($cache_data['cache'][$key]);
                saveData('system-cache', $cache_data);
            }
        }
        
        return null;
    }
    
    /**
     * مدیریت کش - ذخیره در کش
     */
    public static function setCache($key, $value, $ttl_seconds = 300) {
        $expire = time() + $ttl_seconds;
        
        // ذخیره در کش حافظه
        self::$memory_cache[$key] = [
            'value' => $value,
            'expire' => $expire,
            'created' => time()
        ];
        
        // ذخیره در کش فایل
        $cache_data = loadData('system-cache');
        $cache_data['cache'][$key] = [
            'value' => $value,
            'expire' => $expire,
            'created' => time(),
            'size' => strlen(serialize($value))
        ];
        
        // آپدیت آمار
        $cache_data['stats']['hits'] = ($cache_data['stats']['hits'] ?? 0) + 1;
        $cache_data['stats']['size'] = array_sum(array_column($cache_data['cache'], 'size'));
        
        // پاکسازی خودکار اگر کش بزرگ شده
        if (count($cache_data['cache']) > 1000) {
            self::cleanupExpiredCache(true);
        }
        
        return saveData('system-cache', $cache_data);
    }
    
    /**
     * مدیریت کش - پاک کردن کش
     */
    public static function clearCache($key = null) {
        if ($key === null) {
            // پاک کردن همه کش
            self::$memory_cache = [];
            $cache_data = loadData('system-cache');
            $cache_data['cache'] = [];
            saveData('system-cache', $cache_data);
        } else {
            // پاک کردن کش خاص
            unset(self::$memory_cache[$key]);
            $cache_data = loadData('system-cache');
            unset($cache_data['cache'][$key]);
            saveData('system-cache', $cache_data);
        }
    }
    
    /**
     * پاکسازی کش‌های منقضی شده
     */
    public static function cleanupExpiredCache($force = false) {
        $cache_data = loadData('system-cache');
        $now = time();
        
        // هر 30 دقیقه یکبار پاکسازی شود
        if (!$force && ($now - ($cache_data['last_cleanup'] ?? 0)) < 1800) {
            return;
        }
        
        $cleaned_count = 0;
        foreach ($cache_data['cache'] as $key => $item) {
            if ($item['expire'] < $now) {
                unset($cache_data['cache'][$key]);
                $cleaned_count++;
                
                // پاک از کش حافظه هم
                unset(self::$memory_cache[$key]);
            }
        }
        
        $cache_data['last_cleanup'] = $now;
        saveData('system-cache', $cache_data);
        
        return $cleaned_count;
    }
    
    /**
     * بررسی هشدارهای تأخیر
     */
    public static function checkDelayAlerts() {
        $chains = loadData('approval-chains');
        $alerts = [];
        
        foreach ($chains['chains'] as $chain_id => $chain) {
            if ($chain['status'] !== 'completed') {
                $current_stage = $chain['current_stage'];
                $deadline = $chain['deadlines'][$current_stage] ?? 0;
                
                if ($deadline && $deadline < time()) {
                    // تأخیر بیش از 24 ساعت
                    $overdue_hours = floor((time() - $deadline) / 3600);
                    
                    if ($overdue_hours >= 24) {
                        $alerts[] = [
                            'chain_id' => $chain_id,
                            'invoice_id' => $chain['invoice_id'],
                            'stage' => $current_stage,
                            'overdue_hours' => $overdue_hours,
                            'stage_users' => $chain['stages'][$current_stage]['users'] ?? []
                        ];
                        
                        // ارسال نوتیفیکیشن به سرپرست
                        if ($chain['supervisor_id']) {
                            sendNotification(
                                $chain['supervisor_id'],
                                "🚨 هشدار تأخیر شدید: فاکتور #{$chain['invoice_id']} بیش از 24 ساعت تأخیر دارد",
                                $chain['invoice_id']
                            );
                        }
                    }
                }
            }
        }
        
        return $alerts;
    }
    
    /**
     * دریافت زنجیره‌های تحت نظارت یک سرپرست
     */
    public static function getSupervisorChains($supervisor_id) {
        $chains = loadData('approval-chains');
        $supervisor_chains = [];
        
        foreach ($chains['chains'] as $chain_id => $chain) {
            if ($chain['supervisor_id'] === $supervisor_id) {
                $supervisor_chains[$chain_id] = $chain;
            }
        }
        
        return $supervisor_chains;
    }
    
    /**
     * دریافت آمار زنجیره‌ها
     */
    public static function getChainStatistics() {
        $chains = loadData('approval-chains');
        $stats = [
            'total_chains' => count($chains['chains'] ?? []),
            'active_chains' => 0,
            'completed_chains' => 0,
            'delayed_chains' => 0,
            'average_completion_time' => 0,
            'by_stage' => [],
            'by_supervisor' => []
        ];
        
        $total_completion_time = 0;
        $completed_count = 0;
        
        foreach ($chains['chains'] as $chain) {
            if ($chain['status'] === 'completed') {
                $stats['completed_chains']++;
                if (isset($chain['completed_at']) && isset($chain['created_at'])) {
                    $completion_time = $chain['completed_at'] - $chain['created_at'];
                    $total_completion_time += $completion_time;
                    $completed_count++;
                }
            } else {
                $stats['active_chains']++;
                
                // بررسی تأخیر
                $current_stage = $chain['current_stage'];
                $deadline = $chain['deadlines'][$current_stage] ?? 0;
                if ($deadline && $deadline < time()) {
                    $stats['delayed_chains']++;
                }
            }
            
            // آمار بر اساس مرحله
            $current_stage = $chain['current_stage'];
            if (!isset($stats['by_stage'][$current_stage])) {
                $stats['by_stage'][$current_stage] = 0;
            }
            $stats['by_stage'][$current_stage]++;
            
            // آمار بر اساس سرپرست
            $supervisor_id = $chain['supervisor_id'];
            if ($supervisor_id) {
                if (!isset($stats['by_supervisor'][$supervisor_id])) {
                    $stats['by_supervisor'][$supervisor_id] = 0;
                }
                $stats['by_supervisor'][$supervisor_id]++;
            }
        }
        
        if ($completed_count > 0) {
            $stats['average_completion_time'] = round($total_completion_time / $completed_count / 3600, 2); // به ساعت
        }
        
        return $stats;
    }
}
?>