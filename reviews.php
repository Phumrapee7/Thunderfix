<?php
session_start();
include('db/db.php');
include('csrf.php');

// ถ้าไม่ได้ล็อกอินให้เด้งไปหน้า index
if(!isset($_SESSION['user_id'])){
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$tid     = $_GET['tid'];  // technician id

// ดึงข้อมูลช่าง
$stmt_t = $conn->prepare("SELECT * FROM technicians WHERE id=?");
$stmt_t->bind_param("i",$tid);
$stmt_t->execute();
$tech = $stmt_t->get_result()->fetch_assoc();

// ถ้ามีการ post รีวิว
if($_SERVER['REQUEST_METHOD']=='POST'){
    csrf_require();

    $rating  = $_POST['rating'];
    $comment = $_POST['comment'];

    $sql = $conn->prepare("INSERT INTO reviews(user_id,technician_id,rating,comment) VALUES(?,?,?,?)");
    $sql->bind_param("iiis",$user_id,$tid,$rating,$comment);
    $sql->execute();
    header("Location: reviews.php?tid=$tid");
    exit;
}

// ดึงรีวิวทั้งหมด
$stmt_r = $conn->prepare("SELECT r.*, u.name FROM reviews r JOIN users u ON r.user_id=u.id WHERE technician_id=? ORDER BY created_at DESC");
$stmt_r->bind_param("i",$tid);
$stmt_r->execute();
$reviews = $stmt_r->get_result();
?>

<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>รีวิวช่าง | ThunderFix</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Sarabun&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css?v=1">
</head>
<body class="bg-light">
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

<div class="container mt-4">
    <a href="dashboard.php" class="btn btn-secondary mb-2">← กลับ</a>
    <div class="card mb-4">
        <div class="card-body">
            <h3><?php echo htmlspecialchars($tech['name'], ENT_QUOTES, 'UTF-8'); ?></h3>
            <p>ประเภท: <?php echo htmlspecialchars($tech['tech_type'] ?: 'ไม่ระบุ', ENT_QUOTES, 'UTF-8'); ?> | เบอร์: <?php echo htmlspecialchars($tech['phone'], ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <h5>ให้คะแนน/รีวิว</h5>
            <form method="POST">
                <?php echo csrf_field(); ?>
                <div class="mb-2">
                    <label>คะแนน (1-5)</label>
                    <select name="rating" class="form-control" required>
                        <option value="5">5</option>
                        <option value="4">4</option>
                        <option value="3">3</option>
                        <option value="2">2</option>
                        <option value="1">1</option>
                    </select>
                </div>
                <div class="mb-2">
                    <label>ความคิดเห็น</label>
                    <textarea name="comment" class="form-control" required></textarea>
                </div>
                <button type="submit" class="btn btn-primary">ส่งรีวิว</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <h5>รีวิวทั้งหมด</h5>
            <?php while($row = $reviews->fetch_assoc()): ?>
                <div class="border-bottom py-2">
                    <strong><?php echo htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                    <span class="badge bg-warning text-dark"><?php echo (int)$row['rating']; ?>/5</span>
                    <p><?php echo nl2br(htmlspecialchars($row['comment'], ENT_QUOTES, 'UTF-8')); ?></p>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
</div>
</body>
</html>
