<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$token = trim($_POST['token'] ?? '');
$newPassword = trim($_POST['new_password'] ?? '');
$confirmPassword = trim($_POST['confirm_password'] ?? '');

// Validasi input
if (empty($token) || empty($newPassword) || empty($confirmPassword)) {
    echo json_encode(['success' => false, 'message' => 'Semua field harus diisi']);
    exit;
}

if ($newPassword !== $confirmPassword) {
    echo json_encode(['success' => false, 'message' => 'Password dan konfirmasi password tidak cocok']);
    exit;
}

if (strlen($newPassword) < 6) {
    echo json_encode(['success' => false, 'message' => 'Password minimal 6 karakter']);
    exit;
}

try {
    // Cek token di database
    $stmt = $pdo->prepare("
        SELECT * FROM password_reset_tokens 
        WHERE token = :token 
        AND used = 0 
        AND expiry > NOW()
    ");
    $stmt->execute(['token' => $token]);
    $resetToken = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$resetToken) {
        echo json_encode([
            'success' => false,
            'message' => 'Link reset password tidak valid atau sudah kadaluarsa. Silakan request reset password baru.'
        ]);
        exit;
    }

    // Hash password baru dengan algoritma yang kuat
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

    // Update password di tabel users
    $stmt = $pdo->prepare("UPDATE users SET password = :password WHERE id = :id");
    $stmt->execute([
        'password' => $hashedPassword,
        'id' => $resetToken['user_id']
    ]);

    // Tandai token sebagai sudah digunakan
    $stmt = $pdo->prepare("UPDATE password_reset_tokens SET used = 1 WHERE token = :token");
    $stmt->execute(['token' => $token]);

    // Hapus semua session aktif user ini untuk keamanan (opsional)
    $stmt = $pdo->prepare("UPDATE users SET session_id = NULL WHERE id = :id");
    $stmt->execute(['id' => $resetToken['user_id']]);

    echo json_encode([
        'success' => true,  
        'message' => 'Password berhasil direset! Silakan login dengan password baru Anda.',
        'redirect' => 'login.html'
    ]);

} catch (PDOException $e) {
    error_log("Reset Password Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan sistem. Silakan coba lagi atau hubungi admin.'
    ]);
}
?>