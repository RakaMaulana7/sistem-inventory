<?php
session_start();
require "../config/database.php";

header('Content-Type: application/json');

$excludeRoomId = isset($_GET['exclude_room']) ? (int)$_GET['exclude_room'] : null;

$sql = "SELECT fasilitas FROM ruangan WHERE fasilitas IS NOT NULL AND fasilitas != ''";
$params = []; 

if ($excludeRoomId) {
    $sql .= " AND id != ?";
    $params[] = $excludeRoomId;
}

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $allRooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $terpakai = [];
    foreach ($allRooms as $room) {
        $items = array_map('trim', explode(',', $room['fasilitas']));
        foreach ($items as $item) {
            if (!empty($item)) {
                $terpakai[] = $item;
            }
        }
    }
    
    echo json_encode($terpakai);
} catch (PDOException $e) {
    echo json_encode([]);
}
?>