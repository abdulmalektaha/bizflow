<?php
require_once 'config.php'; // يجلب $db_connection
$message = "";

if (isset($_POST['add_customer'])) {
    try {
        $full_name = $_POST['full_name'];
        $telegram_id = $_POST['telegram_id'];
        
        $sql = "INSERT INTO customers (full_name, telegram_id) VALUES (:full_name, :telegram_id)";
        $stmt = $db_connection->prepare($sql);
        
        $stmt->execute([
            ':full_name' => $full_name,
            ':telegram_id' => $telegram_id
        ]);
        
        $message = "تمت إضافة العميل بنجاح!";
    } catch (PDOException $e) {
        $message = "خطأ: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>إضافة عميل - BizFlow</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h1><a href="index.php" style="text-decoration: none; color: inherit;">لوحة تحكم BizFlow</a></h1>
        <?php if (!empty($message)): ?>
            <div class="message <?php echo (strpos($message, 'خطأ') === false) ? 'success' : 'error'; ?>"><?php echo $message; ?></div>
        <?php endif; ?>
        <h2>إضافة عميل جديد</h2>
        <form action="add_customer.php" method="POST">
            <input type="text" name="full_name" placeholder="اسم العميل الكامل" required>
            <input type="text" name="telegram_id" placeholder="معرّف التلغرام (الرقمي)" required>
            <button type="submit" name="add_customer">إضافة العميل</button>
        </form>
    </div>
</body>
</html>
