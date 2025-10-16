<?php

// -- مفاتيح API والإعدادات الثابتة --
// تبحث عن متغيرات البيئة، وإذا لم تجدها، تستخدم القيم المحلية
define('TELEGRAM_BOT_TOKEN', getenv('TELEGRAM_BOT_TOKEN') ?: '8464809764:AAE7Rv4Iu2_Rq0eCcxN9QwqF_3iundvsq90');
define('OPENAI_API_KEY', getenv('OPENAI_API_KEY') ?: 'sk-proj-jMlhCS7q4TfTS4c_SUMDPR5cwveEj6Ebc6qC1_3bkADKUnrvKHzUgIu-Zl25Z8TA9C_VRnVJG89T3BlbkFJxE2XgZvjXQMDnGiSIHdfWE-AzXx4o_9PoyN3WTW7_xcBUGPoJygQ_5vwFS3dy7r9l7pqboUw0A');
define('MANAGEMENT_CHAT_ID', getenv('MANAGEMENT_CHAT_ID') ?: '7751190692');

// -- بيانات اعتماد قاعدة البيانات الديناميكية --
if (getenv('DB_HOST')) {
    // بيئة Railway
    define('DB_SERVER', getenv('DB_HOST'));
    define('DB_PORT', getenv('DB_PORT'));
    define('DB_USERNAME', getenv('DB_USER'));
    define('DB_PASSWORD', getenv('DB_PASSWORD'));
    define('DB_NAME', getenv('DB_DATABASE'));
    
    // اتصال بـ PostgreSQL باستخدام PDO
    try {
        $conn_string = "pgsql:host=" . DB_SERVER . ";port=" . DB_PORT . ";dbname=" . DB_NAME;
        $db_connection = new PDO($conn_string, DB_USERNAME, DB_PASSWORD);
        $db_connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        die("فشل الاتصال بقاعدة بيانات Railway: " . $e->getMessage());
    }

} else {
    // بيئة XAMPP المحلية
    define('DB_SERVER', 'localhost');
    define('DB_USERNAME', 'root');
    define('DB_PASSWORD', '');
    define('DB_NAME', 'bizflow_db');
    
    // اتصال بـ MySQL باستخدام MySQLi
    $db_connection = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
    if ($db_connection->connect_error) {
        die("فشل الاتصال بقاعدة البيانات المحلية: " . $db_connection->connect_error);
    }
    $db_connection->set_charset("utf8");
}

// الكود جاهز الآن لاستخدامه مع Telegram و OpenAI و قاعدة البيانات

?>
