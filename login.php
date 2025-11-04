<?php
// --- 1. تضمين الإعدادات (سيبدأ الجلسة) ---
require_once 'config.php';

// --- 2. التحقق من تسجيل الدخول ---
// إذا كان المستخدم مسجلاً دخوله بالفعل، قم بإعادة توجيهه إلى لوحة التحكم
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// --- 3. معالجة نموذج تسجيل الدخول ---
$error_message = null;
$success_message = $_SESSION['success_message'] ?? null; // جلب رسالة النجاح من التسجيل
if ($success_message) {
    unset($_SESSION['success_message']); // حذف الرسالة بعد عرضها
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error_message = "البريد الإلكتروني وكلمة المرور مطلوبان.";
    } else {
        try {
            // البحث عن المستخدم عن طريق البريد الإلكتروني
            $stmt = $db_connection->prepare("SELECT user_id, password_hash, company_name, email FROM users WHERE email = :email");
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // التحقق من وجود المستخدم ومطابقة كلمة المرور
            if ($user && password_verify($password, $user['password_hash'])) {
                // نجاح تسجيل الدخول! قم بتخزين بيانات المستخدم في الجلسة
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['company_name'] = $user['company_name'];
                $_SESSION['email'] = $user['email'];
                
                // إعادة التوجيه إلى لوحة التحكم الرئيسية
                header("Location: index.php");
                exit;
            } else {
                // فشل تسجيل الدخول
                $error_message = "البريد الإلكتروني أو كلمة المرور غير صحيحة.";
            }
        } catch (PDOException $e) {
            logError("Database Error (login.php): " . $e->getMessage());
            $error_message = "حدث خطأ في قاعدة البيانات أثناء محاولة تسجيل الدخول.";
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
    <title>تسجيل الدخول - BizFlow</title>
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
        }
    </style>
</head>
<body>

    <!-- استخدام حاوية المصادقة الجديدة -->
    <div class="auth-container">
        <h1>مرحبًا بعودتك!</h1>
        <p>سجل الدخول إلى حسابك في BizFlow</p>

        <!-- عرض رسائل الحالة -->
        <?php if ($error_message): ?>
            <div class="message error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        <?php if ($success_message): ?>
            <div class="message success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <!-- نموذج تسجيل الدخول -->
        <form action="login.php" method="POST">
            <div class="form-group">
                <label for="email">البريد الإلكتروني</label>
                <input type="email" id="email" name="email" required>
            </div>
            
            <div class="form-group">
                <label for="password">كلمة المرور</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="button">تسجيل الدخول</button>
            </div>
        </form>
        
        <div class="auth-switch">
            ليس لديك حساب؟ <a href="register.php">أنشئ حسابًا جديدًا</a>
        </div>
    </div>

</body>
</html>

