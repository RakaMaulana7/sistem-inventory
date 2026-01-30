<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "../config/database.php";

// Proteksi dasar
if (!isset($_SESSION['login']) || $_SESSION['role'] !== 'user') {
    header("Location: ../login.html");
    exit;
}

// Ambil data user
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// --- LOGIKA NOTIFIKASI AKTIF ---
// Hanya ambil yang BELUM dibaca (is_read = 0) - termasuk approval/rejection dan verification
$stmtNotif = $pdo->prepare("
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
    WHERE p.user_id = ? AND p.status IN ('approved', 'rejected', 'returning', 'kembali') AND p.is_read = 0
    ORDER BY p.updated_at DESC LIMIT 5
");
$stmtNotif->execute([$_SESSION['user_id']]);
$notifs = $stmtNotif->fetchAll();
?>

<script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>

<nav x-data="{ openMenu: false, openNotif: false }" class="fixed top-0 left-0 w-full bg-white/90 backdrop-blur-md border-b border-gray-100 z-50 transition-all duration-300">
    <div class="max-w-[1400px] mx-auto px-4 md:px-6 h-16 md:h-20 flex justify-between items-center">
        
        <!-- Logo -->
        <div class="flex items-center gap-3 cursor-pointer group" onclick="location.href='dashboard.php'">
            <img src="../assets/umsura.png" alt="Logo FT" class="h-8 md:h-10 transition-transform duration-500 group-hover:scale-110" onerror="this.src='https://placehold.co/40x40?text=FT'">
        </div>

        <!-- Right Side: Menu Links + Notification + Profile -->
        <div class="flex items-center gap-2 md:gap-3">
            
            <!-- Menu Navigation Links (Desktop) -->
            <div class="hidden lg:flex items-center gap-1 mr-2">
                <a href="peminjaman_saya.php" class="flex items-center gap-2 px-3 py-2 text-sm text-gray-600 hover:text-brand hover:bg-green-50 rounded-xl transition-all duration-300 group">
                    <i data-lucide="file-text" class="w-4 h-4 group-hover:scale-110 transition-transform"></i>
                    <span class="font-medium">Peminjaman</span>
                </a>
                <a href="riwayat_peminjaman.php" class="flex items-center gap-2 px-3 py-2 text-sm text-gray-600 hover:text-brandSecondary hover:bg-blue-50 rounded-xl transition-all duration-300 group">
                    <i data-lucide="history" class="w-4 h-4 group-hover:scale-110 transition-transform"></i>
                    <span class="font-medium">Riwayat</span>
                </a>
            </div>

            <!-- Divider (Desktop only) -->
            <div class="hidden lg:block w-px h-8 bg-gray-200 mr-1"></div>
            
            <!-- Notification Bell -->
            <div class="relative">
                <button @click="openNotif = !openNotif; openMenu = false" 
                    class="relative p-2 md:p-2.5 rounded-full transition-all duration-300 hover:rotate-12 active:scale-90"
                    :class="openNotif ? 'bg-green-50 text-brand' : 'hover:bg-gray-100 text-gray-600'">
                    <i data-lucide="bell" class="w-5 h-5 md:w-[22px] md:h-[22px]"></i>
                    <?php if (count($notifs) > 0): ?>
                        <span class="absolute top-1.5 right-1.5 md:top-2 md:right-2.5 w-2.5 h-2.5 bg-gradient-to-r from-brand to-emerald-500 rounded-full border-2 border-white animate-pulse"></span>
                    <?php endif; ?>
                </button>

                <!-- Notification Dropdown -->
                <div x-show="openNotif" 
                    x-cloak
                    @click.outside="openNotif = false"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 scale-95 translate-y-2"
                    x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                    x-transition:leave="transition ease-in duration-75"
                    x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                    x-transition:leave-end="opacity-0 scale-95 translate-y-2"
                    class="absolute right-[-60px] sm:right-0 mt-4 w-[280px] sm:w-96 bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
                    
                    <div class="p-4 border-b border-gray-50 flex justify-between items-center bg-gradient-to-r from-green-50 to-blue-50">
                        <h3 class="font-bold text-gray-800">Notifikasi</h3>
                        <?php if (count($notifs) > 0): ?>
                            <span class="text-[10px] bg-gradient-to-r from-brand to-emerald-500 text-white px-2 py-0.5 rounded-full font-bold">Baru</span>
                        <?php endif; ?>
                    </div>

                    <div class="max-h-[350px] overflow-y-auto custom-scrollbar">
                        <?php if (count($notifs) > 0): ?>
                            <div class="flex flex-col">
                                <?php foreach ($notifs as $n): ?>
                                    <?php 
                                        // Determine notification type and styling
                                        $isApproval = in_array($n['status'], ['approved', 'rejected']);
                                        $isVerification = in_array($n['status'], ['selesai', 'kembali']);
                                        
                                        if ($n['status'] === 'approved') {
                                            $bgStatus = 'bg-green-50';
                                            $iconColor = 'text-green-600';
                                            $icon = 'check-circle';
                                            $labelStatus = 'Disetujui';
                                        } elseif ($n['status'] === 'rejected') {
                                            $bgStatus = 'bg-red-50';
                                            $iconColor = 'text-red-600';
                                            $icon = 'x-circle';
                                            $labelStatus = 'Ditolak';
                                        } elseif ($n['status'] === 'returning' || $n['status'] === 'kembali') {
                                            $bgStatus = 'bg-blue-50';
                                            $iconColor = 'text-blue-600';
                                            $icon = 'check-circle-2';
                                            $labelStatus = 'Telah Dikembalikan';
                                        }
                                    ?>
                                    <a href="read_notif.php?id=<?= $n['id'] ?>" class="p-4 border-b border-gray-50 hover:bg-gradient-to-r hover:from-green-50/30 hover:to-blue-50/30 transition-colors block decoration-none">
                                        <div class="flex gap-3">
                                            <div class="shrink-0 w-10 h-10 rounded-full <?php echo $bgStatus; ?> flex items-center justify-center <?php echo $iconColor; ?>">
                                                <i data-lucide="<?php echo $icon; ?>" class="w-5 h-5"></i>
                                            </div>
                                            <div class="flex-1">
                                                <div class="flex justify-between items-start">
                                                    <?php if ($isApproval): ?>
                                                        <p class="text-sm font-bold text-gray-800">Permintaan <?php echo $labelStatus; ?></p>
                                                    <?php else: ?>
                                                        <p class="text-sm font-bold text-gray-800">Peminjaman <?php echo $labelStatus; ?></p>
                                                    <?php endif; ?>
                                                    <span class="text-[9px] text-blue-500 font-bold bg-blue-50 px-1.5 py-0.5 rounded uppercase">Klik Tandai Baca</span>
                                                </div>
                                                <p class="text-xs text-gray-600 mt-0.5">
                                                    <?php if ($isApproval): ?>
                                                        Peminjaman <strong><?php echo htmlspecialchars($n['nama_aset']); ?></strong> telah diperbarui oleh admin.
                                                    <?php else: ?>
                                                        Peminjaman <strong><?php echo htmlspecialchars($n['nama_aset']); ?></strong> <?php echo strtolower($labelStatus); ?> dan tersimpan di riwayat.
                                                    <?php endif; ?>
                                                </p>
                                                <p class="text-[10px] text-gray-400 mt-2 flex items-center gap-1">
                                                    <i data-lucide="clock" class="w-3 h-3"></i>
                                                    <?php echo date('d M, H:i', strtotime($n['updated_at'])); ?>
                                                </p>
                                            </div>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="py-12 text-center flex flex-col items-center">
                                <div class="bg-gray-100 p-3 rounded-full mb-3 text-gray-400">
                                    <i data-lucide="bell-off"></i>
                                </div>
                                <p class="text-gray-500 text-sm font-medium px-4">Tidak ada pemberitahuan baru</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Profile Menu -->
            <div class="relative">
                <button @click="openMenu = !openMenu; openNotif = false"
                    class="flex items-center gap-2 md:gap-3 pl-1.5 pr-2 md:pl-2 md:pr-3 py-1 md:py-1.5 rounded-full border transition-all duration-300 hover:shadow-md active:scale-95"
                    :class="openMenu ? 'border-brand bg-green-50/50 ring-2 ring-green-100' : 'border-gray-200 hover:border-gray-300 bg-white'">
                    
                    <img src="<?php echo $user['foto'] ?? 'https://cdn-icons-png.flaticon.com/512/847/847969.png' ?>" 
                         class="w-7 h-7 md:w-8 md:h-8 rounded-full object-cover bg-gray-100">
                    
                    <div class="hidden md:block text-left">
                        <p class="text-xs font-bold text-gray-800 leading-none mb-0.5"><?php echo explode(' ', $user['nama'])[0]; ?></p>
                        <p class="text-[10px] text-gray-500 font-medium uppercase"><?php echo $user['username']; ?></p>
                    </div>
                    
                    <i data-lucide="chevron-down" class="text-gray-400 transition-transform duration-300 w-3.5 h-3.5 md:w-4 md:h-4" :class="openMenu ? 'rotate-180' : ''"></i>
                </button>

                <!-- Profile Dropdown -->
                <div x-show="openMenu" 
                    x-cloak
                    @click.outside="openMenu = false"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 scale-95 translate-y-2"
                    x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                    x-transition:leave="transition ease-in duration-75"
                    x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                    x-transition:leave-end="opacity-0 scale-95 translate-y-2"
                    class="absolute right-0 mt-4 w-52 md:w-56 bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
                    
                    <div class="p-4 border-b border-gray-100 bg-gradient-to-r from-green-50 to-blue-50">
                        <p class="font-bold text-gray-800 truncate text-sm"><?php echo htmlspecialchars($user['nama']); ?></p>
                        <p class="text-[10px] text-gray-500 truncate"><?php echo htmlspecialchars($user['username']); ?></p>
                    </div>
                    
                    <div class="p-2">
                        <a href="profile.php" class="flex items-center gap-3 px-3 py-2 md:py-2.5 text-sm text-gray-600 hover:bg-green-50 hover:text-brand rounded-xl transition-all">
                            <i data-lucide="user" class="w-4 h-4"></i> Profil Saya
                        </a>
                        <!-- Menu Mobile: Peminjaman & Riwayat (hanya tampil di mobile) -->
                        <a href="peminjaman_saya.php" class="lg:hidden flex items-center gap-3 px-3 py-2 md:py-2.5 text-sm text-gray-600 hover:bg-blue-50 hover:text-brandSecondary rounded-xl transition-all">
                            <i data-lucide="file-text" class="w-4 h-4"></i> Peminjaman Saya
                        </a>
                        <a href="riwayat_peminjaman.php" class="lg:hidden flex items-center gap-3 px-3 py-2 md:py-2.5 text-sm text-gray-600 hover:bg-green-50 hover:text-brand rounded-xl transition-all">
                            <i data-lucide="history" class="w-4 h-4"></i> Riwayat
                        </a>
                    </div>
                    <div class="p-2 border-t border-gray-100">
                        <a href="../auth/logout.php" class="flex items-center gap-3 px-3 py-2 md:py-2.5 text-sm text-red-600 hover:bg-red-600 hover:text-white font-medium rounded-xl transition-all">
                            <i data-lucide="log-out" class="w-4 h-4"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</nav>

<div class="h-16 md:h-20"></div>

<style>
    [x-cloak] { display: none !important; }
    .custom-scrollbar::-webkit-scrollbar { width: 4px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: #f1f1f1; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #e5e7eb; border-radius: 10px; }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #10b981; }
</style>

<script>
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
    
    // Tambahkan Tailwind config untuk brand colors
    if (typeof tailwind !== 'undefined') {
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: '#10b981',
                        brandHover: '#059669',
                        brandSecondary: '#3b82f6',
                        brandSecondaryHover: '#2563eb',
                    }
                }
            }
        }
    }
</script>