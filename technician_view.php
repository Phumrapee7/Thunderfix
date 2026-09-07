<?php
// technician_view.php — โปรไฟล์ช่างแบบสาธารณะ (อ่านอย่างเดียว)
session_start();
include('db/db.php');

// รับ id ช่าง
$tid = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($tid <= 0) {
    http_response_code(400);
    echo "bad request";
    exit;
}

// ดึงข้อมูลช่าง (เฉพาะคอลัมน์ที่จำเป็น)
$stmt = $conn->prepare("
  SELECT id, name, tech_type, bio, years_experience,
         min_price, emergency_fee, languages,
         phone_public, line_id_public, phone, line_id,
         service_radius_km, business_hours, business_hours_json,
         profile_image, cover_image, latitude, longitude
  FROM technicians
  WHERE id = ? LIMIT 1
");
$stmt->bind_param("i", $tid);
$stmt->execute();
$tech = $stmt->get_result()->fetch_assoc();

if (!$tech) {
    http_response_code(404);
    echo "ไม่พบข้อมูลช่าง";
    exit;
}

// ฟังก์ชันแปลง business_hours_json -> ข้อความอ่านง่าย (fallback)
function hoursJsonToText($json)
{
    $map = ['mon' => 'จันทร์', 'tue' => 'อังคาร', 'wed' => 'พุธ', 'thu' => 'พฤหัสบดี', 'fri' => 'ศุกร์', 'sat' => 'เสาร์', 'sun' => 'อาทิตย์'];
    $arr = json_decode($json, true);
    if (!is_array($arr)) return null;
    $out = [];
    foreach ($map as $k => $th) {
        if (!isset($arr[$k])) continue;
        $info = $arr[$k];
        if (isset($info['closed']) && $info['closed']) $out[] = "$th: ปิด";
        elseif (isset($info['start'], $info['end']))   $out[] = "$th: {$info['start']}–{$info['end']}";
    }
    return $out ? implode(', ', $out) : null;
}

// เตรียมเวลาทำการสำหรับแสดง
$displayHours = '';
if (!empty($tech['business_hours'])) {
    $displayHours = $tech['business_hours'];
} elseif (!empty($tech['business_hours_json'])) {
    $displayHours = hoursJsonToText($tech['business_hours_json']) ?: '';
}

// เคารพ flag สาธารณะ
$publicPhone = ($tech['phone_public'] && !empty($tech['phone'])) ? $tech['phone'] : '';
$publicLine  = ($tech['line_id_public'] && !empty($tech['line_id'])) ? $tech['line_id'] : '';
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="utf-8">
    <title>โปรไฟล์ช่าง | <?= htmlspecialchars($tech['name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun&display=swap" rel="stylesheet">
    <link href="css/style.css?v=1" rel="stylesheet">
    <style>
        .wrap {
            max-width: 980px;
            margin: auto
        }

        .cover {
            width: 100%;
            height: 220px;
            object-fit: cover;
            border-radius: 12px
        }

        .avatar {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border-radius: 50%;
            border: 3px solid #fff;
            margin-top: -60px
        }
    </style>
</head>

<body class="py-4">
<nav class="navbar navbar-expand-lg">
    <div class="container d-flex justify-content-between align-items-center">
        <div>
            <a class="navbar-brand fw-bold text-white" href="index.php">ThunderFix</a>
            <span class="navbar-text text-white">ค้นหาช่างทันใจ</span>
        </div>
        <?php if (isset($_SESSION['user_id'])): ?>
            <a href="logout.php" class="btn btn-sm btn-light">ออกจากระบบ</a>
        <?php endif; ?>
    </div>
</nav>
    <div class="container mt-5">
        <div class="d-flex align-items-center gap-3 px-2">
            <img class="avatar"
                src="uploads/profile_images/<?= htmlspecialchars($tech['profile_image'] ?: 'default_profile.png') ?>"
                alt="avatar" onerror="this.src='assets/no-avatar.png'">
            <div class="mt-3">
                <h3 class="mb-1"><?= htmlspecialchars($tech['name']) ?></h3>
                <div class="text-muted">
                    ประเภทงานช่าง:
                    <span class="badge text-bg-secondary"><?= htmlspecialchars($tech['tech_type'] ?: 'ไม่ระบุ') ?></span>
                </div>
            </div>
            <div class="ms-auto mt-3 d-flex gap-2">
                <?php if (($_SESSION['role'] ?? '') === 'user'): ?>
                    <a class="btn btn-primary" href="create_or_get_conversation.php?technician_id=<?= $tid ?>">เริ่มแชท</a>
                    <a class="btn btn-outline-primary" href="reviews.php?tid=<?= $tid ?>">ให้คะแนน/รีวิว</a>
                <?php endif; ?>
                <a class="btn btn-outline-secondary" href="dashboard.php">← กลับไปค้นหา</a>
            </div>
        </div>
    </div>

    <!-- รายละเอียด -->
    <div class="card p-3 mb-3">
        <h5 class="border-bottom pb-2 mb-3">ข้อมูลช่าง</h5>

        <?php if (!empty($tech['bio'])): ?>
            <p><strong>เกี่ยวกับช่าง:</strong> <?= nl2br(htmlspecialchars($tech['bio'])) ?></p>
        <?php endif; ?>

        <p><strong>ประสบการณ์:</strong> <?= (int)$tech['years_experience'] ?> ปี</p>
        <p><strong>ราคาเริ่มต้น:</strong> <?= number_format((int)$tech['min_price']) ?> ฿</p>
        <p><strong>ค่าบริการด่วน:</strong> <?= number_format((int)$tech['emergency_fee']) ?> ฿</p>

        <?php if (!empty($tech['languages'])): ?>
            <p><strong>ภาษา:</strong> <?= htmlspecialchars($tech['languages']) ?></p>
        <?php endif; ?>

        <?php if ($publicPhone): ?>
            <p><strong>เบอร์โทร:</strong> <?= htmlspecialchars($publicPhone) ?></p>
        <?php endif; ?>
        <?php if ($publicLine): ?>
            <p><strong>Line ID:</strong> <?= htmlspecialchars($publicLine) ?></p>
        <?php endif; ?>

        <?php if (!empty($tech['service_radius_km'])): ?>
            <p><strong>พื้นที่ให้บริการ:</strong> รัศมี <?= htmlspecialchars($tech['service_radius_km']) ?> กม.</p>
        <?php endif; ?>

        <p><strong>เวลาทำการ:</strong> <?= $displayHours ? htmlspecialchars($displayHours) : 'ยังไม่ระบุ' ?></p>
    </div>

    <!-- (ทางเลือก) บล็อกรีวิวลูกค้าในอนาคต -->
    <div class="card p-3">
        <h5 class="border-bottom pb-2 mb-3">รีวิวจากลูกค้า</h5>
        <p class="text-muted mb-0">ยังไม่มีรีวิวสำหรับช่างคนนี้</p>
    </div>
    </div>
</body>

</html>