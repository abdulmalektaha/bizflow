<?php
// ===================================================
// [1. إعدادات الأمان وتسجيل الأخطاء والوقت]
// ===================================================
ini_set('display_errors', 0); // لا تعرض الأخطاء
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
// [جديد] ملف سجل خاص بـ run_tasks
ini_set('error_log', '/var/www/html/php_errors.log'); // سجل الأخطاء الفادحة العام
$log_file = '/var/www/html/run_tasks.log'; // ملف لتسجيل خطوات التنفيذ

// تحديد المنطقة الزمنية (مثال: كوالالمبور) - مهم للتواريخ
date_default_timezone_set('Asia/Kuala_Lumpur');

// دالة بسيطة للتسجيل في الملف
function logMessage($message) {
    global $log_file;
    $timestamp = date('[Y-m-d H:i:s T]');
    file_put_contents($log_file, $timestamp . ' ' . $message . PHP_EOL, FILE_APPEND);
}

// ===================================================
// [2. جلب الإعدادات والاتصال بقاعدة البيانات]
// ===================================================
require_once 'config.php'; // يجلب $db_connection والتوكن

logMessage("--- بدء تشغيل مهمة run_tasks.php ---");

// التحقق من اتصال قاعدة البيانات
if (!$db_connection) {
     logMessage("!! خطأ فادح: فشل الاتصال بقاعدة البيانات. توقف التنفيذ.");
     error_log("run_tasks.php - Database connection not established in config.php");
     exit(1); // الخروج مع رمز خطأ
}

// ===================================================
// [3. المهام المجدولة]
// ===================================================

// --- المهمة الأولى: تنبيهات العملاء بالفواتير المستحقة غداً ---
logMessage("المهمة 1: البحث عن فواتير مستحقة غداً...");
try {
    $tomorrow_date = date('Y-m-d', strtotime('+1 day'));
    // [تحديث] استخدام first_name, last_name, telegram_chat_id
    $sql_customers = "SELECT i.amount, i.due_date, c.first_name, c.last_name, c.telegram_chat_id 
                      FROM invoices AS i 
                      JOIN customers AS c ON i.customer_id = c.customer_id 
                      WHERE i.status = 'pending' AND i.due_date = :due_date AND c.telegram_chat_id IS NOT NULL"; // التأكد من وجود chat_id
    $stmt_customers = $db_connection->prepare($sql_customers);
    $stmt_customers->execute([':due_date' => $tomorrow_date]);
    $due_invoices = $stmt_customers->fetchAll(PDO::FETCH_ASSOC);

    if (count($due_invoices) > 0) {
        logMessage("تم العثور على " . count($due_invoices) . " فاتورة مستحقة غداً.");
        foreach($due_invoices as $row) {
            $customer_name = trim($row['first_name'] . ' ' . $row['last_name']);
            $message = "مرحباً " . $customer_name . "،\n\nنود تذكيرك بأن لديك فاتورة مستحقة غداً بتاريخ " . $row['due_date'] . ".\nالمبلغ المستحق: " . number_format($row['amount'], 2) . " ريال.\n\nشكراً لتعاونك.";
            
            sendTelegramMessage($row['telegram_chat_id'], $message);
            logMessage("✔️ تم إرسال تنبيه إلى العميل: " . $customer_name . " (Chat ID: " . $row['telegram_chat_id'] . ")");
            sleep(1); // انتظار ثانية بين الرسائل لتجنب حظر تيليجرام
        }
    } else {
        logMessage("لا توجد فواتير مستحقة غداً.");
    }
} catch (PDOException $e) {
    logMessage("!! خطأ PDO في المهمة 1: " . $e->getMessage());
    error_log("run_tasks.php - Task 1 PDO Error: " . $e->getMessage());
} catch (Exception $e) {
    logMessage("!! خطأ عام في المهمة 1: " . $e->getMessage());
    error_log("run_tasks.php - Task 1 General Error: " . $e->getMessage());
}

/*
// --- المهمة الثانية: تنبيهات الموظفين بالفواتير المتأخرة ---
// !! [معطلة مؤقتًا] تحتاج إلى جدول employees وعمود employee_id في invoices !!
logMessage("المهمة 2: البحث عن فواتير متأخرة (تنبيه الموظفين)... [معطلة حاليًا]");
/*
try {
    $yesterday_date = date('Y-m-d', strtotime('-1 day'));
    // [تحتاج تعديل] يتطلب جدول employees وعمود i.employee_id
    $sql_employees = "SELECT i.amount, c.first_name, c.last_name, e.full_name AS employee_name, e.telegram_id AS employee_telegram_id 
                      FROM invoices AS i 
                      JOIN customers AS c ON i.customer_id = c.customer_id 
                      JOIN employees AS e ON i.employee_id = e.employee_id 
                      WHERE i.status = 'pending' AND i.due_date = :due_date AND e.telegram_id IS NOT NULL";
    $stmt_employees = $db_connection->prepare($sql_employees);
    $stmt_employees->execute([':due_date' => $yesterday_date]);
    $overdue_invoices = $stmt_employees->fetchAll(PDO::FETCH_ASSOC);

    if (count($overdue_invoices) > 0) {
        logMessage("تم العثور على " . count($overdue_invoices) . " فاتورة متأخرة.");
        foreach($overdue_invoices as $row) {
             $customer_name = trim($row['first_name'] . ' ' . $row['last_name']);
            $message = "⚠️ تنبيه تأخر سداد ⚠️\n\nمرحباً " . $row['employee_name'] . "،\nفاتورة العميل ( " . $customer_name . " ) بمبلغ " . number_format($row['amount'], 2) . " ريال قد تجاوزت تاريخ الاستحقاق.\n\nيرجى المتابعة.";
            sendTelegramMessage($row['employee_telegram_id'], $message);
            logMessage("✔️ تم إرسال تنبيه إلى الموظف: " . $row['employee_name'] . " بخصوص العميل: " . $customer_name);
            sleep(1); 
        }
    } else {
        logMessage("لا توجد فواتير متأخرة من الأمس.");
    }
} catch (PDOException $e) {
    logMessage("!! خطأ PDO في المهمة 2: " . $e->getMessage());
    error_log("run_tasks.php - Task 2 PDO Error: " . $e->getMessage());
} catch (Exception $e) {
    logMessage("!! خطأ عام في المهمة 2: " . $e->getMessage());
    error_log("run_tasks.php - Task 2 General Error: " . $e->getMessage());
}
*/


// --- المهمة الثالثة: التقرير اليومي للإدارة ---
logMessage("المهمة 3: إعداد التقرير اليومي للإدارة...");
try {
    $today_date = date('Y-m-d');
    
    // [تحديث] استخدام Prepared Statement لعدد الفواتير الجديدة
    $sql_new_invoices = "SELECT COUNT(*) FROM invoices WHERE DATE(creation_date) = :today";
    $stmt_new = $db_connection->prepare($sql_new_invoices);
    $stmt_new->execute([':today' => $today_date]);
    $new_invoices_count = $stmt_new->fetchColumn();

    // عدد ومجموع الفواتير قيد الانتظار (هذا صحيح)
    $pending_data_stmt = $db_connection->query("SELECT COUNT(*) as count, SUM(amount) as total FROM invoices WHERE status = 'pending'");
    $pending_data = $pending_data_stmt->fetch(PDO::FETCH_ASSOC);
    $pending_invoices_count = $pending_data['count'] ?? 0;
    $pending_invoices_total = number_format($pending_data['total'] ?? 0, 2);

    $report_message = "📊 **التقرير اليومي لـ BizFlow** 📊\n\n"
                    . "🗓️ ليوم: " . $today_date . "\n\n"
                    . "✉️ فواتير جديدة أُنشئت اليوم: **" . $new_invoices_count . "**\n"
                    . "⏳ إجمالي الفواتير قيد الانتظار: **" . $pending_invoices_count . "**\n"
                    . "💰 إجمالي المبالغ المستحقة: **" . $pending_invoices_total . " ريال**\n\n"
                    . "يوم عمل موفق!";
                    
    // التأكد من وجود MANAGEMENT_CHAT_ID قبل الإرسال
    if (defined('MANAGEMENT_CHAT_ID') && MANAGEMENT_CHAT_ID != 'YOUR_CHAT_ID_HERE' && !empty(MANAGEMENT_CHAT_ID)) {
        sendTelegramMessage(MANAGEMENT_CHAT_ID, $report_message, 'Markdown'); // استخدام دالة الإرسال
        logMessage("✔️ تم إرسال التقرير اليومي إلى الإدارة (Chat ID: " . MANAGEMENT_CHAT_ID . ").");
    } else {
         logMessage("⚠️ لم يتم إرسال التقرير اليومي: MANAGEMENT_CHAT_ID غير معرف أو غير صحيح في config.php.");
         error_log("run_tasks.php - MANAGEMENT_CHAT_ID not set or invalid in config.php");
    }
    
} catch (PDOException $e) {
    logMessage("!! خطأ PDO في المهمة 3: " . $e->getMessage());
    error_log("run_tasks.php - Task 3 PDO Error: " . $e->getMessage());
} catch (Exception $e) {
    logMessage("!! خطأ عام في المهمة 3: " . $e->getMessage());
    error_log("run_tasks.php - Task 3 General Error: " . $e->getMessage());
}

logMessage("--- انتهت مهمة run_tasks.php ---");

// ===================================================
// [6. دالة مساعدة لإرسال الرسائل (مكررة من webhook.php)]
// ===================================================
// [جديد] تم تحويلها لدالة منفصلة لتجنب تكرار الكود
function sendTelegramMessage($chat_id, $message, $parse_mode = 'HTML') {
    if (!defined('TELEGRAM_BOT_TOKEN') || TELEGRAM_BOT_TOKEN == 'YOUR_TELEGRAM_BOT_TOKEN_HERE' || empty(TELEGRAM_BOT_TOKEN)) {
        logMessage("!! خطأ: TELEGRAM_BOT_TOKEN غير معرف أو غير صحيح في config.php.");
        error_log("run_tasks.php - TELEGRAM_BOT_TOKEN not set or invalid.");
        return false; // لا يمكن الإرسال بدون توكن
    }
     if (empty($chat_id)) {
        logMessage("!! خطأ: محاولة إرسال رسالة إلى chat_id فارغ.");
        error_log("run_tasks.php - Attempted to send message with empty chat_id.");
        return false; 
    }

    try {
        $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/sendMessage";
        $data = [
            'chat_id' => $chat_id,
            'text' => $message,
            'parse_mode' => $parse_mode
        ];
        
        $options = [
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => http_build_query($data),
                'ignore_errors' => true,
                'timeout' => 10 // [جديد] إضافة مهلة زمنية للطلب
            ],
             'ssl' => [ 
                 'verify_peer' => false,
                 'verify_peer_name' => false,
            ]
        ];
        $context  = stream_context_create($options);
        $result = @file_get_contents($url, false, $context); 

        if ($result === FALSE) {
            logMessage("!! فشل إرسال الرسالة إلى $chat_id. تعذر الاتصال أو القراءة من URL: $url");
            error_log("sendTelegramMessage failed to chat_id: $chat_id. Could not connect or read from URL: $url");
            return false;
        } elseif (isset($http_response_header) && strpos($http_response_header[0], '200 OK') === false) {
             logMessage("!! فشل إرسال الرسالة إلى $chat_id. حالة غير متوقعة: {$http_response_header[0]}. الاستجابة: $result");
             error_log("sendTelegramMessage returned non-200 status for chat_id: $chat_id. Status: {$http_response_header[0]}. Response: $result");
             return false;
        }
        return true; // تم الإرسال بنجاح (أو على الأقل لم يحدث خطأ)

    } catch (Throwable $t) {
        logMessage("!! خطأ غير متوقع في دالة sendTelegramMessage: " . $t->getMessage());
        error_log("sendTelegramMessage - Unexpected Throwable: " . $t->getMessage());
        return false;
    }
}
?>
