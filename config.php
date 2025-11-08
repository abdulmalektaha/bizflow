<?php
// [1. بدء الجلسة]
// !! هام جدًا: يجب أن يكون هذا هو السطر الأول في كل ملف للتعامل مع تسجيل الدخول !!
session_start();

// [2. إعدادات عرض الأخطاء (للأمان)]
// إيقاف عرض الأخطاء للمستخدمين
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// [3. إعدادات تسجيل الأخطاء (للتشخيص)]
// تفعيل تسجيل الأخطاء في ملف
ini_set('log_errors', 1);
// تحديد مسار ملف السجل (يجب أن يكون قابلاً للكتابة بواسطة www-data)
ini_set('error_log', '/var/www/html/php_errors.log'); 

// دالة مساعدة لتسجيل الأخطاء المخصصة
function logError($message) {
    // التأكد من أن الرسالة هي سلسلة نصية
    if (!is_string($message)) {
        $message = print_r($message, true);
    }
    // [توقيت كوالالمبور]
    $timestamp = date('Y-m-d H:i:s'); 
    @file_put_contents('/var/www/html/php_errors.log', "[$timestamp] " . $message . PHP_EOL, FILE_APPEND);
}

// [4. ضبط المنطقة الزمنية (هام)]
// ضبط المنطقة الزمنية للسيرفر لتكون متوافقة مع توقيتك
// (استخدم "Asia/Kuala_Lumpur" لتوقيت ماليزيا)
date_default_timezone_set('Asia/Kuala_Lumpur');


// [5. إعدادات المفاتيح (API Keys)]
// !! هام: تم وضع قيمك الحقيقية هنا !!
define('TELEGRAM_BOT_TOKEN', '8464809764:AAE7Rv4Iu2_Rq0eCcxN9QwqF_3iundvsq90'); 
define('OPENAI_API_KEY', 'sk-proj-jMlhCS7q4TfTS4c_SUMDPR5cwveEj6...'); // (القيمة التي أدخلتها سابقًا - اتركها كما هي)
define('MANAGEMENT_CHAT_ID', '7751190692'); // (رقمك الخاص)


// [6. إعدادات الاتصال بقاعدة البيانات (PostgreSQL)]
$db_host = '127.0.0.1'; // يعني "هذا السيرفر"
$db_name = 'bizflow_db';
$db_user = 'postgres'; // هذا هو المستخدم الافتراضي لـ PostgreSQL
$db_pass = 'qweasd123$'; // !! [تم التصحيح] هذا هو الخطأ الإملائي الذي أصلحناه !!


// [7. كود الاتصال بقاعدة البيانات]
$db_connection = null; // تعريف المتغير أولاً

try {
    // هذا هو سطر الاتصال الصحيح لـ PostgreSQL
    $conn_string = "pgsql:host=$db_host;dbname=$db_name";
    
    // محاولة الاتصال
    $db_connection = new PDO($conn_string, $db_user, $db_pass);
    
    // هذا السطر مهم جدًا لإظهار الأخطاء بشكل واضح (سيتم تسجيلها ولن تُعرض)
    $db_connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {
    // إذا فشل الاتصال، سجل الخطأ وموت
    logError("!! خطأ فادح في الاتصال بقاعدة البيانات (config.php): " . $e->getMessage());
    // عرض رسالة عامة للمستخدم (بما أن display_errors متوقف)
    die("حدث خطأ أثناء الاتصال بالخادم. يرجى المحاولة لاحقًا."); 
}

?>

