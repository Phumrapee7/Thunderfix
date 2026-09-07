<!-- index.php -->
 <?php
session_start();
?>

<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>ThunderFix | หน้าหลัก</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Sarabun&display=swap" rel="stylesheet">
  <link href="css/style.css?v=1" rel="stylesheet">
</head>
<body class="bg-light">

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow">
  <div class="container d-flex justify-content-between align-items-center">
    <div>
      <a class="navbar-brand fw-bold" href="index.php">ThunderFix</a>
      <span class="navbar-text text-white">
        ค้นหาช่างทันใจ
      </span>
    </div>
    <?php if (isset($_SESSION['user_id'])): ?>
      <a href="logout.php" class="btn btn-sm btn-light">ออกจากระบบ</a>
    <?php endif; ?>
  </div>
</nav>

<!-- Content -->
<div class="container mt-5 text-center">
  <h1 class="mb-4">ยินดีต้อนรับสู่ ThunderFix</h1>
  <p>ระบบค้นหาช่างใกล้ตัว พร้อมรีวิว</p>
  <a href="login.php" class="btn btn-primary btn-lg m-2">เข้าสู่ระบบ</a>
  <a href="register.php" class="btn btn-outline-primary btn-lg m-2">สมัครสมาชิก</a>
</div>

</body>
</html>
