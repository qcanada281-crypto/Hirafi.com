<?php
session_start();
require_once 'backend/config.php';

$user_type  = $_SESSION['user_type'] ?? null;
$is_client  = ($user_type === 'client');
$is_artisan = ($user_type === 'craftsman');
$client_id  = $is_client  ? (int)$_SESSION['client_id']    : 0;
$artisan_id = $is_artisan ? (int)$_SESSION['craftsman_id'] : 0;

if (!$is_client && !$is_artisan) {
    header('Location: client_login.html');
    exit;
}

$conv_id = (int)($_GET['conv'] ?? 0);

// Escaping helper
function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>المحادثة الخاصة — حرفي</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg: #111B21;
            --panel: #202C33;
            --border: #2A3942;
            --brand: #25D366;
            --brand-light: #128C7E;
            --msg-me: #005C4B;
            --msg-other: #202C33;
            --text: #FFFFFF;
            --muted: #AEBAC1;
            --radius-lg: 18px;
            --radius-md: 8px;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Cairo', sans-serif;
            background: #000;
            color: var(--text);
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0; left: 0; right: 0;
            height: 140px;
            background: #0d5445;
            z-index: 0;
        }

        /* ===== APP BAR (hidden — replaced by sidebar header) ===== */
        .app-bar { display: none; }

        /* ===== MAIN LAYOUT ===== */
        .chat-container {
            position: relative;
            z-index: 10;
            width: calc(100% - 32px);
            max-width: 1600px;
            height: calc(100vh - 32px);
            margin: 16px auto;
            display: grid;
            grid-template-columns: 400px 1fr;
            background: var(--bg);
            box-shadow: 0 8px 30px rgba(0,0,0,0.5);
            border-radius: 3px;
            overflow: hidden;
        }

        /* ===== SIDEBAR ===== */
        .conv-list {
            background: var(--panel);
            display: flex;
            flex-direction: column;
            border-left: 1px solid var(--border);
            overflow: hidden;
        }

        .sidebar-header {
            background: #202C33;
            padding: 10px 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            min-height: 60px;
            flex-shrink: 0;
        }

        .sidebar-header .brand-area {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .sidebar-header img.profile-pic {
            width: 40px; height: 40px;
            border-radius: 50%;
            object-fit: cover;
            cursor: pointer;
        }

        .sidebar-header .brand-text {
            font-size: 1.1rem;
            font-weight: 800;
            background: linear-gradient(135deg, #25D366, #aeffd8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .sidebar-header .icon-btn {
            background: transparent;
            border: none;
            color: #AEBAC1;
            font-size: 1.2rem;
            cursor: pointer;
            padding: 8px;
            border-radius: 50%;
            transition: background 0.2s, color 0.2s;
            display: flex;
            align-items: center;
        }

        .sidebar-header .icon-btn:hover { background: rgba(255,255,255,0.08); color: #E9EDEF; }

        .sidebar-header .icons-group { display: flex; gap: 4px; }

        /* Search Bar */
        .conv-search {
            padding: 8px 12px;
            background: #111B21;
            flex-shrink: 0;
        }

        .conv-search input {
            width: 100%;
            background: #202C33;
            border: none;
            padding: 9px 14px 9px 38px;
            border-radius: 8px;
            color: #E9EDEF;
            font-family: inherit;
            font-size: 0.9rem;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='%23AEBAC1' stroke-width='2'%3E%3Ccircle cx='11' cy='11' r='8'%3E%3C/circle%3E%3Cpath d='m21 21-4.35-4.35'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: left 10px center;
            transition: background-color 0.2s;
        }

        .conv-search input::placeholder { color: #8696A0; }
        .conv-search input:focus { outline: none; background-color: #2A3942; }

        /* Scrollable list */
        #conversations-wrapper {
            flex: 1;
            overflow-y: auto;
        }

        /* Scrollbar */
        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.15); border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.25); }

        /* Conversation Item */
        .conv-item {
            display: flex;
            padding: 0 16px;
            cursor: pointer;
            transition: background 0.15s;
            text-decoration: none;
            color: inherit;
            align-items: center;
        }

        .conv-item:hover { background: #2A3942; }
        .conv-item.active { background: #2A3942; }

        .conv-item .conv-avatar {
            width: 49px; height: 49px;
            border-radius: 50%;
            object-fit: cover;
            flex-shrink: 0;
            margin-left: 14px;
            margin-right: 2px;
        }

        .conv-item .conv-body {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 13px 0;
            border-bottom: 1px solid #222d34;
            min-width: 0;
        }

        .conv-item:last-child .conv-body { border-bottom: none; }

        .conv-item .conv-row1 {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 3px;
        }

        .conv-item .name {
            font-size: 1.05rem;
            font-weight: 600;
            color: #E9EDEF;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .conv-item .time {
            font-size: 0.73rem;
            color: #8696A0;
            white-space: nowrap;
            margin-right: 8px;
        }

        .conv-item .conv-row2 {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .conv-item .last-msg {
            font-size: 0.88rem;
            color: #8696A0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            flex: 1;
        }

        .conv-item .unread {
            background: #25D366;
            color: #111B21;
            font-size: 0.73rem;
            font-weight: 700;
            min-width: 20px;
            height: 20px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 5px;
            flex-shrink: 0;
            margin-right: 8px;
        }

        /* ===== CHAT WORKSPACE ===== */
        .chat-workspace {
            display: flex;
            flex-direction: column;
            position: relative;
            overflow: hidden;
        }

        /* Chat background pattern */
        .chat-workspace::before {
            content: '';
            position: absolute;
            inset: 0;
            background-color: #0B141A;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='60' height='60'%3E%3Crect fill='%23182229' width='60' height='60'/%3E%3Ccircle cx='30' cy='30' r='8' fill='none' stroke='%23263139' stroke-width='1'/%3E%3Cpath d='M0 30 L60 30 M30 0 L30 60' stroke='%23263139' stroke-width='0.5'/%3E%3C/svg%3E");
            opacity: 0.85;
            z-index: 0;
        }

        .chat-workspace::after {
            content: '';
            position: absolute;
            inset: 0;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='400' height='400'%3E%3Crect fill='none' width='400' height='400'/%3E%3Cg opacity='0.04' fill='%2325D366'%3E%3Cpath d='M40 10 L10 40 M80 10 L10 80 M120 10 L10 120'/%3E%3C/g%3E%3C/svg%3E");
            opacity: 1;
            z-index: 0;
            pointer-events: none;
        }

        .chat-workspace > * { position: relative; z-index: 1; }

        .chat-workspace.fullscreen-mode {
            position: fixed;
            inset: 0;
            z-index: 10000;
        }

        /* Chat Header */
        .chat-header {
            background: #202C33;
            padding: 10px 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            min-height: 60px;
            flex-shrink: 0;
            z-index: 5;
        }

        .chat-header .user-info {
            display: flex;
            align-items: center;
            gap: 0;
            cursor: pointer;
        }

        .chat-header img {
            width: 40px; height: 40px;
            border-radius: 50%;
            object-fit: cover;
            margin-left: 12px;
        }

        .chat-header h4 {
            font-size: 1rem;
            font-weight: 600;
            color: #E9EDEF;
            line-height: 1.3;
        }

        .chat-header .status {
            font-size: 0.78rem;
            color: #8696A0;
            display: block;
        }

        .header-actions {
            display: flex;
            gap: 4px;
            align-items: center;
        }

        .header-actions button {
            background: transparent;
            border: none;
            color: #AEBAC1;
            font-size: 1.2rem;
            cursor: pointer;
            padding: 8px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s, color 0.2s;
        }

        .header-actions button:hover { background: rgba(255,255,255,0.08); color: #E9EDEF; }

        /* Messages list */
        .messages-list {
            flex: 1;
            overflow-y: auto;
            padding: 16px 8%;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        /* Bubbles */
        .msg-bubble {
            max-width: 65%;
            padding: 6px 10px 22px 10px;
            border-radius: 7.5px;
            font-size: 0.93rem;
            line-height: 19px;
            position: relative;
            word-wrap: break-word;
            box-shadow: 0 1px 0.5px rgba(0,0,0,0.3);
            animation: msgFadeIn 0.25s ease;
        }

        @keyframes msgFadeIn {
            from { opacity: 0; transform: translateY(8px) scale(0.98); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }

        /* ME — sent (right in RTL = align-self flex-start) */
        .msg-bubble.me {
            align-self: flex-start;
            background: #005C4B;
            color: #E9EDEF;
            border-top-right-radius: 0;
        }

        .msg-bubble.me::after {
            content: '';
            position: absolute;
            top: 0; right: -8px;
            border-top: 8px solid #005C4B;
            border-right: 8px solid transparent;
        }

        /* OTHER — received (left in RTL = align-self flex-end) */
        .msg-bubble.other {
            align-self: flex-end;
            background: #202C33;
            color: #E9EDEF;
            border-top-left-radius: 0;
        }

        .msg-bubble.other::after {
            content: '';
            position: absolute;
            top: 0; left: -8px;
            border-top: 8px solid #202C33;
            border-left: 8px solid transparent;
        }

        .msg-bubble .time {
            position: absolute;
            bottom: 5px;
            left: 9px;
            font-size: 0.67rem;
            color: rgba(255,255,255,0.55);
            display: flex;
            align-items: center;
            gap: 3px;
            white-space: nowrap;
        }

        .msg-bubble .status-icon {
            font-size: 0.72rem;
            color: #53BDEB;
        }

        /* Media in bubbles */
        .msg-bubble img.media-img {
            max-width: 100%;
            border-radius: 6px;
            cursor: pointer;
            transition: transform 0.2s ease;
            display: block;
            margin-bottom: 4px;
        }

        .msg-bubble img.media-img:hover { transform: scale(1.02); }

        .msg-bubble video.media-img {
            max-width: 100%;
            border-radius: 6px;
            display: block;
            margin-bottom: 4px;
        }

        /* File previews */
        .msg-bubble .file-preview {
            display: flex;
            align-items: center;
            gap: 12px;
            background: rgba(0,0,0,0.18);
            padding: 10px 14px;
            border-radius: 6px;
            color: #E9EDEF;
            text-decoration: none;
            margin-bottom: 4px;
            transition: background 0.2s;
        }

        .msg-bubble .file-preview:hover { background: rgba(0,0,0,0.28); }

        .msg-bubble .file-preview i { font-size: 1.5rem; color: #25D366; flex-shrink: 0; }

        .msg-bubble .file-preview span {
            font-size: 0.88rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 180px;
        }

        /* Typing indicator */
        .typing-indicator {
            position: absolute;
            bottom: 70px;
            right: 8%;
            background: #202C33;
            padding: 7px 16px;
            border-radius: 18px;
            font-size: 0.82rem;
            color: #25D366;
            box-shadow: 0 2px 6px rgba(0,0,0,0.3);
            display: none;
            z-index: 20;
        }

        /* Input area */
        .input-area {
            background: #202C33;
            padding: 8px 16px;
            display: flex;
            gap: 8px;
            align-items: flex-end;
            flex-shrink: 0;
            z-index: 5;
        }

        .btn-icon {
            background: transparent;
            border: none;
            color: #8696A0;
            font-size: 1.35rem;
            cursor: pointer;
            padding: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: color 0.2s, background 0.2s;
            width: 40px; height: 40px;
            flex-shrink: 0;
        }

        .btn-icon:hover { color: #E9EDEF; background: rgba(255,255,255,0.06); }

        .input-area textarea {
            flex: 1;
            background: #2A3942;
            border: none;
            border-radius: 8px;
            padding: 11px 16px;
            color: #E9EDEF;
            font-family: inherit;
            font-size: 0.95rem;
            resize: none;
            min-height: 42px;
            max-height: 180px;
            overflow: auto;
            outline: none;
            line-height: 1.5;
        }

        .input-area textarea::placeholder { color: #8696A0; }

        .btn-send {
            background: #25D366;
            color: #111B21;
            width: 42px; height: 42px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .btn-send:hover { background: #2be977; color: #111B21; }

        /* Preview modal */
        .preview-modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(11,20,26,0.96);
            z-index: 99999;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .preview-modal.active { display: flex; }

        .preview-modal-content {
            max-width: 90%;
            max-height: 90vh;
            position: relative;
            border-radius: 8px;
            overflow: hidden;
        }

        .preview-modal-content img,
        .preview-modal-content video,
        .preview-modal-content iframe {
            display: block;
            max-width: 100%;
            max-height: 85vh;
            height: auto;
        }

        .preview-modal-close {
            position: absolute;
            top: 14px; right: 14px;
            background: rgba(255,255,255,0.12);
            border: none;
            color: #fff;
            width: 36px; height: 36px;
            border-radius: 50%;
            display: grid; place-items: center;
            cursor: pointer;
            font-size: 1.2rem;
            z-index: 10;
        }

        .preview-modal-caption {
            padding: 12px 16px;
            color: #E9EDEF;
            font-size: 0.9rem;
            background: rgba(0,0,0,0.5);
        }

        /* Responsive */
        @media (max-width: 900px) {
            .chat-container { width: 100%; height: 100vh; margin: 0; border-radius: 0; grid-template-columns: 1fr; }
            body::before { display: none; }
            .conv-list { display: none; }
            .messages-list { padding: 12px 4%; }
            .msg-bubble { max-width: 85%; }
        }

        @media (min-width: 901px) and (max-width: 1200px) {
            .chat-container { grid-template-columns: 320px 1fr; }
        }
    </style>
</head>
<body>

    <!-- Main layout -->
    <div class="chat-container">

        <!-- ===== SIDEBAR ===== -->
        <aside class="conv-list">

            <!-- Sidebar top header -->
            <div class="sidebar-header">
                <div class="brand-area">
                    <img class="profile-pic"
                         src="<?php echo $is_client ? 'img/man.webp' : 'img/artisan.jpg'; ?>"
                         onerror="this.src='https://ui-avatars.com/api/?name=User&background=128C7E&color=fff'"
                         alt="Profile">
                    <span class="brand-text">⚒ حرفي</span>
                </div>
                <div class="icons-group">
                    <a href="<?php echo $is_client ? 'backend/client_dashboard.php' : 'backend/artisan_dashboard.php'; ?>" style="text-decoration:none;">
                        <button class="icon-btn" title="العودة للوحة التحكم"><i class="fa-solid fa-arrow-right"></i></button>
                    </a>
                    <button class="icon-btn" title="فلترة"><i class="fa-solid fa-filter"></i></button>
                    <button class="icon-btn" title="قائمة"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                </div>
            </div>

            <!-- Search -->
            <div class="conv-search">
                <input type="text" placeholder="البحث في المحادثات أو البدء بمحادثة جديدة...">
            </div>

            <!-- Dynamically loaded conversations -->
            <div id="conversations-wrapper"></div>

        </aside>

        <!-- ===== CHAT WORKSPACE ===== -->
        <section class="chat-workspace">

            <!-- Empty state -->
            <div id="no-chat-selected" style="position:relative; z-index:2; flex:1; display:flex; flex-direction:column; justify-content:center; align-items:center; gap:20px; background:rgba(11,20,26,0.7);">
                <div style="width:200px; height:200px; border-radius:50%; background:rgba(37,211,102,0.08); display:flex; align-items:center; justify-content:center;">
                    <i class="fa-regular fa-comments" style="font-size:5rem; color:#25D366; opacity:0.6;"></i>
                </div>
                <h2 style="color:#E9EDEF; font-weight:300; font-size:1.8rem;">حرفي ويب</h2>
                <p style="color:#8696A0; text-align:center; max-width:380px; line-height:1.7;">تواصل بسهولة وأمان مع الحرفيين والعملاء.<br>اختر محادثة من القائمة للبدء.</p>
                <div style="position:absolute; bottom:24px; left:0; right:0; text-align:center; color:#3C494E; font-size:0.8rem;">
                    <i class="fa-solid fa-lock" style="margin-left:6px;"></i> رسائلك محمية بالتشفير من طرف إلى طرف
                </div>
            </div>

            <!-- Active chat -->
            <div id="chat-active-box" style="display:none; flex:1; flex-direction:column; overflow:hidden;">

                <!-- Chat Header -->
                <div class="chat-header">
                    <div class="user-info">
                        <img id="chat-header-avatar" src="" style="display:none;" alt="avatar">
                        <div style="margin-right:4px;">
                            <h4 id="chat-header-name">جاري التحميل...</h4>
                            <span class="status" id="chat-header-status">متصل الآن</span>
                        </div>
                    </div>
                    <div class="header-actions">
                        <button title="مكالمة فيديو"><i class="fa-solid fa-video"></i></button>
                        <button title="مكالمة صوتية"><i class="fa-solid fa-phone"></i></button>
                        <button title="بحث"><i class="fa-solid fa-magnifying-glass"></i></button>
                        <button id="chat-fullscreen-btn" onclick="toggleFullscreenChat()" title="تكبير/تصغير">
                            <i class="fa-solid fa-expand"></i><span id="fullscreen-text" style="display:none;"></span>
                        </button>
                        <?php if ($is_client): ?>
                        <button onclick="openChatComplaintModal()" title="إبلاغ الإدارة" style="color:#F87171;">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Messages -->
                <div class="messages-list" id="messages-parent"></div>

                <!-- Typing indicator -->
                <div class="typing-indicator" id="typing-indicator-box">
                    <i class="fa-solid fa-ellipsis" style="margin-left:6px;"></i> يكتب الآن...
                </div>

                <!-- Input Area -->
                <div class="input-area">
                    <button class="btn-icon" title="إيموجي"><i class="fa-regular fa-face-smile"></i></button>
                    <button class="btn-icon" title="أرفق ملف" onclick="document.getElementById('chat-file-input').click()"><i class="fa-solid fa-paperclip"></i></button>
                    <input type="file" id="chat-file-input" accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.ppt,.pptx" style="display:none;" onchange="handleFileSend(event)">
                    <button class="btn-icon" title="إرسال الموقع" onclick="sendLocation()"><i class="fa-solid fa-location-dot"></i></button>

                    <textarea id="chat-textarea" placeholder="اكتب رسالتك..." oninput="handleTyping()"></textarea>

                    <button class="btn-icon" title="رسالة صوتية"><i class="fa-solid fa-microphone"></i></button>
                    <button class="btn-icon btn-send" onclick="sendMessage()" title="إرسال">
                        <i class="fa-solid fa-paper-plane"></i>
                    </button>
                </div>

            </div>
        </section>
    </div>

    <!-- Chat Complaint Modal -->
    <div id="chat-complaint-modal" style="display:none; position:fixed; inset:0; background:rgba(11,20,26,0.9); z-index:9999; align-items:center; justify-content:center; padding:16px;">
        <div style="background:#202C33; border:1px solid #2A3942; border-radius:12px; padding:28px; max-width:500px; width:100%; box-shadow:0 20px 60px rgba(0,0,0,0.6);">
            <h3 style="margin-bottom:20px; color:#E9EDEF; display:flex; align-items:center; gap:10px;">
                <i class="fa-solid fa-triangle-exclamation" style="color:#F87171;"></i> تقديم شكوى للإدارة
            </h3>
            <div style="display:flex; flex-direction:column; gap:16px;">
                <div>
                    <label style="display:block; margin-bottom:8px; font-weight:600; color:#AEBAC1; font-size:0.92rem;">سبب الشكوى:</label>
                    <select id="chat-comp-type" style="width:100%; padding:12px 14px; background:#2A3942; border:none; color:#E9EDEF; border-radius:8px; outline:none; font-family:inherit; font-size:0.95rem;">
                        <option value="no_response">توقف عن الرد والمماطلة</option>
                        <option value="bad_behavior">أسلوب سيء أو شتم</option>
                        <option value="fraud">احتيال أو طلب تحويل مالي غير مشروع</option>
                        <option value="late_work">تأخير في الموعد المتفق عليه</option>
                        <option value="other">سبب آخر</option>
                    </select>
                </div>
                <div>
                    <label style="display:block; margin-bottom:8px; font-weight:600; color:#AEBAC1; font-size:0.92rem;">تفاصيل المشكلة:</label>
                    <textarea id="chat-comp-desc" style="width:100%; height:110px; padding:12px 14px; background:#2A3942; border:none; color:#E9EDEF; border-radius:8px; resize:none; outline:none; font-family:inherit; font-size:0.92rem; line-height:1.6;" placeholder="يرجى كتابة التفاصيل التي حدثت في الدردشة ليتم مراجعتها من قبل المديرين..."></textarea>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:12px; margin-top:4px;">
                    <button onclick="closeChatComplaintModal()" style="padding:11px 20px; border-radius:8px; background:transparent; border:1px solid #AEBAC1; color:#AEBAC1; cursor:pointer; font-family:inherit; font-size:0.92rem;">إلغاء</button>
                    <button onclick="submitChatComplaint()" style="padding:11px 22px; border-radius:8px; background:#ef4444; border:none; color:white; font-weight:700; cursor:pointer; font-family:inherit; font-size:0.92rem;">إرسال للإدارة الآن</button>
                </div>
            </div>
        </div>
    </div>

<script>
    const activeConvId = <?php echo $conv_id; ?>;
    const userType     = "<?php echo $user_type; ?>";
    let lastMsgId      = 0;
    let pollInterval   = null;

    function normalizeAvatarUrl(raw) {
        const avatar = String(raw || '').trim();
        if (!avatar) return '';
        if (/^(https?:)?\/\//i.test(avatar) || avatar.startsWith('/') || avatar.startsWith('data:')) {
            return avatar;
        }
        if (avatar.startsWith('../')) {
            return avatar.slice(3);
        }
        if (avatar.includes('/')) {
            return avatar;
        }
        return 'uploads/avatars/' + avatar;
    }

    // Load conversations list
    async function loadConversations() {
        try {
            const res = await fetch('backend/chat.php?action=conversations');
            const data = await res.json();
            if (data.success) {
                const parent = document.getElementById('conversations-wrapper');
                parent.innerHTML = data.conversations.map(c => {
                    const isActive = (c.id === activeConvId) ? 'active' : '';
                    const unread   = (c.unread_count > 0 && !isActive) ? `<span class="unread">${c.unread_count}</span>` : '';
                    
                    const otherAvatarStr = (c.other_avatar || "").trim();
                    const avatar = otherAvatarStr ? normalizeAvatarUrl(otherAvatarStr) : `https://ui-avatars.com/api/?name=${encodeURIComponent(c.other_name)}&background=0f766e&color=fff`;
                    
                    if (isActive) {
                        document.getElementById('chat-header-avatar').src = avatar;
                        document.getElementById('chat-header-avatar').style.display = 'block';
                    }

                    return `
                        <a href="?conv=${c.id}" class="conv-item ${isActive}">
                            <img class="conv-avatar" src="${avatar}" alt="avatar">
                            <div class="conv-body">
                                <div class="conv-row1">
                                    <div class="name">${escapeHtml(c.other_name)}</div>
                                    <span class="time">${formatTime(c.last_msg_at || c.created_at)}</span>
                                </div>
                                <div class="conv-row2">
                                    <div class="last-msg">${escapeHtml(c.last_msg || 'محادثة جديدة لم تبدأ بعد')}</div>
                                    ${unread}
                                </div>
                            </div>
                        </a>
                    `;
                }).join('');
            }
        } catch(e){}
    }

    // Load messages chat details
    async function loadChatMessages() {
        if (activeConvId <= 0) return;
        document.getElementById('no-chat-selected').style.display = 'none';
        document.getElementById('chat-active-box').style.display = 'flex';

        try {
            const res = await fetch(`backend/chat.php?action=history&conv_id=${activeConvId}`);
            const data = await res.json();
            if (data.success) {
                if (userType === 'client') {
                    targetArtisanId = data.conv.artisan_id;
                }

                // Update header info
                document.getElementById('chat-header-name').textContent = data.conv.request_title || 'محادثة خاصة للاتفاق';
                
                const parent = document.getElementById('messages-parent');
                parent.innerHTML = data.messages.map(m => renderMessage(m)).join('');
                
                if (data.messages.length > 0) {
                    lastMsgId = data.messages[data.messages.length - 1].id;
                }
                scrollToBottom();

                // Setup Poll interval
                if (!pollInterval) {
                    pollInterval = setInterval(pollNewMessages, 2500);
                }

                // Mark messages as seen when opening the chat
                await fetch('backend/chat.php?action=mark_seen', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ conv_id: activeConvId })
                });
            }
        } catch(e){}
    }

    // Poll messages
    async function pollNewMessages() {
        try {
            const res = await fetch(`backend/chat.php?action=poll&conv_id=${activeConvId}&last_id=${lastMsgId}`);
            const data = await res.json();
            if (data.success) {
                const typingIndicator = document.getElementById('typing-indicator-box');
                typingIndicator.style.display = data.is_typing ? 'block' : 'none';

                if (data.messages.length > 0) {
                    const parent = document.getElementById('messages-parent');
                    data.messages.forEach(m => {
                        parent.innerHTML += renderMessage(m);
                        lastMsgId = m.id;
                    });
                    scrollToBottom();
                }
            }
        } catch(e){}
    }

    // Send textual message
    async function sendMessage() {
        const txt = document.getElementById('chat-textarea');
        const val = txt.value.trim();
        if (!val) return;

        try {
            const res = await fetch('backend/chat.php?action=send', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ conv_id: activeConvId, content: val, message_type: 'text' })
            });
            const data = await res.json();
            if (data.success) {
                txt.value = '';
                pollNewMessages();
            }
        } catch(e){}
    }

    // Send location
    function sendLocation() {
        if (!navigator.geolocation) {
            alert('الجي بي اس غير مدعوم في متصفحك');
            return;
        }
        navigator.geolocation.getCurrentPosition(async (pos) => {
            try {
                await fetch('backend/chat.php?action=send', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        conv_id: activeConvId,
                        message_type: 'location',
                        latitude: pos.coords.latitude,
                        longitude: pos.coords.longitude
                    })
                });
                pollNewMessages();
            } catch(e){}
        });
    }

    // Send media files
    async function handleFileSend(e) {
        const file = e.target.files[0];
        if (!file) return;

        let ft = 'image';
        const ext = (file.name.split('.').pop() || '').toLowerCase();
        const docExts = ['doc','docx','ppt','pptx'];

        if (file.type.startsWith('video/')) ft = 'video';
        else if (file.type.startsWith('audio/')) ft = 'audio';
        else if (file.type === 'application/pdf') ft = 'pdf';
        else if (docExts.includes(ext)) ft = 'document';
        else if (file.type.startsWith('image/')) ft = 'image';
        else if (file.type === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' || file.type === 'application/msword') ft = 'document';
        else if (file.type === 'application/vnd.openxmlformats-officedocument.presentationml.presentation' || file.type === 'application/vnd.ms-powerpoint') ft = 'document';

        const fd = new FormData();
        fd.append('conv_id', activeConvId);
        fd.append('message_type', ft);
        fd.append('file', file);

        try {
            await fetch('backend/chat.php?action=send', {
                method: 'POST',
                body: fd
            });
            pollNewMessages();
        } catch(e){}
    }

    // Typing indication
    let typingTimeout = null;
    async function handleTyping() {
        clearTimeout(typingTimeout);
        try {
            await fetch('backend/chat.php?action=typing', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ conv_id: activeConvId })
            });
        } catch(e){}
    }

    // Render helper
    function renderMessage(m) {
        const isMe   = (m.sender_type === userType);
        const bubble = isMe ? 'me' : 'other';
        
        let body = `<p>${escapeHtml(m.content)}</p>`;
        
        if (m.message_type === 'image' && m.file_path) {
            body = `<img class="media-img" src="${m.file_path}" alt="media" onclick="showPreview(${JSON.stringify(m.file_path)}, 'image', ${JSON.stringify(m.file_name || '')})">`;
        } else if (m.message_type === 'video' && m.file_path) {
            body = `<video class="media-img" controls onclick="showPreview(${JSON.stringify(m.file_path)}, 'video', ${JSON.stringify(m.file_name || '')})" src="${m.file_path}"></video>`;
        } else if (m.message_type === 'location' && m.latitude) {
            body = `<p><i class="fa-solid fa-location-dot"></i> موقع تم مشاركته:<br><a href="https://maps.google.com/?q=${m.latitude},${m.longitude}" target="_blank" style="color:var(--brand-light)">خرائط جوجل</a></p>`;
        } else if (m.message_type === 'pdf') {
            body = `<a class="file-preview" href="javascript:void(0);" onclick="showPreview(${JSON.stringify(m.file_path)}, 'pdf', ${JSON.stringify(m.file_name || 'ملف PDF')})"><i class="fa-solid fa-file-pdf"></i><span>${escapeHtml(m.file_name || 'ملف PDF')}</span></a>`;
        } else if (m.message_type === 'document') {
            body = `<a class="file-preview" href="javascript:void(0);" onclick="showPreview(${JSON.stringify(m.file_path)}, 'document', ${JSON.stringify(m.file_name || 'ملف')})"><i class="fa-solid fa-file-lines"></i><span>${escapeHtml(m.file_name || 'ملف')}</span></a>`;
        }

        return `
            <div class="msg-bubble ${bubble}">
                ${body}
                <span class="time">
                    ${formatTime(m.created_at)}
                    ${isMe ? `<span class="status-icon" style="color:var(--brand-light)"><i class="fa-solid fa-check"></i></span>`:''}
                </span>
            </div>
        `;
    }

    function scrollToBottom() {
        const el = document.getElementById('messages-parent');
        el.scrollTop = el.scrollHeight;
    }

    function escapeHtml(text) {
        if (!text) return '';
        return text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
    }

    function formatTime(str) {
        if (!str) return '';
        const d = new Date(str.replace(/-/g, '/'));
        return d.toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit' });
    }

    // Modal Complaint logic
    let targetArtisanId = null;
    function openChatComplaintModal() {
        if (!activeConvId) {
            alert('يرجى تحديد محادثة أولاً');
            return;
        }
        document.getElementById('chat-complaint-modal').style.display = 'flex';
        // We will retrieve artisan ID from URL or since it's the opened conversation we assume we can extract targetArtisanId from fetch calls or directly ask the API. But for now, we just fetch the convo.
        // Actually, during 'loadChatMessages', we can save it.
    }

    function closeChatComplaintModal() {
        document.getElementById('chat-complaint-modal').style.display = 'none';
        document.getElementById('chat-comp-desc').value = '';
    }

    async function submitChatComplaint() {
        const ctype = document.getElementById('chat-comp-type').value;
        const cdesc = document.getElementById('chat-comp-desc').value.trim() + '\n(تم تقديم البلاغ مباشرة من صفحة المحادثات)';
        
        if (!cdesc.trim()) { alert('يرجى كتابة التفاصيل'); return; }

        try {
            const res = await fetch('backend/complaints.php?action=create', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    artisan_id: targetArtisanId,
                    conv_id: activeConvId, // Passed so backend detects artisan ID
                    complaint_type: ctype,
                    description: cdesc,
                    incident_date: new Date().toISOString().split('T')[0]
                })
            });
            const data = await res.json();
            if (data.success) {
                alert('تم رفع الشكوى للإدارة بنجاح. سيتم مراجعة المحادثات واتخاذ اللازم.');
                closeChatComplaintModal();
            } else {
                alert(data.message || 'حدث خطأ فني');
            }
        } catch(e) {
            alert('حدث خطأ في الاتصال');
        }
    }

    // Toggle Chat Fullscreen
    function toggleFullscreenChat() {
        const workspace = document.querySelector('.chat-workspace');
        const icon = document.querySelector('#chat-fullscreen-btn i');
        
        workspace.classList.toggle('fullscreen-mode');
        if (workspace.classList.contains('fullscreen-mode')) {
            icon.className = 'fa-solid fa-compress';
        } else {
            icon.className = 'fa-solid fa-expand';
        }
        scrollToBottom();
    }

    // Preview modal element
    const previewModal = document.createElement('div');
    previewModal.className = 'preview-modal';
    previewModal.id = 'preview-modal';
    previewModal.innerHTML = `
        <div class="preview-modal-content">
            <button class="preview-modal-close" onclick="closePreview()">×</button>
            <div id="preview-modal-body"></div>
            <div class="preview-modal-caption" id="preview-modal-caption"></div>
        </div>
    `;
    document.body.appendChild(previewModal);

    function showPreview(url, type, name) {
        const body = document.getElementById('preview-modal-body');
        const caption = document.getElementById('preview-modal-caption');
        const safeName = name || '';
        if (type === 'image') {
            body.innerHTML = `<img src="${url}" alt="${safeName}">`;
        } else if (type === 'video') {
            body.innerHTML = `<video controls autoplay src="${url}"></video>`;
        } else if (type === 'pdf') {
            body.innerHTML = `<iframe src="${url}" style="width:100%;height:80vh;border:none;"></iframe>`;
        } else {
            body.innerHTML = `<div style="padding:24px; text-align:center; color:#f8fafc;">
                <i class="fa-solid fa-file-lines" style="font-size:2rem; margin-bottom:14px;"></i>
                <p style="margin:0 0 16px;">يمكنك فتح الملف أو تحميله من الرابط أدناه.</p>
                <a href="${url}" target="_blank" rel="noopener" style="display:inline-flex; align-items:center; gap:10px; padding:12px 18px; border-radius:12px; background:#14b8a6; color:#fff; text-decoration:none; font-weight:700;"><i class="fa-solid fa-arrow-up-right-from-square"></i> فتح الملف</a>
            </div>`;
        }
        caption.textContent = safeName;
        previewModal.classList.add('active');
    }

    function closePreview() {
        const modal = document.getElementById('preview-modal');
        modal.classList.remove('active');
        document.getElementById('preview-modal-body').innerHTML = '';
    }

    // Close preview on background click
    document.addEventListener('click', function(event) {
        const modal = document.getElementById('preview-modal');
        if (!modal) return;
        if (event.target === modal) {
            closePreview();
        }
    });

    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closePreview();
        }
    });

    // Initialize
    loadConversations();
    loadChatMessages();
</script>
</body>
</html>
