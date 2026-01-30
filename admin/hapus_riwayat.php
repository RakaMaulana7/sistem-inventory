<?php
session_start();
require "../config/database.php";

// Proteksi Admin
if (!isset($_SESSION['login']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.html");
    exit;
}

$id = $_GET['id'] ?? null;
$bulk = $_GET['bulk'] ?? false;
$kategori = $_GET['kategori'] ?? null;

try {
    if ($bulk && $kategori) {
        // --- HAPUS SEMUA DATA PER KATEGORI ---
        
        // 1. Ambil nama file foto sebelum dihapus dari DB untuk dihapus dari storage
        $stmtFiles = $pdo->prepare("SELECT foto_pengembalian FROM peminjaman WHERE kategori = ? AND status IN ('kembali', 'rejected', 'selesai')");
        $stmtFiles->execute([$kategori]);
        $files = $stmtFiles->fetchAll(PDO::FETCH_COLUMN);

        foreach ($files as $file) {
            if ($file && file_exists("../" . $file)) {
                unlink("../" . $file);
            }
        }

        // 2. Hapus dari database
        $stmt = $pdo->prepare("DELETE FROM peminjaman WHERE kategori = ? AND status IN ('kembali', 'rejected', 'selesai')");
        $stmt->execute([$kategori]);

    } elseif ($id) {
        // --- HAPUS SATU DATA ---
        
        // 1. Hapus file fisik
        $stmtFile = $pdo->prepare("SELECT foto_pengembalian FROM peminjaman WHERE id = ?");
        $stmtFile->execute([$id]);
        $file = $stmtFile->fetchColumn();

        if ($file && file_exists("../" . $file)) {
            unlink("../" . $file);
        }

        // 2. Hapus dari database
        $stmt = $pdo->prepare("DELETE FROM peminjaman WHERE id = ?");
        $stmt->execute([$id]);
    }

    // Kembali ke halaman sebelumnya
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit;

} catch (PDOException $e) {
    die("Gagal menghapus data: " . $e->getMessage());
}