<?php
// [1. بدء الجلسة والاتصال]
// يجب أن يكون session_start() في config.php هو السطر الأول
require_once 'config.php';

// [2. حارس الأمان]
// التحقق مما إذا كان المستخدم مسجلاً دخوله
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php"); // إعادة التوجيه إلى صفحة تسجيل الدخول
    exit;
}

// جلب معلومات المستخدم المسجل دخوله
$current_user_id = $_SESSION['user_id'];
$company_name = $_SESSION['company_name'] ?? 'BizFlow';

// [3. منطق الصفحة: ربط حساب تيليجرام]
$link_code = null;
$error_message = null;
$success_message = null;

try {
    // التحقق مما إذا كان الحساب مربوطًا بالفعل
    $stmt_check = $db_connection->prepare("SELECT telegram_chat_id, telegram_link_code FROM users WHERE user_id = :user_id");
    $stmt_check->execute(['user_id' => $current_user_id]);
    $user_data = $stmt_check->fetch(PDO::FETCH_ASSOC);

    $is_linked = !empty($user_data['telegram_chat_id']);
    $link_code = $user_data['telegram_link_code'];

    // إذا لم يكن الحساب مربوطًا وليس لديه رمز، قم بإنشاء رمز جديد
    if (!$is_linked && empty($link_code)) {
        // إنشاء رمز فريد وآمن (BZF- متبوعًا بـ 10 أحرف/أرقام)
        $link_code = 'BZF-' . strtoupper(bin2hex(random_bytes(5)));
        
        // حفظ الرمز في قاعدة البيانات لهذا المستخدم
        $stmt_update = $db_connection->prepare("UPDATE users SET telegram_link_code = :link_code WHERE user_id = :user_id");
        $stmt_update->execute(['link_code' => $link_code, 'user_id' => $current_user_id]);
        
        $success_message = "تم إنشاء رمز ربط جديد لك.";
    }

} catch (PDOException $e) {
    // معالجة أخطاء قاعدة البيانات
    $error_message = "حدث خطأ في قاعدة البيانات: " . $e->getMessage();
    // تسجيل الخطأ (للمسؤول)
    logError("Account Page Error (User: $current_user_id): " . $e->getMessage());
} catch (Exception $e) {
    // معالجة أخطاء إنشاء الرمز (مثل random_bytes)
     $error_message = "حدث خطأ غير متوقع أثناء إنشاء الرمز: " . $e->getMessage();
     logError("Account Page Error (User: $current_user_id): " . $e->getMessage());
}


// [4. تضمين القالب العلوي (Header)]
// سيقوم هذا الملف بالتحقق من تسجيل الدخول وعرض شريط التنقل
require 'header.php';
?>

<!-- 5. محتوى الصفحة -->
<div class="page-title">إدارة الحساب</div>
<p class="page-subtitle">هنا يمكنك إدارة إعدادات حسابك وربط الخدمات.</p>

<?php if ($error_message): ?>
    <div class="message error"><?php echo htmlspecialchars($error_message); ?></div>
<?php endif; ?>
<?php if ($success_message): ?>
    <div class="message success"><?php echo htmlspecialchars($success_message); ?></div>
<?php endif; ?>

<div class="form-container" style="max-width: 600px; margin: 20px auto;">
    <h2>مرحبًا بك، <?php echo htmlspecialchars($company_name); ?>!</h2>
    <p><strong>البريد الإلكتروني:</strong> <?php echo htmlspecialchars($_SESSION['email'] ?? 'غير متاح'); ?></p>
    
    <hr style="margin: 20px 0;">

    <h3>ربط حساب تيليجرام</h3>
    
    <?php if ($is_linked): ?>
        <!-- إذا كان الحساب مربوطًا بالفعل -->
        <div class="message success">
            <p><strong>حسابك مربوط بنجاح!</strong></p>
            <p>معرف تيليجرام الخاص بك المسجل لدينا هو: <strong><?php echo htmlspecialchars($user_data['telegram_chat_id']); ?></strong></p>
            <p>(إذا أردت تغيير الحساب المربوط، ستحتاج إلى الاتصال بالدعم - لم نبرمج هذه الميزة بعد).</p>
        </div>
    <?php else: ?>
        <!-- إذا لم يكن الحساب مربوطًا -->
        <p>اربط حسابك في البوت BizFlow Assistant Bot لتتمكن من إضافة العملاء والفواتير مباشرة من تيليجرام.</p>
        <ol style="line-height: 1.6;">
            <li>افتح تطبيق تيليجرام وابحث عن البوت (أو أرسل اسم البوت لعميلك).</li>
            <li>أرسل الأمر التالي <strong>بالضبط كما هو</strong> للبوت:</li>
        </ol>

        <div class="code-box">
            /link <?php echo htmlspecialchars($link_code); ?>
        </div>
        
        <p style="font-size: 0.9em; color: #555; margin-top: 15px;">
            <small>
                هذا الرمز صالح للاستخدام مرة واحدة فقط لربط هذا الحساب بحساب تيليجرام الذي ترسل منه الرسالة.
            </small>
        </p>
    <?php endif; ?>
    
</div>

<?php
// [6. تضمين القالب السفلي (Footer)]
require 'footer.php';
?>
