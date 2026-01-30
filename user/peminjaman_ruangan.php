<?php
session_start();
require "../config/database.php";
require "../auth/auth_helper.php";

cek_kemanan_login($pdo);

date_default_timezone_set('Asia/Jakarta');

// 1. PROTEKSI LOGIN
if (!isset($_SESSION['login']) || $_SESSION['role'] !== 'user') {
    header("Location: ../login.html");
    exit;
}

// 2. AMBIL DATA RUANGAN
$id = $_GET['id'] ?? null;
if (!$id) { die("ID Ruangan tidak ditemukan."); }

$stmt = $pdo->prepare("SELECT r.*, g.nama_gedung FROM ruangan r INNER JOIN gedung g ON r.gedung_id = g.id WHERE r.id = ?");
$stmt->execute([$id]);
$room = $stmt->fetch(PDO::FETCH_ASSOC);

// 3. AMBIL TEMPLATE DARI DATABASE
function getSetting($pdo, $nama_setting, $default = '') {
    $stmt = $pdo->prepare("SELECT nilai FROM pengaturan WHERE nama_setting = ?");
    $stmt->execute([$nama_setting]);
    $result = $stmt->fetch();
    return $result ? $result['nilai'] : $default;
}

$template_file = getSetting($pdo, 'template_ruangan_file');
$template_name = getSetting($pdo, 'template_ruangan_name', 'Template Surat Peminjaman Ruangan');

// 4. LOGIKA STATUS RUANGAN
$status_fisik = $room['status_fisik'] ?? 'Baik';
$status_booking = $room['status_booking'] ?? 'Tersedia';

$statusRuangan = "Tersedia";
if ($status_fisik === "Perlu Perbaikan") {
    $statusRuangan = "Perlu Perbaikan";
} elseif ($status_fisik === "Sedang Dipakai" || $status_booking === "Dipinjam") {
    $statusRuangan = "Tidak Tersedia";
}

$isUnavailable = ($statusRuangan !== "Tersedia");
$badgeStyle = $isUnavailable ? "bg-red-100 text-red-700 border-red-200" : "bg-green-100 text-green-700 border-green-200";

// 5. FUNGSI UNTUK MENGHITUNG DAN MENGELOMPOKKAN FASILITAS BERDASARKAN NAMA (TANPA KODE)
function groupFacilities($fasilitasString) {
    if (empty($fasilitasString)) {
        return [];
    }
    
    $items = array_map('trim', explode(',', $fasilitasString));
    $grouped = [];
    
    foreach ($items as $item) {
        $baseName = preg_replace('/[A-Z0-9\-_].*/u', '', $item);
        
        if (empty($baseName)) {
            $baseName = preg_split('/[A-Z0-9\-_]/', $item)[0];
        }
        
        $baseName = ucfirst(strtolower(trim($baseName)));
        
        if (isset($grouped[$baseName])) {
            $grouped[$baseName]++;
        } else {
            $grouped[$baseName] = 1;
        }
    }
    
    return $grouped;
}

$fasilitasGrouped = groupFacilities($room['fasilitas']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Pinjam <?= htmlspecialchars($room['nama_ruangan']) ?> | Inventory FT</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        input[type="date"], input[type="time"] {
            -webkit-appearance: none;
            min-height: 45px;
        }
    </style>
</head>
<body class="bg-gray-50 flex flex-col min-h-screen">

    <?php include "../components/UserNavbar.php"; ?>

    <main class="pt-5 md:pt-10 pb-20 px-4 md:px-8 w-full max-w-7xl mx-auto flex-1">
        
        <button onclick="location.href='ruangan.php'" class="group flex items-center gap-2 text-gray-500 hover:text-[#d13b1f] mb-6 font-semibold transition-all text-sm md:text-base">
            <div class="p-2 bg-white rounded-full shadow-sm group-hover:shadow-md border border-gray-200 transition-all">
                <i data-lucide="arrow-left" class="w-4 h-4 md:w-5 md:h-5"></i>
            </div>
            Kembali ke Daftar Ruangan
        </button>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 md:gap-8">
            
            <div class="lg:col-span-1">
                <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden lg:sticky lg:top-24">
                    <div class="h-48 md:h-60 bg-gray-200">
                        <img src="<?= $room['photo'] ?: '../assets/default-room.jpg' ?>" class="w-full h-full object-cover">
                    </div>
                    <div class="p-5 md:p-6">
                        <h2 class="text-xl md:text-2xl font-bold text-gray-800 leading-tight"><?= htmlspecialchars($room['nama_ruangan']) ?></h2>
                        <div class="flex flex-col gap-2 mt-3 mb-6">
                            <p class="text-gray-500 flex items-center gap-2 text-xs md:text-sm">
                                <i data-lucide="map-pin" class="w-4 h-4 text-[#d13b1f]"></i> 
                                <?= $room['nama_ruangan'] ?>, <?= $room['lantai'] ?>
                            </p>
                            <p class="text-gray-500 flex items-center gap-2 text-xs md:text-sm">
                                <i data-lucide="users" class="w-4 h-4 text-[#d13b1f]"></i> 
                                Kapasitas: <?= $room['kapasitas'] ?> Orang
                            </p>
                            <div class="flex items-start gap-2 mt-1">
                                <i data-lucide="package" class="w-4 h-4 text-[#d13b1f] shrink-0 mt-0.5"></i> 
                                <div class="flex flex-wrap gap-1">
                                    <?php 
                                    if(!empty($fasilitasGrouped)) {
                                        foreach($fasilitasGrouped as $fasilitas => $jumlah): ?>
                                            <span class="text-[10px] font-bold text-gray-600 bg-gray-100 px-2 py-0.5 rounded-md uppercase">
                                                <?= htmlspecialchars($fasilitas) ?> (<?= $jumlah ?>)
                                            </span>
                                        <?php endforeach; 
                                    } else {
                                        echo '<span class="text-xs text-gray-400 italic">Tidak ada fasilitas terdaftar</span>';
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="inline-flex items-center gap-2 px-4 py-2.5 rounded-2xl text-xs md:text-sm font-bold border w-full justify-center <?= $badgeStyle ?>">
                            <i data-lucide="<?= $isUnavailable ? 'alert-circle' : 'check-circle' ?>" class="w-4 h-4"></i>
                            Status: <?= $statusRuangan ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-2">
                <div class="bg-white rounded-3xl shadow-lg border border-gray-100 p-5 md:p-8">
                    <header class="mb-8">
                        <h1 class="text-xl md:text-2xl font-bold text-gray-800">Formulir Peminjaman</h1>
                        <p class="text-gray-500 text-xs md:text-sm mt-1">Lengkapi detail pemakaian untuk verifikasi admin.</p>
                    </header>

                    <!-- TEMPLATE DOWNLOAD SECTION (BARU) -->
                    <?php if ($template_file): ?>
                    <div class="mb-6 p-4 bg-gradient-to-r from-blue-50 to-indigo-50 border-2 border-blue-200 rounded-2xl">
                        <div class="flex items-start gap-4">
                            <div class="p-3 bg-white rounded-xl shadow-sm">
                                <i data-lucide="file-down" class="w-6 h-6 text-blue-600"></i>
                            </div>
                            <div class="flex-1">
                                <h3 class="font-bold text-gray-800 mb-1">Template Tersedia</h3>
                                <p class="text-xs text-gray-600 mb-3">Download template surat peminjaman untuk mempermudah pengisian dokumen</p>
                                <a href="../uploads/templates/<?= htmlspecialchars($template_file) ?>" download
                                    class="inline-flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-xl font-bold hover:from-blue-700 hover:to-indigo-700 shadow-lg hover:shadow-xl transition-all text-sm">
                                    <i data-lucide="download" class="w-4 h-4"></i>
                                    Download <?= htmlspecialchars($template_name) ?>
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

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

                    <?php if($isUnavailable): ?>
                        <div class="bg-red-50 border border-red-100 p-4 rounded-2xl flex items-start gap-3 mb-6 text-red-700">
                            <i data-lucide="alert-triangle" class="w-5 h-5 shrink-0"></i>
                            <p class="text-xs md:text-sm font-semibold">Maaf, ruangan ini tidak tersedia sementara waktu.</p>
                        </div>
                    <?php endif; ?>

                    <form action="proses_peminjaman.php" method="POST" enctype="multipart/form-data" class="space-y-6 <?= $isUnavailable ? 'opacity-40 pointer-events-none grayscale' : '' ?>">
                        <input type="hidden" name="item_id" value="<?= $id ?>">
                        <input type="hidden" name="kategori" value="ruangan">

                        <div class="bg-gray-50 p-4 md:p-6 rounded-2xl border border-gray-100">
                            <h3 class="text-[10px] md:text-xs font-bold text-gray-400 uppercase tracking-widest mb-4 flex items-center gap-2">
                                <i data-lucide="calendar-clock" class="w-4 h-4"></i> Jadwal Penggunaan
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs md:text-sm font-semibold text-gray-700 mb-1.5">Waktu Mulai</label>
                                    <div class="flex flex-col sm:flex-row gap-2">
                                        <input type="date" id="tgl_mulai" name="tanggal_mulai" min="<?= date('Y-m-d') ?>" required class="flex-1 px-4 py-2.5 rounded-xl border border-gray-300 focus:ring-2 focus:ring-[#d13b1f] outline-none text-sm">
                                        <input type="time" id="jam_mulai" name="waktu_mulai" required class="sm:w-32 px-4 py-2.5 rounded-xl border border-gray-300 focus:ring-2 focus:ring-[#d13b1f] outline-none text-sm">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs md:text-sm font-semibold text-gray-700 mb-1.5">Waktu Selesai</label>
                                    <div class="flex flex-col sm:flex-row gap-2">
                                        <input type="date" id="tgl_selesai" name="tanggal_selesai" min="<?= date('Y-m-d') ?>" required class="flex-1 px-4 py-2.5 rounded-xl border border-gray-300 focus:ring-2 focus:ring-[#d13b1f] outline-none text-sm">
                                        <input type="time" id="jam_selesai" name="waktu_selesai" required class="sm:w-32 px-4 py-2.5 rounded-xl border border-gray-300 focus:ring-2 focus:ring-[#d13b1f] outline-none text-sm">
                                    </div>
                                </div>
                            </div>
                            <p id="error-time" class="hidden text-red-500 text-[10px] font-bold mt-2 uppercase tracking-wide italic items-center gap-1">
                                <i data-lucide="info" class="w-3 h-3"></i> Waktu peminjaman tidak boleh sudah lewat!
                            </p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div>
                                <label class="block text-xs md:text-sm font-semibold text-gray-700 mb-1.5">No. WhatsApp Utama</label>
                                <input type="tel" name="telepon_utama" placeholder="08xxxx" required oninput="this.value = this.value.replace(/[^0-9]/g, '');" inputmode="numeric" class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:ring-2 focus:ring-[#d13b1f] outline-none text-sm transition-all">
                            </div>
                            <div>
                                <label class="block text-xs md:text-sm font-semibold text-gray-700 mb-1.5">Kontak Darurat</label>
                                <input type="tel" name="telepon_darurat" placeholder="08xxxx" required oninput="this.value = this.value.replace(/[^0-9]/g, '');" inputmode="numeric" class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:ring-2 focus:ring-[#d13b1f] outline-none text-sm transition-all">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-5">
                            <div>
                                <label class="block text-xs md:text-sm font-semibold text-gray-700 mb-1.5">Jaminan Fisik</label>
                                <select name="jaminan" required class="w-full px-4 py-3 rounded-xl border border-gray-300 outline-none text-sm bg-white cursor-pointer">
                                    <option value="" disabled selected>Pilih Jaminan Fisik</option>
                                    <option value="KTM">KTM (Kartu Tanda Mahasiswa)</option>
                                    <option value="KTP">KTP (Kartu Tanda Penduduk)</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Upload Surat Peminjaman</label>
                            <div id="drop-zone" class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-2xl hover:border-[#d13b1f] transition-all relative bg-white">
                                <div class="space-y-1 text-center">
                                    <i id="upload-icon" data-lucide="upload-cloud" class="mx-auto h-10 w-10 text-gray-400 transition-colors"></i>
                                    <div class="flex text-sm text-gray-600 justify-center">
                                        <p id="file-name-display" class="font-medium">
                                            <span class="text-[#d13b1f] hover:text-[#b53118] cursor-pointer">Pilih file</span> atau drag and drop
                                        </p>
                                    </div>
                                    <p id="file-info-text" class="text-[10px] text-gray-400 uppercase">PDF, JPG, PNG up to 2MB</p>
                                </div>
                                <input type="file" name="surat_peminjaman" id="file-input" accept=".pdf,.jpg,.jpeg,.png" required class="absolute inset-0 opacity-0 cursor-pointer">
                            </div>
                        </div>

                        <div class="flex flex-col sm:flex-row gap-3 pt-4 border-t border-gray-100">
                            <button type="button" onclick="history.back()" class="w-full sm:w-auto px-6 py-3.5 rounded-xl border border-gray-200 text-gray-700 font-semibold hover:bg-gray-50 transition-colors text-sm">
                                Batal
                            </button>
                            <button type="submit" id="btn_submit" name="kirim_pengajuan" class="w-full flex-1 px-6 py-3.5 rounded-xl font-bold text-white shadow-lg transition-all transform active:scale-95 bg-[#d13b1f] hover:bg-[#b53118] shadow-red-200 text-sm">
                                Kirim Pengajuan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <?php include "../components/Footer.php"; ?>

    <script>
        lucide.createIcons();

        // VALIDASI WAKTU REAL-TIME
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
            if (!tglMulai.value || !jamMulai.value) return;

            const selectedStart = new Date(`${tglMulai.value}T${jamMulai.value}`);
            const now = new Date();

            if (selectedStart < now) {
                disableSubmit();
                tglMulai.classList.add('border-red-500', 'bg-red-50');
                jamMulai.classList.add('border-red-500', 'bg-red-50');
                errorLabel.classList.remove('hidden');
                lucide.createIcons();
                return;
            } else {
                enableSubmit();
                tglMulai.classList.remove('border-red-500', 'bg-red-50');
                jamMulai.classList.remove('border-red-500', 'bg-red-50');
                errorLabel.classList.add('hidden');
            }

            if (tglSelesai && jamSelesai && tglSelesai.value && jamSelesai.value) {
                const selectedEnd = new Date(`${tglSelesai.value}T${jamSelesai.value}`);
                if (selectedEnd < selectedStart) {
                    disableSubmit();
                    alert('Waktu selesai harus lebih dari waktu mulai!');
                    return;
                } else {
                    enableSubmit();
                }
            }
        }

        tglMulai.addEventListener('input', function(){
            if (tglSelesai) tglSelesai.min = this.value;
            checkTime();
        });
        jamMulai.addEventListener('input', checkTime);
        if (tglSelesai) {
            tglSelesai.addEventListener('change', function(){
                if (this.value < tglMulai.value) {
                    alert('Tanggal selesai tidak boleh lebih awal dari tanggal mulai!');
                    this.value = '';
                }
                checkTime();
            });
        }
        if (jamSelesai) {
            jamSelesai.addEventListener('change', function(){
                if (tglSelesai.value === tglMulai.value) {
                    if (this.value <= jamMulai.value) {
                        alert('Waktu selesai harus lebih dari waktu mulai!');
                        this.value = '';
                    }
                }
                checkTime();
            });
        }

        // LOGIKA UPLOAD FILE
        const fileInput = document.getElementById('file-input');
        const dropZone = document.getElementById('drop-zone');
        const fileNameDisplay = document.getElementById('file-name-display');
        const uploadIcon = document.getElementById('upload-icon');
        const infoText = document.getElementById('file-info-text');

        fileInput.addEventListener('change', function() {
            if (this.files && this.files.length > 0) {
                const fileName = this.files[0].name;
                dropZone.classList.add('border-green-500', 'bg-green-50');
                uploadIcon.classList.replace('text-gray-400', 'text-green-500');
                uploadIcon.setAttribute('data-lucide', 'check-circle-2');
                fileNameDisplay.innerHTML = `<span class="text-green-700 font-bold">Terpilih: ${fileName}</span>`;
                infoText.innerText = "Klik untuk mengganti file";
                lucide.createIcons();
            }
        });
    </script>
</body>
</html>