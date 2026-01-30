<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Load PHPMailer
require_once __DIR__ . '/../vendor/PHPMailer/src/Exception.php';
require_once __DIR__ . '/../vendor/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/PHPMailer/src/SMTP.php';

// ============================================
// KONFIGURASI EMAIL - GANTI SESUAI PUNYA ANDA
// ============================================

define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'irafaziraa07@gmail.com'); // ← GANTI dengan email Anda
define('SMTP_PASSWORD', 'lwpq tico teyo bgvg');            // ← GANTI dengan App Password
define('SMTP_FROM_EMAIL', 'irafaziraa07@gmail.com'); // ← GANTI dengan email Anda
define('SMTP_FROM_NAME', 'Sistem Inventory FT UMSurabaya');

function sendEmail($toEmail, $toName, $subject, $htmlBody) {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';
        
        // Untuk localhost - disable SSL verification
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($toEmail, $toName);
        $mail->addReplyTo('admin.inventory@ft-umsurabaya.ac.id', 'Admin Inventory');
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = strip_tags($htmlBody);
        
        // Send
        $mail->send();
        error_log("[EMAIL SUCCESS] Email sent to: " . $toEmail);
        return true;
        
    } catch (Exception $e) {
        error_log("[EMAIL ERROR] Failed to send to {$toEmail}. Error: {$mail->ErrorInfo}");
        return false;
    }
}

function testEmailConnection() {
    $mail = new PHPMailer(true);
    
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        $mail->smtpConnect();
        error_log("[EMAIL TEST] SMTP Connection successful!");
        return true;
        
    } catch (Exception $e) {
        error_log("[EMAIL TEST] SMTP Connection failed: {$mail->ErrorInfo}");
        return false;
    }
}
?>