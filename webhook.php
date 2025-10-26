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
    // [3. قراءة الرسالة المستلمة من تلغرام]
    // ===================================================
    $update = file_get_contents('php://input');
    // سنحتفظ بهذا مؤقتًا للمساعدة في التصحيح إذا لزم الأمر
    file_put_contents('debug.txt', date('[Y-m-d H:i:s] ') . $update . PHP_EOL, FILE_APPEND); 

    $data = json_decode($update, true);

    if (isset($data['message']['text'])) {
        $chat_id = $data['message']['chat']['id'];
        $text = $data['message']['text'];
    } else {
        // إذا لم تكن رسالة نصية (مثل صورة أو ملصق)، قم بالخروج بصمت
        exit();
    }

    // ===================================================
    // [4. المنطق الرئيسي للبوت (الرد على الأوامر)]
    // !! استخدام "mb_strpos" للتعامل مع اللغة العربية بشكل أفضل !!
    // ===================================================

    try { // وضع كل المنطق داخل try لضمان التقاط أي خطأ غير متوقع

        // --- أمر إضافة فاتورة جديدة ---
        if (mb_strpos($text, 'فاتورة') !== false && mb_strpos($text, 'جديدة') !== false) {
            
            // --- هذا هو الكود لإضافة فاتورة ---
            try {
                // !! [للتطوير المستقبلي]: يجب الحصول على هذه المعلومات من المستخدم !!
                // !! حاليًا، يفترض أن العميل رقم 1 موجود !!
                $customer_id = 1; 
                $amount = 150.00; // مبلغ افتراضي
                $due_date = date('Y-m-d', strtotime('+30 days')); // تاريخ الاستحقاق بعد 30 يوم
                
                $sql = "INSERT INTO invoices (customer_id, amount, status, due_date) VALUES (:customer_id, :amount, 'pending', :due_date)";
                $stmt = $db_connection->prepare($sql);
                $stmt->execute([
                    'customer_id' => $customer_id,
                    'amount' => $amount,
                    'due_date' => $due_date
                ]);
                
                sendMessage($chat_id, "✅ تمت إضافة الفاتورة بنجاح!");
                
            } catch (PDOException $e) {
                sendMessage($chat_id, "⚠️ حدث خطأ أثناء محاولة إضافة الفاتورة. يرجى المحاولة مرة أخرى أو الاتصال بالدعم.");
                // تسجيل الخطأ المفصل في السجل الخاص بنا (وليس للمستخدم)
                error_log("webhook.php - PDO Error adding invoice: " . $e->getMessage()); 
            }

        // --- أمر إضافة عميل ---
        } elseif (mb_strpos($text, 'عميل') !== false) {
            
            // --- هذا هو الكود لإضافة عميل ---
            try {
                // !! [للتطوير المستقبلي]: يجب الحصول على هذه المعلومات من المستخدم !!
                $first_name = "مستخدم"; // اسم افتراضي
                $last_name = "جديد";   // اسم افتراضي
                $user_chat_id = $chat_id; // استخدام الـ chat_id الخاص بالشخص الذي أرسل الرسالة
                
                // ==== [هذا هو التحقق الجديد والمهم] ====
                 $checkSql = "SELECT customer_id FROM customers WHERE telegram_chat_id = :chat_id";
                 $checkStmt = $db_connection->prepare($checkSql);
                 $checkStmt->execute(['chat_id' => $user_chat_id]);
                 
                 if ($checkStmt->fetch()) {
                     // إذا وجد العميل
                     sendMessage($chat_id, "ℹ️ أنت مسجل بالفعل كعميل."); 
                 } else {
                     // إذا لم يجد العميل، قم بالإضافة
                     $sql = "INSERT INTO customers (first_name, last_name, telegram_chat_id) VALUES (:first, :last, :chat_id)";
                     $stmt = $db_connection->prepare($sql);
                     $stmt->execute(['first' => $first_name, 'last' => $last_name, 'chat_id' => $user_chat_id]);
                     
                     // الحصول على الـ ID الخاص بالعميل الجديد (مفيد)
                     $new_customer_id = $db_connection->lastInsertId(); 
                     
                     sendMessage($chat_id, "✅ تمت إضافتك كعميل جديد برقم: " . $new_customer_id);
                 }
                // ==== [نهاية التحقق الجديد] ====
                
            } catch (PDOException $e) {
                sendMessage($chat_id, "⚠️ حدث خطأ أثناء محاولة إضافة العميل. يرجى المحاولة مرة أخرى أو الاتصال بالدعم.");
                // تسجيل الخطأ المفصل في السجل الخاص بنا
                error_log("webhook.php - PDO Error adding customer: " . $e->getMessage()); 
            }

        // --- أمر /start ---
        } elseif (mb_strpos($text, '/start') === 0) { // التأكد أنها تبدأ بـ /start
            sendMessage($chat_id, "مرحباً بك في BizFlow! أنا جاهز لاستقبال أوامرك.\nالأوامر المتاحة:\n- إضافة عميل\n- إضافة فاتورة جديدة");

        // --- أمر غير مفهوم ---
        } else {
            sendMessage($chat_id, "❓ أمر غير مفهوم. الأوامر المتاحة:\n- /start\n- إضافة عميل\n- إضافة فاتورة جديدة");
        }

    } catch (Throwable $t) { // التقاط أي خطأ فادح آخر لم نكن نتوقعه
        sendMessage($chat_id, "⚠️ حدث خطأ عام غير متوقع. تم إبلاغ المسؤولين.");
        error_log("webhook.php - Unexpected Throwable: " . $t->getMessage() . "\n" . $t->getTraceAsString());
    }


    // ===================================================
    // [5. دالة إرسال الرسائل إلى تلغرام]
    // ===================================================
    function sendMessage($chat_id, $message) {
        // نستخدم التوكن الذي تم تعريفه في config.php
        $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/sendMessage";
        $data = [
            'chat_id' => $chat_id,
            'text' => $message,
            'parse_mode' => 'HTML' // السماح بتنسيق بسيط للنص (مثل Bold)
        ];
        
        $options = [
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => http_build_query($data),
                'ignore_errors' => true // مهم لكي لا يتوقف الكود إذا فشل الإرسال
            ],
        ];
        $context  = stream_context_create($options);
        @file_get_contents($url, false, $context); // استخدام @ لإخفاء أي تحذيرات إذا فشل الاتصال بتلغرام
    }

    ?>
