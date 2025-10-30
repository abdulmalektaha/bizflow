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
$invoice_id = $_GET['id'] ?? null;

// [4. معالجة إرسال النموذج (POST Request) - التحديث]
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // جلب البيانات من النموذج
    $invoice_id_post = $_POST['invoice_id'] ?? null;
    $customer_id = $_POST['customer_id'] ?? null;
    $amount = trim($_POST['amount'] ?? '');
    $status = trim($_POST['status'] ?? '');
    $due_date = trim($_POST['due_date'] ?? '');

    // التحقق من أن البيانات الأساسية موجودة
    if ($invoice_id_post && $customer_id && $amount && $status && $due_date) {
        try {
            // [تحديث الأمان]
            // قم بالتحديث فقط إذا كانت الفاتورة تنتمي للمستخدم الحالي
            $sql = "UPDATE invoices 
                    SET customer_id = :customer_id, 
                        amount = :amount, 
                        status = :status, 
                        due_date = :due_date
                    WHERE invoice_id = :invoice_id AND user_id = :user_id";
            
            $stmt = $db_connection->prepare($sql);
            $stmt->execute([
                'customer_id' => $customer_id,
                'amount' => $amount,
                'status' => $status,
                'due_date' => $due_date,
                'invoice_id' => $invoice_id_post,
                'user_id' => $current_user_id
            ]);

            // التحقق مما إذا كان أي صف قد تأثر
            if ($stmt->rowCount() > 0) {
                $_SESSION['success_message'] = "تم تحديث بيانات الفاتورة بنجاح!";
            } else {
                $_SESSION['error_message'] = "لم يتم إجراء أي تغييرات أو أن الفاتورة غير موجودة.";
            }
            header("Location: index.php");
            exit();

        } catch (PDOException $e) {
            logError("edit_invoice.php (POST) - PDOException: " . $e->getMessage());
            $error_message = "حدث خطأ في قاعدة البيانات أثناء التحديث.";
            // البقاء في الصفحة لعرض رسالة الخطأ
        }
    } else {
        $error_message = "يرجى ملء جميع الحقول المطلوبة.";
    }
}

// [5. جلب بيانات الفاتورة للعرض (GET Request)]
// إذا لم يكن طلب POST، أو إذا فشل التحقق من POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if (!$invoice_id) {
        $_SESSION['error_message'] = "لم يتم تحديد معرف الفاتورة.";
        header("Location: index.php");
        exit();
    }

    try {
        // [تحديث الأمان]
        // قم بالجلب فقط إذا كانت الفاتورة تنتمي للمستخدم الحالي
        $sql = "SELECT * FROM invoices WHERE invoice_id = :id AND user_id = :user_id";
        $stmt = $db_connection->prepare($sql);
        $stmt->execute(['id' => $invoice_id, 'user_id' => $current_user_id]);
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

        // إذا لم يتم العثور على الفاتورة (أو لا تنتمي للمستخدم)، أعد التوجيه
        if (!$invoice) {
            $_SESSION['error_message'] = "خطأ: الفاتورة غير موجودة أو لا يمكنك الوصول إليها.";
            header("Location: index.php");
            exit();
        }
        
        // [تحديث الأمان] جلب العملاء الخاصين بهذا المستخدم فقط لملء القائمة المنسدلة
        $sql_customers = "SELECT customer_id, first_name, last_name FROM customers WHERE user_id = :user_id ORDER BY first_name";
        $stmt_customers = $db_connection->prepare($sql_customers);
        $stmt_customers->execute(['user_id' => $current_user_id]);
        $customers = $stmt_customers->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        logError("edit_invoice.php (GET) - PDOException: " . $e->getMessage());
        $_SESSION['error_message'] = "حدث خطأ أثناء جلب بيانات الفاتورة.";
        header("Location: index.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تعديل الفاتورة - BizFlow</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        
        <!-- شريط التنقل العلوي -->
        <div class="header-nav">
            <h1>لوحة تحكم <?php echo htmlspecialchars($current_company_name); ?> - تعديل الفاتورة</h1>
            <div>
                <a href="index.php" class="nav-link active">عرض الفواتير</a>
                <a href="customers.php" class="nav-link">عرض العملاء</a>
                <a href="logout.php" class="nav-link logout-btn">تسجيل الخروج</a>
            </div>
        </div>

        <!-- عرض رسائل الخطأ (إذا حدث خطأ أثناء POST) -->
        <?php if (!empty($error_message)): ?>
            <p class="message error-message"><?php echo htmlspecialchars($error_message); ?></p>
        <?php endif; ?>

        <!-- نموذج تعديل الفاتورة -->
        <form action="edit_invoice.php?id=<?php echo htmlspecialchars($invoice_id); ?>" method="post" class="data-form">
            <!-- إخفاء حقل ID الفاتورة لإرساله مع النموذج -->
            <input type="hidden" name="invoice_id" value="<?php echo htmlspecialchars($invoice['invoice_id']); ?>">
            
            <div class="form-group">
                <label for="customer_id">العميل (مطلوب):</label>
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
                <label for="amount">المبلغ (مطلوب):</label>
                <input type="number" step="0.01" id="amount" name="amount" value="<?php echo htmlspecialchars($invoice['amount'] ?? ''); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="status">الحالة (مطلوب):</label>
                <select id="status" name="status" required>
                    <option value="pending" <?php echo ($invoice['status'] == 'pending') ? 'selected' : ''; ?>>قيد الانتظار</option>
                    <option value="paid" <?php echo ($invoice['status'] == 'paid') ? 'selected' : ''; ?>>مدفوعة</option>
                    <option value="cancelled" <?php echo ($invoice['status'] == 'cancelled') ? 'selected' : ''; ?>>ملغاة</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="due_date">تاريخ الاستحقاق (مطلوب):</label>
                <input type="date" id="due_date" name="due_date" value="<?php echo htmlspecialchars($invoice['due_date'] ?? ''); ?>" required>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="button button-primary">حفظ التغييرات</button>
                <a href="index.php" class="button button-secondary">إلغاء</a>
            </div>
        </form>

    </div>
</body>
</html>

