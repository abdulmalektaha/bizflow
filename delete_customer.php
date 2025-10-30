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
$customer_id = $_GET['id'] ?? null;

// [4. التحقق من وجود ID العميل]
if (empty($customer_id)) {
    $_SESSION['error_message'] = "خطأ: لم يتم تحديد معرف العميل.";
    header("Location: customers.php");
    exit();
}

// [5. محاولة الحذف]
try {
    // [تحديث الأمان]
    // قم بالحذف فقط إذا كان customer_id و user_id يتطابقان
    // هذا يمنع المستخدم من حذف عملاء لا يملكهم
    $sql = "DELETE FROM customers 
            WHERE customer_id = :customer_id AND user_id = :user_id";
    
    $stmt = $db_connection->prepare($sql);
    $stmt->execute([
        'customer_id' => $customer_id,
        'user_id' => $current_user_id
    ]);

    // التحقق مما إذا كان أي صف قد تأثر
    if ($stmt->rowCount() > 0) {
        // تم الحذف بنجاح
        $_SESSION['success_message'] = "تم حذف العميل بنجاح!";
    } else {
        // لم يتم الحذف (إما أن العميل غير موجود أو لا ينتمي للمستخدم)
        $_SESSION['error_message'] = "خطأ: لا يمكن العثور على العميل أو لا تملك الصلاحية لحذفه.";
    }

} catch (PDOException $e) {
    // [معالجة خطأ المفتاح الخارجي (الأهم)]
    // إذا كان العميل مرتبطًا بفواتير، ستمنع قاعدة البيانات الحذف
    if ($e->getCode() == '23503') { 
        $_SESSION['error_message'] = "خطأ: لا يمكن حذف العميل لأنه مرتبط بفواتير موجودة.";
    } else {
        // خطأ آخر في قاعدة البيانات
        logError("delete_customer.php - PDOException: " . $e->getMessage());
        $_SESSION['error_message'] = "حدث خطأ في قاعدة البيانات أثناء محاولة الحذف.";
    }
}

// [6. إعادة التوجيه]
// العودة دائمًا إلى صفحة قائمة العملاء
header("Location: customers.php");
exit();

?>
