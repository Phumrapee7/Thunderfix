<?php
session_start();
include('db/db.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];

if (!isset($_GET['technician_id'])) {
    echo "ไม่พบช่างที่ต้องการแชท";
    exit;
}

$technician_id = intval($_GET['technician_id']);

// เช็คว่ามี conversation นี้อยู่แล้วไหม (ระหว่าง user กับ technician)
$sql = "SELECT id FROM conversations WHERE (user_id = ? AND technician_id = ?) LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $user_id, $technician_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $conversation_id = $row['id'];
} else {
    // สร้าง conversation ใหม่
    $sql = "INSERT INTO conversations (user_id, technician_id, created_at) VALUES (?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $user_id, $technician_id);
    $stmt->execute();
    $conversation_id = $stmt->insert_id;
}

// redirect ไปหน้าแชท
header("Location: chat.php?conversation_id=$conversation_id");
exit;
