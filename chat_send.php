<?php
session_start();
include('db/db.php');
include('csrf.php');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo "ไม่มีสิทธิ์เข้าถึง";
    exit;
}

csrf_require();

$user_id = $_SESSION['user_id'];
$role    = $_SESSION['role'] ?? '';

$conversation_id = intval($_POST['conversation_id'] ?? 0);
$message = trim($_POST['message'] ?? '');

if ($conversation_id === 0 || $message === '') {
    http_response_code(400);
    echo "ข้อมูลไม่ครบ";
    exit;
}

// ตรวจสอบสิทธิ์เข้าถึง conversation (เหมือน chat.php — user ต้องเป็นเจ้าของ conversation)
$stmt = $conn->prepare("SELECT * FROM conversations WHERE id = ?");
$stmt->bind_param("i", $conversation_id);
$stmt->execute();
$conv = $stmt->get_result()->fetch_assoc();

if (!$conv) {
    http_response_code(404);
    echo "ไม่พบการสนทนา";
    exit;
}

if ($role === 'technician') {
    if ($conv['technician_id'] != $user_id) {
        http_response_code(403);
        echo "คุณไม่มีสิทธิ์เข้าถึงแชทนี้";
        exit;
    }
} else {
    if ($conv['user_id'] != $user_id) {
        http_response_code(403);
        echo "คุณไม่มีสิทธิ์เข้าถึงแชทนี้";
        exit;
    }
}

// sender มาจาก session เท่านั้น ห้ามเชื่อค่าจาก client (กันปลอมเป็นอีกฝ่าย)
$sender = $role === 'technician' ? 'technician' : 'user';

$stmt = $conn->prepare("INSERT INTO messages (conversation_id, sender, message, created_at) VALUES (?, ?, ?, NOW())");
$stmt->bind_param("iss", $conversation_id, $sender, $message);
$stmt->execute();

echo "success";
