<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once 'config.php';

$action = $_GET['action'] ?? '';

function respond($success, $message = '', $data = null) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function isAdmin() {
    return isset($_SESSION['admin_id']) && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin';
}

if (!isAdmin()) {
    respond(false, 'غير مصرح لك بالوصول');
}

switch ($action) {
    case 'get_all':
        $search = $_GET['search'] ?? '';
        $category = $_GET['category'] ?? '';
        
        $sql = "SELECT * FROM lessons WHERE 1=1";
        $params = [];
        $types = "";
        
        if ($search) {
            $sql .= " AND (title LIKE ? OR description LIKE ?)";
            $searchTerm = "%$search%";
            $params[] = &$searchTerm;
            $params[] = &$searchTerm;
            $types .= "ss";
        }
        
        if ($category && $category !== 'all') {
            $sql .= " AND category = ?";
            $params[] = &$category;
            $types .= "s";
        }
        
        $sql .= " ORDER BY order_num ASC, created_at DESC";
        
        $stmt = $conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        $lessons = [];
        while ($row = $result->fetch_assoc()) {
            $stmt2 = $conn->prepare("SELECT COUNT(*) as count FROM lesson_enrollments WHERE lesson_id = ?");
            $stmt2->bind_param("i", $row['id']);
            $stmt2->execute();
            $enrollResult = $stmt2->get_result();
            $row['enrolled_count'] = $enrollResult->fetch_assoc()['count'] ?? 0;
            $stmt2->close();
            $lessons[] = $row;
        }
        
        respond(true, '', $lessons);
        break;
    
    case 'get':
        $id = intval($_GET['id'] ?? 0);
        $stmt = $conn->prepare("SELECT * FROM lessons WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $lesson = $result->fetch_assoc();
        $stmt->close();
        
        if ($lesson) {
            respond(true, '', $lesson);
        } else {
            respond(false, 'الدرس غير موجود');
        }
        break;
    
    case 'create':
        $data = json_decode(file_get_contents('php://input'), true);
        $title = trim($data['title'] ?? '');
        $description = trim($data['description'] ?? '');
        $content = trim($data['content'] ?? '');
        $category = trim($data['category'] ?? 'عام');
        $difficulty = $data['difficulty'] ?? 'beginner';
        $duration = intval($data['duration_minutes'] ?? 0);
        
        if (!$title) {
            respond(false, 'يرجى إدخال عنوان الدرس');
        }
        
        $stmt = $conn->prepare("INSERT INTO lessons (title, description, content, category, difficulty, duration_minutes, is_published) VALUES (?, ?, ?, ?, ?, ?, 0)");
        $stmt->bind_param("sssssi", $title, $description, $content, $category, $difficulty, $duration);
        
        if ($stmt->execute()) {
            respond(true, 'تم إنشاء الدرس بنجاح', ['id' => $conn->insert_id]);
        } else {
            respond(false, 'فشل إنشاء الدرس');
        }
        break;
    
    case 'update':
        $data = json_decode(file_get_contents('php://input'), true);
        $id = intval($data['id'] ?? 0);
        $title = trim($data['title'] ?? '');
        $description = trim($data['description'] ?? '');
        $content = trim($data['content'] ?? '');
        $category = trim($data['category'] ?? 'عام');
        $difficulty = $data['difficulty'] ?? 'beginner';
        $duration = intval($data['duration_minutes'] ?? 0);
        
        if (!$id || !$title) {
            respond(false, 'معلومات غير كاملة');
        }
        
        $stmt = $conn->prepare("UPDATE lessons SET title = ?, description = ?, content = ?, category = ?, difficulty = ?, duration_minutes = ? WHERE id = ?");
        $stmt->bind_param("sssssii", $title, $description, $content, $category, $difficulty, $duration, $id);
        
        if ($stmt->execute()) {
            respond(true, 'تم تحديث الدرس بنجاح');
        } else {
            respond(false, 'فشل تحديث الدرس');
        }
        break;
    
    case 'delete':
        $id = intval($_GET['id'] ?? 0);
        
        if (!$id) {
            respond(false, 'معرف الدرس مطلوب');
        }
        
        $stmt = $conn->prepare("DELETE FROM lessons WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            respond(true, 'تم حذف الدرس بنجاح');
        } else {
            respond(false, 'فشل حذف الدرس');
        }
        break;
    
    case 'toggle_publish':
        $id = intval($_GET['id'] ?? 0);
        
        if (!$id) {
            respond(false, 'معرف الدرس مطلوب');
        }
        
        $stmt = $conn->prepare("UPDATE lessons SET is_published = NOT is_published WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            respond(true, 'تم تحديث حالة الدرس');
        } else {
            respond(false, 'فشل تحديث الحالة');
        }
        break;
    
    case 'reorder':
        $data = json_decode(file_get_contents('php://input'), true);
        $order = $data['order'] ?? [];
        
        foreach ($order as $index => $lessonId) {
            $stmt = $conn->prepare("UPDATE lessons SET order_num = ? WHERE id = ?");
            $stmt->bind_param("ii", $index, $lessonId);
            $stmt->execute();
            $stmt->close();
        }
        
        respond(true, 'تم تحديث الترتيب');
        break;
    
    case 'get_categories':
        $result = $conn->query("SELECT DISTINCT category FROM lessons ORDER BY category ASC");
        $categories = [];
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row['category'];
        }
        respond(true, '', $categories);
        break;
    
    case 'get_stats':
        $totalLessons = $conn->query("SELECT COUNT(*) as count FROM lessons")->fetch_assoc()['count'];
        $publishedLessons = $conn->query("SELECT COUNT(*) as count FROM lessons WHERE is_published = 1")->fetch_assoc()['count'];
        $totalEnrollments = $conn->query("SELECT COUNT(*) as count FROM lesson_enrollments")->fetch_assoc()['count'];
        $completedEnrollments = $conn->query("SELECT COUNT(*) as count FROM lesson_enrollments WHERE is_completed = 1")->fetch_assoc()['count'];
        
        $topLessons = $conn->query("
            SELECT l.id, l.title, COUNT(e.id) as enroll_count 
            FROM lessons l 
            LEFT JOIN lesson_enrollments e ON l.id = e.lesson_id 
            GROUP BY l.id 
            ORDER BY enroll_count DESC 
            LIMIT 5
        ")->fetch_all(MYSQLI_ASSOC);
        
        respond(true, '', [
            'total_lessons' => $totalLessons,
            'published_lessons' => $publishedLessons,
            'total_enrollments' => $totalEnrollments,
            'completed_enrollments' => $completedEnrollments,
            'top_lessons' => $topLessons
        ]);
        break;
    
    default:
        respond(false, 'إجراء غير معروف');
}