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
    $username = trim($_POST['username']);
    $department = trim($_POST['department']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    
    if (empty($username) || empty($department) || empty($email) || empty($password)) {
        $error = 'تمامی فیلدها الزامی هستند';
    } elseif ($password !== $confirm_password) {
        $error = 'رمز عبور و تکرار آن مطابقت ندارند';
    } elseif (strlen($password) < 6) {
        $error = 'رمز عبور باید حداقل 6 کاراکتر باشد';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'فرمت ایمیل نامعتبر است';
    } else {
        if (registerUser($username, $department, $email, $password)) {
            $success = 'ثبت نام با موفقیت انجام شد. اکنون می‌توانید وارد شوید.';
            header('Refresh: 3; url=login.php');
        } else {
            $error = 'نام کاربری یا ایمیل قبلاً استفاده شده است';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ثبت نام - سیستم پیگیری فاکتور</title>
    <link rel="stylesheet" href="css/auth.css">
    <link rel="icon" href="assets/icons/favicon.ico">
</head>
<body>
    <div class="container active">
        <div class="curved-shape"></div>
        <div class="curved-shape2"></div>
        
        <div class="form-box Register">
            <h2 class="animation" style="--li:17; --S:0;">ثبت نام در سیستم</h2>
            <form action="" method="POST">
                <?php if ($error): ?>
                    <div class="error-message"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="success-message"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <div class="input-box animation" style="--li:18; --S:1">
                    <input type="text" name="username" required value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                    <label>نام کاربری</label>
                </div>

                <div class="input-box animation" style="--li:19; --S:2">
                    <input type="text" name="department" required value="<?php echo htmlspecialchars($_POST['department'] ?? ''); ?>">
                    <label>نام بخش</label>
                </div>

                <div class="input-box animation" style="--li:20; --S:3">
                    <input type="email" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    <label>ایمیل</label>
                    <small style="color:#ff6b6b; font-size:11px; display:block; margin-top:5px;">برای بازیابی رمز عبور ضروری است</small>
                </div>

                <div class="input-box animation" style="--li:21; --S:4">
                    <input type="password" name="password" required>
                    <label>رمز عبور</label>
                </div>

                <div class="input-box animation" style="--li:22; --S:5">
                    <input type="password" name="confirm_password" required>
                    <label>تکرار رمز عبور</label>
                </div>

                <div class="input-box animation" style="--li:23; --S:6">
                    <button class="btn" type="submit">ثبت نام</button>
                </div>

                <div class="regi-link animation" style="--li:24; --S:7;">
                    <p>حساب کاربری دارید؟ <a href="login.php">ورود به سیستم</a></p>
                </div>
            </form>
        </div>

        <div class="info-content Register">
            <h2 class="animation" style="--li:17; --S:0">سیستم پیگیری فاکتور</h2>
            <p class="animation" style="--li:18; --S:1">به سامانه جامع مدیریت و پیگیری فاکتورها خوش آمدید</p>
        </div>
    </div>

    <script src="js/main.js"></script>
</body>
</html>