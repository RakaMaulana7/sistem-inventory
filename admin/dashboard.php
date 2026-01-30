<?php
session_start();
if (!isset($_SESSION['login']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.html"); 
    exit;
}

require "../config/database.php";
require "../auth/auth_helper.php";

cek_kemanan_login($pdo);


$admin_name = $_SESSION['nama'] ?? 'Admin';

// --- DATA STATISTIK ---
$totalUser = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn() ?: 0;
$totalAdmin = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn() ?: 0;
$totalSemua = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn() ?: 0;
$totalPerluTindakan = $pdo->query("SELECT COUNT(*) FROM peminjaman WHERE status IN ('pending', 'returning')")->fetchColumn() ?: 0;

// --- AMBIL SEMUA DATA NOTIFIKASI SEKALIGUS ---
$stmtAll = $pdo->query("
    SELECT 
        p.*, 
        p.kategori as jenis_aset, 
        p.tanggal_mulai as tgl_pinjam, 
        p.tanggal_selesai as tgl_kembali,
        p.telepon_utama as telepon,
        p.telepon_darurat as telp_darurat,
        u.nama as nama_user, 
        u.prodi,
        CASE 
            WHEN p.kategori = 'ruangan' THEN IFNULL(r.nama_ruangan, 'Ruangan Tidak Ditemukan')
            WHEN p.kategori = 'sarana' THEN IFNULL(s.nama, 'Sarana Tidak Ditemukan')
            WHEN p.kategori = 'transportasi' THEN IFNULL(t.nama, 'Transportasi Tidak Ditemukan')
            ELSE 'Aset Tidak Diketahui'
        END as nama_aset,
        -- Ambil data untuk ruangan
        CASE 
            WHEN p.kategori = 'ruangan' THEN r.fasilitas
            ELSE NULL
        END as detail_unit_raw,
        -- Ambil data untuk sarana
        CASE 
            WHEN p.kategori = 'sarana' THEN s.kode_label
            ELSE NULL
        END as kode_label_sarana,
        CASE 
            WHEN p.kategori = 'sarana' THEN s.kondisi
            ELSE NULL
        END as kondisi_sarana
    FROM peminjaman p 
    JOIN users u ON p.user_id = u.id 
    LEFT JOIN ruangan r ON p.item_id = r.id AND p.kategori = 'ruangan'
    LEFT JOIN sarana s ON p.item_id = s.id AND p.kategori = 'sarana'
    LEFT JOIN transportasi t ON p.item_id = t.id AND p.kategori = 'transportasi'
    WHERE p.status IN ('pending', 'approved', 'returning') 
    ORDER BY FIELD(p.status, 'pending', 'returning', 'approved'), p.created_at DESC
");

$semuaNotifikasi = $stmtAll->fetchAll(PDO::FETCH_ASSOC);

// PERBAIKAN: Parse data fasilitas dan sarana
foreach ($semuaNotifikasi as &$n) {
    $units = [];
    
    // UNTUK RUANGAN: Parse fasilitas dari string CSV
    if ($n['jenis_aset'] === 'ruangan' && !empty($n['detail_unit_raw'])) {
        $items = explode(', ', trim($n['detail_unit_raw']));
        
        foreach ($items as $item) {
            $item = trim($item);
            if (empty($item)) continue;
            
            if (preg_match('/^(.+?)\s*\[(.+?)\]$/', $item, $matches)) {
                $namaBarang = trim($matches[1]);
                $kodeLabel = trim($matches[2]);
                
                $stmtKondisi = $pdo->prepare("SELECT kondisi FROM sarana WHERE kode_label = ? LIMIT 1");
                $stmtKondisi->execute([$kodeLabel]);
                $kondisiData = $stmtKondisi->fetch(PDO::FETCH_ASSOC);
                $kondisi = $kondisiData ? $kondisiData['kondisi'] : 'Baik';
                
                $units[] = [
                    'label' => $kodeLabel,
                    'nama' => $namaBarang,
                    'kondisi' => $kondisi,
                    'catatan' => ''
                ];
            } else {
                $units[] = [
                    'label' => $item,
                    'nama' => $item,
                    'kondisi' => 'Baik',
                    'catatan' => ''
                ];
            }
        }
    }
    
    // UNTUK SARANA: Buat array detail dari data sarana
    if ($n['jenis_aset'] === 'sarana' && !empty($n['kode_label_sarana'])) {
        $units[] = [
            'label' => $n['kode_label_sarana'],
            'nama' => $n['nama_aset'],
            'jumlah' => $n['jumlah'] ?? 1,
            'kondisi' => $n['kondisi_sarana'] ?? 'Baik',
            'catatan' => ''
        ];
    }
    
    $n['detail_unit_fasilitas'] = $units;
}

// --- HITUNG BADGE NOTIFIKASI ---
function getCount($pdo, $type) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM peminjaman WHERE kategori = ? AND status IN ('pending', 'returning')");
    $stmt->execute([$type]);
    return $stmt->fetchColumn() ?: 0;
}
$countR = getCount($pdo, 'ruangan');
$countS = getCount($pdo, 'sarana');
$countT = getCount($pdo, 'transportasi');

// --- DAFTAR PENGGUNA DENGAN PAGINATION ---
$usersPerPage = 10;
$userPage = isset($_GET['user_page']) ? (int)$_GET['user_page'] : 1;
$userOffset = ($userPage - 1) * $usersPerPage;

// Hitung total users
$totalUsersCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalUserPages = ceil($totalUsersCount / $usersPerPage);

// Ambil data users dengan limit
$usersStmt = $pdo->prepare("SELECT * FROM users ORDER BY role ASC, nama ASC LIMIT ? OFFSET ?");
$usersStmt->execute([$usersPerPage, $userOffset]);
$users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../assets/kampusums.png" />

    <title>Dashboard Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
        @keyframes slide-up {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .animate-slide-up { animation: slide-up 0.4s ease-out; }

        body.modal-open > *:not(.logout-modal) {
            filter: blur(4px);
            transition: filter 0.2s ease;
        }
    </style>
</head>
<body class="bg-slate-50 font-sans flex" x-data="{ 
    showVerifyModal: false, 
    selectedItem: null,
    activeTab: 'ruangan',
    allNotif: <?= htmlspecialchars(json_encode($semuaNotifikasi), ENT_QUOTES, 'UTF-8') ?>,
    openVerify(item) {
        this.selectedItem = item;
        this.showVerifyModal = true;
        setTimeout(() => lucide.createIcons(), 100);
    }
}">

    <?php include "../components/AdminNavbar.php"; ?>

    <main class="flex-1 ml-0 md:ml-60 p-4 md:p-8">
        
        <?php if(isset($_GET['status']) && $_GET['status'] === 'verified'): ?>
        <div class="fixed top-4 right-4 z-50 bg-green-500 text-white px-6 py-4 rounded-2xl shadow-2xl flex items-center gap-3 animate-slide-up">
            <i data-lucide="check-circle" class="w-5 h-5"></i>
            <div>
                <p class="font-bold">Verifikasi Berhasil!</p>
                <p class="text-xs opacity-90"><?= htmlspecialchars($_GET['msg'] ?? 'Kondisi fasilitas telah diperbarui') ?></p>
            </div>
        </div>
        <script>
            setTimeout(() => {
                window.location.href = 'dashboard.php';
            }, 3000);
        </script>
        <?php endif; ?>
        
        <header class="mb-8 pt-16 md:pt-0">
            <h1 class="text-3xl font-extrabold text-gray-800">
                Halo, <span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-600 to-purple-600 capitalize"><?= htmlspecialchars($admin_name) ?>!</span> 👋
            </h1>
            <p class="text-gray-500 mt-1">Berikut adalah ringkasan aktivitas kampus hari ini.</p>
        </header>

        <section class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
            <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-500 to-indigo-600 p-6 text-white shadow-xl shadow-blue-200">
                <div class="relative z-5 flex items-center justify-between">
                    <div><p class="text-blue-100 text-sm font-medium mb-1">Total Pengguna Yang Terdaftar</p><h3 class="text-4xl font-bold"><?= $totalSemua ?></h3></div>
                    <div class="p-3 bg-white/20 rounded-2xl backdrop-blur-sm"><i data-lucide="users"></i></div>
                </div>
            </div>
            <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-emerald-400 to-teal-600 p-6 text-white shadow-xl shadow-emerald-200">
                <div class="relative z-10 flex items-center justify-between">
                    <div><p class="text-emerald-100 text-sm font-medium mb-1">Pengguna (Mahasiswa)</p><h3 class="text-4xl font-bold"><?= $totalUser ?></h3></div>
                    <div class="p-3 bg-white/20 rounded-2xl backdrop-blur-sm"><i data-lucide="user"></i></div>
                </div>
            </div>
            <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-purple-500 to-fuchsia-600 p-6 text-white shadow-xl shadow-purple-200">
                <div class="relative z-10 flex items-center justify-between">
                    <div><p class="text-purple-100 text-sm font-medium mb-1">Pengguna (Admin)</p><h3 class="text-4xl font-bold"><?= $totalAdmin ?></h3></div>
                    <div class="p-3 bg-white/20 rounded-2xl backdrop-blur-sm"><i data-lucide="shield"></i></div>
                </div>
            </div>
        </section>

        <section class="bg-white rounded-3xl shadow-sm border border-gray-100 mb-10 overflow-hidden">
            <div class="p-6 border-b border-gray-100 flex flex-col lg:flex-row justify-between items-center gap-4 bg-gradient-to-r from-gray-50 to-white">
                <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                    <div class="p-2 bg-orange-100 rounded-lg text-orange-600"><i data-lucide="bell" class="w-5 h-5"></i></div>
                    Permintaan & Verifikasi
                </h2>
                
                <div class="flex bg-gray-100 p-1.5 rounded-xl overflow-x-auto">
                    <button @click="activeTab = 'ruangan'" :class="activeTab === 'ruangan' ? 'bg-orange-500 text-white shadow-lg shadow-orange-200' : 'text-gray-500 hover:text-gray-700'" class="px-5 py-2 rounded-lg text-sm font-bold transition-all flex items-center gap-2 whitespace-nowrap">
                        <i data-lucide="building" class="w-4 h-4"></i> Ruangan 
                        <?php if($countR > 0): ?><span class="bg-red-500 text-white text-[10px] px-1.5 py-0.5 rounded-full"><?= $countR ?></span><?php endif; ?>
                    </button>
                    <button @click="activeTab = 'sarana'" :class="activeTab === 'sarana' ? 'bg-blue-500 text-white shadow-lg shadow-blue-200' : 'text-gray-500 hover:text-gray-700'" class="px-5 py-2 rounded-lg text-sm font-bold transition-all flex items-center gap-2 whitespace-nowrap">
                        <i data-lucide="wrench" class="w-4 h-4"></i> Sarana
                        <?php if($countS > 0): ?><span class="bg-red-500 text-white text-[10px] px-1.5 py-0.5 rounded-full"><?= $countS ?></span><?php endif; ?>
                    </button>
                    <button @click="activeTab = 'transportasi'" :class="activeTab === 'transportasi' ? 'bg-green-500 text-white shadow-lg shadow-green-200' : 'text-gray-500 hover:text-gray-700'" class="px-5 py-2 rounded-lg text-sm font-bold transition-all flex items-center gap-2 whitespace-nowrap">
                        <i data-lucide="truck" class="w-4 h-4"></i> Transportasi
                        <?php if($countT > 0): ?><span class="bg-red-500 text-white text-[10px] px-1.5 py-0.5 rounded-full"><?= $countT ?></span><?php endif; ?>
                    </button>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-slate-600 font-bold border-b border-gray-200">
                        <tr>
                            <th class="py-4 px-6">User / Prodi</th>
                            <th class="py-4 px-6">Kontak</th>
                            <th class="py-4 px-6">Aset</th>
                            <th class="py-4 px-6">Waktu Pinjam</th>
                            <th class="py-4 px-6">Dokumen</th>
                            <th class="py-4 px-6 text-center">Status</th>
                            <th class="py-4 px-6 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <template x-for="n in allNotif.filter(i => i.jenis_aset === activeTab)" :key="n.id">
                            <tr class="hover:bg-blue-50/30 transition-colors group">
                                <td class="py-4 px-6">
                                    <div class="font-bold text-gray-800" x-text="n.nama_user"></div>
                                    <div class="text-[10px] text-gray-400 uppercase tracking-wider" x-text="n.prodi"></div>
                                </td>
                                <td class="py-4 px-6 font-mono text-xs text-gray-600">
                                    <div class="text-blue-600 font-bold" x-text="n.telepon"></div>
                                    <div class="text-gray-400 italic" x-text="n.telp_darurat"></div>
                                </td>
                                <td class="py-4 px-6 font-semibold text-gray-700" x-text="n.nama_aset"></td>
                                <td class="py-4 px-6 text-xs">
                                    <div class="text-blue-600 font-bold" x-text="n.tgl_pinjam"></div>
                                    <div class="text-gray-400">s/d <span x-text="n.tgl_kembali"></span></div>
                                </td>
                                <td class="py-4 px-6">
                                    <template x-if="n.surat_peminjaman">
                                        <a :href="'../' + n.surat_peminjaman" target="_blank" class="inline-flex items-center gap-1 text-blue-600 hover:underline font-bold text-[10px]">
                                            <i data-lucide="file-text" class="w-3 h-3"></i> SURAT PEMINJAMAN
                                        </a>
                                    </template>
                                    <template x-if="!n.surat_peminjaman">
                                        <span class="text-gray-400 italic text-[10px]">No File</span>
                                    </template>
                                </td>
                                <td class="py-4 px-6 text-center">
                                    <span :class="{
                                        'bg-amber-400': n.status === 'pending',
                                        'bg-blue-500': n.status === 'approved',
                                        'bg-purple-500': n.status === 'returning'
                                    }" class="px-3 py-1 text-white text-[10px] font-bold rounded-full uppercase" x-text="n.status === 'returning' ? 'DIKEMBALIKAN' : n.status"></span>
                                </td>
                                <td class="py-4 px-6 text-right">
                                    <div class="flex justify-end gap-2">
                                        <template x-if="n.status === 'pending'">
                                            <div class="flex gap-2">
                                                <a :href="'proses_peminjaman.php?id=' + n.id + '&action=approve'" 
                                                class="p-2 bg-emerald-100 text-emerald-600 rounded-xl hover:bg-emerald-500 hover:text-white transition-all shadow-sm"
                                                title="Setujui">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                                </a>
                                                
                                                <a :href="'proses_peminjaman.php?id=' + n.id + '&action=reject'" 
                                                class="p-2 bg-rose-100 text-rose-600 rounded-xl hover:bg-rose-500 hover:text-white transition-all shadow-sm"
                                                title="Tolak">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                                                </a>
                                            </div>
                                        </template>

                                        <button @click="openVerify(n)" 
                                                class="flex items-center gap-1 px-3 py-2 bg-blue-100 text-blue-600 rounded-xl hover:bg-blue-500 hover:text-white transition-all font-bold text-xs shadow-sm">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                            Detail
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="allNotif.filter(i => i.jenis_aset === activeTab).length === 0">
                            <td colspan="7" class="py-12 text-center text-gray-400 italic">Tidak ada permintaan saat ini.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="p-6 border-b border-gray-100">
                <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                    <div class="p-2 bg-blue-100 rounded-lg text-blue-600"><i data-lucide="users" class="w-5 h-5"></i></div>
                    Daftar Pengguna
                </h2>
                <p class="text-xs text-gray-500 mt-1">Total <?= $totalUsersCount ?> pengguna terdaftar</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="bg-slate-50 text-slate-600 text-xs uppercase tracking-wider font-bold border-b">
                            <th class="py-3 px-4">Nama Lengkap</th>
                            <th class="py-3 px-4">NIM / ID</th>
                            <th class="py-3 px-4">Prodi</th>
                            <th class="py-3 px-4">Role</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="4" class="py-12 text-center text-gray-400 italic">
                                Tidak ada data pengguna
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $u): ?>
                            <?php if ($u['role'] === 'staf') continue; // 🔥 skip staf ?>
                            <tr class="hover:bg-gray-50/50 transition-colors">
                                <td class="py-3 px-4 font-bold text-slate-700">
                                    <?= htmlspecialchars($u['nama']) ?>
                                </td>
                                <td class="py-3 px-4 text-slate-500 font-mono">
                                    <?= htmlspecialchars($u['username']) ?>
                                </td>
                                <td class="py-3 px-4 text-slate-600">
                                    <?= htmlspecialchars($u['prodi'] ?? '-') ?>
                                </td>
                                <td class="py-3 px-4">
                                    <?php
                                        $roleStyle = 'bg-green-100 text-green-700 border-green-200'; // default user
                                        if ($u['role'] === 'admin') {
                                            $roleStyle = 'bg-purple-100 text-purple-700 border-purple-200';
                                        }
                                    ?>
                                    <span class="px-2.5 py-1 rounded-md text-[10px] font-bold uppercase border <?= $roleStyle ?>">
                                        <?= htmlspecialchars($u['role']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>

                </table>
            </div>

            <?php if($totalUserPages > 1): ?>
            <div class="p-5 border-t border-gray-50 bg-gray-50/30 flex justify-between items-center">
                <p class="text-xs text-gray-500 font-medium">
                    Menampilkan halaman <?= $userPage ?> dari <?= $totalUserPages ?> (Total: <?= $totalUsersCount ?> pengguna)
                </p>
                <div class="flex gap-2">
                    <a href="?user_page=<?= max(1, $userPage-1) ?>" 
                       class="p-2 rounded-lg bg-white border text-gray-600 hover:bg-gray-50 transition-all <?= $userPage == 1 ? 'opacity-50 pointer-events-none' : '' ?>">
                        <i data-lucide="chevron-left" class="w-4 h-4"></i>
                    </a>
                    
                    <!-- Page Numbers -->
                    <div class="flex gap-1">
                        <?php
                        $startPage = max(1, $userPage - 2);
                        $endPage = min($totalUserPages, $userPage + 2);
                        
                        for($i = $startPage; $i <= $endPage; $i++):
                        ?>
                        <a href="?user_page=<?= $i ?>" 
                           class="px-3 py-2 rounded-lg text-sm font-medium transition-all <?= $i == $userPage ? 'bg-blue-600 text-white' : 'bg-white border text-gray-600 hover:bg-gray-50' ?>">
                            <?= $i ?>
                        </a>
                        <?php endfor; ?>
                    </div>
                    
                    <a href="?user_page=<?= min($totalUserPages, $userPage+1) ?>" 
                       class="p-2 rounded-lg bg-white border text-gray-600 hover:bg-gray-50 transition-all <?= $userPage == $totalUserPages ? 'opacity-50 pointer-events-none' : '' ?>">
                        <i data-lucide="chevron-right" class="w-4 h-4"></i>
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </section>
    </main>

    <!-- MODAL VERIFIKASI -->
    <div x-show="showVerifyModal" 
         class="fixed inset-0 bg-black/60 backdrop-blur-sm z-[100] flex items-center justify-center p-4" 
         x-cloak 
         x-transition>
        
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl overflow-hidden flex flex-col animate-slide-up max-h-[90vh]" 
             @click.away="showVerifyModal = false">
            
            <div class="p-5 border-b border-gray-100 flex justify-between items-center bg-gray-50">
                <h3 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                    <i data-lucide="shield-check" class="text-blue-600"></i> 
                    <span x-text="selectedItem?.jenis_aset === 'ruangan' ? 'Verifikasi & Kondisi Fasilitas Ruangan' : 'Verifikasi & Detail Peminjaman Sarana'"></span>
                </h3>
                <button @click="showVerifyModal = false" class="text-gray-400 hover:text-gray-600">
                    <i data-lucide="x"></i>
                </button>
            </div>
            
            <form action="proses_verifikasi_kondisi.php" method="GET">
                <input type="hidden" name="action" value="selesai">
                <input type="hidden" name="id" :value="selectedItem?.id">

                <div class="p-6 overflow-y-auto custom-scrollbar" style="max-height: calc(90vh - 200px);">
                    <div x-if="selectedItem" class="mb-6 bg-slate-50 p-4 rounded-xl border border-gray-100 grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-[10px] text-gray-400 font-bold uppercase tracking-wider">Peminjam</p>
                            <p class="text-sm font-bold text-gray-700" x-text="selectedItem.nama_user"></p>
                        </div>
                        <div>
                            <p class="text-[10px] text-gray-400 font-bold uppercase tracking-wider">Nama Aset</p>
                            <p class="text-sm font-bold text-gray-700" x-text="selectedItem.nama_aset"></p>
                        </div>
                    </div>

                    <!-- DETAIL UNTUK RUANGAN -->
                    <template x-if="selectedItem && selectedItem.jenis_aset === 'ruangan'">
                        <div class="mb-6">
                            <label class="text-[10px] text-blue-600 font-black uppercase mb-2 block tracking-widest items-center gap-2">
                                <i data-lucide="package" class="w-3 h-3"></i>
                                Detail Fasilitas Ruangan
                                <template x-if="selectedItem.status === 'returning'">
                                    <span class="text-green-600 text-[9px] normal-case bg-green-50 px-2 py-0.5 rounded-full">Dapat diedit</span>
                                </template>
                                <template x-if="selectedItem.status !== 'returning'">
                                    <span class="text-gray-400 text-[9px] normal-case bg-gray-50 px-2 py-0.5 rounded-full">Hanya tampilan</span>
                                </template>
                            </label>
                            <div class="border border-gray-200 rounded-xl overflow-hidden">
                                <table class="w-full text-left text-xs">
                                    <thead class="bg-slate-100 text-gray-600 font-bold border-b border-gray-200">
                                        <tr>
                                            <th class="py-2.5 px-4">No</th>
                                            <th class="py-2.5 px-4">Nama Barang</th>
                                            <th class="py-2.5 px-4">Kode Label</th>
                                            <th class="py-2.5 px-4">Kondisi</th>
                                            <template x-if="selectedItem.status === 'returning'">
                                                <th class="py-2.5 px-4">Catatan</th>
                                            </template>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <template x-for="(unit, index) in selectedItem.detail_unit_fasilitas" :key="unit.label">
                                            <tr class="hover:bg-gray-50/50 transition-colors">
                                                <td class="py-2.5 px-4 text-gray-500 font-semibold" x-text="index + 1"></td>
                                                <td class="py-2.5 px-4 font-semibold text-gray-700" x-text="unit.nama"></td>
                                                <td class="py-2.5 px-4 font-mono font-bold text-blue-600" x-text="unit.label"></td>
                                                <td class="py-2.5 px-4">
                                                    <template x-if="selectedItem.status === 'returning'">
                                                        <select :name="'kondisi[' + unit.label + ']'" 
                                                                x-model="unit.kondisi"
                                                                class="w-full px-2 py-1 text-[10px] font-bold rounded-lg border-2 outline-none transition-all cursor-pointer"
                                                                :class="{
                                                                    'bg-emerald-50 border-emerald-300 text-emerald-700': unit.kondisi === 'Baik',
                                                                    'bg-amber-50 border-amber-300 text-amber-700': unit.kondisi === 'Rusak Ringan',
                                                                    'bg-rose-50 border-rose-300 text-rose-700': unit.kondisi === 'Rusak Berat'
                                                                }">
                                                            <option value="Baik">Baik</option>
                                                            <option value="Rusak Ringan">Rusak Ringan</option>
                                                            <option value="Rusak Berat">Rusak Berat</option>
                                                        </select>
                                                    </template>
                                                    <template x-if="selectedItem.status !== 'returning'">
                                                        <span 
                                                            :class="{
                                                                'bg-emerald-100 text-emerald-700 border-emerald-200': unit.kondisi === 'Baik',
                                                                'bg-amber-100 text-amber-700 border-amber-200': unit.kondisi === 'Rusak Ringan',
                                                                'bg-rose-100 text-rose-700 border-rose-200': unit.kondisi === 'Rusak Berat'
                                                            }" 
                                                            class="px-2 py-0.5 rounded-md text-[9px] font-bold uppercase border inline-block" 
                                                            x-text="unit.kondisi">
                                                        </span>
                                                    </template>
                                                </td>
                                                <template x-if="selectedItem.status === 'returning'">
                                                    <td class="py-2.5 px-4">
                                                        <input type="text" 
                                                               :name="'catatan[' + unit.label + ']'"
                                                               x-model="unit.catatan"
                                                               placeholder="Catatan kondisi..."
                                                               class="w-full px-2 py-1 text-[10px] border border-gray-200 rounded-lg focus:border-blue-400 outline-none">
                                                    </td>
                                                </template>
                                            </tr>
                                        </template>
                                        <template x-if="!selectedItem.detail_unit_fasilitas || selectedItem.detail_unit_fasilitas.length === 0">
                                            <tr>
                                                <td colspan="5" class="py-4 text-center text-gray-400 bg-gray-50/50">
                                                    Ruangan ini belum memiliki fasilitas terdaftar.
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                            <template x-if="selectedItem.status === 'returning'">
                                <div class="mt-2 flex items-center gap-2 text-[10px] text-green-600 font-medium bg-green-50 px-3 py-2 rounded-lg border border-green-200">
                                    <i data-lucide="alert-circle" class="w-3 h-3"></i>
                                    <span>Perubahan kondisi akan otomatis tersinkron ke database sarana</span>
                                </div>
                            </template>
                            <template x-if="selectedItem.status !== 'returning'">
                                <div class="mt-2 flex items-center gap-2 text-[10px] text-gray-500 italic bg-gray-50 px-3 py-2 rounded-lg border border-gray-200">
                                    <i data-lucide="info" class="w-3 h-3"></i>
                                    <span>Kondisi fasilitas saat pengajuan peminjaman (tidak dapat diubah)</span>
                                </div>
                            </template>
                        </div>
                    </template>

                    <!-- DETAIL UNTUK SARANA -->
                    <template x-if="selectedItem && selectedItem.jenis_aset === 'sarana'">
                        <div class="mb-6">
                            <label class="text-[10px] text-blue-600 font-black uppercase mb-2 block tracking-widest flex items-center gap-2">
                                <i data-lucide="wrench" class="w-3 h-3"></i>
                                Detail Peminjaman Sarana
                                <template x-if="selectedItem.status === 'returning'">
                                    <span class="text-green-600 text-[9px] normal-case bg-green-50 px-2 py-0.5 rounded-full">Dapat diedit</span>
                                </template>
                                <template x-if="selectedItem.status !== 'returning'">
                                    <span class="text-gray-400 text-[9px] normal-case bg-gray-50 px-2 py-0.5 rounded-full">Hanya tampilan</span>
                                </template>
                            </label>
                            <div class="border border-gray-200 rounded-xl overflow-hidden">
                                <table class="w-full text-left text-xs">
                                    <thead class="bg-slate-100 text-gray-600 font-bold border-b border-gray-200">
                                        <tr>
                                            <th class="py-2.5 px-4">Nama Barang</th>
                                            <th class="py-2.5 px-4">Kode Label</th>
                                            <th class="py-2.5 px-4">Jumlah</th>
                                            <th class="py-2.5 px-4">Kondisi</th>
                                            <template x-if="selectedItem.status === 'returning'">
                                                <th class="py-2.5 px-4">Catatan</th>
                                            </template>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <template x-for="(unit, index) in selectedItem.detail_unit_fasilitas" :key="unit.label">
                                            <tr class="hover:bg-gray-50/50 transition-colors">
                                                <td class="py-2.5 px-4 font-semibold text-gray-700" x-text="unit.nama"></td>
                                                <td class="py-2.5 px-4 font-mono font-bold text-blue-600" x-text="unit.label"></td>
                                                <td class="py-2.5 px-4 font-bold text-gray-600" x-text="unit.jumlah + ' Unit'"></td>
                                                <td class="py-2.5 px-4">
                                                    <template x-if="selectedItem.status === 'returning'">
                                                        <select :name="'kondisi[' + unit.label + ']'" 
                                                                x-model="unit.kondisi"
                                                                class="w-full px-2 py-1 text-[10px] font-bold rounded-lg border-2 outline-none transition-all cursor-pointer"
                                                                :class="{
                                                                    'bg-emerald-50 border-emerald-300 text-emerald-700': unit.kondisi === 'Baik',
                                                                    'bg-amber-50 border-amber-300 text-amber-700': unit.kondisi === 'Rusak Ringan',
                                                                    'bg-rose-50 border-rose-300 text-rose-700': unit.kondisi === 'Rusak Berat'
                                                                }">
                                                            <option value="Baik">Baik</option>
                                                            <option value="Rusak Ringan">Rusak Ringan</option>
                                                            <option value="Rusak Berat">Rusak Berat</option>
                                                        </select>
                                                    </template>
                                                    <template x-if="selectedItem.status !== 'returning'">
                                                        <span 
                                                            :class="{
                                                                'bg-emerald-100 text-emerald-700 border-emerald-200': unit.kondisi === 'Baik',
                                                                'bg-amber-100 text-amber-700 border-amber-200': unit.kondisi === 'Rusak Ringan',
                                                                'bg-rose-100 text-rose-700 border-rose-200': unit.kondisi === 'Rusak Berat'
                                                            }" 
                                                            class="px-2 py-0.5 rounded-md text-[9px] font-bold uppercase border inline-block" 
                                                            x-text="unit.kondisi">
                                                        </span>
                                                    </template>
                                                </td>
                                                <template x-if="selectedItem.status === 'returning'">
                                                    <td class="py-2.5 px-4">
                                                        <input type="text" 
                                                               :name="'catatan[' + unit.label + ']'"
                                                               x-model="unit.catatan"
                                                               placeholder="Catatan kondisi..."
                                                               class="w-full px-2 py-1 text-[10px] border border-gray-200 rounded-lg focus:border-blue-400 outline-none">
                                                    </td>
                                                </template>
                                            </tr>
                                        </template>
                                        <template x-if="!selectedItem.detail_unit_fasilitas || selectedItem.detail_unit_fasilitas.length === 0">
                                            <tr>
                                                <td colspan="5" class="py-4 text-center text-gray-400 bg-gray-50/50">
                                                    Data sarana tidak ditemukan.
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                            <template x-if="selectedItem.status === 'returning'">
                                <div class="mt-2 flex items-center gap-2 text-[10px] text-green-600 font-medium bg-green-50 px-3 py-2 rounded-lg border border-green-200">
                                    <i data-lucide="alert-circle" class="w-3 h-3"></i>
                                    <span>Perubahan kondisi akan otomatis tersinkron ke database sarana</span>
                                </div>
                            </template>
                            <template x-if="selectedItem.status !== 'returning'">
                                <div class="mt-2 flex items-center gap-2 text-[10px] text-gray-500 italic bg-gray-50 px-3 py-2 rounded-lg border border-gray-200">
                                    <i data-lucide="info" class="w-3 h-3"></i>
                                    <span>Kondisi sarana saat pengajuan peminjaman (tidak dapat diubah)</span>
                                </div>
                            </template>
                        </div>
                    </template>

                    <div x-show="selectedItem?.status === 'returning' && selectedItem?.foto_pengembalian">
                        <p class="text-[10px] text-purple-600 font-bold uppercase mb-2 tracking-widest">Bukti Foto Pengembalian:</p>
                        <a :href="'../' + selectedItem.foto_pengembalian" target="_blank" class="block group relative overflow-hidden rounded-xl border border-gray-200">
                            <img :src="'../' + selectedItem.foto_pengembalian" class="w-full h-48 object-cover group-hover:scale-105 transition-transform duration-500">
                            <div class="absolute inset-0 bg-black/20 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                <span class="text-white text-xs font-bold bg-black/50 px-3 py-1 rounded-full">Lihat Ukuran Penuh</span>
                            </div>
                        </a>
                    </div>
                </div>

                <div class="p-5 border-t bg-gray-50 flex justify-end gap-3">
                    <button type="button" @click="showVerifyModal = false" 
                            class="px-5 py-2.5 text-sm font-bold text-gray-600 bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition-all">
                        Tutup
                    </button>
                    
                    <template x-if="selectedItem && selectedItem.status === 'returning'">
                        <button type="submit" 
                                class="px-5 py-2.5 text-sm font-bold text-white bg-green-600 rounded-xl shadow-lg shadow-green-100 hover:bg-green-700 transition-all flex items-center gap-2">
                            <i data-lucide="check-circle" class="w-4 h-4"></i> 
                            Konfirmasi & Selesaikan
                        </button>
                    </template>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => { lucide.createIcons(); });
    </script>
</body>
</html>