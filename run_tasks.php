<?php
require_once 'config.php'; // يجلب $db_connection

echo "<h1>مهمة إرسال التنبيهات والتقارير</h1>";
echo "بدء تشغيل...<br>";

// --- المهمة الأولى: تنبيهات العملاء بالفواتير المستحقة غداً ---
echo "<hr><h3>المهمة 1: تنبيهات العملاء</h3>";
$tomorrow_date = date('Y-m-d', strtotime('+1 day'));
$sql_customers = "SELECT i.amount, i.due_date, c.full_name, c.telegram_id FROM invoices AS i JOIN customers AS c ON i.customer_id = c.customer_id WHERE i.status = 'pending' AND i.due_date = :due_date";
$stmt_customers = $db_connection->prepare($sql_customers);
$stmt_customers->execute([':due_date' => $tomorrow_date]);
$due_invoices = $stmt_customers->fetchAll(PDO::FETCH_ASSOC);

if (count($due_invoices) > 0) {
    echo "تم العثور على " . count($due_invoices) . " فاتورة مستحقة غداً.<br>";
    foreach($due_invoices as $row) {
        $message = "مرحباً " . $row['full_name'] . "،\n\nنود تذكيرك بأن لديك فاتورة مستحقة غداً بتاريخ " . $row['due_date'] . ".\nالمبلغ المستحق: " . $row['amount'] . " ريال.\n\nشكراً لتعاونك.";
        $api_url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/sendMessage?chat_id=" . $row['telegram_id'] . "&text=" . urlencode($message);
        file_get_contents($api_url);
        echo "✔️ تم إرسال تنبيه إلى العميل: " . $row['full_name'] . "<br>";
    }
} else {
    echo "لا توجد فواتير مستحقة غداً.<br>";
}

// --- المهمة الثانية: تنبيهات الموظفين بالفواتير المتأخرة ---
echo "<hr><h3>المهمة 2: تنبيهات الموظفين</h3>";
$yesterday_date = date('Y-m-d', strtotime('-1 day'));
$sql_employees = "SELECT i.amount, c.full_name AS customer_name, e.full_name AS employee_name, e.telegram_id AS employee_telegram_id FROM invoices AS i JOIN customers AS c ON i.customer_id = c.customer_id JOIN employees AS e ON i.employee_id = e.employee_id WHERE i.status = 'pending' AND i.due_date = :due_date";
$stmt_employees = $db_connection->prepare($sql_employees);
$stmt_employees->execute([':due_date' => $yesterday_date]);
$overdue_invoices = $stmt_employees->fetchAll(PDO::FETCH_ASSOC);

if (count($overdue_invoices) > 0) {
    echo "تم العثور على " . count($overdue_invoices) . " فاتورة متأخرة.<br>";
    foreach($overdue_invoices as $row) {
        $message = "⚠️ تنبيه تأخر سداد ⚠️\n\nمرحباً " . $row['employee_name'] . "،\nفاتورة العميل ( " . $row['customer_name'] . " ) بمبلغ " . $row['amount'] . " ريال قد تجاوزت تاريخ الاستحقاق.\n\nيرجى المتابعة.";
        $api_url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/sendMessage?chat_id=" . $row['employee_telegram_id'] . "&text=" . urlencode($message);
        file_get_contents($api_url);
        echo "✔️ تم إرسال تنبيه إلى الموظف: " . $row['employee_name'] . "<br>";
    }
} else {
    echo "لا توجد فواتير متأخرة من الأمس.<br>";
}

// --- المهمة الثالثة: التقرير اليومي للإدارة ---
echo "<hr><h3>المهمة 3: التقرير اليومي للإدارة</h3>";
$today_date = date('Y-m-d');
$sql_new_invoices = $db_connection->query("SELECT COUNT(*) as count FROM invoices WHERE DATE(creation_date) = '$today_date'")->fetchColumn();
$pending_data_stmt = $db_connection->query("SELECT COUNT(*) as count, SUM(amount) as total FROM invoices WHERE status = 'pending'");
$pending_data = $pending_data_stmt->fetch(PDO::FETCH_ASSOC);
$pending_invoices_count = $pending_data['count'];
$pending_invoices_total = number_format($pending_data['total'] ?? 0, 2);

$report_message = "📊 **التقرير اليومي لـ BizFlow** 📊\n\n🗓️ ليوم: " . $today_date . "\n\n✉️ فواتير جديدة أُنشئت اليوم: **" . $sql_new_invoices . "**\n⏳ إجمالي الفواتير قيد الانتظार: **" . $pending_invoices_count . "**\n💰 إجمالي المبالغ المستحقة: **" . $pending_invoices_total . " ريال**\n\nيوم عمل موفق!";
$api_url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/sendMessage?chat_id=" . MANAGEMENT_CHAT_ID . "&text=" . urlencode($report_message) . "&parse_mode=Markdown";
file_get_contents($api_url);
echo "✔️ تم إرسال التقرير اليومي إلى الإدارة بنجاح.<br>";
echo "<hr>انتهت جميع المهام.";
?>
