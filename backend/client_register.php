<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once 'config.php';
/** @var mysqli $conn */

$response = ['success' => false, 'message' => 'حدث خطأ أثناء التسجيل'];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('طريقة الطلب غير صحيحة');
    }

    $data = json_decode(file_get_contents('php://input'), true) ?? [];

    $full_name = trim($data['full_name'] ?? '');
    $email     = trim($data['email'] ?? '');
    $phone     = trim($data['phone'] ?? '');
    $city      = trim($data['city'] ?? '');
    $password  = $data['password'] ?? '';
    $confirm   = $data['confirm_password'] ?? '';

    if (mb_strlen($full_name) < 3) {
        throw new Exception('الاسم الكامل يجب أن يكون 3 أحرف على الأقل');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('البريد الإلكتروني غير صحيح');
    }
    if (!preg_match('/^(05|06|07)\d{8}$/', $phone)) {
        throw new Exception('رقم الهاتف يجب أن يبدأ بـ 05/06/07 ويتكون من 10 أرقام');
    }
    if (strlen($password) < 6) {
        throw new Exception('كلمة المرور يجب أن تكون 6 أحرف على الأقل');
    }
    if ($password !== $confirm) {
        throw new Exception('كلمتا المرور غير متطابقتين');
    }

    $chk = $conn->prepare("SELECT id FROM clients WHERE email = ? LIMIT 1");
    $chk->bind_param("s", $email);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) {
        throw new Exception('البريد الإلكتروني مسجل بالفعل');
    }

    $hashed = password_hash($password, PASSWORD_BCRYPT);
    $stmt   = $conn->prepare(
        "INSERT INTO clients (full_name, email, phone, city, password) VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->bind_param("sssss", $full_name, $email, $phone, $city, $hashed);
    if (!$stmt->execute()) {
        throw new Exception('فشل إنشاء الحساب، حاول مرة أخرى');
    }

    $client_id = (int)$conn->insert_id;

    $_SESSION['client_id']   = $client_id;
    $_SESSION['client_name'] = $full_name;
    $_SESSION['client_email']= $email;
    $_SESSION['user_type']   = 'client';

    $response['success']  = true;
    $response['message']  = 'تم إنشاء حسابك بنجاح! مرحباً بك في حرفي';
    $response['redirect'] = 'backend/client_dashboard.php';

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>
