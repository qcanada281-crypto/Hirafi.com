<?php
header('Content-Type: application/json; charset=utf-8');
header('Content-Type: application/json');

session_start();

require_once 'config.php';

$response = ['success' => false, 'message' => 'حدث خطأ أثناء التسجيل'];

function normalize_files_array($field_name) {
    if (!isset($_FILES[$field_name])) {
        return [];
    }

    $files = $_FILES[$field_name];
    $normalized = [];

    if (is_array($files['name'])) {
        $count = count($files['name']);
        for ($i = 0; $i < $count; $i++) {
            $error = $files['error'][$i] ?? UPLOAD_ERR_NO_FILE;
            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $normalized[] = [
                'name' => $files['name'][$i] ?? '',
                'type' => $files['type'][$i] ?? '',
                'tmp_name' => $files['tmp_name'][$i] ?? '',
                'error' => $error,
                'size' => (int)($files['size'][$i] ?? 0)
            ];
        }
    } else {
        $error = $files['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($error !== UPLOAD_ERR_NO_FILE) {
            $normalized[] = [
                'name' => $files['name'] ?? '',
                'type' => $files['type'] ?? '',
                'tmp_name' => $files['tmp_name'] ?? '',
                'error' => $error,
                'size' => (int)($files['size'] ?? 0)
            ];
        }
    }

    return $normalized;
}

function map_experience_to_years($experience_label) {
    switch ($experience_label) {
        case '0-1':
            return 1;
        case '1-3':
            return 3;
        case '3-7':
            return 7;
        case '7+':
            return 10;
        default:
            return -1;
    }
}

function ensure_directory($path) {
    if (!is_dir($path) && !mkdir($path, 0755, true) && !is_dir($path)) {
        throw new Exception('تعذر إنشاء مجلد الرفع');
    }
}

function store_uploaded_file($file, $target_dir, $prefix, $allowed_extensions, $max_file_size) {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new Exception('فشل رفع ملف');
    }

    if (($file['size'] ?? 0) <= 0 || ($file['size'] ?? 0) > $max_file_size) {
        throw new Exception('حجم الملف غير صالح');
    }

    $original_name = $file['name'] ?? '';
    $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
    if (!in_array($extension, $allowed_extensions, true)) {
        throw new Exception('نوع ملف غير مدعوم');
    }

    ensure_directory($target_dir);

    $safe_prefix = preg_replace('/[^a-zA-Z0-9_]/', '_', $prefix);
    $new_file_name = $safe_prefix . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
    $absolute_path = rtrim($target_dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $new_file_name;

    if (!move_uploaded_file($file['tmp_name'], $absolute_path)) {
        throw new Exception('تعذر حفظ الملف على الخادم');
    }

    return [
        'file_name' => $new_file_name,
        'size' => (int)$file['size'],
        'mime_type' => $file['type'] ?? 'application/octet-stream'
    ];
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('طريقة الطلب غير صحيحة');
    }

    $full_name = trim($_POST['fullName'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $date_of_birth = trim($_POST['dateOfBirth'] ?? '');
    $craftsman_id_input = strtoupper(trim($_POST['craftsmenID'] ?? ''));
    $experience_label = trim($_POST['experience'] ?? '');
    $specialization = trim($_POST['specialization'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirmPassword'] ?? '';

    if (strlen($full_name) < 3) {
        throw new Exception('الاسم الكامل يجب أن يكون 3 أحرف على الأقل');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('البريد الإلكتروني غير صحيح');
    }

    if (!preg_match('/^(05|06|07)\d{8}$/', $phone)) {
        throw new Exception('رقم الهاتف المغربي يجب أن يتكون من 10 أرقام ويبدأ بـ 05 أو 06 أو 07');
    }

    if ($date_of_birth === '') {
        throw new Exception('تاريخ الميلاد مطلوب');
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_of_birth)) {
        throw new Exception('صيغة تاريخ الميلاد غير صحيحة');
    }
    if (strtotime($date_of_birth) === false || strtotime($date_of_birth) > time()) {
        throw new Exception('تاريخ الميلاد غير صالح');
    }

    $experience_years = map_experience_to_years($experience_label);
    if ($experience_years < 0) {
        throw new Exception('سنوات الخبرة غير صحيحة');
    }

    if ($specialization === '') {
        throw new Exception('المهارة الأساسية مطلوبة');
    }

    if (strlen($password) < 6) {
        throw new Exception('كلمة المرور يجب أن تكون 6 أحرف على الأقل');
    }

    if ($password !== $confirm_password) {
        throw new Exception('كلمتا المرور غير متطابقتين');
    }

    if (!isset($_POST['terms'])) {
        throw new Exception('يجب الموافقة على الشروط والأحكام');
    }

    $check_email_stmt = $conn->prepare("SELECT id FROM craftsmen WHERE email = ? LIMIT 1");
    $check_email_stmt->bind_param("s", $email);
    $check_email_stmt->execute();
    if ($check_email_stmt->get_result()->num_rows > 0) {
        throw new Exception('البريد الإلكتروني مسجل بالفعل');
    }

    $craftsman_id = preg_replace('/[^A-Z0-9]/', '', $craftsman_id_input);
    if ($craftsman_id === '') {
        $craftsman_id = 'CRAFT' . date('y') . random_int(1000, 9999);
    }

    $check_id_stmt = $conn->prepare("SELECT id FROM craftsmen WHERE craftsman_id = ? LIMIT 1");
    $attempts = 0;
    while ($attempts < 10) {
        $check_id_stmt->bind_param("s", $craftsman_id);
        $check_id_stmt->execute();
        if ($check_id_stmt->get_result()->num_rows === 0) {
            break;
        }

        $craftsman_id = 'CRAFT' . date('y') . random_int(1000, 9999);
        $attempts++;
    }

    if ($attempts >= 10) {
        throw new Exception('تعذر إنشاء رقم حرفي فريد');
    }

    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
    $profession = $specialization;
    $status = 'pending';

    $conn->begin_transaction();

    $insert_stmt = $conn->prepare(
        "INSERT INTO craftsmen (
            craftsman_id, full_name, email, phone, password,
            city, profession, specialization, experience_years,
            experience_label, date_of_birth, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );

    $insert_stmt->bind_param(
        "ssssssssisss",
        $craftsman_id,
        $full_name,
        $email,
        $phone,
        $hashed_password,
        $city,
        $profession,
        $specialization,
        $experience_years,
        $experience_label,
        $date_of_birth,
        $status
    );

    if (!$insert_stmt->execute()) {
        throw new Exception('فشل حفظ بيانات الحرفي');
    }

    $craftsman_database_id = (int)$conn->insert_id;

    $base_relative = 'uploads/craftsmen/' . $craftsman_id;
    $base_absolute = dirname(__DIR__) . DIRECTORY_SEPARATOR . $base_relative;
    $documents_relative = $base_relative . '/documents';
    $documents_absolute = $base_absolute . DIRECTORY_SEPARATOR . 'documents';
    $portfolio_relative = $base_relative . '/portfolio';
    $portfolio_absolute = $base_absolute . DIRECTORY_SEPARATOR . 'portfolio';

    ensure_directory($documents_absolute);
    ensure_directory($portfolio_absolute);

    $document_extensions = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
    $portfolio_extensions = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'pdf', 'mp4', 'mov', 'avi', 'mkv'];
    $max_document_size = 8 * 1024 * 1024;
    $max_portfolio_size = 20 * 1024 * 1024;

    $insert_document_stmt = $conn->prepare(
        "INSERT INTO documents (craftsman_id, document_type, file_path, file_name, file_size, mime_type)
         VALUES (?, ?, ?, ?, ?, ?)"
    );

    $insert_portfolio_stmt = $conn->prepare(
        "INSERT INTO artisan_portfolio (craftsman_id, media_type, media_url, title)
         VALUES (?, ?, ?, ?)"
    );

    $experience_files = normalize_files_array('experienceCert');
    foreach ($experience_files as $file) {
        $stored = store_uploaded_file(
            $file,
            $documents_absolute,
            'experience_cert',
            $document_extensions,
            $max_document_size
        );

        $document_type = 'شهادة خبرة';
        $relative_path = $documents_relative . '/' . $stored['file_name'];
        $insert_document_stmt->bind_param(
            "isssis",
            $craftsman_database_id,
            $document_type,
            $relative_path,
            $stored['file_name'],
            $stored['size'],
            $stored['mime_type']
        );
        if (!$insert_document_stmt->execute()) {
            throw new Exception('فشل حفظ وثيقة شهادة الخبرة');
        }
    }

    $other_cert_files = normalize_files_array('otherCerts');
    foreach ($other_cert_files as $file) {
        $stored = store_uploaded_file(
            $file,
            $documents_absolute,
            'other_cert',
            $document_extensions,
            $max_document_size
        );

        $document_type = 'شهادة أخرى';
        $relative_path = $documents_relative . '/' . $stored['file_name'];
        $insert_document_stmt->bind_param(
            "isssis",
            $craftsman_database_id,
            $document_type,
            $relative_path,
            $stored['file_name'],
            $stored['size'],
            $stored['mime_type']
        );
        if (!$insert_document_stmt->execute()) {
            throw new Exception('فشل حفظ الشهادات الأخرى');
        }
    }

    $portfolio_files = normalize_files_array('portfolio');
    foreach ($portfolio_files as $index => $file) {
        $stored = store_uploaded_file(
            $file,
            $portfolio_absolute,
            'portfolio',
            $portfolio_extensions,
            $max_portfolio_size
        );

        $extension = strtolower(pathinfo($stored['file_name'], PATHINFO_EXTENSION));
        $video_extensions = ['mp4', 'mov', 'avi', 'mkv'];
        $media_type = in_array($extension, $video_extensions, true) ? 'video' : 'image';
        $media_url = $portfolio_relative . '/' . $stored['file_name'];
        $media_title = 'ملف أعمال #' . ($index + 1);

        $insert_portfolio_stmt->bind_param(
            "isss",
            $craftsman_database_id,
            $media_type,
            $media_url,
            $media_title
        );
        if (!$insert_portfolio_stmt->execute()) {
            throw new Exception('فشل حفظ ملفات معرض الأعمال');
        }
    }

    $conn->commit();

    $response['success'] = true;
    $response['message'] = 'تم التسجيل بنجاح. حسابك الآن قيد المراجعة ولن يظهر للعملاء حتى تتم الموافقة عليه من الإدارة.';
    $response['craftsmenID'] = $craftsman_id;

} catch (Exception $e) {
    if (isset($conn) && $conn instanceof mysqli) {
        @$conn->rollback();
    }
    $response['message'] = $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>
