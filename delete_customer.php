<?php
// جلب الإعدادات والاتصال بقاعدة البيانات
require_once 'config.php';

$customer_id = null;
$error_redirect = "customers.php?error=unknown"; // رسالة خطأ افتراضية

// 1. التحقق من وجود ID في الرابط
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: customers.php");
    exit();
}
$customer_id = intval($_GET['id']);

try {
    if (!$db_connection) {
        throw new Exception("خطأ فادح: لم يتم تأسيس الاتصال بقاعدة البيانات.");
    }

    // 2. محاولة حذف العميل
    // ملاحظة: لقد قمنا بربط جدول invoices بـ customers (FOREIGN KEY)
    // هذا يعني أن PostgreSQL لن يسمح بحذف عميل إذا كان لديه فواتير (وهذا جيد!)
    // سنلتقط هذا الخطأ المحدد ونرسل رسالة واضحة.
    
    $sql = "DELETE FROM customers WHERE customer_id = :id";
    $stmt = $db_connection->prepare($sql);
    $stmt->execute(['id' => $customer_id]);

    // 3. التحقق مما إذا كان الحذف قد تم بالفعل
    if ($stmt->rowCount() > 0) {
        // نجح الحذف
        session_start();
        $_SESSION['success_message'] = "تم حذف العميل (ID: $customer_id) بنجاح.";
        session_write_close();
        header("Location: customers.php");
        exit();
    } else {
        // لم يتم العثور على العميل لحذفه
        header("Location: customers.php?error=notfound");
        exit();
    }

} catch (PDOException $e) {
    // 4. التقاط الأخطاء، خاصة خطأ المفتاح الخارجي (Foreign Key)
    
    $error_code = $e->getCode();
    
    // SQLSTATE[23503] هو الرمز القياسي لـ Foreign Key Violation
    if ($error_code == "23503") {
        // لا يمكن الحذف، العميل مرتبط بفواتير
        $error_redirect = "customers.php?error=has_invoices";
    } else {
        // خطأ آخر في قاعدة البيانات
        error_log("delete_customer.php - PDOException: " . $e->getMessage());
        $error_redirect = "customers.php?error=db_error";
    }
    
    session_start();
    $_SESSION['error_message'] = "فشل حذف العميل. (Code: $error_code)"; // يمكننا تحسين الرسالة هنا
    session_write_close();
    header("Location: " . $error_redirect);
    exit();

} catch (Exception $e) {
    error_log("delete_customer.php - General Exception: " . $e->getMessage());
    header("Location: " . $error_redirect);
    exit();
}
?>
