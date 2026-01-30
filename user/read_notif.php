<?php
session_start();
require_once "../config/database.php";
require_once "../auth/auth_helper.php";

cek_kemanan_login($pdo);

if (!isset($_SESSION['login']) || $_SESSION['role'] !== 'user') {
    header("Location: ../login.html");
    exit;
}

$user_id = $_SESSION['user_id'] ?? $_SESSION['id'];
$peminjaman_id = $_GET['id'] ?? '';

if (empty($peminjaman_id)) {
    header("Location: peminjaman_saya.php");
    exit;
}

try {
    // Verifikasi bahwa notifikasi milik user saat ini
    $stmt = $pdo->prepare("
        SELECT id, status, kategori FROM peminjaman 
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$peminjaman_id, $user_id]);
    $notif = $stmt->fetch();

    if ($notif) {
        // Mark as read
        $stmt = $pdo->prepare("UPDATE peminjaman SET is_read = 1 WHERE id = ?");
        $stmt->execute([$peminjaman_id]);
    }

    // Redirect berdasarkan status dengan tab parameter
    $tab = isset($notif['kategori']) ? $notif['kategori'] : 'ruangan';
    
    if ($notif && in_array($notif['status'], ['returning', 'kembali'])) {
        // Redirect ke riwayat jika sudah returning/dikembalikan
        header("Location: riwayat_peminjaman.php?tab=$tab");
    } else {
        // Redirect ke peminjaman saya untuk approval/rejection dengan tab sesuai kategori
        header("Location: peminjaman_saya.php?tab=$tab");
    }
    
} catch (PDOException $e) {
    error_log("Error marking notification as read: " . $e->getMessage());
    header("Location: peminjaman_saya.php");
}
?>
