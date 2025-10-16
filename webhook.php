<?php
require_once 'config.php'; // لجلب مفتاح البوت

// قراءة الرسالة القادمة من تلغرام
$update = file_get_contents('php://input');
$update_array = json_decode($update, true);

// حفظ الرسالة في ملف logs.txt لنتمكن من تحليلها
file_put_contents('logs.txt', $update . PHP_EOL, FILE_APPEND);

// إذا كانت هناك رسالة نصية
if (isset($update_array['message']['text'])) {
    $chat_id = $update_array['message']['chat']['id'];
    $text = $update_array['message']['text'];

    // رد بسيط ومؤقت للتأكيد
    $response_text = "لقد استقبلت رسالتك: " . $text;

    // إرسال الرد
    $api_url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/sendMessage?chat_id=" . $chat_id . "&text=" . urlencode($response_text);
    file_get_contents($api_url);
}
?>