<?php
session_start();
include('db/db.php');

/* รับพารามิเตอร์ค้นหา */
$q       = trim($_GET['q']   ?? '');
$radius  = isset($_GET['radius']) ? floatval($_GET['radius']) : 0.0;  // กม.
$lat     = isset($_GET['lat'])    ? floatval($_GET['lat'])    : null;
$lng     = isset($_GET['lng'])    ? floatval($_GET['lng'])    : null;

$haveGeo = ($lat !== null && $lng !== null);

/* สร้าง SQL:
   - ถ้ามี lat/lng: คำนวณ distance ด้วย Haversine (หน่วยกม.)
   - ถ้าไม่มี: distance = NULL
*/
if ($haveGeo) {
    $select = "SELECT id, name, tech_type, phone, profile_image, years_experience, min_price,
        latitude, longitude, service_radius_km,
        (6371 * ACOS(
            COS(RADIANS(?)) * COS(RADIANS(latitude)) *
            COS(RADIANS(longitude) - RADIANS(?)) +
            SIN(RADIANS(?)) * SIN(RADIANS(latitude))
        )) AS distance
        FROM technicians";
} else {
    $select = "SELECT id, name, tech_type, phone, profile_image, years_experience, min_price,
        latitude, longitude, service_radius_km,
        NULL AS distance
        FROM technicians";
}

$where  = [];
$params = [];
$types  = "";

/* bind lat/lng ถ้ามี */
if ($haveGeo) {
    $params[] = $lat;
    $params[] = $lng;
    $params[] = $lat;
    $types   .= "ddd";
}

/* กรองด้วยชื่อ */
if ($q !== '') {
    $where[]  = "name LIKE ?";
    $params[] = "%{$q}%";
    $types   .= "s";
}

$sql = $select;
if ($where) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

/* ถ้ามีรัศมี + พิกัด: ใช้ HAVING distance <= radius
   (และเคารพรัศมีบริการของช่างถ้ามี: distance <= service_radius_km)
*/
if ($haveGeo && $radius > 0) {
    $sql .= " HAVING distance <= ? AND (service_radius_km IS NULL OR distance <= service_radius_km)";
    $params[] = $radius;
    $types   .= "d";
}

/* จัดเรียงผลลัพธ์ */
if ($haveGeo) {
    $sql .= " ORDER BY distance ASC, name ASC";
} else {
    $sql .= " ORDER BY name ASC";
}

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="utf-8">
    <title>ค้นหาช่าง | ThunderFix</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun&display=swap" rel="stylesheet">
    <link href="css/style.css?v=1" rel="stylesheet">
    <style>
        .avatar {
            width: 56px;
            height: 56px;
            object-fit: cover;
            border-radius: 50%
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

    <!-- ฟอร์มค้นหา: ชื่อ + รัศมี + ใช้ตำแหน่งของฉัน -->
    <form method="GET" class="row g-2 mb-3" role="search" id="searchForm">
        <div class="col-md-4">
            <input class="form-control" type="search" name="q" placeholder="พิมพ์ชื่อช่าง..."
                value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="col-md-2">
            <input class="form-control" type="number" step="0.1" min="0" name="radius" placeholder="รัศมี (กม.)"
                value="<?= htmlspecialchars($_GET['radius'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="col-md-2">
            <input class="form-control" type="text" name="lat" id="lat" placeholder="ละติจูด"
                value="<?= htmlspecialchars($_GET['lat'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="col-md-2">
            <input class="form-control" type="text" name="lng" id="lng" placeholder="ลองจิจูด"
                value="<?= htmlspecialchars($_GET['lng'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button class="btn btn-outline-primary flex-grow-1" type="submit">ค้นหา</button>
            <button class="btn btn-secondary" type="button" id="useMyLocation">ใช้ตำแหน่งของฉัน</button>
        </div>
        <a href="logout.php" class="btn btn-secondary mt-4">ออกจากระบบ</a>
    </form>

    <?php if ($q !== '' || ($haveGeo && $radius > 0)): ?>
        <p class="text-muted">
            <?php if ($q !== ''): ?>คำค้น: <strong><?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?></strong> • <?php endif; ?>
        <?php if ($haveGeo): ?>
            ตำแหน่ง: <?= htmlspecialchars($lat) ?>, <?= htmlspecialchars($lng) ?>
            <?php if ($radius > 0): ?> • รัศมี: <?= htmlspecialchars($radius) ?> กม.<?php endif; ?>
            <?php endif; ?>
        </p>
    <?php endif; ?>

    <?php if ($result->num_rows === 0): ?>
        <div class="alert alert-info">ไม่พบช่างตามเงื่อนไข</div>
    <?php else: ?>
        <?php while ($row = $result->fetch_assoc()): ?>
            <div class="card mb-2 p-2">
                <div class="d-flex align-items-center gap-3">
                    <img class="avatar"
                        src="uploads/profile_images/<?= htmlspecialchars($row['profile_image'] ?: 'default_profile.png') ?>"
                        alt="avatar" onerror="this.src='assets/no-avatar.png'">
                    <div class="flex-grow-1">
                        <div class="d-flex align-items-center gap-2">
                            <h6 class="mb-0"><?= htmlspecialchars($row['name']) ?></h6>
                            <?php if (!empty($row['tech_type'])): ?>
                                <span class="badge text-bg-secondary"><?= htmlspecialchars($row['tech_type']) ?></span>
                            <?php endif; ?>
                        </div>
                        <small class="text-muted">
                            ประสบการณ์ <?= (int)$row['years_experience'] ?> ปี •
                            ราคาเริ่มต้น <?= number_format((int)$row['min_price']) ?> ฿
                            <?php if ($row['distance'] !== null): ?>
                                • ห่าง <?= number_format((float)$row['distance'], 2) ?> กม.
                            <?php endif; ?>
                        </small>
                    </div>
                    <a class="btn btn-sm btn-primary"
                        href="technician_view.php?id=<?= (int)$row['id'] ?>">ดูโปรไฟล์</a>
                </div>
            </div>
        <?php endwhile; ?>
    <?php endif; ?>

    <script>
        // ปุ่ม "ใช้ตำแหน่งของฉัน"
        document.getElementById('useMyLocation').addEventListener('click', () => {
            if (!navigator.geolocation) {
                alert('เบราว์เซอร์ของคุณไม่รองรับการระบุตำแหน่ง');
                return;
            }
            navigator.geolocation.getCurrentPosition(
                pos => {
                    const {
                        latitude,
                        longitude
                    } = pos.coords;
                    document.getElementById('lat').value = latitude.toFixed(6);
                    document.getElementById('lng').value = longitude.toFixed(6);

                    // ถ้ายังไม่ใส่รัศมี ให้ใส่ค่าแนะนำ (เช่น 10 กม.)
                    const radiusInput = document.querySelector('input[name="radius"]');
                    if (!radiusInput.value) radiusInput.value = 10;

                    document.getElementById('searchForm').submit();
                },
                err => {
                    let msg = 'ไม่สามารถดึงตำแหน่งได้';
                    if (err.code === 1) msg = 'คุณปฏิเสธการขอตำแหน่ง (กรุณาอนุญาตการระบุตำแหน่ง)';
                    alert(msg);
                }, {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 0
                }
            );
        });
    </script>
</div>
</body>

</html>