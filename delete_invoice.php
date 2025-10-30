<?php
// [1. بدء الجلسة والاتصال]
require_once 'config.php'; // سيقوم ببدء الجلسة session_start()

// [2. حارس الأمان (Authentication Guard)]
// التحقق مما إذا كان المستخدم مسجلاً دخوله
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// [3. جلب بيانات المستخدم الحالي من الجلسة]
$current_user_id = $_SESSION['user_id'];
$invoice_id = $_GET['id'] ?? null;

// [4. التحقق من وجود ID الفاتورة]
if (empty($invoice_id)) {
    $_SESSION['error_message'] = "خطأ: لم يتم تحديد معرف الفاتورة.";
    header("Location: index.php");
    exit();
}

// [5. محاولة الحذف]
try {
    // [تحديث الأمان]
    // قم بالحذف فقط إذا كانت الفاتورة تنتمي للمستخدم الحالي
    $sql = "DELETE FROM invoices 
            WHERE invoice_id = :invoice_id AND user_id = :user_id";
    
    $stmt = $db_connection->prepare($sql);
    $stmt->execute([
        'invoice_id' => $invoice_id,
        'user_id' => $current_user_id
    ]);

    // التحقق مما إذا كان أي صف قد تأثر
    if ($stmt->rowCount() > 0) {
        // تم الحذف بنجاح
        $_SESSION['success_message'] = "تم حذف الفاتورة بنجاح!";
    } else {
        // لم يتم الحذف (إما أن الفاتورة غير موجودة أو لا تنتمي للمستخدم)
        $_SESSION['error_message'] = "خطأ: لا يمكن العثور على الفاتورة أو لا تملك الصلاحية لحذفها.";
    }

} catch (PDOException $e) {
    // معالجة أي أخطاء أخرى في قاعدة البيانات
    logError("delete_invoice.php - PDOException: " . $e->getMessage());
    $_SESSION['error_message'] = "حدث خطأ في قاعدة البيانات أثناء محاولة الحذف.";
}

// [6. إعادة التوجيه]
// العودة دائمًا إلى صفحة قائمة الفواتير
header("Location: index.php");
exit();

?>

