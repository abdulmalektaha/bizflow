<?php
// --- 1. استدعاء القالب العلوي (Header) ---
// سيتولى هذا الملف بدء الجلسة، التحقق من تسجيل الدخول، وعرض شريط التنقل
require 'header.php';

// --- 2. منطق هذه الصفحة فقط (تعديل الفاتورة) ---
$invoice_id = $_GET['id'] ?? null;
$invoice = null;
$customers = [];
$error_message = null;
$current_user_id = $_SESSION['user_id'];

// التحقق من أن ID الفاتورة موجود
if (!$invoice_id || !filter_var($invoice_id, FILTER_VALIDATE_INT)) {
    header("Location: index.php?error=" . urlencode("معرف الفاتورة غير صالح."));
    exit;
}

try {
    // --- معالجة النموذج عند الإرسال (POST) ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // جلب البيانات من النموذج
        $customer_id = trim($_POST['customer_id']);
        $amount = trim($_POST['amount']);
        $due_date = trim($_POST['due_date']);
        $status = trim($_POST['status']);
        
        // التحقق من المدخلات
        if (empty($customer_id) || empty($amount) || empty($status)) {
            $error_message = "العميل والمبلغ والحالة هي حقول مطلوبة.";
        } else {
            // [حارس التفويض] التأكد من أن المستخدم يقوم بتحديث فاتورة يملكها
            $sql = "UPDATE invoices SET 
                        customer_id = :customer_id, 
                        amount = :amount, 
                        due_date = :due_date,
                        status = :status 
                    WHERE invoice_id = :invoice_id AND user_id = :user_id";
            
            $stmt = $db_connection->prepare($sql);
            $stmt->execute([
                ':customer_id' => $customer_id,
                ':amount' => $amount,
                ':due_date' => $due_date ? $due_date : null, // السماح بقيمة NULL
                ':status' => $status,
                ':invoice_id' => $invoice_id,
                ':user_id' => $current_user_id
            ]);
            
            // تخزين رسالة النجاح في الجلسة وإعادة التوجيه
            $_SESSION['success_message'] = "تم تحديث الفاتورة بنجاح!";
            header("Location: index.php");
            exit;
        }
    }

    // --- جلب بيانات الفاتورة لعرضها في النموذج (GET) ---
    // [حارس التفويض] التأكد من أن المستخدم يجلب فاتورة يملكها
    $sql_get = "SELECT * FROM invoices WHERE invoice_id = :invoice_id AND user_id = :user_id";
    $stmt_get = $db_connection->prepare($sql_get);
    $stmt_get->execute([':invoice_id' => $invoice_id, ':user_id' => $current_user_id]);
    $invoice = $stmt_get->fetch(PDO::FETCH_ASSOC);

    // إذا لم يتم العثور على الفاتورة (أو لا يملكها المستخدم)
    if (!$invoice) {
        header("Location: index.php?error=" . urlencode("لم يتم العثور على الفاتورة أو ليس لديك صلاحية للوصول إليها."));
        exit;
    }
    
    // جلب قائمة العملاء الخاصين بهذا المستخدم لملء القائمة المنسدلة
    $stmt_customers = $db_connection->prepare("SELECT customer_id, first_name, last_name FROM customers WHERE user_id = :user_id ORDER BY first_name");
    $stmt_customers->execute([':user_id' => $current_user_id]);
    $customers = $stmt_customers->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    logError("Database Error (edit_invoice.php): " . $e->getMessage());
    $error_message = "حدث خطأ أثناء معالجة الطلب.";
}
?>

<!-- 3. عرض محتوى الصفحة -->
<div class="page-header">
    <h1>تعديل الفاتورة (رقم #<?php echo htmlspecialchars($invoice_id); ?>)</h1>
</div>

<!-- عرض رسائل الخطأ (إن وجدت) -->
<?php if ($error_message): ?>
    <div class="message error"><?php echo htmlspecialchars($error_message); ?></div>
<?php endif; ?>

<!-- نموذج تعديل الفاتورة -->
<div class="form-container">
    <form action="edit_invoice.php?id=<?php echo htmlspecialchars($invoice_id); ?>" method="POST">
        
        <div class="form-group">
            <label for="customer_id">العميل (مطلوب)</label>
            <select id="customer_id" name="customer_id" required>
                <?php foreach ($customers as $customer): ?>
                    <option value="<?php echo htmlspecialchars($customer['customer_id']); ?>" 
                        <?php echo ($customer['customer_id'] == $invoice['customer_id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group">
            <label for="amount">المبلغ (مطلوب)</label>
            <input type="number" step="0.01" id="amount" name="amount" value="<?php echo htmlspecialchars($invoice['amount'] ?? ''); ?>" required>
        </div>
        
        <div class="form-group">
            <label for="due_date">تاريخ الاستحقاق</label>
            <input type="date" id="due_date" name="due_date" value="<?php echo htmlspecialchars($invoice['due_date'] ?? ''); ?>">
        </div>
        
        <div class="form-group">
            <label for="status">الحالة (مطلوب)</label>
            <select id="status" name="status" required>
                <option value="pending" <?php echo ($invoice['status'] == 'pending') ? 'selected' : ''; ?>>قيد الانتظار</option>
                <option value="paid" <?php echo ($invoice['status'] == 'paid') ? 'selected' : ''; ?>>مدفوعة</option>
                <option value="cancelled" <?php echo ($invoice['status'] == 'cancelled') ? 'selected' : ''; ?>>ملغاة</option>
            </select>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="button">حفظ التغييرات</button>
            <a href="index.php" class="button button-secondary">إلغاء</a>
        </div>
    </form>
</div>

<?php
// --- 4. استدعاء القالب السفلي (Footer) ---
require 'footer.php';
?>

