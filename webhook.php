<?php
// ===================================================
// [1. إعدادات الأمان وتسجيل الأخطاء]
// ===================================================
ini_set('display_errors', 0); 
ini_set('display_startup_errors', 0); 
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', '/var/www/html/php_errors.log'); 

// ===================================================
// [2. جلب الإعدادات والاتصال بقاعدة البيانات]
// ===================================================
require_once 'config.php'; 

// ===================================================
// [3. قراءة الرسالة وتحديد المستخدم وحالته]
// ===================================================
$update = file_get_contents('php://input');
@file_put_contents('debug.txt', date('[Y-m-d H:i:s] ') . $update . PHP_EOL, FILE_APPEND); 
$data = json_decode($update, true);

// متغيرات أساسية
$chat_id = null;
$text = null;
$callback_query_id = null; // [جديد] لتحديد ضغطات الأزرار
$callback_data = null;     // [جديد] البيانات المرسلة من الزر

$user_state = 'idle'; 
$pending_data = []; 

// [تعديل] التحقق من نوع التحديث (رسالة نصية أو ضغطة زر)
if (isset($data['message']['text'])) {
    $chat_id = $data['message']['chat']['id'];
    $text = trim($data['message']['text']); 
} elseif (isset($data['callback_query'])) { // [جديد] معالجة ضغطات الأزرار
    $callback_query_id = $data['callback_query']['id'];
    $chat_id = $data['callback_query']['message']['chat']['id']; // Chat ID يأتي من رسالة الزر
    $callback_data = $data['callback_query']['data']; // البيانات المرفقة بالزر
    // لا يوجد نص ($text) في حالة ضغطة الزر
} else {
    // تجاهل الأنواع الأخرى من التحديثات
    exit(); 
}

// [هام] يجب التأكد من وجود chat_id قبل المتابعة
if (!$chat_id) {
     error_log("webhook.php - Could not determine chat_id from update.");
     exit();
}

if (!$db_connection) {
     error_log("webhook.php - Database connection not established in config.php");
     // إرسال رسالة خطأ للمستخدم إذا أمكن (فقط إذا كان لدينا chat_id)
     if($chat_id) { sendMessage($chat_id, "⚠️ حدث خطأ فادح في الاتصال بقاعدة البيانات. يرجى المحاولة لاحقًا."); }
     exit();
}

try {
    // --- [لا تغيير هنا] جلب حالة المستخدم والبيانات المؤقتة ---
    $stmt = $db_connection->prepare("SELECT state FROM customers WHERE telegram_chat_id = :chat_id");
    $stmt->execute(['chat_id' => $chat_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $user_state = $result['state'] ?: 'idle'; 
    } else {
        $user_state = 'idle'; 
    }
    $stmt = $db_connection->prepare("SELECT data FROM pending_data WHERE telegram_chat_id = :chat_id");
    $stmt->execute(['chat_id' => $chat_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result && $result['data']) {
        $pending_data = json_decode($result['data'], true) ?: [];
    }
} catch (PDOException $e) {
    error_log("webhook.php - Error fetching user state/pending data: " . $e->getMessage());
    sendMessage($chat_id, "⚠️ حدث خطأ أثناء جلب بيانات المستخدم. يرجى المحاولة مرة أخرى.");
    exit(); 
}

// ===================================================
// [4. المنطق الرئيسي للبوت]
// ===================================================

try {
    
    // --- [جديد] معالجة ضغطات الأزرار أولاً ---
    if ($callback_query_id) {
        
        // الرد على تيليجرام لتأكيد استلام الضغطة (إزالة علامة التحميل من الزر)
        answerCallbackQuery($callback_query_id); 

        // التحقق من الحالة الحالية للمستخدم
        switch($user_state) {
            case 'awaiting_invoice_customer_selection':
                 // نتوقع أن callback_data يحتوي على customer_id
                 if (is_numeric($callback_data)) {
                     $customer_id = intval($callback_data);
                     // التحقق السريع من وجود العميل (احتياطي)
                     $stmt = $db_connection->prepare("SELECT first_name FROM customers WHERE customer_id = :id");
                     $stmt->execute(['id' => $customer_id]);
                     if ($stmt->fetch()) {
                         $pending_data = ['operation' => 'add_invoice']; 
                         $pending_data['customer_id'] = $customer_id;
                         updatePendingData($db_connection, $chat_id, $pending_data);
                         updateUserState($db_connection, $chat_id, 'awaiting_invoice_amount');
                         sendMessage($chat_id, "💰 ممتاز (تم اختيار العميل #$customer_id). الآن، من فضلك أدخل مبلغ الفاتورة (أرقام فقط):");
                     } else {
                         sendMessage($chat_id, "❌ العميل المحدد لم يعد موجودًا. يرجى البدء من جديد بإرسال 'إضافة فاتورة جديدة'.");
                         clearPendingData($db_connection, $chat_id);
                         updateUserState($db_connection, $chat_id, 'idle');
                     }
                 } else {
                      sendMessage($chat_id, "⚠️ حدث خطأ في بيانات الزر. يرجى البدء من جديد بإرسال 'إضافة فاتورة جديدة'.");
                      clearPendingData($db_connection, $chat_id);
                      updateUserState($db_connection, $chat_id, 'idle');
                 }
                break;
                
            // أضف حالات أخرى لمعالجة أزرار مختلفة هنا
                
            default:
                // تجاهل الضغطة إذا كانت الحالة غير متوقعة
                sendMessage($chat_id, "❓ تم الضغط على زر في سياق غير متوقع. تم إلغاء العملية.");
                clearPendingData($db_connection, $chat_id);
                updateUserState($db_connection, $chat_id, 'idle');
                break;
        }
        
    // --- إذا لم تكن ضغطة زر، فهي رسالة نصية ---    
    } elseif ($text !== null) {

        switch ($user_state) {
            
            // --- حالات إضافة العميل (لا تغيير هنا) ---
            case 'awaiting_customer_first_name':
                // ... (نفس الكود السابق) ...
                 if (!empty($text)) {
                    $pending_data = ['operation' => 'add_customer']; 
                    $pending_data['first_name'] = $text;
                    updatePendingData($db_connection, $chat_id, $pending_data);
                    updateUserState($db_connection, $chat_id, 'awaiting_customer_last_name');
                    sendMessage($chat_id, "👍 الاسم الأول '$text' تم حفظه. الآن، من فضلك أدخل الاسم الأخير للعميل:");
                } else {
                    sendMessage($chat_id, "❌ الاسم الأول لا يمكن أن يكون فارغًا. من فضلك أعد إدخاله.");
                }
                break;
            case 'awaiting_customer_last_name':
                // ... (نفس الكود السابق) ...
                 if (!empty($text)) {
                    if (isset($pending_data['operation']) && $pending_data['operation'] == 'add_customer') {
                        $pending_data['last_name'] = $text;
                        updatePendingData($db_connection, $chat_id, $pending_data);
                        updateUserState($db_connection, $chat_id, 'awaiting_customer_email'); 
                        sendMessage($chat_id, "📧 ممتاز. أخيرًا، من فضلك أدخل البريد الإلكتروني للعميل (أو اكتب 'تخطي' إذا لم يكن متوفرًا):"); 
                    } else {
                         sendMessage($chat_id, "⚠️ حدث خطأ غير متوقع. يرجى البدء من جديد بإرسال 'إضافة عميل'.");
                         clearPendingData($db_connection, $chat_id);
                         updateUserState($db_connection, $chat_id, 'idle');
                    }
                } else {
                    sendMessage($chat_id, "❌ الاسم الأخير لا يمكن أن يكون فارغًا. من فضلك أعد إدخاله.");
                }
                break;
            case 'awaiting_customer_email':
                // ... (نفس الكود السابق) ...
                 if (isset($pending_data['operation']) && $pending_data['operation'] == 'add_customer') {
                    $email = null; 
                    if (!empty($text) && mb_strtolower(trim($text)) != 'تخطي') {
                        if (filter_var($text, FILTER_VALIDATE_EMAIL)) {
                            $email = $text;
                        } else {
                            sendMessage($chat_id, "⚠️ صيغة البريد الإلكتروني غير صحيحة. من فضلك أعد إدخاله بشكل صحيح (مثل user@example.com) أو اكتب 'تخطي'.");
                            break; 
                        }
                    } 
                    $first_name = $pending_data['first_name'] ?? 'غير معروف';
                    $last_name = $pending_data['last_name'] ?? 'غير معروف';
                    
                    $sql = "INSERT INTO customers (first_name, last_name, telegram_chat_id, email, state) 
                            VALUES (:first, :last, :chat_id, :email, 'idle') 
                            ON CONFLICT (telegram_chat_id) DO UPDATE SET 
                            first_name = EXCLUDED.first_name, 
                            last_name = EXCLUDED.last_name, 
                            email = EXCLUDED.email,
                            state = 'idle'"; 
                    $stmt = $db_connection->prepare($sql);
                    $stmt->execute([
                        'first' => $first_name, 
                        'last' => $last_name, 
                        'chat_id' => $chat_id,
                        'email' => $email 
                    ]);
                    
                    clearPendingData($db_connection, $chat_id);
                    sendMessage($chat_id, "✅ تم إضافة/تحديث العميل '$first_name $last_name' بنجاح!");
                 } else {
                     sendMessage($chat_id, "⚠️ حدث خطأ غير متوقع. يرجى البدء من جديد بإرسال 'إضافة عميل'.");
                     clearPendingData($db_connection, $chat_id);
                     updateUserState($db_connection, $chat_id, 'idle');
                 }
                break; 

            // --- [تم تعديل هذه الحالة] ---
            case 'awaiting_invoice_customer_id': 
                // هذه الحالة لم نعد نستخدمها مباشرة لأننا نستخدم الأزرار
                // لكن نتركها احتياطًا إذا أدخل المستخدم شيئًا بالخطأ
                sendMessage($chat_id, "⏳ يرجى اختيار العميل من الأزرار التي تم إرسالها.");
                break;

            // --- حالات إضافة الفاتورة (لا تغيير كبير هنا، فقط التأكد من العملية) ---
            case 'awaiting_invoice_amount':
                if (isset($pending_data['operation']) && $pending_data['operation'] == 'add_invoice' && is_numeric($text) && floatval($text) > 0) {
                    $pending_data['amount'] = floatval($text);
                    updatePendingData($db_connection, $chat_id, $pending_data);
                    updateUserState($db_connection, $chat_id, 'awaiting_invoice_due_date');
                    sendMessage($chat_id, "📅 جيد جدًا. أخيرًا، من فضلك أدخل تاريخ استحقاق الفاتورة (بالصيغة YYYY-MM-DD، مثال: 2025-12-31):");
                } else {
                    sendMessage($chat_id, "❌ يرجى إدخال مبلغ صحيح (أرقام أكبر من صفر).");
                }
                break;

            case 'awaiting_invoice_due_date':
                 if (isset($pending_data['operation']) && $pending_data['operation'] == 'add_invoice') {
                    $date_parts = explode('-', $text);
                    if (count($date_parts) == 3 && checkdate($date_parts[1], $date_parts[2], $date_parts[0])) {
                        $due_date = $text;
                        $customer_id = $pending_data['customer_id'] ?? null;
                        $amount = $pending_data['amount'] ?? 0;
                        
                        if ($customer_id && $amount > 0) {
                             $sql = "INSERT INTO invoices (customer_id, amount, status, due_date) VALUES (:customer_id, :amount, 'pending', :due_date)";
                             $stmt = $db_connection->prepare($sql);
                             $stmt->execute([
                                 'customer_id' => $customer_id, 
                                 'amount' => $amount, 
                                 'due_date' => $due_date
                             ]);
                             
                             clearPendingData($db_connection, $chat_id);
                             updateUserState($db_connection, $chat_id, 'idle'); 
                             sendMessage($chat_id, "✅ تمت إضافة الفاتورة بنجاح!");
                        } else {
                             sendMessage($chat_id, "⚠️ حدث خطأ في البيانات المجمعة. يرجى البدء من جديد بإرسال 'إضافة فاتورة جديدة'.");
                             clearPendingData($db_connection, $chat_id);
                             updateUserState($db_connection, $chat_id, 'idle');
                        }
                    } else {
                        sendMessage($chat_id, "❌ صيغة التاريخ غير صحيحة. يرجى إدخاله بالصيغة YYYY-MM-DD (مثل 2025-12-31).");
                    }
                } else {
                     sendMessage($chat_id, "⚠️ حدث خطأ غير متوقع. يرجى البدء من جديد بإرسال 'إضافة فاتورة جديدة'.");
                     clearPendingData($db_connection, $chat_id);
                     updateUserState($db_connection, $chat_id, 'idle');
                }
                break; 

                
            // --- الحالة الافتراضية (idle) - البحث عن الأوامر الرئيسية ---
            case 'idle':
            default:
                if (mb_strpos($text, '/start') === 0) {
                    sendMessage($chat_id, "مرحباً بك في BizFlow! أنا جاهز لاستقبال أوامرك.\nالأوامر المتاحة:\n- إضافة عميل\n- إضافة فاتورة جديدة");
                
                } elseif (mb_strpos($text, 'عميل') !== false) {
                    // ... (نفس كود بدء إضافة العميل) ...
                     $stmt = $db_connection->prepare("SELECT customer_id FROM customers WHERE telegram_chat_id = :chat_id");
                     $stmt->execute(['chat_id' => $chat_id]);
                     if ($stmt->fetch()) {
                         sendMessage($chat_id, "ℹ️ أنت مسجل بالفعل كعميل. هل تريد تعديل بياناتك؟ (لم تتم برمجة هذه الميزة بعد)");
                     } else {
                        ensureCustomerRecord($db_connection, $chat_id); 
                        updateUserState($db_connection, $chat_id, 'awaiting_customer_first_name');
                        clearPendingData($db_connection, $chat_id); 
                        sendMessage($chat_id, "📝 حسنًا، لنبدأ بإضافة عميل جديد. من فضلك أدخل الاسم الأول للعميل:");
                     }

                } elseif (mb_strpos($text, 'فاتورة') !== false && mb_strpos($text, 'جديدة') !== false) {
                    // --- [جديد] بدء عملية إضافة الفاتورة باستخدام الأزرار ---
                    ensureCustomerRecord($db_connection, $chat_id); 
                    clearPendingData($db_connection, $chat_id); 
                    
                    // جلب أول 10 عملاء لعرضهم كأزرار
                    $customerStmt = $db_connection->query("SELECT customer_id, first_name, last_name FROM customers ORDER BY customer_id LIMIT 10");
                    $customers = $customerStmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (empty($customers)) {
                        sendMessage($chat_id, "⚠️ لا يوجد عملاء مسجلون بعد. يرجى إضافة عميل أولاً باستخدام الأمر 'إضافة عميل'.");
                        updateUserState($db_connection, $chat_id, 'idle'); // أعد الحالة idle
                    } else {
                        $keyboard = [];
                        foreach ($customers as $customer) {
                            // كل زر يحتوي على اسم العميل، والبيانات المرفقة (callback_data) هي customer_id
                             $keyboard[][] = ['text' => $customer['first_name'] . ' ' . $customer['last_name'], 'callback_data' => (string)$customer['customer_id']];
                        }
                        
                        // إضافة زر إلغاء
                        $keyboard[][] = ['text' => '❌ إلغاء', 'callback_data' => 'cancel_invoice']; 
                        
                        $reply_markup = json_encode(['inline_keyboard' => $keyboard]);
                        
                        updateUserState($db_connection, $chat_id, 'awaiting_invoice_customer_selection'); // الحالة الجديدة تنتظر ضغطة زر
                        sendMessage($chat_id, "🧾 لمن تريد إصدار هذه الفاتورة؟ يرجى الاختيار من القائمة:", $reply_markup);
                    }

                } else {
                    sendMessage($chat_id, "❓ أمر غير مفهوم. الأوامر المتاحة:\n- /start\n- إضافة عميل\n- إضافة فاتورة جديدة");
                }
                break; 
                
        } // نهاية switch للحالات النصية
        
    } // نهاية else if ($text !== null)

} catch (PDOException $e) { 
    sendMessage($chat_id, "⚠️ حدث خطأ أثناء معالجة طلبك المتعلق بقاعدة البيانات. تم إبلاغ المسؤولين.");
    error_log("webhook.php - PDOException in main logic: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    try { clearPendingData($db_connection, $chat_id); updateUserState($db_connection, $chat_id, 'idle'); } catch (Exception $cleanupError) { error_log("webhook.php - Error during cleanup after PDOException: " . $cleanupError->getMessage()); }

} catch (Throwable $t) { 
    if ($chat_id) { @sendMessage($chat_id, "⚠️ حدث خطأ عام غير متوقع في النظام. تم إبلاغ المسؤولين."); }
    error_log("webhook.php - Unexpected Throwable: " . $t->getMessage() . "\n" . $t->getTraceAsString());
    try { clearPendingData($db_connection, $chat_id); updateUserState($db_connection, $chat_id, 'idle'); } catch (Exception $cleanupError) { error_log("webhook.php - Error during cleanup after Throwable: " . $cleanupError->getMessage()); }
}

// ===================================================
// [5. دوال مساعدة (Helper Functions)]
// ===================================================

/**
 * يرسل رسالة إلى مستخدم تلغرام، مع دعم للأزرار المضمنة (Inline Keyboard).
 */
function sendMessage($chat_id, $message, $reply_markup = null) { // [تعديل] إضافة بارامتر للأزرار
    global $db_connection; 
    try {
        $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/sendMessage";
        $data = [
            'chat_id' => $chat_id,
            'text' => $message,
            'parse_mode' => 'HTML' 
        ];
        
        // [جديد] إضافة الأزرار إذا كانت موجودة
        if ($reply_markup !== null) {
            $data['reply_markup'] = $reply_markup;
        }
        
        $options = [
            'http' => [
                'method'  => 'POST',
                // [تعديل] استخدام application/json عند إرسال reply_markup
                'header'  => "Content-Type: application/json\r\n", 
                'content' => json_encode($data), // ترميز البيانات كـ JSON
                'ignore_errors' => true 
            ],
             'ssl' => [ 
                 'verify_peer' => false,
                 'verify_peer_name' => false,
            ]
        ];
        $context  = stream_context_create($options);
        $result = @file_get_contents($url, false, $context); 

        if ($result === FALSE) { error_log("sendMessage failed to chat_id: $chat_id."); } 
        elseif (isset($http_response_header) && strpos($http_response_header[0], '200 OK') === false) { error_log("sendMessage returned non-200 status for chat_id: $chat_id."); }

    } catch (Throwable $t) { error_log("sendMessage - Unexpected Throwable: " . $t->getMessage()); }
}

/**
 * [جديد] يرد على ضغطة الزر (Callback Query) لإزالة علامة التحميل.
 */
function answerCallbackQuery($callback_query_id, $text = null) {
     try {
        $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/answerCallbackQuery";
        $data = ['callback_query_id' => $callback_query_id];
        if ($text) {
             $data['text'] = $text; // رسالة صغيرة تظهر للمستخدم (اختياري)
            // $data['show_alert'] = true; // لعرض الرسالة كنافذة منبثقة (اختياري)
        }
        
        $options = [
            'http' => ['method'  => 'POST', 'header'  => "Content-Type: application/json\r\n", 'content' => json_encode($data), 'ignore_errors' => true ],
             'ssl' => [ 'verify_peer' => false, 'verify_peer_name' => false,]
        ];
        $context  = stream_context_create($options);
        @file_get_contents($url, false, $context); 
        // لا نهتم كثيرًا بنتيجة الرد هنا، فقط نحاول إرساله

    } catch (Throwable $t) { error_log("answerCallbackQuery - Unexpected Throwable: " . $t->getMessage()); }
}


// --- باقي الدوال المساعدة (لا تغيير) ---
function ensureCustomerRecord($db, $chat_id) { try { $stmt = $db->prepare("INSERT INTO customers (telegram_chat_id, first_name, last_name, state) VALUES (:chat_id, 'Unknown', 'User', 'idle') ON CONFLICT (telegram_chat_id) DO NOTHING"); $stmt->execute(['chat_id' => $chat_id]); } catch (PDOException $e) { error_log("ensureCustomerRecord failed for chat_id $chat_id: " . $e->getMessage()); } }
function updateUserState($db, $chat_id, $new_state) { try { ensureCustomerRecord($db, $chat_id); $stmt = $db->prepare("UPDATE customers SET state = :state WHERE telegram_chat_id = :chat_id"); $stmt->execute(['state' => $new_state, 'chat_id' => $chat_id]); } catch (PDOException $e) { error_log("updateUserState failed for chat_id $chat_id: " . $e->getMessage()); } }
function updatePendingData($db, $chat_id, $data_array) { try { $json_data = json_encode($data_array); if ($json_data === false) { error_log("updatePendingData failed for chat_id $chat_id: Failed to encode data to JSON."); return; } ensureCustomerRecord($db, $chat_id); $stmt = $db->prepare("INSERT INTO pending_data (telegram_chat_id, data) VALUES (:chat_id, :data) ON CONFLICT (telegram_chat_id) DO UPDATE SET data = EXCLUDED.data"); $stmt->execute(['chat_id' => $chat_id, 'data' => $json_data]); } catch (PDOException $e) { error_log("updatePendingData failed for chat_id $chat_id: " . $e->getMessage()); } }
function clearPendingData($db, $chat_id) { try { $stmt = $db->prepare("DELETE FROM pending_data WHERE telegram_chat_id = :chat_id"); $stmt->execute(['chat_id' => $chat_id]); } catch (PDOException $e) { error_log("clearPendingData failed for chat_id $chat_id: " . $e->getMessage()); } }

?>
