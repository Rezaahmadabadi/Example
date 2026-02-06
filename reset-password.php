<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$success = '';
$token = $_GET['token'] ?? '';

// بررسی معتبر بودن توکن
if (!$token || !isValidResetToken($token)) {
    header('Location: forgot-password.php?error=invalid_token');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($new_password) || empty($confirm_password)) {
        $error = 'لطفا تمام فیلدها را پر کنید';
    } elseif ($new_password !== $confirm_password) {
        $error = 'رمز عبور و تکرار آن مطابقت ندارند';
    } elseif (strlen($new_password) < 6) {
        $error = 'رمز عبور باید حداقل 6 کاراکتر باشد';
    } else {
        if (resetPassword($token, $new_password)) {
            $success = 'رمز عبور با موفقیت تغییر یافت';
            header('Refresh: 3; url=login.php');
        } else {
            $error = 'خطا در تغییر رمز عبور';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تنظیم رمز جدید - سیستم پیگیری فاکتور</title>
    <link rel="stylesheet" href="css/auth.css">
    <link rel="icon" href="assets/icons/favicon.ico">
</head>
<body>
    <div class="container active">
        <div class="curved-shape"></div>
        <div class="curved-shape2"></div>
        
        <div class="form-box ResetPassword">
            <h2 class="animation" style="--li:17; --S:0;">تنظیم رمز جدید</h2>
            <form action="" method="POST">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                
                <?php if ($error): ?>
                    <div class="error-message"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="success-message"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <div class="input-box animation" style="--li:18; --S:1">
                    <input type="password" name="new_password" required>
                    <label>رمز عبور جدید</label>
                </div>

                <div class="input-box animation" style="--li:19; --S:2">
                    <input type="password" name="confirm_password" required>
                    <label>تکرار رمز عبور جدید</label>
                </div>

                <div class="input-box animation" style="--li:20; --S:3">
                    <button class="btn" type="submit">تغییر رمز عبور</button>
                </div>

                <div class="regi-link animation" style="--li:21; --S:4;">
                    <p><a href="login.php">بازگشت به صفحه ورود</a></p>
                </div>
            </form>
        </div>

        <div class="info-content ResetPassword">
            <h2 class="animation" style="--li:17; --S:0">تنظیم رمز جدید</h2>
            <p class="animation" style="--li:18; --S:1">رمز عبور جدید خود را وارد کنید</p>
        </div>
    </div>

    <script src="js/main.js"></script>
</body>
</html>