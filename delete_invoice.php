<?php
// [1. بدء الجلسة]
// يجب أن يكون هذا أول شيء في الملف
session_start();

// [2. جلب الإعدادات والاتصال]
require_once 'config.php';

$invoice_id = null;
$error_message = null;
$success_message = null;

try {
    // [3. التحقق من وجود ID في الرابط وأنه رقم صحيح]
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        throw new Exception("رقم الفاتورة غير صالح أو مفقود.");
    }
    $invoice_id = intval($_GET['id']);

    if (!$db_connection) {
        throw new Exception("خطأ فادح: لم يتم تأسيس الاتصال بقاعدة البيانات.");
    }

    // [4. تجهيز وتنفيذ أمر الحذف]
    $sql = "DELETE FROM invoices WHERE invoice_id = :id";
    $stmt = $db_connection->prepare($sql);
    $stmt->execute(['id' => $invoice_id]);

    // [5. التحقق مما إذا كان الحذف قد تم بالفعل]
    if ($stmt->rowCount() > 0) {
        // إذا نجح الحذف (تم العثور على الصف وحذفه)
        $_SESSION['success_message'] = "تم حذف الفاتورة (ID: $invoice_id) بنجاح!";
    } else {
        // إذا لم يتم العثور على الفاتورة (ربما تم حذفها بالفعل)
        throw new Exception("لم يتم العثور على الفاتورة (ID: $invoice_id) لحذفها.");
    }

} catch (PDOException $e) {
    // [6. معالجة أخطاء قاعدة البيانات]
    // (هنا لا نتوقع خطأ مفتاح خارجي، لأن الفاتورة لا تمنع حذف شيء آخر)
    $_SESSION['error_message'] = "حدث خطأ في قاعدة البيانات أثناء محاولة الحذف.";
    error_log("delete_invoice.php - PDOException: " . $e->getMessage());
} catch (Exception $e) {
    // [7. معالجة الأخطاء العامة]
    $_SESSION['error_message'] = $e->getMessage();
    error_log("delete_invoice.php - General Exception: " . $e->getMessage());
}

// [8. إعادة التوجيه إلى صفحة الفواتير الرئيسية]
// سيقوم index.php بقراءة رسالة النجاح أو الخطأ من الجلسة
header("Location: index.php");
exit();

?>
