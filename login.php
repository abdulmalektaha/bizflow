<?php
// [1. بدء الجلسة]
// يجب أن يكون هذا أول شيء في الملف
session_start();

// [2. جلب الإعدادات والاتصال]
require_once 'config.php';

$error_message = null;
$success_message = null;

// [3. التحقق مما إذا كان المستخدم مسجلاً دخوله بالفعل]
// إذا كان المستخدم مسجلاً دخوله، قم بإعادة توجيهه إلى لوحة التحكم
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// [4. عرض رسالة النجاح إذا كان قادمًا من صفحة التسجيل]
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']); // حذف الرسالة بعد عرضها
}

// [5. التعامل مع نموذج تسجيل الدخول (POST request)]
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    try {
        if (!$db_connection) {
            throw new Exception("خطأ فادح: لم يتم تأسيس الاتصال بقاعدة البيانات.");
        }

        // [6. التحقق من المدخلات]
        if (empty($email) || empty($password)) {
            $error_message = "البريد الإلكتروني وكلمة المرور مطلوبان.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "البريد الإلكتروني غير صالح.";
        } else {
            // [7. البحث عن المستخدم في قاعدة البيانات]
            $sql = "SELECT user_id, email, password_hash, company_name FROM users WHERE email = :email";
            $stmt = $db_connection->prepare($sql);
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // [8. التحقق من كلمة المرور]
            // password_verify() هي الدالة الآمنة لمقارنة كلمة المرور بالهاش
            if ($user && password_verify($password, $user['password_hash'])) {
                
                // [9. نجح تسجيل الدخول: بدء الجلسة]
                // إعادة إنشاء معرف الجلسة لمزيد من الأمان
                session_regenerate_id(true); 
                
                // تخزين بيانات المستخدم في الجلسة
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['company_name'] = $user['company_name'];
                
                // [10. إعادة التوجيه إلى لوحة التحكم الرئيسية]
                header("Location: index.php");
                exit();
                
            } else {
                // فشل تسجيل الدخول
                $error_message = "البريد الإلكتروني أو كلمة المرور غير صحيحة.";
            }
        }
    } catch (PDOException $e) {
        $error_message = "حدث خطأ أثناء الاتصال بقاعدة البيانات.";
        error_log("login.php - PDOException: " . $e->getMessage());
    } catch (Exception $e) {
        $error_message = $e->getMessage();
        error_log("login.php - General Exception: " . $e->getMessage());
    }
}

session_write_close();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تسجيل الدخول - BizFlow</title>
    <link rel="stylesheet" href="style.css">
    <!-- استخدام نفس تنسيق صفحة التسجيل -->
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
            background-color: #28a745; /* أخضر */
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 18px;
            font-weight: bold;
        }
        .submit-btn:hover {
            background-color: #218838;
        }
        .register-link {
            text-align: center;
            margin-top: 20px;
            display: block;
            color: #555;
        }
        .register-link a {
            color: #007bff;
            text-decoration: none;
        }
        .register-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>تسجيل الدخول</h1>

        <?php if ($success_message): ?>
            <p class="success-message"><?php echo htmlspecialchars($success_message); ?></p>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <p class="error-message"><?php echo htmlspecialchars($error_message); ?></p>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="email">البريد الإلكتروني</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="form-group">
                <label for="password">كلمة المرور</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="submit-btn">تسجيل الدخول</button>
        </form>
        
        <span class="register-link">
            ليس لديك حساب؟ <a href="register.php">أنشئ حسابًا جديدًا</a>
        </span>

    </div>
</body>
</html>
