<?php
session_start();
include('db/db.php');
include('csrf.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? '';

if (!isset($_GET['conversation_id'])) {
    echo "ไม่พบการสนทนา";
    exit;
}

$conversation_id = intval($_GET['conversation_id']);

// ตรวจสอบสิทธิ์เข้าถึง conversation (user ต้องเป็นเจ้าของ conversation)
$sql = "SELECT * FROM conversations WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $conversation_id);
$stmt->execute();
$conv = $stmt->get_result()->fetch_assoc();

if (!$conv) {
    echo "ไม่พบการสนทนา";
    exit;
}

if ($role === 'technician') {
    if ($conv['technician_id'] != $user_id) {
        echo "คุณไม่มีสิทธิ์เข้าถึงแชทนี้";
        exit;
    }
} else {
    if ($conv['user_id'] != $user_id) {
        echo "คุณไม่มีสิทธิ์เข้าถึงแชทนี้";
        exit;
    }
}

// ดึงชื่อ/รูปของ "อีกฝ่าย" มาโชว์บนแถบหัวข้อและข้าง bubble ข้อความ (แบบ LINE/Messenger)
if ($role === 'technician') {
    $stmtOther = $conn->prepare("SELECT name FROM users WHERE id=?");
    $stmtOther->bind_param("i", $conv['user_id']);
    $stmtOther->execute();
    $other = $stmtOther->get_result()->fetch_assoc();
    $otherName  = $other['name'] ?? 'ผู้ใช้';
    $otherImage = null; // ตาราง users ไม่มีคอลัมน์รูปโปรไฟล์
} else {
    $stmtOther = $conn->prepare("SELECT name, profile_image FROM technicians WHERE id=?");
    $stmtOther->bind_param("i", $conv['technician_id']);
    $stmtOther->execute();
    $other = $stmtOther->get_result()->fetch_assoc();
    $otherName  = $other['name'] ?? 'ช่าง';
    $otherImage = $other['profile_image'] ?? null;
}

// สร้าง <img>/วงกลมตัวอักษรย่อ ใช้ได้ทั้งแถบหัวข้อและ avatar ข้าง bubble
function chat_avatar_html($name, $imagePath, $size = 36)
{
    $name = trim((string)$name) !== '' ? $name : '?';
    if (!empty($imagePath)) {
        $src = 'uploads/profile_images/' . htmlspecialchars($imagePath, ENT_QUOTES, 'UTF-8');
        return '<img class="chat-avatar" style="width:' . $size . 'px;height:' . $size . 'px" src="' . $src . '" alt="avatar" onerror="this.src=\'assets/no-avatar.png\'">';
    }
    $initial = mb_substr($name, 0, 1, 'UTF-8');
    $palette = ['#2f6fed', '#e07a5f', '#3d9970', '#b5179e', '#f4a261', '#577590'];
    $idx = array_sum(array_map('ord', str_split($name))) % count($palette);
    $bg = $palette[$idx];
    return '<div class="chat-avatar chat-avatar-initial" style="width:' . $size . 'px;height:' . $size . 'px;background:' . $bg . '">'
        . htmlspecialchars($initial, ENT_QUOTES, 'UTF-8') . '</div>';
}

// ถ้ามีส่งข้อความใหม่
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    csrf_require();

    $message = trim($_POST['message']);
    if ($message !== '') {
        $sender = $role === 'technician' ? 'technician' : 'user';
        $sql = "INSERT INTO messages (conversation_id, sender, message, created_at) VALUES (?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iss", $conversation_id, $sender, $message);
        $stmt->execute();
        header("Location: chat.php?conversation_id=$conversation_id");
        exit;
    }
}

// ดึงข้อความทั้งหมดของ conversation
$sql = "SELECT * FROM messages WHERE conversation_id = ? ORDER BY created_at ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $conversation_id);
$stmt->execute();
$messages = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>แชทกับ <?php echo htmlspecialchars($otherName, ENT_QUOTES, 'UTF-8'); ?> | ThunderFix</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
<link href="https://fonts.googleapis.com/css2?family=Sarabun&display=swap" rel="stylesheet" />
<link href="css/style.css?v=1" rel="stylesheet" />
<style>
  .chat-container {
    max-width: 700px;
    margin: 20px auto;
    border: 1px solid #ddd;
    border-radius: 12px;
    padding: 0;
    height: 75vh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    background: #fff;
  }
  .chat-header {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 15px;
    border-bottom: 1px solid #eee;
    background: #fafafa;
    flex-shrink: 0;
  }
  .chat-header-name {
    font-weight: 700;
  }
  .chat-avatar {
    border-radius: 50%;
    object-fit: cover;
    flex-shrink: 0;
  }
  .chat-avatar-initial {
    border-radius: 50%;
    color: #fff;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
  }
  .messages {
    flex: 1;
    overflow-y: auto;
    padding: 15px;
    display: flex;
    flex-direction: column;
  }
  .message-row {
    display: flex;
    align-items: flex-end;
    gap: 6px;
    margin-bottom: 4px;
  }
  .message-row.mine {
    justify-content: flex-end;
  }
  .message-row.theirs {
    justify-content: flex-start;
  }
  .msg-avatar-slot {
    width: 28px;
    flex-shrink: 0;
  }
  .message {
    margin-bottom: 0;
    padding: 10px 15px;
    border-radius: 15px;
    max-width: 75%;
    word-wrap: break-word;
  }
  .message.user {
    background-color: #5f00ba;
    color: white;
  }
  .message.technician {
    background-color: #c700c7;
    color: white;
  }
  form {
    display: flex;
    gap: 10px;
    padding: 15px;
    border-top: 1px solid #eee;
    flex-shrink: 0;
  }
  textarea {
    flex: 1;
    resize: none;
    padding: 10px;
    border-radius: 10px;
    border: 1px solid #ccc;
    font-family: 'Sarabun', sans-serif;
  }
  button {
    background: linear-gradient(to right, #5f00ba, #c700c7);
    color: white;
    border: none;
    border-radius: 10px;
    padding: 10px 15px;
    cursor: pointer;
  }
  button:hover {
    background: linear-gradient(to right, #400088, #990099);
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

<div class="chat-container">
  <div class="chat-header">
    <?php echo chat_avatar_html($otherName, $otherImage, 36); ?>
    <div class="chat-header-name"><?php echo htmlspecialchars($otherName, ENT_QUOTES, 'UTF-8'); ?></div>
  </div>

  <div class="messages" id="messages">
    <?php
    $myRole = $role === 'technician' ? 'technician' : 'user';
    $prevSender = null;
    while ($msg = $messages->fetch_assoc()):
        $isMine = $msg['sender'] === $myRole;
        $showAvatar = !$isMine && $msg['sender'] !== $prevSender;
        $prevSender = $msg['sender'];
    ?>
      <div class="message-row <?php echo $isMine ? 'mine' : 'theirs'; ?>">
        <?php if (!$isMine): ?>
          <div class="msg-avatar-slot">
            <?php if ($showAvatar) echo chat_avatar_html($otherName, $otherImage, 28); ?>
          </div>
        <?php endif; ?>
        <div class="message <?php echo htmlspecialchars($msg['sender'], ENT_QUOTES, 'UTF-8'); ?>">
          <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($msg['created_at'])); ?></small><br>
          <?php echo nl2br(htmlspecialchars($msg['message'], ENT_QUOTES, 'UTF-8')); ?>
        </div>
      </div>
    <?php endwhile; ?>
  </div>

  <form method="POST">
    <?php echo csrf_field(); ?>
    <textarea name="message" rows="2" placeholder="พิมพ์ข้อความ..." required></textarea>
    <button type="submit">ส่ง</button>
  </form>
</div>

<script>
// เลื่อนแชทลงล่างสุดอัตโนมัติ
const messagesDiv = document.getElementById('messages');
messagesDiv.scrollTop = messagesDiv.scrollHeight;
</script>

</body>
</html>
