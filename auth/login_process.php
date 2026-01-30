<?php
date_default_timezone_set('Asia/Jakarta'); 
ob_start(); 
session_start();
header('Content-Type: application/json');
require "../config/database.php";

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';
$timeout  = 5; // Batas waktu dalam menit

try {
    $stmt = $pdo->prepare("SELECT id, password, role, nama, session_id, last_activity FROM users WHERE username = :username LIMIT 1");
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        
        // CEK APAKAH ADA SESI AKTIF
        if (!empty($user['session_id'])) {
            $last_active = strtotime($user['last_activity']);
            $now = time();
            $selisih_menit = ($now - $last_active) / 60;

            // Jika belum lewat 5 menit, tolak login baru
            if ($selisih_menit < $timeout) {
                $sisa = ceil($timeout - $selisih_menit);
                ob_clean();
                echo json_encode([
                    'success' => false, 
                    'message' => "Akun masih aktif di perangkat lain. Tunggu $sisa menit lagi atau logout dari perangkat sebelumnya."
                ]);
                exit;
            }
        }

        // JIKA LOLOS CEK (Atau sudah lewat 5 menit)
        session_regenerate_id(true);
        $new_session_id = session_id();

        // Update session_id dan reset waktu aktivitas ke sekarang (NOW)
        $update = $pdo->prepare("UPDATE users SET session_id = :sid, last_activity = NOW() WHERE id = :id");
        $update->execute([':sid' => $new_session_id, ':id' => $user['id']]);

        $_SESSION['login']   = true;
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['nama']    = $user['nama']; 
        $_SESSION['role']    = $user['role'];

        $redirect = ($user['role'] === 'admin') ? "admin/dashboard.php" : "user/dashboard.php";
        
        ob_clean();
        echo json_encode(['success' => true, 'redirect' => $redirect]);

    } else {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Username atau password salah']);
    }
} catch (Exception $e) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Error sistem']);
}