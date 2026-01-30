<?php
session_start();
require "../config/database.php";
require "../auth/auth_helper.php";

cek_kemanan_login($pdo);

if (!isset($_SESSION['login'])) {
    header("Location: ../login.html");
    exit;
}

$user_id = $_SESSION['user_id'] ?? $_SESSION['id'];

try {
    // Query untuk notifikasi verifikasi/pengembalian yang belum dibaca
    $stmtNotifVerify = $pdo->prepare("
        SELECT p.id, p.status, p.kategori, p.updated_at,
        CASE 
            WHEN p.kategori = 'ruangan' THEN r.nama_ruangan
            WHEN p.kategori = 'sarana' THEN s.nama
            WHEN p.kategori = 'transportasi' THEN t.nama
        END as nama_aset
        FROM peminjaman p
        LEFT JOIN ruangan r ON p.item_id = r.id AND p.kategori = 'ruangan'
        LEFT JOIN sarana s ON p.item_id = s.id AND p.kategori = 'sarana'
        LEFT JOIN transportasi t ON p.item_id = t.id AND p.kategori = 'transportasi'
        WHERE p.user_id = ? AND p.status IN ('selesai', 'kembali', 'returning') AND p.is_read = 0
        ORDER BY p.updated_at DESC
    ");
    $stmtNotifVerify->execute([$user_id]);
    $notifikasiVerifikasi = $stmtNotifVerify->fetchAll(PDO::FETCH_ASSOC);
    
    // Ambil semua data riwayat sekaligus untuk Alpine.js
    // Kita gunakan UNION atau query terpisah lalu digabung di PHP
    
    // 1. Riwayat Ruangan
    $stmtR = $pdo->prepare("SELECT p.*, r.nama_ruangan as nama_item 
        FROM peminjaman p JOIN ruangan r ON p.item_id = r.id 
        WHERE p.user_id = ? AND p.kategori = 'ruangan' 
        AND p.status IN ('kembali', 'rejected', 'selesai', 'returning') 
        ORDER BY p.created_at DESC");
    $stmtR->execute([$user_id]);
    $riwayat_ruangan = $stmtR->fetchAll(PDO::FETCH_ASSOC);

    // 2. Riwayat Sarana
    $stmtS = $pdo->prepare("SELECT p.*, s.nama as nama_item 
        FROM peminjaman p JOIN sarana s ON p.item_id = s.id 
        WHERE p.user_id = ? AND p.kategori = 'sarana' 
        AND p.status IN ('kembali', 'rejected', 'selesai', 'returning') 
        ORDER BY p.created_at DESC");
    $stmtS->execute([$user_id]);
    $riwayat_sarana = $stmtS->fetchAll(PDO::FETCH_ASSOC);

    // 3. Riwayat Transportasi
    $stmtT = $pdo->prepare("SELECT p.*, t.nama as nama_item 
        FROM peminjaman p JOIN transportasi t ON p.item_id = t.id 
        WHERE p.user_id = ? AND p.kategori = 'transportasi' 
        AND p.status IN ('kembali', 'rejected', 'selesai', 'returning') 
        ORDER BY p.created_at DESC");
    $stmtT->execute([$user_id]);
    $riwayat_transportasi = $stmtT->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

function getStatusBadge($status) {
    $s = strtolower($status);
    if ($s === 'selesai' || $s === 'kembali') {
        return '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold bg-green-50 text-green-600 border border-green-200 uppercase"><i data-lucide="check-circle" class="w-3 h-3"></i> Selesai</span>';
    } else if ($s === 'rejected') {
        return '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold bg-red-50 text-red-600 border border-red-200 uppercase"><i data-lucide="x-circle" class="w-3 h-3"></i> Ditolak</span>';
    }
    return '<span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-gray-50 text-gray-500 uppercase">'.$status.'</span>';
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../assets/kampusums.png" />
    <title>Riwayat Peminjaman</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        [x-cloak] { display: none !important; }
        
        /* Animasi Pulse */
        @keyframes pulse-once {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.8; }
        }
        .animate-pulse-once {
            animation: pulse-once 2s ease-in-out;
        }
    </style>
</head>
<body class="bg-gray-50 flex flex-col min-h-screen" x-data="{ tab: getInitialTab() }" x-init="$watch('tab', () => {})" x-cloak>
    <?php include "../components/UserNavbar.php"; ?>

    <main class="pt-7 pb-20 px-4 md:px-8 max-w-7xl mx-auto w-full grow">
        <!-- NOTIFIKASI VERIFIKASI/PENGEMBALIAN -->
        <?php if (!empty($notifikasiVerifikasi)): ?>
            <?php foreach ($notifikasiVerifikasi as $notif): ?>
                <?php 
                    $isSelesai = $notif['status'] === 'selesai';
                    $bgColor = 'bg-blue-50 border-blue-200';
                    $icon = 'check-circle-2';
                    $iconColor = 'text-blue-600';
                    $textColor = 'text-blue-800';
                    $labelStatus = $isSelesai ? 'Telah Selesai' : 'Telah Dikembalikan';
                ?>
                <div class="mb-6 p-4 md:p-5 rounded-2xl border <?php echo $bgColor; ?> flex items-start gap-4 animate-pulse-once">
                    <div class="shrink-0 w-12 h-12 rounded-full flex items-center justify-center flex-shrink-0 bg-blue-100">
                        <i data-lucide="<?php echo $icon; ?>" class="w-6 h-6 <?php echo $iconColor; ?>"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-bold <?php echo $textColor; ?> mb-1">Peminjaman <?php echo $labelStatus; ?></p>
                        <p class="text-sm text-gray-600 mb-3">
                            Peminjaman <strong><?php echo htmlspecialchars($notif['nama_aset']); ?></strong> <?php echo strtolower($labelStatus); ?> dan sudah tersimpan di riwayat.
                        </p>
                        <a href="read_notif.php?id=<?php echo $notif['id']; ?>" class="inline-block text-xs font-bold bg-white px-4 py-2 rounded-lg border border-blue-200 text-blue-700 hover:bg-blue-50 transition-colors">
                            Tandai Sudah Dibaca
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-800 tracking-tight">Riwayat Peminjaman</h1>
                <p class="text-gray-500 mt-1">Arsip seluruh kegiatan peminjaman Anda yang telah selesai.</p>
            </div>
            <a href="dashboard.php" class="flex items-center gap-2 px-4 py-2 bg-white border border-gray-200 text-gray-600 rounded-xl hover:bg-gray-50 transition-colors font-medium shadow-sm">
                <i data-lucide="arrow-left" class="w-4 h-4"></i> Kembali
            </a>
        </div>

        <div class="flex p-1.5 bg-gray-200/50 rounded-2xl mb-8 w-fit backdrop-blur-sm">
            <button @click="tab = 'ruangan'" 
                :class="tab === 'ruangan' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                class="flex items-center gap-2 px-6 py-2.5 rounded-xl text-sm font-bold transition-all">
                <i data-lucide="building" class="w-4 h-4"></i> Ruangan
            </button>
            <button @click="tab = 'sarana'" 
                :class="tab === 'sarana' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                class="flex items-center gap-2 px-6 py-2.5 rounded-xl text-sm font-bold transition-all">
                <i data-lucide="box" class="w-4 h-4"></i> Sarana
            </button>
            <button @click="tab = 'transportasi'" 
                :class="tab === 'transportasi' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                class="flex items-center gap-2 px-6 py-2.5 rounded-xl text-sm font-bold transition-all">
                <i data-lucide="truck" class="w-4 h-4"></i> Transportasi
            </button>
        </div>

        <div class="bg-white rounded-4xl shadow-sm border border-gray-100 overflow-hidden">
            
            <div x-show="tab === 'ruangan'" x-cloak>
                <?php renderRiwayatTable($riwayat_ruangan, 'building'); ?>
            </div>

            <div x-show="tab === 'sarana'" x-cloak>
                <?php renderRiwayatTable($riwayat_sarana, 'box'); ?>
            </div>

            <div x-show="tab === 'transportasi'" x-cloak>
                <?php renderRiwayatTable($riwayat_transportasi, 'truck'); ?>
            </div>

        </div>
    </main>

    <?php 
    // Fungsi Helper untuk merender tabel agar kode lebih bersih
    function renderRiwayatTable($data, $icon) { ?>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-gray-50/50 border-b border-gray-100 text-gray-400 text-[10px] uppercase font-black">
                        <th class="py-6 px-8">Item</th>
                        <th class="py-6 px-8">Tanggal</th>
                        <th class="py-6 px-8">Waktu Pelaksanaan</th>
                        <th class="py-6 px-8 text-center">Status Akhir</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php if (empty($data)): ?>
                        <tr>
                            <td colspan="4" class="py-20 text-center opacity-30">
                                <i data-lucide="folder-open" class="w-12 h-12 mx-auto mb-2 text-gray-300"></i>
                                <p class="font-bold">Belum ada riwayat di kategori ini</p>
                            </td>
                        </tr>
                    <?php else: foreach ($data as $row): ?>
                        <tr class="hover:bg-gray-50/50 transition-all">
                            <td class="py-6 px-8">
                                <div class="flex items-center gap-4">
                                    <div class="w-10 h-10 rounded-xl bg-gray-100 text-gray-500 flex items-center justify-center">
                                        <i data-lucide="<?= $icon ?>" class="w-5 h-5"></i>
                                    </div>
                                    <div>
                                        <p class="font-bold text-gray-800"><?= htmlspecialchars($row['nama_item']) ?></p>
                                    </div>
                                </div>
                            </td>
                            <td class="py-6 px-8 text-sm font-semibold text-gray-600">
                                <?= date('d M Y', strtotime($row['tanggal_mulai'])) ?>
                            </td>
                            <td class="py-6 px-8 text-sm">
                                <span class="bg-gray-100 px-3 py-1 rounded-lg font-mono text-gray-600">
                                    <?= date('H:i', strtotime($row['waktu_mulai'])) ?> - <?= date('H:i', strtotime($row['waktu_selesai'])) ?>
                                </span>
                            </td>
                            <td class="py-6 px-8 text-center">
                                <?= getStatusBadge($row['status']) ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    <?php } ?>
    <br><br><br><br><br><br><br><br><br><br>

    <?php include "../components/Footer.php"; ?>
    <script>
        lucide.createIcons();
        
        function getInitialTab() {
            const urlParams = new URLSearchParams(window.location.search);
            const tab = urlParams.get('tab') || 'ruangan';
            return tab;
        }
    </script>
</body>
</html>