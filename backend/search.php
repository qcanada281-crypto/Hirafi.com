<?php
header('Content-Type: application/json; charset=utf-8');
// هنا كتحدد لينا الباهوم غادي يرجع JSON باش يفهمو الفرونتاند
// و charset=utf-8 باش يدعم الحروف العربية والدارجة

require_once 'config.php';
// جيب لي فيه معلومات الاتصال مع قاعدة البيانات (الداتاباز)

$profession = trim($_GET['profession'] ?? '');
// خد 'profession' من URL، إلا ماجاينا والو حط فاضي
// trim() باش يزيل الفراغات من البداية والنهاية

$city = trim($_GET['city'] ?? '');
// نفس الشي للمدينة

$minRating = floatval($_GET['minRating'] ?? 0);
// خد 'minRating' من URL، إلا ماجاينا حط 0
// floatval() باش يحولها لرقم عشري

$response = ['success' => true, 'craftsmen' => []];
// هنا كتهيئ الجواب اللي غادي يرجع، فيه نجاح والقائمة دالصناع فاضي باش تملاها

try {
    // نبداو الاستعلام ديناميكياً
    $sql = "SELECT
                id,
                craftsman_id AS login_code,
                full_name AS name,
                specialization AS profession,
                city,
                email,
                phone,
                IFNULL(rating,0) AS rating,
                IFNULL(total_reviews,0) AS total_reviews,
                IFNULL(experience_years,0) AS experience_years,
                IFNULL(avatar,'') AS avatar,
                IFNULL(excerpt,'') AS excerpt
            FROM craftsmen
            WHERE status='active'";
    // SQL ديالنا: جيب الصناع اللي 'active' ماشي معطلين
    // IFNULL: إلا كانت القيمة فارغة، حط القيمة اللي عطيت
    
    $types = '';
    // فاضي باش نملا نوع المعاملات (سطرين، أرقام، إلخ)
    $params = [];
    // فاضي باش نملا القيم اللي غادي نربطوها فالإستعلام

    // إلا كانت 'profession' ماشي فارغة
    if ($profession !== '') {
        $sql .= " AND (LOWER(specialization) LIKE ? OR LOWER(full_name) LIKE ? )";
        // زيد شرط: إما الحرفة أو الاسم فيه الكلمة اللي دخل المستخدم
        // LOWER() باش البحث يكون غير حساس للحروف الكبيرة/الصغيرة
        
        $prof_like = '%' . mb_strtolower($profession) . '%';
        // حول لدارجة وزد % من الجانبين باش يبحث في أي مكان
        
        $types .= 'ss';
        // زيد 'ss' اللي معناها جوج معاملات من نوع سترينج
        
        $params[] = $prof_like;
        $params[] = $prof_like;
        // زيد القيمتين فالمصفوفة
    }

    // نفس الشي للمدينة
    if ($city !== '') {
        $sql .= " AND LOWER(city) LIKE ?";
        $city_like = '%' . mb_strtolower($city) . '%';
        $types .= 's';
        $params[] = $city_like;
    }

    // إلا كان الحد الأدنى للتقييم أكبر من 0
    if ($minRating > 0) {
        $sql .= " AND rating >= ?";
        $types .= 'd'; // d = double (رقم عشري)
        $params[] = $minRating;
    }

    $sql .= " ORDER BY rating DESC, id DESC LIMIT 100";
    // رتب النتائج: الأولوية للتصنيف الأعلى، ثم للآخرين
    // DESC = تنازلي (من الأكبر للأصغر)
    // LIMIT 100 = ما كتجيبش أكثر من 100 نتيجة

    $stmt = $conn->prepare($sql);
    // جهز الاستعلام (هاذشي مهم للأمان ضد الهجمات)

    // إلا كان الاستعارف معد بنجاح
    if ($stmt) {
        // إلا كان فيه معاملات لازم نربطوها
        if (!empty($params)) {
            $bind_names[] = $types;
            // هاذ الطريقة ديال الربط المعاملات ديناميكياً
            for ($i = 0; $i < count($params); $i++) {
                $bind_name = 'bind' . $i;
                // عمل متغير جديد كل مرة (bind0, bind1, إلخ)
                $$bind_name = $params[$i];
                // $$bind_name معناها: خد اسم المتغير كنص وخلق متغير بيه
                $bind_names[] = &$$bind_name;
                // زد المرجع للمتغير الجديد
            }
            call_user_func_array([$stmt, 'bind_param'], $bind_names);
            // نفذ bind_param مع كل المعاملات
        }

        $stmt->execute();
        // نـفـذ الاستعلام
        
        $res = $stmt->get_result();
        // خد النتائج
        
        // جري على كل صف (صناع) جابهم
        while ($row = $res->fetch_assoc()) {
            // تأكد من القيم باش تكونو موجودين للفرونتاند
            $row['avatar'] = $row['avatar'] ?: 'img/default-avatar.png';
            // إلا ماكانش عندو صورة، حط الصورة الافتراضية
            
            $row['excerpt'] = $row['excerpt'] ?: '';
            // إلا ماكانش عندو وصف، حط فاضي
            
            // زد الصناع لنتيجة الجواب
            $response['craftsmen'][] = [
                'id' => (int)$row['id'],
                'login_code' => $row['login_code'] ?? '',
                'name' => $row['name'],
                'profession' => $row['profession'],
                'city' => $row['city'] ?: '', // إلا ماجاينا والو حط فاضي
                'email' => $row['email'] ?? '',
                'phone' => $row['phone'] ?? '',
                'rating' => floatval($row['rating']),
                'total_reviews' => (int)($row['total_reviews'] ?? 0),
                'experience_years' => (int)($row['experience_years'] ?? 0),
                'avatar' => $row['avatar'],
                'excerpt' => $row['excerpt']
            ];
        }
    }
} catch (Exception $e) {
    // إلا وقع شي غلط (مثلاً الداتاباز مامتصلتش)
    // تجاهل الخطا ورجع قائمة فارغة
    // (الفرونتاند غادي يظهر "ماكاين والو")
}

// صدر النتيجة بصيغة JSON مع دعم اللغة العربية
echo json_encode($response, JSON_UNESCAPED_UNICODE);
