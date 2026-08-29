<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once 'config.php';

function respond($success, $message, $data = null) {
    echo json_encode([
        'success' => (bool)$success,
        'message' => (string)$message,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'طريقة الطلب غير صحيحة');
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    respond(false, 'بيانات غير صالحة');
}

$name = trim((string)($payload['name'] ?? ''));
$email = trim((string)($payload['email'] ?? ''));
$phone = trim((string)($payload['phone'] ?? ''));
$subject = trim((string)($payload['subject'] ?? ''));
$message = trim((string)($payload['message'] ?? ''));
$senderType = trim((string)($payload['sender_type'] ?? 'guest'));

if ($name === '' || $email === '' || $phone === '' || $message === '') {
    respond(false, 'المرجو ملء جميع الحقول المطلوبة');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(false, 'صيغة البريد الإلكتروني غير صحيحة');
}

if (!preg_match('/^[0-9+\s-]{6,30}$/u', $phone)) {
    respond(false, 'رقم الهاتف غير صالح');
}

if (!in_array($senderType, ['guest', 'craftsman', 'client'], true)) {
    $senderType = 'guest';
}

if (mb_strlen($name) > 120 || mb_strlen($email) > 150 || mb_strlen($phone) > 30 || mb_strlen($subject) > 200) {
    respond(false, 'بعض الحقول تتجاوز الحد المسموح');
}

$stmt = $conn->prepare(
    "INSERT INTO contact_messages
    (sender_name, sender_email, sender_phone, sender_type, subject, message_text)
    VALUES (?, ?, ?, ?, ?, ?)"
);

if (!$stmt) {
    respond(false, 'تعذر تجهيز عملية الإرسال');
}

$stmt->bind_param("ssssss", $name, $email, $phone, $senderType, $subject, $message);
if (!$stmt->execute()) {
    respond(false, 'فشل إرسال الرسالة، حاول مرة أخرى');
}

respond(true, 'تم إرسال رسالتك بنجاح. الإدارة ستتواصل معك قريباً.');
?>
