<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once 'config.php';

$response = ['success' => false, 'message' => 'غير مصرح'];

// Determine current user from session
$user_type = $_SESSION['user_type'] ?? null;
$user_id   = null;
if ($user_type === 'client') {
    $user_id = (int)($_SESSION['client_id'] ?? 0);
} elseif ($user_type === 'craftsman') {
    $user_id = (int)($_SESSION['craftsman_id'] ?? 0);
} elseif ($user_type === 'admin') {
    $user_id = (int)($_SESSION['admin_id'] ?? 0);
    $user_type = 'admin';
}

if (!$user_id) {
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

$action = trim($_REQUEST['action'] ?? 'list');

try {
    if ($action === 'list') {
        $limit = min((int)($_GET['limit'] ?? 20), 50);
        $stmt  = $conn->prepare(
            "SELECT id, type, title, body, link, is_read, created_at
             FROM notifications
             WHERE user_type = ? AND user_id = ?
             ORDER BY created_at DESC
             LIMIT ?"
        );
        $stmt->bind_param("sii", $user_type, $user_id, $limit);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $response['success']       = true;
        $response['notifications'] = $rows;
        $response['unread']        = count(array_filter($rows, fn($r) => !$r['is_read']));

    } elseif ($action === 'count') {
        $stmt = $conn->prepare(
            "SELECT COUNT(*) as cnt FROM notifications WHERE user_type = ? AND user_id = ? AND is_read = 0"
        );
        $stmt->bind_param("si", $user_type, $user_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $response['success'] = true;
        $response['count']   = (int)($row['cnt'] ?? 0);

    } elseif ($action === 'mark_read') {
        $id = (int)($_REQUEST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $conn->prepare(
                "UPDATE notifications SET is_read = 1 WHERE id = ? AND user_type = ? AND user_id = ?"
            );
            $stmt->bind_param("isi", $id, $user_type, $user_id);
        } else {
            $stmt = $conn->prepare(
                "UPDATE notifications SET is_read = 1 WHERE user_type = ? AND user_id = ?"
            );
            $stmt->bind_param("si", $user_type, $user_id);
        }
        $stmt->execute();
        $response['success'] = true;
        $response['message'] = 'تم تحديد الإشعارات كمقروءة';
    }

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>
