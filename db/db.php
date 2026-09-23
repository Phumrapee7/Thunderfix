<?php
$configFile = __DIR__ . '/config.php';
if (!file_exists($configFile)) {
    die('ไม่พบ db/config.php — คัดลอกจาก db/config.example.php แล้วกรอกค่าเชื่อมต่อฐานข้อมูลของคุณก่อนใช้งาน');
}
$config = require $configFile;

// Create connection
$conn = new mysqli($config['host'], $config['user'], $config['pass'], $config['dbname']);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ตั้งค่า charset ให้รองรับภาษาไทย
$conn->set_charset("utf8");
?>
