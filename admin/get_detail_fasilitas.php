<?php
header('Content-Type: application/json');
require "../config/database.php";

// Ambil parameter kode label dari request
$kode_labels = isset($_GET['kode_labels']) ? $_GET['kode_labels'] : '';

if (empty($kode_labels)) {
    echo json_encode([]);
    exit;
}

// Parse kode labels (format: FT-KRS-001,FT-KRS-002,FT-PRJ-001)
$kode_array = array_map('trim', explode(',', $kode_labels));

try {
    // Buat placeholder untuk prepared statement
    $placeholders = implode(',', array_fill(0, count($kode_array), '?'));
    
    // Query untuk ambil detail sarana berdasarkan kode label
    $sql = "SELECT kode_label, nama, kondisi, tahun_beli, jenis, lokasi, status 
            FROM sarana 
            WHERE kode_label IN ($placeholders)
            ORDER BY FIELD(kode_label, " . $placeholders . ")";
    
    $stmt = $pdo->prepare($sql);
    
    // Execute dengan parameter kode_array 2x (untuk IN dan FIELD)
    $params = array_merge($kode_array, $kode_array);
    $stmt->execute($params);
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($results);
    
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>