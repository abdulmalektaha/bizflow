<?php
// [1. بدء الجلسة والاتصال]
// config.php سيقوم ببدء الجلسة session_start()
require_once 'config.php';

// [2. حارس الأمان (Authentication Guard)]
// التحقق مما إذا كان المستخدم مسجلاً دخوله
if (!isset($_SESSION['user_id'])) {
    // إذا لم يكن مسجلاً، قم بإعادة توجيهه إلى صفحة تسجيل الدخول
    header("Location: login.php");
    exit();
}

// [3. جلب بيانات المستخدم الحالي من الجلسة]
$current_user_id = $_SESSION['user_id'];
$current_company_name = $_SESSION['company_name'] ?? 'شركتي';

// [4. معالجة رسائل الحالة (النجاح أو الخطأ) القادمة من صفحات أخرى]
$success_message = $_SESSION['success_message'] ?? null;
$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['success_message'], $_SESSION['error_message']);

// [5. جلب العملاء (فقط الخاصة بالمستخدم الحالي)]
try {
    // [تحديث الأمان] إضافة AND user_id = :user_id
    $sql = "SELECT customer_id, first_name, last_name, email, telegram_chat_id
            FROM customers
            WHERE user_id = :user_id
            ORDER BY first_name ASC";
            
    $stmt = $db_connection->prepare($sql);
    $stmt->execute(['user_id' => $current_user_id]);
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error_message = "خطأ فادح في جلب العملاء: " . $e->getMessage();
    logError("customers.php - (fetch customers) PDOException: " . $e->getMessage());
    $customers = []; // عرض جدول فارغ في حالة الخطأ
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>قائمة العملاء - BizFlow</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        
        <!-- شريط التنقل العلوي -->
        <div class="header-nav">
            <h1>لوحة تحكم <?php echo htmlspecialchars($current_company_name); ?> - قائمة العملاء</h1>
            <div>
                <a href="index.php" class="nav-link">عرض الفواتير</a>
                <a href="customers.php" class="nav-link active">عرض العملاء</a>
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

        <!-- جدول العملاء -->
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>الاسم الأول</th>
                    <th>الاسم الأخير</th>
                    <th>البريد الإلكتر الإلكتروني</th>
                    <th>معرف تيليجرام</th>
                    <th>إجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($customers)): ?>
                    <?php foreach ($customers as $customer): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($customer['customer_id']); ?></td>
                            <td><?php echo htmlspecialchars($customer['first_name']); ?></td>
                            <td><?php echo htmlspecialchars($customer['last_name']); ?></td>
                            <td><?php echo htmlspecialchars($customer['email'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($customer['telegram_chat_id'] ?? '-'); ?></td>
                            <td class="actions">
                                <a href="edit_customer.php?id=<?php echo $customer['customer_id']; ?>" class="action-link edit-link">تعديل</a>
                                <a href="delete_customer.php?id=<?php echo $customer['customer_id']; ?>" class="action-link delete-link" onclick="return confirm('هل أنت متأكد من حذف هذا العميل؟ سيؤدي هذا إلى إلغاء ربط الفواتير الخاصة به (لن يتم حذف الفواتير).');">حذف</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align: center;">لا يوجد عملاء لعرضهم حاليًا. (جرب إضافة عميل جديد عبر البوت!)</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>

