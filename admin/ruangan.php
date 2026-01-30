<?php
session_start();
require "../config/database.php"; 
require "../auth/auth_helper.php";

cek_kemanan_login($pdo);


$msg = "";

// --- Ambil Nama, Kode Label, dan Tipe Barang Sarana ---
$stmtSarana = $pdo->query("SELECT nama, kode_label, tipe_barang FROM sarana ORDER BY tipe_barang ASC, nama ASC, kode_label ASC");
$daftar_sarana = $stmtSarana->fetchAll(PDO::FETCH_ASSOC);

// --- Ambil Data Gedung dari Database ---
$stmtGedung = $pdo->query("SELECT * FROM gedung ORDER BY nama_gedung ASC");
$daftar_gedung = $stmtGedung->fetchAll(PDO::FETCH_ASSOC);

// --- Ambil semua fasilitas yang sudah digunakan oleh ruangan lain ---
function getFasilitasTerpakai($pdo, $excludeRoomId = null) {
    $sql = "SELECT fasilitas FROM ruangan WHERE fasilitas IS NOT NULL AND fasilitas != ''";
    $params = [];
    
    if ($excludeRoomId) {
        $sql .= " AND id != ?";
        $params[] = $excludeRoomId;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $allRooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $terpakai = [];
    foreach ($allRooms as $room) {
        $items = array_map('trim', explode(',', $room['fasilitas']));
        foreach ($items as $item) {
            if (!empty($item)) {
                $terpakai[] = $item;
            }
        }
    }
    
    return $terpakai;
}

// ⚡ Inisialisasi fasilitasTerpakai sebagai array kosong dulu
$fasilitasTerpakai = [];

// 1. Logika Hapus Data
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM ruangan WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: ruangan.php?status=deleted");
        exit;
    } catch (PDOException $e) {
        $msg = "Gagal menghapus: " . $e->getMessage();
    }
}

// 2. Logika Tambah & Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id        = !empty($_POST['id']) ? $_POST['id'] : null;
    $nama      = $_POST['nama_ruangan'];
    $gedung_id = (int)$_POST['gedung_id'];  // Ubah dari 'gedung' ke 'gedung_id' dan cast ke INT
    $lantai    = $_POST['lantai'];
    $kapasitas = (int)$_POST['kapasitas'];
    
    // Ambil ukuran ruangan dari POST
    $ukuran_panjang = !empty($_POST['ukuran_panjang']) ? $_POST['ukuran_panjang'] : null;
    $ukuran_lebar = !empty($_POST['ukuran_lebar']) ? $_POST['ukuran_lebar'] : null;
    $ukuran_luas = !empty($_POST['ukuran_luas']) ? $_POST['ukuran_luas'] : null;
    
    $fasilitas = isset($_POST['fasilitas']) ? implode(', ', $_POST['fasilitas']) : '';
    
    $deskripsi = $_POST['deskripsi'];
    $status    = $_POST['status'];
    $photo     = $_POST['photo_base64'];

    try {
        // 🔒 VALIDASI DUPLIKAT NAMA RUANGAN
        if ($id) {
            // Saat update, cek apakah nama sudah dipakai ruangan lain (kecuali dirinya sendiri)
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM ruangan WHERE nama_ruangan = ? AND id != ?");
            $checkStmt->execute([$nama, $id]);
        } else {
            // Saat tambah baru, cek apakah nama sudah ada
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM ruangan WHERE nama_ruangan = ?");
            $checkStmt->execute([$nama]);
        }
        
        $count = $checkStmt->fetchColumn();
        
        if ($count > 0) {
            $msg = "⚠️ Nama ruangan '$nama' sudah terdaftar! Gunakan nama lain.";
        } else {
            // Lanjutkan proses simpan jika tidak ada duplikat
            if ($id) {
                $sql = "UPDATE ruangan SET nama_ruangan=?, gedung_id=?, lantai=?, kapasitas=?, ukuran_panjang=?, ukuran_lebar=?, ukuran_luas=?, fasilitas=?, deskripsi=?, photo=?, status=?, updated_at=NOW() WHERE id=?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$nama, $gedung_id, $lantai, $kapasitas, $ukuran_panjang, $ukuran_lebar, $ukuran_luas, $fasilitas, $deskripsi, $photo, $status, $id]);
                $msg = "✅ Data ruangan berhasil diperbarui!";
            } else {
                $sql = "INSERT INTO ruangan (nama_ruangan, gedung_id, lantai, kapasitas, ukuran_panjang, ukuran_lebar, ukuran_luas, fasilitas, deskripsi, photo, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$nama, $gedung_id, $lantai, $kapasitas, $ukuran_panjang, $ukuran_lebar, $ukuran_luas, $fasilitas, $deskripsi, $photo, $status]);

                $msg = "✅ Ruangan baru berhasil ditambahkan!";
            }
        }
    } catch (PDOException $e) {
        $msg = "❌ Error Database: " . $e->getMessage();
    }
}

// 3. Logika Ambil Data (Search & Filter)
$search = $_GET['search'] ?? '';
$filter_gedung = $_GET['filter_gedung'] ?? 'all';

// Query dengan JOIN ke tabel gedung
$sql_query = "SELECT r.*, g.nama_gedung, g.jumlah_lantai 
              FROM ruangan r 
              INNER JOIN gedung g ON r.gedung_id = g.id 
              WHERE 1=1";
$params = [];

if (!empty($search)) {
    // Gunakan alias tabel: r. untuk ruangan, g. untuk gedung
    $sql_query .= " AND (r.nama_ruangan LIKE :s1 OR r.fasilitas LIKE :s2 OR g.nama_gedung LIKE :s3)";
    $params[':s1'] = "%$search%";
    $params[':s2'] = "%$search%";
    $params[':s3'] = "%$search%";
}

if ($filter_gedung !== 'all') {
    // Filter menggunakan gedung_id (INT)
    $sql_query .= " AND r.gedung_id = :g";
    $params[':g'] = (int)$filter_gedung;  // Cast ke INT
}

$sql_query .= " ORDER BY id DESC";
$stmt = $pdo->prepare($sql_query);
$stmt->execute($params);
$rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 🎯 FUNGSI GROUPING FASILITAS
function groupFacilities($fasilitasString) {
    if (empty($fasilitasString)) {
        return [];
    }
    
    $items = array_map('trim', explode(',', $fasilitasString));
    $grouped = [];
    
    foreach ($items as $item) {
        if (preg_match('/^(.+?)\s*\[(.+?)\]$/', $item, $matches)) {
            $baseName = trim($matches[1]);
        } else {
            $baseName = preg_replace('/[A-Z0-9\-_].*/u', '', $item);
            
            if (empty($baseName)) {
                $baseName = preg_split('/[A-Z0-9\-_]/', $item)[0];
            }
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
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../assets/kampusums.png" />
    <title>Kelola Ruangan</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }
        body.modal-open > *:not(.logout-modal) {
    filter: blur(4px);
    transition: filter 0.2s ease;
}
    </style>
</head>
<body class="bg-gray-50 font-sans flex flex-col lg:flex-row" x-data="ruanganApp()">

    <?php include "../components/AdminNavbar.php"; ?>

    <main class="flex-1 lg:ml-60 p-4 md:p-5 mt-16 lg:mt-0 min-h-screen w-full overflow-x-hidden">
        <div class="max-w-7xl mx-auto">
            
            <div class="flex flex-col md:flex-row md:justify-between md:items-end gap-3 md:gap-4 mb-8">
                <div>
                    <h1 class="text-2xl md:text-3xl font-bold text-gray-800"> Kelola Ruangan </h1>
                    <p class="text-gray-500 mt-1 text-sm md:text-base">Memanajement Ruangan Fakultas Teknik.</p>
                </div>
                <div class="flex flex-col sm:flex-row gap-2 sm:gap-3">
                    <a href="gedung.php" class="flex items-center justify-center gap-2 bg-gray-600 hover:bg-gray-700 text-white px-4 md:px-5 py-2 text-sm md:text-base rounded-lg md:rounded-xl font-medium shadow-md transition-all">
                        <i data-lucide="building-2"></i> Kelola Gedung
                    </a>

                <button @click="openTambah()" class="flex items-center justify-center gap-2 bg-[#d13b1f] hover:bg-[#b53118] text-white px-4 md:px-5 py-2 text-sm md:text-base rounded-lg md:rounded-xl font-medium shadow-md transition-all">
                    <i data-lucide="plus"></i>
                    Kelola Ruangan
                </button>
                </div>
            </div>


            <?php if($msg || isset($_GET['status'])): ?>
            <div class="<?= strpos($msg, '⚠️') !== false || strpos($msg, '❌') !== false ? 'bg-red-100 border-red-200 text-red-700' : 'bg-green-100 border-green-200 text-green-700' ?> border px-4 py-3 rounded-xl mb-6 flex items-center justify-between shadow-sm">
                <div class="flex items-center gap-2 font-medium">
                    <i data-lucide="<?= strpos($msg, '⚠️') !== false || strpos($msg, '❌') !== false ? 'alert-circle' : 'check-circle' ?>" size="18"></i> 
                    <?= $msg ?: "Aksi berhasil diproses!" ?>
                </div>
                <button type="button" onclick="window.location.href='ruangan.php'"><i data-lucide="x" size="16"></i></button>
            </div>
            <?php endif; ?>

            <div x-show="showForm" x-transition x-cloak class="relative bg-black/5 rounded-2xl shadow-xl border border-black/10 p-6 mb-8">
                <button type="button" 
                        @click="showForm = false"
                        class="absolute top-1 right-1 w-10 h-10 flex items-center justify-center bg-white hover:bg-red-50 text-gray-400 hover:text-red-500 rounded-xl border border-gray-200 hover:border-red-300 transition-all shadow-sm hover:shadow-md z-10 group">
                    <i data-lucide="x" class="w-5 h-5 group-hover:scale-110 transition-transform"></i>
                </button>                
            <form action="ruangan.php" method="POST">
                    <input type="hidden" name="id" x-model="form.id">
                    <input type="hidden" name="photo_base64" x-model="form.photo">
                    <input type="hidden" name="ukuran_panjang" x-model="selectedRoomSize.panjang">
                    <input type="hidden" name="ukuran_lebar" x-model="selectedRoomSize.lebar">
                    <input type="hidden" name="ukuran_luas" x-model="selectedRoomSize.luas">
                    
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                        <div class="lg:col-span-1">
                            <label class="block text-[10px] font-black text-gray-400 uppercase mb-3">Foto Ruangan</label>
                            <div @click="$refs.fileInput.click()" class="relative h-64 w-full border-2 border-dashed border-gray-300 rounded-xl bg-gray-50 flex flex-col items-center justify-center cursor-pointer overflow-hidden group hover:border-[#d13b1f] transition-all">
                                <template x-if="form.photo">
                                    <img :src="form.photo" class="w-full h-full object-cover">
                                </template>
                                <template x-if="!form.photo">
                                    <div class="text-center text-gray-400">
                                        <i data-lucide="camera" class="mx-auto mb-2"></i>
                                        <p class="text-xs">Upload Foto</p>
                                    </div>
                                </template>
                                <input type="file" x-ref="fileInput" class="hidden" @change="handleFile" accept="image/*">
                            </div>
                            
                            <!-- Info Ukuran Ruangan -->
                            <template x-if="selectedRoomSize.panjang && selectedRoomSize.lebar">
                                <div class="mt-4 p-5 bg-gradient-to-br from-blue-50 via-indigo-50 to-purple-50 rounded-2xl border-2 border-blue-300 shadow-lg relative overflow-hidden">
                                    <!-- Decorative Elements -->
                                    <div class="absolute top-0 right-0 w-24 h-24 bg-blue-200/20 rounded-full blur-2xl"></div>
                                    <div class="absolute bottom-0 left-0 w-20 h-20 bg-purple-200/20 rounded-full blur-xl"></div>
                                    
                                    <div class="relative z-10">
                                        <div class="flex items-center gap-2 mb-4">
                                            <div class="p-2 bg-blue-500 rounded-lg shadow-md">
                                                <i data-lucide="ruler" class="w-4 h-4 text-white"></i>
                                            </div>
                                            <h4 class="text-xs font-black text-blue-700 uppercase tracking-wide">Dimensi Ruangan</h4>
                                        </div>
                                        
                                        <div class="space-y-3">
                                            <!-- Panjang -->
                                            <div class="flex items-center justify-between p-2 bg-white/60 backdrop-blur-sm rounded-lg">
                                                <div class="flex items-center gap-2">
                                                    <div class="w-1.5 h-6 bg-gradient-to-b from-blue-400 to-blue-600 rounded-full"></div>
                                                    <span class="text-xs text-gray-600 font-semibold">Panjang</span>
                                                </div>
                                                <span class="text-sm font-black text-blue-700" x-text="selectedRoomSize.panjang + ' m'"></span>
                                            </div>
                                            
                                            <!-- Lebar -->
                                            <div class="flex items-center justify-between p-2 bg-white/60 backdrop-blur-sm rounded-lg">
                                                <div class="flex items-center gap-2">
                                                    <div class="w-1.5 h-6 bg-gradient-to-b from-indigo-400 to-indigo-600 rounded-full"></div>
                                                    <span class="text-xs text-gray-600 font-semibold">Lebar</span>
                                                </div>
                                                <span class="text-sm font-black text-indigo-700" x-text="selectedRoomSize.lebar + ' m'"></span>
                                            </div>
                                            
                                            <!-- Luas Total -->
                                            <div class="mt-3 pt-3 border-t-2 border-blue-200/50">
                                                <div class="p-3 bg-gradient-to-r from-blue-500 to-purple-500 rounded-xl shadow-lg">
                                                    <div class="flex items-center justify-between">
                                                        <span class="text-xs text-white/90 font-bold uppercase tracking-wide">Luas Total</span>
                                                        <div class="text-right">
                                                            <p class="text-[10px] text-white/70 font-medium mb-0.5" x-text="selectedRoomSize.panjang + ' × ' + selectedRoomSize.lebar + ' m'"></p>
                                                            <p class="text-2xl font-black text-white drop-shadow-md" x-text="selectedRoomSize.luas + ' m²'"></p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <div class="lg:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div>
                                <label class="text-[10px] font-bold text-gray-400 uppercase">Gedung <span class="text-red-500">*</span></label>
                                <select name="gedung_id" x-model="form.gedung_id" @change="onGedungChange()" required class="w-full bg-gray-50 px-3 py-2.5 rounded-lg mt-1 outline-none">
                                    <option value="">-- Pilih Gedung --</option>
                                    <?php foreach($daftar_gedung as $gedung): ?>
                                    <option value="<?= $gedung['id'] ?>">
                                        <?= htmlspecialchars($gedung['nama_gedung']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <label class="text-[10px] font-bold text-gray-400 uppercase">Lantai <span class="text-red-500">*</span></label>
                                <select name="lantai" x-model="form.lantai" @change="onLantaiChange()" required class="w-full bg-gray-50 px-3 py-2.5 rounded-lg mt-1 outline-none">
                                    <option value="">-- Pilih Lantai --</option>
                                    <template x-for="L in listLantai()" :key="L">
                                        <option :value="'Lantai ' + L" x-text="'Lantai ' + L"></option>
                                    </template>
                                </select>
                                <p class="text-[10px] text-gray-500 mt-1 italic" x-show="!form.gedung">
                                    Pilih gedung terlebih dahulu
                                </p>
                            </div>

                            <div class="md:col-span-2">
                                <label class="text-[10px] font-bold text-gray-400 uppercase">
                                    Nama Ruangan <span class="text-red-500">*</span>
                                </label>
                                
                                <select x-model="form.nama_ruangan" 
                                        @change="onRoomChange()"
                                        name="nama_ruangan" 
                                        required
                                        :disabled="!form.lantai"
                                        class="w-full bg-gray-50 px-3 py-2.5 rounded-lg mt-1 outline-none disabled:opacity-50 disabled:cursor-not-allowed">
                                    <option value="">-- Pilih Ruangan --</option>
                                    <template x-for="room in filteredRoomTemplates" :key="room.nama">
                                        <option :value="room.nama" x-text="room.nama + (room.panjang ? ' (' + room.panjang + ' × ' + room.lebar + ' m)' : '')"></option>
                                    </template>
                                </select>
                                <p class="text-[10px] text-gray-500 mt-1 italic" x-show="!form.lantai">
                                    Pilih gedung dan lantai terlebih dahulu
                                </p>
                                <p class="text-[10px] text-gray-500 mt-1 italic" x-show="form.lantai && filteredRoomTemplates.length === 0">
                                    Tidak ada template ruangan di lantai ini. Silakan tambahkan template di halaman Gedung.
                                </p>
                                <p class="text-[10px] text-green-600 mt-1 font-medium" x-show="form.lantai && filteredRoomTemplates.length > 0">
                                    ✓ Tersedia <span x-text="filteredRoomTemplates.length"></span> ruangan di <span x-text="form.lantai"></span>
                                </p>
                            </div>
                            
                            <div>
                                <label class="text-[10px] font-bold text-gray-400 uppercase">Kapasitas (Orang)</label>
                                <input type="number" name="kapasitas" x-model="form.kapasitas" class="w-full bg-gray-50 px-3 py-2.5 rounded-lg mt-1 outline-none">
                            </div>

                            <div class="md:col-span-2">
                                <label class="text-[10px] font-bold text-gray-400 uppercase mb-3 block">
                                    Pilih Fasilitas Ruangan
                                </label>
                                
                                <!-- Tabs Tipe -->
                                <div class="flex flex-wrap gap-2 mb-4 p-3 bg-gray-100 rounded-xl overflow-x-auto">
                                    <button type="button" 
                                            @click="activeTipe = 'all'"
                                            :class="activeTipe === 'all' ? 'bg-white text-[#d13b1f] shadow-md' : 'text-gray-500 hover:text-gray-700'"
                                            class="px-4 py-2 rounded-lg text-xs font-bold transition-all whitespace-nowrap flex-shrink-0">
                                        <i data-lucide="grid-3x3" class="w-3 h-3 inline mr-1"></i>
                                        Semua Tipe
                                    </button>
                                    <template x-for="tipe in availableTipes" :key="tipe.name">
                                        <button type="button" 
                                                @click="activeTipe = tipe.name"
                                                :class="activeTipe === tipe.name ? 'bg-white text-[#d13b1f] shadow-md' : 'text-gray-500 hover:text-gray-700'"
                                                class="px-4 py-2 rounded-lg text-xs font-bold transition-all whitespace-nowrap flex-shrink-0">
                                            <span x-text="tipe.name + ' (' + tipe.count + ')'"></span>
                                        </button>
                                    </template>
                                </div>
                                
                                <!-- Grid Fasilitas -->
                                <div class="p-4 bg-gray-50 rounded-xl border border-gray-200 max-h-64 overflow-y-auto">
                                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                                        <?php foreach ($daftar_sarana as $sarana): 
                                            $labelInfo = $sarana['nama'] . " [" . $sarana['kode_label'] . "]";
                                            $tipeDisplay = !empty($sarana['tipe_barang']) ? $sarana['tipe_barang'] : 'Tanpa Tipe';
                                        ?>
                                        <div x-show="(activeTipe === 'all' || activeTipe === '<?= htmlspecialchars($tipeDisplay) ?>') && !isFasilitasTerpakai('<?= htmlspecialchars($labelInfo) ?>')"
                                             x-transition
                                             class="relative">
                                            <label class="flex items-start gap-2 p-3 bg-white border-2 border-gray-200 rounded-lg cursor-pointer hover:border-[#d13b1f] transition-all group">
                                                <input type="checkbox" 
                                                       name="fasilitas[]" 
                                                       value="<?= htmlspecialchars($labelInfo) ?>" 
                                                       x-model="form.fasilitasArray"
                                                       class="mt-0.5 w-4 h-4 text-[#d13b1f] border-gray-300 rounded focus:ring-[#d13b1f] flex-shrink-0">
                                                <div class="flex-1 min-w-0">
                                                    <p class="text-xs font-bold text-gray-700 truncate"><?= htmlspecialchars($sarana['nama']) ?></p>
                                                    <p class="text-[9px] text-gray-400 font-mono mb-1"><?= htmlspecialchars($sarana['kode_label']) ?></p>
                                                    <?php if(!empty($sarana['tipe_barang'])): ?>
                                                    <span class="inline-block px-2 py-0.5 bg-purple-100 text-purple-700 rounded text-[8px] font-bold">
                                                        <?= htmlspecialchars($sarana['tipe_barang']) ?>
                                                    </span>
                                                    <?php else: ?>
                                                    <span class="inline-block px-2 py-0.5 bg-gray-100 text-gray-500 rounded text-[8px] font-bold">
                                                        Tanpa Tipe
                                                    </span>
                                                    <?php endif; ?>
                                                </div>
                                            </label>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <!-- Empty State -->
                                    <div x-show="getFilteredFasilitasCount() === 0" class="text-center py-8">
                                        <i data-lucide="inbox" class="w-12 h-12 text-gray-300 mx-auto mb-2"></i>
                                        <p class="text-sm text-gray-400 italic">Tidak ada fasilitas tersedia dengan tipe ini</p>
                                    </div>
                                </div>
                                
                                <!-- Fasilitas Terpilih -->
                                <div class="mt-4">
                                    <div class="flex items-center justify-between mb-2">
                                        <label class="text-[10px] font-bold text-gray-700 uppercase flex items-center gap-2">
                                            <i data-lucide="check-circle" class="w-3 h-3 text-green-600"></i>
                                            Fasilitas Terpilih
                                        </label>
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs font-bold text-gray-500" x-text="form.fasilitasArray.length + ' item'"></span>
                                            <button type="button"
                                                    x-show="form.fasilitasArray.length > 0"
                                                    @click="form.fasilitasArray = []"
                                                    class="text-[9px] px-2 py-1 bg-red-100 text-red-600 rounded hover:bg-red-200 transition-colors font-bold uppercase">
                                                Hapus Semua
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div class="p-3 bg-green-50 border-2 border-green-200 rounded-xl min-h-[100px] max-h-40 overflow-y-auto">
                                        <template x-if="form.fasilitasArray.length === 0">
                                            <div class="text-center py-6">
                                                <i data-lucide="package-open" class="w-10 h-10 text-green-300 mx-auto mb-2"></i>
                                                <p class="text-xs text-green-600 italic">Belum ada fasilitas dipilih</p>
                                                <p class="text-[10px] text-green-500 mt-1">Pilih dari daftar di atas</p>
                                            </div>
                                        </template>
                                        
                                        <template x-if="form.fasilitasArray.length > 0">
                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                                <template x-for="(fasilitas, index) in form.fasilitasArray" :key="index">
                                                    <div class="group relative flex items-start gap-2 p-2 bg-white border-2 border-green-300 rounded-lg hover:border-red-400 transition-all">
                                                        <div class="w-6 h-6 bg-green-100 rounded flex items-center justify-center flex-shrink-0">
                                                            <span class="text-[10px] font-bold text-green-700" x-text="index + 1"></span>
                                                        </div>
                                                        <div class="flex-1 min-w-0">
                                                            <p class="text-[10px] font-bold text-gray-700 truncate" x-text="getFasilitasName(fasilitas)"></p>
                                                            <p class="text-[8px] text-gray-400 font-mono" x-text="getFasilitasCode(fasilitas)"></p>
                                                            <span class="inline-block mt-1 px-1.5 py-0.5 bg-purple-100 text-purple-600 rounded text-[7px] font-bold" 
                                                                  x-text="getFasilitasTipe(fasilitas)"></span>
                                                        </div>
                                                        <button type="button"
                                                                @click="removeFasilitas(index)"
                                                                class="absolute top-1 right-1 opacity-0 group-hover:opacity-100 w-5 h-5 bg-red-500 text-white rounded-full hover:bg-red-600 transition-all flex items-center justify-center">
                                                            <i data-lucide="x" class="w-3 h-3"></i>
                                                        </button>
                                                    </div>
                                                </template>
                                            </div>
                                        </template>
                                    </div>
                                    
                                    <p class="text-[10px] text-gray-500 mt-2 italic flex items-center gap-1">
                                        <i data-lucide="info" class="w-3 h-3"></i>
                                        Fasilitas dikelompokkan berdasarkan tipe. Hover untuk hapus item.
                                    </p>
                                </div>
                            </div>

                            <div>
                                <label class="text-[10px] font-bold text-gray-400 uppercase">Status Ruangan</label>
                                <select name="status" x-model="form.status" class="w-full bg-gray-50 px-3 py-2.5 rounded-lg mt-1 outline-none">
                                    <option value="Tersedia">Tersedia</option>
                                    <option value="Dipakai">Dipakai</option>
                                    <option value="Diperbaiki">Diperbaiki</option>
                                </select>
                            </div>
                            <div class="md:col-span-2">
                                <label class="text-[10px] font-bold text-gray-400 uppercase">Deskripsi Singkat</label>
                                <textarea name="deskripsi" x-model="form.deskripsi" rows="2" class="w-full bg-gray-50 px-3 py-2.5 rounded-lg mt-1 outline-none"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 mt-8 pt-4 border-t">
                        <button type="button" @click="showForm = false" class="text-gray-400 font-bold text-xs px-6 uppercase tracking-widest">Batal</button>
                        <button type="submit" class="bg-[#d13b1f] text-white px-10 py-2.5 rounded-xl font-bold shadow-lg uppercase tracking-tight">Simpan Data</button>
                    </div>
                </form>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="p-6 border-b border-gray-50 flex justify-between items-center">
                    <div class="flex p-1 bg-gray-100 rounded-xl">
                        <a href="?filter_gedung=all" class="px-4 py-1.5 text-xs font-bold rounded-lg <?= $filter_gedung == 'all' ? 'bg-white text-[#d13b1f] shadow-sm' : 'text-gray-400' ?>">Semua</a>
                        <?php foreach($daftar_gedung as $gedung): ?>
                        <a href="?filter_gedung=<?= $gedung['id'] ?>" 
                        class="px-4 py-1.5 text-xs font-bold rounded-lg whitespace-nowrap <?= $filter_gedung == $gedung['id'] ? 'bg-white text-[#d13b1f] shadow-sm' : 'text-gray-400' ?>">
                            <?= htmlspecialchars($gedung['nama_gedung']) ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <form method="GET" class="relative w-72">
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Cari ruangan atau fasilitas..." class="w-full pl-10 pr-4 py-2.5 bg-gray-50 rounded-xl text-sm outline-none">
                        <i data-lucide="search" class="absolute left-3 top-3 w-4 h-4 text-gray-400"></i>
                    </form>
                </div>
            <div class="w-full overflow-x-auto rounded-xl border border-gray-100">
                <table class="w-full text-left">
                    <thead>
                        <tr class="bg-gray-50/50">
                            <th class="p-4 text-[10px] font-black text-gray-400 uppercase border-b">Detail Ruangan</th>
                            <th class="p-4 text-[10px] font-black text-gray-400 uppercase border-b">Fasilitas</th>
                            <th class="p-4 text-[10px] font-black text-gray-400 uppercase border-b">Lokasi & Ukuran</th>
                            <th class="p-4 text-[10px] font-black text-gray-400 uppercase border-b text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php foreach($rooms as $row): 
                            $fasilitasGrouped = groupFacilities($row['fasilitas']);
                        ?>
                        <tr class="hover:bg-gray-50/80 transition-colors">
                            <td class="p-4">
                                <div class="flex items-center gap-4">
                                    <img src="<?= $row['photo'] ?: 'https://via.placeholder.com/150?text=FT' ?>" class="w-16 h-16 rounded-xl object-cover border shadow-sm">
                                    <div>
                                        <p class="font-bold text-gray-800 text-base leading-tight"><?= htmlspecialchars($row['nama_ruangan']) ?></p>
                                        <p class="text-[10px] text-gray-400 uppercase font-bold tracking-widest mt-0.5"><?= htmlspecialchars($row['nama_gedung']) ?></p>
                                        <div class="mt-2">
                                            <span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase <?= $row['status'] == 'Tersedia' ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600' ?>">
                                                <?= $row['status'] ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="p-4 max-w-xs">
                                <div class="flex flex-wrap gap-1.5 mb-2">
                                    <?php 
                                    if(!empty($fasilitasGrouped)) {
                                        foreach($fasilitasGrouped as $fasilitas => $jumlah): ?>
                                            <button type="button" 
                                                    @click="showDetailFasilitas('<?= htmlspecialchars($row['fasilitas']) ?>', '<?= htmlspecialchars($fasilitas) ?>', '<?= htmlspecialchars($row['nama_ruangan']) ?>')"
                                                    class="px-2 py-0.5 bg-gray-100 text-gray-600 text-[9px] font-bold rounded border border-gray-200 uppercase hover:bg-[#d13b1f] hover:text-white hover:border-[#d13b1f] transition-all cursor-pointer">
                                                <?= htmlspecialchars($fasilitas) ?> (<?= $jumlah ?>)
                                            </button>
                                        <?php endforeach;
                                    } else { 
                                        echo '<span class="text-[9px] text-gray-300 italic">Tidak ada fasilitas</span>'; 
                                    } ?>
                                </div>
                                <p class="text-[11px] text-gray-400 line-clamp-2 italic leading-relaxed">
                                    <?= htmlspecialchars($row['deskripsi']) ?>
                                </p>
                            </td>
                            <td class="p-4">
                                <p class="text-xs font-bold text-gray-700 mb-1"><?= $row['lantai'] ?></p>
                                <div class="flex items-center gap-1 text-gray-400 mb-2">
                                    <i data-lucide="users" size="12"></i>
                                    <span class="text-[10px] font-medium"><?= $row['kapasitas'] ?> Orang</span>
                                </div>
                                <?php if(!empty($row['ukuran_panjang']) && !empty($row['ukuran_lebar'])): ?>
                                <div class="inline-flex items-center gap-1.5 px-2 py-1 bg-blue-50 rounded-lg">
                                    <i data-lucide="maximize-2" size="11" class="text-blue-600"></i>
                                    <span class="text-[10px] font-bold text-blue-700"><?= $row['ukuran_luas'] ?> m²</span>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td class="p-4">
                                <div class="flex justify-center gap-2">
                                    <button @click='openEdit(<?= json_encode($row) ?>)' class="text-blue-500 hover:bg-blue-50 p-1 rounded-lg transition-colors">
                                        <i data-lucide="edit-3" class="w-4 h-4"></i>
                                    </button>
                                    <button onclick="confirmDeleteRuangan(<?= $row['id'] ?>, '<?= htmlspecialchars($row['nama_ruangan']) ?>', '<?= htmlspecialchars($row['nama_gedung']) ?>')" 
                                        class="text-red-400 hover:bg-red-50 p-2 rounded-lg transition-colors">
                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($rooms)): ?>
                        <tr><td colspan="4" class="p-20 text-center text-gray-400 italic">Data tidak ditemukan...</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            </div>
        </div>

        <!-- Modal Detail Fasilitas -->
        <div x-show="showModalFasilitas" 
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             @click="showModalFasilitas = false"
             class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4"
             x-cloak>
            <div @click.stop 
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 scale-90"
                 x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-90"
                 class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[80vh] overflow-hidden flex flex-col">
                
                <!-- Header Modal -->
                <div class="bg-gradient-to-r from-[#d13b1f] to-[#b53118] text-white p-6 flex justify-between items-start">
                    <div>
                        <h3 class="text-xl font-bold mb-1 flex items-center gap-2">
                            <i data-lucide="package" class="w-5 h-5"></i>
                            Detail Fasilitas
                        </h3>
                        <p class="text-white/80 text-sm" x-text="modalFasilitasData.namaRuangan"></p>
                    </div>
                    <button @click="showModalFasilitas = false" class="hover:bg-white/20 p-2 rounded-lg transition-colors">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>

                <!-- Content Modal -->
                <div class="p-6 overflow-y-auto flex-1">
                    <div class="mb-4">
                        <h4 class="text-sm font-black text-gray-400 uppercase mb-2">Kategori Fasilitas</h4>
                        <div class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 rounded-lg">
                            <i data-lucide="tag" class="w-4 h-4 text-[#d13b1f]"></i>
                            <span class="font-bold text-gray-800 capitalize" x-text="modalFasilitasData.namaFasilitas"></span>
                            <span class="px-2 py-0.5 bg-[#d13b1f] text-white rounded-full text-xs font-bold" x-text="modalFasilitasData.items.length + ' Item'"></span>
                        </div>
                    </div>

                    <h4 class="text-sm font-black text-gray-400 uppercase mb-3">Daftar Item</h4>
                    
                    <template x-if="modalFasilitasData.items.length > 0">
                        <div class="space-y-2">
                            <template x-for="(item, index) in modalFasilitasData.items" :key="index">
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg border border-gray-200 hover:border-[#d13b1f] transition-all group">
                                    <div class="flex items-center gap-3 flex-1">
                                        <div class="w-8 h-8 bg-white rounded-lg flex items-center justify-center shadow-sm group-hover:bg-[#d13b1f] transition-colors flex-shrink-0">
                                            <span class="text-xs font-bold text-gray-600 group-hover:text-white" x-text="index + 1"></span>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="font-bold text-gray-800 text-sm capitalize" x-text="item.nama"></p>
                                            <div class="flex items-center gap-2 mt-1 flex-wrap">
                                                <p class="text-[10px] text-gray-400 font-mono">
                                                    Kode: <span class="font-bold text-[#d13b1f]" x-text="item.kode"></span>
                                                </p>
                                                <span class="text-gray-300">•</span>
                                                <p class="text-[10px] text-gray-400">
                                                    <span class="font-semibold" x-text="item.jenis"></span>
                                                </p>
                                                <span class="text-gray-300">•</span>
                                                <p class="text-[10px] text-gray-400">
                                                    Tahun: <span class="font-semibold" x-text="item.tahun_beli"></span>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2 flex-shrink-0">
                                        <span 
                                            class="px-2 py-1 rounded text-[9px] font-bold uppercase whitespace-nowrap"
                                            :class="{
                                                'bg-green-100 text-green-600': item.kondisi === 'Baik',
                                                'bg-yellow-100 text-yellow-600': item.kondisi === 'Rusak Ringan',
                                                'bg-red-100 text-red-600': item.kondisi === 'Rusak Berat',
                                                'bg-gray-100 text-gray-600': !['Baik', 'Rusak Ringan', 'Rusak Berat'].includes(item.kondisi)
                                            }"
                                            x-text="item.kondisi">
                                        </span>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>

                    <template x-if="modalFasilitasData.items.length === 0">
                        <div class="text-center py-12">
                            <i data-lucide="inbox" class="w-16 h-16 text-gray-300 mx-auto mb-3"></i>
                            <p class="text-gray-400 italic">Tidak ada data fasilitas</p>
                        </div>
                    </template>
                </div>

                <!-- Footer Modal -->
                <div class="border-t p-4 bg-gray-50 flex justify-between items-center">
                    <p class="text-xs text-gray-500">
                        <i data-lucide="info" class="inline w-3 h-3"></i>
                        Data fasilitas yang terdaftar di ruangan ini
                    </p>
                    <button @click="showModalFasilitas = false" class="px-6 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold rounded-lg text-sm transition-colors">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Fungsi konfirmasi hapus ruangan dengan SweetAlert2
        function confirmDeleteRuangan(id, namaRuangan, gedung) {
            Swal.fire({
                title: 'Apakah Anda yakin?',
                html: `Anda akan menghapus ruangan:<br><strong>${namaRuangan}</strong><br><span class="text-gray-500">${gedung}</span>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d13b1f',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal',
                reverseButtons: true,
                customClass: {
                    popup: 'rounded-2xl',
                    title: 'text-xl font-bold',
                    htmlContainer: 'text-sm'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    // Redirect ke URL hapus
                    window.location.href = `ruangan.php?delete=${id}`;
                }
            });
        }

        function ruanganApp() {
            return {
                showForm: false,
                showModalFasilitas: false,
                activeTipe: 'all',
                allRoomTemplates: {}, // Menyimpan semua template ruangan per lantai
                filteredRoomTemplates: [], // Ruangan yang difilter berdasarkan lantai
                selectedRoomSize: { // Data ukuran ruangan yang dipilih
                    panjang: '',
                    lebar: '',
                    luas: 0
                },
                modalFasilitasData: {
                    namaFasilitas: '',
                    items: [],
                    namaRuangan: ''
                },
                form: { 
                    id: '', 
                    nama_ruangan: '', 
                    gedung_id: '', 
                    lantai: '', 
                    kapasitas: 20, 
                    fasilitasArray: [], 
                    deskripsi: '', 
                    status: 'Tersedia', 
                    photo: '' 
                },
                fasilitasTerpakaiList: [],
                gedungData: <?= json_encode($daftar_gedung) ?>,
                saranaData: <?= json_encode($daftar_sarana) ?>,
                
                get availableTipes() {
                    // Group dan hitung fasilitas per tipe yang available
                    const tipes = {};
                    this.saranaData.forEach(item => {
                        const labelInfo = item.nama + " [" + item.kode_label + "]";
                        if (!this.isFasilitasTerpakai(labelInfo)) {
                            const tipe = item.tipe_barang || 'Tanpa Tipe';
                            if (!tipes[tipe]) {
                                tipes[tipe] = 0;
                            }
                            tipes[tipe]++;
                        }
                    });
                    
                    return Object.keys(tipes)
                        .sort((a, b) => {
                            // "Tanpa Tipe" selalu di akhir
                            if (a === 'Tanpa Tipe') return 1;
                            if (b === 'Tanpa Tipe') return -1;
                            return a.localeCompare(b);
                        })
                        .map(name => ({
                            name: name,
                            count: tipes[name]
                        }));
                },
                
                getFilteredFasilitasCount() {
                    if (this.activeTipe === 'all') {
                        return this.saranaData.filter(item => {
                            const labelInfo = item.nama + " [" + item.kode_label + "]";
                            return !this.isFasilitasTerpakai(labelInfo);
                        }).length;
                    }
                    
                    return this.saranaData.filter(item => {
                        const labelInfo = item.nama + " [" + item.kode_label + "]";
                        const tipe = item.tipe_barang || 'Tanpa Tipe';
                        return tipe === this.activeTipe && !this.isFasilitasTerpakai(labelInfo);
                    }).length;
                },
                
                getFasilitasName(fasilitasString) {
                    const match = fasilitasString.match(/^(.+?)\s*\[/);
                    return match ? match[1].trim() : fasilitasString;
                },
                
                getFasilitasCode(fasilitasString) {
                    const match = fasilitasString.match(/\[(.+?)\]$/);
                    return match ? match[1].trim() : '';
                },
                
                getFasilitasTipe(fasilitasString) {
                    const kode = this.getFasilitasCode(fasilitasString);
                    const item = this.saranaData.find(s => s.kode_label === kode);
                    return item && item.tipe_barang ? item.tipe_barang : 'Tanpa Tipe';
                },
                
                removeFasilitas(index) {
                    this.form.fasilitasArray.splice(index, 1);
                    // Re-init icons after removal
                    setTimeout(() => lucide.createIcons(), 50);
                },
                
                listLantai() {
                    const selectedGedung = this.gedungData.find(g => g.id == this.form.gedung_id);
                    
                    if (selectedGedung) {
                        const lantaiArray = [];
                        for (let i = 1; i <= selectedGedung.jumlah_lantai; i++) {
                            lantaiArray.push(i);
                        }
                        return lantaiArray;
                    }
                    return [];
                },

                async onGedungChange() {
                    // Reset lantai dan ruangan saat gedung berubah
                    this.form.lantai = '';
                    this.form.nama_ruangan = '';
                    this.filteredRoomTemplates = [];
                    this.selectedRoomSize = { panjang: '', lebar: '', luas: 0 };
                    
                    // Load template ruangan untuk gedung yang dipilih
                    await this.loadRoomTemplates();
                },

                onLantaiChange() {
                    // Reset nama ruangan saat lantai berubah
                    this.form.nama_ruangan = '';
                    this.selectedRoomSize = { panjang: '', lebar: '', luas: 0 };
                    
                    // Filter ruangan berdasarkan lantai yang dipilih
                    this.filterRoomsByLantai();
                },

                onRoomChange() {
                    // Cari data ruangan yang dipilih
                    const selectedRoom = this.filteredRoomTemplates.find(r => r.nama == this.form.nama_ruangan);
                    
                    if (selectedRoom && selectedRoom.panjang && selectedRoom.lebar) {
                        this.selectedRoomSize = {
                            panjang: parseFloat(selectedRoom.panjang),
                            lebar: parseFloat(selectedRoom.lebar),
                            luas: parseFloat(selectedRoom.luas) || (parseFloat(selectedRoom.panjang) * parseFloat(selectedRoom.lebar))
                        };
                    } else {
                        this.selectedRoomSize = { panjang: '', lebar: '', luas: 0 };
                    }
                    
                    // Re-init icons
                    setTimeout(() => lucide.createIcons(), 100);
                },

                async loadRoomTemplates() {
                    if (!this.form.gedung_id) {
                        this.allRoomTemplates = {};
                        this.filteredRoomTemplates = [];
                        return;
                    }
                    
                    try {
                        // Cari gedung yang dipilih
                        const selectedGedung = this.gedungData.find(g => g.id == this.form.gedung_id);
                        
                        if (selectedGedung && selectedGedung.daftar_ruangan) {
                            // Parse JSON daftar_ruangan
                            this.allRoomTemplates = JSON.parse(selectedGedung.daftar_ruangan);
                        } else {
                            this.allRoomTemplates = {};
                        }
                        
                        // Filter jika sudah ada lantai yang dipilih
                        if (this.form.lantai) {
                            this.filterRoomsByLantai();
                        }
                    } catch (error) {
                        console.error('Error loading room templates:', error);
                        this.allRoomTemplates = {};
                        this.filteredRoomTemplates = [];
                    }
                },

                filterRoomsByLantai() {
                    if (!this.form.lantai || !this.allRoomTemplates) {
                        this.filteredRoomTemplates = [];
                        return;
                    }
                    
                    // Extract nomor lantai dari "Lantai X"
                    const lantaiNumber = this.form.lantai.replace('Lantai ', '');
                    
                    // Ambil daftar ruangan untuk lantai tersebut
                    const roomsInLantai = this.allRoomTemplates[lantaiNumber];
                    
                    if (roomsInLantai && Array.isArray(roomsInLantai)) {
                        // Convert ke format object dengan support format lama dan baru
                        this.filteredRoomTemplates = roomsInLantai.map(room => {
                            if (typeof room === 'string') {
                                // Format lama (string only)
                                return { nama: room, panjang: null, lebar: null, luas: null };
                            } else if (room && typeof room === 'object') {
                                // Format baru (object)
                                return {
                                    nama: room.nama || '',
                                    panjang: room.panjang || null,
                                    lebar: room.lebar || null,
                                    luas: room.luas || (room.panjang && room.lebar ? room.panjang * room.lebar : null)
                                };
                            }
                            return null;
                        }).filter(room => room && room.nama && room.nama.trim() !== '');
                    } else {
                        this.filteredRoomTemplates = [];
                    }
                },

                async showDetailFasilitas(fasilitasString, namaFasilitas, namaRuangan) {
                    if (!fasilitasString) return;
                    
                    // Parse semua fasilitas
                    const allItems = fasilitasString.split(',').map(item => item.trim());
                    
                    // Filter hanya fasilitas yang sesuai
                    const filteredItems = allItems.filter(item => {
                        // Extract base name dari item (misal: "Kursi [FT-KRS-001]" -> "kursi")
                        const baseName = item.match(/^(.+?)\s*\[/);
                        if (baseName) {
                            return baseName[1].toLowerCase().trim() === namaFasilitas.toLowerCase().trim();
                        }
                        return false;
                    });
                    
                    // Parse setiap item untuk mendapatkan nama dan kode
                    const parsedItems = filteredItems.map(item => {
                        const match = item.match(/^(.+?)\s*\[(.+?)\]$/);
                        if (match) {
                            return {
                                nama: match[1].trim(),
                                kode: match[2].trim(),
                                kondisi: 'Loading...',
                                tahun_beli: '-',
                                jenis: '-',
                                status: '-'
                            };
                        }
                        return null;
                    }).filter(item => item !== null);
                    
                    // Set data awal (loading state)
                    this.modalFasilitasData = {
                        namaFasilitas: namaFasilitas,
                        items: parsedItems,
                        namaRuangan: namaRuangan
                    };
                    
                    this.showModalFasilitas = true;
                    
                    // Re-init lucide icons untuk modal
                    setTimeout(() => lucide.createIcons(), 100);
                    
                    // Fetch detail kondisi dari API
                    try {
                        const kodeLabels = parsedItems.map(item => item.kode).join(',');
                        const response = await fetch(`get_detail_fasilitas.php?kode_labels=${encodeURIComponent(kodeLabels)}`);
                        const detailData = await response.json();
                        
                        // Update items dengan data kondisi
                        this.modalFasilitasData.items = parsedItems.map(item => {
                            const detail = detailData.find(d => d.kode_label === item.kode);
                            if (detail) {
                                return {
                                    ...item,
                                    kondisi: detail.kondisi || 'Tidak diketahui',
                                    tahun_beli: detail.tahun_beli || '-',
                                    jenis: detail.jenis || '-',
                                    status: detail.status || '-'
                                };
                            }
                            return {
                                ...item,
                                kondisi: 'Data tidak ditemukan',
                                tahun_beli: '-',
                                jenis: '-',
                                status: '-'
                            };
                        });
                    } catch (error) {
                        console.error('Error fetching fasilitas detail:', error);
                        // Jika error, set kondisi default
                        this.modalFasilitasData.items = parsedItems.map(item => ({
                            ...item,
                            kondisi: 'Error memuat data',
                            tahun_beli: '-',
                            jenis: '-',
                            status: '-'
                        }));
                    }
                },

                async loadFasilitasTerpakai(excludeRoomId = null) {
                    try {
                        const params = new URLSearchParams();
                        if (excludeRoomId) {
                            params.append('exclude_room', excludeRoomId);
                        }
                        
                        const response = await fetch('get_fasilitas_terpakai.php?' + params.toString());
                        const data = await response.json();
                        this.fasilitasTerpakaiList = data;
                    } catch (error) {
                        console.error('Error loading fasilitas terpakai:', error);
                        this.fasilitasTerpakaiList = [];
                    }
                },

                isFasilitasTerpakai(labelInfo) {
                    return this.fasilitasTerpakaiList.includes(labelInfo);
                },

                async openTambah() {
                    this.form = { 
                        id: '', 
                        nama_ruangan: '', 
                        gedung_id: '', 
                        lantai: '', 
                        kapasitas: 20, 
                        fasilitasArray: [], 
                        deskripsi: '', 
                        status: 'Tersedia', 
                        photo: '' 
                    };
                    
                    this.allRoomTemplates = {};
                    this.filteredRoomTemplates = [];
                    this.selectedRoomSize = { panjang: '', lebar: '', luas: 0 };
                    this.activeTipe = 'all';
                    
                    await this.loadFasilitasTerpakai();
                    
                    this.showForm = true;
                    window.scrollTo({top: 0, behavior: 'smooth'});
                    setTimeout(() => lucide.createIcons(), 100);
                },

                async openEdit(data) {
                    let currentFasilitas = data.fasilitas ? data.fasilitas.split(', ').map(i => i.trim()) : [];
                    this.form = {...data, fasilitasArray: currentFasilitas};
                    this.activeTipe = 'all';
                    
                    // Set ukuran ruangan dari data existing
                    if (data.ukuran_panjang && data.ukuran_lebar) {
                        this.selectedRoomSize = {
                            panjang: parseFloat(data.ukuran_panjang),
                            lebar: parseFloat(data.ukuran_lebar),
                            luas: parseFloat(data.ukuran_luas) || (parseFloat(data.ukuran_panjang) * parseFloat(data.ukuran_lebar))
                        };
                    } else {
                        this.selectedRoomSize = { panjang: '', lebar: '', luas: 0 };
                    }
                    
                    await this.loadFasilitasTerpakai(data.id);
                    await this.loadRoomTemplates(); // Load templates untuk gedung yang dipilih
                    
                    // Filter ruangan jika lantai sudah ada
                    if (this.form.lantai) {
                        this.filterRoomsByLantai();
                    }
                    
                    this.showForm = true;
                    window.scrollTo({top: 0, behavior: 'smooth'});
                    setTimeout(() => lucide.createIcons(), 100);
                },

                handleFile(e) {
                    const file = e.target.files[0];
                    if (!file) return;
                    const reader = new FileReader();
                    reader.onload = (f) => { this.form.photo = f.target.result; };
                    reader.readAsDataURL(file);
                }
            }
        }
        document.addEventListener('DOMContentLoaded', () => lucide.createIcons());
    </script>
</body>
</html>