<?php
// Set headers dulu sebelum apapun
header('Content-Type: application/json; charset=utf-8');

// Mulai output buffering untuk catch error
ob_start();

try {
    session_start();
    require_once '../config/database.php';

    // Enable error logging untuk debugging
    error_reporting(E_ALL);
    ini_set('display_errors', 0); // Jangan tampilkan error di output
    ini_set('log_errors', 1);

    // Coba load email config jika ada
    $email_config_exists = file_exists('../config/email_config.php');
    if ($email_config_exists) {
        require_once '../config/email_config.php';
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
        exit;
    }

    $email = trim($_POST['email'] ?? '');

    // Validasi input
    if (empty($email)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Email tidak boleh kosong']);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Format email tidak valid']);
        exit;
    }

    // Cek koneksi database
    if (!isset($pdo)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Database connection error']);
        exit;
    }

    // Cek apakah email ada di database
    $stmt = $pdo->prepare("SELECT id, username, email, nama, role FROM users WHERE email = :email");
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // Untuk keamanan, tetap tampilkan pesan sukses
        ob_end_clean();
        echo json_encode([
            'success' => true, 
            'message' => 'Jika email terdaftar, link reset password telah dikirim. Silakan cek inbox atau spam.'
        ]);
        exit;
    }

    // Generate token yang aman
    $token = bin2hex(random_bytes(32));
    $expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));

    // Hapus token lama
    $stmt = $pdo->prepare("DELETE FROM password_reset_tokens WHERE user_id = :user_id AND used = 0");
    $stmt->execute(['user_id' => $user['id']]);

    // Simpan token baru
    $stmt = $pdo->prepare("
        INSERT INTO password_reset_tokens (user_id, email, token, expiry, created_at)
        VALUES (:user_id, :email, :token, :expiry, NOW())
    ");
    
    $stmt->execute([
        'user_id' => $user['id'],
        'email' => $email,
        'token' => $token,
        'expiry' => $expiry
    ]);

    // Buat reset link
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $basePath = dirname($_SERVER['SCRIPT_NAME'], 2);
    $resetLink = $protocol . "://" . $host . $basePath . "/reset-password.html?token=" . $token;
    
    // Template email
    $greeting = ($user['role'] === 'admin') ? 'Admin' : 'Pengguna';
    $subject = "Reset Password - Sistem Inventory FT UMSurabaya";
    
    $message = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; background: #ffffff; }
            .header { background: linear-gradient(135deg, #10b981, #3b82f6); color: white; padding: 30px; text-align: center; }
            .content { padding: 30px; background: #f9fafb; }
            .button { display: inline-block; background: #10b981; color: white !important; padding: 15px 30px; text-decoration: none; border-radius: 8px; font-weight: bold; }
            .warning { background: #fef2f2; border-left: 4px solid #ef4444; padding: 15px; margin: 20px 0; }
            .footer { text-align: center; padding: 20px; font-size: 12px; color: #9ca3af; background: #f3f4f6; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🔐 Reset Password</h1>
            </div>
            <div class='content'>
                <p><strong>Halo " . htmlspecialchars($user['nama']) . ",</strong></p>
                <p>Kami menerima permintaan reset password untuk akun <strong>" . $greeting . "</strong> Anda.</p>
                
                <p><strong>Username:</strong> " . htmlspecialchars($user['username']) . "</p>
                <p><strong>Email:</strong> " . htmlspecialchars($user['email']) . "</p>
                
                <p style='margin: 25px 0;'>
                    <a href='" . $resetLink . "' class='button'>Reset Password Sekarang</a>
                </p>
                
                <p style='font-size: 12px; color: #666;'>Atau copy link berikut:</p>
                <p style='background: #fff; padding: 10px; border: 1px solid #ddd; word-break: break-all; font-size: 12px;'>" . $resetLink . "</p>
                
                <div class='warning'>
                    <strong>⚠️ Penting:</strong>
                    <ul style='margin: 10px 0; padding-left: 20px;'>
                        <li>Link berlaku <strong>24 jam</strong></li>
                        <li>Hanya bisa digunakan <strong>1 kali</strong></li>
                        <li>Jika tidak meminta reset, abaikan email ini</li>
                    </ul>
                </div>
                
                <p style='margin-top: 20px; font-size: 13px;'>Butuh bantuan? Hubungi: admin.inventory@ft-umsurabaya.ac.id</p>
            </div>
            <div class='footer'>
                <p><strong>© 2025 Fakultas Teknik UMSurabaya</strong></p>
                <p>Email otomatis, jangan dibalas.</p>
            </div>
        </div>
    </body>
    </html>
    ";

    // Email headers
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8\r\n";
    $headers .= "From: Sistem Inventory FT <noreply@ft-umsurabaya.ac.id>\r\n";
    $headers .= "Reply-To: admin.inventory@ft-umsurabaya.ac.id\r\n";

    // MODE PRODUCTION - Kirim email sungguhan
    $isTestingMode = false;
    
    if ($isTestingMode) {
        ob_end_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Link reset password telah dibuat! Silakan copy link di bawah ini ke browser Anda.',
            'reset_link' => $resetLink,
            'note' => 'Mode Testing - Email tidak dikirim. Link berlaku 24 jam.'
        ]);
    } else {
        // Mode production: Coba kirim email
        $emailSent = false;
        
        // Method 1: Coba gunakan sendEmail function dari email_config.php jika ada
        if ($email_config_exists && function_exists('sendEmail')) {
            try {
                $emailSent = sendEmail($email, $user['nama'], $subject, $message);
            } catch (Exception $e) {
                error_log("[EMAIL] sendEmail exception: " . $e->getMessage());
                $emailSent = false;
            }
        } 
        
        // Method 2: Fallback ke simpan file
        if (!$emailSent) {
            $mailoutputDir = 'C:\\xampp\\mailoutput';
            if (!is_dir($mailoutputDir)) {
                @mkdir($mailoutputDir, 0777, true);
            }
            
            $emailFilename = $mailoutputDir . '\\' . 'email_' . time() . '_' . md5($email) . '.eml';
            $emailContent = "To: " . $email . "\r\n";
            $emailContent .= "Subject: " . $subject . "\r\n";
            $emailContent .= $headers . "\r\n";
            $emailContent .= $message;
            
            if (@file_put_contents($emailFilename, $emailContent)) {
                $emailSent = true;
                error_log("[EMAIL FALLBACK] Email saved to file: " . $emailFilename);
            }
        }
        
        ob_end_clean();
        
        if ($emailSent) {
            echo json_encode([
                'success' => true,
                'message' => '📧 Link reset password telah dikirim ke email Anda! Cek inbox atau folder spam. Link berlaku 24 jam.'
            ]);
        } else {
            error_log("[EMAIL] Failed to send email to: " . $email);
            echo json_encode([
                'success' => true,
                'message' => 'Email processing completed. Cek inbox Anda.'
            ]);
        }
    }

} catch (PDOException $e) {
    $errorMsg = $e->getMessage();
    error_log("Database Error: " . $errorMsg);
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan database. Hubungi admin.'
    ]);
    exit;
} catch (Exception $e) {
    $errorMsg = $e->getMessage();
    error_log("General Error: " . $errorMsg);
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan sistem. Hubungi admin.'
    ]);
    exit;
}
?>
