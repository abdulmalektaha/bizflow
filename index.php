<?php
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
$sql = "SELECT i.invoice_id, i.amount, i.status, i.due_date, c.full_name AS customer_name
        FROM invoices AS i
        JOIN customers AS c ON i.customer_id = c.customer_id
        ORDER BY i.creation_date DESC";
$stmt = $db_connection->query($sql);
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>لوحة تحكم BizFlow</title>
    <link rel="stylesheet" href="style.css">
    <style>
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; border: 1px solid #ddd; text-align: right; }
        th { background-color: #f2f2f2; }
        .status-pending { color: #ff8c00; font-weight: bold; }
        .status-paid { color: #28a745; font-weight: bold; }
        .action-link { color: #007bff; text-decoration: none; }
    </style>
</head>
<body>
    <div class="container">
        <h1>لوحة تحكم BizFlow</h1>
        <div class="nav-links">
            <a href="add_customer.php">إضافة عميل جديد</a>
            <a href="add_invoice.php">إضافة فاتورة جديدة</a>
        </div>
        <h2>سجل الفواتير</h2>
        <table>
            <thead>
                <tr>
                    <th>العميل</th><th>المبلغ</th><th>تاريخ الاستحقاق</th><th>الحالة</th><th>إجراء</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($invoices) > 0): ?>
                    <?php foreach($invoices as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['customer_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['amount']); ?> ريال</td>
                            <td><?php echo htmlspecialchars($row['due_date']); ?></td>
                            <td>
                                <?php if ($row['status'] == 'pending'): ?>
                                    <span class="status-pending">مستحقة</span>
                                <?php elseif ($row['status'] == 'paid'): ?>
                                    <span class="status-paid">مدفوعة</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($row['status'] == 'pending'): ?>
                                    <a href="index.php?action=mark_paid&id=<?php echo $row['invoice_id']; ?>" class="action-link">تحديد كمدفوعة</a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="5" style="text-align: center;">لا توجد فواتير لعرضها.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
