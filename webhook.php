<?php
// [1. CONFIG & HELPERS]
require_once 'config.php'; 

// !! الإصلاح الهام: تعريف المتغير الذي تستخدمه الدوال !!
$BOT_TOKEN = defined('TELEGRAM_BOT_TOKEN') ? TELEGRAM_BOT_TOKEN : '';

// --- دوال المساعدة ---

function sendMessage($chat_id, $text, $keyboard = null) {
    global $BOT_TOKEN; // الآن هذا المتغير أصبح يحتوي على القيمة الصحيحة
    $url = "https://api.telegram.org/bot" . $BOT_TOKEN . "/sendMessage";
    
    $payload = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];
    if ($keyboard) {
        $payload['reply_markup'] = json_encode(['inline_keyboard' => $keyboard]);
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}

function answerCallbackQuery($callback_query_id, $text = null) {
    global $BOT_TOKEN;
    $url = "https://api.telegram.org/bot" . $BOT_TOKEN . "/answerCallbackQuery";
    $payload = ['callback_query_id' => $callback_query_id];
    if ($text) $payload['text'] = $text;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
}

// --- دوال قاعدة البيانات ---

function getUserByChatId($db, $chat_id) {
    $stmt = $db->prepare("SELECT * FROM users WHERE telegram_chat_id = ?");
    $stmt->execute([$chat_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getUserByLinkCode($db, $code) {
    $stmt = $db->prepare("SELECT * FROM users WHERE telegram_link_code = ?");
    $stmt->execute([$code]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function updateUserState($db, $user_id, $state) {
    $stmt = $db->prepare("UPDATE users SET conversation_state = ? WHERE user_id = ?");
    $stmt->execute([$state, $user_id]);
}

function savePendingData($db, $user_id, $data) {
    $json = json_encode($data);
    $sql = "INSERT INTO pending_data (user_id, data, updated_at) VALUES (?, ?, NOW())
            ON CONFLICT (user_id) DO UPDATE SET data = EXCLUDED.data, updated_at = NOW()";
    $stmt = $db->prepare($sql);
    $stmt->execute([$user_id, $json]);
}

function getPendingData($db, $user_id) {
    $stmt = $db->prepare("SELECT data FROM pending_data WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $json = $stmt->fetchColumn();
    return $json ? json_decode($json, true) : [];
}

function clearPendingData($db, $user_id) {
    $stmt = $db->prepare("DELETE FROM pending_data WHERE user_id = ?");
    $stmt->execute([$user_id]);
}

// --- [2. MAIN LOGIC] ---

try {
    $input = file_get_contents('php://input');
    $update = json_decode($input, true);

    if (!$update) exit;

    $chat_id = null;
    $user_text = null;
    $callback_data = null;

    if (isset($update['message'])) {
        $chat_id = $update['message']['chat']['id'];
        $user_text = trim($update['message']['text'] ?? '');
    } elseif (isset($update['callback_query'])) {
        $chat_id = $update['callback_query']['message']['chat']['id'];
        $callback_data = $update['callback_query']['data'];
        $callback_id = $update['callback_query']['id'];
        answerCallbackQuery($callback_id);
    }

    if (!$chat_id) exit;

    // 1. معالجة أمر الربط /link (الأولوية القصوى)
    if ($user_text && strpos($user_text, '/link') === 0) {
        $parts = explode(' ', $user_text);
        $code = $parts[1] ?? '';
        
        if (!$code) {
            sendMessage($chat_id, "❌ الرجاء إرسال الرمز بعد الأمر. مثال:\n/link BZF-12345");
            exit;
        }

        $target_user = getUserByLinkCode($db_connection, $code);
        if ($target_user) {
            $stmt = $db_connection->prepare("UPDATE users SET telegram_chat_id = ?, telegram_link_code = NULL WHERE user_id = ?");
            $stmt->execute([$chat_id, $target_user['user_id']]);
            
            // تنظيف الحالة القديمة
            updateUserState($db_connection, $target_user['user_id'], 'idle');
            clearPendingData($db_connection, $target_user['user_id']);
            
            sendMessage($chat_id, "✅ تم ربط حسابك بنجاح بشركة: <b>" . htmlspecialchars($target_user['company_name']) . "</b>");
        } else {
            sendMessage($chat_id, "❌ رمز الربط غير صالح أو منتهي الصلاحية.");
        }
        exit;
    }

    // 2. التحقق من المستخدم
    $user = getUserByChatId($db_connection, $chat_id);
    
    if (!$user) {
        sendMessage($chat_id, "👋 مرحبًا! حسابك غير مربوط.\nيرجى الذهاب إلى موقع BizFlow (صفحة حسابي) للحصول على رمز الربط، ثم أرسل:\n/link [الرمز]");
        exit;
    }

    $user_id = $user['user_id'];
    $state = $user['conversation_state'] ?? 'idle';

    // 3. معالجة أمر الإلغاء
    if ($user_text === '/cancel' || $user_text === 'إلغاء') {
        updateUserState($db_connection, $user_id, 'idle');
        clearPendingData($db_connection, $user_id);
        sendMessage($chat_id, "✅ تم إلغاء العملية.");
        exit;
    }

    // 4. معالجة الحالات (State Machine)
    if ($callback_data) {
        // معالجة ضغطات الأزرار
        if (strpos($callback_data, 'cust_id:') === 0) {
            $cust_id = str_replace('cust_id:', '', $callback_data);
            
            // حفظ العميل المختار والانتقال للمبلغ
            $data = ['invoice_customer_id' => $cust_id];
            savePendingData($db_connection, $user_id, $data);
            updateUserState($db_connection, $user_id, 'awaiting_invoice_amount');
            
            sendMessage($chat_id, "💰 تم اختيار العميل. الآن، أدخل <b>مبلغ الفاتورة</b> (أرقام فقط):");
        } elseif ($callback_data === 'invoice_cancel') {
            updateUserState($db_connection, $user_id, 'idle');
            sendMessage($chat_id, "تم الإلغاء.");
        }
        exit;
    }

    // معالجة النصوص
    switch ($state) {
        case 'idle':
            if ($user_text === '/start') {
                sendMessage($chat_id, "مرحبًا <b>{$user['company_name']}</b>! 🚀\n\nالأوامر المتاحة:\n- <b>إضافة عميل</b>\n- <b>إضافة فاتورة جديدة</b>");
            
            } elseif ($user_text === 'إضافة عميل') {
                updateUserState($db_connection, $user_id, 'awaiting_customer_fname');
                sendMessage($chat_id, "👤 أدخل <b>الاسم الأول</b> للعميل:");
            
            } elseif ($user_text === 'إضافة فاتورة جديدة') {
                // جلب العملاء للأزرار
                $stmt = $db_connection->prepare("SELECT customer_id, first_name, last_name FROM customers WHERE user_id = ? LIMIT 10");
                $stmt->execute([$user_id]);
                $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (!$customers) {
                    sendMessage($chat_id, "⚠️ لا يوجد عملاء. أضف عميلًا أولاً.");
                } else {
                    $keyboard = [];
                    foreach ($customers as $c) {
                        $keyboard[] = [['text' => $c['first_name'] . ' ' . $c['last_name'], 'callback_data' => 'cust_id:' . $c['customer_id']]];
                    }
                    $keyboard[] = [['text' => '❌ إلغاء', 'callback_data' => 'invoice_cancel']];
                    
                    updateUserState($db_connection, $user_id, 'awaiting_invoice_customer');
                    sendMessage($chat_id, "🧾 لمن هذه الفاتورة؟ اختر من القائمة:", ['inline_keyboard' => $keyboard]);
                }
            } else {
                sendMessage($chat_id, "❓ أمر غير معروف.");
            }
            break;

        case 'awaiting_customer_fname':
            savePendingData($db_connection, $user_id, ['fname' => $user_text]);
            updateUserState($db_connection, $user_id, 'awaiting_customer_lname');
            sendMessage($chat_id, "أدخل <b>الاسم الأخير</b>:");
            break;

        case 'awaiting_customer_lname':
            $data = getPendingData($db_connection, $user_id);
            $data['lname'] = $user_text;
            savePendingData($db_connection, $user_id, $data);
            updateUserState($db_connection, $user_id, 'awaiting_customer_email');
            sendMessage($chat_id, "أدخل <b>البريد الإلكتروني</b> (أو 'لا'):");
            break;

        case 'awaiting_customer_email':
            $data = getPendingData($db_connection, $user_id);
            $email = ($user_text === 'لا') ? null : $user_text;
            
            $stmt = $db_connection->prepare("INSERT INTO customers (user_id, first_name, last_name, email) VALUES (?, ?, ?, ?)");
            $stmt->execute([$user_id, $data['fname'], $data['lname'], $email]);
            
            updateUserState($db_connection, $user_id, 'idle');
            clearPendingData($db_connection, $user_id);
            sendMessage($chat_id, "✅ تم إضافة العميل بنجاح!");
            break;

        case 'awaiting_invoice_amount':
            if (!is_numeric($user_text)) {
                sendMessage($chat_id, "❌ الرجاء إدخال رقم صحيح.");
                break;
            }
            $data = getPendingData($db_connection, $user_id);
            $data['amount'] = $user_text;
            savePendingData($db_connection, $user_id, $data);
            updateUserState($db_connection, $user_id, 'awaiting_invoice_date');
            sendMessage($chat_id, "📅 أدخل تاريخ الاستحقاق (YYYY-MM-DD):");
            break;

        case 'awaiting_invoice_date':
            $data = getPendingData($db_connection, $user_id);
            $stmt = $db_connection->prepare("INSERT INTO invoices (user_id, customer_id, amount, due_date, status) VALUES (?, ?, ?, ?, 'pending')");
            $stmt->execute([$user_id, $data['invoice_customer_id'], $data['amount'], $user_text]);
            
            updateUserState($db_connection, $user_id, 'idle');
            clearPendingData($db_connection, $user_id);
            sendMessage($chat_id, "✅ تم إنشاء الفاتورة بنجاح!");
            break;
    }

} catch (Exception $e) {
    // Error logging if needed
}
```

4.  اضغط **`Commit changes`**.

### ## الخطوة الثانية: تحديث السيرفر

اذهب إلى الـ Terminal ونفذ الأوامر المعتادة:
```bash
cd ~/bizflow
git pull
sudo rm -rf /var/www/html/*
sudo cp -r * /var/www/html/
sudo chown -R www-data:www-data /var/www/html
```

### ## الاختبار النهائي

اذهب إلى البوت وأرسل `/start`. سيجيبك فورًا!
