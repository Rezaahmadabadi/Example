<?php
require_once 'config.php';
require_once 'functions.php';

// شروع session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// تابع برای بررسی لاگین بودن کاربر
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// تابع برای بررسی ادمین بودن کاربر
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// ========== توابع جدید برای سیستم سرپرستی ==========

/**
 * تابع برای بررسی سرپرست بودن کاربر
 */
function isSupervisor($user_id = null) {
    if ($user_id === null) {
        $user_id = $_SESSION['user_id'] ?? null;
    }
    
    $user = getUser($user_id);
    return $user && isset($user['is_supervisor']) && $user['is_supervisor'];
}

/**
 * تابع برای بررسی دسترسی سرپرستی
 */
function hasSupervisorAccess($invoice_id) {
    if (isAdmin()) return true;
    
    if (!isSupervisor()) return false;
    
    require_once 'approval-system.php';
    $chain = ApprovalSystem::getInvoiceChain($invoice_id);
    
    if (!$chain) return false;
    
    // بررسی آیا کاربر سرپرست این زنجیره است
    return $chain['supervisor_id'] === $_SESSION['user_id'];
}

/**
 * تابع برای دریافت کاربران زیردست یک سرپرست
 */
function getSubordinateUsers($supervisor_id) {
    $users = loadData('users');
    $subordinates = [];
    
    foreach ($users as $user) {
        if (isset($user['supervisor_id']) && $user['supervisor_id'] === $supervisor_id) {
            $subordinates[] = $user;
        }
    }
    
    return $subordinates;
}

/**
 * تابع برای تنظیم سرپرست برای کاربر
 */
function setUserSupervisor($user_id, $supervisor_id) {
    $users = loadData('users');
    $updated = false;
    
    foreach ($users as &$user) {
        if ($user['id'] === $user_id) {
            $user['supervisor_id'] = $supervisor_id;
            $updated = true;
            break;
        }
    }
    
    if ($updated) {
        // همچنین باید کاربر سرپرست را به عنوان سرپرست علامت‌گذاری کنیم
        foreach ($users as &$user) {
            if ($user['id'] === $supervisor_id) {
                $user['is_supervisor'] = true;
                break;
            }
        }
        
        return saveData('users', $users);
    }
    
    return false;
}

// تابع برای دریافت اطلاعات کاربر
function getUser($user_id) {
    $users = loadData('users');
    foreach ($users as $user) {
        if ($user['id'] === $user_id) {
            return $user;
        }
    }
    return null;
}

// تابع برای لاگین کاربر
function loginUser($username, $password) {
    $users = loadData('users');
    
    foreach ($users as $user) {
        if ($user['username'] === $username && $user['is_active']) {
            // بررسی رمز عبور
            if (password_verify($password, $user['password'])) {
                // تنظیم session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['department'] = $user['department'];
                $_SESSION['role'] = $user['role'];
                
                // به‌روزرسانی آخرین لاگین
                updateLastLogin($user['id']);
                
                return true;
            }
        }
    }
    
    return false;
}

// تابع برای ثبت‌نام کاربر جدید
function registerUser($username, $department, $email, $password) {
    $users = loadData('users');
    
    // بررسی تکراری نبودن نام کاربری و ایمیل
    foreach ($users as $user) {
        if ($user['username'] === $username || $user['email'] === $email) {
            return false;
        }
    }
    
    // ایجاد کاربر جدید
    $new_user = [
        'id' => uniqid(),
        'username' => $username,
        'department' => $department,
        'email' => $email,
        'password' => password_hash($password, PASSWORD_DEFAULT),
        'role' => 'user',
        'is_active' => true,
        'can_create_invoice' => false,
        'can_receive_referral' => true,
        'avatar' => '',
        'created_at' => time(),
        'last_login' => null
    ];
    
    $users[] = $new_user;
    
    if (saveData('users', $users)) {
        return true;
    }
    
    return false;
}

// تابع برای تغییر رمز عبور
function changePassword($user_id, $current_password, $new_password) {
    $users = loadData('users');
    
    foreach ($users as &$user) {
        if ($user['id'] === $user_id) {
            // بررسی رمز عبور فعلی
            if (password_verify($current_password, $user['password'])) {
                $user['password'] = password_hash($new_password, PASSWORD_DEFAULT);
                return saveData('users', $users);
            }
            break;
        }
    }
    
    return false;
}

// تابع برای به‌روزرسانی آخرین لاگین
function updateLastLogin($user_id) {
    $users = loadData('users');
    
    foreach ($users as &$user) {
        if ($user['id'] === $user_id) {
            $user['last_login'] = time();
            break;
        }
    }
    
    saveData('users', $users);
}

// تابع برای ارسال ایمیل بازیابی رمز عبور
function sendPasswordResetEmail($email) {
    $users = loadData('users');
    
    foreach ($users as $user) {
        if ($user['email'] === $email && $user['is_active']) {
            // تولید توکن بازیابی
            $token = bin2hex(random_bytes(32));
            $expires = time() + (60 * 60); // 1 ساعت
            
            // ذخیره توکن در فایل جداگانه
            $reset_tokens = loadData('password-reset-tokens');
            $reset_tokens[] = [
                'token' => $token,
                'user_id' => $user['id'],
                'expires' => $expires
            ];
            saveData('password-reset-tokens', $reset_tokens);
            
            // در اینجا کد ارسال ایمیل واقعی قرار می‌گیرد
            // برای نمونه، فقط توکن را برمی‌گردانیم
            return $token;
        }
    }
    
    return false;
}

// تابع برای بررسی معتبر بودن توکن بازیابی
function isValidResetToken($token) {
    $reset_tokens = loadData('password-reset-tokens');
    $now = time();
    
    foreach ($reset_tokens as $reset_token) {
        if ($reset_token['token'] === $token && $reset_token['expires'] > $now) {
            return true;
        }
    }
    
    return false;
}

// تابع برای بازیابی رمز عبور
function resetPassword($token, $new_password) {
    $reset_tokens = loadData('password-reset-tokens');
    $users = loadData('users');
    
    foreach ($reset_tokens as $reset_token) {
        if ($reset_token['token'] === $token && $reset_token['expires'] > time()) {
            // پیدا کردن کاربر و تغییر رمز عبور
            foreach ($users as &$user) {
                if ($user['id'] === $reset_token['user_id']) {
                    $user['password'] = password_hash($new_password, PASSWORD_DEFAULT);
                    
                    if (saveData('users', $users)) {
                        // حذف توکن استفاده شده
                        $reset_tokens = array_filter($reset_tokens, function($t) use ($token) {
                            return $t['token'] !== $token;
                        });
                        saveData('password-reset-tokens', $reset_tokens);
                        
                        return true;
                    }
                }
            }
        }
    }
    
    return false;
}

// ========== توابع جدید برای سامانه مودیان ==========

// تابع برای بررسی دسترسی کاربر به درخواست سامانه مودیان
function canUserAccessTaxTransaction($user_id, $transaction_id) {
    $transaction = getTaxTransaction($transaction_id);
    if (!$transaction) return false;
    
    return $transaction['created_by'] === $user_id || 
           in_array($user_id, $transaction['assigned_to']) ||
           isAdmin();
}

// تابع برای بررسی مجوز ایجاد فاکتور
function canCreateInvoice($user_id) {
    if (isAdmin()) return true;
    
    $user = getUser($user_id);
    return $user && isset($user['can_create_invoice']) && $user['can_create_invoice'];
}

// تابع برای بررسی مجوز دریافت ارجاع
function canReceiveReferral($user_id) {
    $user = getUser($user_id);
    return $user && (!isset($user['can_receive_referral']) || $user['can_receive_referral']);
}

// تابع برای دریافت کاربران فعال
function getActiveUsers() {
    $users = loadData('users');
    return array_filter($users, function($user) {
        return $user['is_active'];
    });
}

// تابع برای دریافت کاربران قابل ارجاع
function getReferrableUsers($exclude_user_id = null) {
    $users = getActiveUsers();
    
    return array_filter($users, function($user) use ($exclude_user_id) {
        if ($user['id'] === $exclude_user_id) return false;
        return canReceiveReferral($user['id']);
    });
}

// تابع برای بررسی مالکیت فاکتور
function isInvoiceOwner($user_id, $invoice_id) {
    $invoices = loadData('invoices');
    
    foreach ($invoices as $invoice) {
        if ($invoice['id'] === $invoice_id) {
            return $invoice['created_by'] === $user_id;
        }
    }
    
    return false;
}

// تابع برای بررسی دسترسی به فاکتور
function canUserAccessInvoice($user_id, $invoice_id) {
    if (isAdmin()) return true;
    
    $invoices = loadData('invoices');
    
    foreach ($invoices as $invoice) {
        if ($invoice['id'] === $invoice_id) {
            // اگر کاربر ایجادکننده باشد
            if ($invoice['created_by'] === $user_id) return true;
            
            // اگر کاربر فعلی باشد
            if ($invoice['current_user_id'] === $user_id) return true;
            
            // اگر در تاریخچه پیگیری باشد
            foreach ($invoice['tracking_history'] as $history) {
                if ($history['user_id'] === $user_id) return true;
            }
        }
    }
    
    return false;
}

// تابع برای لاگ کردن فعالیت‌ها
function logActivity($user_id, $action, $description, $related_id = null) {
    $activities = loadData('activities');
    
    $activity = [
        'id' => uniqid(),
        'user_id' => $user_id,
        'action' => $action,
        'description' => $description,
        'related_id' => $related_id,
        'timestamp' => time(),
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ];
    
    $activities[] = $activity;
    saveData('activities', $activities);
}

// تابع برای پاک کردن توکن‌های منقضی شده
function cleanupExpiredTokens() {
    $reset_tokens = loadData('password-reset-tokens');
    $now = time();
    
    $valid_tokens = array_filter($reset_tokens, function($token) use ($now) {
        return $token['expires'] > $now;
    });
    
    saveData('password-reset-tokens', $valid_tokens);
}

// اجرای پاکسازی در هر بار لاود (اختیاری)
cleanupExpiredTokens();
?>