<?php
// ==================== GET HIRAFI DATA (duplicate of get_student_data.php) ====================

header('Content-Type: application/json; charset=utf-8');
session_start();

require_once 'config.php';

$response = ['success' => false, 'data' => null];

try {
    if (!isset($_SESSION['craftsman_id'])) {
        throw new Exception('غير مصرح');
    }

    $craftsman_id = $_SESSION['craftsman_id'];

    $stmt = $conn->prepare("SELECT * FROM craftsmen WHERE id = ?");
    $stmt->bind_param("i", $craftsman_id);
    $stmt->execute();
    $craftsman = $stmt->get_result()->fetch_assoc();

    if (!$craftsman) {
        throw new Exception('الحرفي غير موجود');
    }

    $grades_stmt = $conn->prepare("SELECT * FROM grades WHERE craftsman_id = ?");
    $grades_stmt->bind_param("i", $craftsman_id);
    $grades_stmt->execute();
    $grades = $grades_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $docs_stmt = $conn->prepare("SELECT * FROM documents WHERE craftsman_id = ?");
    $docs_stmt->bind_param("i", $craftsman_id);
    $docs_stmt->execute();
    $documents = $docs_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $response['success'] = true;
    $response['data'] = [
        'craftsman' => $craftsman,
        'grades' => $grades,
        'documents' => $documents
    ];

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>
