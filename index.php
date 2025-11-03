<?php
// [1. بدء الجلسة والاتصال]
// يتطلب أن يكون config.php يبدأ بـ session_start()
require_once 'config.php';

// [2. حارس الأمان]
// التأكد من أن المستخدم مسجل دخوله
if (!isset($_SESSION['user_id'])) {
    // إذا لم يكن مسجل دخوله، أعد توجيهه إلى صفحة تسجيل الدخول
    header("Location: login.php?message=Please login first");
    exit();
}

// [3. جلب بيانات المستخدم من الجلسة]
$current_user_id = $_SESSION['user_id'];
$company_name = $_SESSION['company_name'] ?? 'BizFlow'; // اسم الشركة أو اسم افتراضي

// [4. معالجة تحديث حالة الفاتورة (mark_paid)]
if (isset($_GET['action']) && $_GET['action'] == 'mark_paid' && isset($_GET['id'])) {
    try {
        $invoice_id_to_update = $_GET['id'];
        
        // !! تأمين إضافي: التأكد من أن الفاتورة ملك للمستخدم الحالي قبل تحديثها !!
        $update_sql = "UPDATE invoices SET status = 'paid' WHERE invoice_id = :id AND user_id = :user_id";
        $stmt = $db_connection->prepare($update_sql);
        $stmt->execute([':id' => $invoice_id_to_update, ':user_id' => $current_user_id]);
        
        // إعادة توجيه مع رسالة نجاح
        header("Location: index.php?status=paid_success");
        exit();
    } catch (PDOException $e) {
        // في حالة حدوث خطأ
        header("Location: index.php?status=paid_error");
        exit();
    }
}

// [5. معالجة الحذف (إذا تم الإرسال من هذا النموذج)]
// (تم نقل المنطق الرئيسي إلى delete_invoice.php، ولكن يمكن تركه هنا كمرجع أو إزالته)

// [6. جلب بيانات الفواتير (للمستخدم الحالي فقط!)]
$invoices = [];
try {
    // !! تصفية البيانات: جلب الفواتير المرتبطة بـ user_id الحالي فقط !!
    $sql = "SELECT i.invoice_id, i.amount, i.status, i.due_date, c.first_name, c.last_name
            FROM invoices AS i
            JOIN customers AS c ON i.customer_id = c.customer_id
            WHERE i.user_id = :user_id
            ORDER BY i.creation_date DESC";
    $stmt = $db_connection->prepare($sql);
    $stmt->execute([':user_id' => $current_user_id]);
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // معالجة خطأ جلب البيانات (يمكن عرض رسالة خطأ)
    $error_message = "خطأ في جلب الفواتير: " . $e->getMessage();
}

// [7. معالجة رسائل الحالة (للتنبيهات)]
$message = '';
if (isset($_GET['status'])) {
    if ($_GET['status'] == 'paid_success') {
        $message = '<div class="message success">تم تحديث حالة الفاتورة إلى "مدفوعة" بنجاح!</div>';
    }
    if ($_GET['status'] == 'delete_success') {
        $message = '<div class="message success">تم حذف الفاتورة بنجاح!</div>';
    }
    if ($_GET['status'] == 'edit_success') {
        $message = '<div class="message success">تم تعديل الفاتورة بنجاح!</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- استخدام اسم الشركة من الجلسة -->
    <title>لوحة تحكم <?php echo htmlspecialchars($company_name); ?> - الفواتير</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* (يمكن نقل هذا إلى style.css لاحقًا) */
        .message { padding: 10px; margin-bottom: 15px; border-radius: 5px; text-align: center; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .nav-link { 
            display: inline-block;
            background-color: #007bff;
            color: white;
            padding: 8px 12px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            margin: 5px;
        }
        .nav-link:hover { background-color: #0056b3; }
        .nav-bar { margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="container">
        
        <!-- استخدام اسم الشركة من الجلسة -->
        <h1>لوحة تحكم <?php echo htmlspecialchars($company_name); ?> - الفواتير</h1>

        <!-- شريط التنقل -->
        <div class="nav-bar">
            <a href="index.php" class="nav-link" style="background-color: #6c757d;">عرض الفواتير</a>
            <a href="customers.php" class="nav-link">عرض العملاء</a>
            <a href="account.php" class="nav-link">حسابي</a>
            <a href="logout.php" class="nav-link" style="background-color: #dc3545;">تسجيل الخروج</a>
        </div>

        <!-- عرض رسائل النجاح/الخطأ -->
        <?php echo $message; ?>

        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>العميل</th>
                    <th>المبلغ</th>
                    <th>تاريخ الاستحقاق</th>
                    <th>الحالة</th>
                    <th>إجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php if (isset($error_message)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; color: red;"><?php echo htmlspecialchars($error_message); ?></td>
                    </tr>
                <?php elseif (empty($invoices)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center;">
                            لا توجد فواتير لعرضها حاليًا. (جرب إضافة فاتورة جديدة عبر البوت!)
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($invoices as $invoice): ?>
                        <tr>
                            <td>#<?php echo htmlspecialchars($invoice['invoice_id']); ?></td>
                            <td><?php echo htmlspecialchars($invoice['first_name'] . ' ' . $invoice['last_name']); ?></td>
                            <td><?php echo htmlspecialchars(number_format($invoice['amount'], 2)); ?></td>
                            <td><?php echo htmlspecialchars($invoice['due_date']); ?></td>
                            <td>
                                <span class="status-<?php echo htmlspecialchars($invoice['status']); ?>">
                                    <?php 
                                    if ($invoice['status'] == 'paid') {
                                        echo 'مدفوعة';
                                    } elseif ($invoice['status'] == 'pending') {
                                        echo 'قيد الانتظار';
                                    } elseif ($invoice['status'] == 'cancelled') {
                                        echo 'ملغاة';
                                    } else {
                                        echo htmlspecialchars($invoice['status']);
                                    }
                                    ?>
                                </span>
                            </td>
                            <td class="actions-links">
                                <!-- [تعديل] رابط لتحديد كمدفوعة (فقط إذا لم تكن مدفوعة) -->
                                <?php if ($invoice['status'] == 'pending'): ?>
                                    <a href="index.php?action=mark_paid&id=<?php echo $invoice['invoice_id']; ?>" 
                                       onclick="return confirm('هل أنت متأكد من تحديث حالة الفاتورة إلى مدفوعة؟');">تحديد كمدفوعة</a>
                                <?php endif; ?>
                                
                                <!-- [جديد] رابط التعديل -->
                                <a href="edit_invoice.php?id=<?php echo $invoice['invoice_id']; ?>" class="edit-link">تعديل</a>
                                
                                <!-- [جديد] رابط الحذف -->
                                <a href="delete_invoice.php?id=<?php echo $invoice['invoice_id']; ?>" class="delete-link" 
                                   onclick="return confirm('هل أنت متأكد من حذف هذه الفاتورة؟ لا يمكن التراجع عن هذا الإجراء.');">حذف</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
