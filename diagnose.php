<?php
// إعدادات لعرض كل الأخطاء على الشاشة
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>🔍 تقرير فحص نظام BizFlow</h1>";
echo "<hr>";

// 1. فحص وجود الملفات الأساسية
echo "<h3>1. فحص الملفات:</h3>";
$files = ['config.php', 'webhook.php'];
foreach ($files as $file) {
    if (file_exists($file)) {
        echo "✅ الملف <b>$file</b> موجود.<br>";
    } else {
        echo "❌ الملف <b>$file</b> غير موجود!<br>";
        die("توقف الفحص: ملفات أساسية مفقودة.");
    }
}

// 2. فحص الدالة المكررة (السبب المحتمل للمشكلة)
echo "<h3>2. فحص تكرار الدوال (سبب توقف البوت):</h3>";
$webhook_content = file_get_contents('webhook.php');
if (strpos($webhook_content, 'function logError') !== false || strpos($webhook_content, 'function logMessage') !== false) {
    echo "❌ <b style='color:red'>خطر:</b> تم العثور على تعريف دالة <code>logError</code> أو <code>logMessage</code> داخل <code>webhook.php</code>.<br>";
    echo "💡 <b>الحل:</b> يجب حذف هذه الدالة من <code>webhook.php</code> لأنها موجودة بالفعل في <code>config.php</code>.<br>";
} else {
    echo "✅ ملف <code>webhook.php</code> سليم (لا يحتوي على دوال مكررة).<br>";
}

// 3. فحص الاتصال بقاعدة البيانات
echo "<h3>3. فحص قاعدة البيانات:</h3>";
try {
    require_once 'config.php'; // محاولة استدعاء الإعدادات
    
    if (isset($db_connection)) {
        // محاولة إجراء استعلام بسيط
        $stmt = $db_connection->query("SELECT count(*) FROM users");
        echo "✅ <b>نجح الاتصال بقاعدة البيانات!</b><br>";
        echo "✅ تم العثور على الجدول <code>users</code>.<br>";
    } else {
        echo "❌ متغير الاتصال <code>\$db_connection</code> غير موجود في <code>config.php</code>.<br>";
    }
} catch (PDOException $e) {
    echo "❌ <b style='color:red'>فشل الاتصال بقاعدة البيانات:</b> " . $e->getMessage() . "<br>";
    echo "💡 <b>الحل:</b> تأكد من كلمة المرور في <code>config.php</code>.<br>";
} catch (Throwable $e) {
    echo "❌ <b>حدث خطأ فادح أثناء تحميل الإعدادات:</b> " . $e->getMessage() . "<br>";
}

// 4. فحص صلاحيات السجلات
echo "<h3>4. فحص الصلاحيات:</h3>";
$log_file = '/var/www/html/php_errors.log';
if (is_writable($log_file)) {
    echo "✅ ملف السجل <code>php_errors.log</code> قابل للكتابة.<br>";
} else {
    if (file_exists($log_file)) {
        echo "❌ ملف السجل موجود ولكنه <b>غير قابل للكتابة</b>.<br>";
    } else {
        echo "⚠️ ملف السجل غير موجود. سيحاول النظام إنشاءه.<br>";
        // محاولة الإنشاء
        @file_put_contents($log_file, "Test log entry\n", FILE_APPEND);
        if (file_exists($log_file)) {
            echo "✅ تم إنشاء ملف السجل بنجاح.<br>";
        } else {
            echo "❌ فشل إنشاء ملف السجل (مشكلة صلاحيات المجلد).<br>";
        }
    }
}

echo "<hr><p>انتهى الفحص.</p>";
?>
