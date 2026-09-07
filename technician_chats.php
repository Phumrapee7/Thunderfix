<?php
session_start();
include('db/db.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'technician') {
    die("ไม่มีสิทธิ์เข้าถึง");
}
$tech_id = $_SESSION['user_id'];

// conversations ไม่มี last_message_at ในตัว เลยหาเวลาข้อความล่าสุดจาก messages แทน
// (COALESCE กันกรณีที่ยังไม่มีใครทักเลย ให้ใช้เวลาสร้าง conversation แทน)
$stmt = $conn->prepare("SELECT c.id, u.name AS user_name,
        COALESCE(MAX(m.created_at), c.created_at) AS last_message_at
    FROM conversations c
    JOIN users u ON c.user_id = u.id
    LEFT JOIN messages m ON m.conversation_id = c.id
    WHERE c.technician_id = ?
    GROUP BY c.id, u.name, c.created_at
    ORDER BY last_message_at DESC");
$stmt->bind_param("i", $tech_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8" />
<title>รายชื่อแชท</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Sarabun&display=swap" rel="stylesheet">
<link href="css/style.css?v=1" rel="stylesheet">
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
<h2 class="mb-3">รายชื่อแชทของคุณ</h2>
<ul class="list-group">
<?php while ($row = $result->fetch_assoc()): ?>
    <li class="list-group-item">
        <a href="chat.php?conversation_id=<?php echo $row['id']; ?>">
            แชทกับ: <?php echo htmlspecialchars($row['user_name']); ?>
            (ล่าสุด: <?php echo date('d/m/Y H:i', strtotime($row['last_message_at'])); ?>)
        </a>
    </li>
<?php endwhile; ?>
</ul>
</div>
</body>
</html>
