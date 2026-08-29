<?php
header('Content-Type: application/json; charset=utf-8');

require_once 'config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$response = ['success' => false, 'craftsman' => null, 'requires_login' => false];

if ($id <= 0) {
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

session_start();
$isLoggedIn = isset($_SESSION['craftsman_id']) || isset($_SESSION['admin_id']);

try {
    $sql = "SELECT 
                id,
                craftsman_id AS login_code,
                full_name AS name,
                specialization AS profession,
                city,
                address,
                email,
                phone,
                whatsapp,
                working_hours,
                IFNULL(rating,0) AS rating,
                IFNULL(total_reviews,0) AS total_reviews,
                IFNULL(experience_years,0) AS experience_years,
                IFNULL(avatar,'') AS avatar,
                IFNULL(profile_image,'') AS profile_image,
                IFNULL(excerpt,'') AS excerpt,
                IFNULL(bio,'') AS bio,
                IFNULL(skills,'') AS skills,
                IFNULL(portfolio_images,'') AS portfolio_images,
                IFNULL(portfolio_videos,'') AS portfolio_videos,
                IFNULL(documents_verified,0) AS documents_verified,
                IFNULL(badge_type,'') AS badge_type,
                IFNULL(is_featured,0) AS is_featured,
                date_of_birth,
                gender,
                status,
                created_at
            FROM craftsmen
            WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        $response['error'] = 'Prepare failed: ' . $conn->error;
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($row = $res->fetch_assoc()) {
        if ($row['status'] === 'suspended' || $row['status'] === 'inactive') {
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($row['status'] === 'pending') {
            $can_view = isset($_SESSION['admin_id']) || (isset($_SESSION['craftsman_id']) && (int)$_SESSION['craftsman_id'] === $id);
            if (!$can_view) {
                echo json_encode($response, JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
        
        $row['avatar'] = $row['avatar'] ?: 'img/default-avatar.png';
        $row['profile_image'] = $row['profile_image'] ?: $row['avatar'];
        $row['excerpt'] = $row['excerpt'] ?: '';
        $row['bio'] = $row['bio'] ?: '';
        $row['skills'] = $row['skills'] ?: '';
        $row['badge_type'] = $row['badge_type'] ?: '';
        $row['is_featured'] = (int)$row['is_featured'];
        $row['documents_verified'] = (int)$row['documents_verified'];
        $row['date_of_birth'] = $row['date_of_birth'] ?: '';
        $row['gender'] = $row['gender'] ?: '';
        $row['address'] = $row['address'] ?: '';
        $row['whatsapp'] = $row['whatsapp'] ?: '';
        $row['working_hours'] = $row['working_hours'] ?: '';
        
        $row['phone'] = $row['phone'] ?? '';
        
        $portfolioImages = [];
        if (!empty($row['portfolio_images'])) {
            $imgArray = json_decode($row['portfolio_images'], true);
            if (is_array($imgArray)) {
                $portfolioImages = $imgArray;
            }
        }
        $row['portfolio_images'] = $portfolioImages;
        
        $portfolioVideos = [];
        if (!empty($row['portfolio_videos'])) {
            $vidArray = json_decode($row['portfolio_videos'], true);
            if (is_array($vidArray)) {
                $portfolioVideos = $vidArray;
            }
        }
        $row['portfolio_videos'] = $portfolioVideos;
        
        $sqlPortfolio = "SELECT media_type, media_url, title, description, work_date 
                         FROM artisan_portfolio 
                         WHERE craftsman_id = ? 
                         ORDER BY created_at DESC 
                         LIMIT 20";
        $stmtPort = $conn->prepare($sqlPortfolio);
        $stmtPort->bind_param('i', $id);
        $stmtPort->execute();
        $resPort = $stmtPort->get_result();
        
        $portfolioItems = [];
        while ($portRow = $resPort->fetch_assoc()) {
            $portfolioItems[] = $portRow;
        }
        $row['portfolio_items'] = $portfolioItems;
        
        $response['success'] = true;
        $response['craftsman'] = $row;
        $response['requires_login'] = !$isLoggedIn;
    }
} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);