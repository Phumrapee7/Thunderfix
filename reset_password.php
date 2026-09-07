<?php
session_start();
include('db/db.php');
include('csrf.php');

function find_valid_reset(mysqli $conn, string $token): ?array
{
    if ($token === '') return null;
    $tokenHash = hash('sha256', $token);
    $stmt = $conn->prepare(
        "SELECT id, account_type, account_id FROM password_resets
         WHERE token_hash=? AND used_at IS NULL AND expires_at > NOW() LIMIT 1"
    );
    $stmt->bind_param("s", $tokenHash);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ?: null;
}

$token   = $_GET['token'] ?? $_POST['token'] ?? '';
$reset   = find_valid_reset($conn, $token);
$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    $new_password     = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (!$reset) {
        $error = 'ลิงก์นี้หมดอายุหรือถูกใช้ไปแล้ว กรุณาขอลิงก์ใหม่อีกครั้ง';
    } elseif ($new_password === '' || $confirm_password === '') {
        $error = 'กรุณากรอกข้อมูลให้ครบทุกช่อง';
    } elseif ($new_password !== $confirm_password) {
        $error = 'รหัสผ่านใหม่และยืนยันรหัสผ่านไม่ตรงกัน';
    } elseif (strlen($new_password) < 8) {
        $error = 'รหัสผ่านใหม่ควรมีอย่างน้อย 8 ตัวอักษร';
    } else {
        $table = $reset['account_type'] === 'technician' ? 'technicians' : 'users';
        $hash  = password_hash($new_password, PASSWORD_DEFAULT);

        $upd = $conn->prepare("UPDATE $table SET password=? WHERE id=?");
        $upd->bind_param("si", $hash, $reset['account_id']);
        $upd->execute();

        $mark = $conn->prepare("UPDATE password_resets SET used_at = NOW() WHERE id=?");
        $mark->bind_param("i", $reset['id']);
        $mark->execute();

        $success = 'ตั้งรหัสผ่านใหม่เรียบร้อย! กรุณาเข้าสู่ระบบด้วยรหัสผ่านใหม่';
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8" />
  <title>ตั้งรหัสผ่านใหม่</title>
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
    <h3 class="mb-3 text-center">ตั้งรหัสผ่านใหม่</h3>

    <?php if ($success): ?>
      <div class="alert alert-success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
      <a class="btn btn-primary w-100" href="index.php">กลับไปเข้าสู่ระบบ</a>

    <?php elseif (!$reset): ?>
      <div class="alert alert-danger">ลิงก์นี้ไม่ถูกต้อง หมดอายุ หรือถูกใช้ไปแล้ว</div>
      <a class="btn btn-primary w-100" href="forgot_password.php">ขอลิงก์ใหม่</a>

    <?php else: ?>
      <p class="text-muted text-center">กรอกรหัสผ่านใหม่ (อย่างน้อย 8 ตัวอักษร)</p>

      <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
      <?php endif; ?>

      <form method="POST" novalidate>
        <?php echo csrf_field(); ?>
        <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">

        <div class="mb-3" style="position:relative;">
          <span class="form-icon">🔒</span>
          <input type="password" id="new_password" name="new_password" class="form-control with-icon"
                 placeholder="รหัสผ่านใหม่ (อย่างน้อย 8 ตัวอักษร)" minlength="8" required>
          <button type="button" class="toggle-password-btn" data-target="new_password" title="ดู/ซ่อนรหัส">👁</button>
        </div>

        <div class="mb-3" style="position:relative;">
          <span class="form-icon">✅</span>
          <input type="password" id="confirm_password" name="confirm_password" class="form-control with-icon"
                 placeholder="ยืนยันรหัสผ่านใหม่" minlength="8" required>
          <button type="button" class="toggle-password-btn" data-target="confirm_password" title="ดู/ซ่อนรหัส">👁</button>
        </div>

        <button type="submit" class="btn btn-success w-100">บันทึกรหัสผ่านใหม่</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<script>
  document.querySelectorAll('.toggle-password-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const target = document.getElementById(btn.dataset.target);
      target.type = target.type === 'password' ? 'text' : 'password';
      btn.textContent = target.type === 'password' ? '👁' : '🙈';
      target.focus({preventScroll:true});
    });
  });
</script>
</body>
</html>
