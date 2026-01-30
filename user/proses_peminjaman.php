<?php
session_start();
require "../config/database.php";

// Set timezone agar sinkron dengan waktu pengiriman formulir
date_default_timezone_set('Asia/Jakarta');

// 1. PROTEKSI LOGIN - HANYA USER
if (!isset($_SESSION['login']) || $_SESSION['role'] !== 'user') {
    header("Location: ../login.html");
    exit;
}

if (isset($_POST['kirim_pengajuan'])) {
    // Ambil ID User dari session
    $user_id = $_SESSION['user_id'] ?? $_SESSION['id']; 
    
    // Ambil Data dari Form
    $item_id     = $_POST['item_id']; 
    $kategori    = $_POST['kategori']; 
    $tgl_mulai   = $_POST['tanggal_mulai'];
    $jam_mulai   = $_POST['waktu_mulai'];
    $tgl_selesai = $_POST['tanggal_selesai'];
    $jam_selesai = $_POST['waktu_selesai'];
    $telp_utama  = $_POST['telepon_utama'];
    $telp_darurat = $_POST['telepon_darurat'];
    $jaminan     = $_POST['jaminan'];
    $jumlah      = $_POST['jumlah'] ?? 1;
    $status      = 'pending'; 

    // --- LOGIKA VALIDASI WAKTU LEWAT (SISI SERVER) ---
    $waktu_mulai_input = strtotime($tgl_mulai . " " . $jam_mulai);
    $waktu_sekarang = time();

    if ($waktu_mulai_input < $waktu_sekarang) {
        // Jika waktu sudah lewat, lempar kembali ke halaman form dengan pesan error
        header("Location: peminjaman_ruangan.php?id=$item_id&err=expired");
        exit;
    }
    // ------------------------------------------------

    // 2. PROSES UPLOAD SURAT
    $path_database = null;
    if (isset($_FILES["surat_peminjaman"]) && $_FILES["surat_peminjaman"]["error"] == 0) {
        $target_dir = "../uploads/surat/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        
        $file_extension = pathinfo($_FILES["surat_peminjaman"]["name"], PATHINFO_EXTENSION);
        $new_filename = "SURAT_" . strtoupper($kategori) . "_" . time() . "_" . $user_id . "." . $file_extension;
        $target_file = $target_dir . $new_filename;
        
        if (move_uploaded_file($_FILES["surat_peminjaman"]["tmp_name"], $target_file)) {
            $path_database = "uploads/surat/" . $new_filename;
        }
    }

    try {
        // 3. SIMPAN KE TABEL PEMINJAMAN
        $sql = "INSERT INTO peminjaman (
                    user_id, item_id, kategori, tanggal_mulai, waktu_mulai, 
                    tanggal_selesai, waktu_selesai, telepon_utama, telepon_darurat, 
                    jaminan, surat_peminjaman, status, jumlah, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $user_id, $item_id, $kategori, $tgl_mulai, $jam_mulai, 
            $tgl_selesai, $jam_selesai, $telp_utama, $telp_darurat, 
            $jaminan, $path_database, $status, $jumlah
        ]);

        // 4. REDIRECT SETELAH BERHASIL
        header("Location: peminjaman_saya.php?tab=$kategori");

    } catch (PDOException $e) {
        die("Error Database: " . $e->getMessage());
    }
} else {
    header("Location: dashboard.php");
    exit;
}