<?php
// --- 1. بدء الجلسة والإعدادات ---
// session_start() موجود الآن في config.php وهو أول شيء يتم استدعاؤه
require_once 'config.php';

// --- 2. حارس الأمان (Authentication Guard) ---
// التحقق مما إذا كان المستخدم مسجلاً دخوله
if (!isset($_SESSION['user_id'])) {
    // إذا لم يكن مسجلاً، أعد توجيهه إلى صفحة تسجيل الدخول
    header("Location: login.php");
    exit;
}

// جلب اسم الشركة من الجلسة لعرضه
$company_name = $_SESSION['company_name'] ?? 'BizFlow';

// لتحديد الصفحة "النشطة" في شريط التنقل
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- سيتم تعيين العنوان ديناميكيًا في كل صفحة -->
    <title>لوحة تحكم <?php echo htmlspecialchars($company_name); ?></title>
    <!-- رابط لملف التنسيق CSS -->
    <link rel="stylesheet" href="style.css?v=1.1"> 
    <!-- ?v=1.1 لإجبار المتصفح على تحميل النسخة الجديدة من CSS -->
</head>
<body>

    <!-- 3. شريط التنقل (Navbar) -->
    <nav class="navbar">
        <div class="brand"><?php echo htmlspecialchars($company_name); ?></div>
        <div class="nav-links">
            <a href="index.php" class="<?php echo ($current_page == 'index.php' || $current_page == 'edit_invoice.php') ? 'active' : ''; ?>">الفواتير</a>
            <a href="customers.php" class="<?php echo ($current_page == 'customers.php' || $current_page == 'edit_customer.php') ? 'active' : ''; ?>">العملاء</a>
            <a href="account.php" class="<?php echo ($current_page == 'account.php') ? 'active' : ''; ?>">حسابي</a>
            <a href="logout.php" class="logout">تسجيل الخروج</a>
        </div>
    </nav>

    <!-- 4. بدء حاوية المحتوى الرئيسية -->
    <div class="container">
       
        <!-- سيتم وضع محتوى الصفحة (مثل جدول الفواتير) هنا -->
