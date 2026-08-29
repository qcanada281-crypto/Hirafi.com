<?php
session_start();
require_once 'config.php';

/** @var mysqli|null $conn */
$conn = (isset($conn) && $conn instanceof mysqli) ? $conn : null;
if ($conn === null) {
    http_response_code(500);
    exit('Database connection is not available.');
}

if (
    !isset($_SESSION['craftsman_id']) ||
    (isset($_SESSION['user_type']) && $_SESSION['user_type'] !== 'craftsman')
) {
    header('Location: ../hirafi_login.html');
    exit;
}

$craftsman_database_id = (int)$_SESSION['craftsman_id'];
$allowed_sections = ['overview', 'projects', 'profile', 'job-requests', 'my-proposals'];

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function push_flash_message($type, $message) {
    if (!isset($_SESSION['artisan_dashboard_flash']) || !is_array($_SESSION['artisan_dashboard_flash'])) {
        $_SESSION['artisan_dashboard_flash'] = [];
    }

    $_SESSION['artisan_dashboard_flash'][] = [
        'type' => $type,
        'message' => $message
    ];
}

function normalize_asset_url($path) {
    $path = trim((string)$path);
    if ($path === '') {
        return '';
    }

    if (preg_match('/^(https?:)?\/\//i', $path) === 1 || strpos($path, 'data:') === 0) {
        return $path;
    }

    if (strpos($path, '../') === 0 || strpos($path, '/') === 0) {
        return $path;
    }

    return '../' . ltrim($path, './');
}

function ensure_directory_exists($path) {
    if (!is_dir($path) && !mkdir($path, 0755, true) && !is_dir($path)) {
        throw new RuntimeException('تعذر إنشاء مجلد حفظ الصور.');
    }
}

function normalize_storage_key($raw) {
    $sanitized = preg_replace('/[^A-Za-z0-9_\-]/', '', (string)$raw);
    return $sanitized !== '' ? $sanitized : 'craftsman';
}

function resolve_local_asset_absolute_path($relative_path) {
    $relative_path = trim((string)$relative_path);
    if ($relative_path === '') {
        return '';
    }

    $normalized = str_replace('\\', '/', ltrim($relative_path, '/'));
    $project_root = realpath(__DIR__ . '/..');
    if ($project_root === false) {
        return '';
    }

    return $project_root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized);
}

function store_avatar_upload($file, $storage_key) {
    if (!is_array($file) || !isset($file['error']) || (int)$file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ((int)$file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('فشل رفع الصورة الشخصية.');
    }

    $max_bytes = 3 * 1024 * 1024; // 3MB
    $file_size = (int)($file['size'] ?? 0);
    if ($file_size <= 0 || $file_size > $max_bytes) {
        throw new RuntimeException('حجم الصورة كبير. الحد الأقصى هو 3MB.');
    }

    $tmp_name = (string)($file['tmp_name'] ?? '');
    if ($tmp_name === '' || !is_uploaded_file($tmp_name)) {
        throw new RuntimeException('ملف الصورة غير صالح.');
    }

    $image_info = @getimagesize($tmp_name);
    if ($image_info === false) {
        throw new RuntimeException('الملف المحدد ليس صورة صالحة.');
    }

    $mime_type = (string)($image_info['mime'] ?? '');
    $allowed_mimes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    if (!isset($allowed_mimes[$mime_type])) {
        throw new RuntimeException('صيغة الصورة غير مدعومة. استعمل JPG أو PNG أو WEBP أو GIF.');
    }

    $relative_dir = 'uploads/craftsmen/' . normalize_storage_key($storage_key) . '/avatars';
    $absolute_dir = resolve_local_asset_absolute_path($relative_dir);
    if ($absolute_dir === '') {
        throw new RuntimeException('تعذر الوصول لمسار حفظ الصورة.');
    }
    ensure_directory_exists($absolute_dir);

    $new_filename = 'avatar_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $allowed_mimes[$mime_type];
    $absolute_path = $absolute_dir . DIRECTORY_SEPARATOR . $new_filename;

    if (!move_uploaded_file($tmp_name, $absolute_path)) {
        throw new RuntimeException('تعذر حفظ الصورة على الخادم.');
    }

    return $relative_dir . '/' . $new_filename;
}

function store_portfolio_media_upload($file, $storage_key, $media_type) {
    if (!is_array($file) || !isset($file['error']) || (int)$file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ((int)$file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('فشل رفع ملف الورش.');
    }

    $is_image = $media_type === 'image';
    $max_bytes = $is_image ? (8 * 1024 * 1024) : (30 * 1024 * 1024);
    $file_size = (int)($file['size'] ?? 0);
    if ($file_size <= 0 || $file_size > $max_bytes) {
        throw new RuntimeException($is_image ? 'حجم الصورة كبير. الحد الأقصى 8MB.' : 'حجم الفيديو كبير. الحد الأقصى 30MB.');
    }

    $tmp_name = (string)($file['tmp_name'] ?? '');
    if ($tmp_name === '' || !is_uploaded_file($tmp_name)) {
        throw new RuntimeException('الملف المرفوع غير صالح.');
    }

    $original_name = (string)($file['name'] ?? '');
    $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

    $allowed_image_extensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    $allowed_video_extensions = ['mp4', 'webm', 'ogg', 'mov'];

    if ($is_image) {
        if (!in_array($extension, $allowed_image_extensions, true)) {
            throw new RuntimeException('صيغة الصورة غير مدعومة. استعمل JPG أو PNG أو WEBP أو GIF.');
        }

        $image_info = @getimagesize($tmp_name);
        if ($image_info === false) {
            throw new RuntimeException('الملف المحدد ليس صورة صالحة.');
        }
    } else {
        if (!in_array($extension, $allowed_video_extensions, true)) {
            throw new RuntimeException('صيغة الفيديو غير مدعومة. استعمل MP4 أو WEBM أو OGG أو MOV.');
        }
    }

    $relative_dir = 'uploads/craftsmen/' . normalize_storage_key($storage_key) . '/portfolio';
    $absolute_dir = resolve_local_asset_absolute_path($relative_dir);
    if ($absolute_dir === '') {
        throw new RuntimeException('تعذر الوصول لمسار حفظ الوسائط.');
    }
    ensure_directory_exists($absolute_dir);

    $new_filename = 'portfolio_' . ($is_image ? 'img' : 'video') . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
    $absolute_path = $absolute_dir . DIRECTORY_SEPARATOR . $new_filename;

    if (!move_uploaded_file($tmp_name, $absolute_path)) {
        throw new RuntimeException('تعذر حفظ ملف الورش على الخادم.');
    }

    return $relative_dir . '/' . $new_filename;
}

function delete_managed_avatar_file($avatar_path) {
    $avatar_path = trim((string)$avatar_path);
    if ($avatar_path === '') {
        return;
    }

    $normalized = str_replace('\\', '/', ltrim($avatar_path, '/'));
    if (strpos($normalized, 'uploads/craftsmen/') !== 0) {
        return;
    }

    $absolute_path = resolve_local_asset_absolute_path($normalized);
    if ($absolute_path !== '' && is_file($absolute_path)) {
        @unlink($absolute_path);
    }
}

function delete_managed_portfolio_file($media_path) {
    $media_path = trim((string)$media_path);
    if ($media_path === '') {
        return;
    }

    $normalized = str_replace('\\', '/', ltrim($media_path, '/'));
    if (strpos($normalized, 'uploads/craftsmen/') !== 0 || strpos($normalized, '/portfolio/') === false) {
        return;
    }

    $absolute_path = resolve_local_asset_absolute_path($normalized);
    if ($absolute_path !== '' && is_file($absolute_path)) {
        @unlink($absolute_path);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $target_section = trim($_POST['redirect_section'] ?? 'overview');
    if (!in_array($target_section, $allowed_sections, true)) {
        $target_section = 'overview';
    }

    try {
        $action = trim($_POST['action'] ?? '');
        if ($action === '') {
            throw new RuntimeException('العملية غير محددة.');
        }

        if ($action === 'update_profile') {
            $full_name = trim($_POST['full_name'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $city = trim($_POST['city'] ?? '');
            $specialization = trim($_POST['specialization'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $whatsapp = trim($_POST['whatsapp'] ?? '');
            $working_hours = trim($_POST['working_hours'] ?? '');
            $experience_years = (int)($_POST['experience_years'] ?? 0);
            $bio = trim($_POST['bio'] ?? '');
            $manual_avatar = trim($_POST['avatar'] ?? '');
            $remove_avatar = isset($_POST['remove_avatar']) && (string)$_POST['remove_avatar'] === '1';
            $profession = $specialization;

            if (mb_strlen($full_name) < 3) {
                throw new RuntimeException('الاسم الكامل يجب أن يحتوي على 3 أحرف على الأقل.');
            }

            if ($phone === '') {
                throw new RuntimeException('رقم الهاتف مطلوب.');
            }

            if ($experience_years < 0 || $experience_years > 60) {
                throw new RuntimeException('سنوات الخبرة يجب أن تكون بين 0 و 60.');
            }

            if (mb_strlen($specialization) > 80 || mb_strlen($city) > 80 || mb_strlen($manual_avatar) > 255) {
                throw new RuntimeException('بعض الحقول أطول من الحد المسموح.');
            }

            if (mb_strlen($address) > 180) {
                throw new RuntimeException('العنوان طويل بزاف. قصرو حتى 180 حرف.');
            }

            if (mb_strlen($whatsapp) > 30) {
                throw new RuntimeException('رقم واتساب طويل بزاف. استعمل 30 رقم/حرف كحد أقصى.');
            }

            if (mb_strlen($working_hours) > 120) {
                throw new RuntimeException('حقل أوقات العمل طويل بزاف. قصرو حتى 120 حرف.');
            }

            if ($whatsapp !== '' && !preg_match('/^[0-9+\\s-]+$/u', $whatsapp)) {
                throw new RuntimeException('رقم واتساب خاصو يكون أرقام ومسافات وعلامة + فقط.');
            }

            $current_stmt = $conn->prepare('SELECT avatar, craftsman_id FROM craftsmen WHERE id = ? LIMIT 1');
            if (!$current_stmt) {
                throw new RuntimeException('تعذر جلب بيانات الحرفي الحالية.');
            }
            $current_stmt->bind_param('i', $craftsman_database_id);
            $current_stmt->execute();
            $current_result = $current_stmt->get_result();
            $current_data = $current_result ? $current_result->fetch_assoc() : null;
            $current_avatar = trim((string)($current_data['avatar'] ?? ''));
            $storage_key = normalize_storage_key($current_data['craftsman_id'] ?? ($_SESSION['craftsman_number'] ?? ('id_' . $craftsman_database_id)));

            $avatar = $current_avatar;
            if ($manual_avatar !== '') {
                $avatar = $manual_avatar;
            }
            if ($remove_avatar) {
                $avatar = '';
            }

            $uploaded_avatar = store_avatar_upload($_FILES['avatar_file'] ?? null, $storage_key);
            if ($uploaded_avatar !== null) {
                $avatar = $uploaded_avatar;
            }

            $city = trim($_POST['city'] ?? '');
            if ($city === 'أخرى') {
                $city = trim($_POST['city_manual'] ?? '');
            }

            $update_stmt = $conn->prepare(
                "UPDATE craftsmen
                 SET full_name = ?, phone = ?, city = ?, profession = ?, specialization = ?,
                     experience_years = ?, bio = ?, avatar = ?, address = ?, whatsapp = ?, working_hours = ?
                 WHERE id = ? LIMIT 1"
            );

            if (!$update_stmt) {
                throw new RuntimeException('تعذر تجهيز تحديث الملف الشخصي.');
            }

            $update_stmt->bind_param(
                "sssssisssssi",
                $full_name,
                $phone,
                $city,
                $profession,
                $specialization,
                $experience_years,
                $bio,
                $avatar,
                $address,
                $whatsapp,
                $working_hours,
                $craftsman_database_id
            );

            if (!$update_stmt->execute()) {
                throw new RuntimeException('فشل حفظ بيانات الملف الشخصي.');
            }

            if (($uploaded_avatar !== null || $remove_avatar) && $current_avatar !== '' && $current_avatar !== $avatar) {
                delete_managed_avatar_file($current_avatar);
            }

            push_flash_message('success', 'تم تحديث الملف الشخصي بنجاح.');
        } elseif ($action === 'change_password') {
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';

            if ($current_password === '') {
                throw new RuntimeException('الرقم السري الحالي مطلوب.');
            }

            if (mb_strlen($new_password) < 6) {
                throw new RuntimeException('الرقم السري الجديد يجب أن يكون 6 أحرف على الأقل.');
            }

            if ($new_password !== $confirm_password) {
                throw new RuntimeException('تأكيد الرقم السري لا يتطابق.');
            }

            // Get current password from database
            $pwd_stmt = $conn->prepare('SELECT password FROM craftsmen WHERE id = ? LIMIT 1');
            $pwd_stmt->bind_param('i', $craftsman_database_id);
            $pwd_stmt->execute();
            $pwd_result = $pwd_stmt->get_result();
            $pwd_row = $pwd_result->fetch_assoc();

            if (!$pwd_row || !password_verify($current_password, $pwd_row['password'])) {
                throw new RuntimeException('الرقم السري الحالي غير صحيح.');
            }

            // Hash new password and update
            $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $update_pwd_stmt = $conn->prepare('UPDATE craftsmen SET password = ? WHERE id = ? LIMIT 1');
            $update_pwd_stmt->bind_param('si', $new_password_hash, $craftsman_database_id);

            if (!$update_pwd_stmt->execute()) {
                throw new RuntimeException('فشل تغيير الرقم السري.');
            }

            push_flash_message('success', 'تم تغيير الرقم السري بنجاح.');
        } elseif ($action === 'add_project') {
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $location = trim($_POST['location'] ?? '');
            if ($location === 'أخرى') {
                $location = trim($_POST['location_manual'] ?? '');
            }
            $work_date = trim($_POST['work_date'] ?? '');
            $media_type = trim($_POST['media_type'] ?? 'image');
            $media_url = trim($_POST['media_url'] ?? '');
            $storage_key = normalize_storage_key($_SESSION['craftsman_number'] ?? ('id_' . $craftsman_database_id));

            if (mb_strlen($title) < 3) {
                throw new RuntimeException('عنوان الورش يجب أن يحتوي على 3 أحرف على الأقل.');
            }

            if (!in_array($media_type, ['image', 'video'], true)) {
                throw new RuntimeException('نوع الوسيط غير صحيح.');
            }

            if ($media_url === '') {
                $uploaded_media_path = store_portfolio_media_upload($_FILES['media_file'] ?? null, $storage_key, $media_type);
                if ($uploaded_media_path !== null) {
                    $media_url = $uploaded_media_path;
                }
            }

            if ($media_url === '') {
                throw new RuntimeException('المرجو رفع صورة/فيديو للورش.');
            }

            if (mb_strlen($location) > 100 || mb_strlen($title) > 200 || mb_strlen($media_url) > 255) {
                throw new RuntimeException('هناك حقل يتجاوز الطول المسموح.');
            }

            if ($work_date !== '') {
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $work_date) !== 1 || strtotime($work_date) === false) {
                    throw new RuntimeException('تاريخ الإنجاز غير صالح.');
                }
            } else {
                $work_date = null;
            }

            $insert_stmt = $conn->prepare(
                "INSERT INTO artisan_portfolio
                    (craftsman_id, media_type, media_url, title, description, location, work_date)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );

            if (!$insert_stmt) {
                throw new RuntimeException('تعذر تجهيز إضافة الورش.');
            }

            $insert_stmt->bind_param(
                "issssss",
                $craftsman_database_id,
                $media_type,
                $media_url,
                $title,
                $description,
                $location,
                $work_date
            );

            if (!$insert_stmt->execute()) {
                throw new RuntimeException('فشل إضافة الورش.');
            }

            push_flash_message('success', 'تمت إضافة الورش الجديد إلى قائمة الأعمال.');
        } elseif ($action === 'delete_project') {
            $project_id = (int)($_POST['project_id'] ?? 0);
            if ($project_id <= 0) {
                throw new RuntimeException('رقم الورش غير صالح.');
            }

            $media_stmt = $conn->prepare(
                "SELECT media_url FROM artisan_portfolio
                 WHERE id = ? AND craftsman_id = ?
                 LIMIT 1"
            );

            if (!$media_stmt) {
                throw new RuntimeException('تعذر تجهيز جلب بيانات الورش.');
            }

            $media_stmt->bind_param("ii", $project_id, $craftsman_database_id);
            $media_stmt->execute();
            $media_result = $media_stmt->get_result();
            $media_row = $media_result ? $media_result->fetch_assoc() : null;
            $project_media_url = trim((string)($media_row['media_url'] ?? ''));

            $delete_stmt = $conn->prepare(
                "DELETE FROM artisan_portfolio
                 WHERE id = ? AND craftsman_id = ?
                 LIMIT 1"
            );

            if (!$delete_stmt) {
                throw new RuntimeException('تعذر تجهيز حذف الورش.');
            }

            $delete_stmt->bind_param("ii", $project_id, $craftsman_database_id);

            if (!$delete_stmt->execute()) {
                throw new RuntimeException('فشل حذف الورش.');
            }

            if ($delete_stmt->affected_rows < 1) {
                throw new RuntimeException('لم يتم العثور على الورش المطلوب أو لا تملك صلاحية حذفه.');
            }

            if ($project_media_url !== '') {
                delete_managed_portfolio_file($project_media_url);
            }

            push_flash_message('success', 'تم حذف الورش بنجاح.');
        } else {
            throw new RuntimeException('الإجراء غير معروف.');
        }
    } catch (Throwable $e) {
        push_flash_message('error', $e->getMessage());
    }

    header('Location: artisan_dashboard.php?section=' . urlencode($target_section));
    exit;
}

$stmt = $conn->prepare("SELECT * FROM craftsmen WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $craftsman_database_id);
$stmt->execute();
$result = $stmt->get_result();
$artisan = $result ? $result->fetch_assoc() : null;

if (!$artisan) {
    session_destroy();
    header('Location: ../hirafi_login.html');
    exit;
}

$portfolio = [];
$stmt2 = $conn->prepare(
    "SELECT id, media_type, media_url, title, description, location, work_date, views_count, created_at
     FROM artisan_portfolio
     WHERE craftsman_id = ?
     ORDER BY created_at DESC"
);
$stmt2->bind_param("i", $craftsman_database_id);
$stmt2->execute();
$result2 = $stmt2->get_result();
if ($result2) {
    $portfolio = $result2->fetch_all(MYSQLI_ASSOC);
}

$flash_messages = [];
if (isset($_SESSION['artisan_dashboard_flash']) && is_array($_SESSION['artisan_dashboard_flash'])) {
    $flash_messages = $_SESSION['artisan_dashboard_flash'];
}
unset($_SESSION['artisan_dashboard_flash']);

$display_name = $artisan['full_name'] ?? '';
$display_number = $artisan['craftsman_id'] ?? '';
$display_profession = $artisan['specialization'] ?: ($artisan['profession'] ?? '');
$display_city = $artisan['city'] ?? '';
$display_phone = $artisan['phone'] ?? '';
$display_email = $artisan['email'] ?? '';
$display_bio = $artisan['bio'] ?? '';
$display_address = $artisan['address'] ?? '';
$display_whatsapp = $artisan['whatsapp'] ?? '';
$display_working_hours = $artisan['working_hours'] ?? '';
$display_experience = (int)($artisan['experience_years'] ?? 0);
$display_rating = $artisan['rating'] ?? '0.00';
$display_reviews = (int)($artisan['total_reviews'] ?? 0);
$display_image = trim((string)($artisan['avatar'] ?: ($artisan['profile_image'] ?? '')));

if ($display_image === '') {
    $display_image = '../img/default-avatar.png';
} else {
    $display_image = normalize_asset_url($display_image);
}

$total_projects = count($portfolio);
$image_projects = 0;
$video_projects = 0;
$total_views = 0;

foreach ($portfolio as $item) {
    if (($item['media_type'] ?? '') === 'video') {
        $video_projects++;
    } else {
        $image_projects++;
    }
    $total_views += (int)($item['views_count'] ?? 0);
}

$active_section = trim($_GET['section'] ?? 'overview');
if (!in_array($active_section, $allowed_sections, true)) {
    $active_section = 'overview';
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة الحرفي - <?php echo h($display_name); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root{
            --bg:#f1f5f9;
            --panel:#ffffff;
            --ink:#0f172a;
            --muted:#475569;
            --line:#d9e2ec;
            --brand:#0f766e;
            --brand-strong:#115e59;
            --accent:#ea580c;
            --danger:#b91c1c;
            --ok:#15803d;
            --radius:18px;
            --shadow:0 18px 36px rgba(15,23,42,.12);
        }
        *{box-sizing:border-box}
        body{
            margin:0;
            font-family:"Cairo",sans-serif;
            background:
                radial-gradient(circle at 10% 10%, rgba(20,184,166,.18) 0, transparent 34%),
                radial-gradient(circle at 85% 16%, rgba(251,146,60,.16) 0, transparent 30%),
                radial-gradient(circle at 50% 90%, rgba(59,130,246,.15) 0, transparent 36%),
                var(--bg);
            color:var(--ink);
        }
        .shell{
            max-width:1360px;
            margin:22px auto;
            padding:0 14px;
            display:grid;
            grid-template-columns:315px minmax(0,1fr);
            gap:16px;
        }
        .side{
            background:linear-gradient(170deg,#0f172a 0,#1e293b 46%,#0b625a 100%);
            color:#f8fafc;
            border-radius:24px;
            padding:22px;
            box-shadow:var(--shadow);
            position:sticky;
            top:14px;
            height:fit-content;
        }
        .me{text-align:center;padding-bottom:18px;border-bottom:1px solid rgba(226,232,240,.32);margin-bottom:16px}
        .me img{width:110px;height:110px;border-radius:50%;object-fit:cover;border:4px solid rgba(255,255,255,.84);box-shadow:0 10px 24px rgba(2,6,23,.35)}
        .me h2{margin:12px 0 5px;font-size:1.24rem}
        .me p{margin:4px 0;font-size:.9rem;color:#dbeafe}
        .chip{display:inline-block;margin-top:8px;padding:4px 11px;border-radius:999px;background:rgba(20,184,166,.24);border:1px solid rgba(153,246,228,.45);font-size:.82rem}
        .tabs{display:flex;flex-direction:column;gap:8px}
        .tabs button{
            border:0;
            background:rgba(255,255,255,.08);
            color:#fff;
            padding:11px 12px;
            border-radius:12px;
            font:inherit;
            cursor:pointer;
            text-align:right;
            display:flex;
            justify-content:space-between;
            align-items:center;
            transition:.22s ease;
        }
        .tabs button.active{
            background:#fff;
            color:#0f172a;
            font-weight:800;
            transform:translateY(-1px);
        }
        .tabs button:hover{background:rgba(255,255,255,.18)}
        .logout{margin-top:14px;padding-top:14px;border-top:1px solid rgba(226,232,240,.3)}
        .logout a{
            display:flex;
            justify-content:center;
            gap:8px;
            text-decoration:none;
            color:#fee2e2;
            background:rgba(185,28,28,.25);
            border:1px solid rgba(254,202,202,.52);
            padding:11px;
            border-radius:11px;
            transition:.2s ease;
        }
        .logout a:hover{background:rgba(185,28,28,.35)}
        .main{display:flex;flex-direction:column;gap:14px}
        .head{
            background:linear-gradient(125deg,#ffffff 0,#ecfeff 54%,#fff7ed 100%);
            border:1px solid var(--line);
            border-radius:22px;
            padding:16px 18px;
            box-shadow:var(--shadow);
            display:flex;
            justify-content:space-between;
            gap:12px;
            align-items:center;
            flex-wrap:wrap;
        }
        .head h1{margin:0;font-size:1.36rem}
        .head p{margin:5px 0 0;color:var(--muted);font-size:.93rem}
        .badge{
            background:#fffbeb;
            border:1px solid #fed7aa;
            color:#9a3412;
            border-radius:999px;
            padding:7px 11px;
            font-size:.81rem;
            font-weight:800;
        }
        .head-messages-btn {
            background: none;
            border: none;
            color: var(--muted);
            cursor: pointer;
            font-size: 1.2rem;
            padding: 0.5rem;
            position: relative;
            transition: color 0.3s;
        }
        .head-messages-btn:hover {
            color: var(--brand);
        }
        .head-messages-btn .msg-badge {
            position: absolute;
            top: 0;
            right: 0;
            background: #ef4444;
            color: white;
            font-size: 0.65rem;
            padding: 0.15rem 0.4rem;
            border-radius: 10px;
            display: none;
        }
        .head-messages-btn .msg-badge.show {
            display: block;
        }
        .flash{padding:11px 13px;border-radius:12px;border:1px solid transparent;font-size:.9rem}
        .ok{background:#ecfdf3;border-color:#86efac;color:var(--ok)}
        .err{background:#fff1f2;border-color:#fecdd3;color:#9f1239}
        .section{
            display:none;
            background:var(--panel);
            border:1px solid var(--line);
            border-radius:22px;
            padding:18px;
            box-shadow:var(--shadow);
        }
        .section.active{display:block;animation:sectionIn .24s ease both}
        .title{margin:0 0 14px;font-size:1.15rem;display:flex;gap:8px;align-items:center}
        .stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}
        .card{
            border:1px solid var(--line);
            background:linear-gradient(160deg,#ffffff,#f8fafc);
            padding:13px;
            border-radius:13px;
        }
        .card .v{font-size:1.45rem;font-weight:800;color:var(--brand-strong)}
        .card .l{font-size:.85rem;color:var(--muted)}
        .form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
        .fg{display:flex;flex-direction:column;gap:6px}
        .full{grid-column:1/-1}
        label{font-size:.86rem;color:var(--muted);font-weight:700}
        input,select,textarea{
            border:1px solid var(--line);
            border-radius:11px;
            padding:10px 12px;
            font:inherit;
            background:#fff;
        }
        input:focus,select:focus,textarea:focus{
            outline:0;
            border-color:var(--brand);
            box-shadow:0 0 0 4px rgba(15,118,110,.14);
        }
        textarea{min-height:104px;resize:vertical}
        .actions{margin-top:10px;display:flex;gap:10px;flex-wrap:wrap}
        .btn{
            border:0;
            border-radius:11px;
            padding:10px 14px;
            font:inherit;
            font-weight:800;
            cursor:pointer;
            transition:.2s ease;
        }
        .btn:hover{transform:translateY(-1px)}
        .pri{background:linear-gradient(120deg,var(--brand),var(--brand-strong));color:#fff}
        .pri:hover{filter:brightness(.95)}
        .danger{background:#fee2e2;color:var(--danger);border:1px solid #fca5a5}
        .danger:hover{background:#fecaca}
        .subsec{margin-top:18px;padding-top:14px;border-top:1px dashed #cbd5e1}
        .subsec h3{margin:0 0 10px;font-size:1rem;color:#0f172a;display:flex;gap:8px;align-items:center}
        .avatar-upload-box{
            border:1px dashed #93c5fd;
            border-radius:14px;
            padding:10px;
            display:grid;
            grid-template-columns:110px minmax(0,1fr);
            gap:11px;
            background:#f8fbff;
        }
        .avatar-upload-box img{
            width:110px;
            height:110px;
            border-radius:12px;
            object-fit:cover;
            border:1px solid #bfdbfe;
            background:#fff;
        }
        .avatar-upload-controls{display:grid;gap:8px}
        .avatar-upload-controls small{color:var(--muted);font-size:.82rem}
        .check-inline{display:flex;align-items:center;gap:8px;font-size:.88rem;color:#334155}
        .check-inline input{width:auto}
        .manual-avatar{
            margin-top:8px;
            background:#f8fafc;
            border:1px solid #e2e8f0;
            border-radius:10px;
            padding:8px 10px;
        }
        .manual-avatar summary{cursor:pointer;font-weight:700;color:#334155}
        .manual-avatar input{margin-top:8px}
        .grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(245px,1fr));gap:11px;margin-top:12px}
        .proj{border:1px solid var(--line);border-radius:13px;overflow:hidden;background:#fff;display:flex;flex-direction:column}
        .cover{height:176px;background:#e2e8f0;position:relative}
        .cover img,.cover video{width:100%;height:100%;object-fit:cover;display:block}
        .ptype{position:absolute;top:10px;left:10px;background:rgba(15,23,42,.86);color:#fff;border-radius:999px;padding:3px 8px;font-size:.74rem}
        .body{padding:11px;display:flex;flex-direction:column;gap:7px;flex:1}
        .body h3{margin:0;font-size:1rem}
        .meta{font-size:.84rem;color:var(--muted);display:flex;gap:8px;flex-wrap:wrap}
        .desc{margin:0;font-size:.88rem;line-height:1.45;color:#1e293b;flex:1}
        .mini-list{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:10px}
        .mini-item{border:1px solid var(--line);background:#f8fafc;border-radius:12px;padding:10px;display:flex;flex-direction:column;gap:8px}
        .mini-item h4{margin:0;font-size:.96rem;color:#0f172a}
        .mini-meta{font-size:.82rem;color:var(--muted);display:flex;gap:8px;flex-wrap:wrap}
        .empty{padding:22px;border:1px dashed #cbd5e1;border-radius:12px;text-align:center;color:var(--muted);background:#f8fafc}
        @keyframes sectionIn{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}
        @media (max-width:1100px){.shell{grid-template-columns:1fr}.side{position:static}}
        @media (max-width:780px){
            .stats{grid-template-columns:repeat(2,minmax(0,1fr))}
            .form{grid-template-columns:1fr}
            .avatar-upload-box{grid-template-columns:1fr}
            .avatar-upload-box img{width:100%;max-width:180px;height:180px}
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(15,23,42,0.6);
            backdrop-filter: blur(8px);
            align-items: center;
            justify-content: center;
        }
        .modal.show {
            display: flex;
            animation: fadeIn 0.3s ease;
        }
        .modal-content-proposal {
            background: linear-gradient(135deg, rgba(255,255,255,0.95) 0%, rgba(240,253,250,0.95) 100%);
            border: 1px solid rgba(255,255,255,0.6);
            box-shadow: 0 20px 40px rgba(15,23,42,0.15);
            border-radius: 20px;
            padding: 24px;
            width: 90%;
            max-width: 550px;
            position: relative;
            animation: slideUp 0.3s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .badge-saas {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 700;
            text-align: center;
        }
        .badge-pending {
            background: #fffbeb;
            color: #b45309;
            border: 1px solid #fde68a;
        }
        .badge-under_review {
            background: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
        }
        .badge-resolved {
            background: #ecfdf5;
            color: #047857;
            border: 1px solid #a7f3d0;
        }
        .badge-rejected {
            background: #fff1f2;
            color: #b91c1c;
            border: 1px solid #fecdd3;
        }
        .badge-accepted {
            background: #ecfdf5;
            color: #047857;
            border: 1px solid #a7f3d0;
        }
        table.premium {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        table.premium th, table.premium td {
            padding: 12px;
            text-align: right;
            border-bottom: 1px solid var(--line);
        }
        table.premium th {
            background: rgba(15, 118, 110, 0.05);
            color: var(--brand-strong);
            font-weight: 700;
        }
        table.premium tr:hover {
            background: rgba(0, 0, 0, 0.01);
        }

        /* ── Premium Language Pill Switcher ── */
        .lang-pill-switcher {
            display: inline-flex;
            background: #f1f5f9;
            border-radius: 999px;
            padding: 4px;
            gap: 2px;
            border: 1px solid var(--line, #e2e8f0);
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        .lang-opt {
            border: none;
            background: transparent;
            border-radius: 999px;
            padding: 5px 14px;
            font-size: 0.78rem;
            font-weight: 700;
            cursor: pointer;
            color: #64748b;
            transition: all 0.22s ease;
            letter-spacing: 0.4px;
            font-family: inherit;
        }
        .lang-opt:hover { color: var(--brand-strong, #0f766e); }
        .lang-opt.active {
            background: #3b82f6;
            color: #fff;
            box-shadow: 0 2px 10px rgba(59,130,246,0.38);
        }
    </style>
</head>
<body>
    <div class="shell">
        <aside class="side">
            <div class="me">
                <img src="<?php echo h($display_image); ?>" alt="صورة الحرفي">
                <h2><?php echo h($display_name); ?></h2>
                <p><?php echo h($display_profession !== '' ? $display_profession : 'حرفي محترف'); ?></p>
                <p><i class="fas fa-location-dot"></i> <?php echo h($display_city !== '' ? $display_city : 'المدينة غير محددة'); ?></p>
                <span class="chip">رقم الحرفي: <?php echo h($display_number); ?></span>
            </div>
            <nav class="tabs">
                <button type="button" data-section="overview" class="<?php echo $active_section === 'overview' ? 'active' : ''; ?>"><span>نظرة عامة</span><i class="fas fa-chart-line"></i></button>
                <button type="button" data-section="projects" class="<?php echo $active_section === 'projects' ? 'active' : ''; ?>"><span>إدارة الأوراش</span><i class="fas fa-briefcase"></i></button>
                <button type="button" data-section="job-requests" class="<?php echo $active_section === 'job-requests' ? 'active' : ''; ?>" onclick="loadJobRequests();"><span>طلبات العمل المتاحة</span><i class="fas fa-list-check"></i></button>
                <button type="button" data-section="my-proposals" class="<?php echo $active_section === 'my-proposals' ? 'active' : ''; ?>" onclick="loadMyProposals();"><span>عروضي المقترحة</span><i class="fas fa-paper-plane"></i></button>
                <button type="button" data-section="profile" class="<?php echo $active_section === 'profile' ? 'active' : ''; ?>"><span>الملف الشخصي</span><i class="fas fa-user-pen"></i></button>
            </nav>
            <div class="logout"><a href="logout.php"><i class="fas fa-right-from-bracket"></i><span>تسجيل الخروج</span></a></div>
        </aside>

        <main class="main">
            <header class="head">
                <div>
                    <h1>لوحة تحكم الحرفي</h1>
                    <p>تحكم في بروفايلك وأضف أو احذف الأوراش ديالك بسهولة.</p>
                </div>
                <div style="display:flex;align-items:center;gap:1rem;">
                    <a class="head-messages-btn" href="messages_center.php" title="الرسائل" style="text-decoration:none;display:inline-block;">
                        <i class="fas fa-envelope"></i>
                        <span class="msg-badge" id="msgBadge">0</span>
                    </a>
                    <div class="badge"><i class="fas fa-star"></i> <?php echo h((string)$display_rating); ?>/5 (<?php echo h((string)$display_reviews); ?> تقييم)</div>
                    <div class="lang-pill-switcher" id="langSwitcher">
                        <button class="lang-opt" id="btn-fr" onclick="setLang('fr')">🇫🇷 FR</button>
                        <button class="lang-opt" id="btn-ar" onclick="setLang('ar')">🇲🇦 AR</button>
                    </div>
                </div>
            </header>

            <?php foreach ($flash_messages as $flash): ?>
                <?php $ok = ($flash['type'] ?? '') === 'success'; ?>
                <div class="flash <?php echo $ok ? 'ok' : 'err'; ?>"><?php echo h($flash['message'] ?? ''); ?></div>
            <?php endforeach; ?>

            <?php if (isset($artisan['status']) && $artisan['status'] === 'pending'): ?>
                <div class="flash" style="background:#fff7ed; border-color:#fdba74; color:#c2410c; padding: 15px; font-weight: bold; font-size: 1rem; text-align: center; border-radius: 12px; margin-bottom: 10px; border-width: 2px;">
                    <i class="fas fa-exclamation-triangle" style="margin-left: 8px;"></i>
                    حسابك حالياً قيد المراجعة من طرف الإدارة. يمكنك إضافة صور أعمالك وتحديث ملفك، لكنه لن يظهر للعملاء حتى يتم قبوله.
                </div>
            <?php endif; ?>

            <!-- OVERVIEW -->
            <section id="section-overview" class="section <?php echo $active_section === 'overview' ? 'active' : ''; ?>">
                <h2 class="title"><i class="fas fa-chart-pie"></i> نظرة سريعة</h2>
                <div class="stats">
                    <div class="card"><div class="v"><?php echo h((string)$total_projects); ?></div><div class="l">مجموع الأوراش</div></div>
                    <div class="card"><div class="v"><?php echo h((string)$image_projects); ?></div><div class="l">صور الأعمال</div></div>
                    <div class="card"><div class="v"><?php echo h((string)$video_projects); ?></div><div class="l">فيديوهات الأعمال</div></div>
                    <div class="card"><div class="v"><?php echo h((string)$total_views); ?></div><div class="l">إجمالي المشاهدات</div></div>
                </div>
                <div style="margin-top:14px">
                    <?php if (empty($portfolio)): ?>
                        <div class="empty">مازال ما ضفتي حتى ورش. دخل لقسم إدارة الأوراش وزيد أول مشروع.</div>
                    <?php else: ?>
                        <div class="grid">
                            <?php foreach (array_slice($portfolio, 0, 4) as $item): ?>
                                <article class="proj">
                                    <?php $preview = normalize_asset_url($item['media_url'] ?? ''); $is_video = ($item['media_type'] ?? '') === 'video'; ?>
                                    <div class="cover">
                                        <?php if ($preview !== ''): ?>
                                            <?php if ($is_video): ?>
                                                <video controls preload="metadata"><source src="<?php echo h($preview); ?>"></video>
                                            <?php else: ?>
                                                <img src="<?php echo h($preview); ?>" alt="<?php echo h($item['title'] ?: 'مشروع'); ?>">
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        <span class="ptype"><?php echo $is_video ? 'فيديو' : 'صورة'; ?></span>
                                    </div>
                                    <div class="body">
                                        <h3><?php echo h($item['title'] ?: 'بدون عنوان'); ?></h3>
                                        <p class="meta">
                                            <span><i class="fas fa-location-dot"></i> <?php echo h($item['location'] ?: 'غير محدد'); ?></span>
                                            <span><i class="fas fa-eye"></i> <?php echo h((string)($item['views_count'] ?? 0)); ?></span>
                                        </p>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <!-- PROJECTS -->
            <section id="section-projects" class="section <?php echo $active_section === 'projects' ? 'active' : ''; ?>">
                <h2 class="title"><i class="fas fa-briefcase"></i> إدارة الأوراش</h2>
                <form method="post" action="artisan_dashboard.php?section=projects" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add_project">
                    <input type="hidden" name="redirect_section" value="projects">
                    <div class="form">
                        <div class="fg"><label for="title">عنوان الورش</label><input id="title" name="title" type="text" maxlength="200" required></div>
                        <div class="fg"><label for="media_type">نوع الوسيط</label><select id="media_type" name="media_type"><option value="image">صورة</option><option value="video">فيديو</option></select></div>
                        <div class="fg">
                            <label for="location">المكان</label>
                            <select id="location" name="location" onchange="toggleManualCity(this, 'location_manual')">
                                <option value="">اختر المدينة</option>
                                <option value="الدار البيضاء">الدار البيضاء</option>
                                <option value="الرباط">الرباط</option>
                                <option value="مراكش">مراكش</option>
                                <option value="فاس">فاس</option>
                                <option value="طنجة">طنجة</option>
                                <option value="أكادير">أكادير</option>
                                <option value="مكناس">مكناس</option>
                                <option value="وجدة">وجدة</option>
                                <option value="القنيطرة">القنيطرة</option>
                                <option value="تطوان">تطوان</option>
                                <option value="آسفي">آسفي</option>
                                <option value="المحمدية">المحمدية</option>
                                <option value="خريبكة">خريبكة</option>
                                <option value="الجديدة">الجديدة</option>
                                <option value="بني ملال">بني ملال</option>
                                <option value="الناظور">الناظور</option>
                                <option value="أخرى">أخرى (أدخل يدويا)</option>
                            </select>
                            <input type="text" id="location_manual" name="location_manual" placeholder="أدخل اسم المدينة هنا" style="display:none; margin-top:8px;" maxlength="100">
                        </div>
                        <div class="fg"><label for="work_date">تاريخ الإنجاز</label><input id="work_date" name="work_date" type="date"></div>
                        <div class="fg full">
                            <label for="media_url">رابط/مسار الصورة أو الفيديو</label>
                            <input id="media_url" name="media_url" type="text" maxlength="255" placeholder="uploads/craftsmen/... أو https://...">
                        </div>
                        <div class="fg full">
                            <label for="media_file">أو ارفع ملف (صورة/فيديو) من حاسوبك</label>
                            <input id="media_file" name="media_file" type="file" accept="image/*,video/*" style="padding:8px;border:1px solid #cbd5e1;border-radius:8px;width:100%;max-width:400px;">
                        </div>
                        <div class="fg full"><label for="description">وصف الورش</label><textarea id="description" name="description" placeholder="قدم تفاصيل العمل والخدمة..."></textarea></div>
                    </div>
                    <div class="actions"><button type="submit" class="btn pri"><i class="fas fa-plus"></i> إضافة ورش جديد</button></div>
                </form>

                <hr style="border:0;border-top:1px solid var(--line);margin:18px 0;">

                <?php if (empty($portfolio)): ?>
                    <div class="empty">لا يوجد أي ورش مسجل حاليا.</div>
                <?php else: ?>
                    <div class="grid">
                        <?php foreach ($portfolio as $item): ?>
                            <?php $preview = normalize_asset_url($item['media_url'] ?? ''); $is_video = ($item['media_type'] ?? '') === 'video'; ?>
                            <article class="proj">
                                <div class="cover">
                                    <?php if ($preview !== ''): ?>
                                        <?php if ($is_video): ?>
                                            <video controls preload="metadata"><source src="<?php echo h($preview); ?>"></video>
                                        <?php else: ?>
                                            <img src="<?php echo h($preview); ?>" alt="<?php echo h($item['title'] ?: 'مشروع'); ?>">
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <span class="ptype"><?php echo $is_video ? 'فيديو' : 'صورة'; ?></span>
                                </div>
                                <div class="body">
                                    <h3><?php echo h($item['title'] ?: 'بدون عنوان'); ?></h3>
                                    <p class="meta">
                                        <span><i class="fas fa-location-dot"></i> <?php echo h($item['location'] ?: 'غير محدد'); ?></span>
                                        <span><i class="fas fa-eye"></i> <?php echo h((string)($item['views_count'] ?? 0)); ?></span>
                                        <?php if (!empty($item['work_date'])): ?><span><i class="fas fa-calendar"></i> <?php echo h($item['work_date']); ?></span><?php endif; ?>
                                    </p>
                                    <p class="desc"><?php echo h($item['description'] ?: 'بدون وصف.'); ?></p>
                                    <form method="post" action="artisan_dashboard.php?section=projects" onsubmit="return confirm('واش متأكد بغيتي تمسح هاد الورش؟');">
                                        <input type="hidden" name="action" value="delete_project">
                                        <input type="hidden" name="redirect_section" value="projects">
                                        <input type="hidden" name="project_id" value="<?php echo h((string)$item['id']); ?>">
                                        <button type="submit" class="btn danger"><i class="fas fa-trash"></i> حذف الورش</button>
                                    </form>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <!-- PROFILE -->
            <section id="section-profile" class="section <?php echo $active_section === 'profile' ? 'active' : ''; ?>">
                <h2 class="title"><i class="fas fa-user-pen"></i> تعديل الملف الشخصي</h2>
                <form method="post" action="artisan_dashboard.php?section=profile" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="update_profile">
                    <input type="hidden" name="redirect_section" value="profile">
                    <div class="form">
                        <div class="fg"><label for="full_name">الاسم الكامل</label><input id="full_name" name="full_name" type="text" value="<?php echo h($display_name); ?>" maxlength="120" required></div>
                        <div class="fg"><label for="phone">رقم الهاتف</label><input id="phone" name="phone" type="text" value="<?php echo h($display_phone); ?>" maxlength="30" required></div>
                        <div class="fg"><label for="whatsapp">WhatsApp</label><input id="whatsapp" name="whatsapp" type="text" value="<?php echo h($display_whatsapp); ?>" maxlength="30" placeholder="رقم واتساب (اختياري)"></div>
                        <div class="fg"><label for="email">البريد الإلكتروني</label><input id="email" type="email" value="<?php echo h($display_email); ?>" readonly></div>
                        <div class="fg">
                            <label for="city">المدينة</label>
                            <select id="city" name="city" onchange="toggleManualCity(this, 'city_manual')">
                                <option value="">اختر المدينة</option>
                                <?php
                                $citiesList = ['الدار البيضاء', 'الرباط', 'مراكش', 'فاس', 'طنجة', 'أكادير', 'مكناس', 'وجدة', 'القنيطرة', 'تطوان', 'آسفي', 'المحمدية', 'خريبكة', 'الجديدة', 'بني ملال', 'الناظور'];
                                $isOtherCity = true;
                                foreach($citiesList as $c) {
                                    $sel = ($display_city === $c) ? 'selected' : '';
                                    if ($display_city === $c || empty($display_city)) $isOtherCity = false;
                                    echo "<option value=\"$c\" $sel>$c</option>";
                                }
                                ?>
                                <option value="أخرى" <?php echo $isOtherCity ? 'selected' : ''; ?>>أخرى (أدخل يدويا)</option>
                            </select>
                            <input type="text" id="city_manual" name="city_manual" placeholder="أدخل اسم المدينة هنا" style="<?php echo $isOtherCity ? 'display:block; margin-top:8px;' : 'display:none; margin-top:8px;'; ?>" value="<?php echo $isOtherCity ? h($display_city) : ''; ?>" maxlength="80">
                        </div>
                        <div class="fg full"><label for="address">العنوان</label><input id="address" name="address" type="text" value="<?php echo h($display_address); ?>" maxlength="180" placeholder="الشارع، الحي، أقرب نقطة دالة..."></div>
                        <div class="fg"><label for="specialization">التخصص</label><input id="specialization" name="specialization" type="text" value="<?php echo h($display_profession); ?>" maxlength="80"></div>
                        <div class="fg"><label for="experience_years">سنوات الخبرة</label><input id="experience_years" name="experience_years" type="number" min="0" max="60" value="<?php echo h((string)$display_experience); ?>"></div>
                        <div class="fg full"><label for="working_hours">أوقات العمل</label><input id="working_hours" name="working_hours" type="text" value="<?php echo h($display_working_hours); ?>" maxlength="120" placeholder="مثال: من 9 صباحاً إلى 6 مساءً (الإثنين - السبت)"></div>
                        <div class="fg full">
                            <label for="avatar_file">الصورة الشخصية (من جهازك)</label>
                            <div class="avatar-upload-box">
                                <img id="avatarPreview" src="<?php echo h($display_image); ?>" alt="معاينة الصورة الشخصية الحالية">
                                <div class="avatar-upload-controls">
                                    <input id="avatar_file" name="avatar_file" type="file" accept="image/jpeg,image/png,image/webp,image/gif">
                                    <small>الصيغ المسموحة: JPG, PNG, WEBP, GIF. الحجم الأقصى: 3MB.</small>
                                    <label class="check-inline"><input type="checkbox" name="remove_avatar" value="1"> حذف الصورة الحالية</label>
                                </div>
                            </div>
                            <details class="manual-avatar">
                                <summary>إدخال رابط/مسار الصورة يدويا (اختياري)</summary>
                                <input id="avatar" name="avatar" type="text" value="<?php echo h(trim((string)($artisan['avatar'] ?? ''))); ?>" maxlength="255" placeholder="uploads/craftsmen/... أو https://...">
                            </details>
                        </div>
                        <div class="fg full"><label for="bio">نبذة تعريفية</label><textarea id="bio" name="bio" placeholder="عرف بنفسك والخدمات اللي كتقدم..."><?php echo h($display_bio); ?></textarea></div>
                    </div>
                    <div class="actions"><button type="submit" class="btn pri"><i class="fas fa-floppy-disk"></i> حفظ التعديلات</button></div>
                </form>

                <div class="subsec">
                    <h3><i class="fas fa-briefcase"></i> إضافة ورش جديد من نفس الصفحة</h3>
                    <form method="post" action="artisan_dashboard.php?section=profile">
                        <input type="hidden" name="action" value="add_project">
                        <input type="hidden" name="redirect_section" value="profile">
                        <div class="form">
                            <div class="fg"><label for="profile_project_title">عنوان الورش</label><input id="profile_project_title" name="title" type="text" maxlength="200" required></div>
                            <div class="fg"><label for="profile_project_media_type">نوع الوسيط</label><select id="profile_project_media_type" name="media_type"><option value="image">صورة</option><option value="video">فيديو</option></select></div>
                            <div class="fg">
                                <label for="profile_project_location">المكان</label>
                                <select id="profile_project_location" name="location" onchange="toggleManualCity(this, 'profile_location_manual')">
                                    <option value="">اختر المدينة</option>
                                    <option value="الدار البيضاء">الدار البيضاء</option>
                                    <option value="الرباط">الرباط</option>
                                    <option value="مراكش">مراكش</option>
                                    <option value="فاس">فاس</option>
                                    <option value="طنجة">طنجة</option>
                                    <option value="أكادير">أكادير</option>
                                    <option value="مكناس">مكناس</option>
                                    <option value="وجدة">وجدة</option>
                                    <option value="القنيطرة">القنيطرة</option>
                                    <option value="تطوان">تطوان</option>
                                    <option value="آسفي">آسفي</option>
                                    <option value="المحمدية">المحمدية</option>
                                    <option value="خريبكة">خريبكة</option>
                                    <option value="الجديدة">الجديدة</option>
                                    <option value="بني ملال">بني ملال</option>
                                    <option value="الناظور">الناظور</option>
                                    <option value="أخرى">أخرى (أدخل يدويا)</option>
                                </select>
                                <input type="text" id="profile_location_manual" name="location_manual" placeholder="أدخل اسم المدينة هنا" style="display:none; margin-top:8px;" maxlength="100">
                            </div>
                            <div class="fg"><label for="profile_project_work_date">تاريخ الإنجاز</label><input id="profile_project_work_date" name="work_date" type="date"></div>
                            <div class="fg full"><label for="profile_project_media_url">رابط/مسار الصورة أو الفيديو</label><input id="profile_project_media_url" name="media_url" type="text" maxlength="255" placeholder="uploads/craftsmen/... أو https://..." required></div>
                            <div class="fg full"><label for="profile_project_description">وصف الورش</label><textarea id="profile_project_description" name="description" placeholder="قدم تفاصيل العمل والخدمة..."></textarea></div>
                        </div>
                        <div class="actions"><button type="submit" class="btn pri"><i class="fas fa-plus"></i> إضافة الورش</button></div>
                    </form>
                </div>

                <div class="subsec">
                    <h3><i class="fas fa-trash"></i> حذف الأوراش بسهولة</h3>
                    <?php if (empty($portfolio)): ?>
                        <div class="empty">ما كاين حتى ورش حاليا للحذف.</div>
                    <?php else: ?>
                        <div class="mini-list">
                            <?php foreach ($portfolio as $item): ?>
                                <article class="mini-item">
                                    <h4><?php echo h($item['title'] ?: 'بدون عنوان'); ?></h4>
                                    <p class="mini-meta">
                                        <span><i class="fas fa-images"></i> <?php echo ($item['media_type'] ?? '') === 'video' ? 'فيديو' : 'صورة'; ?></span>
                                        <?php if (!empty($item['location'])): ?><span><i class="fas fa-location-dot"></i> <?php echo h($item['location']); ?></span><?php endif; ?>
                                        <?php if (!empty($item['work_date'])): ?><span><i class="fas fa-calendar"></i> <?php echo h($item['work_date']); ?></span><?php endif; ?>
                                    </p>
                                    <form method="post" action="artisan_dashboard.php?section=profile" onsubmit="return confirm('واش متأكد بغيتي تمسح هاد الورش؟');">
                                        <input type="hidden" name="action" value="delete_project">
                                        <input type="hidden" name="redirect_section" value="profile">
                                        <input type="hidden" name="project_id" value="<?php echo h((string)$item['id']); ?>">
                                        <button type="submit" class="btn danger"><i class="fas fa-trash"></i> حذف</button>
                                    </form>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <!-- JOB REQUESTS -->
            <section id="section-job-requests" class="section <?php echo $active_section === 'job-requests' ? 'active' : ''; ?>">
                <h2 class="title"><i class="fas fa-list-check"></i> طلبات العمل المتاحة في تخصصك</h2>
                <div id="artisan-jobs-container">
                    <p style="text-align:center; padding: 2rem; color:var(--muted);">جاري تحميل الطلبات...</p>
                </div>
            </section>

            <!-- MY PROPOSALS -->
            <section id="section-my-proposals" class="section <?php echo $active_section === 'my-proposals' ? 'active' : ''; ?>">
                <h2 class="title"><i class="fas fa-paper-plane"></i> عروضي المقترحة على العملاء</h2>
                <div class="table-card" style="overflow-x:auto;">
                    <table class="premium">
                        <thead>
                            <tr>
                                <th>الطلب</th>
                                <th>العميل</th>
                                <th>السعر المقترح</th>
                                <th>المدة المقدرة</th>
                                <th>تاريخ التقديم</th>
                                <th>حالة العرض</th>
                                <th>الإجراء</th>
                            </tr>
                        </thead>
                        <tbody id="my-proposals-table-body">
                            <tr>
                                <td colspan="7" style="text-align:center; padding: 2rem; color:var(--muted);">جاري تحميل العروض...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>

    <script>
        (function () {
            const buttons = document.querySelectorAll('.tabs button[data-section]');
            const sections = document.querySelectorAll('.section');
            const avatarInput = document.getElementById('avatar_file');
            const avatarPreview = document.getElementById('avatarPreview');
            const removeAvatarInput = document.querySelector('input[name="remove_avatar"]');

            function activate(sectionName, updateUrl) {
                let exists = false;
                buttons.forEach(function(btn) {
                    const active = btn.dataset.section === sectionName;
                    btn.classList.toggle('active', active);
                    if (active) exists = true;
                });
                sections.forEach(function(s) {
                    s.classList.toggle('active', s.id === 'section-' + sectionName);
                });
                if (exists && updateUrl) {
                    const url = new URL(window.location.href);
                    url.searchParams.set('section', sectionName);
                    window.history.replaceState({}, '', url.toString());
                }
            }

            buttons.forEach(function(btn) {
                btn.addEventListener('click', function () { activate(this.dataset.section, true); });
            });

            if (avatarInput && avatarPreview) {
                avatarInput.addEventListener('change', function () {
                    const file = this.files && this.files[0] ? this.files[0] : null;
                    if (!file) {
                        return;
                    }

                    if (removeAvatarInput) {
                        removeAvatarInput.checked = false;
                        avatarPreview.style.opacity = '1';
                    }

                    const localUrl = URL.createObjectURL(file);
                    avatarPreview.src = localUrl;
                });
            }

            if (removeAvatarInput && avatarPreview) {
                removeAvatarInput.addEventListener('change', function () {
                    avatarPreview.style.opacity = this.checked ? '0.35' : '1';
                });
            }
            
            window.toggleManualCity = function(selectElem, textInputId) {
                const manualInput = document.getElementById(textInputId);
                if (selectElem.value === 'أخرى') {
                    manualInput.style.display = 'block';
                    manualInput.focus();
                } else {
                    manualInput.style.display = 'none';
                    manualInput.value = '';
                }
            };
            
            // Messages functionality
            let currentConversation = null;
            let messagesInterval = null;
            
            function toggleMessages() {
                const section = document.getElementById('messagesSection');
                if (!section) return;
                
                if (section.style.display === 'none' || section.style.display === '') {
                    section.style.display = 'flex';
                    loadConversations();
                    checkUnreadMessages();
                    clearInterval(messagesInterval);
                    messagesInterval = setInterval(loadConversations, 30000);
                } else {
                    section.style.display = 'none';
                    clearInterval(messagesInterval);
                }
            }
            
            function checkUnreadMessages() {
                fetch('messages.php?action=get_unread_count')
                    .then(function(res) { return res.json(); })
                    .then(function(data) {
                        const badge = document.getElementById('msgBadge');
                        if (!badge) return;
                        if (data.data && data.data.unread_count > 0) {
                            badge.textContent = data.data.unread_count;
                            badge.classList.add('show');
                        } else {
                            badge.classList.remove('show');
                        }
                    })
                    .catch(function () {});
            }
            
            function escapeMessageHtml(value) {
                return String(value == null ? '' : value)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#39;');
            }
            
            function normalizeMessageDate(dateStr) {
                return String(dateStr || '').replace(' ', 'T');
            }
            
            function renderMessageAttachment(attachment) {
                if (!attachment || !attachment.url) return '';
                const url = escapeMessageHtml(attachment.url);
                const name = escapeMessageHtml(attachment.name || 'ملف مرفق');
                
                if (attachment.type === 'image') {
                    return `
                        <a class="message-attachment image-attachment" href="${url}" target="_blank" rel="noopener">
                            <img src="${url}" alt="${name}">
                        </a>
                    `;
                }
                
                const icon = attachment.type === 'pdf' ? 'fa-file-pdf' : 'fa-file';
                return `
                    <a class="message-attachment file-attachment" href="${url}" target="_blank" rel="noopener">
                        <i class="fas ${icon}"></i>
                        <span>${name}</span>
                    </a>
                `;
            }
            
            function renderMessageContent(msg, sentType) {
                const messageText = String(msg.message_text || '').trim();
                return `
                    <div class="message-bubble ${msg.sender_type === sentType ? 'sent' : 'received'}">
                        ${messageText ? `<div class="message-body">${escapeMessageHtml(messageText)}</div>` : ''}
                        ${renderMessageAttachment(msg.attachment)}
                        <span class="message-time">${formatDateTime(msg.created_at)}</span>
                    </div>
                `;
            }
            
            function updateAttachmentName() {
                const fileInput = document.getElementById('messageAttachment');
                const label = document.getElementById('messageAttachmentName');
                if (!fileInput || !label) return;
                label.textContent = fileInput.files && fileInput.files[0] ? fileInput.files[0].name : '';
            }
            
            function clearAttachmentInput() {
                const fileInput = document.getElementById('messageAttachment');
                if (!fileInput) return;
                fileInput.value = '';
                updateAttachmentName();
            }
            
            function loadConversations() {
                fetch('messages.php?action=get_conversations')
                    .then(function(res) { return res.json(); })
                    .then(function(data) {
                        const list = document.getElementById('conversationsList');
                        if (!list) return;
                        if (data.data && data.data.length > 0) {
                            list.innerHTML = data.data.map(function(conv) { return `
                                <div class="conversation-item ${conv.unread_count > 0 ? 'unread' : ''} ${currentConversation && currentConversation.userId === conv.sender_id && currentConversation.userType === conv.sender_type ? 'active' : ''}" 
                                     data-name="${escapeMessageHtml(conv.sender_name || 'مدير')}"
                                     onclick="selectConversation(${conv.sender_id}, '${conv.sender_type}', this.dataset.name, this)">
                                    <div class="conversation-header">
                                        <span class="conversation-name">${escapeMessageHtml(conv.sender_name || 'مدير')}</span>
                                        <span class="conversation-time">${formatDate(conv.created_at)}</span>
                                    </div>
                                    <div class="conversation-preview">${escapeMessageHtml(conv.message_text || '')}</div>
                                </div>
                            `; }).join('');
                        } else {
                            list.innerHTML = '<div style="padding:1rem;text-align:center;color:#94a3b8;">لا توجد محادثات</div>';
                        }
                        checkUnreadMessages();
                    })
                    .catch(function () {
                        const list = document.getElementById('conversationsList');
                        if (list) {
                            list.innerHTML = '<div style="padding:1rem;text-align:center;color:#94a3b8;">تعذر تحميل المحادثات</div>';
                        }
                    });
            }
            
            function selectConversation(userId, userType, userName, element) {
                currentConversation = { userId, userType, userName };
                
                document.querySelectorAll('.conversation-item').forEach(function(item) { item.classList.remove('active'); });
                if (element) {
                    element.classList.add('active');
                }
                
                document.getElementById('chatHeader').innerHTML = `<span><i class="fas fa-user-shield"></i> ${escapeMessageHtml(userName)}</span>`;
                
                document.getElementById('messageInput').disabled = false;
                document.getElementById('sendBtn').disabled = false;
                
                loadMessages(userId, userType);
                
                if (messagesInterval) clearInterval(messagesInterval);
                messagesInterval = setInterval(function () { loadMessages(userId, userType); }, 5000);
            }
            
            function loadMessages(userId, userType) {
                fetch(`messages.php?action=get_messages&user_id=${userId}&user_type=${userType}`)
                    .then(function(res) { return res.json(); })
                    .then(function(data) {
                        const container = document.getElementById('chatMessages');
                        if (!container) return;
                        if (data.data && data.data.length > 0) {
                            container.innerHTML = data.data.map(function(msg) { return renderMessageContent(msg, 'craftsman'); }).join('');
                            container.scrollTop = container.scrollHeight;
                        } else {
                            container.innerHTML = '<div class="no-conversation">لا توجد رسائل بعد</div>';
                        }
                    })
                    .catch(function () {
                        const container = document.getElementById('chatMessages');
                        if (container) {
                            container.innerHTML = '<div class="no-conversation">تعذر تحميل الرسائل</div>';
                        }
                    });
            }
            
            function sendMessage() {
                const input = document.getElementById('messageInput');
                if (!input) return;
                const message = input.value.trim();
                const fileInput = document.getElementById('messageAttachment');
                const file = fileInput && fileInput.files ? fileInput.files[0] : null;
                
                if ((!message && !file) || !currentConversation) return;
                
                const formData = new FormData();
                formData.append('receiver_id', currentConversation.userId);
                formData.append('receiver_type', currentConversation.userType);
                formData.append('message', message);
                if (file) {
                    formData.append('attachment', file);
                }
                
                fetch('messages.php?action=send', {
                    method: 'POST',
                    body: formData
                })
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    if (data.success) {
                        input.value = '';
                        clearAttachmentInput();
                        loadMessages(currentConversation.userId, currentConversation.userType);
                        loadConversations();
                    } else {
                        alert(data.message || 'فشل إرسال الرسالة');
                    }
                })
                .catch(function () {
                    alert('تعذر إرسال الرسالة حالياً');
                });
            }
            
            document.addEventListener('DOMContentLoaded', function() {
                const messageInputEl = document.getElementById('messageInput');
                if (messageInputEl) {
                    messageInputEl.addEventListener('keypress', function(e) {
                        if (e.key === 'Enter') sendMessage();
                    });
                }
                
                const attachmentInputEl = document.getElementById('messageAttachment');
                if (attachmentInputEl) {
                    attachmentInputEl.addEventListener('change', updateAttachmentName);
                }
            });
            
            function formatDate(dateStr) {
                const date = new Date(normalizeMessageDate(dateStr));
                if (Number.isNaN(date.getTime())) return '';
                const now = new Date();
                const diff = now - date;
                if (diff < 60000) return 'الآن';
                if (diff < 3600000) return Math.floor(diff/60000) + 'د';
                if (diff < 86400000) return Math.floor(diff/3600000) + 'س';
                return date.toLocaleDateString('ar');
            }
            
            function formatDateTime(dateStr) {
                const date = new Date(normalizeMessageDate(dateStr));
                if (Number.isNaN(date.getTime())) return '';
                return date.toLocaleTimeString('ar', { hour: '2-digit', minute: '2-digit' });
            }
            
            // Initialize
            checkUnreadMessages();

            const urlParams = new URLSearchParams(window.location.search);
            const initialSection = urlParams.get('section') || 'overview';
            if (initialSection === 'job-requests') {
                loadJobRequests();
            } else if (initialSection === 'my-proposals') {
                loadMyProposals();
            }

            // === MARKETPLACE INTEGRATION ===
            let allJobs = [];
            async function loadJobRequests() {
                const container = document.getElementById('artisan-jobs-container');
                if (!container) return;
                container.innerHTML = '<div style="text-align:center; padding: 2rem; color:var(--muted);"><i class="fas fa-spinner fa-spin fa-2x"></i> جاري تحميل الطلبات...</div>';
                
                try {
                    const res = await fetch('job_requests.php?action=list_for_artisan');
                    const data = await res.json();
                    if (data.success) {
                        allJobs = data.requests;
                        renderJobsList(allJobs, data.profession);
                    } else {
                        container.innerHTML = `<div class="empty">${escapeMessageHtml(data.message || 'خطأ في تحميل الطلبات')}</div>`;
                    }
                } catch(e) {
                    container.innerHTML = '<div class="empty">فشل تحميل طلبات العمل المفتوحة</div>';
                }
            }

            function renderJobsList(list, profession) {
                const container = document.getElementById('artisan-jobs-container');
                if (!container) return;
                if (list.length === 0) {
                    container.innerHTML = `<div class="empty">لا توجد طلبات جديدة متاحة حالياً لتخصصك (${escapeMessageHtml(profession)}). سنقوم بإعلامك فور توفر طلبات جديدة!</div>`;
                    return;
                }

                const urgencyLabels = {
                    'low': 'منخفضة',
                    'medium': 'متوسطة',
                    'high': 'عالية',
                    'urgent': 'عاجلة جداً'
                };

                const urgencyClasses = {
                    'low': 'badge-saas badge-pending',
                    'medium': 'badge-saas badge-under_review',
                    'high': 'badge-saas badge-rejected',
                    'urgent': 'badge-saas badge-rejected'
                };

                container.innerHTML = `
                    <div style="font-size:0.9rem; margin-bottom:12px; color:var(--muted)">لقد وجدنا لك ${list.length} طلبات تطابق تخصصك (${escapeMessageHtml(profession)}):</div>
                    <div class="grid">
                        ${list.map(job => {
                            const dateStr = job.desired_date ? job.desired_date : 'غير محدد';
                            const budgetStr = job.budget ? job.budget + ' درهم' : 'حسب الاتفاق';
                            
                            let actionButton = '';
                            if (job.my_proposal_id) {
                                actionButton = `
                                    <button class="btn" style="background:#ecfdf5; color:#059669; border:1px solid #a7f3d0; cursor:default; width:100%;" disabled>
                                        <i class="fas fa-check"></i> قدمت عرضاً بالفعل
                                    </button>
                                `;
                            } else {
                                actionButton = `
                                    <button onclick="openProposalModal(${job.id})" class="btn pri" style="width:100%;">
                                        <i class="fas fa-paper-plane"></i> تقديم عرض سعر
                                    </button>
                                `;
                            }

                            return `
                                <article class="proj" style="background:linear-gradient(135deg,#fff,#f8fafc); border:1px solid var(--line); border-radius:16px; min-height:unset;">
                                    <div class="body" style="padding:16px; gap:12px;">
                                        <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                                            <h3 style="margin:0; font-size:1.1rem; color:var(--brand-strong); text-align:right;">${escapeMessageHtml(job.title)}</h3>
                                            <span class="${urgencyClasses[job.urgency]}">${urgencyLabels[job.urgency]}</span>
                                        </div>
                                        
                                        <p style="margin:0; font-size:0.87rem; color:var(--muted); line-height:1.5; text-align:right;">${escapeMessageHtml(job.description)}</p>
                                        
                                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px; font-size:0.82rem; color:var(--muted); border-top:1px solid var(--line); border-bottom:1px solid var(--line); padding:8px 0; text-align:right;">
                                            <div><i class="fas fa-location-dot"></i> ${escapeMessageHtml(job.city)} ${job.neighborhood ? '- ' + escapeMessageHtml(job.neighborhood) : ''}</div>
                                            <div><i class="fas fa-wallet" style="color:var(--accent);"></i> الميزانية: <strong>${budgetStr}</strong></div>
                                            <div><i class="fas fa-calendar-days"></i> تاريخ الإنجاز: ${dateStr}</div>
                                            <div><i class="fas fa-user"></i> العميل: ${escapeMessageHtml(job.client_name)}</div>
                                        </div>
                                        
                                        <div style="display:flex; justify-content:space-between; align-items:center; font-size:0.8rem; color:var(--muted);">
                                            <span>التقديم قبل: ${formatDate(job.created_at)}</span>
                                            <span>العروض المستلمة: <strong>${job.proposal_count}</strong></span>
                                        </div>
                                        
                                        ${actionButton}
                                    </div>
                                </article>
                            `;
                        }).join('')}
                    </div>
                `;
            }

            let myProposalsList = [];
            async function loadMyProposals() {
                const body = document.getElementById('my-proposals-table-body');
                if (!body) return;
                body.innerHTML = '<tr><td colspan="7" style="text-align:center; padding: 2rem; color:var(--muted);"><i class="fas fa-spinner fa-spin fa-2x"></i> جاري تحميل العروض...</td></tr>';
                
                try {
                    const res = await fetch('proposals.php?action=my_proposals');
                    const data = await res.json();
                    if (data.success) {
                        myProposalsList = data.proposals;
                        renderMyProposals(myProposalsList);
                    } else {
                        body.innerHTML = `<tr><td colspan="7" style="text-align:center; padding: 2rem; color:var(--danger);">${escapeMessageHtml(data.message || 'خطأ في تحميل العروض')}</td></tr>`;
                    }
                } catch(e) {
                    body.innerHTML = '<tr><td colspan="7" style="text-align:center; padding: 2rem; color:var(--danger);">تعذر الاتصال بالخادم</td></tr>';
                }
            }

            function renderMyProposals(list) {
                const body = document.getElementById('my-proposals-table-body');
                if (!body) return;
                if (list.length === 0) {
                    body.innerHTML = '<tr><td colspan="7" style="text-align:center; padding: 2rem; color:var(--muted);">لم تقم بتقديم أي عروض أسعار حتى الآن. اذهب لقسم الطلبات المتاحة للبدء!</td></tr>';
                    return;
                }

                const statusLabels = {
                    'pending': 'في الانتظار',
                    'favorite': 'مفضلة للعميل',
                    'accepted': 'مقبول 🎉',
                    'rejected': 'غير مقبول'
                };

                const statusBadges = {
                    'pending': 'badge-saas badge-pending',
                    'favorite': 'badge-saas badge-under_review',
                    'accepted': 'badge-saas badge-resolved',
                    'rejected': 'badge-saas badge-rejected'
                };

                body.innerHTML = list.map(p => {
                    let actionHtml = '';
                    if (p.status === 'accepted') {
                        actionHtml = `
                            <button onclick="openChatFromProposal(${p.request_id}, ${p.id})" class="btn pri" style="font-size:0.8rem; padding:6px 12px;">
                                <i class="fas fa-comments"></i> مراسلة العميل
                            </button>
                        `;
                    } else {
                        actionHtml = `<span style="font-size:0.83rem; color:var(--muted);">لا يوجد إجراء متوفر</span>`;
                    }

                    return `
                        <tr>
                            <td>
                                <strong>${escapeMessageHtml(p.title)}</strong><br>
                                <small style="color:var(--muted)">التخصص: ${escapeMessageHtml(p.category)}</small>
                            </td>
                            <td>${escapeMessageHtml(p.client_name)}</td>
                            <td><strong>${p.proposed_price} درهم</strong></td>
                            <td>${escapeMessageHtml(p.estimated_duration)}</td>
                            <td>${p.created_at ? p.created_at.substring(0,10) : ''}</td>
                            <td>
                                <span class="${statusBadges[p.status] || 'badge-saas'}">${statusLabels[p.status] || p.status}</span>
                            </td>
                            <td>${actionHtml}</td>
                        </tr>
                    `;
                }).join('');
            }

            window.openProposalModal = function(id) {
                const modal = document.getElementById('submitProposalModal');
                const reqInput = document.getElementById('proposal-request-id');
                if (modal && reqInput) {
                    reqInput.value = id;
                    document.getElementById('proposal-submit-form').reset();
                    modal.classList.add('show');
                }
            };

            window.closeProposalModal = function() {
                const modal = document.getElementById('submitProposalModal');
                if (modal) modal.classList.remove('show');
            };

            window.submitArtisanProposal = async function(event) {
                event.preventDefault();
                const reqId = parseInt(document.getElementById('proposal-request-id').value);
                const price = parseFloat(document.getElementById('proposal-proposed-price').value);
                const duration = document.getElementById('proposal-estimated-duration').value.trim();
                const availability = document.getElementById('proposal-availability').value.trim();
                const desc = document.getElementById('proposal-description').value.trim();

                try {
                    const res = await fetch('proposals.php?action=submit', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            request_id: reqId,
                            proposed_price: price,
                            estimated_duration: duration,
                            availability: availability,
                            description: desc
                        })
                    });
                    const data = await res.json();
                    if (data.success) {
                        alert(data.message || 'تم تقديم العرض بنجاح!');
                        closeProposalModal();
                        loadJobRequests();
                        loadMyProposals();
                    } else {
                        alert(data.message || 'خطأ أثناء إرسال العرض');
                    }
                } catch(e) {
                    alert('تعذر تقديم العرض. يرجى التحقق من اتصالك بالإنترنت');
                }
            };

            window.openChatFromProposal = async function(requestId, proposalId) {
                try {
                    const res = await fetch('chat.php?action=get_conversations');
                    const data = await res.json();
                    if (data.success && data.conversations) {
                        const found = data.conversations.find(c => c.proposal_id == proposalId || c.request_id == requestId);
                        if (found) {
                            window.location.href = '../chat.php?conv=' + found.id;
                        } else {
                            window.location.href = '../chat.php';
                        }
                    } else {
                        window.location.href = '../chat.php';
                    }
                } catch(e) {
                    window.location.href = '../chat.php';
                }
            };
        })();
    </script>
    
    <!-- Messages Section -->
    <div id="messagesSection" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:2000;align-items:center;justify-content:center;">
        <div style="background:white;border-radius:12px;width:90%;max-width:900px;height:80vh;display:grid;grid-template-columns:300px 1fr;overflow:hidden;">
            <div style="border-left:1px solid #e2e8f0;display:flex;flex-direction:column;">
                <div style="padding:1rem;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;">
                    <span style="font-weight:600;">الرسائل</span>
                    <button onclick="toggleMessages()" style="background:none;border:none;font-size:1.2rem;cursor:pointer;">&times;</button>
                </div>
                <div id="conversationsList" style="flex:1;overflow-y:auto;">
                    <div style="padding:1rem;text-align:center;color:#94a3b8;">جاري تحميل المحادثات...</div>
                </div>
            </div>
            <div style="display:flex;flex-direction:column;">
                <div class="chat-header" id="chatHeader" style="padding:1rem;border-bottom:1px solid #e2e8f0;background:#f8fafc;">
                    <span>اختر محادثة لبدء المراسلة</span>
                </div>
                <div class="chat-messages" id="chatMessages" style="flex:1;overflow-y:auto;padding:1rem;display:flex;flex-direction:column;gap:1rem;">
                    <div class="no-conversation">اختر محادثة لعرض الرسائل</div>
                </div>
                <div class="chat-input-panel">
                    <div class="chat-attachment-row">
                        <label for="messageAttachment" class="attach-trigger"><i class="fas fa-paperclip"></i> صورة / PDF</label>
                        <span class="attachment-meta" id="messageAttachmentName"></span>
                        <input type="file" id="messageAttachment" accept="image/*,application/pdf" hidden>
                    </div>
                    <div class="chat-input-row">
                        <input type="text" id="messageInput" placeholder="اكتب رسالتك..." disabled style="flex:1;padding:0.75rem;border:1px solid #e2e8f0;border-radius:8px;">
                        <button onclick="sendMessage()" id="sendBtn" disabled style="padding:0.75rem 1.5rem;background:#0f766e;color:white;border:none;border-radius:8px;cursor:pointer;">إرسال</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <style>
    .conversation-item {padding:1rem;border-bottom:1px solid #e2e8f0;cursor:pointer;transition:background 0.3s;}
    .conversation-item:hover {background:#f8fafc;}
    .conversation-item.active {background:#e0f2fe;border-right:3px solid #0f766e;}
    .conversation-item.unread {background:#fef3c7;}
    .conversation-header {display:flex;justify-content:space-between;align-items:center;margin-bottom:0.5rem;}
    .conversation-name {font-weight:600;color:#1e293b;}
    .conversation-time {font-size:0.75rem;color:#94a3b8;}
    .conversation-preview {font-size:0.85rem;color:#64748b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .message-bubble {max-width:70%;padding:0.75rem 1rem;border-radius:12px;position:relative;}
    .message-bubble.sent {background:#0f766e;color:white;align-self:flex-end;border-bottom-left-radius:2px;}
    .message-bubble.received {background:#f1f5f9;color:#1e293b;align-self:flex-start;border-bottom-right-radius:2px;}
    .message-bubble .message-time {font-size:0.7rem;opacity:0.7;margin-top:0.3rem;display:block;}
    .message-body {white-space:pre-wrap;word-break:break-word;}
    .message-attachment {display:inline-flex;align-items:center;gap:0.6rem;text-decoration:none;color:inherit;max-width:100%;overflow:hidden;border-radius:10px;}
    .message-attachment.image-attachment {padding:0.2rem;background:rgba(255,255,255,0.18);}
    .message-bubble.received .message-attachment.image-attachment {background:#ffffff;}
    .message-attachment img {display:block;width:min(240px,100%);max-height:220px;object-fit:cover;border-radius:8px;}
    .message-attachment.file-attachment {padding:0.7rem 0.85rem;background:rgba(255,255,255,0.18);}
    .message-bubble.received .message-attachment.file-attachment {background:#ffffff;}
    .chat-input-panel {padding:1rem;border-top:1px solid #e2e8f0;display:grid;gap:0.75rem;}
    .chat-input-row {display:flex;gap:0.5rem;}
    .chat-attachment-row {display:flex;align-items:center;gap:0.75rem;flex-wrap:wrap;}
    .attach-trigger {display:inline-flex;align-items:center;gap:0.4rem;padding:0.55rem 0.85rem;background:#ccfbf1;color:#0f766e;border-radius:999px;cursor:pointer;font-size:0.85rem;font-weight:600;}
    .attachment-meta {color:#64748b;font-size:0.82rem;min-height:1rem;}
    .no-conversation {display:flex;align-items:center;justify-content:center;height:100%;color:#94a3b8;}
    
    /* Proposal Modal Container */
    #submitProposalModal.show {
        display: flex;
    }
    </style>

    <!-- Proposal Submit Modal -->
    <div id="submitProposalModal" class="modal">
        <div class="modal-content-proposal">
            <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--line); padding-bottom:12px; margin-bottom:18px;">
                <h3 style="margin:0; font-size:1.2rem; color:var(--brand-strong);"><i class="fas fa-paper-plane"></i> تقديم عرض سعر جديد</h3>
                <button onclick="closeProposalModal()" style="background:none; border:none; font-size:1.5rem; cursor:pointer; color:var(--muted);">&times;</button>
            </div>
            <form id="proposal-submit-form" onsubmit="submitArtisanProposal(event)" style="display:flex; flex-direction:column; gap:12px; text-align:right;">
                <input type="hidden" id="proposal-request-id" name="request_id">
                
                <div class="fg">
                    <label for="proposal-proposed-price" style="display:block; font-weight:700; margin-bottom:4px;">السعر المقترح (بالدرهم المغربي) *</label>
                    <input type="number" id="proposal-proposed-price" name="proposed_price" required min="1" placeholder="مثال: 500" style="width:100%; text-align:right;">
                </div>
                
                <div class="fg">
                    <label for="proposal-estimated-duration" style="display:block; font-weight:700; margin-bottom:4px;">المدة التقديرية لإتمام العمل *</label>
                    <input type="text" id="proposal-estimated-duration" name="estimated_duration" required placeholder="مثال: يومين، 3 أيام، أسبوع" style="width:100%; text-align:right;">
                </div>

                <div class="fg">
                    <label for="proposal-availability" style="display:block; font-weight:700; margin-bottom:4px;">مواعيد التوفر المتاحة</label>
                    <input type="text" id="proposal-availability" name="availability" placeholder="مثال: غداً صباحاً، أو نهاية الأسبوع" style="width:100%; text-align:right;">
                </div>

                <div class="fg">
                    <label for="proposal-description" style="display:block; font-weight:700; margin-bottom:4px;">تفاصيل العرض للعميل</label>
                    <textarea id="proposal-description" name="description" placeholder="اشرح للعميل كيف ستنجز العمل ولماذا يجب أن يختارك..." style="width:100%; min-height:80px; text-align:right; font-family:inherit;"></textarea>
                </div>
                
                <div style="display:flex; gap:10px; margin-top:14px; justify-content:flex-start;">
                    <button type="button" onclick="closeProposalModal()" class="btn" style="background:#cbd5e1; color:#334155;">إلغاء</button>
                    <button type="submit" class="btn pri">إرسال العرض</button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="../lang_dict.js"></script>
    <script src="../script.js"></script>
    <script>
        // ── Premium Lang Switcher Init ──
        function setLang(lang) {
            localStorage.setItem('hirafi_lang', lang);
            if (window.applyTranslations) window.applyTranslations(lang);
            document.documentElement.lang = lang;
            document.documentElement.dir = lang === 'ar' ? 'rtl' : 'ltr';
            document.querySelectorAll('.lang-opt').forEach(b => b.classList.remove('active'));
            const btn = document.getElementById('btn-' + lang);
            if (btn) btn.classList.add('active');
        }
        document.addEventListener('DOMContentLoaded', function() {
            const saved = localStorage.getItem('hirafi_lang') || 'ar';
            setLang(saved);
        });
    </script>
</body>
</html>
