<?php
// --- 1. استدعاء القالب العلوي (Header) ---
// سيتولى هذا الملف بدء الجلسة، التحقق من تسجيل الدخول، وعرض شريط التنقل
require 'header.php';

// --- 2. منطق هذه الصفحة فقط (جلب/إنشاء رمز ربط تيليجرام) ---

// جلب بيانات المستخدم من الجلسة وقاعدة البيانات
$user_id = $_SESSION['user_id'];
$user_email = $_SESSION['email']; 
$company_name = $_SESSION['company_name']; 

$link_code = null;
$telegram_id = null;
$error_message = null;

try {
    // جلب رمز الربط الحالي ومعرف تيليجرام (إن وجد)
    $stmt = $db_connection->prepare("SELECT telegram_link_code, telegram_chat_id FROM users WHERE user_id = :user_id");
    $stmt->execute([':user_id' => $user_id]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

    $telegram_id = $user_data['telegram_chat_id'];
    $link_code = $user_data['telegram_link_code'];

    // إذا لم يكن المستخدم مربوطًا وليس لديه رمز، قم بإنشاء رمز جديد
    if (!$telegram_id && !$link_code) {
        $link_code = "BZF-" . strtoupper(bin2hex(random_bytes(5))); // مثال: BZF-A1B2C3D4E5
        $update_stmt = $db_connection->prepare("UPDATE users SET telegram_link_code = :link_code WHERE user_id = :user_id");
        $update_stmt->execute([':link_code' => $link_code, ':user_id' => $user_id]);
    }
} catch (PDOException $e) {
    logError("Database Error (account.php): " . $e->getMessage());
    $error_message = "حدث خطأ أثناء جلب بيانات الحساب.";
}
?>

<!-- 3. عرض محتوى الصفحة -->
<div class="page-header">
    <h1>إدارة الحساب</h1>
</div>

<!-- عرض رسائل الخطأ (إن وجدت) -->
<?php if ($error_message): ?>
    <div class="message error"><?php echo htmlspecialchars($error_message); ?></div>
<?php endif; ?>

<!-- استخدام .form-container لتنسيق الصندوق الأبيض -->
<div class="form-container">
    <h3>مرحبًا بك، <?php echo htmlspecialchars($company_name); ?>!</h3>
    <p><strong>البريد الإلكتروني:</strong> <?php echo htmlspecialchars($user_email); ?></p>
    
    <hr style="border: 0; border-top: 1px solid #eee; margin: 20px 0;">
    
    <h4>ربط حساب تيليجرام</h4>
    
    <?php if ($telegram_id): ?>
        <div class="message success">
            ✅ حسابك مرتبط بنجاح بمعرف تيليجرام: <strong><?php echo htmlspecialchars($telegram_id); ?></strong>
            <br><small>(إذا أردت تغيير الربط، ستحتاج للتواصل مع الدعم الفني - ميزة لم نبرمجها بعد)</small>
        </div>
    <?php else: ?>
        <p>لربط حسابك في BizFlow بالبوت، يرجى اتباع الخطوات التالية:</p>
        <ol>
            <li>افتح تطبيق تيليجرام وتحدث إلى <strong>BizFlow Assistant Bot</strong> (أو أي اسم أعطيته لبوتك).</li>
            <li>أرسل الأمر التالي <strong>بالضبط</strong> كما هو:</li>
        </ol>
        
        <!-- صندوق لعرض الرمز بشكل واضح -->
        <div style="background-color: #f4f4f4; border: 1px solid #ddd; padding: 15px 20px; border-radius: 5px; font-family: monospace; direction: ltr; text-align: left; margin: 20px 0; font-size: 1.1em; font-weight: bold; letter-spacing: 1px;">
            /link <?php echo htmlspecialchars($link_code); ?>
        </div>
        
        <p><small>هذا الرمز صالح للاستخدام مرة واحدة فقط وسيربط هذا الحساب بحساب تيليجرام الذي ترسل منه الرسالة.</small></p>
    <?php endif; ?>
</div>

<?php
// --- 4. استدعاء القالب السفلي (Footer) ---
require 'footer.php';
?>
