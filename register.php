<?php
// [1. بدء الجلسة]
session_start();

// [2. جلب الإعدادات والاتصال]
require_once 'config.php';

$error_message = null;
$success_message = null;

// [3. التعامل مع نموذج التسجيل (POST request)]
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $company_name = trim($_POST['company_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    try {
        if (!$db_connection) {
            throw new Exception("خطأ فادح: لم يتم تأسيس الاتصال بقاعدة البيانات.");
        }

        // [4. التحقق من المدخلات]
        if (empty($company_name) || empty($email) || empty($password) || empty($password_confirm)) {
            $error_message = "جميع الحقول إلزامية.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "البريد الإلكتروني غير صالح.";
        } elseif (strlen($password) < 8) {
            $error_message = "كلمة المرور يجب أن تكون 8 أحرف على الأقل.";
        } elseif ($password !== $password_confirm) {
            $error_message = "كلمتا المرور غير متطابقتين.";
        } else {
            // [5. التحقق مما إذا كان البريد الإلكتروني مستخدمًا بالفعل]
            $checkSql = "SELECT user_id FROM users WHERE email = :email";
            $checkStmt = $db_connection->prepare($checkSql);
            $checkStmt->execute(['email' => $email]);
            
            if ($checkStmt->fetch()) {
                $error_message = "هذا البريد الإلكتروني مسجل بالفعل. يرجى تجربة تسجيل الدخول.";
            } else {
                // [6. تشفير كلمة المرور (الأمان أولاً)]
                $password_hash = password_hash($password, PASSWORD_DEFAULT);

                // [7. إضافة المستخدم الجديد إلى قاعدة البيانات]
                $insertSql = "INSERT INTO users (company_name, email, password_hash) VALUES (:company_name, :email, :password_hash)";
                $insertStmt = $db_connection->prepare($insertSql);
                $insertStmt->execute([
                    'company_name' => $company_name,
                    'email' => $email,
                    'password_hash' => $password_hash
                ]);

                // [8. إعادة التوجيه إلى صفحة تسجيل الدخول]
                // سنرسل رسالة نجاح عبر الجلسة لتراها صفحة تسجيل الدخول
                $_SESSION['success_message'] = "تم إنشاء حسابك بنجاح! يمكنك الآن تسجيل الدخول.";
                header("Location: login.php"); // [مهم] سننشئ هذا الملف لاحقًا
                exit();
            }
        }
    } catch (PDOException $e) {
        $error_message = "حدث خطأ أثناء الاتصال بقاعدة البيانات.";
        error_log("register.php - PDOException: " . $e->getMessage());
    } catch (Exception $e) {
        $error_message = $e->getMessage();
        error_log("register.php - General Exception: " . $e->getMessage());
    }
}

session_write_close();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>إنشاء حساب جديد - BizFlow</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body { font-family: 'Tahoma', sans-serif; background-color: #f4f7f6; margin: 0; padding: 0; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        .container { max-width: 450px; width: 100%; margin: 20px auto; padding: 30px; background: #fff; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; text-align: center; margin-bottom: 25px; }
        
        /* رسائل الحالة */
        .error-message { color: red; text-align: center; margin-bottom: 15px; border: 1px solid red; padding: 10px; border-radius: 4px; background-color: #ffebeb; }
        .success-message { color: green; text-align: center; margin-bottom: 15px; border: 1px solid green; padding: 10px; border-radius: 4px; background-color: #e6ffec; }
        
        /* تنسيق النموذج */
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
        }
        .form-group input {
            width: 95%; /* 100% مع الأخذ بعين الاعتبار padding */
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        .submit-btn {
            display: block;
            width: 100%;
            padding: 12px;
            background-color: #007bff; /* أزرق */
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 18px;
            font-weight: bold;
        }
        .submit-btn:hover {
            background-color: #0056b3;
        }
        .login-link {
            text-align: center;
            margin-top: 20px;
            display: block;
            color: #555;
        }
        .login-link a {
            color: #007bff;
            text-decoration: none;
        }
        .login-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>إنشاء حساب جديد</h1>
        <p style="text-align: center; color: #666;">ابدأ في أتمتة فواتيرك مع BizFlow</p>

        <?php if ($success_message): ?>
            <p class="success-message"><?php echo htmlspecialchars($success_message); ?></p>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <p class="error-message"><?php echo htmlspecialchars($error_message); ?></p>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="company_name">اسم الشركة</label>
                <input type="text" id="company_name" name="company_name" required>
            </div>
            <div class="form-group">
                <label for="email">البريد الإلكتروني</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="form-group">
                <label for="password">كلمة المرور</label>
                <input type="password" id="password" name="password" minlength="8" required>
            </div>
            <div class="form-group">
                <label for="password_confirm">تأكيد كلمة المرور</label>
                <input type="password" id="password_confirm" name="password_confirm" minlength="8" required>
            </div>
            <button type="submit" class="submit-btn">إنشاء الحساب</button>
        </form>
        
        <span class="login-link">
            لديك حساب بالفعل؟ <a href="login.php">سجل الدخول هنا</a>
        </span>

    </div>
</body>
</html>
