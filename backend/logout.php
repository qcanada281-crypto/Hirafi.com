<?php
// ==================== LOGOUT ====================

session_start();

// Destroy session data
$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 3600, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}

session_destroy();

// Delete app remember cookies
setcookie('craftsman_remember', '', time() - 3600, '/');
setcookie('craftsmen_remember', '', time() - 3600, '/');
setcookie('admin_remember', '', time() - 3600, '/');

$redirect = '../index.html';
$isAjax = (
    (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
    (isset($_SERVER['HTTP_ACCEPT']) && stripos((string)$_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
    (isset($_GET['format']) && strtolower((string)$_GET['format']) === 'json')
);

if ($isAjax) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'message' => 'تم تسجيل الخروج بنجاح',
        'redirect' => $redirect,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

header('Location: ' . $redirect);
exit;
