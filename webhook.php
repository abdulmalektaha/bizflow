<?php
// [1. CONFIG & HELPERS]
// ========================================================

// !! هام: يجب أن يكون config.php هو أول ملف يتم استدعاؤه !!
// إنه يبدأ الجلسة session_start() ويعرّف $db_connection و logError()
require_once 'config.php'; 

// !! [تم الإصلاح] لا يوجد session_start() مكرر هنا !!

if (!defined('TELEGRAM_BOT_TOKEN')) {
    logError("CRITICAL: TELEGRAM_BOT_TOKEN is not defined in config.php");
    exit; // Stop execution if token is missing
}

/**
 * Sends a message to the Telegram API.
 * @param int $chat_id
 * @param string $text
 * @param array|null $keyboard Inline keyboard structure
 * @return void
 */
function sendMessage($chat_id, $text, $keyboard = null)
{
    // [!! الإصلاح !!] نستخدم الثابت مباشرة بدلاً من المتغير العام
    $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/sendMessage";

    $payload = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];

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
        logError("cURL Error (sendMessage): " . curl_error($ch));
    }
    curl_close($ch);
}

/**
 * Answers a callback query (from button press).
 * @param string $callback_query_id
 * @param string|null $text
 * @return void
 */
function answerCallbackQuery($callback_query_id, $text = null)
{
    // [!! الإصلاح !!] نستخدم الثابت مباشرة
    $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/answerCallbackQuery";
    $payload = ['callback_query_id' => $callback_query_id];
    if ($text) {
        $payload['text'] = $text;
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_exec($ch);
    if (curl_errno($ch)) {
        logError("cURL Error (answerCallbackQuery): " . curl_error($ch));
    }
    curl_close($ch);
}

// --- Database Helper Functions (Using BizFlow Schema) ---

function getUserByChatId($chat_id)
{
    global $db_connection;
    $stmt = $db_connection->prepare("SELECT * FROM users WHERE telegram_chat_id = ?");
    $stmt->execute([$chat_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getUserByLinkCode($link_code)
{
    global $db_connection;
    $stmt = $db_connection->prepare("SELECT * FROM users WHERE telegram_link_code = ?");
    $stmt->execute([$link_code]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function setConversationState($user_id, $state)
{
    global $db_connection;
    $stmt = $db_connection->prepare("UPDATE users SET conversation_state = ? WHERE user_id = ?");
    $stmt->execute([$state, $user_id]);
}

function getPendingData($user_id)
{
    global $db_connection;
    $stmt = $db_connection->prepare("SELECT data FROM pending_data WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $json_data = $stmt->fetchColumn();
    return $json_data ? json_decode($json_data, true) : [];
}

function setPendingData($user_id, $data)
{
    global $db_connection;
    $json_data = json_encode($data);
    // Use "UPSERT" logic (PostgreSQL syntax)
    $sql = "INSERT INTO pending_data (user_id, data, updated_at) VALUES (?, ?, NOW())
            ON CONFLICT (user_id) DO UPDATE SET data = EXCLUDED.data, updated_at = NOW()";
    $stmt = $db_connection->prepare($sql);
    $stmt->execute([$user_id, $json_data]);
}

function clearPendingData($user_id)
{
    global $db_connection;
    $stmt = $db_connection->prepare("DELETE FROM pending_data WHERE user_id = ?");
    $stmt->execute([$user_id]);
}

function validateDateYMD($date)
{
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

// --- MAIN LOGIC ---
try {
    // Get raw input
    $input = file_get_contents('php://input');
    $update = json_decode($input, true);

    if (!$update) {
        exit; // Not an update we can handle
    }

    // Determine if it's a message or a button click (callback)
    $chat_id = null;
    $user_text = null;
    $callback_data = null;
    $callback_query_id = null;

    if (isset($update['message'])) {
        $chat_id = $update['message']['chat']['id'] ?? null;
        $user_text = trim($update['message']['text'] ?? '');
    } elseif (isset($update['callback_query'])) {
        $callback_query_id = $update['callback_query']['id'];
        $chat_id = $update['callback_query']['message']['chat']['id'] ?? null;
        $callback_data = $update['callback_query']['data'] ?? null;
    }

    if (!$chat_id) {
        exit; // No chat ID, can't respond
    }

    // Find the user account linked to this chat
    $user_row = getUserByChatId($chat_id);
    $user_id = $user_row['user_id'] ?? null;
    $user_state = $user_row['conversation_state'] ?? 'idle';

    // --- 1. Handle Callback Queries (Button Clicks) ---
    if ($callback_data) {
        answerCallbackQuery($callback_query_id); // Acknowledge the click

        if (!$user_row) {
            sendMessage($chat_id, "⚠️ حسابك غير مربوط. الرجاء إرسال /link [CODE] من هاتفك أولاً.");
            exit;
        }

        // User selected a customer from the list
        if (strpos($callback_data, 'select_customer_') === 0) {
            $customer_id = (int) str_replace('select_customer_', '', $callback_data);
            
            $pending_data = ['invoice_customer_id' => $customer_id];
            setPendingData($user_id, $pending_data);
            setConversationState($user_id, 'awaiting_invoice_amount');
            
            // Find customer name for confirmation message
            $stmt = $db_connection->prepare("SELECT first_name, last_name FROM customers WHERE customer_id = ? AND user_id = ?");
            $stmt->execute([$customer_id, $user_id]);
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);
            $customer_name = $customer ? ($customer['first_name'] . ' ' . $customer['last_name']) : "العميل #$customer_id";

            sendMessage($chat_id, "💰 ممتاز (تم اختيار العميل: " . htmlspecialchars($customer_name) . ").\nالآن، من فضلك أدخل مبلغ الفاتورة (أرقام فقط):");
        
        } elseif ($callback_data === 'cancel_invoice') { // Handle cancel button
            updateUserState($db_connection, $user_id, 'idle');
            sendMessage($chat_id, "تم إلغاء إضافة الفاتورة.");
        }
        exit; // End callback processing
    }

    // --- 2. Handle Text Messages ---
    if (!$user_text) {
        exit; // No text to process
    }
    // --- Handle /link command (Highest priority after callbacks) ---
    if (strpos($user_text, '/link') === 0) {
        $parts = explode(' ', $user_text, 2);
        $link_code = $parts[1] ?? null;

        if (!$link_code) {
            sendMessage($chat_id, "❌ يرجى إدخال رمز الربط بعد الأمر، مثال:\n/link BZF-XYZ123");
            exit;
        }

        $target_user = getUserByLinkCode($link_code);

        if ($target_user) {
            // Found user by link code. Link this chat_id to them.
            $stmt = $db_connection->prepare("UPDATE users SET telegram_chat_id = ?, telegram_link_code = NULL WHERE user_id = ?");
            $stmt->execute([$chat_id, $target_user['user_id']]);
            sendMessage($chat_id, "✅ تم ربط حسابك في BizFlow (" . htmlspecialchars($target_user['company_name']) . ") بنجاح!");
            setConversationState($target_user['user_id'], 'idle');
            clearPendingData($target_user['user_id']);
        } else {
            sendMessage($chat_id, "❌ رمز الربط غير صالح أو منتهي الصلاحية.");
        }
        exit; // End /link processing
    }

    // --- 3. Check if user is linked (for all other commands) ---
    if (!$user_row) {
        sendMessage($chat_id, "👋 أهلاً بك في BizFlow!\n\nحسابك غير مربوط. لربط حسابك:\n1. سجل دخولك على الموقع: https://bizflow.systems\n2. اذهب إلى صفحة 'حسابي' وانسخ رمز الربط.\n3. أرسل الأمر: /link [CODE]");
        exit;
    }

    // --- 4. Handle /cancel command ---
    if ($user_text === '/cancel' || $user_text === 'إلغاء') {
        setConversationState($user_id, 'idle');
        clearPendingData($user_id);
        sendMessage($chat_id, "✅ تم إلغاء العملية بنجاح. عدت للوضع العادي.");
        exit;
    }

    // --- 5. Handle messages based on conversation state ---
    switch ($user_state) {

        case 'idle':
            // --- Handle main commands ---
            if ($user_text === '/start') {
                sendMessage($chat_id, "👋 مرحبًا بك في BizFlow!\nأنت مرتبط بحساب: <b>" . htmlspecialchars($user_row['company_name']) . "</b>.\n\nالأوامر المتاحة:\n- <code>إضافة عميل</code>\n- <code>إضافة فاتورة جديدة</code>\n- <code>/cancel</code> لإلغاء أي عملية.");
            
            } elseif ($user_text === 'إضافة عميل') {
                setConversationState($user_id, 'awaiting_customer_first_name');
                clearPendingData($user_id); // Clear any old data
                sendMessage($chat_id, "👤 حسنًا، لنضف عميلًا جديدًا.\nمن فضلك أدخل <b>الاسم الأول</b> للعميل:");
            
            } elseif ($user_text === 'إضافة فاتورة جديدة') {
                // Fetch customers to show as buttons
                $stmt = $db_connection->prepare("SELECT customer_id, first_name, last_name FROM customers WHERE user_id = ? ORDER BY first_name LIMIT 10");
                $stmt->execute([$user_id]);
                $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (!$customers) {
                    sendMessage($chat_id, "⚠️ لا يوجد عملاء مضافين بعد. يجب إضافة عميل أولاً باستخدام الأمر 'إضافة عميل'.");
                    exit;
                }

                $keyboard = [];
                foreach ($customers as $cust) {
                    $keyboard[] = [['text' => $cust['first_name'] . ' ' . $cust['last_name'], 'callback_data' => 'select_customer_' . $cust['customer_id']]];
                }
                $keyboard[] = [['text' => '❌ إلغاء', 'callback_data' => 'cancel_invoice']]; // We defined /cancel, but a button is good too

                setConversationState($user_id, 'awaiting_invoice_customer_id');
                sendMessage($chat_id, "🧾 لمن تريد إصدار هذه الفاتورة؟ (اختر من القائمة)", ['inline_keyboard' => $keyboard]);
            
            } else {
                sendMessage($chat_id, "❓ أمر غير مفهوم. الأوامر المتاحة:\n- <code>إضافة عميل</code>\n- <code>إضافة فاتورة جديدة</code>\n- <code>/cancel</code> لإلغاء أي عملية.");
            }
            break;

        // --- Customer adding states ---
        case 'awaiting_customer_first_name':
            $pending_data = [];
            $pending_data['customer_first_name'] = $user_text;
            setPendingData($user_id, $pending_data);
            setConversationState($user_id, 'awaiting_customer_last_name');
            sendMessage($chat_id, "📛 ممتاز. الآن، من فضلك أدخل <b>اسم العائلة</b> للعميل:");
            break;

        case 'awaiting_customer_last_name':
            $pending_data = getPendingData($user_id);
            $pending_data['customer_last_name'] = $user_text;
            setPendingData($user_id, $pending_data);
            setConversationState($user_id, 'awaiting_customer_email');
            sendMessage($chat_id, "📧 جيد. أخيرًا، من فضلك أدخل <b>البريد الإلكتروني</b> للعميل (أو اكتب 'لا' لتخطيه):");
            break;

        case 'awaiting_customer_email':
            $pending_data = getPendingData($user_id);
            $email = (strtolower($user_text) === 'لا' || $user_text === '-') ? null : $user_text;
            
            // Validate email
            if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                sendMessage($chat_id, "❌ البريد الإلكتروني غير صالح. يرجى إدخال بريد صحيح أو إرسال 'لا'.");
                exit(); // Stay in the same state
            }

            // Add customer to DB
            $stmt = $db_connection->prepare("INSERT INTO customers (user_id, first_name, last_name, email) VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $user_id,
                $pending_data['customer_first_name'] ?? 'N/A',
                $pending_data['customer_last_name'] ?? 'N/A',
                $email
            ]);

            clearPendingData($user_id);
            setConversationState($user_id, 'idle');
            sendMessage($chat_id, "✅ تم إضافة العميل <b>" . htmlspecialchars($pending_data['customer_first_name']) . " " . htmlspecialchars($pending_data['customer_last_name']) . "</b> بنجاح!");
            break;

        // --- Invoice adding states ---
        case 'awaiting_invoice_customer_id':
            // This state waits for a *callback query* (button press). 
            // If user types text instead, we prompt them to use the buttons.
            sendMessage($chat_id, "⚠️ يرجى الضغط على أحد أزرار العملاء أعلاه. أو أرسل /cancel للبدء من جديد.");
            break;

        case 'awaiting_invoice_amount':
            if (!is_numeric($user_text) || $user_text <= 0) {
                sendMessage($chat_id, "❌ المبلغ غير صالح. يرجى إدخال رقم صحيح (مثل 150.50):");
                break;
            }
            $pending_data = getPendingData($user_id);
            $pending_data['invoice_amount'] = $user_text;
            setPendingData($user_id, $pending_data);
            setConversationState($user_id, 'awaiting_invoice_due_date');
            sendMessage($chat_id, "📅 جيد جدًا. أخيرًا، من فضلك أدخل <b>تاريخ الاستحقاق</b> (بالصيغة YYYY-MM-DD، مثال: 2025-12-31):");
            break;

        case 'awaiting_invoice_due_date':
            if (!validateDateYMD($user_text)) {
                sendMessage($chat_id, "❌ صيغة التاريخ غير صحيحة. يرجى إدخاله بالصيغة YYYY-MM-DD (مثل 2025-12-31).");
                break;
            }

            $pending_data = getPendingData($user_id);
            $customer_id = $pending_data['invoice_customer_id'] ?? null;
            $amount = $pending_data['invoice_amount'] ?? null;
            $due_date = $user_text;

            if (!$customer_id || !$amount) {
                // Data mismatch, reset state
                clearPendingData($user_id);
                setConversationState($user_id, 'idle');
                sendMessage($chat_id, "⚠️ حدث خطأ في البيانات المؤقتة. يرجى البدء من جديد.");
                break;
            }

            // Add invoice to DB
            $stmt = $db_connection->prepare("INSERT INTO invoices (user_id, customer_id, amount, due_date, status) VALUES (?, ?, ?, ?, 'pending')");
            $stmt->execute([$user_id, $customer_id, $amount, $due_date]);

            clearPendingData($user_id);
            setConversationState($user_id, 'idle');
            sendMessage($chat_id, "✅ تمت إضافة الفاتورة بنجاح!");
            break;

        default:
            logError("Unknown state: $user_state for user_id: $user_id");
            setConversationState($user_id, 'idle');
            sendMessage($chat_id, "⚠️ حدث خطأ في حالة المحادثة. تم إعادة تعيينك. أرسل /start للمتابعة.");
            break;
    } // End of switch($user_state)

} catch (PDOException $e) {
    logError("Webhook PDO Error: ". $e->getMessage() . " (Input: $input)");
    // Don't send technical error details to the user, just a generic message
    sendMessage($chat_id, "⚠️ حدث خطأ أثناء معالجة طلبك المتعلق بقاعدة البيانات. تم إبلاغ المسؤولين.");

} catch (Exception $e) {
    logError("Webhook General Error: " . $e->getMessage() . " (Input: $input)");
    // Don't send technical error details to the user
    sendMessage($chat_id, "⚠️ حدث خطأ عام في النظام. يرجى المحاولة لاحقًا.");
}

// Always respond 200 to Telegram to prevent retry loops
http_response_code(200);
?>
