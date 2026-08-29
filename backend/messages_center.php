<?php
session_start();
require_once 'config.php';

$is_admin = isset($_SESSION['admin_id']) && (string)($_SESSION['user_type'] ?? '') === 'admin';
$is_craftsman = isset($_SESSION['craftsman_id']) && (string)($_SESSION['user_type'] ?? '') === 'craftsman';

if (!$is_admin && !$is_craftsman) {
    header('Location: ../hirafi_login.html');
    exit;
}

$viewer_name = $is_admin
    ? (trim((string)($_SESSION['admin_name'] ?? '')) !== '' ? $_SESSION['admin_name'] : 'الإدارة')
    : (trim((string)($_SESSION['craftsman_name'] ?? '')) !== '' ? $_SESSION['craftsman_name'] : 'حرفي');
$viewer_label = $is_admin ? 'لوحة رسائل الإدارة' : 'مركز الرسائل';
$back_url = $is_admin ? 'admin_dashboard.php' : 'artisan_dashboard.php';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مركز الرسائل — حرفي</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ===== DESIGN SYSTEM ===== */
        :root {
            --bg: #F0F2F5;
            --panel: #FFFFFF;
            --line: #E5E7EB;
            --ink: #111827;
            --muted: #6B7280;
            --brand: #1877F2;
            --brand-hover: #0E63D8;
            --brand-light: #E7F3FF;
            --sent-bg: linear-gradient(135deg, #1877F2, #42A5F5);
            --recv-bg: #F0F0F0;
            --sidebar-w: 360px;
            --detail-w: 320px;
            --header-h: 64px;
            --radius: 20px;
            --radius-sm: 12px;
            --radius-xs: 8px;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --shadow-lg: 0 8px 30px rgba(0,0,0,0.12);
            --transition: 0.25s cubic-bezier(0.4,0,0.2,1);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Cairo', sans-serif;
            background: var(--bg);
            color: var(--ink);
            height: 100vh;
            overflow: hidden;
        }

        /* ===== MAIN LAYOUT ===== */
        .shell {
            height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Top Bar */
        .head {
            background: var(--panel);
            border-bottom: 1px solid var(--line);
            padding: 0 24px;
            height: 56px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
            z-index: 50;
        }

        .head-right {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .head-right .head-icon {
            width: 36px; height: 36px;
            background: var(--brand);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 1rem;
        }

        .head h1 {
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--ink);
        }

        .head p {
            display: none;
        }

        .btn-link {
            text-decoration: none;
            background: transparent;
            color: var(--brand);
            border: 1.5px solid var(--brand);
            border-radius: 20px;
            padding: 7px 18px;
            font-size: 0.85rem;
            font-weight: 600;
            font-family: inherit;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all var(--transition);
        }

        .btn-link:hover {
            background: var(--brand);
            color: #fff;
        }

        /* 3-Column Layout */
        .layout {
            flex: 1;
            display: grid;
            grid-template-columns: var(--sidebar-w) 1fr var(--detail-w);
            overflow: hidden;
            background: var(--bg);
        }

        /* ===== LEFT SIDEBAR ===== */
        .sidebar {
            background: var(--panel);
            border-left: 1px solid var(--line);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .side-head {
            padding: 14px 16px;
            display: flex;
            flex-direction: column;
            gap: 12px;
            flex-shrink: 0;
        }

        .side-head .side-title {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .side-head .side-title h2 {
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--ink);
        }

        .side-head .side-title button {
            width: 34px; height: 34px;
            border-radius: 50%;
            border: none;
            background: var(--bg);
            color: var(--muted);
            cursor: pointer;
            font-size: 0.95rem;
            transition: all var(--transition);
        }

        .side-head .side-title button:hover {
            background: var(--brand-light);
            color: var(--brand);
        }

        .search-wrap {
            position: relative;
        }

        .search-wrap i {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #9CA3AF;
            font-size: 0.9rem;
            pointer-events: none;
        }

        .side-head input {
            width: 100%;
            border: none;
            border-radius: 20px;
            padding: 10px 40px 10px 16px;
            font-family: inherit;
            font-size: 0.9rem;
            background: var(--bg);
            color: var(--ink);
            outline: none;
            transition: all var(--transition);
        }

        .side-head input:focus {
            background: #E8E8E8;
        }

        .side-head input::placeholder { color: #9CA3AF; }

        /* Filter Tabs */
        .filter-tabs {
            display: flex;
            gap: 6px;
            padding: 0 0 4px 0;
            flex-wrap: nowrap;
            overflow-x: auto;
        }

        .filter-tabs button {
            border: none;
            background: var(--bg);
            color: var(--muted);
            font-family: inherit;
            font-size: 0.8rem;
            font-weight: 600;
            padding: 6px 14px;
            border-radius: 18px;
            cursor: pointer;
            white-space: nowrap;
            transition: all var(--transition);
        }

        .filter-tabs button.active,
        .filter-tabs button:hover {
            background: var(--brand-light);
            color: var(--brand);
        }

        /* Contacts List */
        .contacts {
            overflow-y: auto;
            flex: 1;
        }

        .contacts::-webkit-scrollbar { width: 4px; }
        .contacts::-webkit-scrollbar-track { background: transparent; }
        .contacts::-webkit-scrollbar-thumb { background: #D1D5DB; border-radius: 10px; }

        .contact {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            cursor: pointer;
            transition: all var(--transition);
            position: relative;
            border-radius: 0;
        }

        .contact:hover {
            background: #F5F5F5;
        }

        .contact.active {
            background: var(--brand-light);
        }

        .contact-avatar {
            position: relative;
            flex-shrink: 0;
        }

        .contact-avatar .avatar-circle {
            width: 50px; height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #1877F2, #42A5F5);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 700;
            font-size: 1.1rem;
        }

        .contact-avatar .online-dot {
            position: absolute;
            bottom: 2px; left: 2px;
            width: 12px; height: 12px;
            background: #22C55E;
            border: 2.5px solid var(--panel);
            border-radius: 50%;
        }

        .contact-info {
            flex: 1;
            min-width: 0;
        }

        .contact-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2px;
        }

        .contact-name {
            font-weight: 700;
            font-size: 0.95rem;
            color: var(--ink);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .contact-time {
            font-size: 0.72rem;
            color: #9CA3AF;
            white-space: nowrap;
            flex-shrink: 0;
            margin-right: 8px;
        }

        .contact-meta {
            color: var(--muted);
            font-size: 0.78rem;
            margin-bottom: 2px;
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: nowrap;
        }

        .contact-last {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
        }

        .contact-last-text {
            color: #6B7280;
            font-size: 0.83rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            flex: 1;
        }

        .unread {
            background: #EF4444;
            color: #fff;
            border-radius: 50%;
            font-size: 0.7rem;
            font-weight: 700;
            min-width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        /* ===== CENTER CHAT ===== */
        .chat {
            display: flex;
            flex-direction: column;
            background: var(--panel);
            border-left: 1px solid var(--line);
            border-right: 1px solid var(--line);
            overflow: hidden;
        }

        .chat-head {
            padding: 0 20px;
            height: var(--header-h);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: var(--panel);
            border-bottom: 1px solid var(--line);
            flex-shrink: 0;
        }

        .chat-head-main {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .chat-head-avatar {
            width: 40px; height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #1877F2, #42A5F5);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 700;
            font-size: 0.95rem;
            flex-shrink: 0;
        }

        .chat-head strong {
            font-size: 1rem;
            font-weight: 700;
            display: block;
        }

        .chat-head small {
            color: var(--muted);
            font-size: 0.8rem;
        }

        .chat-head-actions {
            display: flex;
            gap: 4px;
        }

        .chat-head-actions button {
            width: 36px; height: 36px;
            border-radius: 50%;
            border: none;
            background: transparent;
            color: var(--brand);
            cursor: pointer;
            font-size: 1.1rem;
            transition: all var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .chat-head-actions button:hover {
            background: var(--brand-light);
        }

        /* Chat Body */
        .chat-body {
            flex: 1;
            overflow-y: auto;
            padding: 20px 24px;
            display: flex;
            flex-direction: column;
            gap: 6px;
            background: var(--bg);
        }

        .chat-body::-webkit-scrollbar { width: 5px; }
        .chat-body::-webkit-scrollbar-track { background: transparent; }
        .chat-body::-webkit-scrollbar-thumb { background: #CBD5E1; border-radius: 10px; }

        /* Bubbles */
        .bubble {
            max-width: min(72%, 520px);
            border-radius: 18px;
            padding: 10px 14px;
            line-height: 1.55;
            font-size: 0.92rem;
            display: grid;
            gap: 6px;
            word-break: break-word;
            position: relative;
            animation: bubbleFade 0.3s ease;
        }

        @keyframes bubbleFade {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .bubble.sent {
            align-self: flex-end;
            background: var(--sent-bg);
            color: #fff;
            border-bottom-left-radius: 4px;
            box-shadow: 0 2px 8px rgba(24, 119, 242, 0.2);
        }

        .bubble.recv {
            align-self: flex-start;
            background: var(--recv-bg);
            color: var(--ink);
            border-bottom-right-radius: 4px;
        }

        .bubble-text {
            white-space: pre-wrap;
        }

        .bubble small {
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 0.68rem;
            opacity: 0.7;
        }

        .bubble.sent small {
            justify-content: flex-start;
        }

        .bubble-attachment {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            border-radius: var(--radius-sm);
            overflow: hidden;
            max-width: 100%;
        }

        .bubble-image {
            padding: 3px;
        }

        .bubble-image img {
            display: block;
            width: min(300px, 100%);
            max-height: 280px;
            object-fit: cover;
            border-radius: var(--radius-sm);
            cursor: pointer;
            transition: transform var(--transition);
        }

        .bubble-image img:hover {
            transform: scale(1.02);
        }

        .bubble-file {
            padding: 12px 14px;
            background: rgba(0,0,0,0.06);
            color: inherit;
            border-radius: var(--radius-xs);
            display: flex;
            align-items: center;
            gap: 12px;
            transition: background var(--transition);
        }

        .bubble-file:hover {
            background: rgba(0,0,0,0.1);
        }

        .bubble.sent .bubble-file {
            background: rgba(255,255,255,0.15);
        }

        .bubble.sent .bubble-file:hover {
            background: rgba(255,255,255,0.25);
        }

        .bubble-file i {
            font-size: 1.4rem;
        }

        .bubble-file span {
            font-size: 0.88rem;
            font-weight: 600;
        }

        /* ===== INPUT AREA ===== */
        .chat-input {
            border-top: 1px solid var(--line);
            padding: 12px 16px;
            display: flex;
            gap: 10px;
            align-items: flex-end;
            background: var(--panel);
            flex-shrink: 0;
        }

        .input-tools {
            display: flex;
            gap: 4px;
            align-items: center;
        }

        .attach-btn {
            width: 38px; height: 38px;
            border-radius: 50%;
            border: none;
            background: transparent;
            color: var(--brand);
            cursor: pointer;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all var(--transition);
        }

        .attach-btn:hover {
            background: var(--brand-light);
        }

        .selected-file {
            display: none;
            max-width: 150px;
            padding: 5px 12px;
            border-radius: 16px;
            background: var(--brand-light);
            color: var(--brand);
            font-size: 0.78rem;
            font-weight: 600;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .selected-file.show {
            display: flex;
            align-items: center;
        }

        .chat-input textarea {
            flex: 1;
            border: none;
            border-radius: 20px;
            padding: 10px 18px;
            min-height: 40px;
            max-height: 140px;
            resize: none;
            font-family: inherit;
            font-size: 0.92rem;
            background: var(--bg);
            color: var(--ink);
            outline: none;
            line-height: 1.5;
        }

        .chat-input textarea::placeholder { color: #9CA3AF; }

        .chat-input button#sendBtn {
            width: 40px; height: 40px;
            border-radius: 50%;
            border: none;
            background: var(--brand);
            color: #fff;
            cursor: pointer;
            font-size: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all var(--transition);
            flex-shrink: 0;
        }

        .chat-input button#sendBtn:hover {
            background: var(--brand-hover);
            transform: scale(1.08);
        }

        .chat-input button#sendBtn:disabled {
            background: #D1D5DB;
            cursor: not-allowed;
            transform: none;
        }

        /* ===== RIGHT DETAIL PANEL ===== */
        .detail-panel {
            background: var(--panel);
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            border-right: 1px solid var(--line);
        }

        .detail-panel::-webkit-scrollbar { width: 4px; }
        .detail-panel::-webkit-scrollbar-thumb { background: #D1D5DB; border-radius: 10px; }

        .detail-header {
            text-align: center;
            padding: 30px 20px 20px;
            border-bottom: 1px solid var(--line);
        }

        .detail-avatar {
            width: 80px; height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #1877F2, #42A5F5);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 800;
            font-size: 1.8rem;
            margin: 0 auto 12px;
        }

        .detail-header h3 {
            font-size: 1.15rem;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .detail-header .detail-meta {
            color: var(--muted);
            font-size: 0.85rem;
        }

        .detail-header .detail-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: 10px;
            padding: 4px 14px;
            border-radius: 20px;
            background: #DCFCE7;
            color: #16A34A;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .detail-header .detail-status .dot {
            width: 8px; height: 8px;
            border-radius: 50%;
            background: #22C55E;
        }

        .detail-section {
            padding: 16px 20px;
            border-bottom: 1px solid var(--line);
        }

        .detail-section h4 {
            font-size: 0.82rem;
            font-weight: 700;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 12px;
        }

        .detail-info-row {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 0;
        }

        .detail-info-row i {
            width: 32px; height: 32px;
            border-radius: 50%;
            background: var(--bg);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--brand);
            font-size: 0.85rem;
            flex-shrink: 0;
        }

        .detail-info-row .info-label {
            font-size: 0.8rem;
            color: var(--muted);
        }

        .detail-info-row .info-value {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--ink);
        }

        .detail-actions {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .detail-actions button {
            width: 100%;
            border: none;
            background: transparent;
            color: var(--ink);
            font-family: inherit;
            font-size: 0.88rem;
            font-weight: 500;
            padding: 10px 14px;
            border-radius: var(--radius-xs);
            cursor: pointer;
            text-align: right;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: background var(--transition);
        }

        .detail-actions button:hover {
            background: var(--bg);
        }

        .detail-actions button.danger {
            color: #EF4444;
        }

        .detail-actions button i {
            width: 20px;
            text-align: center;
        }

        /* ===== EMPTY STATES ===== */
        .empty {
            margin: auto;
            text-align: center;
            padding: 40px 20px;
        }

        .empty-illustration {
            width: 160px; height: 160px;
            border-radius: 50%;
            background: linear-gradient(135deg, rgba(24,119,242,0.08), rgba(66,165,245,0.12));
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }

        .empty-illustration i {
            font-size: 4rem;
            color: var(--brand);
            opacity: 0.5;
        }

        .empty h3 {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 8px;
        }

        .empty p {
            color: var(--muted);
            font-size: 0.9rem;
            max-width: 300px;
            margin: 0 auto;
            line-height: 1.6;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 1200px) {
            .layout { grid-template-columns: var(--sidebar-w) 1fr; }
            .detail-panel { display: none; }
        }

        @media (max-width: 800px) {
            .layout { grid-template-columns: 1fr; }
            .sidebar {
                border-left: 0;
                border-bottom: 1px solid var(--line);
                max-height: 280px;
            }
            .chat { min-height: 50vh; }
            .chat-input { flex-wrap: wrap; }
            .bubble { max-width: 90%; }
            .detail-panel { display: none; }
        }

        .layout.fullscreen {
            position: fixed;
            inset: 0;
            z-index: 1000;
            height: 100vh;
        }

        .qk-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 20px;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 700;
            transition: all var(--transition);
        }
        .qk-btn:hover {
            transform: scale(1.05);
        }
    </style>
</head>
<body>
    <div class="shell">

        <!-- ===== TOP BAR ===== -->
        <div class="head">
            <div class="head-right">
                <div class="head-icon"><i class="fas fa-comments"></i></div>
                <h1><?php echo htmlspecialchars($viewer_label, ENT_QUOTES, 'UTF-8'); ?></h1>
                <p>مرحباً <?php echo htmlspecialchars($viewer_name, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
            <a class="btn-link" href="<?php echo htmlspecialchars($back_url, ENT_QUOTES, 'UTF-8'); ?>"><i class="fas fa-arrow-right"></i> رجوع للوحة</a>
        </div>

        <!-- ===== 3-COLUMN LAYOUT ===== -->
        <div class="layout" id="mainLayout">

            <!-- LEFT: Sidebar -->
            <aside class="sidebar">
                <div class="side-head">
                    <div class="side-title">
                        <h2>المحادثات</h2>
                        <button title="توسيع الشاشة" id="fullscreenBtn" onclick="toggleFullscreen()"><i class="fas fa-expand"></i></button>
                    </div>
                    <div class="search-wrap">
                        <i class="fas fa-search"></i>
                        <input type="text" id="searchInput" placeholder="<?php echo $is_admin ? 'بحث عن حرفي...' : 'بحث...'; ?>">
                    </div>
                    <div class="filter-tabs">
                        <button class="active">الكل</button>
                        <button>غير مقروء</button>
                        <button>اليوم</button>
                        <button>هذا الأسبوع</button>
                    </div>
                </div>
                <div class="contacts" id="contactsList">
                    <div class="empty">
                        <div class="empty-illustration"><i class="fas fa-spinner fa-spin"></i></div>
                        <p>جاري تحميل جهات الاتصال...</p>
                    </div>
                </div>
            </aside>

            <!-- CENTER: Chat -->
            <section class="chat">
                <div class="chat-head" id="chatHeader">
                    <div class="chat-head-main">
                        <strong>اختر جهة اتصال لبدء المحادثة</strong>
                        <small>يمكنك إرسال رسالة نصية أو إضافة صورة وPDF.</small>
                    </div>
                </div>
                <div class="chat-body" id="chatBody">
                    <div class="empty">
                        <div class="empty-illustration"><i class="far fa-comments"></i></div>
                        <h3>اختر محادثة لبدء التواصل</h3>
                        <p>يمكنك إدارة جميع الرسائل والملفات بسهولة من هنا.</p>
                    </div>
                </div>
                <div class="chat-input">
                    <div class="input-tools">
                        <label class="attach-btn" for="attachmentInput" title="إرفاق ملف"><i class="fas fa-paperclip"></i></label>
                        <span class="selected-file" id="selectedFile"></span>
                        <input type="file" id="attachmentInput" accept="image/*,application/pdf" hidden>
                    </div>
                    <textarea id="msgInput" placeholder="اكتب رسالة..." disabled></textarea>
                    <button id="sendBtn" disabled><i class="fas fa-paper-plane"></i></button>
                </div>
            </section>

            <!-- RIGHT: Detail Panel -->
            <aside class="detail-panel" id="detailPanel">
                <div class="detail-header" id="detailHeader">
                    <div class="detail-avatar" id="detailAvatar"><i class="fas fa-user"></i></div>
                    <h3 id="detailName">لم يتم اختيار محادثة</h3>
                    <div class="detail-meta" id="detailMeta">—</div>
                    <div class="detail-status" id="detailStatus">
                        <span class="dot"></span> متصل
                    </div>
                    
                    <div class="detail-quick-actions" style="margin-top: 15px; display: flex; gap: 10px; justify-content: center; display: none;" id="detailQuickActions">
                        <a href="#" id="callBtn" class="qk-btn" style="background:#E7F3FF; color:#1877F2"><i class="fas fa-phone"></i> اتصال</a>
                        <a href="#" id="waBtn" class="qk-btn" style="background:#DCFCE7; color:#16A34A" target="_blank"><i class="fab fa-whatsapp"></i> واتساب</a>
                    </div>
                </div>
                <div class="detail-section">
                    <h4>معلومات الاتصال</h4>
                    <div class="detail-info-row">
                        <i class="fas fa-hammer"></i>
                        <div>
                            <div class="info-label">المهنة</div>
                            <div class="info-value" id="detailProfession">—</div>
                        </div>
                    </div>
                    <div class="detail-info-row">
                        <i class="fas fa-map-marker-alt"></i>
                        <div>
                            <div class="info-label">المدينة</div>
                            <div class="info-value" id="detailCity">—</div>
                        </div>
                    </div>
                    <div class="detail-info-row">
                        <i class="fas fa-phone"></i>
                        <div>
                            <div class="info-label">الهاتف</div>
                            <div class="info-value" id="detailPhone">—</div>
                        </div>
                    </div>
                    <div class="detail-info-row">
                        <i class="fas fa-star"></i>
                        <div>
                            <div class="info-label">التقييم</div>
                            <div class="info-value" id="detailRating">—</div>
                        </div>
                    </div>
                    <div class="detail-info-row">
                        <i class="fas fa-calendar"></i>
                        <div>
                            <div class="info-label">عضو منذ</div>
                            <div class="info-value" id="detailSince">—</div>
                        </div>
                    </div>
                </div>
                <div class="detail-section">
                    <h4>إجراءات سريعة</h4>
                    <div class="detail-actions">
                        <button><i class="fas fa-user"></i> عرض الملف الشخصي</button>
                        <button><i class="fas fa-file-export"></i> تصدير المحادثة</button>
                        <button><i class="fas fa-thumbtack"></i> تثبيت المحادثة</button>
                        <button class="danger" onclick="deleteConversation()"><i class="fas fa-trash"></i> حذف المحادثة</button>
                    </div>
                </div>
            </aside>

        </div>
    </div>

    <script>
        const contactsList = document.getElementById('contactsList');
        const searchInput = document.getElementById('searchInput');
        const chatHeader = document.getElementById('chatHeader');
        const chatBody = document.getElementById('chatBody');
        const msgInput = document.getElementById('msgInput');
        const sendBtn = document.getElementById('sendBtn');
        const attachmentInput = document.getElementById('attachmentInput');
        const selectedFile = document.getElementById('selectedFile');

        let contacts = [];
        let currentContact = null;
        let refreshTimer = null;

        function esc(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function formatTime(value) {
            if (!value) return '';
            const safeValue = String(value).replace(' ', 'T');
            const date = new Date(safeValue);
            if (Number.isNaN(date.getTime())) return '';
            return date.toLocaleString('ar', {
                hour: '2-digit',
                minute: '2-digit',
                day: '2-digit',
                month: '2-digit'
            });
        }

        function getInitials(name) {
            if (!name) return '?';
            const parts = String(name).trim().split(/\s+/);
            return parts.length >= 2
                ? (parts[0][0] + parts[1][0]).toUpperCase()
                : parts[0].substring(0, 2).toUpperCase();
        }

        function renderAttachment(attachment) {
            if (!attachment || !attachment.url) return '';

            const url = esc(attachment.url);
            const name = esc(attachment.name || 'ملف مرفق');
            if (attachment.type === 'image') {
                return `
                    <a class="bubble-attachment bubble-image" href="${url}" target="_blank" rel="noopener">
                        <img src="${url}" alt="${name}">
                    </a>
                `;
            }

            const icon = attachment.type === 'pdf' ? 'fa-file-pdf' : 'fa-file';
            return `
                <a class="bubble-attachment bubble-file" href="${url}" target="_blank" rel="noopener">
                    <i class="fas ${icon}"></i>
                    <span>${name}</span>
                </a>
            `;
        }

        function renderBubble(item) {
            const text = String(item.message_text || '').trim();
            return `
                <div class="bubble ${item.sender_type === currentContact.contact_type ? 'recv' : 'sent'}">
                    ${text ? `<div class="bubble-text">${esc(text)}</div>` : ''}
                    ${renderAttachment(item.attachment)}
                    <small>${formatTime(item.created_at)} <i class="fas fa-check-double" style="font-size:0.6rem;"></i></small>
                </div>
            `;
        }

        function updateSelectedFile() {
            const file = attachmentInput.files && attachmentInput.files[0];
            if (file) {
                selectedFile.textContent = file.name;
                selectedFile.classList.add('show');
            } else {
                selectedFile.textContent = '';
                selectedFile.classList.remove('show');
            }
        }

        function clearSelectedFile() {
            attachmentInput.value = '';
            updateSelectedFile();
        }

        function updateDetailPanel(c) {
            if (!c) return;
            document.getElementById('detailAvatar').textContent = getInitials(c.contact_name);
            document.getElementById('detailName').textContent = c.contact_name || 'مستخدم';
            document.getElementById('detailMeta').textContent = c.contact_meta || '—';
            document.getElementById('detailProfession').textContent = c.profession || c.contact_meta || '—';
            document.getElementById('detailCity').textContent = c.city || '—';
            document.getElementById('detailPhone').textContent = c.phone || '—';
            document.getElementById('detailRating').textContent = c.rating || '—';
            document.getElementById('detailSince').textContent = c.profile_created_at ? formatTime(c.profile_created_at) : '—';
            
            const qkInfo = document.getElementById('detailQuickActions');
            if (c.phone && c.phone.trim() !== '') {
                qkInfo.style.display = 'flex';
                document.getElementById('callBtn').href = 'tel:' + c.phone;
                let waPhone = c.phone.replace(/^0/, '+212').replace(/\s/g, ''); 
                document.getElementById('waBtn').href = 'https://wa.me/' + waPhone;
            } else {
                qkInfo.style.display = 'none';
            }
        }

        function toggleFullscreen() {
            const layout = document.getElementById('mainLayout');
            const head = document.querySelector('.head');
            if (layout.classList.contains('fullscreen')) {
                layout.classList.remove('fullscreen');
                if (head) head.style.display = 'flex';
            } else {
                layout.classList.add('fullscreen');
                if (head) head.style.display = 'none';
            }
        }

        async function deleteConversation() {
            if (!currentContact) return;
            if (!confirm('هل أنت متأكد من حذف هذه المحادثة بالكامل؟ لا يمكن استرجاعها لاحقاً.')) return;
            
            try {
                const res = await fetch(`messages.php?action=delete_conversation&contact_id=${currentContact.contact_id}&contact_type=${currentContact.contact_type}`, { method: 'POST' });
                const data = await res.json();
                
                if (data.success) {
                    alert('تم حذف المحادثة بنجاح.');
                    currentContact = null;
                    document.getElementById('detailQuickActions').style.display = 'none';
                    document.getElementById('detailName').textContent = 'لم يتم اختيار محادثة';
                    msgInput.disabled = true;
                    sendBtn.disabled = true;
                    chatHeader.innerHTML = '<div class="chat-head-main"><strong>اختر جهة اتصال لبدء المحادثة</strong></div>';
                    chatBody.innerHTML = '<div class="empty"><div class="empty-illustration"><i class="far fa-comments"></i></div><h3>تم حذف المحادثة</h3></div>';
                    await loadContacts();
                } else {
                    alert(data.message || 'حدث خطأ أثناء محاولة الحذف.');
                }
            } catch (error) {
                alert('فشل الاتصال بالخادم.');
            }
        }

        async function loadContacts() {
            try {
                const q = encodeURIComponent(searchInput.value.trim());
                const res = await fetch('messages.php?action=get_contacts&search=' + q);
                const data = await res.json();
                contacts = Array.isArray(data.data) ? data.data : [];

                if (currentContact) {
                    currentContact = contacts.find(c =>
                        c.contact_id === currentContact.contact_id &&
                        c.contact_type === currentContact.contact_type
                    ) || currentContact;
                }

                renderContacts();
            } catch (error) {
                contactsList.innerHTML = '<div class="empty"><p>تعذر تحميل جهات الاتصال حالياً.</p></div>';
            }
        }

        function renderContacts() {
            if (!contacts.length) {
                contactsList.innerHTML = '<div class="empty"><div class="empty-illustration"><i class="far fa-address-book"></i></div><p>لا توجد جهات اتصال حالياً.</p></div>';
                return;
            }

            contactsList.innerHTML = contacts.map(c => `
                <div class="contact ${currentContact && currentContact.contact_id === c.contact_id && currentContact.contact_type === c.contact_type ? 'active' : ''}"
                    data-id="${c.contact_id}" data-type="${c.contact_type}">
                    <div class="contact-avatar">
                        <div class="avatar-circle">${getInitials(c.contact_name)}</div>
                        <div class="online-dot"></div>
                    </div>
                    <div class="contact-info">
                        <div class="contact-top">
                            <span class="contact-name">${esc(c.contact_name || 'مستخدم')}</span>
                            <span class="contact-time">${formatTime(c.last_message_at)}</span>
                        </div>
                        <div class="contact-meta">
                            <span>${esc(c.contact_meta || '')}</span>
                        </div>
                        <div class="contact-last">
                            <span class="contact-last-text">${esc(c.last_message || 'لا توجد رسائل بعد')}</span>
                            ${c.unread_count > 0 ? `<span class="unread">${c.unread_count}</span>` : ''}
                        </div>
                    </div>
                </div>
            `).join('');

            contactsList.querySelectorAll('.contact').forEach(el => {
                el.addEventListener('click', async () => {
                    const id = Number(el.dataset.id);
                    const type = el.dataset.type;
                    const found = contacts.find(c => c.contact_id === id && c.contact_type === type);
                    if (!found) return;

                    currentContact = found;
                    msgInput.disabled = false;
                    sendBtn.disabled = false;
                    updateDetailPanel(found);
                    renderContacts();
                    await loadMessages();
                });
            });
        }

        async function loadMessages() {
            if (!currentContact) return;

            try {
                const url = `messages.php?action=get_messages&contact_id=${currentContact.contact_id}&contact_type=${currentContact.contact_type}`;
                const res = await fetch(url);
                const data = await res.json();
                const list = Array.isArray(data.data) ? data.data : [];

                chatHeader.innerHTML = `
                    <div class="chat-head-main">
                        <div class="chat-head-avatar">${getInitials(currentContact.contact_name)}</div>
                        <div>
                            <strong>${esc(currentContact.contact_name)}</strong>
                            <small>${esc(currentContact.contact_meta || '')}</small>
                        </div>
                    </div>
                    <div class="chat-head-actions">
                        <button title="مكالمة صوتية"><i class="fas fa-phone"></i></button>
                        <button title="مكالمة فيديو"><i class="fas fa-video"></i></button>
                        <button title="المزيد"><i class="fas fa-ellipsis-v"></i></button>
                    </div>
                `;

                if (!list.length) {
                    chatBody.innerHTML = '<div class="empty"><div class="empty-illustration"><i class="far fa-paper-plane"></i></div><h3>لا توجد رسائل بعد</h3><p>ابدأ أول رسالة الآن.</p></div>';
                    await loadContacts();
                    return;
                }

                chatBody.innerHTML = list.map(renderBubble).join('');
                chatBody.scrollTop = chatBody.scrollHeight;
                await loadContacts();
            } catch (error) {
                chatBody.innerHTML = '<div class="empty"><p>تعذر تحميل الرسائل حالياً.</p></div>';
            }
        }

        async function sendMessage() {
            const message = msgInput.value.trim();
            const file = attachmentInput.files && attachmentInput.files[0];

            if (!currentContact || (message === '' && !file)) {
                return;
            }

            sendBtn.disabled = true;

            try {
                const formData = new FormData();
                formData.append('receiver_id', currentContact.contact_id);
                formData.append('receiver_type', currentContact.contact_type);
                formData.append('message', message);

                if (file) {
                    formData.append('attachment', file);
                }

                const res = await fetch('messages.php?action=send', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();

                if (!data.success) {
                    alert(data.message || 'فشل إرسال الرسالة');
                    return;
                }

                msgInput.value = '';
                clearSelectedFile();
                await loadMessages();
            } catch (error) {
                alert('وقع خطأ أثناء إرسال الرسالة');
            } finally {
                sendBtn.disabled = false;
            }
        }

        sendBtn.addEventListener('click', sendMessage);
        msgInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });
        attachmentInput.addEventListener('change', updateSelectedFile);
        searchInput.addEventListener('input', () => {
            clearTimeout(window.__searchTimer);
            window.__searchTimer = setTimeout(loadContacts, 250);
        });

        loadContacts();
        refreshTimer = setInterval(() => {
            if (currentContact) {
                loadMessages();
            } else {
                loadContacts();
            }
        }, 7000);
    </script>
</body>
</html>
