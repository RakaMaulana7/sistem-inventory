<?php
session_start();
require "../config/database.php";
require "../auth/auth_helper.php";

cek_kemanan_login($pdo);

// Set timezone
date_default_timezone_set('Asia/Jakarta');

// 1. PROTEKSI LOGIN
if (!isset($_SESSION['login']) || $_SESSION['role'] !== 'user') {
    header("Location: ../login.html");
    exit;
}

// 2. AMBIL DATA TRANSPORTASI BERDASARKAN ID
$id = $_GET['id'] ?? null;
if (!$id) { die("ID Kendaraan tidak ditemukan."); }

$stmt = $pdo->prepare("SELECT * FROM transportasi WHERE id = ?");
$stmt->execute([$id]);
$vehicle = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vehicle) { die("Data kendaraan tidak ditemukan."); }

function getSetting($pdo, $nama_setting, $default = '') {
    $stmt = $pdo->prepare("SELECT nilai FROM pengaturan WHERE nama_setting = ?");
    $stmt->execute([$nama_setting]);
    $result = $stmt->fetch();
    return $result ? $result['nilai'] : $default;
}

$template_file = getSetting($pdo, 'template_transportasi_file');
$template_name = getSetting($pdo, 'template_transportasi_name', 'Template Surat Peminjaman Transportasi');

// 3. LOGIKA STATUS
$statusTransport = $vehicle['status'] ?? 'Tersedia'; 
$isUnavailable = (strtolower($statusTransport) !== 'tersedia');

$badgeStyle = $isUnavailable 
    ? "bg-red-100 text-red-700 border-red-200" 
    : "bg-green-100 text-green-700 border-green-200";

// Helper untuk kondisi badge
function getKondisiBadge($kondisi) {
    switch($kondisi) {
        case 'Baik':
            return '<span class="inline-flex items-center gap-1 px-2.5 py-1 bg-blue-50 text-blue-700 rounded-lg text-xs font-semibold"><i data-lucide="thumbs-up" class="w-3 h-3"></i> Baik</span>';
        case 'Rusak Ringan':
            return '<span class="inline-flex items-center gap-1 px-2.5 py-1 bg-orange-50 text-orange-700 rounded-lg text-xs font-semibold"><i data-lucide="alert-triangle" class="w-3 h-3"></i> Rusak Ringan</span>';
        case 'Rusak Berat':
            return '<span class="inline-flex items-center gap-1 px-2.5 py-1 bg-red-50 text-red-700 rounded-lg text-xs font-semibold"><i data-lucide="alert-octagon" class="w-3 h-3"></i> Rusak Berat</span>';
        default:
            return '<span class="text-xs text-gray-500">-</span>';
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../assets/kampusums.png" />
    <title>Pinjam <?= htmlspecialchars($vehicle['nama']) ?> | Inventory FT</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        input[type="date"], input[type="time"] { min-height: 45px; }
        
        @media (max-width: 640px) {
            input[type="date"], input[type="time"] { min-height: 42px; font-size: 14px; }
        }
    </style>
</head>
<body class="bg-gray-50 flex flex-col min-h-screen">

    <?php include "../components/UserNavbar.php"; ?>

    <main class="pt-4 md:pt-6 lg:pt-8 pb-16 md:pb-20 px-3 sm:px-4 md:px-6 lg:px-8 w-full max-w-7xl mx-auto flex-1">
        
        <!-- Back Button -->
        <button onclick="location.href='transportasi.php'" class="group flex items-center gap-2 text-gray-500 hover:text-[#d13b1f] transition-colors mb-4 md:mb-6 font-medium text-xs sm:text-sm">
            <div class="p-1.5 sm:p-2 bg-white rounded-full shadow-sm group-hover:shadow-md border border-gray-200 transition-all">
                <i data-lucide="arrow-left" class="w-3.5 h-3.5 sm:w-4 sm:h-4 md:w-5 md:h-5"></i>
            </div>
            Kembali ke Daftar
        </button>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-5 md:gap-6 lg:gap-8">
            
            <!-- LEFT COLUMN - Vehicle Info -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-2xl md:rounded-3xl shadow-sm border border-gray-100 overflow-hidden lg:sticky lg:top-24">
                    
                    <!-- Vehicle Image -->
                    <div class="h-40 sm:h-48 md:h-56 bg-gray-200 relative cursor-pointer group" onclick="openPreview('<?= $vehicle['fotoDepan'] ?: '../assets/default-car.jpg' ?>')">
                        <img src="<?= $vehicle['fotoDepan'] ?: '../assets/default-car.jpg' ?>" 
                             class="w-full h-full object-cover transition-transform group-hover:scale-105 duration-500" alt="Foto Depan">
                        
                        <div class="absolute inset-0 bg-black/0 group-hover:bg-black/10 transition-colors flex items-center justify-center opacity-0 group-hover:opacity-100">
                            <span class="bg-black/50 text-white text-[10px] sm:text-xs px-2.5 sm:px-3 py-1 sm:py-1.5 rounded-full backdrop-blur-sm">Klik untuk memperbesar</span>
                        </div>
                        
                        <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent pointer-events-none"></div>
                        
                        <!-- License Plate -->
                        <div class="absolute top-3 sm:top-4 right-3 sm:right-4 bg-white/90 backdrop-blur px-2 sm:px-3 py-0.5 sm:py-1 rounded-lg text-[10px] sm:text-xs font-bold shadow-sm border border-gray-200">
                            <?= htmlspecialchars($vehicle['plat']) ?>
                        </div>

                        <!-- Vehicle Name -->
                        <div class="absolute bottom-3 sm:bottom-4 left-3 sm:left-4 right-3 sm:right-4 text-white">
                            <h2 class="text-lg sm:text-xl md:text-2xl font-bold leading-tight"><?= htmlspecialchars($vehicle['nama']) ?></h2>
                        </div>
                    </div>
                    
                    <!-- Status & Info -->
                    <div class="p-4 sm:p-5 md:p-6">
                        <!-- Status Badge -->
                        <div class="inline-flex items-center gap-1.5 sm:gap-2 px-3 sm:px-4 py-2 sm:py-2.5 rounded-full text-xs sm:text-sm font-bold border mb-3 sm:mb-4 w-full justify-center <?= $badgeStyle ?>">
                            <i data-lucide="<?= $isUnavailable ? 'alert-circle' : 'check-circle' ?>" class="w-3.5 h-3.5 sm:w-4 sm:h-4"></i>
                            Status: <?= $statusTransport ?>
                        </div>

                        <!-- Vehicle Details -->
                        <div class="space-y-3 mb-4">
                            <!-- Lokasi -->
                            <div class="flex items-start gap-2 p-3 bg-gray-50 rounded-xl">
                                <i data-lucide="map-pin" class="w-4 h-4 text-green-600 mt-0.5 shrink-0"></i>
                                <div class="flex-1 min-w-0">
                                    <p class="text-[10px] text-gray-500 uppercase font-bold mb-0.5">Lokasi Parkir</p>
                                    <p class="text-sm text-gray-800 font-semibold"><?= htmlspecialchars($vehicle['lokasi'] ?: 'Parkiran Kampus') ?></p>
                                </div>
                            </div>

                            <!-- Jenis Bensin -->
                            <div class="flex items-start gap-2 p-3 bg-orange-50 rounded-xl">
                                <i data-lucide="fuel" class="w-4 h-4 text-orange-600 mt-0.5 shrink-0"></i>
                                <div class="flex-1 min-w-0">
                                    <p class="text-[10px] text-gray-500 uppercase font-bold mb-0.5">Jenis Bahan Bakar</p>
                                    <p class="text-sm text-orange-800 font-semibold"><?= htmlspecialchars($vehicle['jenis_bensin'] ?: '-') ?></p>
                                </div>
                            </div>

                            <!-- Kondisi -->
                            <div class="flex items-start gap-2 p-3 bg-blue-50 rounded-xl">
                                <i data-lucide="wrench" class="w-4 h-4 text-blue-600 mt-0.5 shrink-0"></i>
                                <div class="flex-1 min-w-0">
                                    <p class="text-[10px] text-gray-500 uppercase font-bold mb-1">Kondisi Kendaraan</p>
                                    <?= getKondisiBadge($vehicle['kondisi']) ?>
                                </div>
                            </div>

                            <!-- Fasilitas Interior -->
                            <?php if(!empty($vehicle['kondisi_dalam'])): ?>
                            <div class="p-3 bg-purple-50 rounded-xl">
                                <p class="text-[10px] text-gray-500 uppercase font-bold mb-2 flex items-center gap-1">
                                    <i data-lucide="clipboard-list" class="w-3 h-3"></i>
                                    Fasilitas Interior
                                </p>
                                <div class="flex flex-wrap gap-1.5">
                                    <?php 
                                    $parts = array_map('trim', explode(',', $vehicle['kondisi_dalam']));
                                    foreach(array_slice($parts, 0, 4) as $p) { ?>
                                        <span class="px-2 py-0.5 bg-white text-gray-700 text-[10px] font-semibold rounded border border-gray-200">
                                            <?= htmlspecialchars($p) ?>
                                        </span>
                                    <?php } ?>
                                    <?php if(count($parts) > 4): ?>
                                        <span class="px-2 py-0.5 bg-purple-600 text-white text-[10px] font-semibold rounded">
                                            +<?= count($parts) - 4 ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Catatan -->
                            <?php if(!empty($vehicle['catatan'])): ?>
                            <div class="p-3 bg-amber-50 border border-amber-200 rounded-xl">
                                <p class="text-[10px] text-amber-700 uppercase font-bold mb-1 flex items-center gap-1">
                                    <i data-lucide="info" class="w-3 h-3"></i>
                                    Catatan Penting
                                </p>
                                <p class="text-xs text-amber-800"><?= htmlspecialchars($vehicle['catatan']) ?></p>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="space-y-4">
                            <!-- Photo Gallery -->
                            <div>
                                <p class="text-[9px] sm:text-[10px] text-gray-400 uppercase font-bold tracking-wider mb-2 sm:mb-3">Dokumentasi Fisik</p>
                                <div class="grid grid-cols-4 gap-1.5 sm:gap-2">
                                    <?php 
                                    $fotos = ['fotoKanan', 'fotoKiri', 'fotoBelakang', 'fotoSpeedometer'];
                                    foreach ($fotos as $f): 
                                        $url = $vehicle[$f] ?? null;
                                    ?>
                                    <div class="aspect-square bg-gray-100 rounded-lg sm:rounded-xl overflow-hidden border border-gray-200 cursor-pointer hover:opacity-80 transition-all relative group"
                                         onclick="openPreview('<?= $url ?>')">
                                        <?php if ($url): ?>
                                            <img src="<?= $url ?>" class="w-full h-full object-cover">
                                            <div class="absolute inset-0 bg-black/20 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                                        <?php else: ?>
                                            <div class="w-full h-full flex items-center justify-center text-gray-300 text-[9px] sm:text-[10px]">N/A</div>
                                        <?php endif; ?>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Info Box -->
                            <div class="bg-blue-50 p-3 sm:p-4 rounded-xl border border-blue-200 flex gap-2 sm:gap-3 items-start">
                                <i data-lucide="info" class="text-blue-600 shrink-0 w-4 h-4 sm:w-5 sm:h-5"></i>
                                <p class="text-[11px] sm:text-xs md:text-sm text-blue-800 leading-relaxed">
                                    Pastikan Anda memiliki <strong>SIM</strong> yang sesuai untuk kendaraan ini.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- RIGHT COLUMN - Form -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-2xl md:rounded-3xl shadow-lg border border-gray-100 p-4 sm:p-5 md:p-6 lg:p-8">
                    <!-- Header -->
                    <header class="mb-5 sm:mb-6 md:mb-8">
                        <h1 class="text-lg sm:text-xl md:text-2xl font-bold text-gray-800">Formulir Peminjaman Transportasi</h1>
                        <p class="text-gray-500 text-[11px] sm:text-xs md:text-sm mt-1">Lengkapi data perjalanan dan upload dokumen yang diperlukan.</p>
                    </header>

                    <!-- Alert Waktu Expired dari Server -->
                    <?php if (isset($_GET['err']) && $_GET['err'] === 'expired'): ?>
                        <div id="alert-expired" class="bg-red-50 border-l-4 border-red-500 p-4 rounded-xl mb-6 flex items-center gap-3 animate-pulse">
                            <div class="p-2 bg-red-100 rounded-full text-red-600">
                                <i data-lucide="clock-alert" class="w-5 h-5"></i>
                            </div>
                            <div>
                                <p class="text-sm font-bold text-red-800">Waktu Tidak Valid!</p>
                                <p class="text-xs text-red-600">Gagal mengirim, waktu peminjaman sudah terlewat.</p>
                            </div>
                            <button onclick="this.parentElement.remove()" class="ml-auto text-red-400">
                                <i data-lucide="x" class="w-4 h-4"></i>
                            </button>
                        </div>
                    <?php endif; ?>

                    <!-- WARNING BOX - Pendampingan Dosen -->
                    <div class="bg-gradient-to-r from-amber-50 to-orange-50 border-2 border-amber-200 rounded-xl sm:rounded-2xl p-3 sm:p-4 md:p-5 mb-4 sm:mb-5 md:mb-6">
                        <div class="flex items-start gap-2 sm:gap-3 md:gap-4">
                            <div class="p-2 sm:p-2.5 md:p-3 bg-amber-100 rounded-full shrink-0">
                                <i data-lucide="alert-triangle" class="text-amber-700 w-4 h-4 sm:w-5 sm:h-5 md:w-6 md:h-6"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h4 class="font-bold text-amber-900 text-xs sm:text-sm md:text-base mb-1.5 sm:mb-2 flex items-center gap-1.5 sm:gap-2">
                                    <i data-lucide="shield-alert" class="w-3.5 h-3.5 sm:w-4 sm:h-4"></i>
                                    Perhatian: Wajib Pendampingan Dosen
                                </h4>
                                <div class="space-y-1.5 sm:space-y-2 text-[11px] sm:text-xs md:text-sm text-amber-800">
                                    <p class="leading-relaxed">
                                        Peminjaman kendaraan <strong>WAJIB</strong> didampingi oleh <strong>Dosen Pembimbing</strong> atau <strong>Dosen Penanggung Jawab</strong> kegiatan.
                                    </p>
                                    <div class="bg-white/60 p-2 sm:p-2.5 md:p-3 rounded-lg border border-amber-200 mt-2 sm:mt-3">
                                        <p class="font-semibold text-amber-900 mb-1.5 sm:mb-2 flex items-center gap-1.5 sm:gap-2 text-[11px] sm:text-xs">
                                            <i data-lucide="check-circle" class="w-3.5 h-3.5 sm:w-4 sm:h-4"></i>
                                            Syarat Pendampingan:
                                        </p>
                                        <ul class="space-y-1 sm:space-y-1.5 ml-4 sm:ml-5 md:ml-6 list-disc text-[10px] sm:text-xs text-amber-800">
                                            <li>Mahasiswa <strong>tidak diperbolehkan</strong> meminjam kendaraan tanpa pendampingan dosen</li>
                                            <li>Dosen pendamping harus tercantum dalam surat peminjaman</li>
                                            <li>Dosen pendamping bertanggung jawab penuh selama perjalanan</li>
                                            <li>Hubungi dosen pembimbing/penanggung jawab sebelum mengajukan peminjaman</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Unavailable Warning -->
                    <?php if($isUnavailable): ?>
                        <div class="bg-red-50 border border-red-200 rounded-xl sm:rounded-2xl p-3 sm:p-4 flex items-start gap-2 sm:gap-3 mb-4 sm:mb-5 md:mb-6">
                            <i data-lucide="alert-circle" class="text-red-600 shrink-0 mt-0.5 w-4 h-4 sm:w-5 sm:h-5"></i>
                            <div class="flex-1 min-w-0">
                                <h4 class="font-bold text-red-700 text-xs sm:text-sm">Unit Tidak Tersedia</h4>
                                <p class="text-[11px] sm:text-xs text-red-600 mt-1">Unit ini sedang <strong><?= $statusTransport ?></strong>. Silakan pilih unit lain atau tunggu hingga tersedia.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                                        <!-- TEMPLATE DOWNLOAD SECTION -->
                    <?php if ($template_file): ?>
                    <div class="mb-6 p-4 bg-gradient-to-r from-purple-50 to-violet-50 border-2 border-purple-200 rounded-2xl">
                        <div class="flex items-start gap-4">
                            <div class="p-3 bg-white rounded-xl shadow-sm">
                                <i data-lucide="file-down" class="w-6 h-6 text-purple-600"></i>
                            </div>
                            <div class="flex-1">
                                <h3 class="font-bold text-gray-800 mb-1">Template Tersedia</h3>
                                <p class="text-xs text-gray-600 mb-3">Download template surat peminjaman untuk mempermudah pengisian dokumen. Pastikan menyertakan nama dosen pendamping!</p>
                                <a href="../uploads/templates/<?= htmlspecialchars($template_file) ?>" download
                                    class="inline-flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-purple-600 to-violet-600 text-white rounded-xl font-bold hover:from-purple-700 hover:to-violet-700 shadow-lg hover:shadow-xl transition-all text-sm">
                                    <i data-lucide="download" class="w-4 h-4"></i>
                                    Download <?= htmlspecialchars($template_name) ?>
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- FORM -->
                    <form action="proses_peminjaman.php" method="POST" enctype="multipart/form-data" class="space-y-4 sm:space-y-5 md:space-y-6 <?= $isUnavailable ? 'opacity-50 pointer-events-none grayscale' : '' ?>">
                        <input type="hidden" name="item_id" value="<?= $id ?>">
                        <input type="hidden" name="kategori" value="transportasi">

                        <!-- JADWAL SECTION -->
                        <div class="bg-gradient-to-br from-gray-50 to-slate-50 p-3 sm:p-4 md:p-5 lg:p-6 rounded-xl sm:rounded-2xl border-2 border-gray-200">
                            <h3 class="text-[11px] sm:text-xs md:text-sm font-black text-gray-800 uppercase tracking-wider mb-3 sm:mb-4 md:mb-5 flex items-center gap-1.5 sm:gap-2">
                                <div class="p-1.5 sm:p-2 bg-blue-100 rounded-lg">
                                    <i data-lucide="calendar-clock" class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-blue-600"></i>
                                </div>
                                Jadwal Pemakaian Kendaraan
                            </h3>
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-5 md:gap-6">
                                <!-- Waktu Ambil -->
                                <div class="space-y-2 sm:space-y-3">
                                    <label class="block text-xs sm:text-sm font-bold text-gray-700 flex items-center gap-1.5 sm:gap-2">
                                        <i data-lucide="log-in" class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-green-600"></i>
                                        Waktu Ambil Kendaraan
                                    </label>
                                    <div class="grid grid-cols-2 gap-2 sm:gap-3">
                                        <div>
                                            <label class="block text-[10px] sm:text-xs text-gray-500 mb-1 sm:mb-1.5 font-medium">Tanggal</label>
                                            <input type="date" id="tgl_mulai" name="tanggal_mulai" min="<?= date('Y-m-d') ?>" required class="w-full px-2 sm:px-3 md:px-4 py-2 sm:py-2.5 md:py-3 border border-gray-300 bg-white rounded-lg sm:rounded-xl focus:ring-2 focus:ring-blue-500 outline-none text-xs sm:text-sm font-medium transition-all">
                                        </div>
                                        <div>
                                            <label class="block text-[10px] sm:text-xs text-gray-500 mb-1 sm:mb-1.5 font-medium">Jam</label>
                                            <input type="time" id="jam_mulai" name="waktu_mulai" required class="w-full px-2 sm:px-3 md:px-4 py-2 sm:py-2.5 md:py-3 border border-gray-300 bg-white rounded-lg sm:rounded-xl focus:ring-2 focus:ring-blue-500 outline-none text-xs sm:text-sm font-medium transition-all">
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Waktu Kembali -->
                                <div class="space-y-2 sm:space-y-3">
                                    <label class="block text-xs sm:text-sm font-bold text-gray-700 flex items-center gap-1.5 sm:gap-2">
                                        <i data-lucide="log-out" class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-red-600"></i>
                                        Waktu Kembali Kendaraan
                                    </label>
                                    <div class="grid grid-cols-2 gap-2 sm:gap-3">
                                        <div>
                                            <label class="block text-[10px] sm:text-xs text-gray-500 mb-1 sm:mb-1.5 font-medium">Tanggal</label>
                                            <input type="date" id="tgl_selesai" name="tanggal_selesai" min="<?= date('Y-m-d') ?>" required class="w-full px-2 sm:px-3 md:px-4 py-2 sm:py-2.5 md:py-3 bg-white border border-gray-300 rounded-lg sm:rounded-xl focus:ring-2 focus:ring-blue-500 outline-none text-xs sm:text-sm font-medium transition-all">
                                        </div>
                                        <div>
                                            <label class="block text-[10px] sm:text-xs text-gray-500 mb-1 sm:mb-1.5 font-medium">Jam</label>
                                            <input type="time" id="jam_selesai" name="waktu_selesai" required class="w-full px-2 sm:px-3 md:px-4 py-2 sm:py-2.5 md:py-3 bg-white border border-gray-300 rounded-lg sm:rounded-xl focus:ring-2 focus:ring-blue-500 outline-none text-xs sm:text-sm font-medium transition-all">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Error Message -->
                            <p id="error-time" class="hidden text-red-500 text-[10px] font-bold mt-2 uppercase tracking-wide italic flex items-center gap-1">
                                <i data-lucide="info" class="w-3 h-3"></i> Waktu peminjaman tidak boleh sudah lewat!
                            </p>
                        </div>

                        <!-- INFORMASI PEMINJAM -->
                        <div class="bg-gradient-to-br from-blue-50 to-indigo-50 p-3 sm:p-4 md:p-5 lg:p-6 rounded-xl sm:rounded-2xl border-2 border-blue-200">
                            <h3 class="text-[11px] sm:text-xs md:text-sm font-black text-gray-800 uppercase tracking-wider mb-3 sm:mb-4 md:mb-5 flex items-center gap-1.5 sm:gap-2">
                                <div class="p-1.5 sm:p-2 bg-blue-100 rounded-lg">
                                    <i data-lucide="user-circle" class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-blue-600"></i>
                                </div>
                                Informasi Peminjam
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 sm:gap-4 md:gap-5">
                                <!-- WhatsApp -->
                                <div>
                                    <label class="block text-xs sm:text-sm font-bold text-gray-700 mb-1.5 sm:mb-2 flex items-center gap-1.5 sm:gap-2">
                                        <i data-lucide="phone" class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-green-600"></i>
                                        No. WhatsApp Utama
                                    </label>
                                    <input type="tel" name="telepon_utama" placeholder="Contoh: 081234567890" required oninput="this.value = this.value.replace(/[^0-9]/g, '');" inputmode="numeric" class="w-full px-3 sm:px-4 py-2 sm:py-2.5 md:py-3 bg-white rounded-lg sm:rounded-xl border-2 border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none text-xs sm:text-sm font-medium transition-all placeholder:text-gray-400">
                                    <p class="text-[10px] sm:text-xs text-gray-500 mt-1 sm:mt-1.5 flex items-center gap-1">
                                        <i data-lucide="info" class="w-3 h-3"></i>
                                        Untuk koordinasi selama peminjaman
                                    </p>
                                </div>
                                <!-- Kontak Darurat -->
                                <div>
                                    <label class="block text-xs sm:text-sm font-bold text-gray-700 mb-1.5 sm:mb-2 flex items-center gap-1.5 sm:gap-2">
                                        <i data-lucide="phone-call" class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-red-600"></i>
                                        Kontak Darurat
                                    </label>
                                    <input type="tel" name="telepon_darurat" placeholder="Contoh: 081234567890" required oninput="this.value = this.value.replace(/[^0-9]/g, '');" inputmode="numeric" class="w-full px-3 sm:px-4 py-2 sm:py-2.5 md:py-3 bg-white rounded-lg sm:rounded-xl border-2 border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none text-xs sm:text-sm font-medium transition-all placeholder:text-gray-400">
                                    <p class="text-[10px] sm:text-xs text-gray-500 mt-1 sm:mt-1.5 flex items-center gap-1">
                                        <i data-lucide="alert-circle" class="w-3 h-3"></i>
                                        Untuk kondisi emergency
                                    </p>
                                </div>
                                <!-- Jaminan -->
                                <div>
                                    <label class="block text-xs sm:text-sm font-bold text-gray-700 mb-1.5 sm:mb-2 flex items-center gap-1.5 sm:gap-2">
                                        <i data-lucide="shield" class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-purple-600"></i>
                                        Jaminan Fisik
                                    </label>
                                    <select name="jaminan" required class="w-full px-3 sm:px-4 py-2 sm:py-2.5 md:py-3 bg-white border-2 border-gray-300 rounded-lg sm:rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none text-xs sm:text-sm font-medium appearance-none cursor-pointer transition-all">
                                        <option value="">-- Pilih Jaminan --</option>
                                        <option value="KTP">KTP (Asli)</option>
                                        <option value="KTM">KTM (Asli)</option>
                                    </select>
                                    <p class="text-[10px] sm:text-xs text-gray-500 mt-1 sm:mt-1.5 flex items-center gap-1">
                                        <i data-lucide="lock" class="w-3 h-3"></i>
                                        Akan dikembalikan setelah kendaraan dikembalikan
                                    </p>
                                </div>
                                <!-- Tujuan -->
                                <div>
                                    <label class="block text-xs sm:text-sm font-bold text-gray-700 mb-1.5 sm:mb-2 flex items-center gap-1.5 sm:gap-2">
                                        <i data-lucide="map-pin" class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-orange-600"></i>
                                        Tujuan Perjalanan
                                    </label>
                                    <input type="text" name="tujuan" placeholder="Contoh: Kantor Dinas Pendidikan Kota" required class="w-full px-3 sm:px-4 py-2 sm:py-2.5 md:py-3 bg-white rounded-lg sm:rounded-xl border-2 border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none text-xs sm:text-sm font-medium transition-all placeholder:text-gray-400">
                                    <p class="text-[10px] sm:text-xs text-gray-500 mt-1 sm:mt-1.5 flex items-center gap-1">
                                        <i data-lucide="navigation" class="w-3 h-3"></i>
                                        Sebutkan tujuan secara spesifik
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- UPLOAD SURAT PEMINJAMAN -->
                        <div class="bg-gradient-to-br from-purple-50 to-pink-50 p-3 sm:p-4 md:p-5 lg:p-6 rounded-xl sm:rounded-2xl border-2 border-purple-200">
                            <h3 class="text-[11px] sm:text-xs md:text-sm font-black text-gray-800 uppercase tracking-wider mb-3 sm:mb-4 flex items-center gap-1.5 sm:gap-2">
                                <div class="p-1.5 sm:p-2 bg-purple-100 rounded-lg">
                                    <i data-lucide="file-text" class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-purple-600"></i>
                                </div>
                                Upload Surat Peminjaman
                            </h3>
                            
                            <div class="bg-white/70 backdrop-blur-sm p-3 sm:p-4 rounded-lg sm:rounded-xl border border-purple-200 mb-3 sm:mb-4">
                                <p class="text-xs sm:text-sm text-gray-700 font-semibold mb-1.5 sm:mb-2 flex items-center gap-1.5 sm:gap-2">
                                    <i data-lucide="info" class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-purple-600"></i>
                                    Surat harus mencantumkan:
                                </p>
                                <ul class="space-y-1 sm:space-y-1.5 ml-4 sm:ml-5 md:ml-6 list-disc text-[10px] sm:text-xs text-gray-600">
                                    <li>Nama lengkap peminjam (mahasiswa)</li>
                                    <li><strong class="text-purple-700">Nama Dosen Pendamping/Pembimbing</strong></li>
                                    <li>Tujuan dan keperluan peminjaman kendaraan</li>
                                    <li>Tanggal dan durasi peminjaman</li>
                                    <li>Stempel dan tanda tangan yang sah</li>
                                </ul>
                            </div>
                            
                            <div id="drop-zone" class="relative group">
                                <label class="flex flex-col items-center justify-center w-full min-h-[140px] sm:min-h-[160px] border-2 sm:border-3 border-purple-300 border-dashed rounded-xl sm:rounded-2xl cursor-pointer bg-white hover:bg-purple-50 transition-all overflow-hidden">
                                    <div class="flex flex-col items-center justify-center py-4 sm:py-6 text-center px-3 sm:px-4">
                                        <div class="p-3 sm:p-4 bg-purple-100 rounded-full mb-2 sm:mb-3">
                                            <i id="upload-icon" data-lucide="upload-cloud" class="w-6 h-6 sm:w-7 sm:h-7 md:w-8 md:h-8 text-purple-600"></i>
                                        </div>
                                        <p id="file-name-display" class="text-xs sm:text-sm font-bold text-gray-700 mb-0.5 sm:mb-1">
                                            <span class="text-purple-600">Klik untuk memilih file</span> atau drag and drop
                                        </p>
                                        <p id="file-info-text" class="text-[10px] sm:text-xs text-gray-500 mt-0.5 sm:mt-1">PDF, JPG, PNG (Maks: 2MB)</p>
                                    </div>
                                    <input type="file" id="file-input" name="surat_peminjaman" accept=".pdf,.jpg,.jpeg,.png" required class="absolute inset-0 opacity-0 cursor-pointer" />
                                </label>
                            </div>
                        </div>

                        <!-- TOMBOL AKSI -->
                        <div class="flex flex-col sm:flex-row gap-3 sm:gap-4 pt-5 sm:pt-6 md:pt-8 border-t-2 border-gray-200">
                            <button type="button" onclick="history.back()" class="w-full sm:w-auto px-5 sm:px-6 md:px-8 py-3 sm:py-3.5 md:py-4 rounded-lg sm:rounded-xl border-2 border-gray-300 text-gray-700 font-bold hover:bg-gray-50 hover:border-gray-400 transition-all text-xs sm:text-sm flex items-center justify-center gap-1.5 sm:gap-2">
                                <i data-lucide="arrow-left" class="w-3.5 h-3.5 sm:w-4 sm:h-4"></i>
                                Kembali
                            </button>
                            <button type="submit" id="btn_submit" name="kirim_pengajuan" class="w-full flex-1 px-5 sm:px-6 md:px-8 py-3 sm:py-3.5 md:py-4 rounded-lg sm:rounded-xl font-bold text-white transition-all flex items-center justify-center gap-1.5 sm:gap-2 text-xs sm:text-sm bg-gradient-to-r from-[#d13b1f] to-[#b53118] hover:from-[#b53118] hover:to-[#a02815] active:scale-[0.98] shadow-lg shadow-red-200">
                                <i data-lucide="send" class="w-4 h-4 sm:w-5 sm:h-5"></i>
                                Kirim Pengajuan Peminjaman
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <?php include "../components/Footer.php"; ?>

    <!-- IMAGE PREVIEW MODAL -->
    <div id="previewModal" class="hidden fixed inset-0 z-[100] flex items-center justify-center bg-black/90 backdrop-blur-sm p-3 sm:p-4" onclick="closePreview()">
        <div class="relative w-full max-w-4xl max-h-full flex justify-center items-center">
            <img id="modalImg" src="" class="max-w-full max-h-[80vh] sm:max-h-[85vh] rounded-lg sm:rounded-xl shadow-2xl object-contain" onclick="event.stopPropagation()">
            <button onclick="closePreview()" class="absolute -top-10 sm:-top-12 right-0 md:-top-4 md:-right-12 bg-white/20 hover:bg-white/40 text-white p-1.5 sm:p-2 rounded-full transition-all">
                <i data-lucide="x" class="w-6 h-6 sm:w-7 sm:h-7 md:w-8 md:h-8"></i>
            </button>
        </div>
    </div>

    <script>
        lucide.createIcons();

        // --- LOGIKA VALIDASI WAKTU REAL-TIME (sama dengan peminjaman_ruangan) ---
        const tglMulai = document.getElementById('tgl_mulai');
        const jamMulai = document.getElementById('jam_mulai');
        const tglSelesai = document.getElementById('tgl_selesai');
        const jamSelesai = document.getElementById('jam_selesai');
        const btnSubmit = document.getElementById('btn_submit');
        const errorLabel = document.getElementById('error-time');

        function disableSubmit(){
            btnSubmit.disabled = true;
            btnSubmit.classList.add('opacity-50', 'cursor-not-allowed', 'grayscale');
        }
        function enableSubmit(){
            btnSubmit.disabled = false;
            btnSubmit.classList.remove('opacity-50', 'cursor-not-allowed', 'grayscale');
        }

        function checkTime() {
            // butuh start date & time dulu
            if (!tglMulai.value || !jamMulai.value) return;

            const selectedStart = new Date(`${tglMulai.value}T${jamMulai.value}`);
            const now = new Date();

            // start harus >= sekarang
            if (selectedStart < now) {
                disableSubmit();
                tglMulai.classList.add('border-red-500', 'bg-red-50');
                jamMulai.classList.add('border-red-500', 'bg-red-50');
                errorLabel.classList.remove('hidden');
                errorLabel.classList.add('flex');
                lucide.createIcons();
                return;
            } else {
                enableSubmit();
                tglMulai.classList.remove('border-red-500', 'bg-red-50');
                jamMulai.classList.remove('border-red-500', 'bg-red-50');
                errorLabel.classList.add('hidden');
                errorLabel.classList.remove('flex');
            }

            // jika end sudah diisi, pastikan end >= start
            if (tglSelesai && jamSelesai && tglSelesai.value && jamSelesai.value) {
                const selectedEnd = new Date(`${tglSelesai.value}T${jamSelesai.value}`);
                if (selectedEnd <= selectedStart) {
                    disableSubmit();
                    tglSelesai.classList.add('border-red-500', 'bg-red-50');
                    jamSelesai.classList.add('border-red-500', 'bg-red-50');
                    alert('Waktu kembali harus lebih dari waktu ambil!');
                    return;
                } else {
                    enableSubmit();
                    tglSelesai.classList.remove('border-red-500', 'bg-red-50');
                    jamSelesai.classList.remove('border-red-500', 'bg-red-50');
                }
            }
        }

        // event listeners
        tglMulai.addEventListener('input', function(){
            if (tglSelesai) tglSelesai.min = this.value;
            checkTime();
        });
        jamMulai.addEventListener('input', checkTime);
        if (tglSelesai) {
            tglSelesai.addEventListener('change', function(){
                if (this.value < tglMulai.value) {
                    alert('Tanggal kembali tidak boleh lebih awal dari tanggal ambil!');
                    this.value = '';
                }
                checkTime();
            });
        }
        if (jamSelesai) {
            jamSelesai.addEventListener('change', function(){
                if (tglSelesai.value === tglMulai.value) {
                    if (this.value <= jamMulai.value) {
                        alert('Waktu kembali harus lebih dari waktu ambil!');
                        this.value = '';
                    }
                }
                checkTime();
            });
        }

        // --- LOGIKA UPLOAD FILE ---
        const fileInput = document.getElementById('file-input');
        const dropZone = document.getElementById('drop-zone');
        const fileNameDisplay = document.getElementById('file-name-display');
        const uploadIcon = document.getElementById('upload-icon');
        const infoText = document.getElementById('file-info-text');

        fileInput.addEventListener('change', function() {
            if (this.files && this.files.length > 0) {
                const file = this.files[0];
                const fileName = file.name;
                const fileSize = (file.size / 1024 / 1024).toFixed(2);

                if (file.size > 2 * 1024 * 1024) {
                    alert("File terlalu besar! Maksimal 2MB.");
                    this.value = "";
                    return;
                }

                dropZone.querySelector('label').classList.add('border-green-500', 'bg-green-50');
                uploadIcon.classList.remove('text-purple-600');
                uploadIcon.classList.add('text-green-600');
                uploadIcon.setAttribute('data-lucide', 'check-circle-2');
                fileNameDisplay.innerHTML = `<span class="text-green-700 font-bold">Terpilih: ${fileName}</span>`;
                infoText.innerHTML = `<span class="text-green-600 font-medium">${fileSize} MB - Klik untuk ganti file</span>`;
                lucide.createIcons();
            }
        });

        // Preview Modal Functions
        function openPreview(src) {
            if(!src || src === '' || src.includes('undefined')) return;
            const modal = document.getElementById('previewModal');
            const img = document.getElementById('modalImg');
            img.src = src;
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closePreview() {
            const modal = document.getElementById('previewModal');
            modal.classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closePreview();
        });
    </script>
</body>
</html>