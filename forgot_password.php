<?php
session_start();
include('db/db.php');
include('csrf.php');

const RESET_TOKEN_TTL_SECONDS = 1800; // 30 นาที

$submitted = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    $email = trim($_POST['email'] ?? '');
    $submitted = true;

    if ($email !== '') {
        // หาบัญชีจาก technicians ก่อน แล้วค่อย users (เหมือน login.php)
        $accountType = null;
        $accountId   = null;

        $stmt = $conn->prepare("SELECT id FROM technicians WHERE email=? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        if ($row = $stmt->get_result()->fetch_assoc()) {
            $accountType = 'technician';
            $accountId   = (int)$row['id'];
        } else {
            $stmt = $conn->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            if ($row = $stmt->get_result()->fetch_assoc()) {
                $accountType = 'user';
                $accountId   = (int)$row['id'];
            }
        }

        // ทำเฉพาะตอนเจอบัญชีจริง แต่ข้อความที่ผู้ใช้เห็นจะเหมือนกันทุกกรณี
        // (กันคนสุ่มอีเมลมาเช็คว่าใครมีบัญชีในระบบบ้าง)
        if ($accountType !== null) {
            // ยกเลิก token เก่าที่ยังไม่ถูกใช้ของบัญชีนี้ ให้เหลือ token ที่ใช้ได้อันเดียว
            $invalidate = $conn->prepare(
                "UPDATE password_resets SET used_at = NOW() WHERE account_type=? AND account_id=? AND used_at IS NULL"
            );
            $invalidate->bind_param("si", $accountType, $accountId);
            $invalidate->execute();

            $token     = bin2hex(random_bytes(32)); // 256-bit, ส่งให้ผู้ใช้ทางลิงก์เท่านั้น
            $tokenHash = hash('sha256', $token);     // เก็บแค่ hash ลง DB

            // คำนวณ expires_at ด้วย NOW() ของ MySQL เอง (ไม่ใช้ PHP time()/date())
            // เพราะ PHP กับ MySQL อาจตั้ง timezone ไม่ตรงกัน ถ้าคำนวณฝั่ง PHP แล้วเทียบกับ
            // NOW() ฝั่ง MySQL ตอน validate อาจทำให้ token หมดอายุทันทีที่สร้าง (นาฬิกาคนละอัน)
            $insert = $conn->prepare(
                "INSERT INTO password_resets (account_type, account_id, token_hash, expires_at)
                 VALUES (?, ?, ?, NOW() + INTERVAL " . (int)(RESET_TOKEN_TTL_SECONDS / 60) . " MINUTE)"
            );
            $insert->bind_param("sis", $accountType, $accountId, $tokenHash);
            $insert->execute();

            $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
            $resetLink = "http://{$host}{$basePath}/reset_password.php?token={$token}";

            // ยังไม่มีระบบส่งอีเมลจริงในโปรเจกต์นี้ (ไม่มี PHPMailer, php.ini ก็ไม่ได้ตั้ง sendmail/SMTP)
            // เลย mock ด้วยการ log ลิงก์ไว้ฝั่ง server แทน — ห้ามส่งค่านี้กลับไปในหน้าเว็บเด็ดขาด
            $logLine = sprintf("[%s] password reset for %s (%s #%d): %s\n", date('c'), $email, $accountType, $accountId, $resetLink);
            file_put_contents(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'thunderfix_password_resets.log', $logLine, FILE_APPEND);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8" />
  <title>ลืมรหัสผ่าน</title>
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
  <div class="card card-wrap p-4 shadow-sm">
    <h3 class="mb-3 text-center">ลืมรหัสผ่าน</h3>

    <?php if ($submitted): ?>
      <div class="alert alert-success">
        ถ้าอีเมลนี้มีอยู่ในระบบ เราได้ส่งลิงก์สำหรับตั้งรหัสผ่านใหม่ไปให้แล้ว
        กรุณาตรวจสอบอีเมลของคุณ (ลิงก์จะหมดอายุใน 30 นาที)
      </div>
      <a class="btn btn-primary w-100" href="index.php">กลับไปเข้าสู่ระบบ</a>
    <?php else: ?>
      <p class="text-muted text-center">กรอกอีเมลที่ใช้สมัคร เราจะส่งลิงก์สำหรับตั้งรหัสผ่านใหม่ให้</p>

      <form method="POST" novalidate>
        <?php echo csrf_field(); ?>
        <div class="mb-3" style="position:relative;">
          <span class="form-icon">📧</span>
          <input type="email" name="email" class="form-control with-icon" placeholder="อีเมล" required>
        </div>

        <button type="submit" class="btn btn-primary w-100">ส่งลิงก์รีเซ็ตรหัสผ่าน</button>
        <div class="text-center mt-3"><a href="index.php">กลับไปเข้าสู่ระบบ</a></div>
      </form>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
