<?php
session_start();
require "../config/database.php";

if (!isset($_SESSION['login']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

if (isset($_POST['import'])) {
    $file = $_FILES['file_csv']['tmp_name'];

    // Validasi apakah file ada
    if (!is_uploaded_file($file)) {
        header("Location: kelola_user.php?msg=error_upload");
        exit;
    }

    // Membuka file CSV
    if (($handle = fopen($file, "r")) !== FALSE) {
        // Lewati baris pertama jika file CSV Anda memiliki header/judul kolom
        fgetcsv($handle, 1000, ",");

        $success = 0;
        $error = 0;

        // Mulai transaksi database
        $pdo->beginTransaction();

        try {
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                // Mendukung dua format CSV:
                // - Lama: 0=Nama, 1=Username/NIM, 2=Prodi, 3=Role
                // - Baru (opsional email): 0=Nama, 1=Username/NIM, 2=Email, 3=Prodi, 4=Role
                $nama = htmlspecialchars($data[0] ?? '');
                $username = htmlspecialchars($data[1] ?? '');

                $email = null;
                $prodi = '';
                $role = 'user';

                if (isset($data[4])) {
                    // Format 5 kolom: gunakan index 2 = email
                    $rawEmail = trim($data[2]);
                    $email = filter_var($rawEmail, FILTER_VALIDATE_EMAIL) ? $rawEmail : null;
                    $prodi = htmlspecialchars($data[3] ?? '');
                    $role = strtolower(htmlspecialchars($data[4] ?? 'user'));
                } else {
                    // Fallback ke format lama
                    $prodi = htmlspecialchars($data[2] ?? '');
                    $role = strtolower(htmlspecialchars($data[3] ?? 'user'));
                }

                // Password default disamakan dengan username
                $password = password_hash($username, PASSWORD_DEFAULT);

                // Cek apakah username sudah terdaftar
                $stmtCek = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
                $stmtCek->execute([$username]);

                // Jika ada email, cek pula duplikat email
                $emailExists = false;
                if ($email) {
                    $stmtEmail = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
                    $stmtEmail->execute([$email]);
                    $emailExists = $stmtEmail->fetchColumn() > 0;
                }

                if ($stmtCek->fetchColumn() == 0 && !$emailExists) {
                    $stmt = $pdo->prepare("INSERT INTO users (nama, username, email, password, prodi, role) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$nama, $username, $email, $password, $prodi, $role]);
                    $success++;
                } else {
                    $error++; // Menghitung jika data sudah ada (skip)
                }
            }
            $pdo->commit();
            header("Location: kelola_user.php?msg=import_done&s=$success&e=$error");
        } catch (Exception $e) {
            $pdo->rollBack();
            die("Gagal mengimpor data: " . $e->getMessage());
        }
        fclose($handle);
    }
}