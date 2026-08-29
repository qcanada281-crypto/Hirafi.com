<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once 'config.php';

$response = ['success' => false, 'message' => 'غير مصرح'];

$user_type = $_SESSION['user_type'] ?? null;
$is_client  = ($user_type === 'client');
$is_artisan = ($user_type === 'craftsman');
$client_id  = $is_client  ? (int)$_SESSION['client_id']    : 0;
$artisan_id = $is_artisan ? (int)$_SESSION['craftsman_id'] : 0;

if (!$is_client && !$is_artisan) {
    echo json_encode(['success'=>false,'message'=>'يرجى تسجيل الدخول أولاً'], JSON_UNESCAPED_UNICODE);
    exit;
}

$action = trim($_REQUEST['action'] ?? '');

// Helper: push notification
function push_notification($conn, $user_type, $user_id, $type, $title, $body, $link = '') {
    $stmt = $conn->prepare(
        "INSERT INTO notifications (user_type, user_id, type, title, body, link) VALUES (?,?,?,?,?,?)"
    );
    $stmt->bind_param("sissss", $user_type, $user_id, $type, $title, $body, $link);
    $stmt->execute();
}

try {
    // ======================== CLIENT CREATES REQUEST ========================
    if ($action === 'create' && $is_client) {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        $title              = trim($data['title'] ?? '');
        $category           = trim($data['category'] ?? '');
        $description        = trim($data['description'] ?? '');
        $budget             = isset($data['budget']) && $data['budget'] !== '' ? (float)$data['budget'] : null;
        $urgency            = in_array($data['urgency'] ?? '', ['low','medium','high','urgent']) ? $data['urgency'] : 'medium';
        $desired_date       = trim($data['desired_date'] ?? '');
        $city               = trim($data['city'] ?? '');
        $neighborhood       = trim($data['neighborhood'] ?? '');
        $latitude           = isset($data['latitude'])  ? (float)$data['latitude']  : null;
        $longitude          = isset($data['longitude']) ? (float)$data['longitude'] : null;
        $contact_preference = in_array($data['contact_preference'] ?? '', ['phone','whatsapp','platform']) ? $data['contact_preference'] : 'platform';

        if (mb_strlen($title) < 3) throw new Exception('عنوان الطلب يجب أن يكون 3 أحرف على الأقل');
        if ($category === '')      throw new Exception('الفئة مطلوبة');
        if ($city === '')          throw new Exception('المدينة مطلوبة');
        if ($desired_date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $desired_date)) {
            throw new Exception('صيغة تاريخ الإنجاز غير صحيحة');
        }
        $desired_date = $desired_date !== '' ? $desired_date : null;

        $stmt = $conn->prepare(
            "INSERT INTO job_requests
                (client_id, title, category, description, budget, urgency, desired_date,
                 city, neighborhood, latitude, longitude, contact_preference)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)"
        );
        $stmt->bind_param(
            "isssdssssdds",
            $client_id, $title, $category, $description, $budget, $urgency,
            $desired_date, $city, $neighborhood, $latitude, $longitude, $contact_preference
        );
        if (!$stmt->execute()) throw new Exception('فشل نشر الطلب، حاول مرة أخرى');

        $request_id = (int)$conn->insert_id;
        $response['success']    = true;
        $response['message']    = 'تم نشر طلبك بنجاح! الحرفيون في المنطقة سيتواصلون معك قريباً';
        $response['request_id'] = $request_id;

        // Notify craftsmen who match the category and city (best-effort)
        try {
            $notify_stmt = $conn->prepare(
                "SELECT id, full_name FROM craftsmen WHERE (specialization = ? OR profession = ?) AND (city = ? OR city = '') LIMIT 100"
            );
            $notify_stmt->bind_param('sss', $category, $category, $city);
            $notify_stmt->execute();
            $matches = $notify_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            foreach ($matches as $m) {
                push_notification($conn, 'craftsman', (int)$m['id'], 'new_job',
                    'طلب جديد في تخصصك',
                    'نشر عميل طلبًا بعنوان: ' . mb_substr($title,0,80) . '... وُضع في ' . ($city ?: 'جميع المدن'),
                    'chat.php?conv=0&request_id=' . $request_id
                );
            }
        } catch (Exception $e) {
            // non-fatal; ignore notification errors
        }

    // ======================== ARTISAN LISTS REQUESTS (filtered by profession) ========================
    } elseif ($action === 'list_for_artisan' && $is_artisan) {
        $page  = max(1, (int)($_GET['page'] ?? 1));
        $limit = 10;
        $offset = ($page - 1) * $limit;

        // Get artisan profession
        $as = $conn->prepare("SELECT profession, specialization, city FROM craftsmen WHERE id = ? LIMIT 1");
        $as->bind_param("i", $artisan_id);
        $as->execute();
        $artisan_data = $as->get_result()->fetch_assoc();
        $profession = $artisan_data['specialization'] ?: $artisan_data['profession'] ?? '';

        $stmt = $conn->prepare(
            "SELECT jr.id, jr.title, jr.category, jr.description, jr.budget, jr.urgency,
                    jr.desired_date, jr.city, jr.neighborhood, jr.contact_preference, jr.status,
                    jr.created_at,
                    c.full_name as client_name, c.avatar as client_avatar,
                    (SELECT COUNT(*) FROM proposals p WHERE p.request_id = jr.id) as proposal_count,
                    (SELECT COUNT(*) FROM job_request_photos ph WHERE ph.request_id = jr.id) as photo_count,
                    (SELECT id FROM proposals WHERE request_id = jr.id AND artisan_id = ?) as my_proposal_id
             FROM job_requests jr
             JOIN clients c ON c.id = jr.client_id
             WHERE jr.category = ? AND jr.status = 'open'
             ORDER BY jr.urgency DESC, jr.created_at DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->bind_param("isii", $artisan_id, $profession, $limit, $offset);
        $stmt->execute();
        $requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        // Count total
        $cnt_stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM job_requests WHERE category = ? AND status = 'open'");
        $cnt_stmt->bind_param("s", $profession);
        $cnt_stmt->execute();
        $total = (int)$cnt_stmt->get_result()->fetch_assoc()['cnt'];

        $response['success']    = true;
        $response['requests']   = $requests;
        $response['total']      = $total;
        $response['page']       = $page;
        $response['pages']      = ceil($total / $limit);
        $response['profession'] = $profession;

    // ======================== CLIENT LISTS OWN REQUESTS ========================
    } elseif ($action === 'my_requests' && $is_client) {
        $stmt = $conn->prepare(
            "SELECT jr.id, jr.title, jr.category, jr.urgency, jr.status, jr.city, jr.budget,
                    jr.created_at,
                    (SELECT COUNT(*) FROM proposals p WHERE p.request_id = jr.id) as proposal_count,
                    (SELECT COUNT(*) FROM proposals p WHERE p.request_id = jr.id AND p.status='accepted') as accepted_count
             FROM job_requests jr
             WHERE jr.client_id = ?
             ORDER BY jr.created_at DESC"
        );
        $stmt->bind_param("i", $client_id);
        $stmt->execute();
        $requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        // Stats
        $stats = ['total'=>0,'open'=>0,'in_progress'=>0,'completed'=>0,'canceled'=>0,'total_proposals'=>0];
        foreach ($requests as $r) {
            $stats['total']++;
            $stats[$r['status']] = ($stats[$r['status']] ?? 0) + 1;
            $stats['total_proposals'] += (int)$r['proposal_count'];
        }

        $response['success']  = true;
        $response['requests'] = $requests;
        $response['stats']    = $stats;

    // ======================== GET SINGLE REQUEST DETAIL ========================
    } elseif ($action === 'detail') {
        $request_id = (int)($_GET['id'] ?? 0);
        if ($request_id <= 0) throw new Exception('رقم الطلب غير صالح');

        $stmt = $conn->prepare(
            "SELECT jr.*, c.full_name as client_name, c.avatar as client_avatar, c.phone as client_phone
             FROM job_requests jr
             JOIN clients c ON c.id = jr.client_id
             WHERE jr.id = ? LIMIT 1"
        );
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $jr = $stmt->get_result()->fetch_assoc();
        if (!$jr) throw new Exception('الطلب غير موجود');

        // Authorization: client owner or matching artisan
        if ($is_client && (int)$jr['client_id'] !== $client_id) throw new Exception('غير مصرح');
        if ($is_artisan) {
            $as2 = $conn->prepare("SELECT profession, specialization FROM craftsmen WHERE id = ? LIMIT 1");
            $as2->bind_param("i", $artisan_id);
            $as2->execute();
            $ad = $as2->get_result()->fetch_assoc();
            $prof = $ad['specialization'] ?: $ad['profession'] ?? '';
            if ($jr['category'] !== $prof && $jr['status'] !== 'open') throw new Exception('غير مصرح بالوصول');
        }

        // Photos
        $ph = $conn->prepare("SELECT id, photo_path FROM job_request_photos WHERE request_id = ?");
        $ph->bind_param("i", $request_id);
        $ph->execute();
        $jr['photos'] = $ph->get_result()->fetch_all(MYSQLI_ASSOC);

        $response['success'] = true;
        $response['request'] = $jr;

    // ======================== CLIENT UPDATES STATUS ========================
    } elseif ($action === 'update_status' && $is_client) {
        $data        = json_decode(file_get_contents('php://input'), true) ?? [];
        $request_id  = (int)($data['request_id'] ?? 0);
        $new_status  = trim($data['status'] ?? '');
        $allowed     = ['canceled', 'completed'];

        if ($request_id <= 0) throw new Exception('رقم الطلب غير صالح');
        if (!in_array($new_status, $allowed, true)) throw new Exception('الحالة غير مسموح بها');

        $chk = $conn->prepare("SELECT id, client_id FROM job_requests WHERE id = ? LIMIT 1");
        $chk->bind_param("i", $request_id);
        $chk->execute();
        $jr = $chk->get_result()->fetch_assoc();
        if (!$jr || (int)$jr['client_id'] !== $client_id) throw new Exception('غير مصرح');

        $upd = $conn->prepare("UPDATE job_requests SET status = ? WHERE id = ? LIMIT 1");
        $upd->bind_param("si", $new_status, $request_id);
        $upd->execute();

        $response['success'] = true;
        $response['message'] = 'تم تحديث حالة الطلب';

    } else {
        throw new Exception('الإجراء غير معروف أو غير مصرح');
    }

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>
