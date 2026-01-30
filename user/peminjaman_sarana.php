<?php
session_start();
require "../config/database.php";
require "../auth/auth_helper.php";

cek_kemanan_login($pdo);

// Set timezone agar sinkron dengan waktu lokal
date_default_timezone_set('Asia/Jakarta');

// 1. PROTEKSI LOGIN
if (!isset($_SESSION['login']) || $_SESSION['role'] !== 'user') {
    header("Location: ../login.html");
    exit;
}

// 2. AMBIL DATA SARANA
$id = $_GET['id'] ?? null;
if (!$id) {
    die("ID Sarana tidak ditemukan.");
}

$stmt = $pdo->prepare("SELECT * FROM sarana WHERE id = ?");
$stmt->execute([$id]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$item) {
    die("Data sarana tidak ditemukan.");
}

function getSetting($pdo, $nama_setting, $default = '') {
    $stmt = $pdo->prepare("SELECT nilai FROM pengaturan WHERE nama_setting = ?");
    $stmt->execute([$nama_setting]);
    $result = $stmt->fetch();
    return $result ? $result['nilai'] : $default;
}

$template_file = getSetting($pdo, 'template_sarana_file');
$template_name = getSetting($pdo, 'template_sarana_name', 'Template Surat Peminjaman Sarana');


// 3. LOGIKA STOK TERSEDIA
$stmtCheck = $pdo->prepare("SELECT SUM(jumlah) as total_dipinjam FROM peminjaman 
                            WHERE item_id = ? AND kategori = 'sarana' AND status IN ('pending', 'approved', 'dipinjam')");
$stmtCheck->execute([$id]);
$dipinjamData = $stmtCheck->fetch(PDO::FETCH_ASSOC);
$totalDipinjam = (int)($dipinjamData['total_dipinjam'] ?? 0);

$stokAwal = (int)$item['jumlah'];
$stokTersedia = max(0, $stokAwal - $totalDipinjam);

$isUnavailable = ($stokTersedia <= 0);

$badgeStyle = "bg-green-100 text-green-700 border-green-200";
if ($isUnavailable) {
    $badgeStyle = "bg-red-100 text-red-700 border-red-200";
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../assets/kampusums.png" />
    <title>Pinjam Sarana - <?= htmlspecialchars($item['nama']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        input::-webkit-outer-spin-button, input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
        html { scroll-behavior: smooth; }
        input[type="date"], input[type="time"] {
            -webkit-appearance: none;
            min-height: 45px;
        }
    </style>
</head>
<body class="bg-gray-50 flex flex-col min-h-screen">

    <?php include "../components/UserNavbar.php"; ?>

    <main class="pt-5 md:pt-4 pb-20 px-4 md:px-8 w-full max-w-7xl mx-auto grow">
        
        <button onclick="history.back()" class="group flex items-center gap-2 text-gray-500 hover:text-[#d13b1f] transition-colors mb-6 font-medium text-sm md:text-base">
            <div class="p-2 bg-white rounded-full shadow-sm group-hover:shadow-md border border-gray-200 transition-all">
                <i data-lucide="arrow-left" class="w-4 h-4 md:w-4.5 md:h-4.5"></i>
            </div>
            Kembali ke Daftar
        </button>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 md:gap-8">
            
            <div class="lg:col-span-1">
                <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden lg:sticky lg:top-28">
                    <div class="h-48 md:h-56 bg-gray-200 relative">
                        <?php if($item['photo']): ?>
                            <img src="<?= $item['photo'] ?>" class="w-full h-full object-cover">
                        <?php else: ?>
                            <div class="flex flex-col items-center justify-center h-full text-gray-400">
                                <i data-lucide="package" class="w-12 h-12 opacity-20"></i>
                                <span class="text-xs mt-2">No Image</span>
                            </div>
                        <?php endif; ?>
                        <div class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent"></div>
                        <div class="absolute bottom-4 left-4 right-4 text-white">
                            <h2 class="text-xl md:text-2xl font-bold leading-tight"><?= htmlspecialchars($item['nama']) ?></h2>
                            <div class="flex items-center gap-2 text-xs md:text-sm opacity-90 mt-1">
                                <i data-lucide="tag" class="w-3.5 h-3.5"></i> <?= htmlspecialchars($item['jenis'] ?? 'Sarana Prasarana') ?>
                            </div>
                        </div>
                    </div>

                    <div class="p-5 md:p-6">
                        <div class="inline-flex items-center gap-2 px-4 py-2.5 rounded-full text-sm font-bold border mb-6 w-full justify-center <?= $badgeStyle ?>">
                            <i data-lucide="<?= !$isUnavailable ? 'check-circle' : 'alert-circle' ?>" class="w-4 h-4"></i>
                            Stok Tersedia: <?= $stokTersedia ?> Unit
                        </div>
                        
                        <div class="space-y-4 text-sm">
                            <!-- TIPE BARANG -->
                            <?php if (!empty($item['tipe_barang'])): ?>
                            <div class="flex items-center justify-between border-b border-gray-50 pb-2 gap-2">
                                <span class="text-gray-400 font-medium shrink-0 flex items-center gap-2">
                                    <i data-lucide="package" class="w-4 h-4"></i>
                                    Tipe Barang
                                </span>
                                <span class="text-gray-700 font-bold text-right"><?= htmlspecialchars($item['tipe_barang']) ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <!-- KODE LABEL -->
                            <?php if (!empty($item['kode_label'])): ?>
                            <div class="flex items-center justify-between border-b border-gray-50 pb-2 gap-2">
                                <span class="text-gray-400 font-medium shrink-0 flex items-center gap-2">
                                    <i data-lucide="hash" class="w-4 h-4"></i>
                                    Kode Label
                                </span>
                                <span class="text-gray-700 font-bold text-right font-mono"><?= htmlspecialchars($item['kode_label']) ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <!-- LOKASI -->
                            <div class="flex items-center justify-between border-b border-gray-50 pb-2 gap-2">
                                <span class="text-gray-400 font-medium shrink-0 flex items-center gap-2">
                                    <i data-lucide="map-pin" class="w-4 h-4"></i>
                                    Lokasi
                                </span>
                                <span class="text-gray-700 font-bold text-right"><?= htmlspecialchars($item['lokasi'] ?? '-') ?></span>
                            </div>
                            
                            <!-- KONDISI -->
                            <div class="flex items-center justify-between border-b border-gray-50 pb-2 gap-2">
                                <span class="text-gray-400 font-medium shrink-0 flex items-center gap-2">
                                    <i data-lucide="wrench" class="w-4 h-4"></i>
                                    Kondisi
                                </span>
                                <span class="text-gray-700 font-bold text-right"><?= htmlspecialchars($item['kondisi'] ?? 'Baik') ?></span>
                            </div>
                            
                            <!-- TOTAL STOK -->
                            <div class="flex items-center justify-between border-b border-gray-50 pb-2">
                                <span class="text-gray-400 font-medium flex items-center gap-2">
                                    <i data-lucide="boxes" class="w-4 h-4"></i>
                                    Total Stok
                                </span>
                                <span class="text-gray-700 font-bold"><?= $stokAwal ?> Unit</span>
                            </div>
                            
                            <!-- TAHUN BELI -->
                            <?php if (!empty($item['tahun_beli'])): ?>
                            <div class="flex items-center justify-between border-b border-gray-50 pb-2 gap-2">
                                <span class="text-gray-400 font-medium shrink-0 flex items-center gap-2">
                                    <i data-lucide="calendar" class="w-4 h-4"></i>
                                    Tahun Beli
                                </span>
                                <span class="text-gray-700 font-bold text-right">
                                    <?php
                                        $tanggalBeli = $item['tahun_beli'];
                                        if (strtotime($tanggalBeli)) {
                                            echo date('d M Y', strtotime($tanggalBeli));
                                        } else {
                                            echo htmlspecialchars($tanggalBeli);
                                        }
                                    ?>
                                </span>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- DESKRIPSI -->
                        <?php if (!empty($item['deskripsi'])): ?>
                        <div class="mt-6 pt-6 border-t border-gray-100">
                            <h4 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-2 flex items-center gap-2">
                                <i data-lucide="file-text" class="w-3.5 h-3.5"></i>
                                Deskripsi
                            </h4>
                            <p class="text-sm text-gray-600 italic leading-relaxed">
                                "<?= htmlspecialchars($item['deskripsi']) ?>"
                            </p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-2">
                <div class="bg-white rounded-3xl shadow-lg border border-gray-100 p-5 md:p-8">
                    <div class="mb-8">
                        <h1 class="text-xl md:text-2xl font-bold text-gray-800">Formulir Pinjam Sarana</h1>
                        <p class="text-gray-500 text-xs md:text-sm mt-1">Gunakan form ini untuk mengajukan peminjaman alat atau sarana prasarana.</p>
                    </div>

                    <?php if($isUnavailable): ?>
                        <div class="bg-red-50 border border-red-200 rounded-xl p-4 flex items-start gap-3 mb-6">
                            <i data-lucide="alert-circle" class="text-red-600 shrink-0 mt-0.5 w-5 h-5"></i>
                            <div>
                                <h4 class="font-bold text-red-700 text-sm md:text-base">Stok Tidak Tersedia</h4>
                                <p class="text-xs md:text-sm text-red-600 mt-1">Maaf, saat ini stok barang sedang kosong atau masih dipinjam pihak lain.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                     <?php if ($template_file): ?>
                    <div class="mb-6 p-4 bg-gradient-to-r from-green-50 to-emerald-50 border-2 border-green-200 rounded-2xl">
                        <div class="flex items-start gap-4">
                            <div class="p-3 bg-white rounded-xl shadow-sm">
                                <i data-lucide="file-down" class="w-6 h-6 text-green-600"></i>
                            </div>
                            <div class="flex-1">
                                <h3 class="font-bold text-gray-800 mb-1">Template Tersedia</h3>
                                <p class="text-xs text-gray-600 mb-3">Download template surat peminjaman untuk mempermudah pengisian dokumen</p>
                                <a href="../uploads/templates/<?= htmlspecialchars($template_file) ?>" download
                                    class="inline-flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-green-600 to-emerald-600 text-white rounded-xl font-bold hover:from-green-700 hover:to-emerald-700 shadow-lg hover:shadow-xl transition-all text-sm">
                                    <i data-lucide="download" class="w-4 h-4"></i>
                                    Download <?= htmlspecialchars($template_name) ?>
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <form action="proses_peminjaman.php" method="POST" enctype="multipart/form-data" class="space-y-6 <?= $isUnavailable ? 'opacity-50 pointer-events-none' : '' ?>">
                        <input type="hidden" name="item_id" value="<?= $id ?>">
                        <input type="hidden" name="kategori" value="sarana">

                        <div class="bg-gray-50 p-4 md:p-5 rounded-2xl border border-gray-100">
                            <h3 class="text-[10px] md:text-xs font-bold text-gray-700 uppercase tracking-widest mb-4 flex items-center gap-2">
                                <i data-lucide="settings" class="w-4 h-4"></i> Detail Pinjam
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-5">
                                <div class="col-span-full md:col-span-1">
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Jumlah Barang</label>
                                    <div class="flex items-center bg-white border border-gray-300 rounded-xl overflow-hidden focus-within:ring-2 focus-within:ring-[#d13b1f] focus-within:border-transparent">
                                        <button type="button" onclick="adjustQty(-1)" class="px-4 py-3 bg-gray-50 hover:bg-gray-100 border-r border-gray-300 text-gray-600 transition-colors">
                                            <i data-lucide="minus" class="w-4 h-4"></i>
                                        </button>
                                        <input type="number" name="jumlah" id="jumlahInput" value="1" min="1" max="<?= $stokTersedia ?>" readonly 
                                               class="w-full text-center font-bold text-gray-800 outline-none bg-transparent">
                                        <button type="button" onclick="adjustQty(1)" class="px-4 py-3 bg-gray-50 hover:bg-gray-100 border-l border-gray-300 text-gray-600 transition-colors">
                                            <i data-lucide="plus" class="w-4 h-4"></i>
                                        </button>
                                    </div>
                                    <p class="text-[10px] text-gray-400 mt-2 uppercase font-bold tracking-tight">Maksimal: <?= $stokTersedia ?> Unit</p>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Kartu Jaminan</label>
                                    <div class="relative">
                                        <select name="jaminan" required class="w-full px-4 py-3 bg-white border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#d13b1f] outline-none appearance-none cursor-pointer text-sm">
                                            <option value="" disabled selected>Pilih Kartu Jaminan</option>
                                            <option value="KTM">KTM (Kartu Tanda Mahasiswa)</option>
                                            <option value="KTP">KTP (Kartu Tanda Penduduk)</option>
                                        </select>
                                        <i data-lucide="chevron-down" class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="bg-gray-50 p-4 md:p-5 rounded-2xl border border-gray-100">
                            <h3 class="text-[10px] md:text-xs font-bold text-gray-700 uppercase tracking-widest mb-4 flex items-center gap-2">
                                <i data-lucide="calendar" class="w-4 h-4"></i> Durasi Peminjaman
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                <div class="space-y-2">
                                    <label class="block text-sm font-medium text-gray-700">Mulai Pinjam</label>
                                    <div class="flex flex-col sm:flex-row gap-2">
                                        <input type="date" id="tgl_mulai" name="tanggal_mulai" min="<?= date('Y-m-d') ?>" required class="w-full px-3 py-2.5 bg-white border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#d13b1f] outline-none text-sm">
                                        <input type="time" id="jam_mulai" name="waktu_mulai" required class="w-full sm:w-32 px-3 py-2.5 bg-white border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#d13b1f] outline-none text-sm">
                                    </div>
                                </div>
                                <div class="space-y-2">
                                    <label class="block text-sm font-medium text-gray-700">Selesai/Kembali</label>
                                    <div class="flex flex-col sm:flex-row gap-2">
                                        <input type="date" id="tgl_selesai" name="tanggal_selesai" min="<?= date('Y-m-d') ?>" required class="w-full px-3 py-2.5 bg-white border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#d13b1f] outline-none text-sm">
                                        <input type="time" id="jam_selesai" name="waktu_selesai" required class="w-full sm:w-32 px-3 py-2.5 bg-white border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#d13b1f] outline-none text-sm">
                                    </div>
                                </div>
                            </div>
                            <p id="error-time" class="hidden text-red-500 text-[10px] font-bold mt-2 uppercase tracking-wide flex items-center gap-1">
                                <i data-lucide="alert-circle" class="w-3 h-3"></i> Waktu peminjaman tidak boleh sudah lewat!
                            </p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-5">
                            <div>
                                <label class="block text-xs md:text-sm font-semibold text-gray-700 mb-1.5">No. WhatsApp Utama</label>
                                <input type="tel" name="telepon_utama" placeholder="08xxxx" required oninput="this.value = this.value.replace(/[^0-9]/g, '');" inputmode="numeric" class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:ring-2 focus:ring-[#d13b1f] outline-none text-sm transition-all">
                            </div>
                            <div>
                                <label class="block text-xs md:text-sm font-semibold text-gray-700 mb-1.5">Kontak Darurat</label>
                                <input type="tel" name="telepon_darurat" placeholder="08xxxx" required oninput="this.value = this.value.replace(/[^0-9]/g, '');" inputmode="numeric" class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:ring-2 focus:ring-[#d13b1f] outline-none text-sm transition-all">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Upload Surat Peminjaman</label>
                            <div id="drop-zone" class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-2xl hover:border-[#d13b1f] transition-all relative bg-white">
                                <div class="space-y-1 text-center">
                                    <i id="upload-icon" data-lucide="upload-cloud" class="mx-auto h-10 w-10 text-gray-400 transition-colors"></i>
                                    <div class="flex text-sm text-gray-600 justify-center">
                                        <p id="file-name-display" class="font-medium text-gray-600">
                                            <span class="text-[#d13b1f] hover:text-[#b53118] cursor-pointer">Pilih file</span> atau drag and drop
                                        </p>
                                    </div>
                                    <p id="file-size-info" class="text-[10px] text-gray-400 uppercase">PDF, JPG, PNG up to 2MB</p>
                                </div>
                                <input type="file" id="file-input" name="surat_peminjaman" accept=".pdf,.jpg,.jpeg,.png" required class="absolute inset-0 opacity-0 cursor-pointer">
                            </div>
                        </div>
                        <div class="flex flex-col sm:flex-row gap-3 pt-4 border-t border-gray-100">
                            <button type="button" onclick="history.back()" class="w-full sm:w-auto px-6 py-3.5 rounded-xl border border-gray-300 text-gray-700 font-semibold hover:bg-gray-50 transition-colors text-sm">
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

    <?php include "../components/footer.php"; ?>

    <script>
        lucide.createIcons();
        
        function adjustQty(amount) {
            const input = document.getElementById('jumlahInput');
            let val = parseInt(input.value) + amount;
            if (val >= 1 && val <= <?= $stokTersedia ?>) {
                input.value = val;
            }
        }

        // --- LOGIKA VALIDASI WAKTU REAL-TIME (SAMA SEPERTI PEMINJAMAN RUANGAN) ---
        const tglMulai = document.getElementById('tgl_mulai');
        const jamMulai = document.getElementById('jam_mulai');
        const tglSelesai = document.getElementById('tgl_selesai');
        const jamSelesai = document.getElementById('jam_selesai');
        const btnSubmit = document.getElementById('btn_submit');
        const errorLabel = document.getElementById('error-time');

        function checkTime() {
            if (!tglMulai.value || !jamMulai.value) return;

            const selectedDate = new Date(`${tglMulai.value}T${jamMulai.value}`);
            const now = new Date();

            if (selectedDate < now) {
                btnSubmit.disabled = true;
                btnSubmit.classList.add('opacity-50', 'cursor-not-allowed', 'grayscale');
                tglMulai.classList.add('border-red-500', 'bg-red-50');
                jamMulai.classList.add('border-red-500', 'bg-red-50');
                errorLabel.classList.remove('hidden');
                lucide.createIcons();
            } else {
                btnSubmit.disabled = false;
                btnSubmit.classList.remove('opacity-50', 'cursor-not-allowed', 'grayscale');
                tglMulai.classList.remove('border-red-500', 'bg-red-50');
                jamMulai.classList.remove('border-red-500', 'bg-red-50');
                errorLabel.classList.add('hidden');
            }
        }

        tglMulai.addEventListener('input', checkTime);
        jamMulai.addEventListener('input', checkTime);

        // Set minimum tanggal selesai = tanggal mulai
        tglMulai.addEventListener('change', function() {
            tglSelesai.min = this.value;
            if (tglSelesai.value && tglSelesai.value < this.value) {
                tglSelesai.value = '';
                jamSelesai.value = '';
            }
        });

        // Validasi tanggal selesai
        tglSelesai.addEventListener('change', function() {
            if (this.value < tglMulai.value) {
                alert('Tanggal selesai tidak boleh lebih awal dari tanggal mulai!');
                this.value = '';
            }
        });

        // Validasi waktu selesai
        jamSelesai.addEventListener('change', function() {
            if (tglSelesai.value === tglMulai.value) {
                if (this.value <= jamMulai.value) {
                    alert('Waktu selesai harus lebih dari waktu mulai!');
                    this.value = '';
                }
            }
        });
    </script>
    
    <script>
        // File upload handling
        const fileInput = document.getElementById('file-input');
        const dropZone = document.getElementById('drop-zone');
        const fileNameDisplay = document.getElementById('file-name-display');
        const uploadIcon = document.getElementById('upload-icon');

        fileInput.addEventListener('change', function() {
            if (this.files && this.files.length > 0) {
                const fileName = this.files[0].name;
                
                dropZone.classList.remove('border-gray-300');
                dropZone.classList.add('border-green-500', 'bg-green-50');
                
                uploadIcon.classList.remove('text-gray-400');
                uploadIcon.classList.add('text-green-500');
                uploadIcon.setAttribute('data-lucide', 'check-circle');
                
                fileNameDisplay.innerHTML = `<span class="text-green-700 font-bold">Terpilih: ${fileName}</span>`;
                
                lucide.createIcons();
            } else {
                dropZone.classList.remove('border-green-500', 'bg-green-50');
                dropZone.classList.add('border-gray-300');
                uploadIcon.setAttribute('data-lucide', 'upload-cloud');
                lucide.createIcons();
            }
        });

        fileInput.addEventListener('dragenter', () => dropZone.classList.add('border-[#d13b1f]', 'bg-gray-50'));
        fileInput.addEventListener('dragleave', () => dropZone.classList.remove('border-[#d13b1f]', 'bg-gray-50'));
        fileInput.addEventListener('drop', () => dropZone.classList.remove('border-[#d13b1f]', 'bg-gray-50'));
    </script>
</body>
</html>