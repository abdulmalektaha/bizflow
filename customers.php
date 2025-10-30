<?php
// جلب الإعدادات والاتصال بقاعدة البيانات
require_once 'config.php';

$customers = [];
$error_message = null;
$success_message = null;

// [جديد] التحقق من رسائل الحالة القادمة من صفحات التعديل/الحذف
if (isset($_GET['status'])) {
    if ($_GET['status'] == 'updated') {
        $success_message = "تم تحديث بيانات العميل بنجاح!";
    }
    if ($_GET['status'] == 'deleted') {
        $success_message = "تم حذف العميل بنجاح!";
    }
}
if (isset($_GET['error'])) {
     if ($_GET['error'] == 'has_invoices') {
        $error_message = "خطأ: لا يمكن حذف العميل لأنه مرتبط بفواتير موجودة.";
    } else {
         $error_message = "حدث خطأ غير متوقع أثناء محاولة الحذف.";
    }
}


try {
    if ($db_connection) {
        $sql = "SELECT customer_id, first_name, last_name, email, telegram_chat_id 
                FROM customers 
                ORDER BY customer_id ASC";
        $stmt = $db_connection->query($sql);
        $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $error_message = "خطأ فادح: لم يتم تأسيس الاتصال بقاعدة البيانات.";
        error_log("customers.php - Database connection not established.");
    }
} catch (PDOException $e) {
    $error_message = "حدث خطأ أثناء جلب بيانات العملاء.";
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
        body { font-family: 'Tahoma', sans-serif; background-color: #f4f7f6; margin: 0; padding: 0; }
        .container { max-width: 1000px; margin: 20px auto; padding: 20px; background: #fff; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        h1 { color: #333; text-align: center; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; border: 1px solid #ddd; text-align: right; }
        th { background-color: #f2f2f2; }
        .error-message { color: red; text-align: center; margin-top: 15px; border: 1px solid red; padding: 10px; border-radius: 4px; background-color: #ffebeb; }
        .success-message { color: green; text-align: center; margin-top: 15px; border: 1px solid green; padding: 10px; border-radius: 4px; background-color: #e6ffec; }
        .nav-link { display: inline-block; margin-bottom: 15px; padding: 8px 15px; background-color: #007bff; color: white; text-decoration: none; border-radius: 4px; }
        .nav-link:hover { background-color: #0056b3; }
        td a { color: #007bff; text-decoration: none; }
        td a:hover { text-decoration: underline; }
        
        /* [جديد] تنسيق أزرار الإجراءات */
        .action-link-edit {
            color: #ffc107; /* أصفر */
            margin-left: 10px;
        }
        .action-link-delete {
            color: #dc3545; /* أحمر */
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>لوحة تحكم BizFlow - قائمة العملاء</h1>
        <a href="index.php" class="nav-link">عرض الفواتير</a>
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
                    <th>#</th>
                    <th>الاسم الأول</th>
                    <th>الاسم الأخير</th>
                    <th>البريد الإلكتروني</th>
                    <th>معرف تيليجرام</th>
                    <th>إجراءات</th> <!-- [جديد] عمود الإجراءات -->
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
                            <!-- [جديد] روابط التعديل والحذف -->
                            <td>
                                <a href="edit_customer.php?id=<?php echo $customer['customer_id']; ?>" class="action-link-edit">تعديل</a>
                                <a href="delete_customer.php?id=<?php echo $customer['customer_id']; ?>" class="action-link-delete" onclick="return confirm('هل أنت متأكد من حذف هذا العميل؟ \nتحذير: لا يمكن حذف العميل إذا كان مرتبطًا بأي فواتير.');">حذف</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php elseif (!$error_message): ?>
                    <tr>
                        <td colspan="6" style="text-align: center;">لا يوجد عملاء لعرضهم حاليًا.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
