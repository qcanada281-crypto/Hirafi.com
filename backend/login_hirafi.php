<?php
// ==================== HIRAFI LOGIN ====================

header('Content-Type: application/json; charset=utf-8');
session_start();

require_once 'config.php';

$response = ['success' => false, 'message' => 'بيانات الدخول غير صحيحة'];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('طريقة الطلب غير صحيحة');
    }

    $data = json_decode(file_get_contents('php://input'), true);

    $email = trim($data['email'] ?? '');
    $craftsmanCode = trim($data['craftsmanCode'] ?? '');
    $identifier = trim($data['identifier'] ?? '');
    $remember = $data['remember'] ?? false;

    if ($email === '' && $craftsmanCode === '' && $identifier !== '') {
        if (strpos($identifier, '@') !== false) {
            $email = $identifier;
        } else {
            $craftsmanCode = $identifier;
        }
    }

    if ($email === '' && $craftsmanCode === '') {
        throw new Exception('البريد الإلكتروني أو كود الحرفي مطلوب');
    }

    if ($email !== '' && $craftsmanCode !== '') {
        $stmt = $conn->prepare(
            "SELECT id, craftsman_id, full_name, email, status
             FROM craftsmen
             WHERE email = ? AND craftsman_id = ?
             LIMIT 1"
        );
        $stmt->bind_param('ss', $email, $craftsmanCode);
    } elseif ($email !== '') {
        $stmt = $conn->prepare(
            "SELECT id, craftsman_id, full_name, email, status
             FROM craftsmen
             WHERE email = ?
             LIMIT 1"
        );
        $stmt->bind_param('s', $email);
    } else {
        $stmt = $conn->prepare(
            "SELECT id, craftsman_id, full_name, email, status
             FROM craftsmen
             WHERE craftsman_id = ?
             LIMIT 1"
        );
        $stmt->bind_param('s', $craftsmanCode);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('البريد الإلكتروني أو كود الحرفي غير مسجل');
    }

    $craftsman = $result->fetch_assoc();

    if ($craftsman['status'] === 'pending') {
        throw new Exception('حسابك قيد المراجعة حالياً. <a href="contact.html" style="text-decoration:underline;color:inherit;font-weight:bold;">تواصل مع الإدارة</a> للاستفسار.');
    }
    if ($craftsman['status'] !== 'active') {
        throw new Exception('حسابك معطل حالياً. <a href="contact.html" style="text-decoration:underline;color:inherit;font-weight:bold;">تواصل مع الإدارة</a>');
    }

    $_SESSION['craftsman_id'] = $craftsman['id'];
    $_SESSION['craftsman_number'] = $craftsman['craftsman_id'];
    $_SESSION['craftsman_name'] = $craftsman['full_name'];
    $_SESSION['craftsman_email'] = $craftsman['email'];
    $_SESSION['user_type'] = 'craftsman';

    if ($remember) {
        $cookie_token = bin2hex(random_bytes(32));
        setcookie('craftsman_remember', $cookie_token, time() + (30 * 24 * 60 * 60), '/');
    }

    $response['success'] = true;
    $response['message'] = 'تم الدخول بنجاح';
    $response['redirect'] = 'backend/artisan_dashboard.php';

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
