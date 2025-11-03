<?php
// [1. بدء الجلسة والاتصال]
// يجب أن يكون session_start() في config.php هو السطر الأول
require_once 'config.php';

// [2. إعدادات تسجيل الأخطاء (للأمان)]
// إيقاف عرض الأخطاء للمستخدم
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
// تفعيل تسجيل الأخطاء في ملف
ini_set('log_errors', 1);
// تحديد ملف السجل (تأكد أن Apache لديه صلاحية الكتابة عليه)
ini_set('error_log', '/var/www/html/php_errors.log'); 

// [3. دالة لإرسال الرسائل إلى تيليجرام]
// تم تحديثها لتدعم الأزرار (Inline Keyboard)
function sendMessage($chat_id, $text, $keyboard = null) {
    $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/sendMessage";
    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'Markdown' // السماح ببعض التنسيقات مثل *bold*
    ];
    if ($keyboard) {
        $data['reply_markup'] = json_encode(['inline_keyboard' => $keyboard]);
    }
    
    $options = [
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data),
        ],
    ];
    $context  = stream_context_create($options);
    file_get_contents($url, false, $context);
}

// [4. دالة لتسجيل الأخطاء]
function logError($message) {
    $timestamp = date("Y-m-d H:i:s");
    $log_message = "[$timestamp] webhook.php - $message" . PHP_EOL;
    file_put_contents('/var/www/html/php_errors.log', $log_message, FILE_APPEND);
}

// [5. دالة لإدارة البيانات المؤقتة]
function setPendingData($chat_id, $data) {
    global $db_connection;
    $json_data = json_encode($data);
    $sql = "INSERT INTO pending_data (telegram_chat_id, data) VALUES (:chat_id, :data)
            ON CONFLICT (telegram_chat_id) DO UPDATE SET data = :data";
    $stmt = $db_connection->prepare($sql);
    $stmt->execute(['chat_id' => $chat_id, 'data' => $json_data]);
}

function getPendingData($chat_id) {
    global $db_connection;
    $sql = "SELECT data FROM pending_data WHERE telegram_chat_id = :chat_id";
    $stmt = $db_connection->prepare($sql);
    $stmt->execute(['chat_id' => $chat_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? json_decode($result['data'], true) : null;
}

function clearPendingData($chat_id) {
    global $db_connection;
    $sql = "DELETE FROM pending_data WHERE telegram_chat_id = :chat_id";
    $stmt = $db_connection->prepare($sql);
    $stmt->execute(['chat_id' => $chat_id]);
}

// [6. دالة لتحديث حالة المستخدم]
function setUserState($user_id, $state) {
    global $db_connection;
    try {
        $sql = "UPDATE users SET conversation_state = :state WHERE user_id = :user_id";
        $stmt = $db_connection->prepare($sql);
        $stmt->execute(['state' => $state, 'user_id' => $user_id]);
    } catch (PDOException $e) {
        logError("Failed to update state for user $user_id: " . $e->getMessage());
    }
}


// ===================================================
// [A. المعالجة الرئيسية للـ Webhook]
// ===================================================

// جلب التحديث (الرسالة) من تيليجرام
$update = file_get_contents('php://input');
$update_data = json_decode($update, true);

// تسجيل كل تحديث (للتصحيح إذا احتجنا)
// file_put_contents('debug.txt', $update . PHP_EOL, FILE_APPEND);

// تحديد نوع التحديث (رسالة عادية أو ضغطة زر)
if (isset($update_data['callback_query'])) {
    // === [B1. التعامل مع ضغطات الأزرار (Callback Query)] ===
    
    $callback_query = $update_data['callback_query'];
    $chat_id = $callback_query['message']['chat']['id'];
    $callback_data = $callback_query['data']; // هذا ما أرسلناه في الزر (مثال: 'select_customer_1')
    
    // تسجيل ضغطة الزر في السجل
    logError("Callback query received from $chat_id: $callback_data");

    // البحث عن المستخدم (الشركة) المرتبط بهذا الحساب
    $user_sql = "SELECT user_id, conversation_state FROM users WHERE telegram_chat_id = :chat_id";
    $user_stmt = $db_connection->prepare($user_sql);
    $user_stmt->execute(['chat_id' => $chat_id]);
    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        sendMessage($chat_id, "⚠️ حسابك غير مربوط. يرجى الذهاب إلى لوحة التحكم (`https://bizflow.systems/account.php`) وربط حسابك أولاً.");
        exit();
    }
    
    $current_user_id = $user['user_id'];
    $user_state = $user['conversation_state'];

    try {
        // التحقق من نوع ضغطة الزر
        if (strpos($callback_data, 'select_customer_') === 0) {
            // المستخدم اختار عميلاً لإضافة فاتورة
            
            // التأكد من أننا كنا نتوقع هذا الاختيار
            if ($user_state == 'awaiting_invoice_customer_id') {
                $customer_id = str_replace('select_customer_', '', $callback_data);
                
                // تخزين العميل المختار في البيانات المؤقتة
                setPendingData($chat_id, ['customer_id' => $customer_id]);
                
                // الانتقال إلى الحالة التالية: طلب المبلغ
                setUserState($current_user_id, 'awaiting_invoice_amount');
                sendMessage($chat_id, "💰 ممتاز (تم اختيار العميل #$customer_id). الآن، من فضلك أدخل مبلغ الفاتورة (أرقام فقط):");
            } else {
                sendMessage($chat_id, "❓ ضغطة زر غير متوقعة. تم إلغاء الأمر.");
                setUserState($current_user_id, 'idle');
            }
        
        } elseif ($callback_data == 'cancel_action') {
            // المستخدم ضغط "إلغاء"
            setUserState($current_user_id, 'idle');
            clearPendingData($chat_id);
            sendMessage($chat_id, "👍 تم إلغاء الأمر بنجاح.");
        }

    } catch (PDOException $e) {
        logError("PDO Error on Callback Query: " . $e->getMessage());
        sendMessage($chat_id, "⚠️ حدث خطأ أثناء معالجة طلبك المتعلق بقاعدة البيانات.");
        setUserState($current_user_id, 'idle');
        clearPendingData($chat_id);
    }
    
} elseif (isset($update_data['message'])) {
    // === [B2. التعامل مع الرسائل النصية العادية] ===
    
    $message = $update_data['message'];
    $chat_id = $message['chat']['id'];
    $text = $message['text'];
    $first_name = $message['from']['first_name'];
    
    // البحث عن المستخدم (الشركة) المرتبط بهذا الحساب
    // كل تفاعل يعتمد على إيجاد المستخدم أولاً
    $user_sql = "SELECT user_id, company_name, conversation_state, telegram_link_code FROM users WHERE telegram_chat_id = :chat_id";
    $user_stmt = $db_connection->prepare($user_sql);
    $user_stmt->execute(['chat_id' => $chat_id]);
    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);

    // --- [1. التحقق من ربط الحساب أو أمر /link] ---
    if (!$user) {
        // المستخدم غير مربوط. هل يحاول الربط؟
        if (strpos($text, '/link ') === 0) {
            $link_code = trim(str_replace('/link ', '', $text));
            if (empty($link_code)) {
                sendMessage($chat_id, "❌ صيغة الأمر خاطئة. يرجى إرسال الأمر هكذا: `/link BZF-XYZ123`");
                exit();
            }

            // البحث عن الرمز في جدول users
            $link_sql = "SELECT user_id, company_name FROM users WHERE telegram_link_code = :link_code AND telegram_chat_id IS NULL";
            $link_stmt = $db_connection->prepare($link_sql);
            $link_stmt->execute(['link_code' => $link_code]);
            $account_to_link = $link_stmt->fetch(PDO::FETCH_ASSOC);

            if ($account_to_link) {
                // وجدنا الحساب والرمز صحيح وغير مستخدم
                $user_id_to_link = $account_to_link['user_id'];
                $company_name = $account_to_link['company_name'];
                
                // ربط الحساب: تحديث telegram_chat_id وإزالة الرمز (للا استخدام مرة واحدة)
                $update_sql = "UPDATE users SET telegram_chat_id = :chat_id, telegram_link_code = NULL WHERE user_id = :user_id";
                $update_stmt = $db_connection->prepare($update_sql);
                $update_stmt->execute(['chat_id' => $chat_id, 'user_id' => $user_id_to_link]);
                
                sendMessage($chat_id, "✅ تم ربط حسابك في BizFlow (" . htmlspecialchars($company_name) . ") بنجاح! \n\nيمكنك الآن البدء بإدارة عملائك وفواتيرك.");
            } else {
                // الرمز خاطئ أو تم استخدامه
                sendMessage($chat_id, "❌ رمز الربط غير صالح أو تم استخدامه من قبل. يرجى التحقق من الرمز في صفحة 'حسابي' على الموقع.");
            }
        } else {
            // المستخدم غير مربوط ولم يرسل أمر /link
            sendMessage($chat_id, "👋 مرحبًا $first_name! \n\nيبدو أن حساب تيليجرام هذا غير مربوط بأي حساب BizFlow. \n\nالرجاء تسجيل الدخول إلى حسابك على `https://bizflow.systems` ثم اذهب إلى صفحة 'حسابي' (`account.php`) للحصول على رمز الربط الخاص بك، ثم أرسله لي هكذا: \n\n`/link BZF-XYZ123`");
        }
        exit(); // إيقاف التنفيذ لأن المستخدم غير مصرح له
    }

    // --- [2. المستخدم مربوط - معالجة الطلبات العادية] ---
    $current_user_id = $user['user_id'];
    $user_state = $user['conversation_state']; // الحالة الحالية للمحادثة
    
    try {
        // استخدام switch للتحكم في حالة المحادثة
        switch ($user_state) {
            
            // === [CASE: awaiting_customer_first_name] ===
            case 'awaiting_customer_first_name':
                // المستخدم أرسل الاسم الأول
                $first_name_input = trim($text);
                setPendingData($chat_id, ['first_name' => $first_name_input]); // تخزين الاسم الأول مؤقتًا
                setUserState($current_user_id, 'awaiting_customer_last_name'); // الانتقال للحالة التالية
                sendMessage($chat_id, "👍 الاسم الأول '" . htmlspecialchars($first_name_input) . "' تم حفظه. \nالآن، من فضلك أدخل الاسم الأخير للعميل:");
                break;
                
            // === [CASE: awaiting_customer_last_name] ===
            case 'awaiting_customer_last_name':
                // المستخدم أرسل الاسم الأخير
                $last_name_input = trim($text);
                $pending_data = getPendingData($chat_id);
                $pending_data['last_name'] = $last_name_input; // إضافة الاسم الأخير للبيانات المؤقتة
                setPendingData($chat_id, $pending_data);
                setUserState($current_user_id, 'awaiting_customer_email'); // الانتقال للحالة التالية
                sendMessage($chat_id, "📧 ممتاز. \nأخيرًا، من فضلك أدخل البريد الإلكتروني للعميل (أو أرسل 'تخطي' إذا لم يكن متوفرًا):");
                break;

            // === [CASE: awaiting_customer_email] ===
            case 'awaiting_customer_email':
                // المستخدم أرسل الإيميل
                $email_input = (trim(mb_strtolower($text)) == 'تخطي') ? null : trim($text);
                $pending_data = getPendingData($chat_id);
                
                // جلب البيانات لإضافتها
                $first_name = $pending_data['first_name'];
                $last_name = $pending_data['last_name'];
                
                // إضافة العميل إلى قاعدة البيانات مرتبطًا بحساب الشركة
                $sql = "INSERT INTO customers (user_id, first_name, last_name, email, telegram_chat_id) 
                        VALUES (:user_id, :first_name, :last_name, :email, NULL)
                        ON CONFLICT (email) WHERE email IS NOT NULL DO NOTHING"; // نتجاهل إذا كان الإيميل مكررًا
                
                $stmt = $db_connection->prepare($sql);
                $stmt->execute([
                    'user_id' => $current_user_id,
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'email' => $email_input
                ]);
                
                // تنظيف الحالة والبيانات المؤقتة
                setUserState($current_user_id, 'idle');
                clearPendingData($chat_id);
                sendMessage($chat_id, "✅ تم إضافة العميل '" . htmlspecialchars($first_name) . " " . htmlspecialchars($last_name) . "' بنجاح!");
                break;

            // === [CASE: awaiting_invoice_amount] ===
            case 'awaiting_invoice_amount':
                $amount_input = trim($text);
                if (!is_numeric($amount_input) || $amount_input <= 0) {
                    sendMessage($chat_id, "❌ المبلغ غير صالح. يرجى إدخال مبلغ الفاتورة (أرقام فقط وتكون أكبر من 0):");
                    break; // البقاء في نفس الحالة
                }
                
                $pending_data = getPendingData($chat_id);
                $pending_data['amount'] = $amount_input;
                setPendingData($chat_id, $pending_data);
                setUserState($current_user_id, 'awaiting_invoice_due_date');
                sendMessage($chat_id, "📅 جيد جدًا. \nأخيرًا، من فضلك أدخل تاريخ استحقاق الفاتورة (بالصيغة YYYY-MM-DD، مثال: " . date('Y-m-d', strtotime('+30 days')) . "):");
                break;
                
            // === [CASE: awaiting_invoice_due_date] ===
            case 'awaiting_invoice_due_date':
                $date_input = trim($text);
                // التحقق من صحة صيغة التاريخ
                $date_parts = explode('-', $date_input);
                if (count($date_parts) != 3 || !checkdate($date_parts[1], $date_parts[2], $date_parts[0])) {
                    sendMessage($chat_id, "❌ صيغة التاريخ غير صحيحة. يرجى إدخاله بالصيغة YYYY-MM-DD (مثل 2025-12-31).");
                    break; // البقاء في نفس الحالة
                }

                $pending_data = getPendingData($chat_id);
                
                // جلب البيانات لإضافتها
                $customer_id = $pending_data['customer_id'];
                $amount = $pending_data['amount'];
                
                // إضافة الفاتورة إلى قاعدة البيانات مرتبطة بحساب الشركة
                $sql = "INSERT INTO invoices (user_id, customer_id, amount, due_date, status) 
                        VALUES (:user_id, :customer_id, :amount, :due_date, 'pending')";
                $stmt = $db_connection->prepare($sql);
                $stmt->execute([
                    'user_id' => $current_user_id,
                    'customer_id' => $customer_id,
                    'amount' => $amount,
                    'due_date' => $date_input
                ]);
                
                // تنظيف الحالة والبيانات المؤقتة
                setUserState($current_user_id, 'idle');
                clearPendingData($chat_id);
                sendMessage($chat_id, "✅ تمت إضافة الفاتورة بنجاح!");
                break;

            // === [CASE: idle (الحالة العادية)] ===
            case 'idle':
            default:
                if (mb_strpos($text, 'إضافة عميل') !== false) {
                    // --- أمر إضافة عميل ---
                    setUserState($current_user_id, 'awaiting_customer_first_name');
                    clearPendingData($chat_id);
                    sendMessage($chat_id, "📝 حسنًا، لنبدأ بإضافة عميل جديد. \nمن فضلك أدخل الاسم الأول للعميل:");
                
                } elseif (mb_strpos($text, 'إضافة فاتورة') !== false) {
                    // --- أمر إضافة فاتورة ---
                    
                    // 1. جلب قائمة العملاء لعرضهم كأزرار
                    $customer_sql = "SELECT customer_id, first_name, last_name FROM customers WHERE user_id = :user_id ORDER BY first_name LIMIT 10"; // جلب أول 10 عملاء
                    $customer_stmt = $db_connection->prepare($customer_sql);
                    $customer_stmt->execute(['user_id' => $current_user_id]);
                    $customers = $customer_stmt->fetchAll(PDO::FETCH_ASSOC);

                    if (empty($customers)) {
                        sendMessage($chat_id, "⚠️ ليس لديك أي عملاء مسجلين بعد. يرجى إضافة عميل أولاً باستخدام أمر 'إضافة عميل'.");
                        break;
                    }

                    $keyboard = [];
                    foreach ($customers as $customer) {
                        // كل زر يحتوي على اسم العميل، ويرسل 'select_customer_' + ID العميل
                        $keyboard[][] = [
                            'text' => htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']), 
                            'callback_data' => 'select_customer_' . $customer['customer_id']
                        ];
                    }
                    $keyboard[][] = [['text' => '❌ إلغاء الأمر', 'callback_data' => 'cancel_action']];

                    // 2. تغيير الحالة وإرسال الرسالة مع الأزرار
                    setUserState($current_user_id, 'awaiting_invoice_customer_id');
                    clearPendingData($chat_id);
                    sendMessage($chat_id, "🧾 لمن تريد إصدار هذه الفاتورة؟ \n(اختر من القائمة أدناه)", $keyboard);

                } elseif ($text == '/start') {
                    // --- أمر /start ---
                    setUserState($current_user_id, 'idle'); // التأكد من إعادة تعيين الحالة
                    clearPendingData($chat_id);
                    sendMessage($chat_id, "👋 مرحبًا بك مجددًا في BizFlow، " . htmlspecialchars($user['company_name']) . "!");
                
                } else {
                    // --- أمر غير مفهوم ---
                    sendMessage($chat_id, "❓ أمر غير مفهوم. الأوامر المتاحة حاليًا:\n- /start\n- إضافة عميل\n- إضافة فاتورة جديدة");
                }
                break;
        } // نهاية switch

    } catch (PDOException $e) {
        logError("PDO Error on Message: " . $e->getMessage());
        sendMessage($chat_id, "⚠️ حدث خطأ أثناء معالجة طلبك المتعلق بقاعدة البيانات. تم إبلاغ المسؤولين.");
        setUserState($current_user_id, 'idle');
        clearPendingData($chat_id);
    }
}
?>
