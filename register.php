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
  $password_raw = $_POST['password'];

  if (strlen($password_raw) < 8) {
    $error = "รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร!";
  } else {
    $password = password_hash($password_raw, PASSWORD_DEFAULT);

    // ตรวจซ้ำอีเมล
    $check = $conn->prepare("SELECT id FROM users WHERE email=?");
    $check->bind_param("s", $email);
    $check->execute();
    $checkResult = $check->get_result();

    if ($checkResult->num_rows > 0) {
      $error = "อีเมลนี้ถูกใช้งานแล้ว!";
    } else {
      // insert
      $sql = $conn->prepare("INSERT INTO users(name,email,password) VALUES(?,?,?)");
      $sql->bind_param("sss", $name, $email, $password);
      if ($sql->execute()) {
        $success = "สมัครสมาชิกสำเร็จ! กรุณาเข้าสู่ระบบ.";
      } else {
        $error = "เกิดข้อผิดพลาดในการสมัครสมาชิก";
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
  <title>สมัครสมาชิก | ThunderFix</title>
  <link href="https://fonts.googleapis.com/css2?family=Sarabun&display=swap" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="css/style.css?v=1" rel="stylesheet" />
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
    <form method="POST" class="login-card">
      <?php echo csrf_field(); ?>
      <h1>สมัครสมาชิกผู้ใช้ทั่วไป</h1>

      <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
      <?php endif; ?>

      <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
      <?php endif; ?>

      <div style="position: relative;">
        <span class="form-icon">👤</span>
        <input type="text " name="name" placeholder="ชื่อ-นามสกุล" class="form-control" required>
      </div>

      <div style="position: relative;">
        <span class="form-icon">📧</span>
        <input type="email" name="email" placeholder="อีเมล" class="form-control" required>
      </div>

      <div style="position: relative;">
        <span class="form-icon">🔒</span>
        <input type="password" name="password" placeholder="รหัสผ่าน" class="form-control" required>
      </div>

      <button type="submit">สมัครสมาชิก</button>

      <a href="add_technician.php">สมัครเป็นช่าง</a>
      <a href="index.php">← กลับไปหน้าหลัก</a>
    </form>
  </div>


</body>

</html>