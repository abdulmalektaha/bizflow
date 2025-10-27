<?php
// جلب الإعدادات والاتصال بقاعدة البيانات
require_once 'config.php';

// إيقاف عرض الأخطاء للعلن (يجب أن يكون في config.php بالفعل، لكن للتأكيد)
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', '/var/www/html/php_errors.log'); // سجل الأخطاء الفادحة

$customers = []; // مصفوفة افتراضية
$error_message = null; // لتخزين أي رسالة خطأ

try {
    // التحقق من وجود اتصال بقاعدة البيانات
    if ($db_connection) {
        // استعلام لجلب جميع العملاء مرتبين حسب ID
        $sql = "SELECT customer_id, first_name, last_name, email, telegram_chat_id 
                FROM customers 
                ORDER BY customer_id ASC";
        $stmt = $db_connection->query($sql);
        
        // جلب كل النتائج كمصفوفة
        $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $error_message = "خطأ فادح: لم يتم تأسيس الاتصال بقاعدة البيانات.";
        error_log("customers.php - Database connection not established.");
    }
} catch (PDOException $e) {
    // تسجيل الخطأ وعرض رسالة عامة للمستخدم
    $error_message = "حدث خطأ أثناء جلب بيانات العملاء. يرجى المحاولة لاحقًا.";
    error_log("customers.php - PDOException fetching customers: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>لوحة تحكم BizFlow - العملاء</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* يمكن إضافة تنسيقات إضافية خاصة بهذه الصفحة هنا إذا لزم الأمر */
        body { font-family: 'Tahoma', sans-serif; background-color: #f4f7f6; margin: 0; padding: 0; }
        .container { max-width: 1000px; margin: 20px auto; padding: 20px; background: #fff; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        h1 { color: #333; text-align: center; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; border: 1px solid #ddd; text-align: right; }
        th { background-color: #f2f2f2; }
        .error-message { color: red; text-align: center; margin-top: 15px; }
        .nav-link { display: inline-block; margin-bottom: 15px; padding: 8px 15px; background-color: #007bff; color: white; text-decoration: none; border-radius: 4px; }
        .nav-link:hover { background-color: #0056b3; }
        td a { color: #007bff; text-decoration: none; } /* تنسيق رابط الإيميل */
        td a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <h1>لوحة تحكم BizFlow - قائمة العملاء</h1>

        <!-- رابط للعودة إلى صفحة الفواتير -->
        <a href="index.php" class="nav-link">عرض الفواتير</a>
        <hr>

        <?php if ($error_message): ?>
            <p class="error-message"><?php echo htmlspecialchars($error_message); ?></p>
        <?php endif; ?>

        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>الاسم الأول</th>
                    <th>الاسم الأخير</th>
                    <th>البريد الإلكتروني</th>
                    <th>معرف تيليجرام</th>
                    <!-- <th>إجراءات</th> يمكن إضافة أعمدة للتعديل/الحذف لاحقاً -->
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($customers)): ?>
                    <?php foreach ($customers as $customer): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($customer['customer_id']); ?></td>
                            <td><?php echo htmlspecialchars($customer['first_name']); ?></td>
                            <td><?php echo htmlspecialchars($customer['last_name']); ?></td>
                            <td>
                                <?php if (!empty($customer['email'])): ?>
                                    <a href="mailto:<?php echo htmlspecialchars($customer['email']); ?>"><?php echo htmlspecialchars($customer['email']); ?></a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($customer['telegram_chat_id']) ?: '-'; ?></td>
                            <!-- <td><a href="#">تعديل</a> | <a href="#">حذف</a></td> -->
                        </tr>
                    <?php endforeach; ?>
                <?php elseif (!$error_message): // لا تعرض هذه الرسالة إذا كان هناك خطأ بالفعل ?>
                    <tr>
                        <td colspan="5" style="text-align: center;">لا يوجد عملاء لعرضهم حاليًا.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
