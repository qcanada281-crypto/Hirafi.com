<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'config.php';

try {
    $sql = "SELECT id, full_name AS name, specialization AS profession, city, phone, 
            IFNULL(avatar,'') AS avatar, IFNULL(excerpt,'') AS excerpt,
            IFNULL(rating,0) AS rating, IFNULL(total_reviews,0) AS total_reviews,
            experience_years, created_at
            FROM craftsmen 
            WHERE status='active' AND avatar IS NOT NULL AND avatar != ''
            ORDER BY created_at DESC
            LIMIT 8";
    
    $stmt = $conn->query($sql);
    $craftsmen = [];
    
    while ($row = $stmt->fetch_assoc()) {
        $craftsmen[] = [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'profession' => $row['profession'],
            'city' => $row['city'],
            'phone' => $row['phone'],
            'avatar' => $row['avatar'] ?: 'img/default-avatar.png',
            'excerpt' => $row['excerpt'] ?: 'حرفي مسجل حديثاً على المنصة',
            'rating' => floatval($row['rating']),
            'total_reviews' => (int)$row['total_reviews'],
            'experience_years' => (int)$row['experience_years'],
            'created_at' => $row['created_at']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'craftsmen' => $craftsmen
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'حدث خطأ في جلب البيانات: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>