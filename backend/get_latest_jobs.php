<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'config.php';

$response = ['success' => false, 'message' => ''];

try {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = 10;
    $offset = ($page - 1) * $limit;

    $category = $_GET['category'] ?? '';
    $city = $_GET['city'] ?? '';

    $query = "SELECT jr.id, jr.title, jr.category, jr.description, jr.budget, jr.urgency, jr.city, jr.neighborhood, jr.created_at, 
              c.full_name as client_name, c.avatar as client_avatar,
              (SELECT COUNT(*) FROM proposals p WHERE p.request_id = jr.id) as proposal_count,
              (SELECT COUNT(*) FROM job_request_photos ph WHERE ph.request_id = jr.id) as photo_count,
              (SELECT photo_path FROM job_request_photos ph2 WHERE ph2.request_id = jr.id ORDER BY id ASC LIMIT 1) as cover_image
              FROM job_requests jr
              JOIN clients c ON c.id = jr.client_id
              WHERE jr.status = 'open' ";
    
    $params = [];
    $types = '';

    if ($category && $category !== 'All Categories' && $category !== '') {
        $query .= " AND jr.category = ?";
        $params[] = $category;
        $types .= 's';
    }

    if ($city && $city !== 'All Cities' && $city !== '') {
        $query .= " AND jr.city = ?";
        $params[] = $city;
        $types .= 's';
    }

    // Default sorting for marketplace
    $query .= " ORDER BY jr.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= 'ii';

    $stmt = $conn->prepare($query);
    if($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $jobs = $result->fetch_all(MYSQLI_ASSOC);

    $response['success'] = true;
    $response['jobs'] = $jobs;

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>
