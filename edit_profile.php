<?php
session_start();
include('db/db.php');
include('csrf.php');

// (แนะนำตอน dev) เปิด error ให้เห็นชัด
// mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'technician') {
    header("Location: index.php");
    exit;
}
$tech_id = (int)$_SESSION['user_id'];

// ดึงข้อมูลช่าง
$stmt = $conn->prepare("SELECT * FROM technicians WHERE id=? LIMIT 1");
$stmt->bind_param("i", $tech_id);
$stmt->execute();
$tech = $stmt->get_result()->fetch_assoc();
if (!$tech) {
    echo "ไม่พบข้อมูลช่าง";
    exit;
}

$error = '';
$success = '';

// ยูทิลอัปโหลดรูป
function handle_image_upload($fileKey, $subdir = 'profile_images')
{
    if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) return [null, null];

    $allowed_types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif'];
    $mime = mime_content_type($_FILES[$fileKey]['tmp_name']);
    if (!isset($allowed_types[$mime])) return [null, "ไฟล์ต้องเป็น JPG, PNG หรือ GIF เท่านั้น"];
    if ($_FILES[$fileKey]['size'] > 5 * 1024 * 1024) return [null, "ไฟล์รูปต้องไม่เกิน 5MB"];

    $ext = $allowed_types[$mime];
    $new_filename = uniqid($subdir . '_', true) . '.' . $ext;

    $base_dir = __DIR__ . '/uploads/' . $subdir . '/';
    if (!is_dir($base_dir)) {
        mkdir($base_dir, 0755, true);
    }

    if (!move_uploaded_file($_FILES[$fileKey]['tmp_name'], $base_dir . $new_filename)) {
        return [null, "เกิดข้อผิดพลาดในการอัปโหลดไฟล์"];
    }
    return [$new_filename, null];
}

// ประเภทช่างที่ให้เลือก (ปรับเพิ่ม/ลดได้ตามธุรกิจ)
$TECH_TYPES = [
    'ไฟฟ้า' => 'ไฟฟ้า',
    'ประปา' => 'ประปา',
    'แอร์' => 'แอร์',
    'ช่างทั่วไป' => 'ช่างทั่วไป',
    'บิ้วอิน/เฟอร์นิเจอร์' => 'บิ้วอิน/เฟอร์นิเจอร์',
    'สี/ทาสี' => 'สี/ทาสี',
    'หลังคา/กันรั่ว' => 'หลังคา/กันรั่ว',
    'อื่นๆ' => 'อื่นๆ',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    // ===== รับค่าจากฟอร์ม =====
    $name             = trim($_POST['name'] ?? '');
    $tech_type        = trim($_POST['tech_type'] ?? ''); // ✅ ประเภทช่าง
    $bio              = trim($_POST['bio'] ?? '');
    $years_experience = max(0, (int)($_POST['years_experience'] ?? 0));
    $min_price        = max(0, (int)($_POST['min_price'] ?? 0));
    $emergency_fee    = max(0, (int)($_POST['emergency_fee'] ?? 0));
    $phone            = trim($_POST['phone'] ?? '');
    $line_id          = trim($_POST['line_id'] ?? '');
    $phone_public     = isset($_POST['phone_public']) ? 1 : 0;
    $line_id_public   = isset($_POST['line_id_public']) ? 1 : 0;
    $languages        = trim($_POST['languages'] ?? '');
    $latitude         = ($_POST['latitude'] ?? '') === '' ? null : (string)(float)$_POST['latitude'];
    $longitude        = ($_POST['longitude'] ?? '') === '' ? null : (string)(float)$_POST['longitude'];
    $service_radius   = ($_POST['service_radius_km'] ?? '') === '' ? null : (string)(float)$_POST['service_radius_km'];

    // validate เบื้องต้น
    if ($name === '') {
        $error = 'กรุณากรอกชื่อช่าง/ชื่อร้าน';
    }

    // เวลาทำการ (JSON)
    $business_hours_clean = null;
    $business_hours_raw = trim($_POST['business_hours_json'] ?? '');
    if (!$error && $business_hours_raw !== '') {
        $decoded = json_decode($business_hours_raw, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $business_hours_clean = json_encode($decoded, JSON_UNESCAPED_UNICODE);
        } else {
            $error = "รูปแบบ JSON ของเวลาทำการไม่ถูกต้อง";
        }
    }

    // อัปโหลดรูป (ถ้ามี) — initialize ไว้ก่อนกัน undefined variable ตอนไม่มีไฟล์ใหม่
    $newProfile = null;
    $newCover   = null;
    if (!$error) {
        if (!empty($_FILES['profile_image']['name'])) {
            [$newProfile, $err1] = handle_image_upload('profile_image', 'profile_images');
            if ($err1) $error = $err1;
        }
        if (!$error && !empty($_FILES['cover_image']['name'])) {
            [$newCover, $err2] = handle_image_upload('cover_image', 'cover_images');
            if ($err2) $error = $err2;
        }
    }

    if (!$error) {
        // ===== UPDATE (เพิ่ม tech_type + COALESCE รูป) =====
        // ===== UPDATE (เพิ่ม tech_type + COALESCE รูป) =====
        $sql = "UPDATE technicians
        SET name=?,
            tech_type=?,
            bio=?,
            years_experience=?,
            min_price=?,
            emergency_fee=?,
            phone=?,
            line_id=?,
            phone_public=?,
            line_id_public=?,
            languages=?,
            latitude=?,
            longitude=?,
            service_radius_km=?,
            business_hours_json=?,
            profile_image=COALESCE(?, profile_image),
            cover_image=COALESCE(?, cover_image)
        WHERE id=?";

        $stmtU = $conn->prepare($sql);

        /*
ลำดับพารามิเตอร์ (18 ตัว):
 1  name                      s
 2  tech_type                 s
 3  bio                       s
 4  years_experience          i
 5  min_price                 i
 6  emergency_fee             i
 7  phone                     s
 8  line_id                   s
 9  phone_public              i
10  line_id_public            i
11  languages                 s
12  latitude                  s  (ส่งเป็นสตริงหรือ NULL เพื่อให้รองรับ NULL ง่าย)
13  longitude                 s  (เช่นเดียวกัน)
14  service_radius_km         s
15  business_hours_json       s
16  newProfile                s  (อาจเป็น NULL → COALESCE จะคงค่าเดิม)
17  newCover                  s  (อาจเป็น NULL)
18  tech_id                   i
*/
        $stmtU->bind_param(
            "sssiiissiisssssssi",
            $name,
            $tech_type,
            $bio,
            $years_experience,
            $min_price,
            $emergency_fee,
            $phone,
            $line_id,
            $phone_public,
            $line_id_public,
            $languages,
            $latitude,
            $longitude,
            $service_radius,
            $business_hours_clean,
            $newProfile,
            $newCover,
            $tech_id
        );

        if ($stmtU->execute()) {
            $success = "บันทึกข้อมูลโปรไฟล์สำเร็จ";
            // โหลดข้อมูลใหม่เพื่อแสดง
            $stmt = $conn->prepare("SELECT * FROM technicians WHERE id=? LIMIT 1");
            $stmt->bind_param("i", $tech_id);
            $stmt->execute();
            $tech = $stmt->get_result()->fetch_assoc();
        } else {
            $error = "เกิดข้อผิดพลาดในการบันทึกข้อมูล";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8" />
    <title>แก้ไขโปรไฟล์ช่าง</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Sarabun&display=swap" rel="stylesheet" />
    <link href="css/style.css?v=1" rel="stylesheet" />
    <style>
        .card-wrap {
            max-width: 900px;
            margin: auto
        }

        .avatar {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 50%
        }

        .cover {
            width: 100%;
            height: 160px;
            object-fit: cover;
            border-radius: 12px
        }

        .hint {
            font-size: .9rem;
            color: #6c757d
        }
    </style>
</head>

<body>
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
<div class="container py-4">
    <div class="card card-wrap p-3 shadow-sm">
        <h2 class="mb-3">แก้ไขโปรไฟล์ช่าง</h2>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="row g-3">
            <?php echo csrf_field(); ?>
            <!-- ชื่อ -->
            <div class="col-12">
                <label class="form-label">ชื่อช่าง / ชื่อร้าน</label>
                <input type="text" name="name" class="form-control" required
                    value="<?= htmlspecialchars($tech['name'] ?? '') ?>">
            </div>

            <!-- ประเภทช่าง -->
            <div class="col-md-6">
                <label class="form-label">ประเภทช่าง</label>
                <select name="tech_type" class="form-select" required>
                    <?php foreach ($TECH_TYPES as $k => $v): ?>
                        <option value="<?= htmlspecialchars($k) ?>"
                            <?= (isset($tech['tech_type']) && $tech['tech_type'] === $k) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($v) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="hint">ใช้เพื่อแสดงหมวดหลักของช่าง และใช้ในระบบค้นหา/ฟิลเตอร์</div>
            </div>
            <!-- รูปโปรไฟล์ -->
            <div class="col-12">
                <label class="form-label">รูปโปรไฟล์ (JPG/PNG/GIF ≤ 5MB)</label><br>
                <img class="avatar mb-2" src="uploads/profile_images/<?= htmlspecialchars($tech['profile_image'] ?: 'default_profile.png'); ?>" alt="avatar" onerror="this.src='assets/no-avatar.png'">
                <input type="file" name="profile_image" accept="image/*" class="form-control">
            </div>

            <!-- Bio -->
            <div class="col-12">
                <label class="form-label">เกี่ยวกับช่าง (Bio)</label>
                <textarea name="bio" class="form-control" rows="4" placeholder="เล่าความเชี่ยวชาญ อุปกรณ์ที่ถนัด ประสบการณ์ ฯลฯ"><?= htmlspecialchars($tech['bio'] ?? '') ?></textarea>
            </div>

            <div class="col-md-3">
                <label class="form-label">ประสบการณ์ (ปี)</label>
                <input type="number" min="0" name="years_experience" value="<?= (int)($tech['years_experience'] ?? 0) ?>" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label">ราคาเริ่มต้น (฿)</label>
                <input type="number" min="0" name="min_price" value="<?= (int)($tech['min_price'] ?? 0) ?>" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label">ค่าบริการด่วน (฿)</label>
                <input type="number" min="0" name="emergency_fee" value="<?= (int)($tech['emergency_fee'] ?? 0) ?>" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label">ภาษา (เช่น th,en)</label>
                <input type="text" name="languages" value="<?= htmlspecialchars($tech['languages'] ?? '') ?>" class="form-control">
            </div>

            <div class="col-md-4">
                <label class="form-label">เบอร์โทร (เก็บในระบบ)</label>
                <input type="text" name="phone" value="<?= htmlspecialchars($tech['phone'] ?? '') ?>" class="form-control">
                <div class="form-check mt-1">
                    <input class="form-check-input" type="checkbox" id="phone_public" name="phone_public" <?= !empty($tech['phone_public']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="phone_public">แสดงสาธารณะ</label>
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label">Line ID (เก็บในระบบ)</label>
                <input type="text" name="line_id" value="<?= htmlspecialchars($tech['line_id'] ?? '') ?>" class="form-control">
                <div class="form-check mt-1">
                    <input class="form-check-input" type="checkbox" id="line_id_public" name="line_id_public" <?= !empty($tech['line_id_public']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="line_id_public">แสดงสาธารณะ</label>
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label">รัศมีให้บริการ (กม.)</label>
                <input type="number" step="0.1" name="service_radius_km" value="<?= htmlspecialchars($tech['service_radius_km'] ?? '10.0') ?>" class="form-control">
            </div>

            <!-- เวลาทำการ -->
            <div class="col-12">
                <label class="form-label">เวลาทำการ</label>
                <div id="businessHoursEditor" class="border rounded p-3">
                    <?php
                    $DAY_LABELS = [
                        'mon' => 'จันทร์', 'tue' => 'อังคาร', 'wed' => 'พุธ', 'thu' => 'พฤหัสบดี',
                        'fri' => 'ศุกร์', 'sat' => 'เสาร์', 'sun' => 'อาทิตย์',
                    ];
                    $currentHours = [];
                    if (!empty($tech['business_hours_json'])) {
                        $decoded = json_decode($tech['business_hours_json'], true);
                        if (is_array($decoded)) $currentHours = $decoded;
                    }
                    foreach ($DAY_LABELS as $dayKey => $dayLabel):
                        $dayData = $currentHours[$dayKey] ?? null;
                        $isClosed = !empty($dayData['closed']);
                        $start = $dayData['start'] ?? '09:00';
                        $end   = $dayData['end'] ?? '18:00';
                    ?>
                    <div class="row align-items-center mb-2 business-hours-row" data-day="<?= htmlspecialchars($dayKey) ?>">
                        <div class="col-4 col-md-2"><?= htmlspecialchars($dayLabel) ?></div>
                        <div class="col-8 col-md-2">
                            <div class="form-check">
                                <input class="form-check-input day-closed" type="checkbox" <?= $isClosed ? 'checked' : '' ?>>
                                <label class="form-check-label">ปิด</label>
                            </div>
                        </div>
                        <div class="col-6 col-md-4">
                            <input type="time" class="form-control form-control-sm day-start"
                                value="<?= htmlspecialchars($start) ?>" <?= $isClosed ? 'disabled' : '' ?>>
                        </div>
                        <div class="col-6 col-md-4">
                            <input type="time" class="form-control form-control-sm day-end"
                                value="<?= htmlspecialchars($end) ?>" <?= $isClosed ? 'disabled' : '' ?>>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="business_hours_json" id="business_hours_json_input">
            </div>

            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-primary">บันทึกข้อมูล</button>
                <a href="technician_profile.php?id=<?= $tech_id; ?>" class="btn btn-outline-secondary">ดูหน้าโปรไฟล์</a>
            </div>
        </form>
    </div>
</div>

<script>
document.querySelectorAll('#businessHoursEditor .day-closed').forEach(function (cb) {
    cb.addEventListener('change', function () {
        var row = cb.closest('.business-hours-row');
        row.querySelector('.day-start').disabled = cb.checked;
        row.querySelector('.day-end').disabled = cb.checked;
    });
});

document.querySelector('form').addEventListener('submit', function () {
    var data = {};
    document.querySelectorAll('#businessHoursEditor .business-hours-row').forEach(function (row) {
        var day = row.dataset.day;
        var closed = row.querySelector('.day-closed').checked;
        if (closed) {
            data[day] = { closed: true };
            return;
        }
        var start = row.querySelector('.day-start').value;
        var end = row.querySelector('.day-end').value;
        if (start && end) {
            data[day] = { start: start, end: end };
        }
    });
    document.getElementById('business_hours_json_input').value = JSON.stringify(data);
});
</script>
</body>

</html>