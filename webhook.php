<?php
// ===================================================
// [1. إعدادات الأمان وتسجيل الأخطاء]
// ===================================================
ini_set('display_errors', 0); // [هام] إيقاف عرض الأخطاء للمستخدم
ini_set('display_startup_errors', 0); // [هام] إيقاف عرض أخطاء البدء
error_reporting(E_ALL);

// [هام] هذان السطران سيسجلان أي خطأ في ملف خاص بدلاً من عرضه
ini_set('log_errors', 1);
ini_set('error_log', '/var/www/html/php_errors.log'); // ملف السجل الخاص بنا

// ===================================================
// [2. جلب الإعدادات والاتصال بقاعدة البيانات]
// ===================================================
require_once 'config.php'; // يجلب $db_connection والتوكن

// ===================================================
// [3. قراءة الرسالة وتحديد المستخدم وحالته]
// ===================================================
$update = file_get_contents('php://input');
// سنحتفظ بهذا مؤقتًا للمساعدة في التصحيح إذا لزم الأمر
@file_put_contents('debug.txt', date('[Y-m-d H:i:s] ') . $update . PHP_EOL, FILE_APPEND); 

$data = json_decode($update, true);

// متغيرات أساسية
$chat_id = null;
$text = null;
$user_state = 'idle'; // الحالة الافتراضية إذا لم يكن المستخدم موجودًا
$pending_data = []; // بيانات مؤقتة

if (isset($data['message']['text'])) {
    $chat_id = $data['message']['chat']['id'];
    $text = trim($data['message']['text']); // استخدام trim لإزالة المسافات الزائدة
} else {
    // تجاهل الرسائل غير النصية أو التعديلات
    // يمكنك إضافة معالجة لأنواع أخرى من الرسائل هنا لاحقًا (مثل Callback Queries للأزرار)
    exit(); 
}

// جلب حالة المستخدم وبياناته المؤقتة من قاعدة البيانات
// التأكد من وجود اتصال قاعدة البيانات أولاً
if (!$db_connection) {
     error_log("webhook.php - Database connection not established in config.php");
     // لا يمكن المتابعة بدون قاعدة بيانات
     exit();
}

try {
    // التحقق من وجود العميل وجلب حالته
    $stmt = $db_connection->prepare("SELECT state FROM customers WHERE telegram_chat_id = :chat_id");
    $stmt->execute(['chat_id' => $chat_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $user_state = $result['state'] ?: 'idle'; // إذا كانت الحالة null، اعتبرها idle
    } else {
        // إذا لم يكن العميل موجودًا، حالته تعتبر idle (سيتم إنشاؤه عند الحاجة)
        $user_state = 'idle'; 
    }

    // جلب البيانات المؤقتة إذا وجدت
    $stmt = $db_connection->prepare("SELECT data FROM pending_data WHERE telegram_chat_id = :chat_id");
    $stmt->execute(['chat_id' => $chat_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result && $result['data']) {
        $pending_data = json_decode($result['data'], true) ?: [];
    }

} catch (PDOException $e) {
    error_log("webhook.php - Error fetching user state/pending data: " . $e->getMessage());
    // لا نرسل رسالة خطأ للمستخدم هنا، لأنها مشكلة داخلية
    exit(); // إيقاف التنفيذ إذا لم نتمكن من قراءة الحالة
}

// ===================================================
// [4. المنطق الرئيسي للبوت (حسب الحالة)]
// ===================================================

try {
    
    // --- معالجة الحالات المختلفة ---
    switch ($user_state) {
        
        // --- حالة انتظار الاسم الأول للعميل ---
        case 'awaiting_customer_first_name':
            if (!empty($text)) {
                $pending_data = []; // مسح أي بيانات قديمة لهذه العملية
                $pending_data['first_name'] = $text;
                updatePendingData($db_connection, $chat_id, $pending_data);
                updateUserState($db_connection, $chat_id, 'awaiting_customer_last_name');
                sendMessage($chat_id, "👍 الاسم الأول '$text' تم حفظه. الآن، من فضلك أدخل الاسم الأخير للعميل:");
            } else {
                sendMessage($chat_id, "❌ الاسم الأول لا يمكن أن يكون فارغًا. من فضلك أعد إدخاله.");
            }
            break;

        // --- حالة انتظار الاسم الأخير للعميل ---
        case 'awaiting_customer_last_name':
            if (!empty($text)) {
                $pending_data['last_name'] = $text;
                updatePendingData($db_connection, $chat_id, $pending_data);
                updateUserState($db_connection, $chat_id, 'awaiting_customer_email'); // <-- [تغيير هنا] ننتقل لسؤال البريد الإلكتروني
                sendMessage($chat_id, "📧 ممتاز. أخيرًا، من فضلك أدخل البريد الإلكتروني للعميل (أو اكتب 'تخطي' إذا لم يكن متوفرًا):"); // <-- [تغيير هنا]
            } else {
                sendMessage($chat_id, "❌ الاسم الأخير لا يمكن أن يكون فارغًا. من فضلك أعد إدخاله.");
            }
            break;
            
        // --- [جديد] حالة انتظار البريد الإلكتروني للعميل ---
        case 'awaiting_customer_email':
             $email = null; // القيمة الافتراضية
            if (!empty($text) && mb_strtolower($text) != 'تخطي') {
                // التحقق من صحة صيغة البريد الإلكتروني (بسيط)
                if (filter_var($text, FILTER_VALIDATE_EMAIL)) {
                    $email = $text;
                } else {
                    sendMessage($chat_id, "⚠️ صيغة البريد الإلكتروني غير صحيحة. من فضلك أعد إدخاله بشكل صحيح (مثل user@example.com) أو اكتب 'تخطي'.");
                    break; // ابق في نفس الحالة وانتظر إدخالاً صحيحًا
                }
            } // إذا كتب 'تخطي' أو لم يكتب شيئًا، ستبقى $email = null

            // --- الآن لدينا كل المعلومات لإضافة/تحديث العميل ---
            $first_name = $pending_data['first_name'] ?? 'غير معروف';
            $last_name = $pending_data['last_name'] ?? 'غير معروف';
            
            // إضافة/تحديث العميل في قاعدة البيانات
            $sql = "INSERT INTO customers (first_name, last_name, telegram_chat_id, email, state) 
                    VALUES (:first, :last, :chat_id, :email, 'idle') 
                    ON CONFLICT (telegram_chat_id) DO UPDATE SET 
                    first_name = EXCLUDED.first_name, 
                    last_name = EXCLUDED.last_name, 
                    email = EXCLUDED.email,
                    state = 'idle'"; // استخدام ON CONFLICT للتعامل مع العملاء الموجودين وتحديث بياناتهم وحالتهم
            $stmt = $db_connection->prepare($sql);
            $stmt->execute([
                'first' => $first_name, 
                'last' => $last_name, 
                'chat_id' => $chat_id,
                'email' => $email // يمكن أن تكون null إذا قام بالتخطي
            ]);
            
            // حذف البيانات المؤقتة
            clearPendingData($db_connection, $chat_id);
            // (الحالة تم تحديثها إلى idle في جملة INSERT/UPDATE)

            sendMessage($chat_id, "✅ تم إضافة/تحديث العميل '$first_name $last_name' بنجاح!");
            break; // نهاية حالة awaiting_customer_email
            
        // --- [أضف هنا حالات أخرى مثل awaiting_invoice_amount, awaiting_invoice_customer] ---    
            
        // --- الحالة الافتراضية (idle) - البحث عن الأوامر الرئيسية ---
        case 'idle':
        default:
            // --- أمر /start ---
            if (mb_strpos($text, '/start') === 0) {
                sendMessage($chat_id, "مرحباً بك في BizFlow! أنا جاهز لاستقبال أوامرك.\nالأوامر المتاحة:\n- إضافة عميل\n- إضافة فاتورة جديدة");
            
            // --- أمر إضافة عميل ---
            } elseif (mb_strpos($text, 'عميل') !== false) {
                 // التحقق إذا كان العميل مسجل بالفعل
                 $stmt = $db_connection->prepare("SELECT customer_id FROM customers WHERE telegram_chat_id = :chat_id");
                 $stmt->execute(['chat_id' => $chat_id]);
                 if ($stmt->fetch()) {
                     sendMessage($chat_id, "ℹ️ أنت مسجل بالفعل كعميل. هل تريد تعديل بياناتك؟ (لم تتم برمجة هذه الميزة بعد)");
                     // يمكن إضافة حالة 'awaiting_update_decision' هنا
                 } else {
                    // ابدأ عملية إضافة العميل
                    // Ensure customer record exists before updating state (Though technically done by ensureCustomerRecord now)
                    ensureCustomerRecord($db_connection, $chat_id); // تأكد من وجود سجل ولو فارغ
                    updateUserState($db_connection, $chat_id, 'awaiting_customer_first_name');
                    clearPendingData($db_connection, $chat_id); // مسح أي بيانات قديمة
                    sendMessage($chat_id, "📝 حسنًا، لنبدأ بإضافة عميل جديد. من فضلك أدخل الاسم الأول للعميل:");
                 }

            // --- أمر إضافة فاتورة جديدة ---
            } elseif (mb_strpos($text, 'فاتورة') !== false && mb_strpos($text, 'جديدة') !== false) {
                 // !! [للتطوير المستقبلي]: يجب بدء عملية إضافة الفاتورة هنا !!
                 // updateUserState($db_connection, $chat_id, 'awaiting_invoice_customer_selection');
                 // sendMessage($chat_id, "لمن تريد إصدار الفاتورة؟ (اعرض قائمة العملاء)");
                 sendMessage($chat_id, "🚧 ميزة إضافة الفاتورة التفاعلية قيد التطوير. حاليًا، يمكنك إضافتها بشكل افتراضي.");
                 
                 // --- كود إضافة الفاتورة الافتراضية (للاختبار) ---
                 try {
                     // تأكد أن العميل رقم 1 موجود أولاً
                     $checkCustomerStmt = $db_connection->prepare("SELECT customer_id FROM customers WHERE customer_id = 1");
                     $checkCustomerStmt->execute();
                     if (!$checkCustomerStmt->fetch()) {
                         sendMessage($chat_id, "⚠️ لا يمكن إضافة فاتورة افتراضية لأن العميل رقم 1 غير موجود. يرجى إضافة عميل أولاً.");
                     } else {
                         $customer_id = 1; 
                         $amount = 150.00; 
                         $due_date = date('Y-m-d', strtotime('+30 days'));
                         $sql = "INSERT INTO invoices (customer_id, amount, status, due_date) VALUES (:customer_id, :amount, 'pending', :due_date)";
                         $stmt = $db_connection->prepare($sql);
                         $stmt->execute(['customer_id' => $customer_id, 'amount' => $amount, 'due_date' => $due_date]);
                         sendMessage($chat_id, "✅ تمت إضافة فاتورة افتراضية للعميل 1 بنجاح!");
                     }
                 } catch (PDOException $e) {
                     sendMessage($chat_id, "⚠️ حدث خطأ أثناء إضافة الفاتورة الافتراضية.");
                     error_log("webhook.php - PDO Error adding default invoice: " . $e->getMessage()); 
                 }
                 // --- نهاية كود الفاتورة الافتراضية ---

            // --- أمر غير مفهوم ---
            } else {
                sendMessage($chat_id, "❓ أمر غير مفهوم. الأوامر المتاحة:\n- /start\n- إضافة عميل\n- إضافة فاتورة جديدة");
            }
            break; // نهاية حالة idle
            
    } // نهاية switch

} catch (Throwable $t) { // التقاط أي خطأ فادح غير متوقع
    // نحاول إرسال رسالة خطأ إذا أمكن
    if ($chat_id) {
       @sendMessage($chat_id, "⚠️ حدث خطأ عام غير متوقع في النظام. تم إبلاغ المسؤولين.");
    }
    // تسجيل الخطأ دائمًا
    error_log("webhook.php - Unexpected Throwable: " . $t->getMessage() . "\n" . $t->getTraceAsString());
}

// ===================================================
// [5. دوال مساعدة (Helper Functions)]
// ===================================================

/**
 * يرسل رسالة إلى مستخدم تلغرام.
 */
function sendMessage($chat_id, $message) {
    global $db_connection; // للوصول إلى الاتصال بقاعدة البيانات إذا لزم الأمر
    try {
        $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/sendMessage";
        $data = [
            'chat_id' => $chat_id,
            'text' => $message,
            'parse_mode' => 'HTML' // يسمح باستخدام بعض تنسيقات HTML البسيطة مثل <b> و <i>
        ];
        
        $options = [
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => http_build_query($data),
                'ignore_errors' => true // مهم حتى نتمكن من تسجيل الأخطاء إذا فشل الإرسال
            ],
             'ssl' => [ // قد تحتاج هذه الخيارات إذا كان هناك مشاكل SSL على السيرفر
                 'verify_peer' => false,
                 'verify_peer_name' => false,
            ]
        ];
        $context  = stream_context_create($options);
        $result = @file_get_contents($url, false, $context); // استخدام @ لمنع ظهور أخطاء PHP مباشرة

        // تسجيل إذا فشل الإرسال أو إذا لم تكن الاستجابة 200 OK
        if ($result === FALSE) {
            error_log("sendMessage failed to chat_id: $chat_id. Could not connect or read from URL: $url");
        } elseif (isset($http_response_header) && strpos($http_response_header[0], '200 OK') === false) {
             error_log("sendMessage returned non-200 status for chat_id: $chat_id. Status: {$http_response_header[0]}. Response: $result");
        }

    } catch (Throwable $t) {
        error_log("sendMessage - Unexpected Throwable: " . $t->getMessage());
    }
}

/**
 * ينشئ سجل عميل إذا لم يكن موجودًا (ضروري لتحديث الحالة أو البيانات المؤقتة).
 * يستخدم ON CONFLICT لتجنب الأخطاء إذا كان المستخدم موجودًا بالفعل.
 */
function ensureCustomerRecord($db, $chat_id) {
    try {
        // ON CONFLICT DO NOTHING يعني أنه إذا كان موجودًا، لا تفعل شيئًا
        $stmt = $db->prepare("INSERT INTO customers (telegram_chat_id, first_name, last_name, state) 
                               VALUES (:chat_id, 'Unknown', 'User', 'idle') 
                               ON CONFLICT (telegram_chat_id) DO NOTHING");
        $stmt->execute(['chat_id' => $chat_id]);
    } catch (PDOException $e) {
         error_log("ensureCustomerRecord failed for chat_id $chat_id: " . $e->getMessage());
         // Consider if we should throw or handle this differently
    }
}


/**
 * يحدث حالة المستخدم في جدول customers.
 * !!! يتطلب أن يكون سجل العميل موجودًا (يتم ضمانه بواسطة ensureCustomerRecord) !!!
 */
function updateUserState($db, $chat_id, $new_state) {
    try {
        // Ensure record exists before attempting update
        ensureCustomerRecord($db, $chat_id); 
        
        $stmt = $db->prepare("UPDATE customers SET state = :state WHERE telegram_chat_id = :chat_id");
        $stmt->execute(['state' => $new_state, 'chat_id' => $chat_id]);
    } catch (PDOException $e) {
        error_log("updateUserState failed for chat_id $chat_id: " . $e->getMessage());
        // Consider sending an error message to the user or admin
    }
}

/**
 * يحفظ أو يحدث البيانات المؤقتة للمستخدم في جدول pending_data.
 * يستخدم ON CONFLICT لتجنب الأخطاء إذا كان المستخدم لديه بيانات مؤقتة بالفعل.
 */
function updatePendingData($db, $chat_id, $data_array) {
    try {
        $json_data = json_encode($data_array);
        if ($json_data === false) {
             error_log("updatePendingData failed for chat_id $chat_id: Failed to encode data to JSON.");
             return; // لا يمكن المتابعة إذا فشل الترميز
        }
        
        // تأكد من وجود العميل أولاً قبل محاولة الإضافة أو التحديث في pending_data
        ensureCustomerRecord($db, $chat_id); 
        
        $stmt = $db->prepare("INSERT INTO pending_data (telegram_chat_id, data) VALUES (:chat_id, :data) 
                                ON CONFLICT (telegram_chat_id) DO UPDATE SET data = EXCLUDED.data");
        $stmt->execute(['chat_id' => $chat_id, 'data' => $json_data]);
    } catch (PDOException $e) {
        error_log("updatePendingData failed for chat_id $chat_id: " . $e->getMessage());
        // Consider sending an error message to the user or admin
    }
}

/**
 * يحذف البيانات المؤقتة للمستخدم من جدول pending_data (عند إكمال أو إلغاء عملية).
 */
function clearPendingData($db, $chat_id) {
     try {
        $stmt = $db->prepare("DELETE FROM pending_data WHERE telegram_chat_id = :chat_id");
        $stmt->execute(['chat_id' => $chat_id]);
    } catch (PDOException $e) {
        error_log("clearPendingData failed for chat_id $chat_id: " . $e->getMessage());
        // This is less critical, but should be logged
    }
}

?>

