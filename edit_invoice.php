<?php
// [1. بدء الجلسة]
// يجب أن يكون هذا أول شيء في الملف
session_start();

// [2. جلب الإعدادات والاتصال]
require_once 'config.php';

$invoice_id = null;
$invoice = null;
$customers = []; // [جديد] مصفوفة لتخزين قائمة العملاء
$error_message = null;
$success_message = null; // (سنستخدم رسالة الجلسة للنجاح)

// [3. التحقق من وجود ID في الرابط]
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    // إذا لم يكن هناك ID، أعد التوجيه إلى صفحة الفواتير
    header("Location: index.php");
    exit();
}
$invoice_id = intval($_GET['id']);

try {
    if (!$db_connection) {
        throw new Exception("خطأ فادح: لم يتم تأسيس الاتصال بقاعدة البيانات.");
    }

    // [4. التعامل مع نموذج التعديل (POST request)]
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // استقبال البيانات من النموذج
        $customer_id = intval($_POST['customer_id'] ?? 0);
        $amount = trim($_POST['amount'] ?? '');
        $due_date = trim($_POST['due_date'] ?? '');
        $status = trim($_POST['status'] ?? '');

        // التحقق من البيانات
        if (empty($customer_id) || empty($amount) || empty($due_date) || empty($status)) {
            $error_message = "جميع الحقول إلزامية.";
        } elseif (!is_numeric($amount) || $amount < 0) {
            $error_message = "المبلغ يجب أن يكون رقمًا صحيحًا.";
        } elseif (!validateDate($due_date)) {
            $error_message = "صيغة التاريخ غير صحيحة (يجب أن تكون YYYY-MM-DD).";
        } elseif ($status != 'pending' && $status != 'paid') {
            $error_message = "حالة الفاتورة غير صالحة.";
        } else {
            // كل شيء سليم، تنفيذ أمر التحديث
            $sql = "UPDATE invoices SET 
                        customer_id = :customer_id, 
                        amount = :amount, 
                        due_date = :due_date, 
                        status = :status 
                    WHERE invoice_id = :invoice_id";
            $stmt = $db_connection->prepare($sql);
            $stmt->execute([
                'customer_id' => $customer_id,
                'amount' => $amount,
                'due_date' => $due_date,
                'status' => $status,
                'invoice_id' => $invoice_id
            ]);

            // إرسال رسالة نجاح وإعادة التوجيه إلى صفحة الفواتير
            $_SESSION['success_message'] = "تم تحديث الفاتورة (ID: $invoice_id) بنجاح!";
            header("Location: index.php");
            exit();
        }
    }

    // [5. جلب بيانات الفاتورة الحالية لعرضها في النموذج (GET request)]
    
    // جلب الفاتورة المحددة
    $stmt_invoice = $db_connection->prepare("SELECT * FROM invoices WHERE invoice_id = :id");
    $stmt_invoice->execute(['id' => $invoice_id]);
    $invoice = $stmt_invoice->fetch(PDO::FETCH_ASSOC);

    // إذا كانت الفاتورة غير موجودة
    if (!$invoice) {
        $error_message = "الفاتورة بالرقم $invoice_id غير موجودة.";
        $invoice = null; // ضمان عدم عرض النموذج
    }
    
    // [جديد] جلب قائمة بجميع العملاء لملء القائمة المنسدلة
    $stmt_customers = $db_connection->query("SELECT customer_id, first_name, last_name FROM customers ORDER BY first_name");
    $customers = $stmt_customers->fetchAll(PDO::FETCH_ASSOC);


} catch (PDOException $e) {
    $error_message = "حدث خطأ أثناء التعامل مع قاعدة البيانات.";
    error_log("edit_invoice.php - PDOException: " . $e->getMessage());
} catch (Exception $e) {
    $error_message = $e->getMessage();
    error_log("edit_invoice.php - General Exception: " . $e->getMessage());
}

/**
 * دالة مساعدة للتحقق من صحة التاريخ
 */
function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

session_write_close();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تعديل الفاتورة - BizFlow</title>
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
        .form-group input,
        .form-group select { /* [جديد] تطبيق التنسيق على القائمة المنسدلة أيضًا */
            width: 95%; /* 100% مع الأخذ بعين الاعتبار padding */
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .form-group input[readonly] { 
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
        <a href="index.php" class="nav-link">العودة إلى قائمة الفواتير</a>
        <hr>
        <h1>تعديل الفاتورة رقم #<?php echo htmlspecialchars($invoice_id); ?></h1>

        <?php if ($error_message): ?>
            <p class="error-message"><?php echo htmlspecialchars($error_message); ?></p>
        <?php endif; ?>

        <?php if ($invoice): // لا تعرض النموذج إذا لم يتم العثور على الفاتورة ?>
        <form method="POST">
            
            <!-- [جديد] القائمة المنسدلة للعملاء -->
            <div class="form-group">
                <label for="customer_id">العميل</label>
                <select id="customer_id" name="customer_id" required>
                    <option value="">-- اختر العميل --</option>
                    <?php foreach ($customers as $customer): ?>
                        <option value="<?php echo $customer['customer_id']; ?>" 
                            <?php if ($customer['customer_id'] == $invoice['customer_id']) echo 'selected'; ?>
                        >
                            <?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?> (ID: <?php echo $customer['customer_id']; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="amount">المبلغ</label>
                <input type="number" step="0.01" id="amount" name="amount" value="<?php echo htmlspecialchars($invoice['amount']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="due_date">تاريخ الاستحقاق</label>
                <!-- استخدام type="date" لمتصفحات الكمبيوتر والموبايل -->
                <input type="date" id="due_date" name="due_date" value="<?php echo htmlspecialchars($invoice['due_date']); ?>" required>
            </div>

            <!-- [جديد] القائمة المنسدلة للحالة -->
            <div class="form-group">
                <label for="status">الحالة</label>
                <select id="status" name="status" required>
                    <option value="pending" <?php if ($invoice['status'] == 'pending') echo 'selected'; ?>>قيد الانتظار</option>
                    <option value="paid" <?php if ($invoice['status'] == 'paid') echo 'selected'; ?>>مدفوعة</option>
                </select>
            </div>
            
            <button type="submit" class="submit-btn">حفظ التغييرات</button>
        </form>
        <?php endif; ?>

    </div>
</body>
</html>

