<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once 'config.php';
/** @var mysqli $conn */

$user_type  = $_SESSION['user_type'] ?? null;
$is_client  = ($user_type === 'client');
$is_artisan = ($user_type === 'craftsman');
$client_id  = $is_client  ? (int)$_SESSION['client_id']    : 0;
$artisan_id = $is_artisan ? (int)$_SESSION['craftsman_id'] : 0;

if (!$is_client && !$is_artisan) {
    echo json_encode(['success'=>false,'message'=>'يرجى تسجيل الدخول أولاً'], JSON_UNESCAPED_UNICODE);
    exit;
}

$action     = trim($_REQUEST['action'] ?? '');
$response   = ['success' => false, 'message' => 'خطأ'];
$sender_type = $is_client ? 'client' : 'artisan';
$sender_id   = $is_client ? $client_id : $artisan_id;

function get_and_verify_conv($conn, $conv_id, $sender_type, $client_id, $artisan_id) {
    $stmt = $conn->prepare("SELECT * FROM conversations WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $conv_id);
    $stmt->execute();
    $conv = $stmt->get_result()->fetch_assoc();
    if (!$conv) throw new Exception('المحادثة غير موجودة');
    if ($sender_type === 'client'  && (int)$conv['client_id']  !== $client_id)  throw new Exception('غير مصرح');
    if ($sender_type === 'artisan' && (int)$conv['artisan_id'] !== $artisan_id) throw new Exception('غير مصرح');
    return $conv;
}

function push_notif_chat($conn, $utype, $uid, $title, $body, $link = '') {
    $s = $conn->prepare("INSERT INTO notifications (user_type,user_id,type,title,body,link) VALUES(?,?,'new_message',?,?,?)");
    $s->bind_param("sisss", $utype, $uid, $title, $body, $link);
    $s->execute();
}

try {
    // ======================== LIST CONVERSATIONS ========================
    if ($action === 'conversations') {
        if ($is_client) {
            $stmt = $conn->prepare(
                "SELECT conv.id, conv.artisan_id, conv.request_id, conv.created_at,
                        conv.is_archived_client as is_archived, conv.is_muted_client as is_muted,
                        jr.title as request_title,
                        c.full_name as other_name, c.avatar as other_avatar, c.specialization as other_prof,
                        (SELECT content FROM chat_messages WHERE conversation_id=conv.id ORDER BY created_at DESC LIMIT 1) as last_msg,
                        (SELECT created_at FROM chat_messages WHERE conversation_id=conv.id ORDER BY created_at DESC LIMIT 1) as last_msg_at,
                        (SELECT COUNT(*) FROM chat_messages WHERE conversation_id=conv.id AND sender_type='artisan' AND status != 'seen') as unread_count
                 FROM conversations conv
                 JOIN job_requests jr ON jr.id=conv.request_id
                 JOIN craftsmen c ON c.id=conv.artisan_id
                 WHERE conv.client_id=?
                 ORDER BY conv.last_message_at DESC"
            );
            $stmt->bind_param("i", $client_id);
        } else {
            $stmt = $conn->prepare(
                "SELECT conv.id, conv.client_id, conv.request_id, conv.created_at,
                        conv.is_archived_artisan as is_archived, conv.is_muted_artisan as is_muted,
                        jr.title as request_title,
                        c.full_name as other_name, c.avatar as other_avatar,
                        (SELECT content FROM chat_messages WHERE conversation_id=conv.id ORDER BY created_at DESC LIMIT 1) as last_msg,
                        (SELECT created_at FROM chat_messages WHERE conversation_id=conv.id ORDER BY created_at DESC LIMIT 1) as last_msg_at,
                        (SELECT COUNT(*) FROM chat_messages WHERE conversation_id=conv.id AND sender_type='client' AND status != 'seen') as unread_count
                 FROM conversations conv
                 JOIN job_requests jr ON jr.id=conv.request_id
                 JOIN clients c ON c.id=conv.client_id
                 WHERE conv.artisan_id=?
                 ORDER BY conv.last_message_at DESC"
            );
            $stmt->bind_param("i", $artisan_id);
        }
        $stmt->execute();
        $response['success']       = true;
        $response['conversations'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // ======================== GET HISTORY ========================
    } elseif ($action === 'history') {
        $conv_id    = (int)($_GET['conv_id'] ?? 0);
        $page       = max(1, (int)($_GET['page'] ?? 1));
        $limit      = 30;
        $offset     = ($page - 1) * $limit;

        $conv = get_and_verify_conv($conn, $conv_id, $sender_type, $client_id, $artisan_id);

        $stmt = $conn->prepare(
            "SELECT id, sender_type, sender_id, message_type, content, file_path, file_name, file_size,
                    latitude, longitude, status, delivered_at, seen_at, created_at
             FROM chat_messages
             WHERE conversation_id = ?
             ORDER BY created_at DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->bind_param("iii", $conv_id, $limit, $offset);
        $stmt->execute();
        $messages = array_reverse($stmt->get_result()->fetch_all(MYSQLI_ASSOC));

        // Mark received messages as delivered
        $other_type = $sender_type === 'client' ? 'artisan' : 'client';
        $conn->query("UPDATE chat_messages SET status='delivered', delivered_at=NOW()
                      WHERE conversation_id=$conv_id AND sender_type='$other_type' AND status='sent'");

        $response['success']  = true;
        $response['messages'] = $messages;
        $response['conv']     = $conv;

    // ======================== POLL NEW MESSAGES ========================
    } elseif ($action === 'poll') {
        $conv_id      = (int)($_GET['conv_id'] ?? 0);
        $last_id      = (int)($_GET['last_id'] ?? 0);

        $conv = get_and_verify_conv($conn, $conv_id, $sender_type, $client_id, $artisan_id);

        $stmt = $conn->prepare(
            "SELECT id, sender_type, sender_id, message_type, content, file_path, file_name,
                    latitude, longitude, status, created_at
             FROM chat_messages
             WHERE conversation_id = ? AND id > ?
             ORDER BY created_at ASC
             LIMIT 50"
        );
        $stmt->bind_param("ii", $conv_id, $last_id);
        $stmt->execute();
        $new_msgs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        // Mark as delivered
        $other_type = $sender_type === 'client' ? 'artisan' : 'client';
        if (!empty($new_msgs)) {
            $conn->query("UPDATE chat_messages SET status='delivered', delivered_at=NOW()
                          WHERE conversation_id=$conv_id AND sender_type='$other_type' AND status='sent'");
        }

        // Typing indicator (simple session-based)
        $typing_key = 'typing_' . $conv_id . '_' . $other_type;
        $is_typing  = false;
        if (isset($_SESSION[$typing_key])) {
            $is_typing = (time() - $_SESSION[$typing_key]) < 5;
        }

        $response['success']   = true;
        $response['messages']  = $new_msgs;
        $response['is_typing'] = $is_typing;

    // ======================== SEND MESSAGE ========================
    } elseif ($action === 'send') {
        $conv_id    = (int)($_POST['conv_id'] ?? (json_decode(file_get_contents('php://input'),true)['conv_id'] ?? 0));
        $input      = json_decode(file_get_contents('php://input'), true) ?? [];
        if ($conv_id === 0) $conv_id = (int)($input['conv_id'] ?? 0);

        $conv = get_and_verify_conv($conn, $conv_id, $sender_type, $client_id, $artisan_id);

        $msg_type = trim($input['message_type'] ?? $_POST['message_type'] ?? 'text');
        $content  = trim($input['content'] ?? $_POST['content'] ?? '');
        $lat      = isset($input['latitude'])  ? (float)$input['latitude']  : null;
        $lng      = isset($input['longitude']) ? (float)$input['longitude'] : null;
        $file_path = null;
        $file_name = null;
        $file_size = null;

        if ($msg_type === 'text' && $content === '') throw new Exception('الرسالة لا يمكن أن تكون فارغة');

        if ($msg_type === 'location') {
            if (!$lat || !$lng) throw new Exception('إحداثيات الموقع غير صالحة');
        }

        // Handle file uploads
        $file_types = ['image','video','audio','pdf','voice','document'];
        if (in_array($msg_type, $file_types) && !empty($_FILES)) {
            $file = $_FILES['file'] ?? null;
            if (!$file || $file['error'] !== UPLOAD_ERR_OK) throw new Exception('فشل رفع الملف');

            $max_sizes = ['image'=>8*1024*1024,'video'=>50*1024*1024,'audio'=>10*1024*1024,'pdf'=>20*1024*1024,'voice'=>5*1024*1024,'document'=>20*1024*1024];
            if ($file['size'] > ($max_sizes[$msg_type] ?? 10*1024*1024)) throw new Exception('حجم الملف كبير جداً');

            $dir_rel = 'uploads/chats/' . $conv_id;
            $dir_abs = dirname(__DIR__) . '/' . $dir_rel;
            if (!is_dir($dir_abs)) mkdir($dir_abs, 0755, true);

            $ext   = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $fname = $msg_type . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            move_uploaded_file($file['tmp_name'], $dir_abs . '/' . $fname);

            $file_path = $dir_rel . '/' . $fname;
            $file_name = $file['name'];
            $file_size = $file['size'];
        }

        $stmt = $conn->prepare(
            "INSERT INTO chat_messages
                (conversation_id, sender_type, sender_id, message_type, content, file_path, file_name, file_size, latitude, longitude)
             VALUES (?,?,?,?,?,?,?,?,?,?)"
        );
        $stmt->bind_param("isissssidd", $conv_id, $sender_type, $sender_id, $msg_type, $content, $file_path, $file_name, $file_size, $lat, $lng);
        $stmt->execute();
        $msg_id = (int)$conn->insert_id;

        // Update conversation last_message_at
        $conn->query("UPDATE conversations SET last_message_at=NOW() WHERE id=$conv_id LIMIT 1");

        // Notify recipient
        $recv_type = $sender_type === 'client' ? 'artisan' : 'client';
        $recv_id   = $sender_type === 'client' ? (int)$conv['artisan_id'] : (int)$conv['client_id'];
        $recv_muted = $sender_type === 'client' ? $conv['is_muted_artisan'] : $conv['is_muted_client'];

        if (!$recv_muted) {
            $sender_name = $is_client ? ($_SESSION['client_name'] ?? 'عميل') : ($_SESSION['craftsman_name'] ?? 'حرفي');
            push_notif_chat($conn, $recv_type, $recv_id,
                'رسالة جديدة من ' . $sender_name,
                $msg_type === 'text' ? $content : '📎 ملف مرفق',
                'chat.php?conv=' . $conv_id
            );
        }

        $response['success']    = true;
        $response['message_id'] = $msg_id;

    // ======================== MARK SEEN ========================
    } elseif ($action === 'mark_seen') {
        $input   = json_decode(file_get_contents('php://input'), true) ?? [];
        $conv_id = (int)($input['conv_id'] ?? 0);
        $conv    = get_and_verify_conv($conn, $conv_id, $sender_type, $client_id, $artisan_id);

        $other_type = $sender_type === 'client' ? 'artisan' : 'client';
        $conn->query("UPDATE chat_messages SET status='seen', seen_at=NOW()
                      WHERE conversation_id=$conv_id AND sender_type='$other_type' AND status != 'seen'");

        $response['success'] = true;

    // ======================== TYPING INDICATOR ========================
    } elseif ($action === 'typing') {
        $input   = json_decode(file_get_contents('php://input'), true) ?? [];
        $conv_id = (int)($input['conv_id'] ?? 0);
        get_and_verify_conv($conn, $conv_id, $sender_type, $client_id, $artisan_id);
        $_SESSION['typing_' . $conv_id . '_' . $sender_type] = time();
        $response['success'] = true;

    // ======================== ARCHIVE / MUTE / REPORT ========================
    } elseif ($action === 'settings') {
        $input    = json_decode(file_get_contents('php://input'), true) ?? [];
        $conv_id  = (int)($input['conv_id'] ?? 0);
        $setting  = trim($input['setting'] ?? '');
        $value    = (int)($input['value'] ?? 0);
        get_and_verify_conv($conn, $conv_id, $sender_type, $client_id, $artisan_id);

        $col_map = [
            'archive'  => $sender_type === 'client' ? 'is_archived_client' : 'is_archived_artisan',
            'mute'     => $sender_type === 'client' ? 'is_muted_client'    : 'is_muted_artisan',
            'report'   => 'is_reported',
        ];
        if (!isset($col_map[$setting])) throw new Exception('الإعداد غير صالح');
        $col = $col_map[$setting];
        $conn->query("UPDATE conversations SET $col=$value WHERE id=$conv_id LIMIT 1");
        $response['success'] = true;

    // ======================== SEARCH MESSAGES ========================
    } elseif ($action === 'search') {
        $conv_id = (int)($_GET['conv_id'] ?? 0);
        $q       = trim($_GET['q'] ?? '');
        get_and_verify_conv($conn, $conv_id, $sender_type, $client_id, $artisan_id);

        if (mb_strlen($q) < 2) throw new Exception('كلمة البحث قصيرة جداً');
        $q_esc   = '%' . $conn->real_escape_string($q) . '%';
        $stmt = $conn->query("SELECT id, sender_type, content, created_at FROM chat_messages
                              WHERE conversation_id=$conv_id AND content LIKE '$q_esc'
                              ORDER BY created_at DESC LIMIT 30");
        $response['success'] = true;
        $response['results'] = $stmt->fetch_all(MYSQLI_ASSOC);

    } else {
        throw new Exception('الإجراء غير معروف');
    }

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>
