<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once 'config.php';
/** @var mysqli $conn */

$response = ['success' => false, 'message' => 'بيانات الدخول غير صحيحة'];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('طريقة الطلب غير صحيحة');
    }

    $data       = json_decode(file_get_contents('php://input'), true) ?? [];
    $email      = trim($data['email'] ?? '');
    $password   = $data['password'] ?? '';
    $remember   = (bool)($data['remember'] ?? false);

    if (empty($email) || empty($password)) {
        throw new Exception('البريد الإلكتروني وكلمة المرور مطلوبان');
    }

    $stmt = $conn->prepare(
        "SELECT id, full_name, email, password, status FROM clients WHERE email = ? LIMIT 1"
    );
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $client = $stmt->get_result()->fetch_assoc();

    if (!$client) {
        throw new Exception('البريد الإلكتروني غير مسجل');
    }
    if ($client['status'] === 'banned') {
        throw new Exception('حسابك موقوف. تواصل مع الإدارة للاستفسار.');
    }
    if ($client['status'] !== 'active') {
        throw new Exception('حسابك غير مفعل حالياً.');
    }
    if (!password_verify($password, $client['password'])) {
        throw new Exception('كلمة المرور غير صحيحة');
    }

    $_SESSION['client_id']    = $client['id'];
    $_SESSION['client_name']  = $client['full_name'];
    $_SESSION['client_email'] = $client['email'];
    $_SESSION['user_type']    = 'client';

    if ($remember) {
        setcookie('client_remember', bin2hex(random_bytes(32)), time() + (30 * 24 * 3600), '/');
    }

    $response['success']  = true;
    $response['message']  = 'مرحباً ' . $client['full_name'] . '!';
    $response['redirect'] = 'backend/client_dashboard.php';

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>
