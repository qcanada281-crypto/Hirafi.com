<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once 'config.php';

$action = trim((string)($_GET['action'] ?? ''));

function respond($success, $message = '', $data = null) {
    echo json_encode([
        'success' => (bool)$success,
        'message' => (string)$message,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function isAdmin() {
    return isset($_SESSION['admin_id']) && (string)($_SESSION['user_type'] ?? '') === 'admin';
}

function isCraftsman() {
    return isset($_SESSION['craftsman_id']) && (string)($_SESSION['user_type'] ?? '') === 'craftsman';
}

function currentUser() {
    if (isAdmin()) {
        return ['type' => 'admin', 'id' => (int)$_SESSION['admin_id']];
    }
    if (isCraftsman()) {
        return ['type' => 'craftsman', 'id' => (int)$_SESSION['craftsman_id']];
    }
    return null;
}

function requestData() {
    $content_type = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));

    if (strpos($content_type, 'application/json') !== false) {
        $decoded = json_decode(file_get_contents('php://input'), true);
        return is_array($decoded) ? $decoded : [];
    }

    if (!empty($_POST)) {
        return $_POST;
    }

    $raw = file_get_contents('php://input');
    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }

    $parsed = [];
    parse_str($raw, $parsed);
    return is_array($parsed) ? $parsed : [];
}

function contactExists($conn, $type, $id) {
    if (!in_array($type, ['admin', 'craftsman'], true) || $id <= 0) {
        return false;
    }

    if ($type === 'admin') {
        $stmt = $conn->prepare("SELECT id FROM admins WHERE id = ? LIMIT 1");
    } else {
        $stmt = $conn->prepare("SELECT id FROM craftsmen WHERE id = ? LIMIT 1");
    }

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result && $result->num_rows > 0;
    $stmt->close();

    return $exists;
}

function buildAttachmentDataFromRow($row) {
    $path = trim((string)($row['attachment_path'] ?? ''));
    if ($path === '') {
        return null;
    }

    $mime = trim((string)($row['attachment_mime'] ?? ''));
    $type = trim((string)($row['attachment_type'] ?? ''));

    if ($type === '') {
        if (strpos($mime, 'image/') === 0) {
            $type = 'image';
        } elseif ($mime === 'application/pdf') {
            $type = 'pdf';
        } else {
            $type = 'file';
        }
    }

    return [
        'url' => str_replace('\\', '/', $path),
        'name' => trim((string)($row['attachment_name'] ?? '')) !== '' ? $row['attachment_name'] : basename($path),
        'mime' => $mime,
        'size' => (int)($row['attachment_size'] ?? 0),
        'type' => $type
    ];
}

function previewText($message_text, $attachment) {
    $message_text = trim((string)$message_text);
    if ($message_text !== '') {
        return $message_text;
    }

    if (!is_array($attachment)) {
        return null;
    }

    if (($attachment['type'] ?? '') === 'image') {
        return 'تم إرسال صورة';
    }

    if (($attachment['type'] ?? '') === 'pdf') {
        return 'تم إرسال ملف PDF';
    }

    return 'تم إرسال ملف مرفق';
}

function storeAttachment() {
    if (empty($_FILES['attachment']) || !is_array($_FILES['attachment'])) {
        return ['meta' => null];
    }

    $file = $_FILES['attachment'];
    $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) {
        return ['meta' => null];
    }

    if ($error !== UPLOAD_ERR_OK) {
        return ['error' => 'تعذر رفع الملف، حاول مرة أخرى'];
    }

    $size = (int)($file['size'] ?? 0);
    if ($size <= 0) {
        return ['error' => 'الملف المرفوع غير صالح'];
    }

    $max_size = 8 * 1024 * 1024;
    if ($size > $max_size) {
        return ['error' => 'حجم الملف كبير، الحد الأقصى هو 8MB'];
    }

    $tmp_name = (string)($file['tmp_name'] ?? '');
    if ($tmp_name === '' || !is_uploaded_file($tmp_name)) {
        return ['error' => 'تعذر قراءة الملف المرفوع'];
    }

    $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;
    $mime = $finfo ? (string)finfo_file($finfo, $tmp_name) : '';
    if ($finfo) {
        finfo_close($finfo);
    }
    if ($mime === '') {
        $mime = (string)($file['type'] ?? '');
    }

    $allowed = [
        'image/jpeg' => ['ext' => 'jpg', 'type' => 'image'],
        'image/png' => ['ext' => 'png', 'type' => 'image'],
        'image/webp' => ['ext' => 'webp', 'type' => 'image'],
        'image/gif' => ['ext' => 'gif', 'type' => 'image'],
        'application/pdf' => ['ext' => 'pdf', 'type' => 'pdf']
    ];

    if (!isset($allowed[$mime])) {
        return ['error' => 'مسموح فقط بالصور وملفات PDF'];
    }

    $upload_dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'messages';
    if (!is_dir($upload_dir) && !mkdir($upload_dir, 0775, true) && !is_dir($upload_dir)) {
        return ['error' => 'تعذر إنشاء مجلد حفظ الملفات'];
    }

    $stored_name = 'msg_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $allowed[$mime]['ext'];
    $destination = $upload_dir . DIRECTORY_SEPARATOR . $stored_name;

    if (!move_uploaded_file($tmp_name, $destination)) {
        return ['error' => 'فشل حفظ الملف المرفوع'];
    }

    $original_name = basename(str_replace('\\', '/', (string)($file['name'] ?? 'attachment')));

    return [
        'meta' => [
            'path' => '../uploads/messages/' . $stored_name,
            'name' => $original_name !== '' ? $original_name : $stored_name,
            'mime' => $mime,
            'size' => $size,
            'type' => $allowed[$mime]['type']
        ]
    ];
}

function normalizeConversationRow($row, $contact_type) {
    $contact_id = (int)($row['id'] ?? 0);
    $contact_name = trim((string)($row['full_name'] ?? '')) !== ''
        ? (string)$row['full_name']
        : ($contact_type === 'admin' ? 'الإدارة' : 'حرفي');
    $contact_meta = $contact_type === 'craftsman'
        ? trim(((string)($row['profession'] ?? '')) . ' - ' . ((string)($row['city'] ?? '')), ' -')
        : ((string)($row['role'] ?? 'admin'));
    $attachment = buildAttachmentDataFromRow($row);
    $last_message = previewText($row['last_message'] ?? '', $attachment);
    $unread_count = (int)($row['unread_count'] ?? 0);

    return [
        'contact_type' => $contact_type,
        'contact_id' => $contact_id,
        'contact_name' => $contact_name,
        'contact_meta' => $contact_meta,
        'status' => (string)($row['status'] ?? 'active'),
        'avatar' => (string)($row['avatar'] ?? ''),
        'last_message' => $last_message,
        'last_message_at' => $row['last_message_at'] ?? null,
        'last_attachment' => $attachment,
        'unread_count' => $unread_count,
        'sender_id' => $contact_id,
        'sender_type' => $contact_type,
        'sender_name' => $contact_name,
        'message_text' => $last_message,
        'created_at' => $row['last_message_at'] ?? null,
        'is_read' => $unread_count > 0 ? 0 : 1,
        
        'city' => trim((string)($row['city'] ?? '')),
        'phone' => trim((string)($row['phone'] ?? '')),
        'profession' => trim((string)($row['profession'] ?? '')),
        'rating' => $row['rating'] ?? 0,
        'profile_created_at' => $row['profile_created_at'] ?? null
    ];
}

function normalizeMessageRow($row) {
    $attachment = buildAttachmentDataFromRow($row);

    return [
        'id' => (int)($row['id'] ?? 0),
        'sender_type' => (string)($row['sender_type'] ?? ''),
        'sender_id' => (int)($row['sender_id'] ?? 0),
        'receiver_type' => (string)($row['receiver_type'] ?? ''),
        'receiver_id' => (int)($row['receiver_id'] ?? 0),
        'subject' => (string)($row['subject'] ?? ''),
        'message_text' => (string)($row['message_text'] ?? ''),
        'is_read' => (int)($row['is_read'] ?? 0),
        'created_at' => $row['created_at'] ?? null,
        'attachment' => $attachment,
        'attachment_url' => $attachment['url'] ?? null,
        'attachment_name' => $attachment['name'] ?? null,
        'attachment_mime' => $attachment['mime'] ?? null,
        'attachment_size' => $attachment['size'] ?? null,
        'attachment_type' => $attachment['type'] ?? null
    ];
}

$viewer = currentUser();
if ($viewer === null) {
    respond(false, 'غير مصرح لك');
}

switch ($action) {
    case 'me':
        respond(true, '', [
            'type' => $viewer['type'],
            'id' => $viewer['id'],
            'name' => $viewer['type'] === 'admin'
                ? ($_SESSION['admin_name'] ?? 'مدير')
                : ($_SESSION['craftsman_name'] ?? 'حرفي')
        ]);
        break;

    case 'send':
        $data = requestData();
        $receiver_id = (int)($data['receiver_id'] ?? 0);
        $receiver_type = trim((string)($data['receiver_type'] ?? ''));
        $subject = trim((string)($data['subject'] ?? ''));
        $message_text = trim((string)($data['message'] ?? ''));

        $stored_attachment = storeAttachment();
        if (isset($stored_attachment['error'])) {
            respond(false, $stored_attachment['error']);
        }

        $attachment = $stored_attachment['meta'] ?? null;

        if ($receiver_id <= 0 || ($message_text === '' && $attachment === null)) {
            respond(false, 'اكتب رسالة أو أرفق ملفاً قبل الإرسال');
        }

        if (!in_array($receiver_type, ['admin', 'craftsman'], true)) {
            respond(false, 'نوع المستقبل غير صالح');
        }

        if ($viewer['type'] === $receiver_type && $viewer['id'] === $receiver_id) {
            respond(false, 'لا يمكن إرسال رسالة لنفسك');
        }

        if ($viewer['type'] === 'craftsman' && $receiver_type !== 'admin') {
            respond(false, 'يمكنك مراسلة الإدارة فقط');
        }

        if (!contactExists($conn, $receiver_type, $receiver_id)) {
            respond(false, 'المستقبل غير موجود');
        }

        $attachment_path = $attachment['path'] ?? null;
        $attachment_name = $attachment['name'] ?? null;
        $attachment_mime = $attachment['mime'] ?? null;
        $attachment_size = $attachment['size'] ?? null;
        $attachment_type = $attachment['type'] ?? null;

        $stmt = $conn->prepare(
            "INSERT INTO messages (
                sender_type,
                sender_id,
                receiver_type,
                receiver_id,
                subject,
                message_text,
                attachment_path,
                attachment_name,
                attachment_mime,
                attachment_size,
                attachment_type
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        if (!$stmt) {
            respond(false, 'تعذر تجهيز عملية الإرسال');
        }

        $stmt->bind_param(
            "sisisssssis",
            $viewer['type'],
            $viewer['id'],
            $receiver_type,
            $receiver_id,
            $subject,
            $message_text,
            $attachment_path,
            $attachment_name,
            $attachment_mime,
            $attachment_size,
            $attachment_type
        );

        if (!$stmt->execute()) {
            respond(false, 'فشل إرسال الرسالة');
        }

        respond(true, 'تم إرسال الرسالة بنجاح', [
            'message_id' => $stmt->insert_id,
            'attachment' => $attachment
        ]);
        break;

    case 'get_unread_count':
        $stmt = $conn->prepare(
            "SELECT COUNT(*) AS total
             FROM messages
             WHERE receiver_type = ? AND receiver_id = ? AND is_read = 0"
        );

        if (!$stmt) {
            respond(false, 'تعذر تحميل عدد الرسائل غير المقروءة');
        }

        $stmt->bind_param("si", $viewer['type'], $viewer['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : ['total' => 0];
        respond(true, '', ['unread_count' => (int)($row['total'] ?? 0)]);
        break;

    case 'get_contacts':
    case 'get_conversations':
        if ($viewer['type'] === 'admin') {
            $search = trim((string)($_GET['search'] ?? ''));
            $params = [];
            $types = '';
            $where = '';

            if ($search !== '') {
                $where = "AND (c.full_name LIKE ? OR c.craftsman_id LIKE ? OR c.profession LIKE ?)";
                $like = '%' . $search . '%';
                $params = [$like, $like, $like];
                $types = 'sss';
            }

            $sql = "SELECT
                        c.id,
                        c.craftsman_id,
                        c.full_name,
                        c.city,
                        c.profession,
                        c.phone,
                        c.rating,
                        c.created_at AS profile_created_at,
                        c.status,
                        c.avatar,
                        lm.message_text AS last_message,
                        lm.created_at AS last_message_at,
                        lm.attachment_path,
                        lm.attachment_name,
                        lm.attachment_mime,
                        lm.attachment_size,
                        lm.attachment_type,
                        COALESCE(unread.unread_count, 0) AS unread_count
                    FROM craftsmen c
                    LEFT JOIN (
                        SELECT
                            CASE
                                WHEN sender_type = 'craftsman' THEN sender_id
                                ELSE receiver_id
                            END AS craftsman_id,
                            MAX(id) AS last_message_id
                        FROM messages
                        WHERE (sender_type = 'admin' AND sender_id = ? AND receiver_type = 'craftsman')
                           OR (receiver_type = 'admin' AND receiver_id = ? AND sender_type = 'craftsman')
                        GROUP BY craftsman_id
                    ) last_map ON last_map.craftsman_id = c.id
                    LEFT JOIN messages lm ON lm.id = last_map.last_message_id
                    LEFT JOIN (
                        SELECT sender_id AS craftsman_id, COUNT(*) AS unread_count
                        FROM messages
                        WHERE receiver_type = 'admin' AND receiver_id = ? AND sender_type = 'craftsman' AND is_read = 0
                        GROUP BY sender_id
                    ) unread ON unread.craftsman_id = c.id
                    WHERE 1=1 {$where}
                    ORDER BY
                        CASE WHEN lm.created_at IS NULL THEN 1 ELSE 0 END,
                        lm.created_at DESC,
                        c.full_name ASC
                    LIMIT 200";

            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                respond(false, 'تعذر تحميل لائحة الحرفيين');
            }

            if ($types === '') {
                $stmt->bind_param("iii", $viewer['id'], $viewer['id'], $viewer['id']);
            } else {
                $stmt->bind_param("iii" . $types, $viewer['id'], $viewer['id'], $viewer['id'], ...$params);
            }

            $stmt->execute();
            $result = $stmt->get_result();
            $rows = [];
            while ($row = $result->fetch_assoc()) {
                $rows[] = normalizeConversationRow($row, 'craftsman');
            }

            respond(true, '', $rows);
        }

        $sql = "SELECT
                    a.id,
                    a.full_name,
                    a.role,
                    a.status,
                    a.created_at AS profile_created_at,
                    '' AS phone,
                    '' AS city,
                    0 AS rating,
                    a.role AS profession,
                    '' AS avatar,
                    lm.message_text AS last_message,
                    lm.created_at AS last_message_at,
                    lm.attachment_path,
                    lm.attachment_name,
                    lm.attachment_mime,
                    lm.attachment_size,
                    lm.attachment_type,
                    COALESCE(unread.unread_count, 0) AS unread_count
                FROM admins a
                LEFT JOIN (
                    SELECT
                        CASE
                            WHEN sender_type = 'admin' THEN sender_id
                            ELSE receiver_id
                        END AS admin_id,
                        MAX(id) AS last_message_id
                    FROM messages
                    WHERE (sender_type = 'craftsman' AND sender_id = ? AND receiver_type = 'admin')
                       OR (receiver_type = 'craftsman' AND receiver_id = ? AND sender_type = 'admin')
                    GROUP BY admin_id
                ) last_map ON last_map.admin_id = a.id
                LEFT JOIN messages lm ON lm.id = last_map.last_message_id
                LEFT JOIN (
                    SELECT sender_id AS admin_id, COUNT(*) AS unread_count
                    FROM messages
                    WHERE receiver_type = 'craftsman' AND receiver_id = ? AND sender_type = 'admin' AND is_read = 0
                    GROUP BY sender_id
                ) unread ON unread.admin_id = a.id
                ORDER BY
                    CASE WHEN lm.created_at IS NULL THEN 1 ELSE 0 END,
                    lm.created_at DESC,
                    a.id ASC";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            respond(false, 'تعذر تحميل لائحة الإدارة');
        }

        $stmt->bind_param("iii", $viewer['id'], $viewer['id'], $viewer['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = [];

        while ($row = $result->fetch_assoc()) {
            $rows[] = normalizeConversationRow($row, 'admin');
        }

        respond(true, '', $rows);
        break;

    case 'get_messages':
        $other_id = (int)($_GET['user_id'] ?? $_GET['contact_id'] ?? 0);
        $other_type = trim((string)($_GET['user_type'] ?? $_GET['contact_type'] ?? ''));

        if ($other_id <= 0 || !in_array($other_type, ['admin', 'craftsman'], true)) {
            respond(false, 'بيانات جهة الاتصال غير صالحة');
        }

        if ($viewer['type'] === 'craftsman' && $other_type !== 'admin') {
            respond(false, 'يمكنك مراسلة الإدارة فقط');
        }

        if (!contactExists($conn, $other_type, $other_id)) {
            respond(false, 'جهة الاتصال غير موجودة');
        }

        $stmt = $conn->prepare(
            "SELECT
                id,
                sender_type,
                sender_id,
                receiver_type,
                receiver_id,
                subject,
                message_text,
                attachment_path,
                attachment_name,
                attachment_mime,
                attachment_size,
                attachment_type,
                is_read,
                created_at
             FROM messages
             WHERE (
                sender_type = ? AND sender_id = ? AND receiver_type = ? AND receiver_id = ?
             ) OR (
                sender_type = ? AND sender_id = ? AND receiver_type = ? AND receiver_id = ?
             )
             ORDER BY created_at ASC, id ASC"
        );

        if (!$stmt) {
            respond(false, 'تعذر تحميل الرسائل');
        }

        $stmt->bind_param(
            "sisisisi",
            $viewer['type'],
            $viewer['id'],
            $other_type,
            $other_id,
            $other_type,
            $other_id,
            $viewer['type'],
            $viewer['id']
        );
        $stmt->execute();
        $result = $stmt->get_result();
        $messages = [];

        while ($row = $result->fetch_assoc()) {
            $messages[] = normalizeMessageRow($row);
        }

        $mark_stmt = $conn->prepare(
            "UPDATE messages
             SET is_read = 1, read_at = NOW()
             WHERE receiver_type = ? AND receiver_id = ? AND sender_type = ? AND sender_id = ? AND is_read = 0"
        );

        if ($mark_stmt) {
            $mark_stmt->bind_param("sisi", $viewer['type'], $viewer['id'], $other_type, $other_id);
            $mark_stmt->execute();
        }

        respond(true, '', $messages);
        break;

    case 'get_craftsmen':
        if ($viewer['type'] !== 'admin') {
            respond(false, 'غير مصرح لك');
        }

        $search = trim((string)($_GET['search'] ?? ''));
        $like = '%' . $search . '%';
        $stmt = $conn->prepare(
            "SELECT id, craftsman_id, full_name, avatar, city, profession, status
             FROM craftsmen
             WHERE (? = '' OR full_name LIKE ? OR craftsman_id LIKE ? OR profession LIKE ?)
             ORDER BY full_name ASC
             LIMIT 50"
        );

        if (!$stmt) {
            respond(false, 'تعذر تحميل لائحة الحرفيين');
        }

        $stmt->bind_param("ssss", $search, $like, $like, $like);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = [];

        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }

        respond(true, '', $rows);
        break;

    case 'delete_conversation':
        $other_id = (int)($_GET['contact_id'] ?? 0);
        $other_type = trim((string)($_GET['contact_type'] ?? ''));

        if ($other_id <= 0 || !in_array($other_type, ['admin', 'craftsman'], true)) {
            respond(false, 'بيانات جهة الاتصال غير صالحة');
        }

        // Delete all messages between the viewer and the target contact
        $stmt = $conn->prepare(
            "DELETE FROM messages
             WHERE (sender_type = ? AND sender_id = ? AND receiver_type = ? AND receiver_id = ?)
                OR (sender_type = ? AND sender_id = ? AND receiver_type = ? AND receiver_id = ?)"
        );
        if (!$stmt) {
            respond(false, 'تعذر تنفيذ عملية الحذف');
        }
        
        $stmt->bind_param(
            "sisisisi",
            $viewer['type'],
            $viewer['id'],
            $other_type,
            $other_id,
            $other_type,
            $other_id,
            $viewer['type'],
            $viewer['id']
        );
        
        if ($stmt->execute()) {
            respond(true, 'تم مسح المحادثة بنجاح');
        } else {
            respond(false, 'فشل مسح المحادثة');
        }
        break;

    default:
        respond(false, 'إجراء غير معروف');
}
