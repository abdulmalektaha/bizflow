<?php
// --- 1. استدعاء القالب العلوي (Header) ---
// سيتولى هذا الملف بدء الجلسة، التحقق من تسجيل الدخول، وعرض شريط التنقل
require 'header.php';

// --- 2. منطق هذه الصفحة فقط (جلب العملاء) ---

// جلب رسائل الحالة (Success/Error) من صفحة التعديل/الحذف
$success_message = $_SESSION['success_message'] ?? null;
$error_message = $_SESSION['error_message'] ?? null;
// مسح الرسائل بعد عرضها
unset($_SESSION['success_message'], $_SESSION['error_message']);

// جلب العملاء الخاصين بهذا المستخدم فقط
$current_user_id = $_SESSION['user_id'];
$customers = [];
try {
    $sql = "SELECT * FROM customers WHERE user_id = :user_id ORDER BY first_name, last_name";
    $stmt = $db_connection->prepare($sql);
    $stmt->execute([':user_id' => $current_user_id]);
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // في حالة حدوث خطأ في قاعدة البيانات، قم بتسجيله
    logError("Database Error (customers.php): " . $e->getMessage());
    $error_message = "حدث خطأ أثناء جلب العملاء.";
}
?>

<!-- 3. عرض محتوى الصفحة -->
<div class="page-header">
    <h1>العملاء</h1>
    <!-- يمكنك إضافة زر "إضافة عميل" هنا لاحقًا -->
</div>

<!-- عرض رسائل الحالة -->
<?php if ($success_message): ?>
    <div class="message success"><?php echo htmlspecialchars($success_message); ?></div>
<?php endif; ?>
<?php if ($error_message): ?>
    <div class="message error"><?php echo htmlspecialchars($error_message); ?></div>
<?php endif; ?>

<!-- جدول العملاء -->
<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>الاسم الأول</th>
                <th>الاسم الأخير</th>
                <th>البريد الإلكتروني</th>
                <th>معرف تيليجرام</th>
                <th>إجراءات</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($customers)): ?>
                <tr>
                    <td colspan="6">لا يوجد عملاء لعرضهم حاليًا. (جرب إضافة عميل جديد عبر البوت!)</td>
                </tr>
            <?php else: ?>
                <?php foreach ($customers as $customer): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($customer['customer_id']); ?></td>
                        <td><?php echo htmlspecialchars($customer['first_name']); ?></td>
                        <td><?php echo htmlspecialchars($customer['last_name']); ?></td>
                        <td><?php echo htmlspecialchars($customer['email'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($customer['telegram_chat_id'] ?? '-'); ?></td>
                        <td class="actions">
                            <a href="edit_customer.php?id=<?php echo $customer['customer_id']; ?>" class="action-link" title="تعديل">✏️</a>
                            <a href="delete_customer.php?id=<?php echo $customer['customer_id']; ?>" class="action-link delete" title="حذف" onclick="return confirm('هل أنت متأكد من حذف هذا العميل؟');">❌</a>
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
