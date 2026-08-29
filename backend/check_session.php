<?php
// ==================== CHECK SESSION ====================

// تحديد نوع المحتوى كـ JSON مع دعم الأحرف العربية
header('Content-Type: application/json; charset=utf-8');

// بدء أو استئناف الجلسة
session_start();

// مصفوفة الرد الافتراضية
$response = [
    'logged_in' => false,      // حالة تسجيل الدخول
    'user_type' => null,       // نوع المستخدم
    'user_name' => null        // اسم المستخدم
];

try {
    // التحقق من جلسة الحرفي
    // الشرط: وجود craftsman_id و user_type وأن user_type يساوي 'craftsman'
    if (isset($_SESSION['craftsman_id']) && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'craftsman') {
        $response['logged_in'] = true;                     // نعم، مسجل دخول
        $response['user_type'] = 'craftsman';              // نوع المستخدم: حرفي
        $response['user_name'] = $_SESSION['craftsman_name'] ?? '';  // اسم الحرفي أو سلسلة فارغة
        $response['craftsman_id'] = $_SESSION['craftsman_number'] ?? ''; // رقم الحرفي
    }
    // التحقق من جلسة المدير (إذا لم يكن طالباً)
    // الشرط: وجود admin_id و user_type وأن user_type يساوي 'admin'
    elseif (isset($_SESSION['admin_id']) && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin') {
        $response['logged_in'] = true;                     // نعم، مسجل دخول
        $response['user_type'] = 'admin';                  // نوع المستخدم: مدير
        $response['user_name'] = $_SESSION['admin_name'] ?? '';    // اسم المدير
        $response['admin_role'] = $_SESSION['admin_role'] ?? '';   // دور المدير
    }
    // التحقق من جلسة العميل
    elseif (isset($_SESSION['client_id']) && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'client') {
        $response['logged_in'] = true;
        $response['user_type'] = 'client';
        $response['user_name'] = $_SESSION['client_name'] ?? '';
    }

} catch (Exception $e) {
    // إذا حدث خطأ (مثلاً مشكلة في الجلسة) - لا تفعل شيئاً
    // الرد يبقى بالقيم الافتراضية (غير مسجل دخول)
}

// تحويل المصفوفة إلى JSON وإرسالها
echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>