<?php
// [1. بدء الجلسة والاتصال]
require_once 'config.php'; 

// [2. حارس الأمان (Authentication Guard)]
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// [3. جلب بيانات المستخدم الحالي]
$current_user_id = $_SESSION['user_id'];
$current_company_name = $_SESSION['company_name'] ?? 'BizFlow';
$error_message = null;
$link_token = null;
$telegram_id = null;

try {
    // جلب بيانات المستخدم من قاعدة البيانات
    $stmt = $db_connection->prepare("SELECT telegram_chat_id, link_token FROM users WHERE user_id = :user_id");
    $stmt->execute(['user_id' => $current_user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        if (!empty($user['telegram_chat_id'])) {
            // -- الحالة 1: الحساب مربوط بالفعل --
            $telegram_id = $user['telegram_chat_id'];
        } else {
            // -- الحالة 2: الحساب غير مربوط، تحقق من وجود رمز --
            if (empty($user['link_token'])) {
                // إذا لم يكن هناك رمز، قم بإنشاء رمز جديد
                $new_token = strtoupper(bin2hex(random_bytes(5))); // مثال: 5A3F9B0D2C
                
                $update_stmt = $db_connection->prepare("UPDATE users SET link_token = :token WHERE user_id = :user_id");
                $update_stmt->execute(['token' => $new_token, 'user_id' => $current_user_id]);
                $link_token = $new_token;
            } else {
                // إذا كان هناك رمز موجود بالفعل، استخدمه
                $link_token = $user['link_token'];
            }
        }
    }

} catch (PDOException $e) {
    logError("account.php - PDOException: " . $e->getMessage());
    $error_message = "حدث خطأ أثناء جلب بيانات الحساب.";
}

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>إدارة الحساب - <?php echo htmlspecialchars($current_company_name); ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        
        <!-- شريط التنقل العلوي -->
        <div class="header-nav">
            <h1>إدارة حساب <?php echo htmlspecialchars($current_company_name); ?></h1>
            <div>
                <a href="index.php" class="nav-link">عرض الفواتير</a>
                <a href="customers.php" class="nav-link">عرض العملاء</a>
                <!-- (سنضيف رابط "حسابي" هنا لاحقًا) -->
                <a href="logout.php" class="nav-link logout-btn">تسجيل الخروج</a>
            </div>
        </div>

        <?php if ($error_message): ?>
            <p class="message error-message"><?php echo htmlspecialchars($error_message); ?></p>
        <?php endif; ?>

        <!-- قسم ربط تيليجرام -->
        <div class="form-container" style="max-width: 700px; margin-top: 20px;">
            <h2><span style="font-size: 1.5em; vertical-align: middle;">🤖</span> ربط حساب تيليجرام</h2>
            
            <?php if ($telegram_id): ?>
                <!-- إذا كان الحساب مربوطًا -->
                <p style="font-size: 1.1em;">
                    حسابك مربوط حاليًا بحساب تيليجرام رقم: <strong><?php echo htmlspecialchars($telegram_id); ?></strong>
                </p>
                <p>البوت الآن جاهز لاستقبال أوامرك لإضافة العملاء والفواتير إلى هذا الحساب.</p>
                <!-- (يمكن إضافة زر "إلغاء الربط" هنا لاحقًا) -->
                
            <?php elseif ($link_token): ?>
                <!-- إذا لم يكن الحساب مربوطًا ويعرض الرمز -->
                <p styleB="font-size: 1.1em;">لربط حسابك في BizFlow بحسابك على تيليجرام، يرجى اتباع الخطوات التالية:</p>
                <ol style="line-height: 1.8;">
                    <li>افتح تطبيق تيليجرام على هاتفك أو جهازك.</li>
                    <li>ابحث عن البوت الخاص بـ BizFlow (أو اضغط على الرابط إذا كان لديك).</li>
                    <li>أرسل الأمر التالي إلى البوت **بالضبط** كما هو:</li>
                </ol>
                <div style="background-color: #f4f4f4; padding: 15px; border-radius: 8px; text-align: center; margin-top: 15px;">
                    <code style="font-size: 1.4em; font-weight: bold; color: #333;">/link <?php echo htmlspecialchars($link_token); ?></code>
                </div>
                <p style="margin-top: 15px; text-align: center; color: #555;">(ملاحظة: هذا الرمز صالح للاستخدام مرة واحدة فقط لربط حسابك).</p>
                
            <?php endif; ?>
            
        </div>
        
    </div>
</body>
</html>
