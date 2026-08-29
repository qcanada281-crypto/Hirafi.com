<?php
session_start();
header('Content-Type: application/json');
require_once 'config.php';
/** @var mysqli $conn */

// Verify client session
if (!isset($_SESSION['client_id']) || (isset($_SESSION['user_type']) && $_SESSION['user_type'] !== 'client')) {
    echo json_encode(['success' => false, 'message' => 'غير مصرح']);
    exit;
}

$client_id = (int)$_SESSION['client_id'];
$action = $_GET['action'] ?? '';

if ($action === 'update') {
    $full_name = $_POST['full_name'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $city = $_POST['city'] ?? '';

    if (empty($full_name) || empty($phone)) {
        echo json_encode(['success' => false, 'message' => 'الاسم ورقم الهاتف مطلوبان']);
        exit;
    }

    $avatar_path = null;
    // Process avatar upload
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($_FILES['avatar']['type'], $allowed_types)) {
            echo json_encode(['success' => false, 'message' => 'صيغة الصورة غير مدعومة. يرجى رفع JPG, PNG, WEBP']);
            exit;
        }

        if ($_FILES['avatar']['size'] > 5 * 1024 * 1024) { // 5MB MAX
            echo json_encode(['success' => false, 'message' => 'حجم الصورة يجب أن لا يتجاوز 5 ميغا بايت']);
            exit;
        }

        $upload_dir = '../uploads/avatars/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
        $file_name = 'client_' . $client_id . '_' . time() . '.' . $ext;
        $target_path = $upload_dir . $file_name;

        if (move_uploaded_file($_FILES['avatar']['tmp_name'], $target_path)) {
            $avatar_path = 'uploads/avatars/' . $file_name;
        } else {
            echo json_encode(['success' => false, 'message' => 'فشل رفع الصورة']);
            exit;
        }
    }

    if ($avatar_path) {
        $stmt = $conn->prepare("UPDATE clients SET full_name = ?, phone = ?, city = ?, avatar = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $full_name, $phone, $city, $avatar_path, $client_id);
    } else {
        $stmt = $conn->prepare("UPDATE clients SET full_name = ?, phone = ?, city = ? WHERE id = ?");
        $stmt->bind_param("sssi", $full_name, $phone, $city, $client_id);
    }

    if ($stmt->execute()) {
        $_SESSION['full_name'] = $full_name;
        echo json_encode(['success' => true, 'message' => 'تم حفظ التعديلات بنجاح', 'avatar' => $avatar_path ? '../' . $avatar_path : null]);
    } else {
        echo json_encode(['success' => false, 'message' => 'فشل في تحديث البيانات']);
    }
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'إجراء غير صالح']);
}
?>
