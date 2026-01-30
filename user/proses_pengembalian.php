<?php
session_start();
require "../config/database.php";

// Pastikan hanya user yang bisa mengakses
if (!isset($_SESSION['login']) || $_SESSION['role'] !== 'user') {
    header("Location: ../login.html");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $peminjaman_id = $_POST['peminjaman_id'];
    $user_id = $_SESSION['user_id'] ?? $_SESSION['id'];

    // 1. Konfigurasi Folder
    $target_dir = "../uploads/pengembalian/";
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    // 2. Validasi File
    if (isset($_FILES['foto_kondisi']) && $_FILES['foto_kondisi']['error'] === 0) {
        $file_extension = strtolower(pathinfo($_FILES["foto_kondisi"]["name"], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];

        if (in_array($file_extension, $allowed)) {
            // Nama file unik agar tidak bentrok
            $file_name = "kembali_" . $peminjaman_id . "_" . time() . "." . $file_extension;
            $target_file = $target_dir . $file_name;
            $db_path = "uploads/pengembalian/" . $file_name;

            if (move_uploaded_file($_FILES["foto_kondisi"]["tmp_name"], $target_file)) {
                try {
                    // 3. Pastikan peminjaman ini milik user dan statusnya 'dipinjam'
                    $checkStmt = $pdo->prepare("SELECT status FROM peminjaman WHERE id = ? AND user_id = ?");
                    $checkStmt->execute([$peminjaman_id, $user_id]);
                    $current = $checkStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$current) {
                        header("Location: peminjaman_saya.php?msg=not_found");
                        exit;
                    }
                    
                    if ($current['status'] !== 'approved') {
                        header("Location: peminjaman_saya.php?msg=invalid_status");
                        exit;
                    }
                    
                    // 4. Update Status ke 'returning' (Menunggu Verifikasi Admin)
                    $stmt = $pdo->prepare("UPDATE peminjaman SET 
                        status = 'returning', 
                        foto_pengembalian = ?,
                        updated_at = NOW() 
                        WHERE id = ? AND user_id = ?");
                    
                    $stmt->execute([$db_path, $peminjaman_id, $user_id]);

                    // Redirect dengan notifikasi sukses
                    header("Location: peminjaman_saya.php?msg=return_submitted");
                    exit;

                } catch (PDOException $e) {
                    // Jika DB gagal, hapus file yang sudah terlanjur diupload
                    if(file_exists($target_file)) unlink($target_file);
                    die("Database Error: " . $e->getMessage());
                }
            } else {
                header("Location: peminjaman_saya.php?msg=upload_failed");
            }
        } else {
            header("Location: peminjaman_saya.php?msg=invalid_format");
        }
    } else {
        header("Location: peminjaman_saya.php?msg=no_file");
    }
}