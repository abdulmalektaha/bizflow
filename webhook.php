<?php
// ===== BizFlow Webhook (Final Multi-Tenant Version) =====
// This file handles all incoming updates from Telegram.

// [1. CONFIG & HELPERS]
// ========================================================

// !! هام: يجب أن يكون config.php هو أول ملف يتم استدعاؤه !!
// إنه يبدأ الجلسة session_start() ويعرّف $db_connection و logError()
require_once 'config.php';

/**
 * Sends a message to a specific Telegram chat.
 *
 * @param string $chat_id The target chat ID.
 * @param string $text The message text.
 * @param array|null $keyboard Optional Inline Keyboard markup.
 * @return bool True on success, false on failure.
 */
function sendMessage($chat_id, $text, $keyboard = null) {
    try {
        $payload = [
            'chat_id' => $chat_id,
            'text' => $text,
            'parse_mode' => 'HTML' // Allow bold, italics, etc.
        ];

        if (!empty($keyboard)) {
            $payload['reply_markup'] = json_encode($keyboard);
        }

        $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/sendMessage";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10); // 10-second timeout
        
        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpcode != 200) {
            logError("Telegram API error. HTTP Code: $httpcode. Response: $response");
            return false;
        }
        return true;

    } catch (Exception $e) {
        logError("sendMessage Exception: " . $e->getMessage());
        return false;
    }
}

/**
 * Gets a user's account details from the database using their chat_id.
 *
 * @param PDO $db The database connection.
 * @param string $chat_id The user's Telegram chat ID.
 * @return array|false The user's row as an array, or false if not found.
 */
function getUserByChatId($db, $chat_id) {
    try {
        $stmt = $db->prepare("SELECT * FROM users WHERE telegram_chat_id = :chat_id");
        $stmt->execute(['chat_id' => $chat_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logError("getUserByChatId PDOException: " . $e->getMessage());
        return false;
    }
}

/**
 * Updates a user's conversation state.
 *
 * @param PDO $db The database connection.
 * @param int $user_id The user's account ID.
 * @param string|null $state The new state (e.g., 'awaiting_customer_name') or null ('idle').
 */
function updateUserConversationState($db, $user_id, $state) {
    if ($state === null) {
        $state = 'idle';
    }
    try {
        $stmt = $db->prepare("UPDATE users SET conversation_state = :state WHERE user_id = :user_id");
        $stmt->execute(['state' => $state, 'user_id' => $user_id]);
    } catch (PDOException $e) {
        logError("updateUserConversationState PDOException: " . $e->getMessage());
    }
}

/**
 * Saves temporary data for a user's conversation.
 * Uses JSONB and ON CONFLICT (upsert) for efficiency.
 *
 * @param PDO $db The database connection.
 * @param int $user_id The user's account ID.
 * @param array $data The full data array to save.
 */
function savePendingData($db, $user_id, $data) {
    try {
        $jsonData = json_encode($data);
        $sql = "INSERT INTO pending_data (user_id, data) 
                VALUES (:user_id, :data)
                ON CONFLICT (user_id) DO UPDATE 
                SET data = EXCLUDED.data, updated_at = CURRENT_TIMESTAMP";
        
        $stmt = $db->prepare($sql);
        $stmt->execute(['user_id' => $user_id, 'data' => $jsonData]);
    } catch (PDOException $e) {
        logError("savePendingData PDOException: " . $e->getMessage());
    }
}

/**
 * Retrieves temporary conversation data for a user.
 *
 * @param PDO $db The database connection.
 * @param int $user_id The user's account ID.
 * @return array The user's pending data, or an empty array if none.
 */
function getPendingData($db, $user_id) {
    try {
        $stmt = $db->prepare("SELECT data FROM pending_data WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $user_id]);
        $json = $stmt->fetchColumn();
        
        return $json ? json_decode($json, true) : [];
    } catch (PDOException $e) {
        logError("getPendingData PDOException: " . $e->getMessage());
        return [];
    }
}

/**
 * Clears temporary conversation data for a user.
 *
 * @param PDO $db The database connection.
 * @param int $user_id The user's account ID.
 */
function clearPendingData($db, $user_id) {
    try {
        $stmt = $db->prepare("DELETE FROM pending_data WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $user_id]);
    } catch (PDOException $e) {
        logError("clearPendingData PDOException: " . $e->getMessage());
    }
}

// ========================================================
// [2. START PROCESSING]
// ========================================================

try {
    // Get the raw POST data from Telegram
    $update = json_decode(file_get_contents('php://input'), true);

    // If there's no update, exit silently
    if (!$update) {
        exit();
    }
    
    // Determine if it's a button press (Callback Query) or a text message
    $callback_query = $update['callback_query'] ?? null;
    $message = $update['message'] ?? null;
    
    $chat_id = null;
    $user_text = null;
    $is_callback = false;

    if ($callback_query) {
        // User pressed an inline button
        $is_callback = true;
        $user_text = $callback_query['data']; // Data from the button (e.g., "customer_id:1")
        $chat_id = $callback_query['from']['id'];
        $message_id = $callback_query['message']['message_id']; // To edit the message later
        
        // Answer the callback query to stop the "loading" icon on the button
        $callback_id = $callback_query['id'];
        file_get_contents("https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/answerCallbackQuery?callback_query_id=" . $callback_id);

    } elseif ($message) {
        // User sent a text message
        $is_callback = false;
        $user_text = $message['text'] ?? '';
        $chat_id = $message['chat']['id'];
    }

    // If we don't have a chat_id, we can't respond
    if (!$chat_id) {
        exit();
    }
    
    // Sanitize the text
    $user_text = trim($user_text);

    // [--- هذا هو تقريبا منتصف الكود ---]

} catch (Exception $e) {
    logError("Unhandled Exception in webhook.php: " . $e->getMessage());
    // Send a generic error message if possible
    if ($chat_id) {
        sendMessage($chat_id, "⚠️ حدث خطأ فادح وغير متوقع. تم إبلاغ المسؤولين.");
    }
}

?>
    // [3. MAIN LOGIC - (Second Half)]
    // ========================================================
    // (This code assumes $db_connection, $chat_id, $user_text, and $is_callback are set)

    // First, check if the user is linked to an account
    $user = getUserByChatId($db_connection, $chat_id);

    // --- Handle Link Command (must work even if user is not linked) ---
    if (strpos($user_text, '/link') === 0) {
        $parts = explode(' ', $user_text);
        $link_code = $parts[1] ?? null;

        if (!$link_code) {
            sendMessage($chat_id, "⚠️ يرجى إرسال الرمز مع الأمر. مثال: /link BZF-XYZ123");
        } else {
            // Find user by this link code
            $stmt = $db_connection->prepare("SELECT * FROM users WHERE telegram_link_code = :code");
            $stmt->execute(['code' => $link_code]);
            $account_to_link = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($account_to_link) {
                // Link successful
                // We also check if this telegram account is already linked to another user
                $existing_user = getUserByChatId($db_connection, $chat_id);
                if($existing_user && $existing_user['user_id'] !== $account_to_link['user_id']) {
                     sendMessage($chat_id, "❌ خطأ: حساب تيليجرام هذا مربوط بالفعل بحساب شركة أخرى.");
                     exit();
                }

                $stmt_link = $db_connection->prepare("UPDATE users SET telegram_chat_id = :chat_id, telegram_link_code = NULL, conversation_state = 'idle' WHERE user_id = :user_id");
                $stmt_link->execute(['chat_id' => $chat_id, 'user_id' => $account_to_link['user_id']]);
                
                // Clear any old pending data
                clearPendingData($db_connection, $account_to_link['user_id']);
                
                sendMessage($chat_id, "✅ تم ربط حسابك في BizFlow (" . htmlspecialchars($account_to_link['company_name']) . ") بنجاح!");
            } else {
                sendMessage($chat_id, "❌ رمز الربط غير صحيح أو انتهت صلاحيته.");
            }
        }
        exit(); // Stop further processing
    }

    // --- Security Gate: If user is NOT linked (and not linking), stop them ---
    if (!$user) {
        sendMessage($chat_id, "⚠️ حسابك غير مربوط.\nيرجى زيارة موقع BizFlow، تسجيل الدخول، والذهاب إلى صفحة 'حسابي' للحصول على رمز الربط، ثم أرسل:\n<code>/link [CODE]</code>");
        exit();
    }

    // --- User is linked ---
    $user_id = $user['user_id'];
    $user_state = $user['conversation_state'] ?? 'idle';
    $pending_data = getPendingData($db_connection, $user_id);

    // --- Universal Cancel Command ---
    if ($user_text === '/cancel' || $user_text === 'إلغاء') {
        clearPendingData($db_connection, $user_id);
        updateUserConversationState($db_connection, $user_id, 'idle');
        sendMessage($chat_id, "✅ تم إلغاء العملية الحالية.");
        exit();
    }


    // [A] Handle Button Presses (Callback Queries)
    // ===========================================
    if ($is_callback) {
        
        $callback_data = $user_text; // e.g., "cust_id:1" or "invoice_cancel"

        // --- Handle Invoice Customer Selection ---
        if (strpos($callback_data, 'cust_id:') === 0) {
            // User selected a customer
            $customer_id = str_replace('cust_id:', '', $callback_data);
            
            // Check if this customer_id is valid AND belongs to this user
            $stmt = $db_connection->prepare("SELECT first_name, last_name FROM customers WHERE customer_id = :customer_id AND user_id = :user_id");
            $stmt->execute(['customer_id' => $customer_id, 'user_id' => $user_id]);
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($customer) {
                $pending_data = ['customer_id' => $customer_id]; // Start pending data
                savePendingData($db_connection, $user_id, $pending_data);
                updateUserConversationState($db_connection, $user_id, 'awaiting_invoice_amount');
                sendMessage($chat_id, "💰 ممتاز (تم اختيار العميل: <b>" . htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']) . "</b>).\nالآن، من فضلك أدخل مبلغ الفاتورة (أرقام فقط):");
            } else {
                sendMessage($chat_id, "❌ خطأ: العميل المحدد غير موجود أو لا ينتمي إليك.");
                updateUserConversationState($db_connection, $user_id, 'idle');
            }
        }
        
        // --- Handle Invoice Cancel ---
        elseif ($callback_data === 'invoice_cancel') {
            updateUserConversationState($db_connection, $user_id, 'idle');
            sendMessage($chat_id, "تم إلغاء إضافة الفاتورة.");
            // You can also edit the original message to remove the buttons here if needed
        }
        
        exit(); // Stop processing for callbacks
    }


    // [B] Handle Text Messages based on State
    // =======================================
    switch ($user_state) {

        // --- [CASE: IDLE] ---
        // User is not in a conversation, check for new commands
        case 'idle':
            $command = mb_strtolower($user_text, 'UTF-8');
            
            if ($command === '/start') {
                sendMessage($chat_id, "👋 مرحبًا بك في BizFlow!\nحسابك (<b>" . htmlspecialchars($user['company_name']) . "</b>) مربوط بنجاح.\n\nالأوامر المتاحة:\n- <code>إضافة عميل</code>\n- <code>إضافة فاتورة جديدة</code>\n- <code>/cancel</code> لإلغاء أي عملية.");
            
            } elseif (strpos($command, 'إضافة عميل') !== false) {
                // --- Start "Add Customer" ---
                clearPendingData($db_connection, $user_id); // Clear old data
                updateUserConversationState($db_connection, $user_id, 'awaiting_customer_first_name');
                sendMessage($chat_id, "📝 حسنًا، لنبدأ بإضافة عميل جديد.\nمن فضلك أدخل <b>الاسم الأول</b> للعميل:");

            } elseif (strpos($command, 'إضافة فاتورة جديدة') !== false) {
                // --- Start "Add Invoice" ---
                // Fetch customers to show as buttons
                $stmt = $db_connection->prepare("SELECT customer_id, first_name, last_name FROM customers WHERE user_id = :user_id ORDER BY first_name LIMIT 10");
                $stmt->execute(['user_id' => $user_id]);
                $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (empty($customers)) {
                    sendMessage($chat_id, "⚠️ يجب أن يكون لديك عملاء أولاً. أرسل <code>إضافة عميل</code> لإضافة أول عميل.");
                    exit();
                }

                $keyboard = ['inline_keyboard' => []];
                $row = [];
                foreach ($customers as $customer) {
                    $button_text = htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']);
                    $callback_data = 'cust_id:' . $customer['customer_id'];
                    $row[] = ['text' => $button_text, 'callback_data' => $callback_data];
                    
                    // Add 2 buttons per row
                    if (count($row) == 2) {
                        $keyboard['inline_keyboard'][] = $row;
                        $row = [];
                    }
                }
                if (!empty($row)) { // Add any remaining buttons
                    $keyboard['inline_keyboard'][] = $row;
                }
                // Add a cancel button
                $keyboard['inline_keyboard'][] = [['text' => '❌ إلغاء', 'callback_data' => 'invoice_cancel']];

                updateUserConversationState($db_connection, $user_id, 'awaiting_invoice_customer_id');
                sendMessage($chat_id, "🧾 لمن تريد إصدار هذه الفاتورة؟\nاختر من القائمة:", $keyboard);

            } else {
                // --- Unknown Command ---
                sendMessage($chat_id, "❓ أمر غير مفهوم. الأوامر المتاحة:\n- <code>/start</code>\n- <code>إضافة عميل</code>\n- <code>إضافة فاتورة جديدة</code>");
            }
            break;

        // --- [CASE: ADD CUSTOMER] ---
        case 'awaiting_customer_first_name':
            $pending_data['first_name'] = $user_text;
            savePendingData($db_connection, $user_id, $pending_data);
            updateUserConversationState($db_connection, $user_id, 'awaiting_customer_last_name');
            sendMessage($chat_id, "👍 الاسم الأول '" . htmlspecialchars($user_text) . "' تم حفظه.\nالآن، من فضلك أدخل <b>الاسم الأخير</b> للعميل:");
            break;

        case 'awaiting_customer_last_name':
            $pending_data['last_name'] = $user_text;
            savePendingData($db_connection, $user_id, $pending_data);
            updateUserConversationState($db_connection, $user_id, 'awaiting_customer_email');
            sendMessage($chat_id, "📧 ممتاز.\nأخيرًا، من فضلك أدخل <b>البريد الإلكتروني</b> للعميل (أو أرسل 'لا' إذا لم يوجد):");
            break;

        case 'awaiting_customer_email':
            $email = (mb_strtolower($user_text, 'UTF-8') === 'لا') ? null : $user_text;
            
            // Validate email
            if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                sendMessage($chat_id, "❌ البريد الإلكتروني غير صالح. يرجى إدخال بريد صحيح أو إرسال 'لا'.");
                exit(); // Stay in the same state
            }
            
            $first_name = $pending_data['first_name'] ?? 'N/A';
            $last_name = $pending_data['last_name'] ?? 'N/A';

            try {
                $sql = "INSERT INTO customers (user_id, first_name, last_name, email, telegram_chat_id) 
                        VALUES (:user_id, :first, :last, :email, NULL)"; // telegram_chat_id for customer is separate
                $stmt = $db_connection->prepare($sql);
                $stmt->execute([
                    'user_id' => $user_id,
                    'first' => $first_name,
                    'last' => $last_name,
                    'email' => $email
                ]);
                
                sendMessage($chat_id, "✅ تم إضافة العميل '" . htmlspecialchars($first_name . ' ' . $last_name) . "' بنجاح!");
                clearPendingData($db_connection, $user_id);
                updateUserConversationState($db_connection, $user_id, 'idle');

            } catch (PDOException $e) {
                if ($e->getCode() == '23505') { // Unique constraint violation
                    logError("add customer PDOException: " . $e->getMessage());
                    sendMessage($chat_id, "⚠️ حدث خطأ: البريد الإلكتروني '$email' مستخدم بالفعل لعميل آخر.");
                } else {
                    logError("add customer PDOException: " . $e->getMessage());
                    sendMessage($chat_id, "⚠️ حدث خطأ أثناء حفظ العميل. تم إبلاغ المسؤولين.");
                }
                // Don't reset state, let them try again
            }
            break;
            
        // --- [CASE: ADD INVOICE] ---
        case 'awaiting_invoice_customer_id':
            // This state is now only waiting for a button press (callback)
            // If the user types text instead, re-prompt them.
            sendMessage($chat_id, "⚠️ يرجى الضغط على أحد الأزرار لاختيار العميل، أو أرسل <code>/cancel</code> للإلغاء.");
            break;
            
        case 'awaiting_invoice_amount':
            if (!is_numeric($user_text) || $user_text <= 0) {
                sendMessage($chat_id, "❌ المبلغ غير صالح. يرجى إدخال أرقام فقط (مثل 150.50).");
                exit(); // Stay in the same state
            }
            $pending_data['amount'] = $user_text;
            savePendingData($db_connection, $user_id, $pending_data);
            updateUserConversationState($db_connection, $user_id, 'awaiting_invoice_due_date');
            sendMessage($chat_id, "📅 جيد جدًا.\nأخيرًا، من فضلك أدخل تاريخ استحقاق الفاتورة (بالصيغة <b>YYYY-MM-DD</b>، مثال: 2025-12-31):");
            break;

        case 'awaiting_invoice_due_date':
            // Validate date format
            $date_parts = explode('-', $user_text);
            if (count($date_parts) !== 3 || !checkdate($date_parts[1], $date_parts[2], $date_parts[0])) {
                sendMessage($chat_id, "❌ صيغة التاريخ غير صحيحة. يرجى إدخاله بالصيغة YYYY-MM-DD (مثل 2025-12-31).");
                exit(); // Stay in the same state
            }
            
            $customer_id = $pending_data['customer_id'] ?? null;
            $amount = $pending_data['amount'] ?? null;

            if (!$customer_id || !$amount) {
                sendMessage($chat_id, "⚠️ حدث خطأ، بيانات الفاتورة ناقصة. تم إلغاء العملية.");
                clearPendingData($db_connection, $user_id);
                updateUserConversationState($db_connection, $user_id, 'idle');
                exit();
            }

            try {
                $sql = "INSERT INTO invoices (user_id, customer_id, amount, status, due_date) VALUES (:user_id, :customer_id, :amount, 'pending', :due_date)";
                $stmt = $db_connection->prepare($sql);
                $stmt->execute([
                    'user_id' => $user_id,
                    'customer_id' => $customer_id,
                    'amount' => $amount,
                    'due_date' => $user_text
                ]);
                
                sendMessage($chat_id, "✅ تمت إضافة الفاتورة بنجاح!");
                clearPendingData($db_connection, $user_id);
                updateUserConversationState($db_connection, $user_id, 'idle');

            } catch (PDOException $e) {
                logError("add invoice PDOException: " . $e->getMessage());
                sendMessage($chat_id, "⚠️ حدث خطأ أثناء حفظ الفاتورة. تم إبلاغ المسؤولين.");
            }
            break;

        // --- [CASE: DEFAULT] ---
        // User is in an unknown state
        default:
            logError("Unknown state: $user_state for user_id: $user_id");
            updateUserConversationState($db_connection, $user_id, 'idle');
            sendMessage($chat_id, "⚠️ حدث خطأ في حالة المحادثة. تم إعادة تعيينك. أرسل /start للمتابعة.");
            break;
    } // End of switch($user_state)

    // --- This is the end of the logic part ---
    // The closing brace for the main try { ... } block
    // and the final exit() / ?>
    // were in the first file.
