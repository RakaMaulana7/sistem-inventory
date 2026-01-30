<?php
session_start();
require "../config/database.php";

// Proteksi Admin
if (!isset($_SESSION['login']) || $_SESSION['role'] !== 'admin') {
    exit("Akses ditolak");
}

$activeTab = $_GET['tab'] ?? 'ruangan';
$month = $_GET['month'] ?? 'all';
$search = $_GET['search'] ?? '';

// Query yang sama dengan halaman arsip
$queryStr = "
    SELECT p.*, u.nama as user_nama, u.prodi, ad.nama as nama_admin,
    CASE 
        WHEN p.kategori = 'ruangan' THEN r.nama_ruangan
        WHEN p.kategori = 'sarana' THEN s.nama
        WHEN p.kategori = 'transportasi' THEN t.nama
    END as nama_aset
    FROM peminjaman p
    JOIN users u ON p.user_id = u.id
    LEFT JOIN users ad ON p.admin_id = ad.id
    LEFT JOIN ruangan r ON p.item_id = r.id AND p.kategori = 'ruangan'
    LEFT JOIN sarana s ON p.item_id = s.id AND p.kategori = 'sarana'
    LEFT JOIN transportasi t ON p.item_id = t.id AND p.kategori = 'transportasi'
    WHERE p.kategori = :kategori 
    AND p.status IN ('kembali', 'rejected', 'selesai')
";

if ($month !== 'all') $queryStr .= " AND MONTH(p.tanggal_mulai) = :month";
if (!empty($search)) $queryStr .= " AND (u.nama LIKE :search OR u.prodi LIKE :search)";

$stmt = $pdo->prepare($queryStr);
$stmt->bindValue(':kategori', $activeTab);
if ($month !== 'all') $stmt->bindValue(':month', $month);
if (!empty($search)) $stmt->bindValue(':search', "%$search%");
$stmt->execute();
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// HEADER EXCEL
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=Laporan_Peminjaman_" . $activeTab . ".xls");
header("Pragma: no-cache");
header("Expires: 0");
?>

<table border="1">
    <tr>
        <th colspan="6" style="font-size: 16px;">LAPORAN PEMINJAMAN <?= strtoupper($activeTab) ?></th>
    </tr>
    <tr style="background-color: #f2f2f2;">
        <th>Peminjam</th>
        <th>Prodi</th>
        <th>Aset</th>
        <th>Waktu Pinjam</th>
        <th>Status</th>
        <th>Petugas (Admin)</th>
    </tr>
    <?php foreach ($data as $row): ?>
    <tr>
        <td><?= $row['user_nama'] ?></td>
        <td><?= $row['prodi'] ?></td>
        <td><?= $row['nama_aset'] ?></td>
        <td><?= $row['tanggal_mulai'] ?> s/d <?= $row['tanggal_selesai'] ?></td>
        <td><?= strtoupper($row['status']) ?></td>
        <td><?= $row['nama_admin'] ?? '-' ?></td>
    </tr>
    <?php endforeach; ?>
</table>