<?php
session_start();
require "../config/database.php";

if (isset($_SESSION['user_id'])) {
    $update = $pdo->prepare("UPDATE users SET session_id = NULL WHERE id = :id");
    $update->execute([':id' => $_SESSION['user_id']]);
}

session_destroy();
header("Location: ../login.html");
exit;