<?php
session_start();
require "../config/database.php"; 
require "../auth/auth_helper.php";

cek_kemanan_login($pdo);

$msg = "";

// 1. Hapus Data
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM sarana WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: sarana.php?status=deleted");
        exit;
    } catch (PDOException $e) { 
        $msg = "Gagal menghapus: " . $e->getMessage(); 
    }
}

// 2. Proses Simpan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id          = $_POST['id'] ?? null;
    $nama        = $_POST['nama'] ?? '';
    $tipe_barang = $_POST['tipe_barang'] ?? '';
    $jenis       = !empty($_POST['jenis']) ? $_POST['jenis'] : 'Furniture'; 
    $template_id = !empty($_POST['template_id']) ? (int)$_POST['template_id'] : null;
    $kondisi     = $_POST['kondisi'] ?? 'Baik';
    
    $tanggal_beli = $_POST['tanggal_beli'] ?? '01';
    $bulan_beli   = $_POST['bulan_beli'] ?? '01';
    $tahun_beli   = $_POST['tahun_beli'] ?? date('Y');
    $full_tanggal_beli = "$tahun_beli-$bulan_beli-$tanggal_beli";
    
    $tanggal_input = strtotime($full_tanggal_beli);
    $tanggal_sekarang = strtotime(date('Y-m-d'));
    
    if ($tanggal_input > $tanggal_sekarang) {
        $msg = "Error: Tanggal pembelian tidak boleh lebih dari tanggal hari ini!";
    } else {
        $gedung      = $_POST['gedung'] ?: '-';
        $lantai      = $_POST['lantai'] ?: '-';
        $ruangan     = $_POST['ruangan'] ?: '-';
        $lokasi      = $gedung . " | " . $lantai . " | " . $ruangan;
        
        $photo       = $_POST['photo_base64'] ?? '';
        $deskripsi   = $_POST['deskripsi'] ?? '';
        $status      = $_POST['status'] ?? 'Tersedia';

        try {
            if (empty($id)) {
                $jumlah = (int)($_POST['jumlah'] ?? 1);
                $prefix = $_POST['prefix_label'] ?? 'UNIT-'; 

                $pdo->beginTransaction();
                
                // 🔥 CEK NOMOR TERAKHIR DARI PREFIX INI
                $stmtCheck = $pdo->prepare("SELECT kode_label FROM sarana WHERE kode_label LIKE ? ORDER BY kode_label DESC LIMIT 1");
                $stmtCheck->execute([$prefix . '%']);
                $lastLabel = $stmtCheck->fetchColumn();
                
                // Ambil nomor terakhir
                $startNumber = 1;
                if ($lastLabel) {
                    // Extract nomor dari label terakhir (contoh: KK-KI-100 -> 100)
                    $lastNumber = (int)preg_replace('/[^0-9]/', '', substr($lastLabel, strlen($prefix)));
                    $startNumber = $lastNumber + 1;
                }
                
                $sql = "INSERT INTO sarana (nama, tipe_barang, template_id, kode_label, tahun_beli, jenis, kondisi, lokasi, deskripsi, jumlah, status, photo) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);

                $stmtRoom = $pdo->prepare("SELECT id FROM ruangan WHERE nama_ruangan = ? LIMIT 1");
                $stmtRoom->execute([$ruangan]);
                $roomData = $stmtRoom->fetch();

                for ($i = 0; $i < $jumlah; $i++) {
                    $nomorUrut = $startNumber + $i;
                    $labelUnik = $prefix . str_pad($nomorUrut, 3, '0', STR_PAD_LEFT); // Format 001, 002, dst
                    
                    $stmt->execute([
                        $nama, 
                        $tipe_barang, 
                        $template_id,
                        $labelUnik, 
                        $full_tanggal_beli, 
                        $jenis, 
                        $kondisi, 
                        $lokasi, 
                        $deskripsi, 
                        1, 
                        $status, 
                        $photo
                    ]);
                    
                    if ($roomData) {
                        $roomId = $roomData['id'];
                        $stmtFas = $pdo->prepare("INSERT IGNORE INTO fasilitas_ruangan (ruangan_id, nama_barang) VALUES (?, ?)");
                        $stmtFas->execute([$roomId, $nama]);

                        $stmtGetFas = $pdo->prepare("SELECT id FROM fasilitas_ruangan WHERE ruangan_id = ? AND nama_barang = ?");
                        $stmtGetFas->execute([$roomId, $nama]);
                        $fasId = $stmtGetFas->fetchColumn();

                        $stmtUnit = $pdo->prepare("INSERT INTO item_unit (fasilitas_id, kode_label, kondisi) VALUES (?, ?, ?)");
                        $stmtUnit->execute([$fasId, $labelUnik, $kondisi]);
                    }
                }
                $pdo->commit();
                $msg = "Berhasil registrasi $jumlah unit $nama ($tipe_barang) dengan label $prefix" . str_pad($startNumber, 3, '0', STR_PAD_LEFT) . " s/d $prefix" . str_pad($startNumber + $jumlah - 1, 3, '0', STR_PAD_LEFT) . " dan tersinkron ke sistem ruangan!";
            } else {
                $sql = "UPDATE sarana SET nama=?, tipe_barang=?, template_id=?, tahun_beli=?, jenis=?, kondisi=?, lokasi=?, deskripsi=?, status=?, photo=? WHERE id=?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $nama, 
                    $tipe_barang, 
                    $template_id, 
                    $full_tanggal_beli, 
                    $jenis, 
                    $kondisi, 
                    $lokasi, 
                    $deskripsi, 
                    $status, 
                    $photo, 
                    $id
                ]);
                $oldLabel = $_POST['kode_label'] ?? '';
                $stmtSync = $pdo->prepare("UPDATE item_unit SET kondisi = ? WHERE kode_label = ?");
                $stmtSync->execute([$kondisi, $oldLabel]);

                $msg = "Update unit " . $oldLabel . " berhasil!";
            }
        } catch (PDOException $e) { 
            if($pdo->inTransaction()) $pdo->rollBack();
            $msg = "Error: " . $e->getMessage(); 
        }
    }
}

// 3. Ambil Data dengan GROUP BY TIPE BARANG
$search = $_GET['search'] ?? '';
$filter_tipe = $_GET['filter_tipe'] ?? 'all';
$filter_status = $_GET['filter_status'] ?? 'all'; // NEW: Filter status peminjaman
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// Build WHERE clause
$where_conditions = "(nama LIKE :s1 OR tipe_barang LIKE :s6 OR kode_label LIKE :s2 OR lokasi LIKE :s3 OR DATE_FORMAT(tahun_beli, '%d %M %Y') LIKE :s4 OR DATE_FORMAT(tahun_beli, '%Y') LIKE :s5)";
$bind_params = [
    ':s1' => "%$search%", 
    ':s2' => "%$search%", 
    ':s3' => "%$search%",
    ':s4' => "%$search%",
    ':s5' => "%$search%",
    ':s6' => "%$search%"
];

if ($filter_tipe !== 'all') {
    $where_conditions .= " AND tipe_barang = :filter_tipe";
    $bind_params[':filter_tipe'] = $filter_tipe;
}

// 🔥 Ambil semua items dengan STATUS PEMINJAMAN
$sql_all = "
    SELECT 
        s.*,
        CASE 
            WHEN s.status = 'Dipinjam' THEN 'Dipinjam'
            WHEN EXISTS (
                SELECT 1 FROM peminjaman p 
                WHERE p.item_id = s.id 
                AND p.kategori = 'sarana' 
                AND p.status IN ('approved', 'returning')
            ) THEN 'Dipinjam'
            ELSE 'Tersedia'
        END as status_pinjam
    FROM sarana s 
    WHERE " . $where_conditions . " 
    ORDER BY tipe_barang, nama
";

$stmt_all = $pdo->prepare($sql_all);
$stmt_all->execute($bind_params);
$all_items = $stmt_all->fetchAll(PDO::FETCH_ASSOC);

// Filter by status peminjaman (after query)
if ($filter_status !== 'all') {
    $all_items = array_filter($all_items, function($item) use ($filter_status) {
        return $item['status_pinjam'] === $filter_status;
    });
    $all_items = array_values($all_items); // Re-index
}

// 🎯 GROUP BY TIPE BARANG
$items_by_tipe = [];
foreach ($all_items as $item) {
    $tipe = !empty($item['tipe_barang']) ? $item['tipe_barang'] : 'Tanpa Tipe';
    
    if (!isset($items_by_tipe[$tipe])) {
        $items_by_tipe[$tipe] = [
            'info' => $item, // Sample item untuk info
            'items' => [],
            'total_unit' => 0,
            'tersedia' => 0, // NEW: Hitung unit tersedia
            'dipinjam' => 0, // NEW: Hitung unit dipinjam
            'lokasi_grouped' => [] // Group by lokasi dalam tipe
        ];
    }
    
    $items_by_tipe[$tipe]['items'][] = $item;
    $items_by_tipe[$tipe]['total_unit']++;
    
    // Hitung status peminjaman
    if ($item['status_pinjam'] === 'Tersedia') {
        $items_by_tipe[$tipe]['tersedia']++;
    } else {
        $items_by_tipe[$tipe]['dipinjam']++;
    }
    
    // Group by lokasi dalam tipe
    $lokasi = $item['lokasi'];
    if (!isset($items_by_tipe[$tipe]['lokasi_grouped'][$lokasi])) {
        $items_by_tipe[$tipe]['lokasi_grouped'][$lokasi] = 0;
    }
    $items_by_tipe[$tipe]['lokasi_grouped'][$lokasi]++;
}

// Pagination untuk tipe
$tipes_array = array_keys($items_by_tipe);
$total_tipes = count($tipes_array);
$total_pages = ceil($total_tipes / $limit);
$paginated_tipes = array_slice($tipes_array, $offset, $limit, true);

// Build final array
$items_by_tipe_paginated = [];
foreach ($paginated_tipes as $tipe) {
    $items_by_tipe_paginated[$tipe] = $items_by_tipe[$tipe];
}

// Total items untuk display
$total_items = $total_tipes;

// Ambil daftar tipe barang yang unik untuk filter
$stmtTipes = $pdo->query("SELECT DISTINCT tipe_barang FROM sarana WHERE tipe_barang IS NOT NULL AND tipe_barang != '' ORDER BY tipe_barang ASC");
$daftar_tipe = $stmtTipes->fetchAll(PDO::FETCH_COLUMN);

// Ambil template sarana dari database
$stmtTemplate = $pdo->query("SELECT * FROM template_sarana ORDER BY jenis_barang ASC, nama_kategori ASC");
$template_sarana = $stmtTemplate->fetchAll(PDO::FETCH_ASSOC);

// Ambil data gedung untuk dropdown ruangan
$stmtGedung = $pdo->query("SELECT * FROM gedung ORDER BY nama_gedung ASC");
$daftar_gedung = $stmtGedung->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../assets/kampusums.png" />
    <title>Inventaris Sarana</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }
        html { scroll-behavior: smooth; }
    </style>
</head>
<body class="bg-gray-50 flex flex-col lg:flex-row" x-data="saranaApp()">

    <?php include "../components/AdminNavbar.php"; ?>

    <main class="flex-1 lg:ml-60 p-4 md:p-5 mt-16 lg:mt-0 min-h-screen w-full overflow-x-hidden">
        <div class="max-w-7xl mx-auto">
            
            <div class="flex flex-col md:flex-row md:justify-between md:items-end gap-4 mb-8">
                <div>
                    <h1 class="text-2xl md:text-3xl font-bold text-gray-800">Manajemen Aset Unit</h1>
                    <p class="text-sm md:text-base text-gray-500 mt-1">Daftar unit berdasarkan klasifikasi tipe barang.</p>
                </div>
                <div class="flex flex-col sm:flex-row gap-2 sm:gap-3">
                    <a href="kelola_sarana.php" class="flex items-center justify-center gap-2 bg-purple-600 hover:bg-purple-700 text-white px-5 py-2.5 rounded-xl font-medium shadow-md transition-all">
                        <i data-lucide="settings" class="w-4 h-4"></i> Kelola Daftar Sarana
                    </a>
                    <button @click="openTambah()" class="flex items-center justify-center gap-2 bg-[#d13b1f] hover:bg-[#b53118] text-white px-5 py-2.5 rounded-xl font-medium shadow-md transition-all active:scale-95">
                        <i data-lucide="plus" class="w-4 h-4"></i> Registrasi Unit Baru
                    </button>
                </div>
            </div>

            <?php if($msg): ?>
            <div class="<?= strpos($msg, 'Error') !== false ? 'bg-red-100 border-red-200 text-red-700' : 'bg-green-100 border-green-200 text-green-700' ?> border px-4 py-3 rounded-xl mb-6 flex items-center justify-between shadow-sm">
                <div class="flex items-center gap-2 text-sm">
                    <i data-lucide="<?= strpos($msg, 'Error') !== false ? 'alert-circle' : 'check-circle' ?>" size="18"></i> <?= $msg ?>
                </div>
                <button type="button" @click="window.location.href='sarana.php'" class="opacity-50 hover:opacity-100 transition-opacity"><i data-lucide="x" size="16"></i></button>
            </div>
            <?php endif; ?>

            <div x-show="showForm" x-transition x-cloak class="relative bg-black/5 rounded-2xl shadow-xl border border-black/10 p-6 mb-8">
                <!-- Close Button -->
                <button type="button" 
                        @click="showForm = false"
                        class="absolute top-4 right-4 w-10 h-10 flex items-center justify-center bg-white hover:bg-red-50 text-gray-400 hover:text-red-500 rounded-xl border border-gray-200 hover:border-red-300 transition-all shadow-sm hover:shadow-md z-10 group">
                    <i data-lucide="x" class="w-5 h-5 group-hover:scale-110 transition-transform"></i>
                </button>

                <form action="sarana.php" method="POST" @submit="return validateDate($event)">
                    <input type="hidden" name="id" x-model="form.id">
                    <input type="hidden" name="kode_label" x-model="form.kode_label">
                    <input type="hidden" name="photo_base64" x-model="form.photo">
                    <input type="hidden" name="template_id" x-model="form.template_id">
                    
                    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 md:gap-8">
                        <div class="lg:col-span-1">
                            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-3">Foto Unit</label>
                            <div @click="$refs.fileInput.click()" class="relative h-48 md:h-56 w-full border-2 border-dashed border-gray-200 rounded-2xl bg-gray-50 flex flex-col items-center justify-center cursor-pointer overflow-hidden group hover:border-[#d13b1f] transition-all">
                                <template x-if="form.photo">
                                    <img :src="form.photo" class="w-full h-full object-cover">
                                </template>
                                <template x-if="!form.photo">
                                    <div class="text-center text-gray-400">
                                        <i data-lucide="camera" class="mx-auto mb-2"></i>
                                        <p class="text-[10px]">Pilih Foto</p>
                                    </div>
                                </template>
                                <input type="file" x-ref="fileInput" class="hidden" @change="handleFile" accept="image/*">
                            </div>
                        </div>

                        <div class="lg:col-span-3 grid grid-cols-1 md:grid-cols-3 gap-4 md:gap-5">
                            <!-- Nama Barang -->
                            <div class="md:col-span-2">
                                <label class="text-[10px] font-bold text-gray-400 uppercase">Nama Barang <span class="text-red-500">*</span></label>
                                <input type="text" 
                                       name="nama" 
                                       x-model="form.nama" 
                                       @input="onNamaChange()"
                                       required 
                                       class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 focus:border-[#d13b1f] focus:ring-2 focus:ring-[#d13b1f]/30 outline-none transition-all font-semibold text-lg md:text-xl">
                            </div>

                            <!-- Tipe Barang (Filtered by Nama) -->
                            <div class="md:col-span-1">
                                <label class="text-[10px] font-bold text-gray-400 uppercase">Tipe Barang</label>
                                <div class="relative mt-1">
                                    <select name="tipe_barang" 
                                            x-model="form.tipe_barang" 
                                            @change="onTipeChange()"
                                            :disabled="filteredTipeOptions.length === 0"
                                            class="w-full bg-gray-50 px-3 py-2.5 rounded-lg outline-none border border-transparent focus:border-[#d13b1f] appearance-none cursor-pointer text-sm disabled:opacity-50 disabled:cursor-not-allowed">
                                        <option value="">Pilih Tipe</option>
                                        
                                        <template x-for="group in filteredTipeOptions" :key="group.kategori">
                                            <optgroup :label="group.kategori">
                                                <template x-for="tipe in group.tipe_list" :key="tipe">
                                                    <option :value="tipe" :data-template-id="group.template_id" x-text="tipe"></option>
                                                </template>
                                            </optgroup>
                                        </template>
                                    </select>
                                    <i data-lucide="chevron-down" class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none"></i>
                                </div>
                                
                                <p class="text-[11px] mt-1 flex items-center gap-1" 
                                   :class="filteredTipeOptions.length > 0 ? 'text-green-600' : 'text-gray-500'">
                                    <i data-lucide="info" class="w-3 h-3"></i>
                                    <span x-show="filteredTipeOptions.length === 0">Isi nama barang dulu</span>
                                    <span x-show="filteredTipeOptions.length > 0" x-text="`${filteredTipeOptions.reduce((sum, g) => sum + g.tipe_list.length, 0)} tipe tersedia`"></span>
                                </p>
                            </div>

                            <div class="col-span-1">
                                <label class="text-[10px] font-bold text-gray-400 uppercase">Jenis Barang</label>
                                <div class="relative mt-1">
                                    <select name="jenis" x-model="form.jenis" class="w-full bg-gray-50 px-3 py-2.5 rounded-lg outline-none border border-transparent focus:border-[#d13b1f] appearance-none cursor-pointer text-sm">
                                        <option value="Furniture">Furniture</option>
                                        <option value="Elektronik">Elektronik</option>
                                        <option value="Peralatan Umum">Peralatan Umum</option>
                                    </select>
                                    <i data-lucide="chevron-down" class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none"></i>
                                </div>
                            </div>

                            <div class="md:col-span-2">
                                <label class="text-[10px] font-bold text-gray-400 uppercase">Tanggal Pembelian</label>
                                <div class="grid grid-cols-3 gap-2 mt-1">
                                    <select name="tanggal_beli" x-model="form.tanggal_beli" @change="checkDateValidity" required class="bg-gray-50 px-2 md:px-3 py-2.5 rounded-lg outline-none border border-transparent focus:border-[#d13b1f] text-sm">
                                        <template x-for="d in 31" :key="d">
                                            <option :value="String(d).padStart(2, '0')" x-text="d"></option>
                                        </template>
                                    </select>
                                    <select name="bulan_beli" x-model="form.bulan_beli" @change="checkDateValidity" required class="bg-gray-50 px-2 md:px-3 py-2.5 rounded-lg outline-none border border-transparent focus:border-[#d13b1f] text-sm">
                                        <option value="01">Jan</option><option value="02">Feb</option><option value="03">Mar</option>
                                        <option value="04">Apr</option><option value="05">Mei</option><option value="06">Jun</option>
                                        <option value="07">Jul</option><option value="08">Ags</option><option value="09">Sep</option>
                                        <option value="10">Okt</option><option value="11">Nov</option><option value="12">Des</option>
                                    </select>
                                    <select name="tahun_beli" x-model="form.tahun_beli" @change="checkDateValidity" required class="bg-gray-50 px-2 md:px-3 py-2.5 rounded-lg outline-none border border-transparent focus:border-[#d13b1f] text-sm">
                                        <template x-for="y in 30" :key="y">
                                            <option :value="new Date().getFullYear() - y + 1" x-text="new Date().getFullYear() - y + 1"></option>
                                        </template>
                                    </select>
                                </div>
                                <div x-show="dateError" x-transition class="mt-2 text-[10px] text-red-600 font-medium bg-red-50 px-3 py-2 rounded-lg border border-red-200 flex items-center gap-2">
                                    <i data-lucide="alert-circle" class="w-3 h-3"></i> <span x-text="dateErrorMessage"></span>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 md:col-span-3 gap-4">
                                <div>
                                    <label class="text-[10px] font-bold text-gray-400 uppercase">Gedung <span class="text-red-500">*</span></label>
                                    <select name="gedung" x-model="form.gedung" @change="onGedungChange()" required class="w-full bg-gray-50 px-3 py-2.5 rounded-lg outline-none mt-1 border border-transparent focus:border-[#d13b1f] text-sm">
                                        <option value="">-- Pilih Gedung --</option>
                                        <?php foreach($daftar_gedung as $gedung): ?>
                                        <option value="<?= htmlspecialchars($gedung['nama_gedung']) ?>">
                                            <?= htmlspecialchars($gedung['nama_gedung']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-[10px] font-bold text-gray-400 uppercase">Lantai <span class="text-red-500">*</span></label>
                                    <select name="lantai" x-model="form.lantai" @change="onLantaiChange()" required :disabled="!form.gedung" class="w-full bg-gray-50 px-3 py-2.5 rounded-lg outline-none mt-1 border border-transparent focus:border-[#d13b1f] text-sm disabled:opacity-50 disabled:cursor-not-allowed">
                                        <option value="">-- Pilih Lantai --</option>
                                        <template x-for="L in listLantai()" :key="L">
                                            <option :value="'Lantai ' + L" x-text="'Lantai ' + L"></option>
                                        </template>
                                    </select>
                                    <p class="text-[10px] text-gray-500 mt-1 italic" x-show="!form.gedung">
                                        Pilih gedung terlebih dahulu
                                    </p>
                                </div>
                                <div>
                                    <label class="text-[10px] font-bold text-gray-400 uppercase">Ruangan <span class="text-red-500">*</span></label>
                                    <select x-model="form.ruangan" 
                                            @change="updatePrefix()"
                                            name="ruangan" 
                                            required
                                            :disabled="!form.lantai"
                                            class="w-full bg-gray-50 px-3 py-2.5 rounded-lg outline-none mt-1 border border-transparent focus:border-[#d13b1f] text-sm disabled:opacity-50 disabled:cursor-not-allowed">
                                        <option value="">-- Pilih Ruangan --</option>
                                        <template x-for="room in filteredRoomTemplates" :key="room.nama">
                                            <option :value="room.nama" x-text="room.nama"></option>
                                        </template>
                                    </select>
                                    <p class="text-[10px] text-gray-500 mt-1 italic" x-show="!form.lantai">
                                        Pilih gedung dan lantai terlebih dahulu
                                    </p>
                                    <p class="text-[10px] text-gray-500 mt-1 italic" x-show="form.lantai && filteredRoomTemplates.length === 0">
                                        Tidak ada template ruangan di lantai ini
                                    </p>
                                    <p class="text-[10px] text-green-600 mt-1 font-medium" x-show="form.lantai && filteredRoomTemplates.length > 0">
                                        ✓ Tersedia <span x-text="filteredRoomTemplates.length"></span> ruangan
                                    </p>
                                </div>
                            </div>

                            <template x-if="!editMode">
                                <div class="md:col-span-3 grid grid-cols-1 md:grid-cols-2 gap-4 p-4 bg-orange-50 rounded-2xl border border-orange-100">
                                    <div>
                                        <label class="text-[10px] font-bold text-orange-400 uppercase">Jumlah Unit</label>
                                        <input type="number" name="jumlah" x-model="form.jumlah" min="1" class="w-full bg-white px-3 py-2 rounded-lg mt-1 border border-orange-200 text-sm">
                                    </div>
                                    <div>
                                        <label class="text-[10px] font-bold text-orange-400 uppercase">Prefix Label (Auto)</label>
                                        <input type="text" name="prefix_label" x-model="form.prefix_label" readonly class="w-full bg-gray-100 px-3 py-2 rounded-lg mt-1 border border-orange-200 text-sm cursor-not-allowed">
                                        <p class="text-[9px] text-orange-600 mt-1 flex items-center gap-1">
                                            <i data-lucide="info" class="w-3 h-3"></i> 
                                            <span>Otomatis dari nama + lokasi. Nomor akan lanjut dari database.</span>
                                        </p>
                                    </div>
                                    
                                    <!-- Info Preview Label -->
                                    <div class="md:col-span-2 bg-blue-50 rounded-lg p-3 border border-blue-200" x-show="form.prefix_label">
                                        <div class="flex items-start gap-2">
                                            <i data-lucide="tag" class="w-4 h-4 text-blue-600 mt-0.5"></i>
                                            <div class="flex-1">
                                                <p class="text-[10px] font-bold text-blue-600 uppercase mb-1">Preview Label yang akan dibuat:</p>
                                                <p class="text-xs text-blue-800">
                                                    <span class="font-mono font-bold" x-text="form.prefix_label + 'XXX'"></span>
                                                    <span class="text-blue-600 ml-1">(nomor akan lanjut otomatis dari database)</span>
                                                </p>
                                                <p class="text-[9px] text-blue-600 mt-1 italic">
                                                    Contoh: Jika sudah ada hingga <span class="font-mono font-bold" x-text="form.prefix_label + '100'"></span>, 
                                                    maka akan dimulai dari <span class="font-mono font-bold" x-text="form.prefix_label + '101'"></span>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <div class="md:col-span-3">
                                <label class="text-[10px] font-bold text-gray-400 uppercase">Kondisi</label>
                                <select name="kondisi" x-model="form.kondisi" class="w-full bg-gray-50 px-3 py-2.5 rounded-lg outline-none mt-1 text-sm">
                                    <option value="Baik">Baik</option>
                                    <option value="Rusak Ringan">Rusak Ringan</option>
                                    <option value="Rusak Berat">Rusak Berat</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col-reverse md:flex-row justify-end gap-3 mt-8 pt-4 border-t">
                        <button type="button" @click="showForm = false" class="text-gray-400 font-bold text-sm px-6 py-2.5 md:py-0">BATAL</button>
                        <button type="submit" :disabled="dateError" :class="dateError ? 'opacity-50 cursor-not-allowed' : ''" class="bg-[#d13b1f] text-white px-8 py-2.5 rounded-xl font-bold shadow-lg transform active:scale-95 transition-all text-sm uppercase">
                            <span x-text="editMode ? 'UPDATE UNIT ' + form.kode_label : 'SIMPAN SEMUA UNIT'"></span>
                        </button>
                    </div>
                </form>
            </div>

            <div class="bg-white rounded-2xl md:rounded-3xl shadow-sm border border-gray-100 overflow-hidden">
                <!-- Filter Tabs berdasarkan Tipe Barang -->
                <div class="p-5 md:p-6 border-b border-gray-50">
                    <div class="flex flex-col gap-4">
                        <!-- Filter Tipe Barang -->
                        <div class="w-full overflow-x-auto">
                            <div class="flex items-center gap-2 mb-2">
                                <i data-lucide="tag" class="w-4 h-4 text-gray-500"></i>
                                <span class="text-xs font-bold text-gray-500 uppercase">Filter Tipe:</span>
                            </div>
                            <div class="flex p-1 bg-gray-100 rounded-xl min-w-max">
                                <a href="?filter_tipe=all<?= $search ? '&search=' . urlencode($search) : '' ?><?= $filter_status !== 'all' ? '&filter_status=' . $filter_status : '' ?>" 
                                   class="px-4 py-1.5 text-xs font-bold rounded-lg whitespace-nowrap transition-all <?= $filter_tipe == 'all' ? 'bg-white text-[#d13b1f] shadow-sm' : 'text-gray-400 hover:text-gray-600' ?>">
                                    Semua Tipe
                                </a>
                                <?php foreach($daftar_tipe as $tipe): ?>
                                <a href="?filter_tipe=<?= urlencode($tipe) ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $filter_status !== 'all' ? '&filter_status=' . $filter_status : '' ?>" 
                                   class="px-4 py-1.5 text-xs font-bold rounded-lg whitespace-nowrap transition-all <?= $filter_tipe == $tipe ? 'bg-white text-[#d13b1f] shadow-sm' : 'text-gray-400 hover:text-gray-600' ?>">
                                    <?= htmlspecialchars($tipe) ?>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Filter Status Peminjaman -->
                        <div class="w-full overflow-x-auto">
                            <div class="flex items-center gap-2 mb-2">
                                <i data-lucide="activity" class="w-4 h-4 text-gray-500"></i>
                                <span class="text-xs font-bold text-gray-500 uppercase">Filter Status:</span>
                            </div>
                            <div class="flex p-1 bg-gray-100 rounded-xl min-w-max gap-1">
                                <a href="?filter_status=all<?= $search ? '&search=' . urlencode($search) : '' ?><?= $filter_tipe !== 'all' ? '&filter_tipe=' . urlencode($filter_tipe) : '' ?>" 
                                   class="px-4 py-1.5 text-xs font-bold rounded-lg whitespace-nowrap transition-all flex items-center gap-1.5 <?= $filter_status == 'all' ? 'bg-white text-[#d13b1f] shadow-sm' : 'text-gray-400 hover:text-gray-600' ?>">
                                    <i data-lucide="list" class="w-3 h-3"></i>
                                    Semua Status
                                </a>
                                <a href="?filter_status=Tersedia<?= $search ? '&search=' . urlencode($search) : '' ?><?= $filter_tipe !== 'all' ? '&filter_tipe=' . urlencode($filter_tipe) : '' ?>" 
                                   class="px-4 py-1.5 text-xs font-bold rounded-lg whitespace-nowrap transition-all flex items-center gap-1.5 <?= $filter_status == 'Tersedia' ? 'bg-white text-green-600 shadow-sm' : 'text-gray-400 hover:text-gray-600' ?>">
                                    <i data-lucide="check-circle" class="w-3 h-3"></i>
                                    Tersedia
                                </a>
                                <a href="?filter_status=Dipinjam<?= $search ? '&search=' . urlencode($search) : '' ?><?= $filter_tipe !== 'all' ? '&filter_tipe=' . urlencode($filter_tipe) : '' ?>" 
                                   class="px-4 py-1.5 text-xs font-bold rounded-lg whitespace-nowrap transition-all flex items-center gap-1.5 <?= $filter_status == 'Dipinjam' ? 'bg-white text-red-600 shadow-sm' : 'text-gray-400 hover:text-gray-600' ?>">
                                    <i data-lucide="lock" class="w-3 h-3"></i>
                                    Dipinjam
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Search Bar -->
                    <div class="flex flex-col sm:flex-row gap-3 items-stretch sm:items-center w-full mt-4">
                        <form method="GET" class="relative w-full sm:w-64 md:w-72">
                            <input type="hidden" name="filter_tipe" value="<?= htmlspecialchars($filter_tipe) ?>">
                            <input type="hidden" name="filter_status" value="<?= htmlspecialchars($filter_status) ?>">
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Cari nama, tipe, kode, lokasi..." class="w-full pl-10 pr-4 py-2 bg-gray-50 rounded-xl text-sm outline-none focus:ring-2 focus:ring-orange-100 border border-transparent">
                            <i data-lucide="search" class="absolute left-3 top-2.5 w-4 h-4 text-gray-400"></i>
                        </form>
                        <?php if($search || $filter_tipe !== 'all' || $filter_status !== 'all'): ?>
                        <a href="sarana.php" class="flex items-center justify-center gap-1 text-xs text-gray-500 hover:text-[#d13b1f] transition-colors whitespace-nowrap">
                            <i data-lucide="x-circle" class="w-4 h-4"></i>
                            <span>Reset Semua Filter</span>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Info Filter Aktif -->
                <?php if($filter_tipe !== 'all' || $search || $filter_status !== 'all'): ?>
                <div class="px-5 md:px-6 py-3 bg-blue-50 border-b border-blue-100 flex items-center gap-2 text-sm">
                    <i data-lucide="filter" class="w-4 h-4 text-blue-600"></i>
                    <span class="text-blue-700">
                        <?php 
                        $parts = [];
                        if($filter_tipe !== 'all') $parts[] = "tipe <strong>" . htmlspecialchars($filter_tipe) . "</strong>";
                        if($filter_status !== 'all') $parts[] = "status <strong>" . $filter_status . "</strong>";
                        if($search) $parts[] = "kata kunci \"<strong>" . htmlspecialchars($search) . "</strong>\"";
                        
                        echo "Menampilkan <strong>" . count($all_items) . "</strong> unit";
                        if(!empty($parts)) {
                            echo " dengan " . implode(" dan ", $parts);
                        }
                        ?>
                    </span>
                </div>
                <?php else: ?>
                <div class="px-5 md:px-6 py-3 bg-gray-50 border-b border-gray-100">
                    <h3 class="font-bold text-gray-700 text-sm md:text-base">
                        Daftar Unit Inventaris (<?= count($all_items) ?> unit, <?= $total_tipes ?> tipe)
                    </h3>
                </div>
                <?php endif; ?>

                <div class="overflow-x-auto">
                    <table class="w-full text-left min-w-[800px]">
                        <thead>
                            <tr class="bg-gray-50/50">
                                <th class="p-5 text-[10px] font-black text-gray-400 uppercase">Tipe Barang</th>
                                <th class="p-5 text-[10px] font-black text-gray-400 uppercase">Lokasi (Jumlah)</th>
                                <th class="p-5 text-[10px] font-black text-gray-400 uppercase">Jenis</th>
                                <th class="p-5 text-[10px] font-black text-gray-400 uppercase">Total Unit</th>
                                <th class="p-5 text-[10px] font-black text-gray-400 uppercase text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <?php if(empty($items_by_tipe_paginated)): ?>
                            <tr>
                                <td colspan="5" class="p-10 text-center text-gray-400">
                                    <i data-lucide="inbox" class="w-12 h-12 mx-auto mb-2 opacity-30"></i>
                                    <p>Tidak ada data ditemukan</p>
                                    <?php if($filter_tipe !== 'all' || $search): ?>
                                    <a href="sarana.php" class="inline-flex items-center gap-1 mt-3 text-sm text-[#d13b1f] hover:underline">
                                        <i data-lucide="arrow-left" class="w-4 h-4"></i>
                                        Tampilkan semua data
                                    </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach($items_by_tipe_paginated as $tipe => $data): 
                                    $info = $data['info'];
                                    $lokasiGrouped = $data['lokasi_grouped'];
                                    $totalUnits = $data['total_unit'];
                                ?>
                                <tr class="hover:bg-gray-50/80 transition-colors">
                                    <td class="p-5">
                                        <div class="flex items-center gap-3">
                                            <div class="w-12 h-12 bg-purple-100 rounded-xl flex items-center justify-center shadow-sm">
                                                <i data-lucide="tag" class="w-6 h-6 text-purple-600"></i>
                                            </div>
                                            <div>
                                                <p class="font-bold text-gray-800 text-base"><?= htmlspecialchars($tipe) ?></p>
                                                <p class="text-[10px] text-gray-400 uppercase">Nama: <?= htmlspecialchars($info['nama']) ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="p-5 max-w-md">
                                        <div class="flex flex-wrap gap-1.5">
                                            <?php 
                                            if(!empty($lokasiGrouped)) {
                                                foreach($lokasiGrouped as $lokasi => $jumlah): ?>
                                                    <button type="button" 
                                                            @click="showDetailLokasi('<?= htmlspecialchars($tipe) ?>', '<?= htmlspecialchars($lokasi) ?>')"
                                                            class="px-2.5 py-1 bg-blue-50 text-blue-700 border border-blue-200 text-[9px] font-bold rounded-lg uppercase hover:bg-blue-600 hover:text-white hover:border-blue-600 transition-all cursor-pointer flex items-center gap-1">
                                                        <i data-lucide="map-pin" class="w-3 h-3"></i>
                                                        <span class="line-clamp-1"><?= htmlspecialchars($lokasi) ?> (<?= $jumlah ?>)</span>
                                                    </button>
                                                <?php endforeach;
                                            } else { 
                                                echo '<span class="text-[9px] text-gray-300 italic">Tidak ada lokasi</span>'; 
                                            } ?>
                                        </div>
                                    </td>
                                    <td class="p-5">
                                        <?php 
                                            $jenis = $info['jenis'];
                                            $jenisColor = 'bg-blue-50 text-blue-600';
                                            $jenisIcon = 'armchair';
                                            if($jenis == 'Elektronik') { $jenisColor = 'bg-green-50 text-green-600'; $jenisIcon = 'monitor'; }
                                            elseif($jenis == 'Peralatan Umum') { $jenisColor = 'bg-purple-50 text-purple-600'; $jenisIcon = 'wrench'; }
                                        ?>
                                        <div class="flex items-center gap-2">
                                            <div class="p-1.5 <?= $jenisColor ?> rounded-lg">
                                                <i data-lucide="<?= $jenisIcon ?>" class="w-3.5 h-3.5"></i>
                                            </div>
                                            <span class="text-xs font-bold text-gray-700"><?= htmlspecialchars($jenis) ?></span>
                                        </div>
                                    </td>
                                    <td class="p-5">
                                        <div class="flex flex-col gap-2">
                                            <!-- Total Unit -->
                                            <div class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-50 rounded-lg">
                                                <i data-lucide="package" class="w-4 h-4 text-blue-600"></i>
                                                <span class="text-sm font-black text-blue-700"><?= $totalUnits ?> Unit</span>
                                            </div>
                                            
                                            <!-- Status Tersedia -->
                                            <?php if($data['tersedia'] > 0): ?>
                                            <div class="inline-flex items-center gap-1.5 px-3 py-1 bg-green-50 rounded-lg">
                                                <div class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></div>
                                                <span class="text-xs font-bold text-green-700"><?= $data['tersedia'] ?> Tersedia</span>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <!-- Status Dipinjam -->
                                            <?php if($data['dipinjam'] > 0): ?>
                                            <div class="inline-flex items-center gap-1.5 px-3 py-1 bg-red-50 rounded-lg">
                                                <i data-lucide="lock" class="w-3 h-3 text-red-600"></i>
                                                <span class="text-xs font-bold text-red-700"><?= $data['dipinjam'] ?> Dipinjam</span>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="p-5">
                                        <div class="flex justify-center gap-1">
                                            <button @click="showDetailTipe('<?= htmlspecialchars($tipe) ?>')" 
                                                    class="p-2 text-blue-500 hover:bg-blue-50 rounded-lg transition-colors"
                                                    title="Lihat Detail Semua Unit">
                                                <i data-lucide="eye" class="w-4 h-4"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if($total_pages > 1): ?>
                <div class="p-5 md:p-6 border-t border-gray-50 flex flex-col sm:flex-row justify-between items-center gap-4">
                    <p class="text-xs md:text-sm text-gray-500 order-2 sm:order-1">
                        Halaman <?= $page ?> dari <?= $total_pages ?>
                    </p>
                    <div class="flex gap-2 w-full sm:w-auto order-1 sm:order-2">
                        <?php 
                        $pagination_params = [];
                        if($search) $pagination_params[] = 'search=' . urlencode($search);
                        if($filter_tipe !== 'all') $pagination_params[] = 'filter_tipe=' . urlencode($filter_tipe);
                        $pagination_query = !empty($pagination_params) ? '&' . implode('&', $pagination_params) : '';
                        ?>
                        
                        <?php if($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?><?= $pagination_query ?>" class="flex-1 sm:flex-none flex items-center justify-center gap-1 px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition-colors text-sm font-medium">
                            <i data-lucide="chevron-left" class="w-4 h-4"></i>
                            <span>Kembali</span>
                        </a>
                        <?php endif; ?>
                        
                        <?php if($page < $total_pages): ?>
                        <a href="?page=<?= $page + 1 ?><?= $pagination_query ?>" class="flex-1 sm:flex-none flex items-center justify-center gap-1 px-4 py-2 bg-[#d13b1f] hover:bg-[#b53118] text-white rounded-lg transition-colors text-sm font-medium">
                            <span>Lanjut</span>
                            <i data-lucide="chevron-right" class="w-4 h-4"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Modal Detail Tipe Barang -->
        <div x-show="showModalTipe" 
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             @click="showModalTipe = false"
             class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4"
             x-cloak>
            <div @click.stop 
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 scale-90"
                 x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-90"
                 class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl max-h-[85vh] overflow-hidden flex flex-col">
                
                <!-- Header Modal -->
                <div class="bg-gradient-to-r from-[#d13b1f] to-[#b53118] text-white p-6 flex justify-between items-start">
                    <div>
                        <h3 class="text-xl font-bold mb-1 flex items-center gap-2">
                            <i data-lucide="tag" class="w-5 h-5"></i>
                            Detail Tipe Barang
                        </h3>
                        <p class="text-white/80 text-sm flex items-center gap-2">
                            <i data-lucide="package" class="w-4 h-4"></i>
                            <span x-text="modalTipeData.tipe"></span>
                        </p>
                    </div>
                    <button @click="showModalTipe = false" class="hover:bg-white/20 p-2 rounded-lg transition-colors">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>

                <!-- Content Modal -->
                <div class="p-6 overflow-y-auto flex-1">
                    <h4 class="text-sm font-black text-gray-400 uppercase mb-3">Daftar Unit (<?= count($all_items) ?> Total)</h4>
                    
                    <template x-if="modalTipeData.items.length > 0">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <template x-for="(item, index) in modalTipeData.items" :key="index">
                                <div class="flex items-start gap-3 p-4 bg-gray-50 rounded-xl border-2 border-gray-200 hover:border-[#d13b1f] transition-all group">
                                    <!-- Foto -->
                                    <div class="relative flex-shrink-0">
                                        <img :src="item.photo || 'https://via.placeholder.com/80'" class="w-20 h-20 rounded-lg object-cover border-2 border-white shadow-md">
                                        <span class="absolute -top-2 -left-2 bg-[#d13b1f] text-white text-[8px] font-bold px-2 py-0.5 rounded-md shadow-sm" x-text="item.kode_label"></span>
                                    </div>
                                    
                                    <!-- Info -->
                                    <div class="flex-1 min-w-0">
                                        <p class="font-bold text-gray-800 text-sm mb-1 truncate" x-text="item.nama"></p>
                                        
                                        <!-- Status Peminjaman Badge -->
                                        <div class="mb-2">
                                            <span x-show="item.status_pinjam === 'Tersedia'" 
                                                  class="inline-flex items-center gap-1 px-2 py-0.5 bg-green-100 text-green-700 rounded text-[9px] font-black uppercase">
                                                <div class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></div>
                                                Tersedia
                                            </span>
                                            <span x-show="item.status_pinjam === 'Dipinjam'" 
                                                  class="inline-flex items-center gap-1 px-2 py-0.5 bg-red-100 text-red-700 rounded text-[9px] font-black uppercase">
                                                <i data-lucide="lock" class="w-3 h-3"></i>
                                                Dipinjam
                                            </span>
                                        </div>
                                        
                                        <div class="space-y-1 text-[10px]">
                                            <!-- Lokasi -->
                                            <div class="flex items-center gap-1.5 text-gray-600">
                                                <i data-lucide="map-pin" class="w-3 h-3 text-blue-500"></i>
                                                <span class="truncate" x-text="item.lokasi"></span>
                                            </div>
                                            
                                            <!-- Jenis -->
                                            <div class="flex items-center gap-1.5 text-gray-600">
                                                <i data-lucide="layers" class="w-3 h-3"></i>
                                                <span x-text="item.jenis"></span>
                                            </div>
                                            
                                            <!-- Tanggal Beli -->
                                            <div class="flex items-center gap-1.5 text-gray-600">
                                                <i data-lucide="calendar" class="w-3 h-3 text-purple-500"></i>
                                                <span x-text="formatDate(item.tahun_beli)"></span>
                                            </div>
                                            
                                            <!-- Kondisi -->
                                            <div class="mt-2">
                                                <span 
                                                    class="inline-block px-2 py-0.5 rounded-full text-[9px] font-black uppercase"
                                                    :class="{
                                                        'bg-green-100 text-green-600': item.kondisi === 'Baik',
                                                        'bg-orange-100 text-orange-600': item.kondisi === 'Rusak Ringan',
                                                        'bg-red-100 text-red-600': item.kondisi === 'Rusak Berat'
                                                    }"
                                                    x-text="item.kondisi">
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Action Buttons -->
                                    <div class="flex flex-col gap-1">
                                        <button @click="editItemFromModal(item)" 
                                                class="p-2 text-blue-500 hover:bg-blue-50 rounded-lg transition-colors"
                                                title="Edit">
                                            <i data-lucide="edit-3" class="w-4 h-4"></i>
                                        </button>
                                        <button @click="deleteItemFromModal(item.id, item.nama, item.kode_label)" 
                                                class="p-2 text-red-500 hover:bg-red-50 rounded-lg transition-colors"
                                                title="Hapus">
                                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>

                    <template x-if="modalTipeData.items.length === 0">
                        <div class="text-center py-12">
                            <i data-lucide="inbox" class="w-16 h-16 text-gray-300 mx-auto mb-3"></i>
                            <p class="text-gray-400 italic">Tidak ada data unit</p>
                        </div>
                    </template>
                </div>

                <!-- Footer Modal -->
                <div class="border-t p-4 bg-gray-50 flex justify-between items-center">
                    <p class="text-xs text-gray-500 flex items-center gap-1">
                        <i data-lucide="info" class="w-3 h-3"></i>
                        Total <span class="font-bold text-[#d13b1f]" x-text="modalTipeData.items.length"></span> unit dengan tipe ini
                    </p>
                    <button @click="showModalTipe = false" class="px-6 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold rounded-lg text-sm transition-colors">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </main>

    <script>
        function confirmDeleteSarana(id, namaBarang, kodeLabel) {
            Swal.fire({
                title: 'Apakah Anda yakin?',
                html: `Anda akan menghapus unit:<br><strong>${namaBarang}</strong><br><span class="text-gray-500 text-sm">[${kodeLabel}]</span>`,
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
                    window.location.href = `sarana.php?delete=${id}`;
                }
            });
        }

        function saranaApp() {
            return {
                showForm: false, editMode: false, dateError: false, dateErrorMessage: '',
                showModalTipe: false,
                modalTipeData: {
                    tipe: '',
                    items: []
                },
                allRoomTemplates: {},
                filteredRoomTemplates: [],
                allTipeOptions: <?= json_encode($template_sarana) ?>,
                filteredTipeOptions: [],
                form: { 
                    id: '', nama: '', tipe_barang: '', template_id: '', customTipe: '', gedung: '', lantai: '', ruangan: '', jumlah: 1, 
                    prefix_label: '', tanggal_beli: '01', bulan_beli: '01',
                    tahun_beli: new Date().getFullYear(), kondisi: 'Baik', jenis: 'Furniture', 
                    photo: '', status: 'Tersedia'
                },
                gedungData: <?= json_encode($daftar_gedung) ?>,
                allItemsData: <?= json_encode($all_items) ?>,
                
                // 🔥 FILTER TIPE BERDASARKAN NAMA BARANG
                onNamaChange() {
                    const namaBarang = this.form.nama.toLowerCase().trim();
                    
                    if (!namaBarang) {
                        this.filteredTipeOptions = [];
                        this.form.tipe_barang = '';
                        this.form.template_id = null;
                        return;
                    }
                    
                    // Filter template yang cocok dengan nama barang
                    const filtered = [];
                    
                    this.allTipeOptions.forEach(template => {
                        const daftarTipe = JSON.parse(template.daftar_tipe || '[]');
                        
                        // Filter tipe yang mengandung kata dari nama barang
                        const matchingTipes = daftarTipe.filter(tipe => {
                            const tipeWords = tipe.toLowerCase().split(/\s+/);
                            const namaWords = namaBarang.split(/\s+/);
                            
                            // Check jika ada kata yang match
                            return namaWords.some(namaWord => 
                                tipeWords.some(tipeWord => 
                                    tipeWord.includes(namaWord) || namaWord.includes(tipeWord)
                                )
                            );
                        });
                        
                        if (matchingTipes.length > 0) {
                            filtered.push({
                                kategori: template.nama_kategori,
                                template_id: template.id,
                                tipe_list: matchingTipes,
                                icon: template.icon
                            });
                        }
                    });
                    
                    this.filteredTipeOptions = filtered;
                    
                    // Auto-select jika hanya ada 1 tipe
                    if (filtered.length === 1 && filtered[0].tipe_list.length === 1) {
                        this.form.tipe_barang = filtered[0].tipe_list[0];
                        this.form.template_id = filtered[0].template_id;
                    } else {
                        this.form.tipe_barang = '';
                        this.form.template_id = null;
                    }
                    
                    // Update prefix setelah filter
                    this.updatePrefix();
                    
                    setTimeout(() => lucide.createIcons(), 50);
                },
                
                showDetailTipe(tipe) {
                    // Filter items berdasarkan tipe
                    const filteredItems = this.allItemsData.filter(item => {
                        const itemTipe = item.tipe_barang || 'Tanpa Tipe';
                        return itemTipe === tipe;
                    });
                    
                    this.modalTipeData = {
                        tipe: tipe,
                        items: filteredItems
                    };
                    
                    this.showModalTipe = true;
                    setTimeout(() => lucide.createIcons(), 100);
                },
                
                showDetailLokasi(tipe, lokasi) {
                    // Filter items berdasarkan tipe DAN lokasi
                    const filteredItems = this.allItemsData.filter(item => {
                        const itemTipe = item.tipe_barang || 'Tanpa Tipe';
                        return itemTipe === tipe && item.lokasi === lokasi;
                    });
                    
                    this.modalTipeData = {
                        tipe: `${tipe} di ${lokasi}`,
                        items: filteredItems
                    };
                    
                    this.showModalTipe = true;
                    setTimeout(() => lucide.createIcons(), 100);
                },
                
                formatDate(dateString) {
                    if (!dateString) return '-';
                    const date = new Date(dateString);
                    const options = { day: '2-digit', month: 'short', year: 'numeric' };
                    return date.toLocaleDateString('id-ID', options);
                },
                
                editItemFromModal(item) {
                    this.showModalTipe = false;
                    setTimeout(() => {
                        this.openEdit(item);
                    }, 300);
                },
                
                deleteItemFromModal(id, nama, kodeLabel) {
                    this.showModalTipe = false;
                    setTimeout(() => {
                        confirmDeleteSarana(id, nama, kodeLabel);
                    }, 300);
                },
                
                listLantai() {
                    const selectedGedung = this.gedungData.find(g => g.nama_gedung === this.form.gedung);
                    
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
                    this.form.lantai = '';
                    this.form.ruangan = '';
                    this.filteredRoomTemplates = [];
                    await this.loadRoomTemplates();
                    this.updatePrefix();
                },
                
                onLantaiChange() {
                    this.form.ruangan = '';
                    this.filterRoomsByLantai();
                    this.updatePrefix();
                },
                
                async loadRoomTemplates() {
                    if (!this.form.gedung) {
                        this.allRoomTemplates = {};
                        this.filteredRoomTemplates = [];
                        return;
                    }
                    
                    try {
                        const selectedGedung = this.gedungData.find(g => g.nama_gedung === this.form.gedung);
                        
                        if (selectedGedung && selectedGedung.daftar_ruangan) {
                            this.allRoomTemplates = JSON.parse(selectedGedung.daftar_ruangan);
                        } else {
                            this.allRoomTemplates = {};
                        }
                        
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
                    
                    const lantaiNumber = this.form.lantai.replace('Lantai ', '');
                    const roomsInLantai = this.allRoomTemplates[lantaiNumber];
                    
                    if (roomsInLantai && Array.isArray(roomsInLantai)) {
                        this.filteredRoomTemplates = roomsInLantai.map(room => {
                            if (typeof room === 'string') {
                                return { nama: room };
                            } else if (room && typeof room === 'object') {
                                return {
                                    nama: room.nama || ''
                                };
                            }
                            return null;
                        }).filter(room => room && room.nama && room.nama.trim() !== '');
                    } else {
                        this.filteredRoomTemplates = [];
                    }
                },
                
                checkDateValidity() {
                    const tanggal = this.form.tanggal_beli;
                    const bulan = this.form.bulan_beli;
                    const tahun = this.form.tahun_beli;
                    if (!tanggal || !bulan || !tahun) { this.dateError = false; return; }
                    const inputDate = new Date(tahun, bulan - 1, tanggal);
                    const today = new Date();
                    today.setHours(0, 0, 0, 0);
                    if (inputDate.getDate() != tanggal || inputDate.getMonth() != (bulan - 1)) {
                        this.dateError = true; this.dateErrorMessage = 'Tanggal tidak valid!'; return;
                    }
                    if (inputDate > today) {
                        this.dateError = true; this.dateErrorMessage = 'Tanggal melebihi hari ini!'; return;
                    }
                    this.dateError = false; this.dateErrorMessage = '';
                },
                
                validateDate(event) {
                    this.checkDateValidity();
                    if (this.dateError) { event.preventDefault(); alert(this.dateErrorMessage); return false; }
                    return true;
                },
                
                // 🔥 UPDATE PREFIX BERDASARKAN NAMA BARANG + LOKASI
                updatePrefix() {
                    if (this.editMode) return; // Jangan update saat edit
                    
                    let prefix = '';
                    
                    // 1. Dari NAMA BARANG (3 huruf pertama atau inisial)
                    if (this.form.nama) {
                        const namaWords = this.form.nama.trim().toUpperCase().split(/\s+/);
                        if (namaWords.length === 1) {
                            // Single word: ambil 3 huruf pertama
                            prefix += namaWords[0].substring(0, 3);
                        } else {
                            // Multiple words: ambil inisial tiap kata
                            prefix += namaWords.map(w => w[0]).join('');
                        }
                    }
                    
                    // 2. Dari RUANGAN (3 huruf pertama atau inisial)
                    if (this.form.ruangan) {
                        if (prefix) prefix += '-'; // Separator
                        
                        const ruanganWords = this.form.ruangan.trim().toUpperCase().split(/\s+/);
                        if (ruanganWords.length === 1) {
                            prefix += ruanganWords[0].substring(0, 3);
                        } else {
                            prefix += ruanganWords.map(w => w[0]).join('');
                        }
                    }
                    
                    // Set prefix dengan trailing dash
                    this.form.prefix_label = prefix ? prefix + '-' : '';
                },

                onTipeChange() {
                    const selectElement = document.querySelector('select[name="tipe_barang"]');
                    const selectedOption = selectElement ? selectElement.options[selectElement.selectedIndex] : null;
                    
                    if (selectedOption && selectedOption.dataset.templateId) {
                        this.form.template_id = parseInt(selectedOption.dataset.templateId);
                    } else {
                        this.form.template_id = null;
                    }
                },
                
                openTambah() {
                    this.editMode = false; this.dateError = false; this.dateErrorMessage = '';
                    this.allRoomTemplates = {};
                    this.filteredRoomTemplates = [];
                    this.filteredTipeOptions = [];
                    this.form = { 
                        id: '', nama: '', tipe_barang: '', template_id: '', customTipe: '', gedung: '', lantai: '', ruangan: '', jumlah: 1, 
                        prefix_label: '', tanggal_beli: '01', bulan_beli: '01',
                        tahun_beli: new Date().getFullYear(), kondisi: 'Baik', jenis: 'Furniture', 
                        photo: '', status: 'Tersedia' 
                    };
                    this.showForm = true;
                    this.refreshIcons();
                },
                
                async openEdit(data) {
                    this.editMode = true; this.dateError = false;
                    let gedung = '', lantai = '', ruangan = '';
                    if (data.lokasi && typeof data.lokasi === 'string' && data.lokasi.includes('|')) {
                        let lok = data.lokasi.split(" | ");
                        gedung = lok[0] || ''; lantai = lok[1] || ''; ruangan = lok[2] || '';
                    } else { ruangan = data.lokasi; }
                    
                    let tb = data.tahun_beli;
                    let t = '01', b = '01', th = new Date().getFullYear();
                    if(tb && typeof tb === 'string' && tb.includes('-')) {
                        const p = tb.split('-'); th = p[0]; b = p[1]; t = p[2];
                    }
                    
                    this.form = { 
                        id: data.id, nama: data.nama, tipe_barang: data.tipe_barang || '', template_id: data.template_id || '', customTipe: '', gedung, lantai, ruangan,
                        tanggal_beli: t, bulan_beli: b, tahun_beli: th,
                        jenis: data.jenis, kondisi: data.kondisi, status: data.status,
                        photo: data.photo, kode_label: data.kode_label,
                        jumlah: data.jumlah, deskripsi: data.deskripsi
                    };
                    
                    // Load filtered tipe options untuk edit mode
                    this.onNamaChange();
                    
                    await this.loadRoomTemplates();
                    
                    if (this.form.lantai) {
                        this.filterRoomsByLantai();
                    }
                    
                    this.showForm = true;
                    window.scrollTo({top: 0, behavior: 'smooth'});
                    setTimeout(() => this.refreshIcons(), 100);
                },
                
                handleFile(e) {
                    const file = e.target.files[0];
                    if (!file) return;
                    const reader = new FileReader();
                    reader.onload = (f) => this.form.photo = f.target.result;
                    reader.readAsDataURL(file);
                },
                
                refreshIcons() { lucide.createIcons(); }
            }
        }
        document.addEventListener('DOMContentLoaded', () => lucide.createIcons());
    </script>
</body>
</html>