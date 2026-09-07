<?php
session_start();
include('db/db.php');

header('Content-Type: application/json');

if (!isset($_GET['lat'], $_GET['lng'], $_GET['dist'])) {
    echo json_encode([]);
    exit;
}

$lat = floatval($_GET['lat']);
$lng = floatval($_GET['lng']);
$dist = floatval($_GET['dist']); // กม.

$conn->set_charset("utf8mb4");

// Debug log
error_log("Received lat=$lat, lng=$lng, dist=$dist");

function haversine_sql($lat, $lng)
{
    return "(6371 * acos(cos(radians($lat)) * cos(radians(lat)) * cos(radians(lng) - radians($lng)) + sin(radians($lat)) * sin(radians(lat))))";
}

$sql = "SELECT id, name, specialty, phone, lat, lng, " . haversine_sql($lat, $lng) . " AS distance
        FROM technicians
        HAVING distance <= ?
        ORDER BY distance ASC";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['error' => $conn->error]);
    exit;
}

$stmt->bind_param("d", $dist);
$stmt->execute();
$result = $stmt->get_result();

$techs = [];
while ($row = $result->fetch_assoc()) {
    $techs[] = $row;
}

echo json_encode($techs);
