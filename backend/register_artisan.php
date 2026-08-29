<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. جلب البيانات من النموذج
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $city = $_POST['city'];
    $profession = $_POST['profession'];
    $experience = $_POST['experience'];
    
    // 2. التحقق من البريد الإلكتروني (إذا كان موجود مسبقاً)
    $check = $conn->prepare("SELECT id FROM artisans WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    $result = $check->get_result();
    
    if ($result->num_rows > 0) {
        die("البريد الإلكتروني موجود مسبقاً");
    }
    
    // 3. إنشاء رقم حرفي فريد
    $craft_number = 'CRAFT' . rand(100000, 999999);
    
    // 4. معالجة الصورة الشخصية
    $profile_image = '';
    if (!empty($_FILES['profile_image']['name'])) {
        $upload_dir = '../uploads/artisans/' . $craft_number . '/';
        mkdir($upload_dir, 0777, true);
        
        $profile_image = $upload_dir . 'profile_' . time() . '.jpg';
        move_uploaded_file($_FILES['profile_image']['tmp_name'], $profile_image);
    }
    
    // 5. إدخال البيانات في قاعدة البيانات
    $sql = "INSERT INTO artisans (craft_number, full_name, email, password, phone, city, profession, experience_years, profile_image) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssiss", $craft_number, $full_name, $email, $password, $phone, $city, $profession, $experience, $profile_image);
    $stmt->execute();
    
    $artisan_id = $conn->insert_id;
    
    // 6. إنشاء مجلدات للحرفي
    $artisan_dir = "../uploads/artisans/$craft_number/";
    mkdir($artisan_dir . 'portfolio', 0777, true);
    mkdir($artisan_dir . 'videos', 0777, true);
    mkdir($artisan_dir . 'certificates', 0777, true);
    
    // 7. معالجة الصور (portfolio)
    if (!empty($_FILES['portfolio_images']['name'][0])) {
        foreach ($_FILES['portfolio_images']['tmp_name'] as $key => $tmp_name) {
            $filename = time() . '_' . $_FILES['portfolio_images']['name'][$key];
            $destination = $artisan_dir . 'portfolio/' . $filename;
            move_uploaded_file($tmp_name, $destination);
            
            // حفظ في قاعدة البيانات
            $sql = "INSERT INTO artisan_portfolio (artisan_id, media_type, media_url) VALUES (?, 'image', ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("is", $artisan_id, $destination);
            $stmt->execute();
        }
    }
    
    // 8. معالجة الفيديوهات
    if (!empty($_FILES['videos']['name'][0])) {
        foreach ($_FILES['videos']['tmp_name'] as $key => $tmp_name) {
            $filename = time() . '_' . $_FILES['videos']['name'][$key];
            $destination = $artisan_dir . 'videos/' . $filename;
            move_uploaded_file($tmp_name, $destination);
            
            // حفظ في قاعدة البيانات
            $sql = "INSERT INTO artisan_portfolio (artisan_id, media_type, media_url) VALUES (?, 'video', ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("is", $artisan_id, $destination);
            $stmt->execute();
        }
    }
    
    // 9. رسالة نجاح وتوجيه
    echo "تم التسجيل بنجاح! رقم الحرفي الخاص بك: $craft_number";
    // header("Location: ../artisan_dashboard.php?craft=$craft_number");
}
?>