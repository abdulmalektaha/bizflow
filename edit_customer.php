<?php
// جلب الإعدادات والاتصال بقاعدة البيانات
require_once 'config.php';

$customer_id = null;
$customer = null;
$error_message = null;
$success_message = null;

// 1. التحقق من وجود ID في الرابط
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    // إذا لم يكن هناك ID، أعد التوجيه إلى صفحة العملاء
    header("Location: customers.php");
    exit();
}
$customer_id = intval($_GET['id']);

try {
    if (!$db_connection) {
        throw new Exception("خطأ فادح: لم يتم تأسيس الاتصال بقاعدة البيانات.");
    }

    // 2. التعامل مع نموذج التعديل (عندما يضغط المستخدم "حفظ التغييرات")
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // استقبال البيانات من النموذج
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');

        // التحقق من البيانات (أساسي)
        if (empty($first_name) || empty($last_name)) {
            $error_message = "الاسم الأول والأخير حقول إلزامية.";
        } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "صيغة البريد الإلكتروني غير صحيحة.";
        } else {
            // تنفيذ أمر التحديث
            $sql = "UPDATE customers SET 
                        first_name = :first_name, 
                        last_name = :last_name, 
                        email = :email 
                    WHERE customer_id = :customer_id";
            $stmt = $db_connection->prepare($sql);
            $stmt->execute([
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => (empty($email) ? null : $email), // السماح بقيمة فارغة
                'customer_id' => $customer_id
            ]);

            // إرسال رسالة نجاح وإعادة التوجيه إلى صفحة العملاء
            session_start();
            $_SESSION['success_message'] = "تم تحديث بيانات العميل (ID: $customer_id) بنجاح!";
            session_write_close();
            header("Location: customers.php");
            exit();
        }
    }

    // 3. جلب بيانات العميل الحالية لعرضها في النموذج (GET request)
    $stmt = $db_connection->prepare("SELECT * FROM customers WHERE customer_id = :id");
    $stmt->execute(['id' => $customer_id]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    // إذا كان العميل غير موجود
    if (!$customer) {
        $error_message = "العميل بالرقم $customer_id غير موجود.";
        $customer = null; // ضمان عدم عرض النموذج
    }

} catch (PDOException $e) {
    $error_message = "حدث خطأ أثناء التعامل مع قاعدة البيانات.";
    error_log("edit_customer.php - PDOException: " . $e->getMessage());
} catch (Exception $e) {
    $error_message = $e->getMessage();
    error_log("edit_customer.php - General Exception: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تعديل العميل - BizFlow</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body { font-family: 'Tahoma', sans-serif; background-color: #f4f7f6; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 20px auto; padding: 20px; background: #fff; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        h1 { color: #333; text-align: center; }
        .error-message { color: red; text-align: center; margin-top: 15px; border: 1px solid red; padding: 10px; border-radius: 4px; background-color: #ffebeb; }
        .nav-link { display: inline-block; margin-bottom: 15px; padding: 8px 15px; background-color: #6c757d; color: white; text-decoration: none; border-radius: 4px; }
        .nav-link:hover { background-color: #5a6268; }
        
        /* تنسيق النموذج */
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-group input {
            width: 95%; /* 100% مع الأخذ بعين الاعتبار padding */
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .form-group input[readonly] { /* جعل ID غير قابل للتعديل */
            background-color: #eee;
            cursor: not-allowed;
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
            font-size: 16px;
        }
        .submit-btn:hover {
            background-color: #218838;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="customers.php" class="nav-link">العودة إلى قائمة العملاء</a>
        <hr>
        <h1>تعديل بيانات العميل</h1>

        <?php if ($error_message): ?>
            <p class="error-message"><?php echo htmlspecialchars($error_message); ?></p>
        <?php endif; ?>

        <?php if ($customer): // لا تعرض النموذج إذا لم يتم العثور على العميل ?>
        <form method="POST">
            <div class="form-group">
                <label for="customer_id">رقم العميل (ID)</label>
                <input type="text" id="customer_id" name="customer_id" value="<?php echo htmlspecialchars($customer['customer_id']); ?>" readonly>
            </div>
            
            <div class="form-group">
                <label for="first_name">الاسم الأول</label>
                <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($customer['first_name']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="last_name">الاسم الأخير</label>
                <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($customer['last_name']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="email">البريد الإلكتروني</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($customer['email']); ?>">
            </div>
            
             <div class="form-group">
                <label for="telegram_chat_id">معرف تيليجرام</label>
                <input type="text" id="telegram_chat_id" name="telegram_chat_id" value="<?php echo htmlspecialchars($customer['telegram_chat_id']); ?>" readonly>
            </div>
            
            <button type="submit" class="submit-btn">حفظ التغييرات</button>
        </form>
        <?php endif; ?>

    </div>
</body>
</html>
