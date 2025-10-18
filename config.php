<?php
// -- مفاتيح API والإعدادات الثابتة --
define('TELEGRAM_BOT_TOKEN', getenv('TELEGRAM_BOT_TOKEN') ?: 'YOUR_LOCAL_TELEGRAM_BOT_TOKEN');
define('OPENAI_API_KEY', getenv('OPENAI_API_KEY') ?: 'YOUR_LOCAL_OPENAI_API_KEY');
define('MANAGEMENT_CHAT_ID', getenv('MANAGEMENT_CHAT_ID') ?: 'YOUR_LOCAL_MANAGEMENT_CHAT_ID');

$db_connection = null;

try {
    // -- يتحقق إذا كان الكود يعمل على Render --
    if (getenv('DATABASE_URL')) {
        // --- بيئة Render (PostgreSQL) ---
        $db_url = getenv('DATABASE_URL');
        $db_parts = parse_url($db_url);

        $db_user = $db_parts['user'] ?? null;
        $db_pass = $db_parts['pass'] ?? null;
        $db_host = $db_parts['host'] ?? null;
        $db_port = $db_parts['port'] ?? 5432; // القيمة الافتراضية إذا لم يتم العثور عليها
        $db_name = ltrim($db_parts['path'] ?? '', '/');

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

    $db_connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {
    die("فشل الاتصال بقاعدة البيانات: " . $e->getMessage());
}
?>
