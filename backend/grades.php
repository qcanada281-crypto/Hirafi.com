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
        $craftsman_id = intval($_GET['craftsman_id'] ?? 0);
        
        if ($craftsman_id) {
            $stmt = $conn->prepare("SELECT g.*, c.full_name as craftsman_name 
                FROM grades g 
                LEFT JOIN craftsmen c ON g.craftsman_id = c.id 
                WHERE g.craftsman_id = ? 
                ORDER BY g.created_at DESC");
            $stmt->bind_param("i", $craftsman_id);
        } else {
            $stmt = $conn->prepare("SELECT g.*, c.full_name as craftsman_name 
                FROM grades g 
                LEFT JOIN craftsmen c ON g.craftsman_id = c.id 
                ORDER BY g.created_at DESC 
                LIMIT 100");
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $grades = [];
        while ($row = $result->fetch_assoc()) {
            $grades[] = $row;
        }
        $stmt->close();
        
        respond(true, '', $grades);
        break;
    
    case 'get_craftsmen':
        $search = $_GET['search'] ?? '';
        $status = $_GET['status'] ?? '';
        
        $sql = "SELECT id, craftsman_id, full_name, profession, city, status FROM craftsmen WHERE 1=1";
        
        if ($search) {
            $sql .= " AND (full_name LIKE '%$search%' OR craftsman_id LIKE '%$search%' OR profession LIKE '%$search%')";
        }
        
        if ($status) {
            $sql .= " AND status = '$status'";
        }
        
        $sql .= " ORDER BY full_name ASC LIMIT 50";
        
        $result = $conn->query($sql);
        $craftsmen = [];
        while ($row = $result->fetch_assoc()) {
            $craftsmen[] = $row;
        }
        
        respond(true, '', $craftsmen);
        break;
    
    case 'create':
        $data = json_decode(file_get_contents('php://input'), true);
        $craftsman_id = intval($data['craftsman_id'] ?? 0);
        $grade_label = trim($data['grade_label'] ?? '');
        $grade_value = floatval($data['grade_value'] ?? 0);
        $notes = trim($data['notes'] ?? '');
        
        if (!$craftsman_id || !$grade_label) {
            respond(false, 'يرجى إكمال جميع الحقول المطلوبة');
        }
        
        $stmt = $conn->prepare("INSERT INTO grades (craftsman_id, grade_label, grade_value, notes) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isds", $craftsman_id, $grade_label, $grade_value, $notes);
        
        if ($stmt->execute()) {
            respond(true, 'تم إضافة النقاط بنجاح');
        } else {
            respond(false, 'فشل إضافة النقاط');
        }
        break;
    
    case 'update':
        $data = json_decode(file_get_contents('php://input'), true);
        $id = intval($data['id'] ?? 0);
        $grade_label = trim($data['grade_label'] ?? '');
        $grade_value = floatval($data['grade_value'] ?? 0);
        $notes = trim($data['notes'] ?? '');
        
        if (!$id || !$grade_label) {
            respond(false, 'معلومات غير كاملة');
        }
        
        $stmt = $conn->prepare("UPDATE grades SET grade_label = ?, grade_value = ?, notes = ? WHERE id = ?");
        $stmt->bind_param("sdsi", $grade_label, $grade_value, $notes, $id);
        
        if ($stmt->execute()) {
            respond(true, 'تم تحديث النقاط بنجاح');
        } else {
            respond(false, 'فشل تحديث النقاط');
        }
        break;
    
    case 'delete':
        $id = intval($_GET['id'] ?? 0);
        
        if (!$id) {
            respond(false, 'معرف الدرس مطلوب');
        }
        
        $stmt = $conn->prepare("DELETE FROM grades WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            respond(true, 'تم حذف الدرس بنجاح');
        } else {
            respond(false, 'فشل حذف الدرس');
        }
        break;
    
    case 'get_stats':
        $totalGrades = $conn->query("SELECT COUNT(*) as count FROM grades")->fetch_assoc()['count'];
        $avgGrade = $conn->query("SELECT AVG(grade_value) as avg FROM grades WHERE grade_value IS NOT NULL")->fetch_assoc()['avg'] ?? 0;
        $totalCraftsmen = $conn->query("SELECT COUNT(*) as count FROM craftsmen")->fetch_assoc()['count'];
        $activeCraftsmen = $conn->query("SELECT COUNT(*) as count FROM craftsmen WHERE status = 'active'")->fetch_assoc()['count'];
        $totalMessages = $conn->query("SELECT COUNT(*) as count FROM messages")->fetch_assoc()['count'];
        
        $topCraftsmen = $conn->query("
            SELECT c.id, c.full_name, c.profession, AVG(g.grade_value) as avg_grade, COUNT(g.id) as grades_count
            FROM craftsmen c
            JOIN grades g ON c.id = g.craftsman_id
            WHERE g.grade_value IS NOT NULL
            GROUP BY c.id
            ORDER BY avg_grade DESC
            LIMIT 10
        ")->fetch_all(MYSQLI_ASSOC);
        
        $gradesByMonth = $conn->query("
            SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count
            FROM grades
            GROUP BY month
            ORDER BY month DESC
            LIMIT 12
        ")->fetch_all(MYSQLI_ASSOC);
        
        respond(true, '', [
            'total_grades' => $totalGrades,
            'average_grade' => round($avgGrade, 2),
            'total_craftsmen' => $totalCraftsmen,
            'active_craftsmen' => $activeCraftsmen,
            'total_messages' => $totalMessages,
            'top_craftsmen' => $topCraftsmen,
            'grades_by_month' => $gradesByMonth
        ]);
        break;
    
    default:
        respond(false, 'إجراء غير معروف');
}