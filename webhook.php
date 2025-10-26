<?php
// ===================================================
// [1. كود تصحيح الأخطاء وتسجيل الأخطاء]
// ===================================================
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// [هام] هذان السطران سيسجلان أي خطأ (حتى الأخطاء الصامتة)
ini_set('log_errors', 1);
ini_set('error_log', '/var/www/html/php_errors.log'); // هذا هو ملف السجل الخاص بنا

// ===================================================
// [2. جلب الإعدادات والاتصال بقاعدة البيانات]
// ===================================================
require_once 'config.php'; // يجلب $db_connection والتوكن

// ===================================================
// [3. قراءة الرسالة المستلمة من تلغرام]
// ===================================================
$update = file_get_contents('php://input');
file_put_contents('debug.txt', $update . PHP_EOL, FILE_APPEND); // سنحتفظ بهذا للتأكد أننا نستقبل

$data = json_decode($update, true);

if (isset($data['message']['text'])) {
    $chat_id = $data['message']['chat']['id'];
    $text = $data['message']['text'];
} else {
    // إذا لم تكن رسالة نصية (مثل صورة أو ملصق)، قم بالخروج
    exit();
}

// ===================================================
// [4. المنطق الرئيسي للبوت (الرد على الأوامر)]
// !! تم التعديل ليصبح أذكى باستخدام "mb_strpos" !!
// ===================================================

// البحث عن كلمة "فاتورة" وكلمة "جديدة"
if (mb_strpos($text, 'فاتورة') !== false && mb_strpos($text, 'جديدة') !== false) {
    
    // --- هذا هو الكود لإضافة فاتورة ---
    try {
        // !! افترض أنك ستضيف فاتورة لـ customer_id = 1 !!
        // !! [خطأ محتمل]: يجب أن تتأكد أولاً أن العميل رقم 1 موجود في جدول customers !!
        $customer_id = 1; 
        $amount = 150.00;
        $due_date = '2025-11-30';
        
        $sql = "INSERT INTO invoices (customer_id, amount, status, due_date) VALUES (:customer_id, :amount, 'pending', :due_date)";
        $stmt = $db_connection->prepare($sql);
        $stmt->execute([
            'customer_id' => $customer_id,
            'amount' => $amount,
            'due_date' => $due_date
        ]);
        
        sendMessage($chat_id, "تمت إضافة الفاتورة بنجاح!");
        
    } catch (PDOException $e) {
        sendMessage($chat_id, "حدث خطأ أثناء إضافة الفاتورة.");
        error_log("PDO Error adding invoice: " . $e->getMessage()); // تسجيل الخطأ يدويًا
    }

// البحث عن كلمة "عميل"
} elseif (mb_strpos($text, 'عميل') !== false) {
    
    // --- هذا هو الكود لإضافة عميل ---
    try {
        // !! افترض أنك ستضيف عميلًا بمعلومات ثابتة الآن للاختبار !!
        $first_name = "Abed";
        $last_name = "Taha";
        $user_chat_id = $chat_id; // استخدام الـ chat_id الخاص بالشخص الذي أرسل الرسالة
        
        $sql = "INSERT INTO customers (first_name, last_name, telegram_chat_id) VALUES (:first, :last, :chat_id)";
        $stmt = $db_connection->prepare($sql);
        $stmt->execute(['first' => $first_name, 'last' => $last_name, 'chat_id' => $user_chat_id]);
        
        sendMessage($chat_id, "تمت إضافة العميل (Abed Taha) بنجاح!");
        
    } catch (PDOException $e) {
        sendMessage($chat_id, "حدث خطأ أثناء إضافة العميل.");
        error_log("PDO Error adding customer: " . $e->getMessage()); // تسجيل الخطأ يدويًا
    }

} elseif (mb_strpos($text, '/start') !== false) {
    sendMessage($chat_id, "مرحباً بك! أنا جاهز لاستقبال أوامرك.");

} else {
    // إذا لم يفهم الأمر
    sendMessage($chat_id, "أمر غير مفهوم: " . $text);
}


// ===================================================
// [5. دالة إرسال الرسائل إلى تلغرام]
// ===================================================
function sendMessage($chat_id, $message) {
    // نستخدم التوكن الذي تم تعريفه في config.php
    $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/sendMessage";
    $data = [
        'chat_id' => $chat_id,
        'text' => $message
    ];
    
    $options = [
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query($data),
        ],
    ];
    $context  = stream_context_create($options);
    file_get_contents($url, false, $context);
}

?>

