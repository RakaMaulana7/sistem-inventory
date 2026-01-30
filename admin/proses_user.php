<?php
session_start();
require "../config/database.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    $nama = trim($_POST['nama']);
    $username = trim($_POST['username']);
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $email = $email === '' ? null : $email;
    $prodi = $_POST['prodi'];
    $role = $_POST['role'];

    // Basic email validation
    if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: kelola_user.php?msg=error_invalid_email");
        exit;
    }

    if ($action === 'add') {
        // Cek username unik
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $checkStmt->execute([$username]);
        if ($checkStmt->fetchColumn() > 0) {
            header("Location: kelola_user.php?msg=error_username_exists");
            exit;
        }

        // Cek email unik jika diisi
        if ($email !== null) {
            $checkEmail = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
            $checkEmail->execute([$email]);
            if ($checkEmail->fetchColumn() > 0) {
                header("Location: kelola_user.php?msg=error_email_exists");
                exit;
            }
        }

        // Password default: sama dengan username
        $password_default = password_hash($username, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (nama, username, email, password, prodi, role) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$nama, $username, $email, $password_default, $prodi, $role]);
        
    } elseif ($action === 'edit') {
        $old_username = $_POST['old_username'];

        // Cek email unik jika diisi (kecuali milik user ini)
        if ($email !== null) {
            $checkEmail = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND username != ?");
            $checkEmail->execute([$email, $old_username]);
            if ($checkEmail->fetchColumn() > 0) {
                header("Location: kelola_user.php?msg=error_email_exists");
                exit;
            }
        }

        // Cek jika tombol Reset Password diklik
        if (isset($_POST['reset_password'])) {
            $new_password = password_hash($username, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET nama=?, email=?, prodi=?, role=?, password=? WHERE username=?");
            $stmt->execute([$nama, $email, $prodi, $role, $new_password, $old_username]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET nama=?, email=?, prodi=?, role=? WHERE username=?");
            $stmt->execute([$nama, $email, $prodi, $role, $old_username]);
        }
    }
    
    header("Location: kelola_user.php?msg=success");
    exit;
}

// Logika Hapus
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $username = $_GET['username'];
    if ($username !== 'admin') {
        $stmt = $pdo->prepare("DELETE FROM users WHERE username = ?");
        $stmt->execute([$username]);
    }
    header("Location: kelola_user.php?msg=deleted");
    exit;
}