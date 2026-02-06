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
    $password = trim($_POST['password']);
    
    if (loginUser($username, $password)) {
        header('Location: dashboard.php');
        exit();
    } else {
        $error = 'نام کاربری یا رمز عبور اشتباه است';
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ورود - سیستم پیگیری فاکتور</title>
    <link rel="stylesheet" href="css/auth.css">
    <link rel="icon" href="assets/icons/favicon.ico">
</head>
<body>
    <div class="container">
        <div class="curved-shape"></div>
        <div class="curved-shape2"></div>
        
        <!-- فرم ورود -->
        <div class="form-box Login">
            <h2 class="animation" style="--D:0; --S:21">ورود</h2>
            <form action="" method="POST">
                <?php if ($error): ?>
                    <div class="error-message"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="success-message"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <div class="input-box animation" style="--D:1; --S:22">
                    <input type="text" name="username" required value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                    <label>نام کاربری</label>
                </div>

                <div class="input-box animation" style="--D:2; --S:23">
                    <input type="password" name="password" required>
                    <label>رمز عبور</label>
                </div>

                <div class="input-box animation" style="--D:3; --S:24">
                    <button class="btn" type="submit">ورود</button>
                </div>

                <div class="input-box animation" style="--D:4; --S:25">
                    <div style="text-align: center; margin-top: 20px;">
                        <a href="forgot-password.php" style="color: #4a9eff; text-decoration: none; font-size: 13px;">
                            🔐 رمز عبور خود را فراموش کرده‌اید؟
                        </a>
                    </div>
                </div>

                <div class="regi-link animation" style="--D:5; --S:26">
                    <p>حساب کاربری ندارید؟ <br> 
                    <a href="register.php">ثبت نام در سیستم</a>
                    </p>
                </div>
            </form>
        </div>

        <div class="info-content Login">
            <h2 class="animation" style="--D:0; --S:20">سیستم پیگیری فاکتور</h2>
            <p class="animation" style="--D:1; --S:21">به سامانه جامع مدیریت و پیگیری فاکتورها خوش آمدید</p>
        </div>

        <!-- فرم ثبت نام (مخفی) -->
        <div class="form-box Register">
            <h2 class="animation" style="--li:17; --S:0;">ثبت نام در سیستم</h2>
            <form action="register.php" method="POST">
                <div class="input-box animation" style="--li:18; --S:1">
                    <input type="text" name="username" required>
                    <label>نام کاربری</label>
                </div>

                <div class="input-box animation" style="--li:19; --S:2">
                    <input type="text" name="department" required>
                    <label>نام بخش</label>
                </div>

                <div class="input-box animation" style="--li:20; --S:3">
                    <input type="email" name="email" required class="email-field" placeholder="برای بازیابی رمز عبور ضروری است">
                    <label>ایمیل</label>
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
                    <p>حساب کاربری دارید؟ <br> <a href="#" class="back-to-login">ورود به سیستم</a></p>
                </div>
            </form>
        </div>

        <div class="info-content Register">
            <h2 class="animation" style="--li:17; --S:0">سیستم پیگیری فاکتور</h2>
            <p class="animation" style="--li:18; --S:1">به سامانه جامع مدیریت و پیگیری فاکتورها خوش آمدید</p>
        </div>

        <!-- فرم بازیابی رمز عبور (مخفی) -->
        <div class="form-box ForgotPassword">
            <h2 class="animation" style="--li:25; --S:8;">بازیابی رمز عبور</h2>
            <form action="forgot-password.php" method="POST">
                <div class="input-box animation" style="--li:26; --S:9">
                    <input type="email" name="email" required>
                    <label>ایمیل خود را وارد کنید</label>
                </div>

                <div class="input-box animation" style="--li:27; --S:10">
                    <button class="btn" type="submit">ارسال لینک بازیابی</button>
                </div>

                <div class="regi-link animation" style="--li:28; --S:11;">
                    <p><a href="#" class="back-to-login">بازگشت به صفحه ورود</a></p>
                </div>
            </form>
        </div>

        <div class="info-content ForgotPassword">
            <h2 class="animation" style="--li:25; --S:8">بازیابی رمز عبور</h2>
            <p class="animation" style="--li:26; --S:9">لینک بازیابی رمز عبور به ایمیل شما ارسال خواهد شد</p>
        </div>
    </div>

    <script src="js/main.js"></script>
<script>
    // مدیریت انتقال بین فرم‌ها با انیمیشن
    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        const mode = urlParams.get('mode');
        const container = document.querySelector('.container');
        
        // این بخش انیمیشن اولیه ورود به فرم‌ها را مدیریت می‌کند
        if (mode === 'register') {
            setTimeout(() => {
                container.classList.add('active');
            }, 100);
        } else if (mode === 'forgot') {
            setTimeout(() => {
                container.classList.add('active');
                container.classList.add('forgot-password-active');
            }, 100);
        }
        
        // مدیریت کلیک روی لینک‌های رفتن به ثبت نام
        document.querySelectorAll('a[href="register.php"]').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                window.location.href = 'login.php?mode=register';
            });
        });
        
        // مدیریت کلیک روی لینک‌های رفتن به بازیابی رمز
        document.querySelectorAll('a[href="forgot-password.php"]').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                window.location.href = 'login.php?mode=forgot';
            });
        });
        
        // --- ✨ بخش اصلاح شده: مدیریت کلیک روی لینک‌های "ورود به سیستم" ---
        document.querySelectorAll('a.back-to-login').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault(); // 1. جلوگیری از ریلود فوری
                
                // 2. شروع انیمیشن بازگشت با حذف کلاس 'active'
                // این کار باعث می‌شود هر فرمی که فعال است (ثبت نام یا بازیابی)
                // انیمیشن خروج خود را اجرا کند.
                container.classList.remove('active');
                
                // 3. اگر در فرم "بازیابی رمز" بودیم، آن کلاس را هم حذف می‌کنیم
                // تا حالت به درستی پاکسازی شود.
                if (container.classList.contains('forgot-password-active')) {
                    container.classList.remove('forgot-password-active');
                }
                
                // 4. منتظر اتمام طولانی‌ترین انیمیشن CSS می‌مانیم
                // در auth.css، انیمیشن .curved-shape دارای 'transition: 1.5s ease' است.
                // ما کمی بیشتر (1550ms) صبر می‌کنیم تا مطمئن شویم انیمیشن کامل شده است.
                setTimeout(() => {
                    // 5. پس از اتمام کامل انیمیشن، به صفحه اصلی ورود ریدایرکت می‌کنیم
                    window.location.href = 'login.php';
                }, 1550); // 1.5 ثانیه برای انیمیشن + 50 میلی‌ثانیه بافر
            });
        });
    });
    </script>
</body>
</html>