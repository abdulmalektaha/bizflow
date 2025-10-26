<?php

// -- [نهاية الأسطر المضافة] --

require_once 'config.php'; // يجلب $db_connection

// [جديد] التعامل مع تحديث حالة الفاتورة باستخدام PDO
if (isset($_GET['action']) && $_GET['action'] == 'mark_paid' && isset($_GET['id'])) {
    $invoice_id_to_update = $_GET['id'];
    $update_sql = "UPDATE invoices SET status = 'paid' WHERE invoice_id = :id";
    $stmt = $db_connection->prepare($update_sql);
    $stmt->execute(['id' => $invoice_id_to_update]);
    header("Location: index.php");
    exit();
}

// [جديد] جلب بيانات الفواتير باستخدام PDO
$invoices = []; // مصفوفة افتراضية
try {
    if ($db_connection) { // [هذا هو الكود الصحيح] تأكد من أن الاتصال موجود 
        $sql = "SELECT i.invoice_id, i.amount, i.status, i.due_date, c.first_name, c.last_name
                FROM invoices AS i
                JOIN customers AS c ON i.customer_id = c.customer_id
                ORDER BY i.creation_date DESC";
        $stmt = $db_connection->query($sql);
        $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    // هذا سيعرض أيضًا الخطأ من config.php إذا فشل الاتصال
    die("خطأ في جلب البيانات أو الاتصال: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>لوحة تحكم BizFlow</title>
    <link rel="stylesheet" href="style.css">
    <style>
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
    </style>
</head>
<body>
    <div class="container">
        <h1>لوحة تحكم BizFlow - الفواتير</h1>

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
                            <td><?php echo htmlspecialchars($invoice['first_name'] . ' ' . $invoice['last_name']); ?></td>
                            <td><?php echo htmlspecialchars($invoice['amount']); ?></td>
                            <td><?php echo htmlspecialchars($invoice['due_date']); ?></td>
                            <td>
                                <span class="status-<?php echo htmlspecialchars($invoice['status']); ?>">
                                    <?php echo $invoice['status'] == 'paid' ? 'مدفوعة' : 'قيد الانتظار'; ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($invoice['status'] != 'paid'): ?>
                                    <a href="index.php?action=mark_paid&id=<?php echo $invoice['invoice_id']; ?>" class="action-link" onclick="return confirm('هل أنت متأكد من تحديث حالة الفاتورة إلى مدفوعة؟');">
                                        تحديد كمدفوعة
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align: center;">لا توجد فواتير لعرضها حاليًا.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>

