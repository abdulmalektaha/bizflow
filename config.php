<?php

// -- مفاتيح API والإعدادات الثابتة --
define('TELEGRAM_BOT_TOKEN', getenv('TELEGRAM_BOT_TOKEN') ?: '8464809764:AAE7Rv4Iu2_Rq0eCcxN9QwqF_3iundvsq90');
define('OPENAI_API_KEY', getenv('OPENAI_API_KEY') ?: 'sk-proj-jMlhCS7q4TfTS4c_SUMDPR5cwveEj6Ebc6qC1_3bkADKUnrvKHzUgIu-Zl25Z8TA9C');
define('MANAGEMENT_CHAT_ID', getenv('MANAGEMENT_CHAT_ID') ?: '7751190692');

// هذا المتغير سيحمل اتصالنا الوحيد بقاعدة البيانات
$db_connection = null;

try {
    // -- بيانات اعتماد قاعدة البيانات الديناميكية --
    if (getenv('DATABASE_URL')) {
        // --- بيئة Railway (PostgreSQL) ---
        $db_url = getenv('DATABASE_URL');
        $db_parts = parse_url($db_url);

        $db_user = $db_parts['user'];
        $db_pass = $db_parts['pass'];
        $db_host = $db_parts['host'];
        $db_port = $db_parts['port'];
        $db_name = ltrim($db_parts['path'], '/');

        $conn_string = "pgsql:host=$db_host;port=$db_port;dbname=$db_name";
        $db_connection = new PDO($conn_string, $db_user, $db_pass);

    } else {
        // --- بيئة XAMPP المحلية (MySQL) ---
        $db_host = '127.0.0.1';
        $db_name = 'bizflow_db';
        $db_user = 'root';
        $db_pass = '';
        $charset = 'utf8mb4';

        $dsn = "mysql:host=$db_host;dbname=$db_name;charset=$charset";
        $db_connection = new PDO($dsn, $db_user, $db_pass);
    }

    // تعيين سمة عالمية للتعامل مع الأخطاء
    $db_connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {
    die("فشل الاتصال بقاعدة البيانات: " . $e->getMessage());
}

?>
