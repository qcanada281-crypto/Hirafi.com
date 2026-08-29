<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once 'config.php';

$user_type = $_SESSION['user_type'] ?? null;
$is_client = ($user_type === 'client');
$client_id = $is_client ? (int)$_SESSION['client_id'] : 0;

if (!$is_client) {
    echo json_encode(['success'=>false,'message'=>'يرجى تسجيل الدخول كعميل أولاً'], JSON_UNESCAPED_UNICODE);
    exit;
}

$response   = ['success' => false, 'message' => 'خطأ في الرفع'];
$request_id = (int)($_POST['request_id'] ?? 0);

try {
    if ($request_id <= 0) throw new Exception('رقم الطلب غير صالح');

    // Verify ownership
    $chk = $conn->prepare("SELECT client_id FROM job_requests WHERE id = ? LIMIT 1");
    $chk->bind_param("i", $request_id);
    $chk->execute();
    $jr = $chk->get_result()->fetch_assoc();
    if (!$jr || (int)$jr['client_id'] !== $client_id) throw new Exception('غير مصرح');

    if (empty($_FILES)) throw new Exception('لم يتم اختيار صور');

    $upload_dir_rel = 'uploads/requests/' . $request_id;
    $upload_dir_abs = dirname(__DIR__) . '/' . $upload_dir_rel;
    if (!is_dir($upload_dir_abs)) mkdir($upload_dir_abs, 0755, true);

    $allowed_mimes = [
        'image/jpeg', 'image/png', 'image/webp', 'image/gif',
        'video/mp4', 'video/webm', 'video/ogg', 'video/quicktime',
        'video/x-msvideo', 'video/x-matroska'
    ];
    $max_size      = 30 * 1024 * 1024; // 30MB for videos and high-res photos
    $uploaded      = [];

    $files = $_FILES['photos'] ?? [];
    if (!is_array($files['name'])) {
        $files_arr = [['name'=>$files['name'],'type'=>$files['type'],'tmp_name'=>$files['tmp_name'],'error'=>$files['error'],'size'=>$files['size']]];
    } else {
        $files_arr = [];
        for ($i = 0; $i < count($files['name']); $i++) {
            $files_arr[] = ['name'=>$files['name'][$i],'type'=>$files['type'][$i],'tmp_name'=>$files['tmp_name'][$i],'error'=>$files['error'][$i],'size'=>$files['size'][$i]];
        }
    }

    foreach ($files_arr as $file) {
        if ($file['error'] !== UPLOAD_ERR_OK) continue;
        if ($file['size'] > $max_size) continue;

        $mime = mime_content_type($file['tmp_name']);
        if (!in_array($mime, $allowed_mimes)) continue;

        $ext   = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg';
        $prefix = str_starts_with($mime, 'video/') ? 'video_' : 'photo_';
        $fname = $prefix . time() . '_' . bin2hex(random_bytes(4)) . '.' . strtolower($ext);
        $abs   = $upload_dir_abs . '/' . $fname;
        move_uploaded_file($file['tmp_name'], $abs);

        $rel = $upload_dir_rel . '/' . $fname;
        $ins = $conn->prepare("INSERT INTO job_request_photos (request_id, photo_path) VALUES (?,?)");
        $ins->bind_param("is", $request_id, $rel);
        $ins->execute();
        $uploaded[] = ['id' => (int)$conn->insert_id, 'path' => $rel];
    }

    if (empty($uploaded)) throw new Exception('لم يتم رفع أي ملف صالح. تأكد من استخدام صور JPG/PNG/WEBP/GIF أو فيديو MP4/WEBM/MOV بحجم أقل من 30MB');

    $response['success']  = true;
    $response['message']  = 'تم رفع ' . count($uploaded) . ' صورة بنجاح';
    $response['photos']   = $uploaded;

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>
