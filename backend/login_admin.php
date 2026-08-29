<?php
// ==================== ADMIN LOGIN ====================

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
    $password = $data['password'] ?? '';
    $role = $data['role'] ?? '';

    if (empty($email) || empty($password)) {
        throw new Exception('البريد الإلكتروني وكلمة المرور مطلوبة');
    }

    // Check if admin table exists, if not create it
    $conn->query("CREATE TABLE IF NOT EXISTS admins (
        id INT PRIMARY KEY AUTO_INCREMENT,
        email VARCHAR(255) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        full_name VARCHAR(255),
        role VARCHAR(50) DEFAULT 'admin',
        status VARCHAR(20) DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Check if default admin exists, if not create one
    $check_admin = $conn->query("SELECT * FROM admins WHERE email = 'admin@hirafi.ma'");
    if ($check_admin->num_rows === 0) {
        $default_password = password_hash('admin123', PASSWORD_DEFAULT);
        $conn->query("INSERT INTO admins (email, password, full_name, role) VALUES ('admin@hirafi.ma', '$default_password', 'مدير النظام', 'admin')");
    }

    $stmt = $conn->prepare("SELECT id, email, password, full_name, role FROM admins WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('البريد الإلكتروني غير مسجل');
    }

    $admin = $result->fetch_assoc();

    if ($admin['password'] && password_verify($password, $admin['password'])) {
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_name'] = $admin['full_name'];
        $_SESSION['admin_email'] = $admin['email'];
        $_SESSION['admin_role'] = $admin['role'];
        $_SESSION['user_type'] = 'admin';

        $response['success'] = true;
        $response['message'] = 'تم الدخول بنجاح';
        $response['redirect'] = 'admin_dashboard.html';
    } else {
        throw new Exception('كلمة المرور غير صحيحة');
    }

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>
