<?php
session_start();
require_once 'config.php';

/** @var mysqli|null $conn */
$conn = (isset($conn) && $conn instanceof mysqli) ? $conn : null;
if ($conn === null) {
    http_response_code(500);
    exit('Database connection is not available.');
}

// التحقق من تسجيل الدخول كمدير
if (!isset($_SESSION['admin_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../admin_login.html');
    exit;
}

$admin_name = $_SESSION['admin_name'] ?? 'مدير النظام';
$admin_role = $_SESSION['admin_role'] ?? 'admin';

// معالجة إجراءات الحرفيين
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $craftsman_id = intval($_POST['craftsman_id'] ?? 0);
    
    if ($craftsman_id > 0) {
        if ($action === 'approve') {
            $stmt = $conn->prepare("UPDATE craftsmen SET status = 'active' WHERE id = ?");
            $stmt->bind_param("i", $craftsman_id);
            if ($stmt->execute()) {
                $message = 'تم تفعيل الحرفي بنجاح';
                $message_type = 'success';
            }
            $stmt->close();
        } elseif ($action === 'reject') {
            $stmt = $conn->prepare("UPDATE craftsmen SET status = 'suspended' WHERE id = ?");
            $stmt->bind_param("i", $craftsman_id);
            if ($stmt->execute()) {
                $message = 'تم رفض/إيقاف الحرفي بنجاح';
                $message_type = 'success';
            }
            $stmt->close();
        } elseif ($action === 'activate') {
            $stmt = $conn->prepare("UPDATE craftsmen SET status = 'active' WHERE id = ?");
            $stmt->bind_param("i", $craftsman_id);
            if ($stmt->execute()) {
                $message = 'تم إعادة تفعيل الحرفي بنجاح';
                $message_type = 'success';
            }
            $stmt->close();
        } elseif ($action === 'deactivate') {
            $stmt = $conn->prepare("UPDATE craftsmen SET status = 'inactive' WHERE id = ?");
            $stmt->bind_param("i", $craftsman_id);
            if ($stmt->execute()) {
                $message = 'تم تعطيل الحرفي بنجاح';
                $message_type = 'success';
            }
            $stmt->close();
        }
    }
}

// جلب إحصائيات
$total_craftsmen = 0;
$total_artisans = 0;
$pending_count = 0;

$result = $conn->query("SELECT COUNT(*) as count FROM craftsmen");
if ($result) {
    $row = $result->fetch_assoc();
    $total_craftsmen = $row['count'] ?? 0;
}

$result = $conn->query("SELECT COUNT(*) as count FROM craftsmen WHERE status = 'active'");
if ($result) {
    $row = $result->fetch_assoc();
    $total_artisans = $row['count'] ?? 0;
}

$result = $conn->query("SELECT COUNT(*) as count FROM craftsmen WHERE status = 'pending'");
if ($result) {
    $row = $result->fetch_assoc();
    $pending_count = $row['count'] ?? 0;
}

// جلب أحصائيات الطلبات والشكاوى
$pending_complaints_count = 0;
$comp_result = $conn->query("SELECT COUNT(*) as count FROM complaints WHERE status = 'pending'");
if ($comp_result) {
    if($row = $comp_result->fetch_assoc()) {
        $pending_complaints_count = $row['count'] ?? 0;
    }
}

// جلب قائمة الحرفيين
$filter = $_GET['filter'] ?? 'all';

// New: city filter and search query (q)
$city = trim($_GET['city'] ?? '');
$q = trim($_GET['q'] ?? '');

// Build dynamic WHERE clause with prepared statement parameters
$where_parts = [];
$params = [];
$types = '';

if ($filter === 'pending') {
    $where_parts[] = "status = ?";
    $types .= 's';
    $params[] = 'pending';
} elseif ($filter === 'active') {
    $where_parts[] = "status = ?";
    $types .= 's';
    $params[] = 'active';
} elseif ($filter === 'inactive') {
    $where_parts[] = "status = ?";
    $types .= 's';
    $params[] = 'inactive';
} elseif ($filter === 'suspended') {
    $where_parts[] = "status = ?";
    $types .= 's';
    $params[] = 'suspended';
}

if ($city !== '' && strtolower($city) !== 'all') {
    $where_parts[] = "city = ?";
    $types .= 's';
    $params[] = $city;
}

if ($q !== '') {
    // Search only by craft ID
    $where_parts[] = "craftsman_id LIKE ?";
    $types .= 's';
    $params[] = "%" . $q . "%";
}

$where_sql = '';
if (count($where_parts) > 0) {
    $where_sql = 'WHERE ' . implode(' AND ', $where_parts);
}

$craftsmen = [];
$sql = "SELECT id, craftsman_id, full_name, email, phone, city, profession, specialization, status, rating, created_at FROM craftsmen $where_sql ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
if ($stmt) {
    if ($types !== '') {
        // bind_param requires references
        $bind_names = [];
        $bind_names[] = & $types;
        for ($i = 0; $i < count($params); $i++) {
            $bind_names[] = & $params[$i];
        }
        call_user_func_array([$stmt, 'bind_param'], $bind_names);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $craftsmen[] = $row;
        }
    }
    $stmt->close();
} else {
    // fallback to simple query if prepare fails
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $craftsmen[] = $row;
        }
    }
}

// دالة لجلب تفاصيل الحرفي
function getCraftsmanDetails(mysqli $conn, int $id) {
    $stmt = $conn->prepare("SELECT c.*, 
        (SELECT COUNT(*) FROM artisan_portfolio WHERE craftsman_id = c.id) as portfolio_count,
        (SELECT COUNT(*) FROM documents WHERE craftsman_id = c.id) as documents_count
        FROM craftsmen c WHERE c.id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $details = $result->fetch_assoc();
    $stmt->close();
    return $details;
}

// معالجة طلب AJAX لجلب تفاصيل الحرفي
if (isset($_GET['action']) && $_GET['action'] === 'get_details' && isset($_GET['id'])) {
    header('Content-Type: application/json');
    $id = intval($_GET['id']);
    $details = getCraftsmanDetails($conn, $id);
    echo json_encode($details);
    exit;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة تحكم الإدارة - خدمة الحرفيين المغاربة</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    :root {
        --bg-start: #061724;
        --bg-end: #08192f;
        --surface: rgba(15, 23, 42, 0.92);
        --surface-strong: rgba(8, 15, 33, 0.95);
        --border: rgba(255, 255, 255, 0.08);
        --text: #e6eef8;
        --text-muted: #9ca3af;
        --accent: #3b82f6;
        --accent-alt: #8b5cf6;
        --success: #10b981;
        --danger: #ef4444;
        --warning: #f59e0b;
        --radius: 18px;
        --transition: 220ms ease;
    }

    body {
        margin: 0;
        min-height: 100vh;
        background: linear-gradient(180deg, var(--bg-start) 0%, var(--bg-end) 100%);
        color: var(--text);
        font-family: 'Cairo', sans-serif;
    }

    .dashboard-container {
        max-width: 1220px;
        margin: 0 auto;
        padding: 1.5rem 1rem 2rem;
    }

    .urgent-badge {
        background: #ef4444;
        color: white;
        border-radius: 50%;
        padding: 2px 7px;
        font-size: 0.8rem;
        font-weight: 800;
        margin-inline-start: 8px;
        display: inline-block;
    }
    
    .badge-pulse {
        animation: pulseEffect 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
    }

    @keyframes pulseEffect {
        0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); }
        70% { transform: scale(1.05); box-shadow: 0 0 0 10px rgba(239, 68, 68, 0); }
        100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
    }

    .admin-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        padding: 1.25rem 1.5rem;
        border-radius: 22px;
        background: rgba(255, 255, 255, 0.06);
        border: 1px solid var(--border);
        backdrop-filter: blur(20px);
        box-shadow: 0 18px 50px rgba(0, 0, 0, 0.18);
        position: relative;
    }

    .admin-header h1 {
        margin: 0;
        font-size: 1.4rem;
        font-weight: 800;
        letter-spacing: 0.02em;
        position: absolute;
        left: 50%;
        transform: translateX(-50%);
        white-space: nowrap;
        pointer-events: none;
    }

    .admin-header > div {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        flex-wrap: wrap;
    }

    .icon-pill,
    .logout-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        padding: 0.8rem 1rem;
        border-radius: 999px;
        border: 1px solid rgba(255, 255, 255, 0.1);
        background: rgba(255, 255, 255, 0.05);
        color: var(--text);
        text-decoration: none;
        transition: transform var(--transition), background var(--transition), border-color var(--transition);
    }

    .icon-pill:hover,
    .logout-btn:hover {
        transform: translateY(-1px);
        border-color: rgba(59, 130, 246, 0.5);
        background: rgba(59, 130, 246, 0.12);
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 1rem;
        margin-bottom: 1.75rem;
    }

    .stat-card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 22px;
        padding: 1.5rem;
        box-shadow: 0 20px 55px rgba(0, 0, 0, 0.15);
        cursor: pointer;
        transition: transform var(--transition), border-color var(--transition), box-shadow var(--transition);
    }

    .stat-card:hover {
        transform: translateY(-4px);
        border-color: rgba(59, 130, 246, 0.4);
        box-shadow: 0 24px 70px rgba(0, 0, 0, 0.22);
    }

    .stat-card i {
        font-size: 1.45rem;
        margin-bottom: 0.9rem;
        color: var(--accent);
    }

    .stat-card h3 {
        margin: 0;
        font-size: 2rem;
        line-height: 1;
    }

    .stat-card p {
        margin: 0.75rem 0 0;
        color: var(--text-muted);
    }

    .section-title {
        margin: 2rem 0 1rem;
        font-size: 1.3rem;
        font-weight: 800;
    }

    .admin-tabs {
        margin-bottom: 1.5rem;
    }

    .admin-tab {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.85rem 1.2rem;
        border-radius: 999px;
        border: 1px solid rgba(255, 255, 255, 0.08);
        background: rgba(255, 255, 255, 0.04);
        color: var(--text);
        cursor: pointer;
        font-weight: 700;
        transition: transform var(--transition), background var(--transition), border-color var(--transition);
    }

    .admin-tab.active {
        background: linear-gradient(90deg, var(--accent), var(--accent-alt));
        color: #fff;
        border-color: transparent;
        box-shadow: 0 18px 40px rgba(59, 130, 246, 0.25);
    }

    .toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1.5rem;
        flex-wrap: wrap;
    }

    .chips {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
    }

    .chip {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.75rem 1rem;
        border-radius: 999px;
        border: 1px solid rgba(255, 255, 255, 0.08);
        background: rgba(255, 255, 255, 0.04);
        color: var(--text-muted);
        text-decoration: none;
        transition: background var(--transition), border-color var(--transition), color var(--transition);
    }

    .chip.active {
        background: rgba(59, 130, 246, 0.16);
        border-color: rgba(59, 130, 246, 0.4);
        color: var(--text);
    }

    .search-panel {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.75rem;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 16px;
        padding: 0.85rem;
        min-width: 320px;
    }

    .search-panel input,
    .search-panel select {
        flex: 1 1 220px;
        min-width: 220px;
        padding: 0.95rem 1rem;
        border-radius: 14px;
        border: 1px solid rgba(255, 255, 255, 0.12);
        background: rgba(15, 23, 42, 0.95);
        color: var(--text);
        outline: none;
    }

    select option {
        background-color: var(--bg-start);
        color: var(--text);
    }

    .search-panel button {
        border: none;
        padding: 0.95rem 1.4rem;
        border-radius: 14px;
        background: linear-gradient(90deg, var(--accent), var(--accent-alt));
        color: #fff;
        font-weight: 700;
        cursor: pointer;
        transition: transform var(--transition), box-shadow var(--transition);
    }

    .search-panel button:hover {
        transform: translateY(-1px);
        box-shadow: 0 14px 30px rgba(59, 130, 246, 0.28);
    }

    .table-card {
        background: rgba(15, 23, 42, 0.96);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 24px;
        padding: 1rem;
        box-shadow: 0 24px 70px rgba(0, 0, 0, 0.18);
        overflow-x: auto;
    }

    table.premium {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        min-width: 900px;
    }

    table.premium thead th {
        position: sticky;
        top: 0;
        background: rgba(7, 11, 27, 0.97);
        padding: 1rem 0.85rem;
        text-align: right;
        color: var(--text-muted);
        font-weight: 700;
        border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    }

    table.premium tbody tr {
        transition: transform var(--transition), background var(--transition);
    }

    table.premium tbody tr:nth-child(odd) {
        background: rgba(255, 255, 255, 0.02);
    }

    table.premium tbody tr:hover {
        background: rgba(59, 130, 246, 0.12);
        transform: translateY(-1px);
    }

    table.premium td {
        padding: 1rem 0.85rem;
        color: var(--text);
        border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        vertical-align: middle;
    }

    .badge-saas {
        display: inline-flex;
        align-items: center;
        padding: 0.55rem 0.85rem;
        border-radius: 999px;
        font-size: 0.82rem;
        font-weight: 700;
    }

    .badge-active { background: rgba(16, 185, 129, 0.16); color: #8ef2c0; }
    .badge-pending { background: rgba(245, 158, 11, 0.18); color: #fde68a; }
    .badge-suspended { background: rgba(239, 68, 68, 0.18); color: #fecaca; }
    .badge-inactive { background: rgba(148, 163, 184, 0.16); color: #cbd5e1; }

    .actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.55rem;
    }

    .action-btn,
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.75rem 0.95rem;
        border-radius: 14px;
        border: 1px solid rgba(255, 255, 255, 0.08);
        background: rgba(255, 255, 255, 0.05);
        color: var(--text);
        cursor: pointer;
        transition: transform var(--transition), box-shadow var(--transition), background var(--transition);
    }

    .action-btn:hover,
    .btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 12px 28px rgba(59, 130, 246, 0.18);
        background: rgba(255, 255, 255, 0.08);
    }

    .btn-approve { background: rgba(16, 185, 129, 0.18); border-color: rgba(16, 185, 129, 0.35); }
    .btn-reject { background: rgba(239, 68, 68, 0.18); border-color: rgba(239, 68, 68, 0.35); }
    .btn-deactivate,
    .btn-activate { background: rgba(255, 255, 255, 0.06); }

    .modal {
        position: fixed;
        inset: 0;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 2rem;
        background: rgba(0, 0, 0, 0.72);
        z-index: 9999;
    }

    .modal.show {
        display: flex;
    }

    .modal-content {
        width: min(840px, 100%);
        background: #061124;
        border-radius: 24px;
        border: 1px solid rgba(255, 255, 255, 0.08);
        overflow: hidden;
        box-shadow: 0 40px 120px rgba(0, 0, 0, 0.45);
        max-height: 90vh;
        display: flex;
        flex-direction: column;
    }

    .modal-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 1.3rem 1.4rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    }

    .modal-header h2 {
        margin: 0;
        font-size: 1.2rem;
        color: var(--text);
    }

    .modal-close {
        width: 44px;
        height: 44px;
        border: none;
        border-radius: 16px;
        background: rgba(255, 255, 255, 0.06);
        color: var(--text);
        cursor: pointer;
    }

    .modal-body {
        padding: 1.5rem 1.6rem;
        color: var(--text-muted);
        overflow-y: auto;
    }

    .detail-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1rem;
    }

    .detail-item {
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 18px;
        padding: 1rem 1.1rem;
    }

    .detail-item label {
        display: block;
        margin-bottom: 0.55rem;
        color: var(--text);
        font-weight: 700;
    }

    .detail-item span,
    .detail-item p {
        margin: 0;
        color: var(--text-muted);
        line-height: 1.75;
        word-break: break-word;
    }

    .status-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.55rem 0.95rem;
        border-radius: 999px;
        font-weight: 700;
        font-size: 0.9rem;
    }

    .message {
        border-radius: 18px;
        padding: 1rem 1.25rem;
        margin-bottom: 1.25rem;
        border: 1px solid rgba(255, 255, 255, 0.08);
        background: rgba(255, 255, 255, 0.04);
    }

    .message-success {
        color: #a7f3d0;
        border-color: rgba(16, 185, 129, 0.24);
    }

    .message-error {
        color: #fecaca;
        border-color: rgba(239, 68, 68, 0.24);
    }

    .empty-state {
        text-align: center;
        padding: 2rem;
        margin-top: 1rem;
        border-radius: 20px;
        background: rgba(255, 255, 255, 0.04);
        border: 1px solid rgba(255, 255, 255, 0.08);
        color: var(--text-muted);
    }

    @media (max-width: 980px) {
        .stats-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .detail-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 680px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }

        .toolbar {
            flex-direction: column;
            align-items: stretch;
        }

        .search-panel {
            width: 100%;
        }

        .table-card {
            padding: 0.85rem;
        }

        table.premium {
            min-width: unset;
        }
    }
    .admin-section {
        display: none;
    }
    .admin-section.active {
        display: block;
    }
    </style>
</head>
<body>
    <header class="admin-header">
        <h1><i class="fas fa-cog"></i> لوحة تحكم الإدارة</h1>
        <div>
            <a class="icon-pill" href="contact_messages_admin.php" title="رسائل الزوار">
                <i class="fas fa-inbox"></i>
            </a>
            <a class="icon-pill" href="messages_center.php" title="رسائل الحرفيين">
                <i class="fas fa-comment-dots"></i>
            </a>
            <span>مرحباً <?php echo htmlspecialchars($admin_name); ?></span>
            <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> تسجيل الخروج</a>
        </div>
    </header>
    
    <div class="dashboard-container">
        <?php if ($message): ?>
        <div class="message <?php echo $message_type === 'success' ? 'message-success' : 'message-error'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>
        
        <h2 class="section-title">إحصائيات الحرفيين</h2>
        
        <div class="stats-grid">
            <div class="stat-card" onclick="window.location.href='?filter=all'">
                <i class="fas fa-users"></i>
                <h3><?php echo $total_craftsmen; ?></h3>
                <p>إجمالي الحرفيين</p>
            </div>
            <div class="stat-card" onclick="window.location.href='?filter=active'" <?php echo $filter === 'active' ? 'style="border:2px solid #1e40af"' : ''; ?>>
                <i class="fas fa-check-circle"></i>
                <h3><?php echo $total_artisans; ?></h3>
                <p>الحرفيين النشطين</p>
            </div>
            <div class="stat-card" onclick="window.location.href='?filter=pending'" <?php echo $filter === 'pending' ? 'style="border:2px solid #1e40af"' : ''; ?>>
                <i class="fas fa-clock"></i>
                <h3><?php echo $pending_count; ?></h3>
                <p>في انتظار التفعيل</p>
                <?php if ($pending_count > 0): ?>
                <span class="badge"><?php echo $pending_count; ?> جديد</span>
                <?php endif; ?>
            </div>
            <div class="stat-card" onclick="window.location.href='?filter=suspended'" <?php echo $filter === 'suspended' ? 'style="border:2px solid #1e40af"' : ''; ?>>
                <i class="fas fa-ban"></i>
                <h3>
                    <?php 
                    $suspended = 0;
                    $result = $conn->query("SELECT COUNT(*) as count FROM craftsmen WHERE status = 'suspended'");
                    if ($result) { $row = $result->fetch_assoc(); $suspended = $row['count'] ?? 0; }
                    echo $suspended;
                    ?>
                </h3>
                <p>الموقوفين</p>
            </div>
        </div>
        
        <h2 class="section-title">إدارة الحرفيين</h2>
        
        <div class="admin-tabs" style="display: flex; align-items: center; justify-content: center; gap: 1rem;">
            <button class="admin-tab active" data-section="craftsmen" onclick="showAdminSection('craftsmen')">
                <i class="fas fa-users"></i> إدارة الحرفيين
            </button>
            <button class="admin-tab" data-section="complaints" onclick="showAdminSection('complaints'); loadAdminComplaints();">
                <i class="fas fa-exclamation-triangle"></i> الشكاوى والمخالفات
                <?php if ($pending_complaints_count > 0): ?>
                <span class="urgent-badge badge-pulse"><?php echo $pending_complaints_count; ?></span>
                <?php endif; ?>
            </button>
        </div>
        
        <!-- Craftsmen Section -->
        <div id="craftsmen-section" class="admin-section active">
        
        <div class="toolbar">
            <div class="left">
                <div class="chips" role="tablist" aria-label="filters">
                    <a href="?filter=all<?php echo $city ? '&city='.urlencode($city) : ''; ?><?php echo $q ? '&q='.urlencode($q) : ''; ?>" class="chip <?php echo $filter === 'all' ? 'active' : ''; ?>">الكل <span style="opacity:.7;margin-inline-start:6px">(<?php echo $total_craftsmen; ?>)</span></a>
                    <a href="?filter=active<?php echo $city ? '&city='.urlencode($city) : ''; ?><?php echo $q ? '&q='.urlencode($q) : ''; ?>" class="chip <?php echo $filter === 'active' ? 'active' : ''; ?>">نشطون <span style="opacity:.7;margin-inline-start:6px">(<?php echo $total_artisans; ?>)</span></a>
                    <a href="?filter=pending<?php echo $city ? '&city='.urlencode($city) : ''; ?><?php echo $q ? '&q='.urlencode($q) : ''; ?>" class="chip <?php echo $filter === 'pending' ? 'active' : ''; ?>">في الانتظار <span style="opacity:.7;margin-inline-start:6px">(<?php echo $pending_count; ?>)</span></a>
                    <a href="?filter=inactive<?php echo $city ? '&city='.urlencode($city) : ''; ?><?php echo $q ? '&q='.urlencode($q) : ''; ?>" class="chip <?php echo $filter === 'inactive' ? 'active' : ''; ?>">معطلون</a>
                    <a href="?filter=suspended<?php echo $city ? '&city='.urlencode($city) : ''; ?><?php echo $q ? '&q='.urlencode($q) : ''; ?>" class="chip <?php echo $filter === 'suspended' ? 'active' : ''; ?>">موقوفون</a>
                </div>
            </div>
            <form id="advancedSearch" class="search-panel" method="get">
                <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>" id="filterInput">
                <input type="text" name="q" id="searchIdInput" placeholder="بحث برقم الحرفي CRAFT000000" value="<?php echo htmlspecialchars($q); ?>" class="search-input" />
                <button type="submit"><i class="fas fa-search"></i> بحث</button>
            </form>
        </div>
        
        <?php if (count($craftsmen) > 0): ?>
        <div class="table-card">
            <table class="premium">
                <thead>
                    <tr>
                        <th>الرقم <i class="fas fa-sort" style="opacity:.5;margin-inline-start:6px"></i></th>
                        <th>الاسم <i class="fas fa-sort" style="opacity:.5;margin-inline-start:6px"></i></th>
                        <th>البريد الإلكتروني</th>
                        <th>الهاتف</th>
                        <th>المدينة</th>
                        <th>المهنة</th>
                        <th>الحالة</th>
                        <th>تاريخ التسجيل</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($craftsmen as $craftsman): ?>
                    <tr data-id="<?php echo htmlspecialchars($craftsman['craftsman_id'] ?? ''); ?>" data-profession="<?php echo htmlspecialchars($craftsman['profession'] ?? ''); ?>" data-city="<?php echo htmlspecialchars($craftsman['city'] ?? ''); ?>" data-fullname="<?php echo htmlspecialchars($craftsman['full_name'] ?? ''); ?>" data-email="<?php echo htmlspecialchars($craftsman['email'] ?? ''); ?>" data-phone="<?php echo htmlspecialchars($craftsman['phone'] ?? ''); ?>">
                        <td><?php echo htmlspecialchars($craftsman['craftsman_id'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($craftsman['full_name'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($craftsman['email'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($craftsman['phone'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($craftsman['city'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($craftsman['profession'] ?? ''); ?></td>
                        <td>
                            <span class="badge-saas badge-<?php echo $craftsman['status'] ?? ''; ?>">
                                <?php 
                                $status_text = '';
                                switch($craftsman['status'] ?? '') {
                                    case 'pending': $status_text = 'في الانتظار'; break;
                                    case 'active': $status_text = 'نشط'; break;
                                    case 'inactive': $status_text = 'معطل'; break;
                                    case 'suspended': $status_text = 'موقوف'; break;
                                    default: $status_text = $craftsman['status'] ?? '';
                                }
                                echo $status_text;
                                ?>
                            </span>
                        </td>
                        <td><?php echo date('Y-m-d', strtotime($craftsman['created_at'] ?? '')); ?></td>
                        <td>
                            <div class="actions">
                                <button class="action-btn" title="تفاصيل" onclick="viewDetails(<?php echo $craftsman['id']; ?>)">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <?php if ($craftsman['status'] === 'pending'): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="approve">
                                    <input type="hidden" name="craftsman_id" value="<?php echo $craftsman['id']; ?>">
                                    <button type="submit" class="btn btn-approve" onclick="return confirm('هل أنت متأكد من تفعيل هذا الحرفي؟')">
                                        <i class="fas fa-check"></i> تفعيل
                                    </button>
                                </form>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="reject">
                                    <input type="hidden" name="craftsman_id" value="<?php echo $craftsman['id']; ?>">
                                    <button type="submit" class="btn btn-reject" onclick="return confirm('هل أنت متأكد من رفض هذا الحرفي؟')">
                                        <i class="fas fa-times"></i> رفض
                                    </button>
                                </form>
                                <?php elseif ($craftsman['status'] === 'active'): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="deactivate">
                                    <input type="hidden" name="craftsman_id" value="<?php echo $craftsman['id']; ?>">
                                    <button type="submit" class="btn btn-deactivate" onclick="return confirm('هل أنت متأكد من تعطيل هذا الحرفي؟')">
                                        <i class="fas fa-pause"></i> تعطيل
                                    </button>
                                </form>
                                <?php elseif ($craftsman['status'] === 'inactive'): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="activate">
                                    <input type="hidden" name="craftsman_id" value="<?php echo $craftsman['id']; ?>">
                                    <button type="submit" class="btn btn-activate" onclick="return confirm('هل أنت متأكد من إعادة تفعيل هذا الحرفي؟')">
                                        <i class="fas fa-play"></i> تفعيل
                                    </button>
                                </form>
                                <?php elseif ($craftsman['status'] === 'suspended'): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="activate">
                                    <input type="hidden" name="craftsman_id" value="<?php echo $craftsman['id']; ?>">
                                    <button type="submit" class="btn btn-activate" onclick="return confirm('هل أنت متأكد من إعادة تفعيل هذا الحرفي؟')">
                                        <i class="fas fa-play"></i> إلغاء الإيقاف
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-user-slash"></i>
            <p>لا يوجد حرفيون في هذه الفئة</p>
        </div>
        <?php endif; ?>
        </div>

        <!-- Complaints Section -->
        <div id="complaints-section" class="admin-section">
            <div class="toolbar">
                <div class="left">
                    <div class="chips" role="tablist">
                        <button onclick="filterAdminComplaints('all')" class="chip active" id="complaints-filter-all">الكل</button>
                        <button onclick="filterAdminComplaints('pending')" class="chip" id="complaints-filter-pending">قيد الانتظار</button>
                        <button onclick="filterAdminComplaints('under_review')" class="chip" id="complaints-filter-under_review">قيد المراجعة</button>
                        <button onclick="filterAdminComplaints('resolved')" class="chip" id="complaints-filter-resolved">تمت التسوية</button>
                    </div>
                </div>
            </div>
            <div class="table-card">
                <table class="premium">
                    <thead>
                        <tr>
                            <th>الرقم</th>
                            <th>العميل</th>
                            <th>الحرفي</th>
                            <th>نوع الشكوى</th>
                            <th>التاريخ</th>
                            <th>الحالة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody id="complaints-table-body">
                        <tr>
                            <td colspan="7" style="text-align:center; padding:2rem; color:var(--text-muted);">جاري تحميل الشكاوى...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Modal for Artisan Details -->
    <div id="detailsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-user"></i> تفاصيل الحرفي</h2>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body" id="modalBody">
                <div class="loading-state">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p>جاري تحميل التفاصيل...</p>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    function viewDetails(id) {
        const modal = document.getElementById('detailsModal');
        const modalBody = document.getElementById('modalBody');
        
        modal.classList.add('show');
        
        // Fetch artisan details
        fetch('admin_dashboard.php?action=get_details&id=' + id)
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.error) {
                    modalBody.innerHTML = '<p style="color: red; text-align: center;">' + data.error + '</p>';
                    return;
                }
                
                const statusText = {
                    'pending': 'في الانتظار',
                    'active': 'نشط',
                    'inactive': 'معطل',
                    'suspended': 'موقوف'
                };
                
                const genderText = {
                    'male': 'ذكر',
                    'female': 'أنثى'
                };
                
                modalBody.innerHTML = `
                    <div class="detail-grid">
                        <div class="detail-item">
                            <label>رقم الحرفي</label>
                            <span>${data.craftsman_id || '-'}</span>
                        </div>
                        <div class="detail-item">
                            <label>الاسم الكامل</label>
                            <span>${data.full_name || '-'}</span>
                        </div>
                        <div class="detail-item">
                            <label>البريد الإلكتروني</label>
                            <span>${data.email || '-'}</span>
                        </div>
                        <div class="detail-item">
                            <label>رقم الهاتف</label>
                            <span>${data.phone || '-'}</span>
                        </div>
                        <div class="detail-item">
                            <label>المدينة</label>
                            <span>${data.city || '-'}</span>
                        </div>
                        <div class="detail-item">
                            <label>المهنة</label>
                            <span>${data.profession || '-'}</span>
                        </div>
                        <div class="detail-item">
                            <label>التخصص</label>
                            <span>${data.specialization || '-'}</span>
                        </div>
                        <div class="detail-item">
                            <label>سنوات الخبرة</label>
                            <span>${data.experience_years || 0} سنوات</span>
                        </div>
                        <div class="detail-item">
                            <label>مستوى الخبرة</label>
                            <span>${data.experience_label || '-'}</span>
                        </div>
                        <div class="detail-item">
                            <label>تاريخ الميلاد</label>
                            <span>${data.date_of_birth || '-'}</span>
                        </div>
                        <div class="detail-item">
                            <label>الجنس</label>
                            <span>${genderText[data.gender] || '-'}</span>
                        </div>
                        <div class="detail-item">
                            <label>التقييم</label>
                            <span>${data.rating || 0} / 5 (${data.total_reviews || 0} تقييم)</span>
                        </div>
                        <div class="detail-item">
                            <label>عدد الأعمال في المعرض</label>
                            <span>${data.portfolio_count || 0}</span>
                        </div>
                        <div class="detail-item">
                            <label>عدد المستندات</label>
                            <span>${data.documents_count || 0}</span>
                        </div>
                        <div class="detail-item">
                            <label>الحالة</label>
                            <span class="status-badge status-${data.status}">${statusText[data.status] || data.status}</span>
                        </div>
                        <div class="detail-item">
                            <label>تاريخ التسجيل</label>
                            <span>${data.created_at ? new Date(data.created_at).toLocaleDateString('ar-EG') : '-'}</span>
                        </div>
                    </div>
                    ${data.bio ? `
                    <div style="margin-top: 1rem;">
                        <div class="detail-item">
                            <label>النبذة الشخصية</label>
                            <p>${data.bio}</p>
                        </div>
                    </div>
                    ` : ''}
                `;
            })
            .catch(function(error) {
                modalBody.innerHTML = '<p style="color: red; text-align: center;">حدث خطأ في تحميل البيانات</p>';
            });
    }
    
    function closeModal() {
        document.getElementById('detailsModal').classList.remove('show');
    }
    
    // Close modal when clicking outside
    document.getElementById('detailsModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeModal();
        }
    });
    
    // Close modal with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeModal();
        }
    });
    
    // Messages functionality disabled in this dashboard page
    // because the chat/conversations UI elements are not present here.
    let currentConversation = null;
    let messagesInterval = null;
    const __admin_dashboard_disable_messages = true;

    
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
    
    function toggleMessages() {
        if (__admin_dashboard_disable_messages) return;
        showAdminSection('messages');
        loadConversations();
        checkUnreadMessages();
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
    
    function loadConversations() {
        const listEl = document.getElementById('admin-conversationsList') || document.getElementById('conversationsList');
        if (!listEl) return;
        fetch('messages.php?action=get_conversations')
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.data && data.data.length > 0) {
                    listEl.innerHTML = data.data.map(function(conv) { return `
                        <div class="conversation-item ${conv.unread_count > 0 ? 'unread' : ''} ${currentConversation && currentConversation.userId === conv.sender_id && currentConversation.userType === conv.sender_type ? 'active' : ''}" 
                             data-name="${escapeMessageHtml(conv.sender_name || 'حرفي')}"
                             onclick="selectConversation(${conv.sender_id}, '${conv.sender_type}', this.dataset.name, this)">
                            <div class="conversation-header">
                                <span class="conversation-name">${escapeMessageHtml(conv.sender_name || 'حرفي')}</span>
                                <span class="conversation-time">${formatDate(conv.created_at)}</span>
                            </div>
                            <div class="conversation-preview">${escapeMessageHtml(conv.message_text || '')}</div>
                        </div>
                    `; }).join('');
                } else {
                    listEl.innerHTML = '<div style="padding:1rem;text-align:center;color:#94a3b8;">لا توجد محادثات</div>';
                }
                checkUnreadMessages();
            })
            .catch(function () {
                listEl.innerHTML = '<div style="padding:1rem;text-align:center;color:#94a3b8;">تعذر تحميل المحادثات</div>';
            });
    }
    
    function selectConversation(userId, userType, userName, element) {
        currentConversation = { userId, userType, userName };
        
        document.querySelectorAll('.conversation-item').forEach(function(item) { item.classList.remove('active'); });
        if (element) {
            element.classList.add('active');
        }
        
        document.getElementById('chatHeader').innerHTML = `
            <span><i class="fas fa-user"></i> ${escapeMessageHtml(userName)}</span>
            <button onclick="openNewMessage()" style="padding:0.4rem 0.8rem;background:#3b82f6;color:white;border:none;border-radius:5px;cursor:pointer;">
                <i class="fas fa-plus"></i> رسالة جديدة
            </button>
        `;
        
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
                    container.innerHTML = data.data.map(function(msg) { return renderMessageContent(msg, 'admin'); }).join('');
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
    
    function openNewMessage() {
        const search = prompt('ابحث عن الحرفي (اكتب اسم أو رقم):');
        if (!search) return;
        
        fetch('messages.php?action=get_craftsmen&search=' + encodeURIComponent(search))
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.data && data.data.length > 0) {
                    const craftsman = data.data[0];
                    selectConversation(craftsman.id, 'craftsman', craftsman.full_name);
                } else {
                    alert('لم يتم العثور على حرفي بهذا الاسم');
                }
            })
            .catch(function () {
                alert('تعذر تحميل لائحة الحرفيين');
            });
    }
    
    const adminMessageInput = document.getElementById('messageInput');
    if (adminMessageInput) {
        adminMessageInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') sendMessage();
        });
    }
    
    const adminAttachmentInput = document.getElementById('messageAttachment');
    if (adminAttachmentInput) {
        adminAttachmentInput.addEventListener('change', updateAttachmentName);
    }
    
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
    if (!__admin_dashboard_disable_messages) {
        checkUnreadMessages();
    }

    
    // Section switching
    function showAdminSection(section) {
        document.querySelectorAll('.admin-tab').forEach(function(t) { t.classList.remove('active'); });
        document.querySelectorAll('.admin-section').forEach(function(s) { s.classList.remove('active'); });
        
        const activeTab = document.querySelector(`.admin-tab[data-section="${section}"]`);
        const activeSection = document.getElementById(section + '-section');
        
        if (activeTab) {
            activeTab.classList.add('active');
        }
        
        if (!activeSection) {
            return;
        }
        
        activeSection.classList.add('active');
    }
    
    // === CHAT & COMPLAINTS WORK ===
    let allComplaints = [];
    async function loadAdminComplaints() {
        try {
            const res = await fetch('complaints.php?action=admin_list');
            const data = await res.json();
            if (data.success) {
                allComplaints = data.complaints;
                renderComplaintsTable(allComplaints);
            }
        } catch(e) {}
    }

    function renderComplaintsTable(list) {
        const body = document.getElementById('complaints-table-body');
        if (!body) return;
        if (list.length === 0) {
            body.innerHTML = `<tr><td colspan="7" style="text-align:center; padding:2rem; color:var(--text-muted);">لا توجد شكاوى حالياً</td></tr>`;
            return;
        }
        const typesAr = {
            'late_work': 'تأخر في العمل',
            'poor_quality': 'جودة عمل ضعيفة',
            'damaged_property': 'إتلاف الممتلكات',
            'fraud': 'عملية احتيال',
            'no_response': 'عدم الرد التام',
            'bad_behavior': 'معاملة سيئة',
            'incomplete_work': 'عمل غير مكتمل',
            'payment_dispute': 'نزاع مالي',
            'other': 'أسباب أخرى'
        };
        const statusAr = {
            'pending': 'جديد',
            'under_review': 'قيد المراجعة',
            'need_more_info': 'نقص معلومات',
            'accepted': 'مقبول',
            'rejected': 'مرفوض',
            'resolved': 'تمت التسوية'
        };
        body.innerHTML = list.map(c => `
            <tr>
                <td>C-${c.id}</td>
                <td>
                    <strong>${escapeMessageHtml(c.client_name)}</strong><br>
                    <small style="color:var(--text-muted)">${escapeMessageHtml(c.client_email)}</small>
                </td>
                <td>
                    <strong>${escapeMessageHtml(c.artisan_name)}</strong><br>
                    <small style="color:var(--text-muted)">Code: ${escapeMessageHtml(c.artisan_code)} (Score: ${c.trust_score})</small>
                </td>
                <td>${typesAr[c.complaint_type] || c.complaint_type}</td>
                <td>${c.created_at ? c.created_at.substring(0,10) : ''}</td>
                <td>
                    <span class="badge-saas badge-${c.status}">${statusAr[c.status] || c.status}</span>
                </td>
                <td>
                    <button class="action-btn" onclick="viewComplaintDetails(${c.id})">
                        <i class="fas fa-eye"></i> التفاصيل والإجراء
                    </button>
                </td>
            </tr>
        `).join('');
    }

    function filterAdminComplaints(status) {
        document.querySelectorAll('#complaints-section .chip').forEach(c => c.classList.remove('active'));
        const target = document.getElementById('complaints-filter-' + status);
        if (target) target.classList.add('active');

        if (status === 'all') {
            renderComplaintsTable(allComplaints);
        } else {
            renderComplaintsTable(allComplaints.filter(c => c.status === status));
        }
    }

    async function viewComplaintDetails(id) {
        const modal = document.getElementById('complaintDetailModal');
        const modalContainer = document.getElementById('complaint-modal-content-box');
        modal.classList.add('show');
        modalContainer.innerHTML = '<div style="padding:4rem;text-align:center;"><i class="fas fa-spinner fa-spin fa-2x"></i></div>';

        try {
            const res = await fetch('complaints.php?action=complaint_details&id=' + id);
            const data = await res.json();
            if (data.success) {
                const c = data.complaint;
                const ev = data.evidence;

                const typesAr = {
                    'late_work': 'تأخر في العمل',
                    'poor_quality': 'جودة عمل ضعيفة',
                    'damaged_property': 'إتلاف الممتلكات',
                    'fraud': 'عملية احتيال',
                    'no_response': 'عدم الرد التام',
                    'bad_behavior': 'معاملة سيئة',
                    'incomplete_work': 'عمل غير مكتمل',
                    'payment_dispute': 'نزاع مالي',
                    'other': 'أسباب أخرى'
                };

                let evidenceHtml = '';
                if (ev.length === 0) {
                    evidenceHtml = '<p style="color:var(--text-muted)">لم يتم تقديم أي مستندات إثباتية.</p>';
                } else {
                    evidenceHtml = '<div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap:12px; margin-top:8px;">';
                    ev.forEach(file => {
                        const path = '../' + file.file_path; // fixed relative location
                        const ext = file.file_path.split('.').pop().toLowerCase();
                        if (file.file_type === 'image') {
                            evidenceHtml += `<a href="${path}" target="_blank"><img src="${path}" style="width:100%; height:130px; object-fit:cover; border-radius:8px; border:1px solid var(--border);" /></a>`;
                        } else if (file.file_type === 'video') {
                            evidenceHtml += `<video src="${path}" controls style="width:100%; height:130px; border-radius:8px; border:1px solid var(--border);"></video>`;
                        } else {
                            let iconClass = 'fa-file-alt';
                            if (ext === 'pdf') iconClass = 'fa-file-pdf';
                            else if (ext === 'pptx' || ext === 'ppt') iconClass = 'fa-file-powerpoint';
                            evidenceHtml += `<a href="${path}" target="_blank" class="btn" style="text-align:center; height:130px; display:flex; flex-direction:column; justify-content:center; align-items:center; gap:8px; white-space:normal; line-height:1.2; overflow:hidden;"><i class="fas ${iconClass} fa-2x"></i><small style="word-break:break-all;">عرض المستند</small></a>`;
                        }
                    });
                    evidenceHtml += '</div>';
                }

                modalContainer.innerHTML = `
                    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap:20px;">
                        <div>
                            <h3 style="margin-bottom:12px; border-bottom:1px solid var(--border); padding-bottom:6px; color:var(--accent);">بيانات الشكوى</h3>
                            <p style="margin-bottom:8px;"><strong>رقم الشكوى:</strong> C-${c.id}</p>
                            <p style="margin-bottom:8px;"><strong>الشاكي (العميل):</strong> ${escapeMessageHtml(c.client_name)}</p>
                            <p style="margin-bottom:8px;"><strong>المشتكى عليه (الحرفي):</strong> ${escapeMessageHtml(c.artisan_name)}</p>
                            <p style="margin-bottom:8px;"><strong>نوع المخالفة:</strong> ${typesAr[c.complaint_type] || c.complaint_type}</p>
                            <p style="margin-bottom:8px;"><strong>تاريخ الحدث:</strong> ${c.incident_date || 'غير محدد'}</p>
                            <p style="margin-bottom:8px;"><strong>المبلغ المتضرر:</strong> ${c.damage_amount ? c.damage_amount + ' درهم' : 'غير محدد'}</p>
                            <p style="margin-bottom:12px;"><strong>تاريخ التقديم:</strong> ${c.created_at}</p>
                            
                            <div style="background:rgba(255,255,255,0.03); padding:12px; border-radius:12px; border:1px solid var(--border);">
                                <label style="display:block; font-weight:700; margin-bottom:6px; color:var(--text);">وصف المشكلة:</label>
                                <p style="white-space:pre-wrap; line-height:1.6; color:var(--text-muted);">${escapeMessageHtml(c.description)}</p>
                            </div>
                        </div>

                        <div>
                            <h3 style="margin-bottom:12px; border-bottom:1px solid var(--border); padding-bottom:6px; color:var(--warning);">معالجة الشكوى</h3>
                            
                            <div style="margin-bottom:16px;">
                                <label style="display:block; font-weight:700; margin-bottom:6px;">تحديث الحالة:</label>
                                <select id="comp-status-select" class="btn" style="width:100%; text-align:right;">
                                    <option value="pending" ${c.status === 'pending' ? 'selected' : ''}>قيد الانتظار</option>
                                    <option value="under_review" ${c.status === 'under_review' ? 'selected' : ''}>قيد المراجعة</option>
                                    <option value="need_more_info" ${c.status === 'need_more_info' ? 'selected' : ''}>طلب توضيحات ومعلومات إضافية</option>
                                    <option value="accepted" ${c.status === 'accepted' ? 'selected' : ''}>قبول الشكوى وإثبات المخالفة</option>
                                    <option value="rejected" ${c.status === 'rejected' ? 'selected' : ''}>رفض الشكوى لعدم كفاية الأدلة</option>
                                    <option value="resolved" ${c.status === 'resolved' ? 'selected' : ''}>تمت التسوية الودية</option>
                                </select>
                            </div>

                            <div style="margin-bottom:16px;">
                                <label style="display:block; font-weight:700; margin-bottom:6px;">ملاحظات الإدارة وقرارها:</label>
                                <textarea id="comp-admin-notes" class="btn" style="width:100%; height:90px; text-align:right; resize:none; padding:8px;" placeholder="اكتب مبررات القرار أو رسالة طلب المعلومات للعميل...">${c.admin_notes || ''}</textarea>
                                <button onclick="saveComplaintStatus(${c.id})" class="btn" style="width:100%; margin-top:8px; background:var(--accent); color:white;">حفظ وتحديث الحالة</button>
                            </div>

                            <div style="border-top:1px solid var(--border); padding-top:16px; margin-top:16px;">
                                <label style="display:block; font-weight:700; margin-bottom:10px; color:var(--danger);"><i class="fas fa-gavel"></i> الإجراءات العقابية ضد الحرفي:</label>
                                <p style="font-size:0.82rem; margin-bottom:10px; color:var(--text-muted)">ملاحظة: الإجراء يطبق مباشرة ويعدل نقاط موثوقية الحرفي المعني.</p>
                                <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
                                    <button onclick="enforcePenalty(${c.id}, ${c.artisan_id}, 'warn')" class="btn btn-reject"><i class="fas fa-exclamation-triangle"></i> توجيه إنذار (-5 نقاط)</button>
                                    <button onclick="enforcePenalty(${c.id}, ${c.artisan_id}, 'request_explanation')" class="btn"><i class="fas fa-question-circle"></i> طلب توضيح رسمي</button>
                                    <button onclick="enforcePenalty(${c.id}, ${c.artisan_id}, 'suspend_temp')" class="btn btn-reject"><i class="fas fa-user-slash"></i> إيقاف مؤقت لحسابه</button>
                                    <button onclick="enforcePenalty(${c.id}, ${c.artisan_id}, 'reduce_visibility')" class="btn"><i class="fas fa-eye-slash"></i> تخفيض الظهور (-15 نقطة)</button>
                                    <button onclick="enforcePenalty(${c.id}, ${c.artisan_id}, 'remove_badge')" class="btn"><i class="fas fa-ribbon"></i> نزع الشارات والتوثيق</button>
                                    <button onclick="enforcePenalty(${c.id}, ${c.artisan_id}, 'delete_account')" class="btn btn-reject" style="background:#5c0000;"><i class="fas fa-trash-alt"></i> حذف حسابه نهائياً</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div style="margin-top:20px; border-top:1px solid var(--border); padding-top:12px;">
                        <h4 style="margin-bottom:8px; color:var(--accent);">مستندات الإثبات والوسائط المعروضة:</h4>
                        ${evidenceHtml}
                    </div>
                `;
            }
        } catch(e) {
            modalContainer.innerHTML = '<p style="color:var(--danger); text-align:center; padding:2rem;">فشل تحميل تفاصيل الشكوى</p>';
        }
    }

    function closeComplaintModal() {
        document.getElementById('complaintDetailModal').classList.remove('show');
    }

    async function saveComplaintStatus(complaintId) {
        const status = document.getElementById('comp-status-select').value;
        const notes = document.getElementById('comp-admin-notes').value;

        try {
            const res = await fetch('complaints.php?action=admin_update_status', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ complaint_id: complaintId, status: status, admin_notes: notes })
            });
            const data = await res.json();
            if (data.success) {
                alert('تم حفظ قرار الحالة بنجاح');
                loadAdminComplaints();
                closeComplaintModal();
            } else {
                alert(data.message || 'خطأ في الحفظ');
            }
        } catch(e) {
            alert('حدث خطأ أثناء حفظ القرار');
        }
    }

    async function enforcePenalty(complaintId, artisanId, penalty) {
        if (!confirm('هل أنت متأكد من تطبيق هذا الإجراء العقابي على الحرفي؟')) return;
        try {
            const res = await fetch('complaints.php?action=admin_action', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ complaint_id: complaintId, artisan_id: artisanId, penalty: penalty })
            });
            const data = await res.json();
            if (data.success) {
                alert('تم تطبيق الإجراء العقابي بنجاح');
                loadAdminComplaints();
                closeComplaintModal();
            } else {
                alert(data.message || 'فشل تطبيق العقوبة');
            }
        } catch(e) {
            alert('خطأ في إرسال الطلب للعقوبة');
        }
    }
    </script>

    <!-- Complaint Detail Modal -->
    <div id="complaintDetailModal" class="modal">
        <div class="modal-content" style="max-width: 90%; width: 850px;">
            <div class="modal-header">
                <h2><i class="fas fa-exclamation-triangle"></i> تفاصيل الشكوى والتحقيق</h2>
                <button class="modal-close" onclick="closeComplaintModal()">&times;</button>
            </div>
            <div class="modal-body" id="complaint-modal-content-box">
                <!-- Loaded dynamically -->
            </div>
        </div>
    </div>
    
    <script src="../lang_dict.js"></script>
    <script src="../script.js"></script>
</body>
</html>
