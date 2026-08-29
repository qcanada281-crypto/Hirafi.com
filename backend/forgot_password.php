<?php
// إرسال النتيجة كـ JSON مع دعم العربية
header('Content-Type: application/json; charset=utf-8');
require_once 'config.php'; // ملف الاتصال بقاعدة البيانات

// النتيجة الافتراضية
$response = ['success' => false, 'message' => 'حدث خطأ'];

try {
    // استقبال البيانات (إما JSON أو form)
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $email = trim($input['email'] ?? '');

    // التحقق من صحة البريد الإلكتروني
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('بريد إلكتروني غير صالح');
    }

    // البحث عن الحرفي في قاعدة البيانات
    $stmt = $conn->prepare("SELECT id, full_name FROM craftsmen WHERE email = ? LIMIT 1");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();

    // إذا لم يوجد مستخدم بهذا البريد
    if ($result->num_rows === 0) {
        throw new Exception('البريد الإلكتروني غير مسجل');
    }

    $user = $result->fetch_assoc(); // بيانات المستخدم

    // إنشاء جدول password_resets إذا لم يكن موجوداً
    $create = "CREATE TABLE IF NOT EXISTS `password_resets` (
        `id` INT PRIMARY KEY AUTO_INCREMENT,
        `user_id` INT NOT NULL,
        `token` VARCHAR(128) NOT NULL,
        `expires_at` DATETIME NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES craftsmen(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $conn->query($create);

    // إنشاء رمز فريد وتاريخ انتهاء (ساعة واحدة)
    $token = bin2hex(random_bytes(32)); // توليد 64 حرف عشوائي
    $expires = date('Y-m-d H:i:s', time() + 3600); // بعد ساعة من الآن

    // حفظ الرمز في قاعدة البيانات
    $ins = $conn->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
    $ins->bind_param('iss', $user['id'], $token, $expires);
    $ins->execute();

    // إنشاء رابط إعادة التعيين
    // (في الإنتاج، يرسل هذا الرابط بالبريد الإلكتروني)
    $resetLink = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://') .
                 $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . "/reset_password.php?token={$token}";

    // إعداد النتيجة الناجحة
    $response['success'] = true;
    $response['message'] = 'طلب إعادة التعيين تم إنشاؤه. تحقق من بريدك الإلكتروني (محاكاة).';
    
    // فقط للاختبار المحلي (يحذف في الإنتاج)
    $response['token'] = $token;           // الرمز الفريد
    $response['reset_link'] = $resetLink;  // الرابط الكامل

} catch (Exception $e) {
    // في حالة حدوث خطأ
    $response['message'] = $e->getMessage();
}

// إرسال النتيجة
echo json_encode($response, JSON_UNESCAPED_UNICODE);
