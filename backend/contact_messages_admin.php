<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['admin_id']) || (string)($_SESSION['user_type'] ?? '') !== 'admin') {
    header('Location: ../admin_login.html');
    exit;
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $action = trim((string)($_POST['action'] ?? ''));
    if ($id > 0 && in_array($action, ['seen', 'replied'], true)) {
        $stmt = $conn->prepare("UPDATE contact_messages SET status = ? WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("si", $action, $id);
            $stmt->execute();
            $message = 'تم تحديث حالة الرسالة.';
        }
    }
}

$rows = [];
$result = $conn->query("SELECT id, sender_name, sender_email, sender_phone, sender_type, subject, message_text, status, created_at FROM contact_messages ORDER BY created_at DESC LIMIT 300");
if ($result) {
    while ($r = $result->fetch_assoc()) {
        $rows[] = $r;
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>رسائل الزوار</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: "Cairo", sans-serif; margin: 0; background: #f1f5f9; color: #0f172a; }
        .wrap { max-width: 1200px; margin: 20px auto; padding: 0 14px; }
        .head { background: #fff; border: 1px solid #dbeafe; border-radius: 14px; padding: 14px; margin-bottom: 12px; display:flex; justify-content:space-between; align-items:center; gap:10px; }
        .head a { text-decoration:none; background:#1d4ed8; color:#fff; padding:8px 12px; border-radius:9px; }
        .msg { background:#ecfdf3; border:1px solid #86efac; color:#166534; border-radius:10px; padding:8px 10px; margin-bottom:10px; }
        .card { background:#fff; border:1px solid #e2e8f0; border-radius:14px; overflow:hidden; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px; border-bottom: 1px solid #e2e8f0; vertical-align: top; text-align: right; }
        th { background:#f8fafc; color:#475569; font-size:.9rem; }
        .status { padding:3px 9px; border-radius:999px; font-size:.78rem; font-weight:700; }
        .new { background:#fee2e2; color:#991b1b; }
        .seen { background:#fef3c7; color:#92400e; }
        .replied { background:#dcfce7; color:#166534; }
        .actions { display:flex; gap:6px; flex-wrap: wrap; }
        .btn { border:0; border-radius:8px; padding:6px 9px; cursor:pointer; font:inherit; font-size:.8rem; }
        .btn-seen { background:#f59e0b; color:#fff; }
        .btn-replied { background:#16a34a; color:#fff; }
        .mini { color:#64748b; font-size:.8rem; margin-top:3px; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="head">
            <div>
                <h2 style="margin:0"><i class="fas fa-inbox"></i> رسائل الزوار إلى الإدارة</h2>
                <div class="mini">هنا غتلقى جميع الرسائل اللي كيجيو من صفحة اتصل بنا.</div>
            </div>
            <a href="admin_dashboard.php"><i class="fas fa-arrow-right"></i> رجوع للوحة الإدارة</a>
        </div>

        <?php if ($message): ?>
            <div class="msg"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <div class="card">
            <?php if (count($rows) === 0): ?>
                <div style="padding:20px;text-align:center;color:#64748b;">لا توجد رسائل زوار حالياً.</div>
            <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>المرسل</th>
                        <th>التواصل</th>
                        <th>الموضوع/النص</th>
                        <th>الوقت</th>
                        <th>الحالة</th>
                        <th>إجراء</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($row['sender_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></strong>
                            <div class="mini"><?php echo htmlspecialchars($row['sender_type'] ?? 'guest', ENT_QUOTES, 'UTF-8'); ?></div>
                        </td>
                        <td>
                            <div><?php echo htmlspecialchars($row['sender_email'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="mini"><?php echo htmlspecialchars($row['sender_phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                        </td>
                        <td>
                            <?php if (!empty($row['subject'])): ?><div><strong><?php echo htmlspecialchars($row['subject'], ENT_QUOTES, 'UTF-8'); ?></strong></div><?php endif; ?>
                            <div><?php echo nl2br(htmlspecialchars($row['message_text'] ?? '', ENT_QUOTES, 'UTF-8')); ?></div>
                        </td>
                        <td><?php echo htmlspecialchars($row['created_at'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>
                            <?php $status = $row['status'] ?? 'new'; ?>
                            <span class="status <?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo $status === 'new' ? 'جديد' : ($status === 'seen' ? 'تمت القراءة' : 'تم الرد'); ?>
                            </span>
                        </td>
                        <td>
                            <form method="post" class="actions">
                                <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                                <button class="btn btn-seen" type="submit" name="action" value="seen">تمت القراءة</button>
                                <button class="btn btn-replied" type="submit" name="action" value="replied">تم الرد</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
