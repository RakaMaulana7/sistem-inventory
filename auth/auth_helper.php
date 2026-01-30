<?php 
function cek_kemanan_login($pdo) {
    // Mulai session jika belum dimulai
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['login']) || !isset($_SESSION['user_id'])) {
        header("Location: ../login.html"); 
        exit;
    }

    $stmt = $pdo->prepare("SELECT session_id FROM users WHERE id = :id");
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user || empty($user['session_id']) || $user['session_id'] !== session_id()) {
        session_unset();
        session_destroy();
        
        header("Location: ../login.html?status=session_expired");
        exit;
    }
}