<?php
// [1. بدء الجلسة والاتصال]
// يجب أن يكون session_start() في config.php هو السطر الأول
require_once 'config.php'; 

// [2. حارس الأمان (Authentication Guard)]
// التحقق مما إذا كان المستخدم مسجلاً دخوله
if (!isset($_SESSION['user_id'])) {
    // إذا لم يكن مسجلاً دخوله، أعد توجيهه إلى صفحة تسجيل الدخول
    header("Location: login.php");
    exit();
}

// [3. جلب بيانات المستخدم الحالي من الجلسة]
$current_user_id = $_SESSION['user_id'];
$current_company_name = $_SESSION['company_name'] ?? 'BizFlow'; // اسم افتراضي

// [4. جلب رسائل الحالة (Success/Error Messages) من الجلسة]
$success_message = $_SESSION['success_message'] ?? null;
$error_message = $_SESSION['error_message'] ?? null;
// مسح الرسائل بعد عرضها لمنع ظهورها مرة أخرى عند تحديث الصفحة
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);

// [5. جلب الفواتير (المصفاة) الخاصة بهذا المستخدم فقط]
$invoices = [];
try {
    // [تحديث الأمان]
    // اختر فقط الفواتير التي يملكها المستخدم الحالي (user_id)
    $sql = "SELECT i.invoice_id, i.amount, i.status, i.due_date, c.first_name, c.last_name
            FROM invoices AS i
            JOIN customers AS c ON i.customer_id = c.customer_id
            WHERE i.user_id = :user_id 
            ORDER BY i.creation_date DESC";
    $stmt = $db_connection->prepare($sql);
    $stmt->execute(['user_id' => $current_user_id]);
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // تسجيل الخطأ بدلاً من عرضه للمستخدم
    logError("index.php - PDOException: " . $e->getMessage());
    $error_message = "حدث خطأ أثناء جلب الفواتير. يرجى المحاولة لاحقًا.";
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <!-- [تحديث] استخدام اسم الشركة الديناميكي في العنوان -->
    <title>لوحة التحكم - <?php echo htmlspecialchars($current_company_name); ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        
        <!-- شريط التنقل العلوي -->
        <div class="header-nav">
            <!-- [تحديث] استخدام اسم الشركة الديناميكي -->
            <h1>لوحة تحكم <?php echo htmlspecialchars($current_company_name); ?> - الفواتير</h1>
            <div>
                <!-- [تحديث] الروابط أصبحت جزءًا من شريط التنقل -->
                <a href="index.php" class="nav-link active">عرض الفواتير</a>
                <a href="customers.php" class="nav-link">عرض العملاء</a>
                <a href="account.php" class="nav-link">حسابي (للربط)</a>
                <a href="logout.php" class="nav-link logout-btn">تسجيل الخروج</a>
            </div>
        </div>

        <!-- عرض رسائل النجاح أو الخطأ -->
        <?php if ($success_message): ?>
            <p class="message success-message"><?php echo htmlspecialchars($success_message); ?></p>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <p class="message error-message"><?php echo htmlspecialchars($error_message); ?></p>
        <?php endif; ?>

        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>العميل</th>
                    <th>المبلغ</th>
                    <th>تاريخ الاستحقاق</th>
                    <th>الحالة</th>
                    <th class="actions-column">إجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($invoices)): ?>
                    <tr>
                        <!-- [تحديث] التأكد من أن الرسالة تشمل كل الأعمدة -->
                        <td colspan="6" style="text-align: center;">لا توجد فواتير لعرضها حاليًا. (جرب إضافة فاتورة جديدة عبر البوت!)</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($invoices as $invoice): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($invoice['invoice_id']); ?></td>
                            <td><?php echo htmlspecialchars($invoice['first_name'] . ' ' . $invoice['last_name']); ?></td>
                            <td><?php echo htmlspecialchars(number_format($invoice['amount'], 2)); ?></td>
                            <td><?php echo htmlspecialchars($invoice['due_date']); ?></td>
                            <td>
                                <span class="status-<?php echo htmlspecialchars($invoice['status']); ?>">
                                    <?php 
                                    if ($invoice['status'] == 'paid') echo 'مدفوعة';
                                    elseif ($invoice['status'] == 'pending') echo 'قيد الانتظار';
                                    elseif ($invoice['status'] == 'cancelled') echo 'ملغاة';
                                    else echo htmlspecialchars($invoice['status']);
                                    ?>
                                </span>
                            </td>
                            <!-- [تحديث] إضافة روابط التعديل والحذف -->
                            <td class="actions-links">
                                <?php if ($invoice['status'] == 'pending'): ?>
                                    <!-- هذا الرابط من الكود الأصلي، يبدو أنه يقوم بالتحديث مباشرة -->
                                    <a href="edit_invoice.php?action=mark_paid&id=<?php echo $invoice['invoice_id']; ?>" class="action-link" onclick="return confirm('هل أنت متأكد من تحديث حالة الفاتورة إلى مدفوعة؟');">دُفعت</a>
                                <?php endif; ?>
                                <a href="edit_invoice.php?id=<?php echo $invoice['invoice_id']; ?>" class="action-link edit">تعديل</a>
                                <a href="delete_invoice.php?id=<?php echo $invoice['invoice_id']; ?>" class="action-link delete" onclick="return confirm('هل أنت متأكد من حذف هذه الفاتورة؟ لا يمكن التراجع عن هذا الإجراء.');">حذف</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
