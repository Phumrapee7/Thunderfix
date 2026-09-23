<?php
session_start();
include('db/db.php');
include('csrf.php');

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  csrf_require();

  $name = $_POST['name'];
  $email = $_POST['email'];
  $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
  $phone = $_POST['phone'];
  $tech_type = $_POST['tech_type'];
  $lat = $_POST['lat'];
  $lng = $_POST['lng'];
  $password_raw = $_POST['password'];

  if (strlen($password_raw) < 8) {
    $error = "รหัสผ่านต้องมีอย่างน้อย 8 ตัว";
  } elseif (!preg_match('/^[0-9]{10}$/', $phone)) {
    $error = "เบอร์โทรต้องมี 10 หลัก";
  } elseif (empty($lat) || empty($lng)) {
  } else {
    $password = password_hash($password_raw, PASSWORD_DEFAULT);



    if (empty($lat) || empty($lng)) {
      $error = "กรุณาอนุญาตให้ระบบเข้าถึงตำแหน่ง GPS ของคุณ";
    } else {
      $check = $conn->prepare("SELECT id FROM technicians WHERE email=?");
      $check->bind_param("s", $email);
      $check->execute();
      $rs = $check->get_result();
      if ($rs->num_rows > 0) {
        $error = "อีเมลนี้มีช่างใช้แล้ว!";
      } else {
        $latitude = (string)(float)$lat;
        $longitude = (string)(float)$lng;
        $sql = $conn->prepare("INSERT INTO technicians(name,email,password,phone,tech_type,latitude,longitude)
                                   VALUES (?,?,?,?,?,?,?)");
        $sql->bind_param("sssssss", $name, $email, $password, $phone, $tech_type, $latitude, $longitude);
        if ($sql->execute()) {
          $success = "สมัครเป็นช่างสำเร็จ! กรุณาเข้าสู่ระบบ.";
        } else {
          $error = "ไม่สามารถสมัครได้ ลองใหม่อีกครั้ง!";
        }
      }
    }
  }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>สมัครเป็นช่าง | ThunderFix</title>

  <!-- โหลดฟอนต์และ CSS ภายนอก -->
  <link href="https://fonts.googleapis.com/css2?family=Sarabun&display=swap" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="css/style.css?v=1" rel="stylesheet" />

  <script>
    // ขอพิกัด GPS และใส่ใน input hidden
    function getLocation() {
      if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function (position) {
          document.getElementById('lat').value = position.coords.latitude;
          document.getElementById('lng').value = position.coords.longitude;
        }, function (error) {
          alert("ไม่สามารถเข้าถึงตำแหน่ง GPS ของคุณได้ กรุณาอนุญาตการใช้งาน");
        });
      } else {
        alert("เบราว์เซอร์ไม่รองรับการระบุตำแหน่ง GPS");
      }
    }
    window.onload = getLocation;

    function validateLocation() {
      const lat = document.getElementById('lat').value;
      const lng = document.getElementById('lng').value;
      if (!lat || !lng) {
        alert("กรุณาอนุญาตให้ระบบเข้าถึงตำแหน่ง GPS ของคุณ");
        return false;
      }
      return true;
    }
  </script>
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
  <div class="centered-container">
    <form method="POST" onsubmit="return validateLocation();" class="login-card">
      <?php echo csrf_field(); ?>
      <h1>สมัครเป็นช่าง</h1>

      <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
      <?php endif; ?>
      <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
      <?php endif; ?>

      <div style="position: relative;">
        <span class="form-icon">👤</span>
        <input type="text" name="name" placeholder="ชื่อ-นามสกุล" class="form-control" required>
      </div>

      <div style="position: relative;">
        <span class="form-icon">📧</span>
        <input type="email" name="email" placeholder="อีเมล" class="form-control" required>
      </div>

      <div style="position: relative;">
        <span class="form-icon">🔒</span>
        <input type="password" name="password" placeholder="รหัสผ่าน" class="form-control" required>
      </div>

      <div style="position: relative;">
        <span class="form-icon">📞</span>
        <input type="text" name="phone" placeholder="เบอร์โทร" class="form-control" required>
      </div>

      <div style="position: relative;">
        <span class="form-icon">🛠</span>
        <select name="tech_type" class="form-control" required>
          <option value="ไฟฟ้า">ไฟฟ้า</option>
          <option value="ประปา">ประปา</option>
          <option value="แอร์">แอร์</option>
          <option value="ช่างทั่วไป">ช่างทั่วไป</option>
          <option value="บิ้วอิน/เฟอร์นิเจอร์">บิ้วอิน/เฟอร์นิเจอร์</option>
          <option value="สี/ทาสี">สี/ทาสี</option>
          <option value="หลังคา/กันรั่ว">หลังคา/กันรั่ว</option>
          <option value="อื่นๆ">อื่นๆ</option>
        </select>
      </div>

      <input type="hidden" name="lat" id="lat" required>
      <input type="hidden" name="lng" id="lng" required>

      <button type="submit">สมัครเป็นช่าง</button>

      <a href="index.php">← กลับหน้าเข้าสู่ระบบ</a>
    </form>
  </div>

</body>

</html>