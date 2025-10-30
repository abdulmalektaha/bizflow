<?php
// [1. بدء الجلسة]
// يجب أن يكون هذا أول شيء في الملف
session_start();

// [2. جلب الإعدادات والاتصال]
require_once 'config.php';

$invoices = [];
$error_message = null;
$success_message = null;

// [3. [جديد] التحقق من رسائل الحالة القادمة من الجلسة]
// نقرأها ونحذفها فورًا حتى لا تظهر مرة أخرى
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']); // حذف الرسالة بعد عرضها
}
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']); // حذف الرسالة بعد عرضها
}


try {
    if (!$db_connection) {
         throw new Exception("خطأ فادح: لم يتم تأسيس الاتصال بقاعدة البيانات.");
    }
    
    // [4. معالجة الإجراءات السريعة (تحديث الحالة)]
    // (هذا هو الكود القديم لتحديد كمدفوعة)
    if (isset($_GET['action']) && $_GET['action'] == 'mark_paid' && isset($_GET['id'])) {
        $invoice_id_to_update = intval($_GET['id']);
        
        $update_sql = "UPDATE invoices SET status = 'paid' WHERE invoice_id = :id";
        $stmt = $db_connection->prepare($update_sql);
        $stmt->execute(['id' => $invoice_id_to_update]);
        
        // [تعديل] استخدام رسالة نجاح بدلاً من إعادة التحميل الفورية
        $success_message = "تم تحديث حالة الفاتورة (ID: $invoice_id_to_update) إلى 'مدفوعة' بنجاح!";
        // ملاحظة: من الأفضل إعادة التوجيه لتجنب إعادة إرسال النموذج،
        // ولكن للتبسيط سنكتفي بعرض الرسالة
        // header("Location: index.php?status=marked_paid"); // خيار أفضل
    }

    // [5. جلب بيانات الفواتير لعرضها]
    $sql = "SELECT i.invoice_id, i.amount, i.status, i.due_date, c.first_name, c.last_name
            FROM invoices AS i
            JOIN customers AS c ON i.customer_id = c.customer_id
            ORDER BY i.creation_date DESC";
    $stmt = $db_connection->query($sql);
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error_message = "حدث خطأ أثناء الاتصال أو جلب البيانات من قاعدة البيانات.";
    error_log("index.php - PDOException: " . $e->getMessage());
} catch (Exception $e) {
    $error_message = $e->getMessage();
    error_log("index.php - General Exception: " . $e->getMessage());
}
session_write_close(); // إغلاق الجلسة بعد الانتهاء
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>لوحة تحكم BizFlow - الفواتير</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body { font-family: 'Tahoma', sans-serif; background-color: #f4f7f6; margin: 0; padding: 0; }
        .container { max-width: 1000px; margin: 20px auto; padding: 20px; background: #fff; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        h1 { color: #333; text-align: center; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; border: 1px solid #ddd; text-align: right; }
        th { background-color: #f2f2f2; }
        
        /* رسائل الحالة */
        .error-message { color: red; text-align: center; margin-top: 15px; border: 1px solid red; padding: 10px; border-radius: 4px; background-color: #ffebeb; }
        .success-message { color: green; text-align: center; margin-top: 15px; border: 1px solid green; padding: 10px; border-radius: 4px; background-color: #e6ffec; }
        
        /* روابط التنقل */
        .nav-link { display: inline-block; margin-bottom: 15px; padding: 8px 15px; background-color: #007bff; color: white; text-decoration: none; border-radius: 4px; }
        .nav-link:hover { background-color: #0056b3; }
        
        /* تنسيق حالة الفاتورة */
        .status-paid { color: #28a745; font-weight: bold; } /* أخضر */
        .status-pending { color: #fd7e14; font-weight: bold; } /* برتقالي */
        
        /* [جديد] تنسيق روابط الإجراءات */
        .action-link { 
            color: #007bff; /* أزرق */
            text-decoration: none; 
            margin-left: 10px;
        }
        .action-link:hover { text-decoration: underline; }
        
        .action-link-edit {
            color: #ffc107; /* أصفر */
            margin-left: 10px;
            text-decoration: none;
        }
        .action-link-edit:hover { text-decoration: underline; }
        
        .action-link-delete {
            color: #dc3545; /* أحمر */
            text-decoration: none;
        }
        .action-link-delete:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <h1>لوحة تحكم BizFlow - الفواتير</h1>
        <!-- رابط للانتقال إلى صفحة العملاء -->
        <a href="customers.php" class="nav-link">عرض العملاء</a>
        <hr>

        <!-- [جديد] عرض رسائل النجاح أو الخطأ -->
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
                    <th>إجراءات</th> <!-- [جديد] تعديل اسم العمود -->
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($invoices)): ?>
                    <?php foreach ($invoices as $invoice): ?>
                        <tr>
                            <td>#<?php echo htmlspecialchars($invoice['invoice_id']); ?></td>
                            <td><?php echo htmlspecialchars($invoice['first_name'] . ' ' . $invoice['last_name']); ?></td>
                            <td><?php echo number_format($invoice['amount'], 2); ?></td>
                            <td><?php echo htmlspecialchars($invoice['due_date']); ?></td>
                            <td>
                                <!-- تنسيق الحالة بناءً على القيمة -->
                                <span class="status-<?php echo htmlspecialchars($invoice['status']); ?>">
                                    <?php echo $invoice['status'] == 'paid' ? 'مدفوعة' : 'قيد الانتظار'; ?>
                                </span>
                            </td>
                            <!-- [جديد] إضافة روابط التعديل والحذف -->
                            <td>
                                <?php if ($invoice['status'] != 'paid'): ?>
                                    <a href="index.php?action=mark_paid&id=<?php echo $invoice['invoice_id']; ?>" class="action-link" onclick="return confirm('هل أنت متأكد من تحديد هذه الفاتورة كمدفوعة؟');">
                                        تحديد كمدفوعة
                                    </a>
                                <?php endif; ?>
                                <a href="edit_invoice.php?id=<?php echo $invoice['invoice_id']; ?>" class="action-link-edit">تعديل</a>
                                <a href="delete_invoice.php?id=<?php echo $invoice['invoice_id']; ?>" class="action-link-delete" onclick="return confirm('هل أنت متأكد من حذف هذه الفاتورة؟ \nهذا الإجراء لا يمكن التراجع عنه.');">حذف</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php elseif (!$error_message): ?>
                    <tr>
                        <td colspan="6" style="text-align: center;">لا توجد فواتير لعرضها حاليًا.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>

