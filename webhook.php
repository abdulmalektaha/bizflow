<?php
file_put_contents(__DIR__ . '/test_log.txt', date('Y-m-d H:i:s') . " - Telegram hit!\n", FILE_APPEND);

session_start();
require_once 'config.php'; // This file MUST define $db_connection

/**
 * Logs an error and notifies the user if chat_id is given
 */
function handleError($message, $chat_id = null) {
    file_put_contents(__DIR__ . '/error_log.txt', date('Y-m-d H:i:s') . " - $message\n", FILE_APPEND);
    if ($chat_id) {
        global $BOT_TOKEN;
        $text = "⚠️ حدث خطأ: " . htmlspecialchars($message) . "\nيرجى المحاولة لاحقًا.";
        $url = "https://api.telegram.org/bot" . $BOT_TOKEN . "/sendMessage";
        $payload = ['chat_id' => $chat_id, 'text' => $text, 'parse_mode' => 'HTML'];
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_exec($ch);
        curl_close($ch);
    }
}

/**
 * Sends a message to the Telegram API.
 */
function sendMessage($chat_id, $text, $keyboard = null) {
    global $BOT_TOKEN;
    $url = "https://api.telegram.org/bot" . $BOT_TOKEN . "/sendMessage";
    $payload = ['chat_id' => $chat_id, 'text' => $text, 'parse_mode' => 'HTML'];
    if ($keyboard) {
        $payload['reply_markup'] = json_encode(['inline_keyboard' => $keyboard]);
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        handleError("cURL Error (sendMessage): " . curl_error($ch), $chat_id);
    }
    curl_close($ch);
}

/**
 * Answers a callback query (from button press).
 */
function answerCallbackQuery($callback_query_id, $chat_id = null, $text = null) {
    global $BOT_TOKEN;
    $url = "https://api.telegram.org/bot" . $BOT_TOKEN . "/answerCallbackQuery";
    $payload = ['callback_query_id' => $callback_query_id];
    if ($text) $payload['text'] = $text;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        handleError("cURL Error (answerCallbackQuery): " . curl_error($ch), $chat_id);
    }
    curl_close($ch);
}

// --- Database Helper Functions ---
function getUserByChatId($chat_id) { global $db_connection;
    $stmt = $db_connection->prepare("SELECT * FROM users WHERE telegram_chat_id = ?");
    $stmt->execute([$chat_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getUserByLinkCode($link_code) { global $db_connection;
    $stmt = $db_connection->prepare("SELECT * FROM users WHERE telegram_link_code = ?");
    $stmt->execute([$link_code]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Similar functions for setConversationState, getPendingData, setPendingData, clearPendingData
// ... (يمكن نسخها كما هي)

// --- MAIN LOGIC ---
try {
    $input = file_get_contents('php://input');
    file_put_contents(__DIR__ . '/raw_input.txt', $input . PHP_EOL, FILE_APPEND);
    $update = json_decode($input, true);
    if (!$update) exit;

    $chat_id = $update['message']['chat']['id'] ?? $update['callback_query']['message']['chat']['id'] ?? null;
    $user_text = trim($update['message']['text'] ?? '');
    $callback_data = $update['callback_query']['data'] ?? null;
    $callback_query_id = $update['callback_query']['id'] ?? null;

    if (!$chat_id) exit;

    $user_row = getUserByChatId($chat_id);
    $user_id = $user_row['user_id'] ?? null;
    $user_state = $user_row['conversation_state'] ?? 'idle';

    // --- Callback Handling ---
    if ($callback_data) {
        answerCallbackQuery($callback_query_id, $chat_id);
        // باقي منطق الـ callback كما في كودك السابق...
        exit;
    }

    // --- Text Handling ---
    if (!$user_text) exit;

    // /link command
    if (strpos($user_text, '/link') === 0) {
        $parts = explode(' ', $user_text, 2);
        $link_code = $parts[1] ?? null;
        if (!$link_code) {
            sendMessage($chat_id, "❌ يرجى إدخال رمز الربط بعد الأمر، مثال:\n/link BZF-XYZ123");
            exit;
        }
        $target_user = getUserByLinkCode($link_code);
        if ($target_user) {
            try {
                $stmt = $db_connection->prepare("UPDATE users SET telegram_chat_id = ?, telegram_link_code = NULL WHERE user_id = ?");
                $stmt->execute([$chat_id, $target_user['user_id']]);
                sendMessage($chat_id, "✅ تم ربط حسابك بنجاح!");
            } catch (PDOException $e) {
                handleError("PDO Error during /link: ".$e->getMessage(), $chat_id);
            }
        } else {
            sendMessage($chat_id, "❌ رمز الربط غير صالح أو منتهي الصلاحية.");
        }
        exit;
    }

    // باقي منطق الرسائل مثل /start، إضافة عميل، إضافة فاتورة، /cancel
    // كل استدعاء PDO داخل try/catch ومرر $chat_id لـ handleError عند الخطأ
    // مثال:
    /*
    try {
        $stmt = $db_connection->prepare("INSERT INTO customers (...) VALUES (...)");
        $stmt->execute([...]);
    } catch (PDOException $e) {
        handleError("PDO Error adding customer: ".$e->getMessage(), $chat_id);
    }
    */

} catch (PDOException $e) {
    handleError("Webhook PDO Error: " . $e->getMessage(), $chat_id);

} catch (Exception $e) {
    handleError("Webhook General Error: " . $e->getMessage(), $chat_id);
}

http_response_code(200);
?>
