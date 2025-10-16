<?php
// جلب الإعدادات والمفاتيح السرية من ملف منفصل وآمن
require_once 'config.php';

echo "<h1>مهمة إرسال التنبيهات والتقارير</h1>";
echo "بدء تشغيل...<br>";

// --- 1. الاتصال بقاعدة البيانات باستخدام الإعدادات من config.php ---
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
$conn->set_charset("utf8");

if ($conn->connect_error) {
    die("فشل الاتصال بقاعدة البيانات: " . $conn->connect_error);
}
echo "تم الاتصال بقاعدة البيانات بنجاح.<br>";

// =================================================================
//  المهمة الأولى: إرسال تنبيهات للعملاء بالفواتير المستحقة غداً
// =================================================================
echo "<hr><h3>المهمة 1: تنبيهات العملاء</h3>";
$tomorrow_date = date('Y-m-d', strtotime('+1 day'));
echo "<b>البحث عن الفواتير المستحقة بتاريخ: " . $tomorrow_date . "</b><br>";

$sql_customers = "SELECT i.amount, i.due_date, c.full_name, c.telegram_id 
                  FROM invoices AS i
                  JOIN customers AS c ON i.customer_id = c.customer_id
                  WHERE i.status = 'pending' AND i.due_date = '$tomorrow_date'";
$result_customers = $conn->query($sql_customers);

if ($result_customers->num_rows > 0) {
    echo "تم العثور على " . $result_customers->num_rows . " فاتورة مستحقة غداً.<br>";
    while($row = $result_customers->fetch_assoc()) {
        $message = "مرحباً " . $row['full_name'] . "،\n\nنود تذكيرك بأن لديك فاتورة مستحقة غداً بتاريخ " . $row['due_date'] . ".\nالمبلغ المستحق: " . $row['amount'] . " ريال.\n\nشكراً لتعاونك.";
        $api_url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/sendMessage?chat_id=" . $row['telegram_id'] . "&text=" . urlencode($message);
        file_get_contents($api_url);
        echo "✔️ تم إرسال تنبيه إلى العميل: " . $row['full_name'] . "<br>";
    }
} else {
    echo "لا توجد فواتير مستحقة غداً.<br>";
}

// =================================================================
//  المهمة الثانية: إرسال تنبيهات للموظفين بالفواتير المتأخرة
// =================================================================
echo "<hr><h3>المهمة 2: تنبيهات الموظفين</h3>";
$yesterday_date = date('Y-m-d', strtotime('-1 day'));
echo "<b>البحث عن الفواتير التي تأخرت (تاريخ استحقاقها " . $yesterday_date . ")</b><br>";

$sql_employees = "SELECT i.amount, c.full_name AS customer_name, e.full_name AS employee_name, e.telegram_id AS employee_telegram_id
                  FROM invoices AS i
                  JOIN customers AS c ON i.customer_id = c.customer_id
                  JOIN employees AS e ON i.employee_id = e.employee_id
                  WHERE i.status = 'pending' AND i.due_date = '$yesterday_date'";
$result_employees = $conn->query($sql_employees);

if ($result_employees->num_rows > 0) {
    echo "تم العثور على " . $result_employees->num_rows . " فاتورة متأخرة.<br>";
    while($row = $result_employees->fetch_assoc()) {
        $message = "⚠️ تنبيه تأخر سداد ⚠️\n\n";
        $message .= "مرحباً " . $row['employee_name'] . "،\n";
        $message .= "فاتورة العميل ( " . $row['customer_name'] . " ) بمبلغ " . $row['amount'] . " ريال قد تجاوزت تاريخ الاستحقاق.\n\n";
        $message .= "يرجى المتابعة.";
        $api_url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/sendMessage?chat_id=" . $row['employee_telegram_id'] . "&text=" . urlencode($message);
        file_get_contents($api_url);
        echo "✔️ تم إرسال تنبيه إلى الموظف: " . $row['employee_name'] . "<br>";
    }
} else {
    echo "لا توجد فواتير متأخرة من الأمس.<br>";
}

// =================================================================
//  المهمة الثالثة: إرسال التقرير اليومي للإدارة
// =================================================================
echo "<hr><h3>المهمة 3: التقرير اليومي للإدارة</h3>";
$today_date = date('Y-m-d');

// 1. حساب عدد الفواتير الجديدة التي أنشئت اليوم
$sql_new_invoices = "SELECT COUNT(*) as count FROM invoices WHERE DATE(creation_date) = '$today_date'";
$new_invoices_count = $conn->query($sql_new_invoices)->fetch_assoc()['count'];

// 2. حساب إجمالي الفواتير غير المدفوعة ومجموع مبالغها
$sql_pending_invoices = "SELECT COUNT(*) as count, SUM(amount) as total FROM invoices WHERE status = 'pending'";
$pending_data = $conn->query($sql_pending_invoices)->fetch_assoc();
$pending_invoices_count = $pending_data['count'];
$pending_invoices_total = number_format($pending_data['total'] ?? 0, 2);

// صياغة رسالة التقرير
$report_message = "📊 **التقرير اليومي لـ BizFlow** 📊\n\n";
$report_message .= "🗓️ ليوم: " . $today_date . "\n\n";
$report_message .= "✉️ فواتير جديدة أُنشئت اليوم: **" . $new_invoices_count . "**\n";
$report_message .= "⏳ إجمالي الفواتير قيد الانتظار: **" . $pending_invoices_count . "**\n";
$report_message .= "💰 إجمالي المبالغ المستحقة: **" . $pending_invoices_total . " ريال**\n\n";
$report_message .= "يوم عمل موفق!";

// إرسال التقرير
$api_url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/sendMessage?chat_id=" . MANAGEMENT_CHAT_ID . "&text=" . urlencode($report_message) . "&parse_mode=Markdown";
file_get_contents($api_url);

echo "✔️ تم إرسال التقرير اليومي إلى الإدارة بنجاح.<br>";

// إغلاق الاتصال
$conn->close();
echo "<hr>انتهت جميع المهام.";

?>