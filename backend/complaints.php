<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once 'config.php';
/** @var mysqli $conn */

$user_type  = $_SESSION['user_type'] ?? null;
$is_client  = ($user_type === 'client');
$is_artisan = ($user_type === 'craftsman');
$is_admin   = ($user_type === 'admin');
$client_id  = $is_client  ? (int)$_SESSION['client_id']    : 0;
$artisan_id = $is_artisan ? (int)$_SESSION['craftsman_id'] : 0;
$admin_id   = $is_admin   ? (int)$_SESSION['admin_id']     : 0;

if (!$is_client && !$is_admin) {
    echo json_encode(['success'=>false,'message'=>'يرجى تسجيل الدخول كعميل أولاً'], JSON_UNESCAPED_UNICODE);
    exit;
}

$action   = trim($_REQUEST['action'] ?? '');
$response = ['success' => false, 'message' => 'خطأ'];

function push_notif_c($conn, $utype, $uid, $type, $title, $body, $link = '') {
    $s = $conn->prepare("INSERT INTO notifications (user_type,user_id,type,title,body,link) VALUES(?,?,?,?,?,?)");
    $s->bind_param("sissss", $utype, $uid, $type, $title, $body, $link);
    $s->execute();
}

function apply_penalty($conn, $artisan_id, $severity) {
    $deductions = ['minor'=>5,'medium'=>10,'serious'=>20,'fraud'=>50];
    $deduct = $deductions[$severity] ?? 5;
    $conn->query("UPDATE craftsmen SET trust_score = GREATEST(0, trust_score - $deduct) WHERE id=$artisan_id LIMIT 1");
    $conn->query("UPDATE craftsmen SET reputation_score = GREATEST(0, reputation_score - $deduct*0.5) WHERE id=$artisan_id LIMIT 1");
}

try {
    // ======================== CLIENT FILES COMPLAINT ========================
    if ($action === 'create' && $is_client) {
        $data             = json_decode(file_get_contents('php://input'), true) ?? [];
        $artisan_id_c     = (int)($data['artisan_id'] ?? 0);
        $complaint_type   = trim($data['complaint_type'] ?? '');
        $description      = trim($data['description'] ?? '');
        $damage_amount    = isset($data['damage_amount']) && $data['damage_amount'] !== '' ? (float)$data['damage_amount'] : null;
        $incident_date    = trim($data['incident_date'] ?? '');

        $allowed_types = ['late_work','poor_quality','damaged_property','fraud','no_response','bad_behavior','incomplete_work','payment_dispute','other'];
        if ($artisan_id_c <= 0)                         throw new Exception('يرجى تحديد الحرفي');
        if (!in_array($complaint_type, $allowed_types)) throw new Exception('نوع الشكوى غير صالح');
        if (mb_strlen($description) < 20)               throw new Exception('وصف الشكوى يجب أن يكون 20 حرفاً على الأقل');

        // Verify client worked with artisan (has accepted proposal)
        $chk = $conn->prepare(
            "SELECT conv.id FROM conversations conv
             WHERE conv.client_id = ? AND conv.artisan_id = ?
             LIMIT 1"
        );
        $chk->bind_param("ii", $client_id, $artisan_id_c);
        $chk->execute();
        if ($chk->get_result()->num_rows === 0) {
            throw new Exception('لا يمكنك تقديم شكوى ضد حرفي لم تتعامل معه');
        }

        // Check for duplicate active complaint
        $dup = $conn->prepare(
            "SELECT id FROM complaints WHERE client_id=? AND artisan_id=? AND status NOT IN ('resolved','rejected') LIMIT 1"
        );
        $dup->bind_param("ii", $client_id, $artisan_id_c);
        $dup->execute();
        if ($dup->get_result()->num_rows > 0) throw new Exception('لديك شكوى مفتوحة ضد هذا الحرفي بالفعل');

        $incident_date = ($incident_date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $incident_date)) ? $incident_date : null;

        $stmt = $conn->prepare(
            "INSERT INTO complaints (client_id, artisan_id, complaint_type, description, damage_amount, incident_date)
             VALUES (?,?,?,?,?,?)"
        );
        $stmt->bind_param("iissds", $client_id, $artisan_id_c, $complaint_type, $description, $damage_amount, $incident_date);
        if (!$stmt->execute()) throw new Exception('فشل تقديم الشكوى');

        $complaint_id = (int)$conn->insert_id;

        // Notify admin
        $admin_stmt = $conn->prepare("SELECT id FROM admins LIMIT 1");
        $admin_stmt->execute();
        $admin_row = $admin_stmt->get_result()->fetch_assoc();
        if ($admin_row) {
            push_notif_c($conn, 'admin', (int)$admin_row['id'], 'new_complaint',
                '🚨 شكوى جديدة',
                'تم تقديم شكوى جديدة من عميل ضد حرفي. نوع الشكوى: ' . $complaint_type,
                'backend/admin_dashboard.php?section=complaints'
            );
        }

        $response['success']      = true;
        $response['message']      = 'تم تقديم شكواك. سيتولى فريق الإدارة مراجعتها وإعلامك بالنتيجة';
        $response['complaint_id'] = $complaint_id;

    // ======================== UPLOAD EVIDENCE ========================
    } elseif ($action === 'upload_evidence' && $is_client) {
        $complaint_id = (int)($_POST['complaint_id'] ?? 0);
        if ($complaint_id <= 0) throw new Exception('رقم الشكوى غير صالح');

        // Verify ownership
        $chk = $conn->prepare("SELECT id FROM complaints WHERE id=? AND client_id=? LIMIT 1");
        $chk->bind_param("ii", $complaint_id, $client_id);
        $chk->execute();
        if ($chk->get_result()->num_rows === 0) throw new Exception('غير مصرح');

        if (empty($_FILES)) throw new Exception('لم يتم رفع أي ملف');

        $upload_dir_rel = 'uploads/complaints/' . $complaint_id;
        $upload_dir_abs = dirname(__DIR__) . '/' . $upload_dir_rel;
        if (!is_dir($upload_dir_abs)) mkdir($upload_dir_abs, 0755, true);

        $allowed_types = [
            'image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp',
            'video/mp4'=>'mp4','video/quicktime'=>'mov','video/webm'=>'webm',
            'application/pdf'=>'pdf',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation'=>'pptx',
            'application/vnd.ms-powerpoint'=>'ppt'
        ];

        $uploaded = [];
        foreach ($_FILES as $key => $file) {
            if (!is_array($file['name'])) {
                $files_arr = [['name'=>$file['name'],'type'=>$file['type'],'tmp_name'=>$file['tmp_name'],'error'=>$file['error'],'size'=>$file['size']]];
            } else {
                $files_arr = [];
                for ($i = 0; $i < count($file['name']); $i++) {
                    $files_arr[] = ['name'=>$file['name'][$i],'type'=>$file['type'][$i],'tmp_name'=>$file['tmp_name'][$i],'error'=>$file['error'][$i],'size'=>$file['size'][$i]];
                }
            }
            foreach ($files_arr as $f) {
                if ($f['error'] !== UPLOAD_ERR_OK) continue;
                if ($f['size'] > 50 * 1024 * 1024) continue;

                $finfo_mime = mime_content_type($f['tmp_name']);
                if (!isset($allowed_types[$finfo_mime])) continue;

                $ext      = $allowed_types[$finfo_mime];
                $fname    = 'ev_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $abs_path = $upload_dir_abs . '/' . $fname;
                move_uploaded_file($f['tmp_name'], $abs_path);

                $rel_path  = $upload_dir_rel . '/' . $fname;
                $ftype     = str_starts_with($finfo_mime, 'image') ? 'image' : (str_starts_with($finfo_mime, 'video') ? 'video' : 'document');
                $fsize     = $f['size'];
                $orig_name = $f['name'];

                $ins = $conn->prepare("INSERT INTO complaint_evidence (complaint_id, file_path, file_type, file_name, file_size) VALUES (?,?,?,?,?)");
                $ins->bind_param("isssi", $complaint_id, $rel_path, $ftype, $orig_name, $fsize);
                $ins->execute();
                $uploaded[] = $rel_path;
            }
        }

        $response['success']  = true;
        $response['uploaded'] = $uploaded;
        $response['message']  = 'تم رفع ' . count($uploaded) . ' ملف(ات) بنجاح';

    // ======================== CLIENT VIEWS OWN COMPLAINTS ========================
    } elseif ($action === 'my_complaints' && $is_client) {
        $stmt = $conn->prepare(
            "SELECT comp.id, comp.complaint_type, comp.description, comp.status, comp.admin_notes,
                    comp.damage_amount, comp.incident_date, comp.created_at,
                    c.full_name as artisan_name, c.specialization as artisan_prof,
                    c.avatar as artisan_avatar, c.craftsman_id as artisan_code,
                    (SELECT COUNT(*) FROM complaint_evidence ce WHERE ce.complaint_id = comp.id) as evidence_count
             FROM complaints comp
             JOIN craftsmen c ON c.id = comp.artisan_id
             WHERE comp.client_id = ?
             ORDER BY comp.created_at DESC"
        );
        $stmt->bind_param("i", $client_id);
        $stmt->execute();
        $complaints = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        // Client artisans worked with (for new complaint form)
        $worked = $conn->prepare(
            "SELECT DISTINCT c.id, c.full_name, c.specialization as profession, c.avatar,
                    c.rating, c.completed_jobs, c.badge_type, c.documents_verified
             FROM conversations conv
             JOIN craftsmen c ON c.id = conv.artisan_id
             WHERE conv.client_id = ?
             ORDER BY conv.created_at DESC"
        );
        $worked->bind_param("i", $client_id);
        $worked->execute();

        $response['success']    = true;
        $response['complaints'] = $complaints;
        $response['worked_with']= $worked->get_result()->fetch_all(MYSQLI_ASSOC);

    // ======================== COMPLAINT DETAILS (WITH EVIDENCE) ========================
    } elseif ($action === 'complaint_details') {
        $complaint_id = (int)($_GET['id'] ?? 0);
        if ($complaint_id <= 0) throw new Exception('رقم الشكوى غير صالح');
        
        // Verify access (admin or the client who created it)
        if (!$is_admin && !$is_client) throw new Exception('غير مصرح');
        if ($is_client) {
            $chk = $conn->prepare("SELECT id FROM complaints WHERE id=? AND client_id=? LIMIT 1");
            $chk->bind_param("ii", $complaint_id, $client_id);
            $chk->execute();
            if ($chk->get_result()->num_rows === 0) throw new Exception('غير مصرح');
        }
        
        // Fetch details
        $stmt = $conn->prepare("SELECT c.*, cl.full_name as client_name, cr.full_name as artisan_name FROM complaints c JOIN clients cl ON c.client_id = cl.id JOIN craftsmen cr ON c.artisan_id = cr.id WHERE c.id = ?");
        $stmt->bind_param("i", $complaint_id);
        $stmt->execute();
        $comp = $stmt->get_result()->fetch_assoc();
        
        // Fetch evidence files
        $evt = $conn->prepare("SELECT * FROM complaint_evidence WHERE complaint_id = ?");
        $evt->bind_param("i", $complaint_id);
        $evt->execute();
        $evidence = $evt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        $response['success']   = true;
        $response['complaint'] = $comp;
        $response['evidence']  = $evidence;

    // ======================== ADMIN VIEWS ALL COMPLAINTS ========================
    } elseif ($action === 'admin_list' && $is_admin) {
        $status_filter = trim($_GET['status'] ?? '');
        $where         = $status_filter ? "WHERE comp.status = '$status_filter'" : '';
        $stmt = $conn->query(
            "SELECT comp.id, comp.complaint_type, comp.description, comp.status, comp.admin_notes,
                    comp.damage_amount, comp.incident_date, comp.created_at, comp.penalty_applied,
                    cl.full_name as client_name, cl.email as client_email,
                    cr.full_name as artisan_name, cr.email as artisan_email,
                    cr.craftsman_id as artisan_code, cr.trust_score, cr.completed_jobs,
                    cr.status as artisan_status,
                    (SELECT COUNT(*) FROM complaint_evidence ce WHERE ce.complaint_id = comp.id) as evidence_count,
                    (SELECT COUNT(*) FROM complaints c2 WHERE c2.artisan_id = comp.artisan_id) as total_complaints
             FROM complaints comp
             JOIN clients cl ON cl.id = comp.client_id
             JOIN craftsmen cr ON cr.id = comp.artisan_id
             $where
             ORDER BY comp.created_at DESC"
        );
        $response['success']    = true;
        $response['complaints'] = $stmt->fetch_all(MYSQLI_ASSOC);

    // ======================== ADMIN UPDATES STATUS ========================
    } elseif ($action === 'admin_update_status' && $is_admin) {
        $data         = json_decode(file_get_contents('php://input'), true) ?? [];
        $complaint_id = (int)($data['complaint_id'] ?? 0);
        $new_status   = trim($data['status'] ?? '');
        $admin_notes  = trim($data['admin_notes'] ?? '');
        $allowed_statuses = ['pending','under_review','need_more_info','accepted','rejected','resolved'];

        if (!in_array($new_status, $allowed_statuses)) throw new Exception('الحالة غير صالحة');

        $conn->query("UPDATE complaints SET status='$new_status', admin_notes='".addslashes($admin_notes)."', updated_at=NOW() WHERE id=$complaint_id LIMIT 1");

        // Get complaint info for notifications
        $info = $conn->query("SELECT client_id, artisan_id FROM complaints WHERE id=$complaint_id")->fetch_assoc();
        if ($info) {
            if ($new_status === 'accepted' || $new_status === 'resolved') {
                push_notif_c($conn, 'client', (int)$info['client_id'], 'complaint_resolved', '✅ تم قبول شكواك', 'تمت مراجعة شكواك وقبولها من قِبل الإدارة', '');
                push_notif_c($conn, 'artisan', (int)$info['artisan_id'], 'complaint_against', '⚠️ تم الفصل في شكوى', 'تم قبول شكوى مقدمة ضدك من عميل. راجع إدارة المنصة', '');
            } elseif ($new_status === 'rejected') {
                push_notif_c($conn, 'client', (int)$info['client_id'], 'complaint_rejected', 'تحديث شكواك', 'بعد المراجعة، لم يتم قبول شكواك حالياً', '');
            } elseif ($new_status === 'need_more_info') {
                push_notif_c($conn, 'client', (int)$info['client_id'], 'complaint_info', '📋 الإدارة تحتاج مزيداً من المعلومات', $admin_notes, '');
            }
        }

        $response['success'] = true;
        $response['message'] = 'تم تحديث حالة الشكوى';

    // ======================== ADMIN PENALTY ACTION ========================
    } elseif ($action === 'admin_action' && $is_admin) {
        $data         = json_decode(file_get_contents('php://input'), true) ?? [];
        $complaint_id = (int)($data['complaint_id'] ?? 0);
        $artisan_id_a = (int)($data['artisan_id'] ?? 0);
        $penalty      = trim($data['penalty'] ?? '');

        $valid_penalties = ['warn','request_explanation','suspend_temp','ban_permanent','reduce_trust','reduce_visibility','remove_badge','delete_account','close','reject'];
        if (!in_array($penalty, $valid_penalties)) throw new Exception('الإجراء غير صالح');

        switch ($penalty) {
            case 'warn':
                push_notif_c($conn, 'artisan', $artisan_id_a, 'warning', '⚠️ تحذير رسمي من الإدارة',
                    'تلقيت تحذيراً رسمياً من إدارة منصة حرفي بسبب شكوى مقدمة ضدك. يُرجى الالتزام بمعايير الجودة.', '');
                apply_penalty($conn, $artisan_id_a, 'minor');
                $conn->query("UPDATE complaints SET penalty_applied='warn' WHERE id=$complaint_id LIMIT 1");
                break;
            case 'suspend_temp':
                $conn->query("UPDATE craftsmen SET status='suspended' WHERE id=$artisan_id_a LIMIT 1");
                push_notif_c($conn, 'artisan', $artisan_id_a, 'suspended', '🚫 تم إيقاف حسابك مؤقتاً',
                    'تم إيقاف حسابك مؤقتاً من منصة حرفي بسبب مخالفات. تواصل مع الإدارة للاستفاء.', '');
                apply_penalty($conn, $artisan_id_a, 'serious');
                $conn->query("UPDATE complaints SET penalty_applied='suspend_temp' WHERE id=$complaint_id LIMIT 1");
                break;
            case 'ban_permanent':
                $conn->query("UPDATE craftsmen SET status='inactive' WHERE id=$artisan_id_a LIMIT 1");
                apply_penalty($conn, $artisan_id_a, 'fraud');
                $conn->query("UPDATE complaints SET penalty_applied='ban_permanent' WHERE id=$complaint_id LIMIT 1");
                break;
            case 'reduce_trust':
                apply_penalty($conn, $artisan_id_a, 'medium');
                $conn->query("UPDATE complaints SET penalty_applied='reduce_trust' WHERE id=$complaint_id LIMIT 1");
                break;
            case 'reduce_visibility':
                $conn->query("UPDATE craftsmen SET is_featured=0, reputation_score=GREATEST(0,reputation_score-15) WHERE id=$artisan_id_a LIMIT 1");
                $conn->query("UPDATE complaints SET penalty_applied='reduce_visibility' WHERE id=$complaint_id LIMIT 1");
                break;
            case 'remove_badge':
                $conn->query("UPDATE craftsmen SET badge_type=NULL, documents_verified=0 WHERE id=$artisan_id_a LIMIT 1");
                $conn->query("UPDATE complaints SET penalty_applied='remove_badge' WHERE id=$complaint_id LIMIT 1");
                break;
            case 'delete_account':
                $conn->query("DELETE FROM craftsmen WHERE id=$artisan_id_a LIMIT 1");
                $conn->query("UPDATE complaints SET penalty_applied='delete_account', status='resolved' WHERE id=$complaint_id LIMIT 1");
                break;
            case 'close': case 'reject':
                $conn->query("UPDATE complaints SET status='$penalty', penalty_applied='$penalty' WHERE id=$complaint_id LIMIT 1");
                break;
            case 'request_explanation':
                push_notif_c($conn, 'artisan', $artisan_id_a, 'explanation_needed', '📋 الإدارة تطلب توضيحاً',
                    'طلبت إدارة حرفي توضيحاً منك بشأن شكوى مقدمة ضدك. تواصل معنا في أقرب وقت.', '');
                $conn->query("UPDATE complaints SET penalty_applied='request_explanation' WHERE id=$complaint_id LIMIT 1");
                break;
        }

        $response['success'] = true;
        $response['message'] = 'تم تطبيق الإجراء بنجاح';

    } else {
        throw new Exception('الإجراء غير معروف');
    }

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>
