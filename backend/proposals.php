<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once 'config.php';

$user_type  = $_SESSION['user_type'] ?? null;
$is_client  = ($user_type === 'client');
$is_artisan = ($user_type === 'craftsman');
$client_id  = $is_client  ? (int)$_SESSION['client_id']    : 0;
$artisan_id = $is_artisan ? (int)$_SESSION['craftsman_id'] : 0;

if (!$is_client && !$is_artisan) {
    echo json_encode(['success'=>false,'message'=>'يرجى تسجيل الدخول أولاً'], JSON_UNESCAPED_UNICODE);
    exit;
}

$action   = trim($_REQUEST['action'] ?? '');
$response = ['success' => false, 'message' => 'خطأ'];

function push_notif($conn, $utype, $uid, $type, $title, $body, $link = '') {
    $s = $conn->prepare("INSERT INTO notifications (user_type,user_id,type,title,body,link) VALUES(?,?,?,?,?,?)");
    $s->bind_param("sissss", $utype, $uid, $type, $title, $body, $link);
    $s->execute();
}

try {
    // ======================== ARTISAN SUBMITS PROPOSAL ========================
    if ($action === 'submit' && $is_artisan) {
        $data            = json_decode(file_get_contents('php://input'), true) ?? [];
        $request_id      = (int)($data['request_id'] ?? 0);
        $proposed_price  = (float)($data['proposed_price'] ?? 0);
        $est_duration    = trim($data['estimated_duration'] ?? '');
        $availability    = trim($data['availability'] ?? '');
        $description     = trim($data['description'] ?? '');
        $message         = trim($data['message'] ?? '');

        if ($request_id <= 0)        throw new Exception('رقم الطلب غير صالح');
        if ($proposed_price <= 0)    throw new Exception('السعر المقترح يجب أن يكون أكبر من 0');
        if ($est_duration === '')    throw new Exception('المدة التقديرية مطلوبة');

        // Verify request is open and matches artisan profession
        $jr = $conn->prepare("SELECT id, client_id, category, status, title FROM job_requests WHERE id = ? LIMIT 1");
        $jr->bind_param("i", $request_id);
        $jr->execute();
        $job = $jr->get_result()->fetch_assoc();
        if (!$job) throw new Exception('الطلب غير موجود');
        if ($job['status'] !== 'open') throw new Exception('هذا الطلب لم يعد مفتوحاً');

        // Check artisan profession matches
        $ap = $conn->prepare("SELECT profession, specialization, full_name FROM craftsmen WHERE id = ? LIMIT 1");
        $ap->bind_param("i", $artisan_id);
        $ap->execute();
        $artisan = $ap->get_result()->fetch_assoc();
        $prof = $artisan['specialization'] ?: $artisan['profession'] ?? '';
        if ($job['category'] !== $prof) throw new Exception('هذا الطلب ليس في تخصصك');

        // Check duplicate
        $dup = $conn->prepare("SELECT id FROM proposals WHERE request_id = ? AND artisan_id = ? LIMIT 1");
        $dup->bind_param("ii", $request_id, $artisan_id);
        $dup->execute();
        if ($dup->get_result()->num_rows > 0) throw new Exception('لقد قدمت عرضاً على هذا الطلب مسبقاً');

        $ins = $conn->prepare(
            "INSERT INTO proposals (request_id, artisan_id, proposed_price, estimated_duration, availability, description, message)
             VALUES (?,?,?,?,?,?,?)"
        );
        $ins->bind_param("iidssss", $request_id, $artisan_id, $proposed_price, $est_duration, $availability, $description, $message);
        if (!$ins->execute()) throw new Exception('فشل تقديم العرض');

        // Notify client
        $client_id_notify = (int)$job['client_id'];
        push_notif($conn, 'client', $client_id_notify, 'new_proposal',
            'عرض جديد على طلبك',
            'قدّم الحرفي ' . $artisan['full_name'] . ' عرضاً على طلبك: ' . $job['title'],
            'backend/client_dashboard.php?section=requests&id=' . $request_id
        );

        $response['success'] = true;
        $response['message'] = 'تم تقديم عرضك بنجاح! سيصلك رد من العميل قريباً';

    // ======================== ARTISAN CONTACTS CLIENT (CREATE CONVERSATION) ========================
    } elseif ($action === 'contact' && $is_artisan) {
        $data       = json_decode(file_get_contents('php://input'), true) ?? [];
        $request_id = (int)($data['request_id'] ?? 0);
        if ($request_id <= 0) throw new Exception('رقم الطلب غير صالح');

        // Verify request exists and is open
        $jr = $conn->prepare("SELECT id, client_id, category, status, title FROM job_requests WHERE id = ? LIMIT 1");
        $jr->bind_param("i", $request_id);
        $jr->execute();
        $job = $jr->get_result()->fetch_assoc();
        if (!$job) throw new Exception('الطلب غير موجود');
        if ($job['status'] !== 'open') throw new Exception('هذا الطلب ليس مفتوحاً');

        // Check artisan profession matches
        $ap = $conn->prepare("SELECT profession, specialization, full_name FROM craftsmen WHERE id = ? LIMIT 1");
        $ap->bind_param("i", $artisan_id);
        $ap->execute();
        $artisan = $ap->get_result()->fetch_assoc();
        $prof = $artisan['specialization'] ?: $artisan['profession'] ?? '';
        if ($job['category'] !== $prof) throw new Exception('هذا الطلب ليس في تخصصك');

        // Create conversation (or reuse existing)
        $conv = $conn->prepare(
            "INSERT INTO conversations (request_id, client_id, artisan_id) VALUES (?,?,?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)"
        );
        $conv->bind_param("iii", $request_id, $job['client_id'], $artisan_id);
        $conv->execute();
        $conv_id = (int)$conn->insert_id;

        // Notify client
        push_notif($conn, 'client', (int)$job['client_id'], 'artisan_contact',
            'حرفي بدأ محادثة',
            'بدأ الحرفي ' . ($artisan['full_name'] ?? '') . ' محادثة بخصوص طلبك: ' . $job['title'],
            'chat.php?conv=' . $conv_id
        );

        $response['success'] = true;
        $response['conversation_id'] = $conv_id;
        $response['chat_url'] = '../chat.php?conv=' . $conv_id;
        $response['message'] = 'تم إنشاء المحادثة. يمكنك التفاوض الآن.';
    // ======================== CLIENT VIEWS PROPOSALS ========================
    } elseif ($action === 'list' && $is_client) {
        $request_id = (int)($_GET['request_id'] ?? 0);
        if ($request_id <= 0) throw new Exception('رقم الطلب غير صالح');

        // Verify ownership
        $chk = $conn->prepare("SELECT client_id FROM job_requests WHERE id = ? LIMIT 1");
        $chk->bind_param("i", $request_id);
        $chk->execute();
        $jr  = $chk->get_result()->fetch_assoc();
        if (!$jr || (int)$jr['client_id'] !== $client_id) throw new Exception('غير مصرح');

        $stmt = $conn->prepare(
            "SELECT p.id, p.proposed_price, p.estimated_duration, p.availability, p.description,
                    p.message, p.status, p.created_at,
                    c.id as artisan_db_id, c.full_name as artisan_name, c.craftsman_id as artisan_code,
                    c.specialization as artisan_profession, c.city as artisan_city,
                    c.avatar as artisan_avatar, c.rating as artisan_rating,
                    c.total_reviews, c.experience_years, c.badge_type,
                    c.trust_score, c.completed_jobs, c.documents_verified,
                    conv.id as conversation_id
             FROM proposals p
             JOIN craftsmen c ON c.id = p.artisan_id
             LEFT JOIN conversations conv ON conv.proposal_id = p.id
             WHERE p.request_id = ?
             ORDER BY p.status ASC, p.proposed_price ASC"
        );
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $proposals = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $response['success']   = true;
        $response['proposals'] = $proposals;

    // ======================== CLIENT ACCEPTS PROPOSAL ========================
    } elseif ($action === 'accept' && $is_client) {
        $data        = json_decode(file_get_contents('php://input'), true) ?? [];
        $proposal_id = (int)($data['proposal_id'] ?? 0);
        if ($proposal_id <= 0) throw new Exception('رقم العرض غير صالح');

        // Get proposal + request + artisan info
        $stmt = $conn->prepare(
            "SELECT p.id, p.request_id, p.artisan_id,
                    jr.client_id, jr.status as jr_status, jr.title as jr_title,
                    c.full_name as artisan_name
             FROM proposals p
             JOIN job_requests jr ON jr.id = p.request_id
             JOIN craftsmen c ON c.id = p.artisan_id
             WHERE p.id = ? LIMIT 1"
        );
        $stmt->bind_param("i", $proposal_id);
        $stmt->execute();
        $prop = $stmt->get_result()->fetch_assoc();

        if (!$prop) throw new Exception('العرض غير موجود');
        if ((int)$prop['client_id'] !== $client_id) throw new Exception('غير مصرح');
        if ($prop['jr_status'] !== 'open') throw new Exception('هذا الطلب ليس مفتوحاً');

        $conn->begin_transaction();

        // Accept this proposal
        $conn->prepare("UPDATE proposals SET status='accepted' WHERE id=? LIMIT 1")
             ->bind_param("i", $proposal_id) || null;
        $conn->query("UPDATE proposals SET status='accepted' WHERE id=$proposal_id LIMIT 1");

        // Reject others
        $conn->query("UPDATE proposals SET status='rejected'
                      WHERE request_id={$prop['request_id']} AND id!=$proposal_id");

        // Mark request in_progress
        $conn->query("UPDATE job_requests SET status='in_progress' WHERE id={$prop['request_id']} LIMIT 1");

        // Create conversation
        $conv = $conn->prepare(
            "INSERT INTO conversations (request_id, proposal_id, client_id, artisan_id) VALUES (?,?,?,?) 
             ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)"
        );
        $conv->bind_param("iiii", $prop['request_id'], $proposal_id, $client_id, $prop['artisan_id']);
        $conv->execute();
        $conv_id = (int)$conn->insert_id;

        // Update completed_jobs
        $conn->query("UPDATE craftsmen SET completed_jobs = completed_jobs + 0 WHERE id={$prop['artisan_id']}"); // keep at 0 until done

        $conn->commit();

        // Notifications
        push_notif($conn, 'artisan', (int)$prop['artisan_id'], 'proposal_accepted',
            '🎉 تم قبول عرضك!',
            'تم قبول عرضك على طلب: ' . $prop['jr_title'],
            'chat.php?conv=' . $conv_id
        );

        $request_id = $prop['request_id'];
        // Notify rejected artisans
        $rej_stmt = $conn->prepare("SELECT artisan_id FROM proposals WHERE request_id=? AND status='rejected'");
        $rej_stmt->bind_param("i", $request_id);
        $rej_stmt->execute();
        foreach ($rej_stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
            push_notif($conn, 'artisan', (int)$r['artisan_id'], 'proposal_rejected',
                'تم اختيار حرفي آخر',
                'لم يتم قبول عرضك على الطلب: ' . $prop['jr_title'],
                ''
            );
        }

        $response['success']         = true;
        $response['message']         = 'تم قبول العرض وإنشاء المحادثة الخاصة بينكما';
        $response['conversation_id'] = $conv_id;
        $response['chat_url']        = '../chat.php?conv=' . $conv_id;

    // ======================== CLIENT REJECTS PROPOSAL ========================
    } elseif ($action === 'reject' && $is_client) {
        $data        = json_decode(file_get_contents('php://input'), true) ?? [];
        $proposal_id = (int)($data['proposal_id'] ?? 0);

        $stmt = $conn->prepare(
            "SELECT p.artisan_id, jr.client_id, jr.title as jr_title
             FROM proposals p JOIN job_requests jr ON jr.id=p.request_id
             WHERE p.id=? LIMIT 1"
        );
        $stmt->bind_param("i", $proposal_id);
        $stmt->execute();
        $p = $stmt->get_result()->fetch_assoc();
        if (!$p || (int)$p['client_id'] !== $client_id) throw new Exception('غير مصرح');

        $conn->query("UPDATE proposals SET status='rejected' WHERE id=$proposal_id LIMIT 1");

        push_notif($conn, 'artisan', (int)$p['artisan_id'], 'proposal_rejected',
            'تم رفض عرضك',
            'للأسف، لم يتم قبول عرضك على الطلب: ' . $p['jr_title'],
            ''
        );

        $response['success'] = true;
        $response['message'] = 'تم رفض العرض';

    // ======================== CLIENT CONTACTS ARTISAN (NO ACCEPT YET) ========================
    } elseif ($action === 'client_contact' && $is_client) {
        $data        = json_decode(file_get_contents('php://input'), true) ?? [];
        $proposal_id = (int)($data['proposal_id'] ?? 0);
        if ($proposal_id <= 0) throw new Exception('رقم العرض غير صالح');

        $stmt = $conn->prepare("SELECT p.request_id, p.artisan_id, jr.client_id, jr.title as jr_title FROM proposals p JOIN job_requests jr ON jr.id=p.request_id WHERE p.id=? LIMIT 1");
        $stmt->bind_param("i", $proposal_id);
        $stmt->execute();
        $p = $stmt->get_result()->fetch_assoc();
        
        if (!$p) throw new Exception('العرض غير موجود');
        if ((int)$p['client_id'] !== $client_id) throw new Exception('غير مصرح');

        // Create or get conversation
        $conv = $conn->prepare(
            "INSERT INTO conversations (request_id, proposal_id, client_id, artisan_id) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)"
        );
        $conv->bind_param("iiii", $p['request_id'], $proposal_id, $client_id, $p['artisan_id']);
        $conv->execute();
        $conv_id = (int)$conn->insert_id;

        $response['success'] = true;
        $response['conversation_id'] = $conv_id;
        $response['chat_url'] = '../chat.php?conv=' . $conv_id;

    // ======================== CLIENT TOGGLES FAVORITE ========================
    } elseif ($action === 'favorite' && $is_client) {
        $data        = json_decode(file_get_contents('php://input'), true) ?? [];
        $proposal_id = (int)($data['proposal_id'] ?? 0);

        $stmt = $conn->prepare("SELECT p.status, jr.client_id FROM proposals p JOIN job_requests jr ON jr.id=p.request_id WHERE p.id=? LIMIT 1");
        $stmt->bind_param("i", $proposal_id);
        $stmt->execute();
        $p = $stmt->get_result()->fetch_assoc();
        if (!$p || (int)$p['client_id'] !== $client_id) throw new Exception('غير مصرح');

        $new_status = $p['status'] === 'favorite' ? 'pending' : 'favorite';
        $conn->query("UPDATE proposals SET status='$new_status' WHERE id=$proposal_id LIMIT 1");

        $response['success']    = true;
        $response['is_favorite']= $new_status === 'favorite';
        $response['message']    = $new_status === 'favorite' ? 'أُضيف للمفضلة' : 'أُزيل من المفضلة';

    // ======================== ARTISAN VIEWS OWN PROPOSALS ========================
    } elseif ($action === 'my_proposals' && $is_artisan) {
        $stmt = $conn->prepare(
            "SELECT p.id, p.proposed_price, p.estimated_duration, p.status, p.created_at,
                    jr.id as request_id, jr.title, jr.category, jr.city, jr.urgency, jr.status as request_status,
                    c.full_name as client_name
             FROM proposals p
             JOIN job_requests jr ON jr.id = p.request_id
             JOIN clients c ON c.id = jr.client_id
             WHERE p.artisan_id = ?
             ORDER BY p.created_at DESC"
        );
        $stmt->bind_param("i", $artisan_id);
        $stmt->execute();
        $response['success']   = true;
        $response['proposals'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    } else {
        throw new Exception('الإجراء غير معروف');
    }

} catch (Exception $e) {
    if ($conn->in_transaction ?? false) { $conn->rollback(); }
    $response['message'] = $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>
