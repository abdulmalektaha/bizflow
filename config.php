<?php
// -- [أسطر إظهار الأخطاء] --
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// -- [نهاية الأسطر المضافة] --


// -- 1. إعدادات المفاتيح (API Keys) --

// !! هام جدًا: ضع التوكن الحقيقي لبوتك هنا بين علامتي التنصيص !!
define('TELEGRAM_BOT_TOKEN', '8464809764:AAE7Rv4Iu2_Rq0eCcxN9QwqF_3iundvsq90'); 

// (إذا كنت تستخدمها، ضعها هنا أيضًا)
define('OPENAI_API_KEY', 'sk-proj-jMlhCS7q4TfTS4c_SUMDPR5cwveEj6Ebc6qC1_3bkADKUnrvKHzUgIu-Zl25Z8TA9C
 VRnVJG89T3BlbkFJxE2XgZvjXQMDnGiSIHdfWE-AzXx4o_9PoyN3WTW7_xcBUGPoJygQ_
 5vwFS3dy7r9l7pqboUw0A'); 
define('MANAGEMENT_CHAT_ID', '7751190692');


// -- 2. إعدادات الاتصال بقاعدة البيانات (PostgreSQL) --

// هذه هي الإعدادات التي سنستخدمها.
// سنقوم بإنشاء قاعدة البيانات وكلمة المرور هذه في الخطوات التالية.
$db_host = '127.0.0.1'; // يعني "هذا السيرفر"
$db_name = 'bizflow_db';
$db_user = 'postgres'; // هذا هو المستخدم الافتراضي لـ PostgreSQL
$db_pass = 'postgres_password'; // !! سنقوم بتعيين كلمة المرور هذه لاحقًا !!


// -- 3. كود الاتصال بقاعدة البيانات --

$db_connection = null; // تعريف المتغير أولاً

try {
    // هذا هو سطر الاتصال الصحيح لـ PostgreSQL
    $conn_string = "pgsql:host=$db_host;dbname=$db_name";

    // محاولة الاتصال
    $db_connection = new PDO($conn_string, $db_user, $db_pass);

    // هذا السطر مهم جدًا لإظهار الأخطاء بشكل واضح
    $db_connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {
    // إذا فشل الاتصال، سيعرض الخطأ بالتفصيل ويموت
    // هذا هو ما نريده أن يحدث الآن لنعرف الخطوة التالية
    die("!! خطأ في الاتصال بقاعدة البيانات (config.php): " . $e->getMessage()); 
}

?>
