<?php
// [1. بدء الجلسة والاتصال]
require_once 'config.php'; // سيقوم ببدء الجلسة session_start()

// [2. حارس الأمان (Authentication Guard)]
// التحقق مما إذا كان المستخدم مسجلاً دخوله
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// [3. جلب بيانات المستخدم الحالي من الجلسة]
$current_user_id = $_SESSION['user_id'];
$current_company_name = $_SESSION['company_name'] ?? 'شركتي';
$customer_id = $_GET['id'] ?? null;

// [4. معالجة إرسال النموذج (POST Request) - التحديث]
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // جلب البيانات من النموذج
    $customer_id_post = $_POST['customer_id'] ?? null;
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telegram_chat_id = trim($_POST['telegram_chat_id'] ?? '');

    // التحقق من أن البيانات الأساسية موجودة
    if ($customer_id_post && $first_name && $last_name) {
        try {
            // [تحديث الأمان]
            // قم بالتحديث فقط إذا كان customer_id و user_id يتطابقان
            // هذا يمنع المستخدم من تعديل عملاء لا يملكهم
            $sql = "UPDATE customers 
                    SET first_name = :first_name, 
                        last_name = :last_name, 
                        email = :email, 
                        telegram_chat_id = :telegram_chat_id
                    WHERE customer_id = :customer_id AND user_id = :user_id";
            
            $stmt = $db_connection->prepare($sql);
            $stmt->execute([
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => $email ?: null, // السماح بقيمة فارغة
                'telegram_chat_id' => $telegram_chat_id ?: null, // السماح بقيمة فارغة
                'customer_id' => $customer_id_post,
                'user_id' => $current_user_id
            ]);

            // التحقق مما إذا كان أي صف قد تأثر
            if ($stmt->rowCount() > 0) {
                $_SESSION['success_message'] = "تم تحديث بيانات العميل بنجاح!";
            } else {
                $_SESSION['error_message'] = "لم يتم إجراء أي تغييرات أو أن العميل غير موجود.";
            }
            header("Location: customers.php");
            exit();

        } catch (PDOException $e) {
            logError("edit_customer.php (POST) - PDOException: " . $e->getMessage());
            $error_message = "حدث خطأ في قاعدة البيانات أثناء التحديث.";
            // البقاء في الصفحة لعرض رسالة الخطأ
        }
    } else {
        $error_message = "يرجى ملء جميع الحقول المطلوبة (الاسم الأول والأخير).";
    }
}

// [5. جلب بيانات العميل للعرض (GET Request)]
// إذا لم يكن طلب POST، أو إذا فشل التحقق من POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if (!$customer_id) {
        $_SESSION['error_message'] = "لم يتم تحديد معرف العميل.";
        header("Location: customers.php");
        exit();
    }

    try {
        // [تحديث الأمان]
        // قم بالجلب فقط إذا كان customer_id و user_id يتطابقان
        $sql = "SELECT * FROM customers WHERE customer_id = :id AND user_id = :user_id";
        $stmt = $db_connection->prepare($sql);
        $stmt->execute(['id' => $customer_id, 'user_id' => $current_user_id]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);

        // إذا لم يتم العثور على العميل (أو لا ينتمي للمستخدم)، أعد التوجيه
        if (!$customer) {
            $_SESSION['error_message'] = "خطأ: العميل غير موجود أو لا يمكنك الوصول إليه.";
            header("Location: customers.php");
            exit();
        }
    } catch (PDOException $e) {
        logError("edit_customer.php (GET) - PDOException: " . $e->getMessage());
        $_SESSION['error_message'] = "حدث خطأ أثناء جلب بيانات العميل.";
        header("Location: customers.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تعديل العميل - BizFlow</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        
        <!-- شريط التنقل العلوي -->
        <div class="header-nav">
            <h1>لوحة تحكم <?php echo htmlspecialchars($current_company_name); ?> - تعديل العميل</h1>
            <div>
                <a href="index.php" class="nav-link">عرض الفواتير</a>
                <a href="customers.php" class="nav-link active">عرض العملاء</a>
                <a href="logout.php" class="nav-link logout-btn">تسجيل الخروج</a>
            </div>
        </div>

        <!-- عرض رسائل الخطأ (إذا حدث خطأ أثناء POST) -->
        <?php if (!empty($error_message)): ?>
            <p class="message error-message"><?php echo htmlspecialchars($error_message); ?></p>
        <?php endif; ?>

        <!-- نموذج تعديل العميل -->
        <form action="edit_customer.php?id=<?php echo htmlspecialchars($customer_id); ?>" method="post" class="data-form">
            <!-- إخفاء حقل ID العميل لإرساله مع النموذج -->
            <input type="hidden" name="customer_id" value="<?php echo htmlspecialchars($customer['customer_id']); ?>">
            
            <div class="form-group">
                <label for="first_name">الاسم الأول (مطلوب):</label>
                <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($customer['first_name'] ?? ''); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="last_name">الاسم الأخير (مطلوب):</label>
                <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($customer['last_name'] ?? ''); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="email">البريد الإلكتروني:</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($customer['email'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="telegram_chat_id">معرف تيليجرام:</label>
                <input type="text" id="telegram_chat_id" name="telegram_chat_id" value="<?php echo htmlspecialchars($customer['telegram_chat_id'] ?? ''); ?>">
            </div>
            
            <div class="form-actions">
                <button type="submit" class="button button-primary">حفظ التغييرات</button>
                <a href="customers.php" class="button button-secondary">إلغاء</a>
            </div>
        </form>

    </div>
</body>
</html>

