<?php
// --- 1. إعدادات الاتصال بقاعدة البيانات ---
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "bizflow_db";

// --- 2. إنشاء الاتصال ---
$conn = new mysqli($servername, $username, $password, $dbname);
$conn->set_charset("utf8"); // لدعم اللغة العربية

// --- 3. التحقق من نجاح الاتصال ---
if ($conn->connect_error) {
    die("فشل الاتصال: " . $conn->connect_error);
}

$message = "";

// --- جلب بيانات العملاء والموظفين لعرضهم في النموذج ---
$customers_result = $conn->query("SELECT customer_id, full_name FROM customers");
$employees_result = $conn->query("SELECT employee_id, full_name FROM employees");


// --- التحقق مما إذا تم إرسال نموذج الفاتورة ---
if (isset($_POST['add_invoice'])) {
    $customer_id = $_POST['customer_id'];
    $employee_id = $_POST['employee_id'];
    $amount = $_POST['amount'];
    $due_date = $_POST['due_date'];
    
    // استخدام "pending" كحالة افتراضية
    $status = 'pending';

    $sql = "INSERT INTO invoices (customer_id, employee_id, amount, status, due_date) VALUES ('$customer_id', '$employee_id', '$amount', '$status', '$due_date')";

    if ($conn->query($sql) === TRUE) {
        $message = "تمت إضافة الفاتورة بنجاح!";
    } else {
        $message = "خطأ: " . $conn->error;
    }
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
            <div class="message <?php echo (strpos($message, 'خطأ') === false) ? 'success' : 'error'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <h2>إضافة فاتورة جديدة</h2>
        <form action="add_invoice.php" method="POST">
            
            <label for="customer_id">اختر العميل:</label>
            <select name="customer_id" id="customer_id" required>
                <?php while($row = $customers_result->fetch_assoc()): ?>
                    <option value="<?php echo $row['customer_id']; ?>"><?php echo $row['full_name']; ?></option>
                <?php endwhile; ?>
            </select>

            <label for="employee_id">الموظف المسؤول:</label>
            <select name="employee_id" id="employee_id" required>
                <?php while($row = $employees_result->fetch_assoc()): ?>
                    <option value="<?php echo $row['employee_id']; ?>"><?php echo $row['full_name']; ?></option>
                <?php endwhile; ?>
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