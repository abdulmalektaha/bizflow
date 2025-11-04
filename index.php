<?php
// --- 1. استدعاء القالب العلوي (Header) ---
// سيتولى هذا الملف بدء الجلسة، التحقق من تسجيل الدخول، وعرض شريط التنقل
require 'header.php';

// --- 2. منطق هذه الصفحة فقط (جلب الفواتير) ---

// جلب رسائل الحالة (Success/Error) من صفحة التعديل/الحذف
$success_message = $_SESSION['success_message'] ?? null;
$error_message = $_SESSION['error_message'] ?? null;
// مسح الرسائل بعد عرضها حتى لا تظهر مرة أخرى
unset($_SESSION['success_message'], $_SESSION['error_message']);

// جلب الفواتير الخاصة بهذا المستخدم فقط
$current_user_id = $_SESSION['user_id'];
$invoices = [];
try {
    // تم تعديل الاستعلام ليشمل اسم العميل الأول والأخير
    $sql = "SELECT i.*, c.first_name, c.last_name 
            FROM invoices i
            LEFT JOIN customers c ON i.customer_id = c.customer_id
            WHERE i.user_id = :user_id 
            ORDER BY i.creation_date DESC";
            
    $stmt = $db_connection->prepare($sql);
    $stmt->execute([':user_id' => $current_user_id]);
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // في حالة حدوث خطأ في قاعدة البيانات، قم بتسجيله
    logError("Database Error (index.php): " . $e->getMessage());
    $error_message = "حدث خطأ أثناء جلب الفواتير.";
}
?>

<!-- 3. عرض محتوى الصفحة -->
<div class="page-header">
    <h1>الفواتير</h1>
    <!-- يمكنك إضافة زر "إضافة فاتورة" هنا لاحقًا -->
</div>

<!-- عرض رسائل الحالة -->
<?php if ($success_message): ?>
    <div class="message success"><?php echo htmlspecialchars($success_message); ?></div>
<?php endif; ?>
<?php if ($error_message): ?>
    <div class="message error"><?php echo htmlspecialchars($error_message); ?></div>
<?php endif; ?>

<!-- جدول الفواتير -->
<div class="table-container">
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
            <?php if (empty($invoices)): ?>
                <tr>
                    <td colspan="6">لا توجد فواتير لعرضها حاليًا. (جرب إضافة فاتورة جديدة عبر البوت!)</td>
                </tr>
            <?php else: ?>
                <?php foreach ($invoices as $invoice): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($invoice['invoice_id']); ?></td>
                        <td><?php echo htmlspecialchars($invoice['first_name'] . ' ' . $invoice['last_name']); ?></td>
                        <td><?php echo htmlspecialchars(number_format($invoice['amount'], 2)); ?></td>
                        <td><?php echo htmlspecialchars($invoice['due_date']); ?></td>
                        <td>
                            <!-- تحسين عرض الحالة بناءً على القيمة -->
                            <span class="status-<?php echo htmlspecialchars(strtolower($invoice['status'])); ?>">
                                <?php 
                                if ($invoice['status'] == 'paid') echo 'مدفوعة';
                                elseif ($invoice['status'] == 'pending') echo 'قيد الانتظار';
                                elseif ($invoice['status'] == 'cancelled') echo 'ملغاة';
                                else echo htmlspecialchars($invoice['status']);
                                ?>
                            </span>
                        </td>
                        <td class="actions">
                            <?php if ($invoice['status'] == 'pending'): ?>
                                <a href="edit_invoice.php?action=mark_paid&id=<?php echo $invoice['invoice_id']; ?>" class="action-link" title="تحديد كمدفوعة">✅</a>
                            <?php endif; ?>
                            <a href="edit_invoice.php?id=<?php echo $invoice['invoice_id']; ?>" class="action-link" title="تعديل">✏️</a>
                            <a href="delete_invoice.php?id=<?php echo $invoice['invoice_id']; ?>" class="action-link delete" title="حذف" onclick="return confirm('هل أنت متأكد من حذف هذه الفاتورة؟');">❌</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php
// --- 4. استدعاء القالب السفلي (Footer) ---
require 'footer.php';
?>

