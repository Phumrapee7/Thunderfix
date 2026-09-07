<?php
session_start();
include('db/db.php');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit;
}

$user_id = $_SESSION['user_id'];
$role    = $_SESSION['role'] ?? '';

$conversation_id = intval($_GET['conversation_id'] ?? 0);
if ($conversation_id === 0) exit;

// ตรวจสอบสิทธิ์เข้าถึง conversation (เหมือน chat.php — user ต้องเป็นเจ้าของ conversation)
$stmt = $conn->prepare("SELECT * FROM conversations WHERE id = ?");
$stmt->bind_param("i", $conversation_id);
$stmt->execute();
$conv = $stmt->get_result()->fetch_assoc();

if (!$conv) {
    exit;
}

if ($role === 'technician') {
    if ($conv['technician_id'] != $user_id) {
        http_response_code(403);
        exit;
    }
} else {
    if ($conv['user_id'] != $user_id) {
        http_response_code(403);
        exit;
    }
}

$stmt = $conn->prepare("SELECT sender, message, created_at FROM messages WHERE conversation_id=? ORDER BY created_at ASC");
$stmt->bind_param("i", $conversation_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $bg = $row['sender'] === 'technician' ? '#4b3f99' : '#6a5acd';
    $sender_text = $row['sender'] === 'technician' ? 'ช่าง' : 'คุณ';

    echo "<div style='max-width:70%; margin-bottom:8px; background:$bg; color:white; padding:6px 12px; border-radius:12px; float:" . ($row['sender']==='technician'?'left':'right') . "; clear:both;'>";
    echo "<small><strong>$sender_text</strong></small><br>";
    echo nl2br(htmlspecialchars($row['message']));
    echo "<br><small style='font-size:10px; opacity:0.7;'>" . date('H:i d/m', strtotime($row['created_at'])) . "</small>";
    echo "</div>";
}
