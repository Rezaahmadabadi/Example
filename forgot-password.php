<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    
    if (empty($email)) {
        $error = 'لطفا ایمیل خود را وارد کنید';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'فرمت ایمیل نامعتبر است';
    } else {
        if (sendPasswordResetEmail($email)) {
            $success = 'لینک بازیابی رمز عبور به ایمیل شما ارسال شد';
        } else {
            $error = 'ایمیل یافت نشد';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>بازیابی رمز عبور - سیستم پیگیری فاکتور</title>
    <link rel="stylesheet" href="css/auth.css">
    <link rel="icon" href="assets/icons/favicon.ico">
</head>
<body>
    <div class="container active">
        <div class="curved-shape"></div>
        <div class="curved-shape2"></div>
        
        <div class="form-box ForgotPassword">
            <h2 class="animation" style="--li:17; --S:0;">بازیابی رمز عبور</h2>
            <form action="" method="POST">
                <?php if ($error): ?>
                    <div class="error-message"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="success-message"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <div class="input-box animation" style="--li:18; --S:1">
                    <input type="email" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    <label>ایمیل خود را وارد کنید</label>
                </div>

                <div class="input-box animation" style="--li:19; --S:2">
                    <button class="btn" type="submit">ارسال لینک بازیابی</button>
                </div>

                <div class="regi-link animation" style="--li:20; --S:3;">
                    <p><a href="login.php">بازگشت به صفحه ورود</a></p>
                </div>
            </form>
        </div>

        <div class="info-content ForgotPassword">
            <h2 class="animation" style="--li:17; --S:0">بازیابی رمز عبور</h2>
            <p class="animation" style="--li:18; --S:1">لینک بازیابی رمز عبور به ایمیل شما ارسال خواهد شد</p>
        </div>
    </div>

    <script src="js/main.js"></script>
</body>
</html>