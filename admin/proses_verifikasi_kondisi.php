<?php
session_start();
require "../config/database.php";

// Proteksi Admin
if (!isset($_SESSION['login']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.html");
    exit;
}

$peminjaman_id = $_GET['id'] ?? null;
$action = $_GET['action'] ?? null;

if (!$peminjaman_id || $action !== 'selesai') {
    header("Location: dashboard.php?status=error&msg=" . urlencode("Parameter tidak valid"));
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Ambil data peminjaman beserta data ruangan (untuk fasilitas)
    $stmt = $pdo->prepare("
        SELECT p.*, 
               r.fasilitas,
               r.nama_ruangan
        FROM peminjaman p
        LEFT JOIN ruangan r ON p.item_id = r.id AND p.kategori = 'ruangan'
        WHERE p.id = ?
    ");
    $stmt->execute([$peminjaman_id]);
    $peminjaman = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$peminjaman) {
        throw new Exception("Data peminjaman tidak ditemukan");
    }
    
    $updateCount = 0;
    
    // ===== PROSES UPDATE KONDISI DAN CATATAN (UNTUK RUANGAN DAN SARANA) =====
    if (isset($_GET['kondisi']) && is_array($_GET['kondisi'])) {
        $kondisiData = $_GET['kondisi'];
        $catatanData = $_GET['catatan'] ?? [];
        
        // Update kondisi dan catatan setiap unit (fasilitas ruangan atau sarana)
        foreach ($kondisiData as $kodeLabel => $kondisiBaru) {
            $catatan = $catatanData[$kodeLabel] ?? '-';
            
            // Update tabel sarana (berlaku untuk fasilitas ruangan DAN sarana)
            $stmtUpdate = $pdo->prepare("
                UPDATE sarana 
                SET kondisi = ?, 
                    catatan = ?
                WHERE kode_label = ?
            ");
            $result = $stmtUpdate->execute([$kondisiBaru, $catatan, $kodeLabel]);
            
            if ($result) {
                $updateCount++;
            }
            
            // Juga update di item_unit jika ada (untuk sinkronisasi ruangan)
            $stmtUpdateUnit = $pdo->prepare("
                UPDATE item_unit 
                SET kondisi = ?,
                    catatan = ?
                WHERE kode_label = ?
            ");
            $stmtUpdateUnit->execute([$kondisiBaru, $catatan, $kodeLabel]);
        }
    }
    
    // ===== AMBIL CATATAN ADMIN (UNTUK TRANSPORTASI) =====
    $catatan_admin = $_GET['catatan_admin'] ?? null;
    
    // ===== UPDATE STATUS PEMINJAMAN MENJADI 'kembali' (SELESAI VERIFIKASI) =====
    $stmtUpdatePeminjaman = $pdo->prepare("
        UPDATE peminjaman 
        SET status = 'kembali',
            admin_id = ?,
            catatan_admin = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    $stmtUpdatePeminjaman->execute([$_SESSION['user_id'], $catatan_admin, $peminjaman_id]);
    
    // ===== UPDATE STATUS ASET MENJADI TERSEDIA =====
    if ($peminjaman['kategori'] === 'ruangan') {
        // ✅ KEMBALIKAN STATUS RUANGAN MENJADI 'Tersedia'
        $stmtUpdateAset = $pdo->prepare("UPDATE ruangan SET status = 'Tersedia' WHERE id = ?");
        $stmtUpdateAset->execute([$peminjaman['item_id']]);
        
        // ✅ KEMBALIKAN STATUS SEMUA FASILITAS MENJADI 'Tersedia'
        if (!empty($peminjaman['fasilitas'])) {
            $fasilitasString = $peminjaman['fasilitas'];
            $kodeLabels = [];
            
            // Parse kode label dari string fasilitas
            if (strpos($fasilitasString, '[') !== false) {
                // Format: "Kursi [FT-KRS-001], Proyektor [FT-PRJ-002]"
                preg_match_all('/\[([^\]]+)\]/', $fasilitasString, $matches);
                $kodeLabels = $matches[1];
            } else {
                // Format lama: "FT-KRS-001,FT-KRS-002"
                $kodeLabels = array_map('trim', explode(',', $fasilitasString));
            }
            
            // Update status semua fasilitas menjadi "Tersedia"
            if (!empty($kodeLabels)) {
                $placeholders = implode(',', array_fill(0, count($kodeLabels), '?'));
                $stmtUpdateFasilitas = $pdo->prepare("
                    UPDATE sarana 
                    SET status = 'Tersedia' 
                    WHERE kode_label IN ($placeholders)
                ");
                $stmtUpdateFasilitas->execute($kodeLabels);
            }
        }
        
    } elseif ($peminjaman['kategori'] === 'sarana') {
        // Untuk sarana, tidak perlu update status karena menggunakan sistem stok
        // Status 'Tersedia' otomatis jika stok > 0
        
        // OPSIONAL: Update status sarana yang dipinjam menjadi tersedia
        $stmtUpdateSarana = $pdo->prepare("
            UPDATE sarana 
            SET status = 'Tersedia' 
            WHERE id = ?
        ");
        $stmtUpdateSarana->execute([$peminjaman['item_id']]);
        
    } elseif ($peminjaman['kategori'] === 'transportasi') {
        // ✅ UPDATE STATUS TRANSPORTASI MENJADI 'Tersedia'
        $stmtUpdateAset = $pdo->prepare("UPDATE transportasi SET status = 'Tersedia' WHERE id = ?");
        $stmtUpdateAset->execute([$peminjaman['item_id']]);
    }
    
    $pdo->commit();
    
    // Buat success message
    $successMsg = "Peminjaman berhasil diselesaikan";
    if ($updateCount > 0) {
        $successMsg .= " dan $updateCount kondisi unit diperbarui";
    }
    if (!empty($catatan_admin)) {
        $successMsg .= " dengan catatan admin";
    }
    if ($peminjaman['kategori'] === 'ruangan') {
        $successMsg .= ". Status ruangan dan semua fasilitas telah dikembalikan ke 'Tersedia'";
    }
    
    header("Location: dashboard.php?status=verified&msg=" . urlencode($successMsg));
    exit;
    
} catch (Exception $e) {
    $pdo->rollBack();
    header("Location: dashboard.php?status=error&msg=" . urlencode($e->getMessage()));
    exit;
}
?>