<?php
// File: get_room_list_by_gedung.php
// API endpoint untuk mengambil daftar ruangan template berdasarkan gedung

header('Content-Type: application/json');
require "../config/database.php";

$gedung = $_GET['gedung'] ?? '';

if (empty($gedung)) {
    echo json_encode(['success' => false, 'message' => 'Nama gedung tidak boleh kosong']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT daftar_ruangan FROM gedung WHERE nama_gedung = ?");
    $stmt->execute([$gedung]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result && !empty($result['daftar_ruangan'])) {
        $daftarRuangan = json_decode($result['daftar_ruangan'], true);
        
        // Flatten array untuk dropdown
        $flatList = [];
        if (is_array($daftarRuangan)) {
            foreach ($daftarRuangan as $lantai => $rooms) {
                if (is_array($rooms)) {
                    foreach ($rooms as $room) {
                        if (!empty(trim($room))) {
                            $flatList[] = [
                                'lantai' => $lantai,
                                'nama_ruangan' => $room,
                                'display' => $room . " (Lantai " . $lantai . ")"
                            ];
                        }
                    }
                }
            }
        }
        
        echo json_encode(['success' => true, 'data' => $flatList]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Tidak ada template ruangan untuk gedung ini']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}