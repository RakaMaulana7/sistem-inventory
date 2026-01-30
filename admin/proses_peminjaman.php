<?php
session_start();
require "../config/database.php";

if (!isset($_SESSION['login']) || $_SESSION['role'] !== 'admin') {
    header("Content-Type: application/json");
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$admin_id = $_SESSION['user_id'] ?? $_SESSION['id']; 
$id       = isset($_GET['id']) ? intval($_GET['id']) : null;
$action   = $_GET['action'] ?? null;

if (!$id || !$action) {
    die("Parameter ID atau Action tidak valid.");
}

try {
    // Ambil data peminjaman beserta fasilitas (untuk ruangan)
    $stmtCheck = $pdo->prepare("
        SELECT p.*, 
               r.fasilitas,
               r.nama_ruangan
        FROM peminjaman p
        LEFT JOIN ruangan r ON p.item_id = r.id AND p.kategori = 'ruangan'
        WHERE p.id = ?
    ");
    $stmtCheck->execute([$id]);
    $dataPeminjaman = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if (!$dataPeminjaman) die("Data peminjaman tidak ditemukan.");

    $pdo->beginTransaction();

    // ==================== AKSI: APPROVE ====================
    if ($action === 'approve') {
        // Update status peminjaman
        $stmt = $pdo->prepare("UPDATE peminjaman SET status = 'approved', admin_id = ? WHERE id = ?");
        $stmt->execute([$admin_id, $id]);

        if ($dataPeminjaman['kategori'] === 'ruangan') {
            // ✅ UPDATE STATUS RUANGAN MENJADI 'Dipakai'
            $updateRuangan = $pdo->prepare("UPDATE ruangan SET status = 'Dipakai' WHERE id = ?");
            $updateRuangan->execute([$dataPeminjaman['item_id']]);
            
            // ✅ UPDATE STATUS SEMUA FASILITAS DI RUANGAN
            if (!empty($dataPeminjaman['fasilitas'])) {
                // Parse fasilitas: format bisa "Kursi [FT-KRS-001], Proyektor [FT-PRJ-002]"
                // atau format lama "FT-KRS-001,FT-KRS-002"
                $fasilitasString = $dataPeminjaman['fasilitas'];
                $kodeLabels = [];
                
                // Cek apakah menggunakan format baru [kode] atau format lama
                if (strpos($fasilitasString, '[') !== false) {
                    // Format baru: "Kursi [FT-KRS-001], Proyektor [FT-PRJ-002]"
                    preg_match_all('/\[([^\]]+)\]/', $fasilitasString, $matches);
                    $kodeLabels = $matches[1];
                } else {
                    // Format lama: "FT-KRS-001,FT-KRS-002"
                    $kodeLabels = array_map('trim', explode(',', $fasilitasString));
                }
                
                // Update status semua fasilitas menjadi "Dipinjam"
                if (!empty($kodeLabels)) {
                    $placeholders = implode(',', array_fill(0, count($kodeLabels), '?'));
                    $stmtUpdateFasilitas = $pdo->prepare("
                        UPDATE sarana 
                        SET status = 'Dipinjam' 
                        WHERE kode_label IN ($placeholders)
                    ");
                    $stmtUpdateFasilitas->execute($kodeLabels);
                }
            }
            
        } elseif ($dataPeminjaman['kategori'] === 'transportasi') {
            // ✅ UPDATE STATUS TRANSPORTASI MENJADI 'Dipakai'
            $updateTransport = $pdo->prepare("UPDATE transportasi SET status = 'Dipakai' WHERE id = ?");
            $updateTransport->execute([$dataPeminjaman['item_id']]);
        }
    } 
    
    // ==================== AKSI: REJECT ====================
    elseif ($action === 'reject') {
        // Update status peminjaman
        $stmt = $pdo->prepare("UPDATE peminjaman SET status = 'rejected', admin_id = ? WHERE id = ?");
        $stmt->execute([$admin_id, $id]);
        
        if ($dataPeminjaman['kategori'] === 'ruangan') {
            // ✅ KEMBALIKAN STATUS RUANGAN MENJADI 'Tersedia'
            $updateRuangan = $pdo->prepare("UPDATE ruangan SET status = 'Tersedia' WHERE id = ?");
            $updateRuangan->execute([$dataPeminjaman['item_id']]);
            
            // ✅ KEMBALIKAN STATUS SEMUA FASILITAS
            if (!empty($dataPeminjaman['fasilitas'])) {
                $fasilitasString = $dataPeminjaman['fasilitas'];
                $kodeLabels = [];
                
                // Parse kode label
                if (strpos($fasilitasString, '[') !== false) {
                    preg_match_all('/\[([^\]]+)\]/', $fasilitasString, $matches);
                    $kodeLabels = $matches[1];
                } else {
                    $kodeLabels = array_map('trim', explode(',', $fasilitasString));
                }
                
                // Kembalikan status fasilitas menjadi "Tersedia"
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
            
        } elseif ($dataPeminjaman['kategori'] === 'transportasi') {
            // Kembalikan status transportasi
            $updateTransport = $pdo->prepare("UPDATE transportasi SET status = 'Tersedia' WHERE id = ?");
            $updateTransport->execute([$dataPeminjaman['item_id']]);
        }
    } 
    
    // ==================== AKSI: SELESAI (Legacy - Redirect ke verifikasi) ====================
    elseif ($action === 'selesai') {
        $catatan = isset($_GET['catatan_admin']) ? $_GET['catatan_admin'] : null;

        // Update status sekaligus simpan catatan admin
        $stmt = $pdo->prepare("UPDATE peminjaman 
                               SET status = 'kembali', 
                                   admin_id = ?, 
                                   catatan_admin = ?, 
                                   updated_at = NOW() 
                               WHERE id = ?");
        $stmt->execute([$admin_id, $catatan, $id]);

        if ($dataPeminjaman['kategori'] === 'ruangan') {
            // Kembalikan status ruangan
            $updateRuangan = $pdo->prepare("UPDATE ruangan SET status = 'Tersedia' WHERE id = ?");
            $updateRuangan->execute([$dataPeminjaman['item_id']]);
            
            // ✅ KEMBALIKAN STATUS SEMUA FASILITAS
            if (!empty($dataPeminjaman['fasilitas'])) {
                $fasilitasString = $dataPeminjaman['fasilitas'];
                $kodeLabels = [];
                
                if (strpos($fasilitasString, '[') !== false) {
                    preg_match_all('/\[([^\]]+)\]/', $fasilitasString, $matches);
                    $kodeLabels = $matches[1];
                } else {
                    $kodeLabels = array_map('trim', explode(',', $fasilitasString));
                }
                
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
            
        } elseif ($dataPeminjaman['kategori'] === 'transportasi') {
            // ✅ UPDATE STATUS TRANSPORTASI MENJADI 'Tersedia'
            $updateTransport = $pdo->prepare("UPDATE transportasi SET status = 'Tersedia' WHERE id = ?");
            $updateTransport->execute([$dataPeminjaman['item_id']]);
        }
    }

    $pdo->commit();
    
    // Success message dengan info auto-update
    $message = "success";
    if ($action === 'approve' && $dataPeminjaman['kategori'] === 'ruangan') {
        $message .= "&updated_facilities=true";
    } elseif ($action === 'reject' && $dataPeminjaman['kategori'] === 'ruangan') {
        $message .= "&restored_facilities=true";
    }
    
    header("Location: dashboard.php?msg=$message&action=" . $action);
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    die("Gagal memproses data: " . $e->getMessage());
}
?>