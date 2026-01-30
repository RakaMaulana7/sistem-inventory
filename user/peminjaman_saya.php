<?php
session_start();
require "../config/database.php";
require "../auth/auth_helper.php";

cek_kemanan_login($pdo);

if (!isset($_SESSION['login']) || $_SESSION['role'] !== 'user') {
    header("Location: ../login.html");
    exit;
}

$user_id = $_SESSION['user_id'] ?? $_SESSION['id'];

// Query untuk notifikasi (approval/rejection yang belum dibaca)
$stmtNotifPending = $pdo->prepare("
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
    WHERE p.user_id = ? AND p.status IN ('approved', 'rejected') AND p.is_read = 0
    ORDER BY p.updated_at DESC
");
$stmtNotifPending->execute([$user_id]);
$notifikasiPending = $stmtNotifPending->fetchAll(PDO::FETCH_ASSOC);

try {
    // QUERY RUANGAN - Ambil dari kolom fasilitas langsung
    // QUERY RUANGAN - Gunakan JOIN ke tabel gedung
    $stmtR = $pdo->prepare("SELECT p.*, r.nama_ruangan as nama_item, 
        g.nama_gedung as gedung, r.lantai, r.fasilitas as list_fasilitas
        FROM peminjaman p 
        JOIN ruangan r ON p.item_id = r.id 
        LEFT JOIN gedung g ON r.gedung_id = g.id
        WHERE p.user_id = ? AND p.kategori = 'ruangan' 
        AND p.status NOT IN ('kembali', 'rejected') 
        ORDER BY p.created_at DESC");
    $stmtR->execute([$user_id]);
    $data_ruangan = $stmtR->fetchAll(PDO::FETCH_ASSOC);

    $stmtS = $pdo->prepare("SELECT p.*, s.nama as nama_item, '' as list_fasilitas
        FROM peminjaman p JOIN sarana s ON p.item_id = s.id 
        WHERE p.user_id = ? AND p.kategori = 'sarana' 
        AND p.status NOT IN ('kembali', 'rejected') 
        ORDER BY p.created_at DESC");
    $stmtS->execute([$user_id]);
    $data_sarana = $stmtS->fetchAll(PDO::FETCH_ASSOC);

    $stmtT = $pdo->prepare("SELECT p.*, t.nama as nama_item, '' as list_fasilitas
        FROM peminjaman p JOIN transportasi t ON p.item_id = t.id 
        WHERE p.user_id = ? AND p.kategori = 'transportasi' 
        AND p.status NOT IN ('kembali', 'rejected') 
        ORDER BY p.created_at DESC");
    $stmtT->execute([$user_id]);
    $data_transportasi = $stmtT->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error Database: " . $e->getMessage());
}

function getStatusBadge($status) {
    switch ($status) {
        case 'pending': return '<span class="px-3 py-1 rounded-full text-[10px] font-bold bg-amber-50 text-amber-600 border border-amber-200 uppercase tracking-wider">Menunggu ACC Admin</span>';
        case 'approved': return '<span class="px-3 py-1 rounded-full text-[10px] font-bold bg-green-50 text-green-600 border border-green-200 uppercase tracking-wider">Disetujui</span>';
        case 'returning': return '<span class="px-3 py-1 rounded-full text-[10px] font-bold bg-purple-50 text-purple-600 border border-purple-200 uppercase tracking-wider">Menunggu Verifikasi</span>';
        default: return '<span class="px-3 py-1 rounded-full text-[10px] font-bold bg-gray-50 text-gray-600 border border-gray-200 uppercase tracking-wider">'.$status.'</span>';
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="../assets/kampusums.png" />
    <title>Peminjaman Saya</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        [x-cloak] { display: none !important; }
        
        /* Animasi Pulse */
        @keyframes pulse-once {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.8; }
        }
        .animate-pulse-once {
            animation: pulse-once 2s ease-in-out;
        }
        
        /* Custom Scrollbar */
        .overflow-y-auto::-webkit-scrollbar {
            width: 6px;
        }
        .overflow-y-auto::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 10px;
        }
        .overflow-y-auto::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
        }
        .overflow-y-auto::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
    </style>
</head>
<body class="bg-gray-50 flex flex-col min-h-screen" x-data="{ showReturnModal: false, selectedId: '', selectedName: '', selectedFasilitas: [] }">
    <?php include "../components/UserNavbar.php"; ?>

    <main class="pt-7 pb-20 px-4 md:px-8 max-w-7xl mx-auto w-full grow">
        <!-- NOTIFIKASI APPROVAL/REJECTION -->
        <?php if (!empty($notifikasiPending)): ?>
            <?php foreach ($notifikasiPending as $notif): ?>
                <?php 
                    $isApproved = $notif['status'] === 'approved';
                    $bgColor = $isApproved ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200';
                    $icon = $isApproved ? 'check-circle' : 'x-circle';
                    $iconColor = $isApproved ? 'text-green-600' : 'text-red-600';
                    $textColor = $isApproved ? 'text-green-800' : 'text-red-800';
                    $labelStatus = $isApproved ? 'Disetujui' : 'Ditolak';
                ?>
                <div class="mb-6 p-4 md:p-5 rounded-2xl border <?php echo $bgColor; ?> flex items-start gap-4 animate-pulse-once">
                    <div class="shrink-0 w-12 h-12 rounded-full flex items-center justify-center flex-shrink-0 <?php echo $isApproved ? 'bg-green-100' : 'bg-red-100'; ?>">
                        <i data-lucide="<?php echo $icon; ?>" class="w-6 h-6 <?php echo $iconColor; ?>"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-bold <?php echo $textColor; ?> mb-1">Permintaan Peminjaman <?php echo $labelStatus; ?></p>
                        <p class="text-sm text-gray-600 mb-3">
                            Peminjaman <strong><?php echo htmlspecialchars($notif['nama_aset']); ?></strong> telah <strong><?php echo strtolower($labelStatus); ?></strong> oleh admin.
                        </p>
                        <a href="read_notif.php?id=<?php echo $notif['id']; ?>" class="inline-block text-xs font-bold bg-white px-4 py-2 rounded-lg border <?php echo $isApproved ? 'border-green-200 text-green-700 hover:bg-green-50' : 'border-red-200 text-red-700 hover:bg-red-50'; ?> transition-colors">
                            Tandai Sudah Dibaca
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
            <div>
                <h1 class="text-3xl font-bold text-gray-800 tracking-tight">Peminjaman Saya</h1>
                <p class="text-gray-500 mt-1">Daftar permohonan yang sedang diproses oleh admin.</p>
            </div>
            <a href="dashboard.php" class="flex items-center gap-2 px-5 py-2.5 bg-white border border-gray-200 text-gray-600 rounded-2xl hover:bg-gray-50 transition-all shadow-sm w-fit font-medium">
                <i data-lucide="home" class="w-4 h-4"></i> Beranda
            </a>
        </div>

        <div class="flex p-1.5 bg-gray-200/50 rounded-2xl mb-8 w-fit backdrop-blur-sm">
            <button onclick="showTab('ruangan', this)" class="tab-btn flex items-center gap-2 px-6 py-2.5 rounded-xl text-sm font-bold transition-all text-gray-500 hover:text-gray-700">
                <i data-lucide="building" class="w-4 h-4"></i> Ruangan
            </button>
            <button onclick="showTab('sarana', this)" class="tab-btn flex items-center gap-2 px-6 py-2.5 rounded-xl text-sm font-bold transition-all text-gray-500 hover:text-gray-700">
                <i data-lucide="box" class="w-4 h-4"></i> Sarana
            </button>
            <button onclick="showTab('transportasi', this)" class="tab-btn flex items-center gap-2 px-6 py-2.5 rounded-xl text-sm font-bold transition-all text-gray-500 hover:text-gray-700">
                <i data-lucide="truck" class="w-4 h-4"></i> Transportasi
            </button>
        </div>

        <div class="bg-white rounded-4xl shadow-sm border border-gray-100 overflow-hidden">
            <div id="ruangan" class="tab-content active"><?php renderTable($data_ruangan, 'building'); ?></div>
            <div id="sarana" class="tab-content"><?php renderTable($data_sarana, 'box'); ?></div>
            <div id="transportasi" class="tab-content"><?php renderTable($data_transportasi, 'truck'); ?></div>
        </div>
    </main>

    <div x-show="showReturnModal" x-transition class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" x-cloak>
        <div class="bg-white rounded-3xl w-full max-w-lg overflow-hidden shadow-2xl border border-white/20 max-h-[90vh] flex flex-col" @click.away="showReturnModal = false">
            <!-- HEADER - FIXED -->
            <div class="px-8 py-6 border-b border-gray-100 flex justify-between items-center bg-gradient-to-r from-gray-50 to-white shrink-0">
                <div>
                    <h3 class="font-bold text-xl text-gray-800 tracking-tight flex items-center gap-2">
                        <i data-lucide="package-check" class="w-5 h-5 text-red-600"></i>
                        Pengembalian Aset
                    </h3>
                    <p class="text-xs text-gray-500 mt-1">Lengkapi form di bawah untuk mengembalikan aset</p>
                </div>
                <button @click="showReturnModal = false" type="button" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>

            <!-- FORM CONTENT - SCROLLABLE -->
            <form action="proses_pengembalian.php" method="POST" enctype="multipart/form-data" class="flex flex-col flex-1 overflow-hidden">
                <input type="hidden" name="peminjaman_id" :value="selectedId"> 
                
                <!-- SCROLLABLE AREA -->
                <div class="px-8 py-6 space-y-5 overflow-y-auto flex-1">
                    <!-- INFO ITEM -->
                    <div class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-2xl px-5 py-4 border border-blue-100">
                        <p class="text-[10px] text-blue-600 uppercase font-black mb-2 flex items-center gap-1.5">
                            <i data-lucide="info" class="w-3 h-3"></i>
                            Item yang Anda kembalikan
                        </p>
                        <p class="font-bold text-gray-800 text-lg" x-text="selectedName"></p>
                        
                    </div>

                    <!-- DAFTAR FASILITAS -->
                    <template x-if="selectedFasilitas && selectedFasilitas.length > 0">
                        <div class="space-y-3">
                            <p class="text-[10px] text-purple-600 uppercase font-black px-1 flex items-center gap-1.5">
                                <i data-lucide="list" class="w-3 h-3"></i>
                                Daftar Fasilitas Ruangan
                            </p>
                            <div class="border border-purple-200 rounded-2xl overflow-hidden bg-purple-50/30 shadow-sm">
                                <div class="max-h-48 overflow-y-auto">
                                    <table class="w-full text-left text-[11px]">
                                        <thead class="bg-purple-100 text-purple-700 font-bold uppercase sticky top-0">
                                            <tr>
                                                <th class="py-2.5 px-4 w-12 text-center">No</th>
                                                <th class="py-2.5 px-4">Nama & Label Fasilitas</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-purple-100/50">
                                            <template x-for="(item, index) in selectedFasilitas" :key="index">
                                                <tr class="hover:bg-purple-100/30 transition-colors">
                                                    <td class="py-2.5 px-4 text-purple-500 font-bold text-center" x-text="index + 1"></td>
                                                    <td class="py-2.5 px-4 text-gray-800 font-medium" x-text="item"></td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- UPLOAD FOTO -->
                    <div x-data="{ preview: null }">
                        <label class="block text-[10px] font-black text-gray-600 uppercase mb-3 flex items-center gap-1.5">
                            <i data-lucide="camera" class="w-3 h-3"></i>
                            Bukti Kondisi Akhir (Wajib)
                        </label>
                        <div class="relative border-2 border-dashed border-gray-300 rounded-2xl p-6 text-center cursor-pointer hover:border-red-400 transition-all group bg-gray-50">
                            <input 
                                type="file" 
                                name="foto_kondisi" 
                                class="absolute inset-0 opacity-0 z-10 cursor-pointer" 
                                required 
                                accept="image/*"
                                @change="const file = $event.target.files[0]; if(file) preview = URL.createObjectURL(file); setTimeout(() => lucide.createIcons(), 50);">
                            
                            <template x-if="!preview">
                                <div class="py-4">
                                    <div class="w-16 h-16 mx-auto mb-3 bg-gray-200 rounded-full flex items-center justify-center group-hover:bg-red-50 transition-all">
                                        <i data-lucide="upload" class="w-8 h-8 text-gray-400 group-hover:text-red-500"></i>
                                    </div>
                                    <p class="text-sm font-semibold text-gray-600 mb-1">Klik untuk upload foto</p>
                                    <p class="text-xs text-gray-400">PNG, JPG, JPEG (Max 5MB)</p>
                                </div>
                            </template>
                            
                            <template x-if="preview">
                                <div class="relative">
                                    <img :src="preview" class="w-full h-48 object-cover rounded-xl shadow-lg">
                                    <div class="absolute top-2 right-2 bg-green-500 text-white px-3 py-1 rounded-full text-[10px] font-bold flex items-center gap-1">
                                        <i data-lucide="check" class="w-3 h-3"></i>
                                        Foto dipilih
                                    </div>
                                </div>
                            </template>
                        </div>
                        <p class="text-[10px] text-gray-500 mt-2 flex items-center gap-1.5">
                            <i data-lucide="alert-circle" class="w-3 h-3"></i>
                            Pastikan foto menunjukkan kondisi aset dengan jelas
                        </p>
                    </div>
                </div>

                <!-- FOOTER BUTTON - FIXED -->
                <div class="px-8 py-5 border-t border-gray-200 bg-gray-50 shrink-0">
                    <div class="flex gap-3">
                        <button 
                            type="button" 
                            @click="showReturnModal = false" 
                            class="flex-1 px-5 py-3 border-2 border-gray-300 text-gray-700 rounded-xl font-bold hover:bg-gray-100 transition-all">
                            Batal
                        </button>
                        <button 
                            type="submit" 
                            class="flex-1 px-5 py-3 bg-gradient-to-r from-red-600 to-red-700 text-white rounded-xl font-bold hover:from-red-700 hover:to-red-800 transition-all shadow-lg shadow-red-200 flex items-center justify-center gap-2">
                            <i data-lucide="send" class="w-4 h-4"></i>
                            Kirim Laporan
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php function renderTable($data, $icon) { ?>
        <table class="w-full text-left table-auto">
            <thead>
                <tr class="bg-gray-50/50 border-b border-gray-100 text-gray-400 text-[10px] uppercase font-black">
                    <th class="py-6 px-8">Detail Item</th>
                    <th class="py-6 px-8">Waktu Pelaksanaan</th>
                    <th class="py-6 px-8">Status</th>
                    <th class="py-6 px-8 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <?php if (empty($data)): ?>
                    <tr><td colspan="4" class="py-20 text-center opacity-30 font-bold">Tidak ada peminjaman aktif</td></tr>
                <?php else: foreach ($data as $row): 
                    // Ganti delimiter dari || menjadi , (koma)
                    $fasilitasArray = !empty($row['list_fasilitas']) ? explode(', ', trim($row['list_fasilitas'])) : [];
                    $jsonFasilitas = json_encode($fasilitasArray);
                ?>
                    <tr class="odd:bg-gray-50 even:bg-white hover:bg-gray-100 transition-all">
                        <td class="py-6 px-8">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 rounded-2xl bg-[#d13b1f]/5 text-[#d13b1f] flex items-center justify-center"><i data-lucide="<?= $icon ?>" class="w-6 h-6"></i></div>
                                <div>
                                    <p class="font-bold text-gray-800"><?= htmlspecialchars($row['nama_item']) ?></p>
                                    <p class="text-xs text-gray-400">#<?= $row['id'] ?></p>
                                </div>
                            </div>
                        </td>
                        <td class="py-6 px-8">
                            <div class="text-sm font-semibold text-gray-700"><?= date('d M Y', strtotime($row['tanggal_mulai'])) ?></div>
                            <div class="text-xs text-gray-400"><?= $row['waktu_mulai'] ?> - <?= $row['waktu_selesai'] ?></div>
                        </td>
                        <td class="py-6 px-8"><?= getStatusBadge($row['status']) ?></td>
                        <td class="py-6 px-8 text-center">
                            <?php if($row['status'] === 'approved'): ?>
                                <button 
                                    data-id="<?= $row['id'] ?>" 
                                    data-name="<?= htmlspecialchars($row['nama_item']) ?>"
                                    data-fasilitas='<?= $jsonFasilitas ?>'
                                    @click="selectedId = $el.dataset.id; 
                                            selectedName = $el.dataset.name; 
                                            selectedFasilitas = JSON.parse($el.dataset.fasilitas); 
                                            showReturnModal = true;
                                            setTimeout(() => lucide.createIcons(), 100);" 
                                    class="bg-[#d13b1f] text-white px-5 py-2.5 rounded-xl text-[10px] font-black uppercase">
                                    Kembalikan
                                </button>
                            <?php else: ?>
                                <i data-lucide="eye" class="w-5 h-5 text-gray-300 mx-auto"></i>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    <?php } ?>
    <br><br><br><br><br><br><br><br><br><br><br>
    <?php include "../components/Footer.php"; ?>
    <script>
        function showTab(tabId, element) {
            document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('bg-white', 'text-gray-900', 'shadow-sm');
                btn.classList.add('text-gray-500');
            });
            element.classList.add('bg-white', 'text-gray-900', 'shadow-sm');
            element.classList.remove('text-gray-500');
        }

        // Auto-open tab from URL parameter
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const tab = urlParams.get('tab') || 'ruangan';
            const tabButton = document.querySelector(`button[onclick="showTab('${tab}', this)"]`);
            if (tabButton) {
                showTab(tab, tabButton);
            }
        });

        lucide.createIcons();
    </script>
</body>
</html>