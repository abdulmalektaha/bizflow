<?php
// --- 1. تضمين الإعدادات (سيبدأ الجلسة) ---
require_once 'config.php';

// --- 2. التحقق من تسجيل الدخول ---
// إذا كان المستخدم مسجلاً دخوله بالفعل، قم بإعادة توجيهه إلى لوحة التحكم
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// --- 3. معالجة نموذج التسجيل ---
$error_message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $company_name = trim($_POST['company_name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];

    // --- التحقق من المدخلات ---
    if (empty($company_name) || empty($email) || empty($password)) {
        $error_message = "يرجى ملء جميع الحقول المطلوبة.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "البريد الإلكتروني غير صالح.";
    } elseif (strlen($password) < 6) {
        $error_message = "كلمة المرور يجب أن تكون 6 أحرف على الأقل.";
    } elseif ($password !== $password_confirm) {
        $error_message = "كلمتا المرور غير متطابقتين.";
    } else {
        try {
            // التحقق أولاً إذا كان البريد الإلكتروني مستخدماً
            $check_stmt = $db_connection->prepare("SELECT user_id FROM users WHERE email = :email");
            $check_stmt->execute([':email' => $email]);
            
            if ($check_stmt->fetch()) {
                $error_message = "هذا البريد الإلكتروني مسجل بالفعل. حاول تسجيل الدخول.";
            } else {
                // تشفير كلمة المرور (Hashing) - خطوة أمان هامة
                $password_hash = password_hash($password, PASSWORD_DEFAULT);

                // إدراج المستخدم الجديد في قاعدة البيانات
                $sql = "INSERT INTO users (company_name, email, password_hash) VALUES (:company_name, :email, :password_hash)";
                $stmt = $db_connection->prepare($sql);
                $stmt->execute([
                    ':company_name' => $company_name,
                    ':email' => $email,
                    ':password_hash' => $password_hash
                ]);

                // تخزين رسالة نجاح في الجلسة لإظهارها في صفحة تسجيل الدخول
                $_SESSION['success_message'] = "تم إنشاء حسابك بنجاح! يمكنك الآن تسجيل الدخول.";
                header("Location: login.php");
                exit;
            }

        } catch (PDOException $e) {
            logError("Database Error (register.php): " . $e->getMessage());
            // 23505 هو رمز خطأ "unique violation" في PostgreSQL
            if ($e->getCode() == '23505') { 
                $error_message = "هذا البريد الإلكتروني مسجل بالفعل.";
            } else {
                $error_message = "حدث خطأ في قاعدة البيانات أثناء محاولة إنشاء الحساب.";
            }
        }
    }
}
?>

<!-- 4. عرض محتوى الصفحة (HTML) -->
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إنشاء حساب جديد - BizFlow</title>
    <!-- ربط ملف التنسيق الرئيسي -->
    <link rel="stylesheet" href="style.css">
    <!-- أنماط مخصصة لصفحات المصادقة -->
    <style>
        body {
            background-color: #f4f7f6; /* نفس خلفية الصفحات الداخلية */
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px 0; /* إضافة padding للأسفل والأعلى */
        }
    </style>
</head>
<body>

    <!-- استخدام حاوية المصادقة الجديدة -->
    <div class="auth-container">
        <h1>إنشاء حساب جديد</h1>
        <p>ابدأ في إدارة فواتيرك مع BizFlow</p>

        <!-- عرض رسائل الحالة -->
        <?php if ($error_message): ?>
            <div class="message error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <!-- نموذج التسجيل -->
        <form action="register.php" method="POST">
            <div class="form-group">
                <label for="company_name">اسم الشركة (أو اسمك)</label>
                <input type="text" id="company_name" name="company_name" required>
            </div>

            <div class="form-group">
                <label for="email">البريد الإلكتروني</label>
                <input type="email" id="email" name="email" required>
            </div>
            
            <div class="form-group">
                <label for="password">كلمة المرور (6 أحرف على الأقل)</label>
                <input type="password" id="password" name="password" required>
            </div>

            <div class="form-group">
                <label for="password_confirm">تأكيد كلمة المرور</label>
                <input type="password" id="password_confirm" name="password_confirm" required>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="button">إنشاء الحساب</button>
            </div>
        </form>
        
        <div class="auth-switch">
            لديك حساب بالفعل؟ <a href="login.php">تسجيل الدخول</a>
        </div>
    </div>

</body>
</html>

