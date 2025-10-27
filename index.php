<?php
// جلب الإعدادات والاتصال بقاعدة البيانات
require_once 'config.php';

// إيقاف عرض الأخطاء للعلن (يجب أن يكون في config.php بالفعل، لكن للتأكيد)
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', '/var/www/html/php_errors.log'); // سجل الأخطاء الفادحة

$invoices = []; // مصفوفة افتراضية
$error_message = null; // لتخزين أي رسالة خطأ
$success_message = null; // لتخزين رسالة النجاح

try {
    // التحقق من وجود اتصال بقاعدة البيانات
    if (!$db_connection) {
        throw new Exception("خطأ فادح: لم يتم تأسيس الاتصال بقاعدة البيانات.");
    }

    // التعامل مع تحديث حالة الفاتورة باستخدام PDO
    if (isset($_GET['action']) && $_GET['action'] == 'mark_paid' && isset($_GET['id'])) {
        $invoice_id_to_update = $_GET['id'];
        // التحقق أولاً من أن الفاتورة موجودة وغير مدفوعة
        $check_sql = "SELECT status FROM invoices WHERE invoice_id = :id";
        $check_stmt = $db_connection->prepare($check_sql);
        $check_stmt->execute(['id' => $invoice_id_to_update]);
        $current_status = $check_stmt->fetchColumn();

        if ($current_status === 'pending') {
            $update_sql = "UPDATE invoices SET status = 'paid' WHERE invoice_id = :id";
            $stmt = $db_connection->prepare($update_sql);
            $stmt->execute(['id' => $invoice_id_to_update]);
            // استخدام جلسة لتمرير رسالة النجاح لتجنب مشاكل إعادة الإرسال عند التحديث
            session_start();
            $_SESSION['success_message'] = "تم تحديث حالة الفاتورة #" . $invoice_id_to_update . " إلى مدفوعة بنجاح!";
            session_write_close(); // إغلاق الجلسة بعد الكتابة
            header("Location: index.php"); // إعادة التوجيه لتحديث الصفحة وعرض الرسالة
            exit();
        } elseif ($current_status === 'paid') {
             $error_message = "الفاتورة #" . $invoice_id_to_update . " مدفوعة بالفعل.";
        } else {
             $error_message = "الفاتورة #" . $invoice_id_to_update . " غير موجودة.";
        }
    }

    // التحقق من وجود رسالة نجاح في الجلسة وعرضها ثم حذفها
    session_start();
    if (isset($_SESSION['success_message'])) {
        $success_message = $_SESSION['success_message'];
        unset($_SESSION['success_message']); // حذف الرسالة بعد عرضها
    }
    session_write_close();

    // جلب بيانات الفواتير باستخدام PDO (بعد أي تحديث محتمل)
    $sql = "SELECT i.invoice_id, i.amount, i.status, i.due_date, c.first_name, c.last_name
            FROM invoices AS i
            JOIN customers AS c ON i.customer_id = c.customer_id
            ORDER BY i.creation_date DESC";
    $stmt = $db_connection->query($sql);
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error_message = "حدث خطأ أثناء التعامل مع قاعدة البيانات. يرجى المحاولة لاحقًا.";
    error_log("index.php - PDOException: " . $e->getMessage());
} catch (Exception $e) {
    $error_message = $e->getMessage(); // عرض الخطأ العام (مثل فشل الاتصال الأولي)
    error_log("index.php - General Exception: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>لوحة تحكم BizFlow - الفواتير</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* يمكن إضافة تنسيقات إضافية خاصة بهذه الصفحة هنا إذا لزم الأمر */
        body { font-family: 'Tahoma', sans-serif; background-color: #f4f7f6; margin: 0; padding: 0; }
        .container { max-width: 1000px; margin: 20px auto; padding: 20px; background: #fff; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        h1 { color: #333; text-align: center; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; border: 1px solid #ddd; text-align: right; }
        th { background-color: #f2f2f2; }
        .status-paid { color: green; font-weight: bold; }
        .status-pending { color: orange; font-weight: bold; }
        .action-link { color: #007bff; text-decoration: none; }
        .action-link:hover { text-decoration: underline; }
        .error-message { color: red; text-align: center; margin-top: 15px; border: 1px solid red; padding: 10px; border-radius: 4px; background-color: #ffebeb; }
        .success-message { color: green; text-align: center; margin-top: 15px; border: 1px solid green; padding: 10px; border-radius: 4px; background-color: #e6ffec; }
        .nav-link { display: inline-block; margin-bottom: 15px; padding: 8px 15px; background-color: #007bff; color: white; text-decoration: none; border-radius: 4px; }
        .nav-link:hover { background-color: #0056b3; }
    </style>
</head>
<body>
    <div class="container">
        <h1>لوحة تحكم BizFlow - الفواتير</h1>

        <!-- [جديد] رابط للانتقال إلى صفحة العملاء -->
        <a href="customers.php" class="nav-link" style="margin-right: 10px;">عرض العملاء</a>
        <!-- يمكنك إضافة أزرار أخرى هنا لاحقًا -->
        <hr>

        <?php if ($success_message): ?>
            <p class="success-message"><?php echo htmlspecialchars($success_message); ?></p>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <p class="error-message"><?php echo htmlspecialchars($error_message); ?></p>
        <?php endif; ?>

        <table>
            <thead>
                <tr>
                    <th>رقم الفاتورة</th>
                    <th>العميل</th>
                    <th>المبلغ</th>
                    <th>تاريخ الاستحقاق</th>
                    <th>الحالة</th>
                    <th>إجراء</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($invoices)): ?>
                    <?php foreach ($invoices as $invoice): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($invoice['invoice_id']); ?></td>
                            <td><?php echo htmlspecialchars(trim($invoice['first_name'] . ' ' . $invoice['last_name'])); ?></td>
                            <td><?php echo htmlspecialchars(number_format($invoice['amount'], 2)); ?> ريال</td>
                            <td><?php echo htmlspecialchars($invoice['due_date']); ?></td>
                            <td>
                                <span class="status-<?php echo htmlspecialchars($invoice['status']); ?>">
                                    <?php echo $invoice['status'] == 'paid' ? 'مدفوعة' : 'قيد الانتظار'; ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($invoice['status'] != 'paid'): ?>
                                    <a href="index.php?action=mark_paid&id=<?php echo $invoice['invoice_id']; ?>" class="action-link" onclick="return confirm('هل أنت متأكد من تحديث حالة الفاتورة #<?php echo $invoice['invoice_id']; ?> إلى مدفوعة؟');">
                                        تحديد كمدفوعة
                                    </a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php elseif (!$error_message): // لا تعرض هذه الرسالة إذا كان هناك خطأ بالفعل ?>
                    <tr>
                        <td colspan="6" style="text-align: center;">لا توجد فواتير لعرضها حاليًا.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
