<?php
require_once 'config.php'; // يجلب $db_connection
$message = "";

try {
    // جلب العملاء والموظفين لعرضهم في النموذج
    $customers = $db_connection->query("SELECT customer_id, full_name FROM customers ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);
    $employees = $db_connection->query("SELECT employee_id, full_name FROM employees ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);

    // التحقق مما إذا تم إرسال نموذج الفاتورة
    if (isset($_POST['add_invoice'])) {
        $customer_id = $_POST['customer_id'];
        $employee_id = $_POST['employee_id'];
        $amount = $_POST['amount'];
        $due_date = $_POST['due_date'];
        $status = 'pending';

        $sql = "INSERT INTO invoices (customer_id, employee_id, amount, status, due_date) VALUES (:customer_id, :employee_id, :amount, :status, :due_date)";
        $stmt = $db_connection->prepare($sql);
        $stmt->execute([
            ':customer_id' => $customer_id,
            ':employee_id' => $employee_id,
            ':amount' => $amount,
            ':status' => $status,
            ':due_date' => $due_date
        ]);
        
        $message = "تمت إضافة الفاتورة بنجاح!";
    }
} catch (PDOException $e) {
    $message = "خطأ: " . $e->getMessage();
    $customers = []; // تأكد من أن المتغيرات موجودة لتجنب الأخطاء
    $employees = [];
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>إضافة فاتورة جديدة - BizFlow</title>
    <link rel="stylesheet" href="style.css"> 
</head>
<body>
    <div class="container">
        <h1><a href="index.php" style="text-decoration: none; color: inherit;">لوحة تحكم BizFlow</a></h1>
        <?php if (!empty($message)): ?>
            <div class="message <?php echo (strpos($message, 'خطأ') === false) ? 'success' : 'error'; ?>"><?php echo $message; ?></div>
        <?php endif; ?>
        <h2>إضافة فاتورة جديدة</h2>
        <form action="add_invoice.php" method="POST">
            <label for="customer_id">اختر العميل:</label>
            <select name="customer_id" id="customer_id" required>
                <?php foreach($customers as $customer): ?>
                    <option value="<?php echo $customer['customer_id']; ?>"><?php echo htmlspecialchars($customer['full_name']); ?></option>
                <?php endforeach; ?>
            </select>
            <label for="employee_id">الموظف المسؤول:</label>
            <select name="employee_id" id="employee_id" required>
                 <?php foreach($employees as $employee): ?>
                    <option value="<?php echo $employee['employee_id']; ?>"><?php echo htmlspecialchars($employee['full_name']); ?></option>
                <?php endforeach; ?>
            </select>
            <label for="amount">مبلغ الفاتورة:</label>
            <input type="text" name="amount" id="amount" placeholder="مثال: 150.50" required>
            <label for="due_date">تاريخ الاستحقاق:</label>
            <input type="date" name="due_date" id="due_date" required>
            <button type="submit" name="add_invoice">إضافة الفاتورة</button>
        </form>
    </div>
</body>
</html>
