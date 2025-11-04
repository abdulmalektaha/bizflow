<?php
// --- 1. استدعاء القالب العلوي (Header) ---
// سيتولى هذا الملف بدء الجلسة، التحقق من تسجيل الدخول، وعرض شريط التنقل
require 'header.php';

// --- 2. منطق هذه الصفحة فقط (تعديل العميل) ---
$customer_id = $_GET['id'] ?? null;
$customer = null;
$error_message = null;
$current_user_id = $_SESSION['user_id'];

// التحقق من أن ID العميل موجود
if (!$customer_id || !filter_var($customer_id, FILTER_VALIDATE_INT)) {
    // إعادة توجيه المستخدم إذا لم يتم توفير ID صالح
    header("Location: customers.php?error=" . urlencode("معرف العميل غير صالح."));
    exit;
}

try {
    // --- معالجة النموذج عند الإرسال (POST) ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // جلب البيانات من النموذج
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $email = trim($_POST['email']);
        
        // التحقق من أن الاسم الأول موجود
        if (empty($first_name)) {
            $error_message = "الاسم الأول مطلوب.";
        } else {
            // [حارس التفويض] التأكد من أن المستخدم يقوم بتحديث عميل يملكه
            $sql = "UPDATE customers SET 
                        first_name = :first_name, 
                        last_name = :last_name, 
                        email = :email 
                    WHERE customer_id = :customer_id AND user_id = :user_id";
            
            $stmt = $db_connection->prepare($sql);
            $stmt->execute([
                ':first_name' => $first_name,
                ':last_name' => $last_name,
                ':email' => $email ? $email : null, // السماح بقيمة NULL إذا كان الحقل فارغًا
                ':customer_id' => $customer_id,
                ':user_id' => $current_user_id
            ]);
            
            // تخزين رسالة النجاح في الجلسة وإعادة التوجيه
            $_SESSION['success_message'] = "تم تحديث بيانات العميل بنجاح!";
            header("Location: customers.php");
            exit;
        }
    }

    // --- جلب بيانات العميل لعرضها في النموذج (GET) ---
    // [حارس التفويض] التأكد من أن المستخدم يجلب عميل يملكه
    $sql_get = "SELECT * FROM customers WHERE customer_id = :customer_id AND user_id = :user_id";
    $stmt_get = $db_connection->prepare($sql_get);
    $stmt_get->execute([':customer_id' => $customer_id, ':user_id' => $current_user_id]);
    $customer = $stmt_get->fetch(PDO::FETCH_ASSOC);

    // إذا لم يتم العثور على العميل (أو لا يملكه المستخدم)
    if (!$customer) {
        header("Location: customers.php?error=" . urlencode("لم يتم العثور على العميل أو ليس لديك صلاحية للوصول إليه."));
        exit;
    }

} catch (PDOException $e) {
    logError("Database Error (edit_customer.php): " . $e->getMessage());
    $error_message = "حدث خطأ أثناء معالجة الطلب.";
    // إذا كان الخطأ بسبب البريد الإلكتروني المكرر
    if ($e->getCode() == '23505') { // 23505 هو رمز خطأ "unique violation" في PostgreSQL
        $error_message = "خطأ: البريد الإلكتروني '$email' مستخدم بالفعل من قبل عميل آخر.";
    }
}
?>

<!-- 3. عرض محتوى الصفحة -->
<div class="page-header">
    <h1>تعديل العميل</h1>
</div>

<!-- عرض رسائل الخطأ (إن وجدت) -->
<?php if ($error_message): ?>
    <div class="message error"><?php echo htmlspecialchars($error_message); ?></div>
<?php endif; ?>

<!-- نموذج تعديل العميل -->
<div class="form-container">
    <form action="edit_customer.php?id=<?php echo htmlspecialchars($customer_id); ?>" method="POST">
        <div class="form-group">
            <label for="first_name">الاسم الأول (مطلوب)</label>
            <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($customer['first_name'] ?? ''); ?>" required>
        </div>
        
        <div class="form-group">
            <label for="last_name">الاسم الأخير</label>
            <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($customer['last_name'] ?? ''); ?>">
        </div>
        
        <div class="form-group">
            <label for="email">البريد الإلكتروني</label>
            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($customer['email'] ?? ''); ?>">
        </div>
        
        <div class="form-actions">
            <button type="submit" class="button">حفظ التغييرات</button>
            <a href="customers.php" class="button button-secondary">إلغاء</a>
        </div>
    </form>
</div>

<?php
// --- 4. استدعاء القالب السفلي (Footer) ---
require 'footer.php';
?>
