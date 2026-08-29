<?php
session_start();
require_once 'config.php';
/** @var mysqli $conn */

// Verify client session
if (!isset($_SESSION['client_id']) || (isset($_SESSION['user_type']) && $_SESSION['user_type'] !== 'client')) {
    header('Location: ../client_login.html');
    exit;
}

$client_id = (int)$_SESSION['client_id'];

// Get client info
$stmt = $conn->prepare("SELECT * FROM clients WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $client_id);
$stmt->execute();
$client = $stmt->get_result()->fetch_assoc();

if (!$client) {
    session_destroy();
    header('Location: ../client_login.html');
    exit;
}

// Escaping helper
function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$display_name = $client['full_name'];
$display_email = $client['email'];
$display_avatar = trim($client['avatar']);
if ($display_avatar === '') {
    $display_avatar = 'https://ui-avatars.com/api/?name='.urlencode($display_name).'&background=0f766e&color=fff';
} else if (!str_starts_with($display_avatar, 'http') && !str_starts_with($display_avatar, '../')) {
    $display_avatar = '../' . ltrim($display_avatar, '/');
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة تحكم العميل - <?php echo h($display_name); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg: #0f172a;
            --panel: rgba(30, 41, 59, 0.7);
            --border: rgba(255, 255, 255, 0.08);
            --brand: #0f766e;
            --brand-light: #14b8a6;
            --accent: #ea580c;
            --text: #f1f5f9;
            --muted: #94a3b8;
            --radius-lg: 20px;
            --radius-md: 12px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Cairo', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            background-image: 
                radial-gradient(circle at 10% 20%, rgba(15, 118, 110, 0.15) 0%, transparent 40%),
                radial-gradient(circle at 90% 80%, rgba(234, 88, 12, 0.1) 0%, transparent 45%);
            background-attachment: fixed;
        }

        .layout {
            max-width: 1400px;
            margin: 0 auto;
            padding: 24px 16px;
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 24px;
        }

        /* Sidebar styling */
        .sidebar {
            background: var(--panel);
            backdrop-filter: blur(16px);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 28px 20px;
            display: flex;
            flex-direction: column;
            gap: 28px;
            height: fit-content;
            position: sticky;
            top: 24px;
        }

        .profile-card {
            text-align: center;
            padding-bottom: 24px;
            border-bottom: 1px solid var(--border);
        }

        .profile-card img {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--brand-light);
            margin-bottom: 12px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
        }

        .profile-card h3 {
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--text);
        }

        .profile-card p {
            font-size: 0.8rem;
            color: var(--muted);
            margin-top: 4px;
        }

        .nav-menu {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .nav-item {
            background: none;
            border: none;
            color: var(--muted);
            padding: 12px 16px;
            border-radius: var(--radius-md);
            font: inherit;
            font-size: 0.95rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
            text-align: right;
            width: 100%;
        }

        .nav-item i {
            font-size: 1.1rem;
        }

        .nav-item:hover {
            color: var(--text);
            background: rgba(255, 255, 255, 0.04);
        }

        .nav-item.active {
            color: #fff;
            background: var(--brand);
            box-shadow: 0 4px 15px rgba(15, 118, 110, 0.3);
        }

        .sidebar-logout {
            margin-top: auto;
            border-top: 1px solid var(--border);
            padding-top: 20px;
        }

        .logout-btn {
            background: rgba(239, 68, 68, 0.1);
            color: #fca5a5;
            border: 1px solid rgba(239, 68, 68, 0.2);
            padding: 12px;
            border-radius: var(--radius-md);
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-weight: 700;
            font-size: 0.9rem;
            transition: all 0.2s;
        }

        .logout-btn:hover {
            background: rgba(239, 68, 68, 0.2);
            transform: translateY(-2px);
        }

        /* Main Content */
        .content {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .header-bar {
            background: var(--panel);
            backdrop-filter: blur(16px);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 20px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            z-index: 50;
        }

        .header-bar h2 {
            font-size: 1.35rem;
            font-weight: 800;
        }

        .bell-btn {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border);
            width: 44px;
            height: 44px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: var(--text);
            position: relative;
            transition: all 0.2s;
        }

        .bell-btn:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .bell-btn .badge {
            position: absolute;
            top: 2px;
            right: 2px;
            background: var(--accent);
            color: white;
            font-size: 0.7rem;
            padding: 2px 6px;
            border-radius: 50%;
        }

        /* Tab Content Containers */
        .tab-content {
            display: none;
            animation: fadeIn 0.4s ease;
        }

        .tab-content.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: var(--panel);
            backdrop-filter: blur(16px);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: 20px;
            text-align: center;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .stat-card .value {
            font-size: 2rem;
            font-weight: 900;
            color: var(--brand-light);
        }

        .stat-card .label {
            font-size: 0.85rem;
            color: var(--muted);
            font-weight: 700;
        }

        /* Forms */
        .premium-card {
            background: var(--panel);
            backdrop-filter: blur(16px);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 30px;
            margin-bottom: 24px;
        }

        .premium-card h3 {
            font-size: 1.15rem;
            font-weight: 800;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .premium-card h3 i {
            color: var(--brand-light);
        }

        /* Standard forms styling */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .fg {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-bottom: 16px;
        }

        .fg.full {
            grid-column: 1 / -1;
        }

        label {
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--muted);
        }

        input, select, textarea {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border);
            padding: 12px 16px;
            border-radius: var(--radius-md);
            color: var(--text);
            font: inherit;
            font-size: 0.95rem;
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--brand-light);
            background: rgba(255, 255, 255, 0.1);
        }

        select option {
            background-color: var(--bg);
            color: var(--text);
        }

        /* Buttons */
        .btn {
            background: var(--brand);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: var(--radius-md);
            font-weight: 700;
            font-size: 0.95rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }

        .btn:hover {
            filter: brightness(1.12);
            transform: translateY(-2px);
        }

        .btn-accent {
            background: var(--accent);
        }

        .btn-muted {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border);
            color: var(--text);
        }

        .btn-muted:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        /* Multistep Steps indicator */
        .steps-bar {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            position: relative;
        }

        .steps-bar::after {
            content: '';
            position: absolute;
            background: var(--border);
            height: 4px;
            top: 15px;
            left: 0;
            right: 0;
            z-index: 1;
        }

        .step-indicator {
            position: relative;
            z-index: 2;
            background: var(--bg);
            border: 2px solid var(--border);
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 0.9rem;
            color: var(--muted);
            transition: all 0.3s;
        }

        .step-indicator.active {
            border-color: var(--brand-light);
            color: var(--brand-light);
            box-shadow: 0 0 15px var(--brand);
        }

        .step-indicator.done {
            background: var(--brand-light);
            border-color: var(--brand-light);
            color: var(--bg);
        }

        .step-section {
            display: none;
        }

        .step-section.active {
            display: block;
        }

        /* Upload area styling */
        .upload-zone {
            border: 2px dashed var(--border);
            border-radius: var(--radius-md);
            padding: 36px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
            background: rgba(255,255,255,0.02);
        }

        .upload-zone:hover {
            border-color: var(--brand-light);
        }

        .upload-zone i {
            font-size: 2.2rem;
            color: var(--muted);
            margin-bottom: 12px;
        }

        /* Proposals cards */
        .proposal-card {
            background: rgba(30, 41, 59, 0.4);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: 24px;
            margin-bottom: 16px;
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 20px;
            align-items: center;
        }

        .proposal-card img {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--border);
        }

        .proposal-card .details {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .artisan-meta {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .badge-verified {
            color: #3b82f6;
            font-size: 0.9rem;
        }

        .rating-star {
            color: #facd15;
            font-size: 0.85rem;
            margin-right: 4px;
        }

        .price-tag {
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--brand-light);
        }

        /* Request list items */
        .req-item {
            background: var(--panel);
            backdrop-filter: blur(16px);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: 20px;
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .req-item .info {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .req-item h4 {
            font-size: 1.05rem;
            font-weight: 700;
        }

        .req-item .meta-row {
            display: flex;
            gap: 16px;
            font-size: 0.82rem;
            color: var(--muted);
        }

        .status-badge {
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 700;
        }

        .status-badge.open { background: rgba(20, 184, 166, 0.15); color: #2dd4bf; }
        .status-badge.in_progress { background: rgba(59, 130, 246, 0.15); color: #60a5fa; }
        .status-badge.completed { background: rgba(16, 185, 129, 0.15); color: #34d399; }
        .status-badge.canceled { background: rgba(239, 68, 68, 0.15); color: #f87171; }

        @media(max-width: 900px) {
            .layout {
                grid-template-columns: 1fr;
            }
            .sidebar {
                position: static;
            }
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<div class="layout">
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="profile-card">
            <img src="<?php echo h($display_avatar); ?>" alt="avatar">
            <h3><?php echo h($display_name); ?></h3>
            <p><?php echo h($display_email); ?></p>
        </div>

        <nav class="nav-menu">
            <button class="nav-item" type="button" data-tab="publish">
                <i class="fa-solid fa-paper-plane"></i>
                نشر طلب جديد
            </button>
            <button class="nav-item" type="button" data-tab="requests">
                <i class="fa-solid fa-list-check"></i>
                طلباتي
            </button>
            <button class="nav-item active" type="button" data-tab="complaints">
                <i class="fa-solid fa-triangle-exclamation"></i>
                مركز الشكاوى
            </button>
            <button class="nav-item" type="button" data-tab="profile">
                <i class="fa-solid fa-user-pen"></i>
                تعديل الملف الشخصي
            </button>
        </nav>

        <div class="sidebar-logout">
            <a href="logout.php" class="logout-btn" data-i18n="client_nav_logout">
                <i class="fa-solid fa-power-off"></i>
                تسجيل الخروج
            </a>
        </div>
    </aside>

    <!-- Main Content Area -->
    <main class="content">
        <div class="header-bar">
            <h2 id="page-title">مركز الشكاوى والمخالفات</h2>
        </div>

        <div id="tab-publish" class="tab-content">
            <div class="premium-card">
                <h3><i class="fa-solid fa-paper-plane"></i> نشر طلب جديد</h3>
                <form id="request-form">
                    <div class="form-grid">
                        <div class="fg">
                            <label>عنوان الطلب *</label>
                            <input type="text" id="req-title" placeholder="مثال: ترميم مطبخ أو صباغة منزل" required>
                        </div>
                        <div class="fg">
                            <label>الفئة *</label>
                            <select id="req-category" required>
                                <option value="">اختر الفئة</option>
                            </select>
                        </div>
                        <div class="fg">
                            <label>المدينة *</label>
                            <select id="req-city" required>
                                <option value="">اختر المدينة</option>
                            </select>
                            <input type="text" id="req-city-manual" placeholder="أدخل اسم المدينة يدوياً" style="display:none; margin-top:8px;" class="input">
                        </div>
                        <div class="fg">
                            <label>الحي</label>
                            <input type="text" id="req-neighborhood" placeholder="مثال: المعاريف">
                        </div>
                        <div class="fg">
                            <label>الميزانية التقريبية (درهم)</label>
                            <input type="number" id="req-budget" placeholder="مثال: 5000">
                        </div>
                        <div class="fg">
                            <label>درجة الاستعجال *</label>
                            <select id="req-urgency" required>
                                <option value="medium">عادي</option>
                                <option value="high">مستعجل</option>
                                <option value="urgent">طارئ</option>
                                <option value="low">مرتقب</option>
                            </select>
                        </div>
                        <div class="fg full">
                            <label>التاريخ المطلوب</label>
                            <input type="date" id="req-date">
                        </div>
                        <div class="fg full">
                            <label>تفاصيل الطلب *</label>
                            <textarea id="req-desc" placeholder="صف ما تحتاجه بالتفصيل لتساعدنا في توجيه العرض إلى الحرفي المناسب..." required></textarea>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-accent">
                        <i class="fa-solid fa-paper-plane"></i> نشر الطلب
                    </button>
                </form>
            </div>
        </div>

        <div id="tab-requests" class="tab-content">
            <div class="premium-card">
                <h3><i class="fa-solid fa-list-check"></i> طلباتي المنشورة</h3>
                <div id="my-requests-list">
                    <p style="color:var(--muted); text-align:center;">جارٍ تحميل طلباتك...</p>
                </div>
            </div>
        </div>

        <div id="tab-complaints" class="tab-content active">
            <div class="premium-card">
                <h3><i class="fa-solid fa-triangle-exclamation"></i> <span data-i18n="client_comp_title">تقديم شكوى جديدة</span></h3>
                <form id="complaint-form">
                    <div class="form-grid">
                        <div class="fg">
                            <label data-i18n="client_comp_artisan">اختر الحرفي الذي تعاملت معه *</label>
                            <select id="comp-artisan" required>
                                <option value="">جاري تحميل قائمة الحرفيين...</option>
                            </select>
                        </div>
                        <div class="fg">
                            <label data-i18n="client_comp_type">نوع المخالفة *</label>
                            <select id="comp-type" required>
                                <option value="poor_quality" data-i18n="client_violation_poor">جودة عمل منخفضة أو سيئة</option>
                                <option value="late_work" data-i18n="client_violation_late">تأخير كبير وغير مبرر في تسليم العمل</option>
                                <option value="damaged_property" data-i18n="client_violation_damage">إلحاق ضرر بالممتلكات</option>
                                <option value="fraud" data-i18n="client_violation_fraud">احتيال أو نصب مالي</option>
                                <option value="no_response" data-i18n="client_violation_no_resp">عدم الرد أو قطع الاتصال فجأة</option>
                                <option value="bad_behavior" data-i18n="client_violation_behavior">سوء سلوك أو معاملة غير لائقة</option>
                                <option value="incomplete_work" data-i18n="client_violation_incomplete">توقف عن العمل دون إكماله</option>
                                <option value="other" data-i18n="client_violation_other">أخرى</option>
                            </select>
                        </div>
                        <div class="fg">
                            <label data-i18n="client_comp_date">تاريخ الواقعة *</label>
                            <input type="date" id="comp-date" required>
                        </div>
                        <div class="fg">
                            <label data-i18n="client_comp_damage">مبلغ الخسارة التقديري بالدرهم (اختياري)</label>
                            <input type="number" id="comp-damage" placeholder="إن وجد">
                        </div>
                        <div class="fg full">
                            <label data-i18n="client_comp_desc">تاريخ وتفاصيل المشكلة بالكامل *</label>
                            <textarea id="comp-desc" placeholder="توضيح كامل للخلل وتفاصيل الاتفاق مع الحرفي لمساعدتنا في معالجة الشكوى..." required></textarea>
                        </div>
                        <div class="fg full">
                            <label data-i18n="client_comp_evidence">أرفق أدلة مادية أو صور فوتوغرافية أو مستندات</label>
                            <input type="file" id="comp-evidence" multiple accept="image/*,video/*,.pdf">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-accent">
                        <i class="fa-solid fa-bullhorn"></i> <span data-i18n="client_btn_submit_comp">إرسال الشكوى رسمياً</span>
                    </button>
                </form>
            </div>

            <div class="premium-card">
                <h3><i class="fa-solid fa-clock-rotate-left"></i> <span data-i18n="client_comp_header_prev">الشكاوى السابقة</span></h3>
                <div id="complaints-list">
                    <p style="color:var(--muted); text-align:center;">لا توجد شكاوى سابقة.</p>
                </div>
            </div>
        </div>

        <div id="tab-profile" class="tab-content">
            <div class="premium-card">
                <h3><i class="fa-solid fa-user-pen"></i> تعديل الملف الشخصي</h3>
                <form id="profile-form" enctype="multipart/form-data">
                    <div class="form-grid">
                        <div class="fg full" style="align-items:center; text-align:center;">
                            <img id="prof-avatar-preview" src="<?php echo h($display_avatar); ?>" alt="avatar preview" style="width:110px;height:110px;object-fit:cover;border-radius:999px;border:1px solid var(--border);margin:0 auto 12px;">
                        </div>
                        <div class="fg">
                            <label>الاسم الكامل *</label>
                            <input type="text" id="prof-name" value="<?php echo h($display_name); ?>" required>
                        </div>
                        <div class="fg">
                            <label>البريد الإلكتروني</label>
                            <input type="email" id="prof-email" value="<?php echo h($display_email); ?>" readonly>
                        </div>
                        <div class="fg">
                            <label>تغيير رقم الهاتف *</label>
                            <input type="text" id="prof-phone" value="<?php echo h($client['phone'] ?? ''); ?>" required>
                        </div>
                        <div class="fg">
                            <label>المدينة *</label>
                            <select id="prof-city" required>
                                <option value="">اختر المدينة</option>
                            </select>
                            <input type="text" id="prof-city-manual" placeholder="أدخل اسم المدينة يدوياً" style="display:none; margin-top:8px;" class="input">
                        </div>
                        <div class="fg full">
                            <label>تحديث الصورة الشخصية</label>
                            <input type="file" id="prof-avatar" accept="image/*">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-accent">
                        <i class="fa-solid fa-save"></i> حفظ التعديلات
                    </button>
                </form>
            </div>
        </div>
    </main>
</div>

<script>
    function escapeMessageHtml(str) {
        if (!str && str !== 0) return '';
        return str.toString()
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function showGlobalToast(msg, type = 'success') {
        let toast = document.getElementById('global-toast');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'global-toast';
            toast.style.cssText = 'position:fixed;bottom:28px;left:50%;transform:translateX(-50%);padding:14px 28px;border-radius:14px;font-weight:700;font-size:0.95rem;z-index:9999;box-shadow:0 8px 30px rgba(0,0,0,0.3);transition:opacity 0.3s;color:#fff;min-width:260px;text-align:center;';
            document.body.appendChild(toast);
        }
        toast.style.background = type === 'success' ? '#059669' : '#dc2626';
        toast.textContent = msg;
        toast.style.opacity = '1';
        clearTimeout(toast._timer);
        toast._timer = setTimeout(() => {
            toast.style.opacity = '0';
        }, 3500);
    }

    async function fetchComplaintsData() {
        const select = document.getElementById('comp-artisan');
        const container = document.getElementById('complaints-list');
        container.innerHTML = '<p style="color:var(--muted);text-align:center;"><i class="fas fa-spinner fa-spin"></i> جاري التحميل...</p>';

        try {
            const res = await fetch('complaints.php?action=my_complaints');
            const data = await res.json();
            if (!data.success) {
                throw new Error(data.message || 'تعذّر تحميل البيانات');
            }

            if (select) {
                if (Array.isArray(data.worked_with) && data.worked_with.length > 0) {
                    select.innerHTML = '<option value="">اختر الحرفي</option>' + data.worked_with.map(a =>
                        `<option value="${a.id}">${escapeMessageHtml(a.full_name)}${a.profession ? ' — ' + escapeMessageHtml(a.profession) : ''}</option>`
                    ).join('');
                } else {
                    select.innerHTML = '<option value="">لا يوجد حرفيون تعاملت معهم بعد</option>';
                }
            }

            if (!Array.isArray(data.complaints) || data.complaints.length === 0) {
                container.innerHTML = '<p style="color:var(--muted);text-align:center;">لا توجد شكاوى سابقة.</p>';
                return;
            }

            const statusMap = {
                pending: 'قيد المراجعة',
                processing: 'قيد المعالجة',
                resolved: 'تم الحل',
                rejected: 'مرفوضة'
            };
            const typeMap = {
                poor_quality: 'جودة منخفضة',
                late_work: 'تأخير كبير',
                damaged_property: 'إتلاف ممتلكات',
                fraud: 'احتيال',
                no_response: 'عدم الرد',
                bad_behavior: 'سوء سلوك',
                incomplete_work: 'عدم الإكمال',
                other: 'أخرى'
            };

            container.innerHTML = data.complaints.map(c => {
                const statusLabel = statusMap[c.status] || c.status;
                const typeLabel = typeMap[c.complaint_type] || c.complaint_type;
                return `
                    <div style="padding:18px 20px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;">
                        <div style="flex:1;">
                            <div style="font-weight:700;font-size:1rem;margin-bottom:6px;">🏷️ ${typeLabel}</div>
                            <div style="color:var(--muted);font-size:0.85rem;margin-bottom:6px;">${escapeMessageHtml(c.artisan_name || '—')} • ${escapeMessageHtml(c.incident_date || '')}</div>
                            <div style="font-size:0.9rem;color:var(--text);line-height:1.6;">${escapeMessageHtml(c.description || '').substring(0, 180)}${c.description && c.description.length > 180 ? '...' : ''}</div>
                            <div style="font-size:0.8rem;color:var(--muted);margin-top:8px;">تاريخ الإرسال: ${escapeMessageHtml(c.created_at || '')}</div>
                            ${c.admin_notes ? `<div style="background:rgba(234, 88, 12, 0.08); border-left:3px solid var(--accent); padding:10px; margin-top:10px; border-radius:4px; font-size:0.85rem;"><strong>رد الإدارة:</strong> ${escapeMessageHtml(c.admin_notes)}</div>` : ''}
                        </div>
                        <span style="background:#94a3b822;color:#94a3b8;padding:5px 14px;border-radius:999px;font-size:0.82rem;font-weight:700;white-space:nowrap;">${statusLabel}</span>
                    </div>`;
            }).join('');
        } catch (err) {
            container.innerHTML = `<p style="color:#ef4444;text-align:center;">${escapeMessageHtml(err.message || 'تعذّر تحميل الشكاوى')}</p>`;
        }
    }

    async function handleComplaintSubmit(e) {
        e.preventDefault();
        const btn = e.target.querySelector('[type=submit]');
        const artisanId = document.getElementById('comp-artisan').value;
        const complaintType = document.getElementById('comp-type').value;
        const incidentDate = document.getElementById('comp-date').value;
        const damageAmount = document.getElementById('comp-damage').value;
        const description = document.getElementById('comp-desc').value.trim();

        if (!artisanId || !complaintType || !incidentDate || description.length < 10) {
            alert('يرجى تعبئة الحقول الإلزامية بشكل صحيح');
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري الإرسال...';

        try {
            const res = await fetch('complaints.php?action=create', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ artisan_id: artisanId, complaint_type: complaintType, incident_date: incidentDate, damage_amount: damageAmount, description })
            });
            const data = await res.json();
            if (!data.success) {
                throw new Error(data.message || 'فشل إرسال الشكوى');
            }

            const files = document.getElementById('comp-evidence').files;
            if (files.length > 0) {
                const fd = new FormData();
                fd.append('complaint_id', data.complaint_id);
                Array.from(files).forEach(file => fd.append('evidence[]', file));
                await fetch('complaints.php?action=upload_evidence', {
                    method: 'POST',
                    body: fd
                });
            }

            showGlobalToast('تم إرسال الشكوى بنجاح ✅', 'success');
            e.target.reset();
            await fetchComplaintsData();
        } catch (err) {
            showGlobalToast(err.message || 'تعذّر إرسال الشكوى', 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-bullhorn"></i> إرسال الشكوى رسمياً';
        }
    }

    async function handleRequestSubmit(e) {
        e.preventDefault();
        const btn = e.target.querySelector('[type=submit]');
        const citySelect = document.getElementById('req-city');
        const cityManual = document.getElementById('req-city-manual');
        const cityValue = citySelect.value === 'أخرى' ? cityManual.value.trim() : citySelect.value.trim();
        const requestData = {
            title: document.getElementById('req-title').value.trim(),
            category: document.getElementById('req-category').value,
            description: document.getElementById('req-desc').value.trim(),
            budget: document.getElementById('req-budget').value,
            urgency: document.getElementById('req-urgency').value,
            desired_date: document.getElementById('req-date').value,
            city: cityValue,
            neighborhood: document.getElementById('req-neighborhood').value.trim(),
        };

        if (!requestData.title || !requestData.category || !requestData.description || !requestData.city) {
            alert('يرجى تعبئة الحقول الإلزامية في طلب العمل');
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري النشر...';

        try {
            const res = await fetch('job_requests.php?action=create', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(requestData)
            });
            const data = await res.json();
            if (!data.success) {
                throw new Error(data.message || 'فشل نشر الطلب');
            }

            showGlobalToast('تم نشر طلبك بنجاح ✅', 'success');
            e.target.reset();
            switchTab('complaints');
        } catch (err) {
            showGlobalToast(err.message || 'تعذّر نشر الطلب', 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> نشر الطلب';
        }
    }

    async function handleProfileSubmit(e) {
        e.preventDefault();
        const btn = e.target.querySelector('[type=submit]');
        const citySelect = document.getElementById('prof-city');
        const cityManual = document.getElementById('prof-city-manual');
        const cityValue = citySelect.value === 'أخرى' ? cityManual.value.trim() : citySelect.value.trim();
        const formData = new FormData();
        formData.append('full_name', document.getElementById('prof-name').value.trim());
        formData.append('phone', document.getElementById('prof-phone').value.trim());
        formData.append('city', cityValue);
        if (!formData.get('full_name') || !formData.get('phone') || !formData.get('city')) {
            alert('يرجى تعبئة الاسم ورقم الهاتف والمدينة');
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري الحفظ...';

        try {
            const res = await fetch('client_profile.php?action=update', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            if (!data.success) {
                throw new Error(data.message || 'فشل حفظ المعلومات');
            }
            showGlobalToast('تم حفظ التعديلات بنجاح ✅', 'success');
            if (data.avatar) {
                document.getElementById('prof-avatar-preview').src = data.avatar;
            }
        } catch (err) {
            showGlobalToast(err.message || 'تعذّر حفظ التعديلات', 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-save"></i> حفظ التعديلات';
        }
    }

    function switchTab(tabId) {
        document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
        document.querySelectorAll('.nav-item').forEach(el => el.classList.toggle('active', el.dataset.tab === tabId));
        document.getElementById('tab-' + tabId).classList.add('active');

        const titles = {
            publish: 'نشر طلب عمل جديد في السوق',
            requests: 'طلباتك المنشورة',
            complaints: 'مركز الشكاوى والمخالفات',
            profile: 'تعديل الملف الشخصي والمعلومات الشخصية'
        };
        document.getElementById('page-title').textContent = titles[tabId] || titles.complaints;

        if (tabId === 'complaints') {
            fetchComplaintsData();
        }
        if (tabId === 'requests') {
            // load client's requests when opening the requests tab
            if (typeof fetchMyRequests === 'function') fetchMyRequests();
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.nav-item').forEach(btn => {
            btn.addEventListener('click', () => switchTab(btn.dataset.tab));
        });

        const complaintForm = document.getElementById('complaint-form');
        if (complaintForm) {
            complaintForm.addEventListener('submit', handleComplaintSubmit);
        }

        const requestForm = document.getElementById('request-form');
        if (requestForm) {
            requestForm.addEventListener('submit', handleRequestSubmit);
        }

        const profileForm = document.getElementById('profile-form');
        if (profileForm) {
            profileForm.addEventListener('submit', handleProfileSubmit);
        }

        fetchComplaintsData();
    });
</script>

<!-- Proposals modal and client requests management -->
<div id="clientProposalsModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;padding:28px;">
    <div style="max-width:920px;width:100%;background:linear-gradient(180deg, rgba(15,23,42,0.96), rgba(2,6,23,0.96));border:1px solid var(--border);border-radius:12px;padding:20px;position:relative;">
        <button onclick="closeClientProposals()" style="position:absolute;top:12px;left:12px;background:transparent;border:none;color:var(--muted);font-size:22px;">×</button>
        <h3 id="clientProposalsTitle" style="margin-bottom:12px;font-weight:800;">العروض على الطلب</h3>
        <div id="clientProposalsList" style="max-height:60vh;overflow:auto;padding-right:6px;">جارٍ التحميل...</div>
    </div>
</div>

<script>
    // Fetch and render client's own requests
    async function fetchMyRequests() {
        const container = document.getElementById('my-requests-list');
        container.innerHTML = '<p style="color:var(--muted);text-align:center;"><i class="fas fa-spinner fa-spin"></i> جاري التحميل...</p>';
        try {
            const res = await fetch('job_requests.php?action=my_requests');
            const data = await res.json();
            if (!data.success) {
                container.innerHTML = '<p style="color:#ef4444;text-align:center;">' + (data.message || 'خطأ') + '</p>';
                return;
            }
            const requests = data.requests || [];
            if (requests.length === 0) {
                container.innerHTML = '<p style="color:var(--muted);text-align:center;">لم تنشر أي طلبات بعد.</p>';
                return;
            }
            container.innerHTML = '';
            for (const r of requests) {
                const div = document.createElement('div');
                div.className = 'req-item';
                div.innerHTML = `
                    <div class="info">
                        <h4>${escapeMessageHtml(r.title)}</h4>
                        <div class="meta-row">
                            <span>${escapeMessageHtml(r.city || '')}</span>
                            <span>• ${escapeMessageHtml(r.category || '')}</span>
                            <span>• ${escapeMessageHtml(r.urgency || '')}</span>
                        </div>
                        <div style="margin-top:8px;color:var(--muted);">${escapeMessageHtml((r.description||'').substring(0,200))}${r.description && r.description.length>200? '...':''}</div>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:8px;align-items:flex-end;">
                        <div class="status-badge ${escapeMessageHtml(r.status||'')}">${escapeMessageHtml(r.status||'')}</div>
                        <div style="display:flex;gap:8px;margin-top:8px;">
                            <button class="btn btn-muted" onclick="openProposals(${r.id})">عرض العروض (${r.proposal_count||0})</button>
                            <button class="btn" onclick="cloneToPublish(${r.id})">نسخ/تعديل</button>
                            <button class="btn btn-muted" onclick="cancelRequest(${r.id})">إلغاء الطلب</button>
                        </div>
                    </div>
                `;
                container.appendChild(div);
            }
        } catch (err) {
            container.innerHTML = '<p style="color:#ef4444;text-align:center;">تعذّر جلب الطلبات</p>';
        }
    }

    function openProposals(requestId) {
        document.getElementById('clientProposalsModal').style.display = 'flex';
        document.getElementById('clientProposalsList').innerHTML = '<p style="color:var(--muted);text-align:center;"><i class="fas fa-spinner fa-spin"></i> جاري التحميل...</p>';
        fetchProposals(requestId);
    }

    function closeClientProposals() { document.getElementById('clientProposalsModal').style.display = 'none'; }

    async function fetchProposals(requestId) {
        try {
            const res = await fetch('proposals.php?action=list&request_id=' + encodeURIComponent(requestId));
            const data = await res.json();
            const list = document.getElementById('clientProposalsList');
            if (!data.success) { list.innerHTML = '<p style="color:#ef4444;text-align:center;">' + (data.message||'خطأ') + '</p>'; return; }
            if (!Array.isArray(data.proposals) || data.proposals.length===0) { list.innerHTML = '<p style="color:var(--muted);text-align:center;">لا توجد عروض حتى الآن.</p>'; return; }
            list.innerHTML = '';
            for (const p of data.proposals) {
                const card = document.createElement('div');
                card.className = 'proposal-card';
                card.innerHTML = `
                    <div style="display:flex;align-items:center;gap:12px;"><img src="${escapeMessageHtml(p.artisan_avatar||'../img/man.webp')}" alt="avatar"></div>
                    <div class="details">
                        <div style="font-weight:800;">${escapeMessageHtml(p.artisan_name||'')}</div>
                        <div class="artisan-meta"><span class="rating-star">★ ${escapeMessageHtml(p.artisan_rating||'')}</span><span>${escapeMessageHtml(p.artisan_profession||'')}</span><span>${escapeMessageHtml(p.artisan_city||'')}</span></div>
                        <div style="margin-top:8px;color:var(--muted);">${escapeMessageHtml(p.message||'')}</div>
                    </div>
                    <div style="text-align:right;">
                        <div class="price-tag">${escapeMessageHtml(p.proposed_price||'')} د.م</div>
                        <div style="margin-top:8px;display:flex;flex-direction:column;gap:8px;align-items:flex-end;">
                            ${p.status==='accepted' ? 
                                '<span class="status-badge in_progress">مقبول</span>' +
                                (p.conversation_id ? '<button class="btn" style="background:#3b82f6;margin-top:8px;" onclick="window.location.href=\'../chat.php?conv=' + p.conversation_id + '\'"><i class="fa-solid fa-comments"></i> المحادثة</button>' : '')
                             : p.status==='rejected' ? 
                                '<span class="status-badge canceled">مرفوض</span>'
                             : 
                                `<button class="btn" style="background:#3b82f6;" onclick="contactArtisan(${p.id})"><i class="fa-solid fa-message"></i> مراسلة</button>
                                 <button class="btn btn-accent" onclick="acceptProposal(${p.id},${requestId})"><i class="fa-solid fa-check"></i> قبول العرض</button>
                                 <button class="btn btn-muted" onclick="rejectProposal(${p.id},${requestId})"><i class="fa-solid fa-xmark"></i> رفض</button>`
                            }
                            <button class="btn btn-muted" onclick="toggleFavorite(${p.id}, this)">${p.status==='favorite'?'إزالة من المفضلة':'إضافة للمفضلة'}</button>
                        </div>
                    </div>
                `;
                list.appendChild(card);
            }
        } catch (err) {
            document.getElementById('clientProposalsList').innerHTML = '<p style="color:#ef4444;text-align:center;">تعذّر تحميل العروض</p>';
        }
    }

    async function acceptProposal(proposalId, requestId) {
        if (!confirm('هل تريد قبول هذا العرض بصورة نهائية؟ سيتم إنشاء محادثة مع الحرفي وتحديث حالة الطلب.')) return;
        try {
            const res = await fetch('proposals.php?action=accept', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({proposal_id: proposalId}) });
            const data = await res.json();
            if (!data.success) return alert(data.message || 'فشل قبول العرض');
            showGlobalToast(data.message || 'تم قبول العرض', 'success');
            closeClientProposals();
            fetchMyRequests();
            if (data.chat_url) window.location.href = data.chat_url;
        } catch (err) { alert('خطأ في الاتصال'); }
    }

    async function contactArtisan(proposalId) {
        try {
            const res = await fetch('proposals.php?action=client_contact', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({proposal_id: proposalId}) });
            const data = await res.json();
            if (!data.success) return alert(data.message || 'تعذّر إنشاء المحادثة');
            if (data.chat_url) window.location.href = data.chat_url;
        } catch (err) { alert('خطأ في الاتصال'); }
    }

    async function rejectProposal(proposalId, requestId) {
        if (!confirm('هل تريد رفض هذا العرض؟')) return;
        try {
            const res = await fetch('proposals.php?action=reject', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({proposal_id: proposalId}) });
            const data = await res.json();
            if (!data.success) return alert(data.message || 'فشل الرفض');
            showGlobalToast(data.message || 'تم رفض العرض', 'success');
            fetchProposals(requestId);
        } catch (err) { alert('خطأ في الاتصال'); }
    }

    async function toggleFavorite(proposalId, btnEl) {
        try {
            const res = await fetch('proposals.php?action=favorite', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({proposal_id: proposalId}) });
            const data = await res.json();
            if (!data.success) return alert(data.message || 'خطأ');
            btnEl.textContent = data.is_favorite ? 'إزالة من المفضلة' : 'إضافة للمفضلة';
        } catch (err) { alert('خطأ في الاتصال'); }
    }

    async function cloneToPublish(requestId) {
        try {
            const res = await fetch('job_requests.php?action=detail&id=' + encodeURIComponent(requestId));
            const data = await res.json();
            if (!data.success) return alert(data.message || 'تعذّر تحميل الطلب');
            const r = data.request;
            // populate publish form
            document.getElementById('req-title').value = r.title||'';
            document.getElementById('req-desc').value = r.description||'';
            document.getElementById('req-budget').value = r.budget||'';
            document.getElementById('req-urgency').value = r.urgency||'medium';
            document.getElementById('req-date').value = r.desired_date||'';
            // set category and city if exists
            if (document.getElementById('req-category')) { document.getElementById('req-category').value = r.category||''; }
            if (document.getElementById('req-city')) { document.getElementById('req-city').value = r.city||''; }
            if (document.getElementById('req-neighborhood')) { document.getElementById('req-neighborhood').value = r.neighborhood||''; }
            // switch to publish tab for editing
            switchTab('publish');
            showGlobalToast('تم نسخ الطلب إلى نموذج النشر يمكنك تعديله ثم إرسال نسخة جديدة', 'success');
        } catch (err) { alert('تعذّر التحميل'); }
    }

    async function cancelRequest(requestId) {
        if (!confirm('هل تريد إلغاء هذا الطلب؟')) return;
        try {
            const res = await fetch('job_requests.php?action=update_status', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({request_id: requestId, status: 'canceled'}) });
            const data = await res.json();
            if (!data.success) return alert(data.message || 'فشل الإلغاء');
            showGlobalToast(data.message || 'تم إلغاء الطلب', 'success');
            fetchMyRequests();
        } catch (err) { alert('خطأ في الاتصال'); }
    }

    </script>

<script src="../lang_dict.js"></script>
<script src="../script.js"></script>
<script>
    window.addEventListener('load', () => {
        if (window.populateCitiesSelect) {
            populateCitiesSelect('req-city', 'req-city-manual');
            populateCitiesSelect('prof-city', 'prof-city-manual', <?php echo json_encode($client['city'] ?? ''); ?>);
        }
        if (window.populateCraftsSelect) {
            populateCraftsSelect('req-category');
        }
    });
</script>
<script>
    window.addEventListener('DOMContentLoaded', () => {
        const currentLang = localStorage.getItem('hirafi_lang') || 'ar';
        if (window.applyLanguage) {
            applyLanguage(currentLang);
        }
    });
</script>
</body>
</html>
