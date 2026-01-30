<?php
session_start();
require "../config/database.php";
require "../auth/auth_helper.php";

cek_kemanan_login($pdo);

$msg = "";

// Tambah/Update Gedung
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_gedung') {
    $id = !empty($_POST['id']) ? $_POST['id'] : null;
    $nama_gedung = trim($_POST['nama_gedung']);
    $jumlah_lantai = (int)$_POST['jumlah_lantai'];
    $keterangan = trim($_POST['keterangan']);
    
    // Ambil data ruangan per lantai dari form
    $daftar_ruangan_json = $_POST['daftar_ruangan'] ?? '{}';

    try {
        // Validasi duplikat nama gedung
        if ($id) {
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM gedung WHERE nama_gedung = ? AND id != ?");
            $checkStmt->execute([$nama_gedung, $id]);
        } else {
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM gedung WHERE nama_gedung = ?");
            $checkStmt->execute([$nama_gedung]);
        }
        
        $count = $checkStmt->fetchColumn();
        
        if ($count > 0) {
            $msg = "⚠️ Nama gedung '$nama_gedung' sudah terdaftar! Gunakan nama lain.";
        } else {
            if ($id) {
                // 🔥 CASCADE DELETE: Hapus ruangan yang tidak ada di template baru
                // Ambil template lama
                $stmtOld = $pdo->prepare("SELECT daftar_ruangan FROM gedung WHERE id = ?");
                $stmtOld->execute([$id]);
                $oldData = $stmtOld->fetch(PDO::FETCH_ASSOC);
                $oldTemplate = !empty($oldData['daftar_ruangan']) ? json_decode($oldData['daftar_ruangan'], true) : [];
                $newTemplate = json_decode($daftar_ruangan_json, true);
                
                // Kumpulkan semua nama ruangan dari template lama dan baru
                $oldRoomNames = [];
                $newRoomNames = [];
                
                if(is_array($oldTemplate)) {
                    foreach($oldTemplate as $lantai => $rooms) {
                        if(is_array($rooms)) {
                            foreach($rooms as $room) {
                                $roomName = is_array($room) ? $room['nama'] : $room;
                                $oldRoomNames[] = $roomName;
                            }
                        }
                    }
                }
                
                if(is_array($newTemplate)) {
                    foreach($newTemplate as $lantai => $rooms) {
                        if(is_array($rooms)) {
                            foreach($rooms as $room) {
                                $roomName = is_array($room) ? $room['nama'] : $room;
                                $newRoomNames[] = $roomName;
                            }
                        }
                    }
                }
                
                // Cari ruangan yang dihapus dari template
                $deletedRooms = array_diff($oldRoomNames, $newRoomNames);
                
                // Hapus ruangan dari tabel ruangan
                $deletedCount = 0;
                if(!empty($deletedRooms)) {
                    foreach($deletedRooms as $roomName) {
                        $stmtDelete = $pdo->prepare("DELETE FROM ruangan WHERE gedung_id = ? AND nama_ruangan = ?");
                        $stmtDelete->execute([$id, $roomName]);
                        $deletedCount += $stmtDelete->rowCount();
                    }
                }
                
                // Update gedung
                $stmt = $pdo->prepare("UPDATE gedung SET nama_gedung=?, jumlah_lantai=?, keterangan=?, daftar_ruangan=? WHERE id=?");
                $stmt->execute([$nama_gedung, $jumlah_lantai, $keterangan, $daftar_ruangan_json, $id]);
                
                if($deletedCount > 0) {
                    $msg = "✅ Gedung berhasil diperbarui! ($deletedCount ruangan dihapus karena tidak ada di template)";
                } else {
                    $msg = "✅ Gedung berhasil diperbarui!";
                }
            } else {
                $stmt = $pdo->prepare("INSERT INTO gedung (nama_gedung, jumlah_lantai, keterangan, daftar_ruangan) VALUES (?, ?, ?, ?)");
                $stmt->execute([$nama_gedung, $jumlah_lantai, $keterangan, $daftar_ruangan_json]);
                $msg = "✅ Gedung baru berhasil ditambahkan!";
            }
        }
    } catch (PDOException $e) {
        $msg = "❌ Error: " . $e->getMessage();
    }
}

// Hapus Gedung
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    try {
        // Cek apakah gedung masih digunakan oleh ruangan
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM ruangan WHERE gedung_id = ?");
        $checkStmt->execute([$id]);
        $count = $checkStmt->fetchColumn();
        
        if ($count > 0) {
            $msg = "⚠️ Gedung tidak dapat dihapus karena masih digunakan oleh $count ruangan!";
        } else {
            $stmt = $pdo->prepare("DELETE FROM gedung WHERE id = ?");
            $stmt->execute([$id]);
            header("Location: gedung.php?status=deleted");
            exit;
        }
    } catch (PDOException $e) {
        $msg = "❌ Gagal menghapus: " . $e->getMessage();
    }
}

// Ambil semua gedung
$stmt = $pdo->query("SELECT * FROM gedung ORDER BY nama_gedung ASC");
$daftar_gedung = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Hitung jumlah ruangan per gedung dan ambil daftar ruangannya
$stmt_count = $pdo->query("SELECT gedung_id, COUNT(*) as jumlah FROM ruangan GROUP BY gedung_id");
$ruangan_count = [];
while ($row = $stmt_count->fetch(PDO::FETCH_ASSOC)) {
    $ruangan_count[$row['gedung_id']] = $row['jumlah'];
}

// Ambil detail ruangan per gedung (dengan JOIN)
$stmt_ruangan = $pdo->query("
    SELECT 
        r.gedung_id,
        r.nama_ruangan, 
        r.lantai, 
        r.kapasitas,
        g.nama_gedung
    FROM ruangan r
    INNER JOIN gedung g ON r.gedung_id = g.id
    ORDER BY g.nama_gedung, r.lantai, r.nama_ruangan
");
$ruangan_list = [];
while ($row = $stmt_ruangan->fetch(PDO::FETCH_ASSOC)) {
    $ruangan_list[$row['gedung_id']][] = $row;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../assets/kampusums.png" />
    <title>Kelola Gedung</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-gray-50 font-sans flex" x-data="gedungApp()">
    
    <?php include "../components/AdminNavbar.php"; ?>

    <main class="flex-1 lg:ml-60 p-4 md:p-5 mt-16 lg:mt-0 min-h-screen w-full overflow-x-hidden">
        <div class="max-w-7xl mx-auto">
            
            <div class="flex flex-col md:flex-row md:justify-between md:items-end gap-3 md:gap-4 mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800">Kelola Gedung</h1>
                    <p class="text-gray-500 mt-1">Manajemen data gedung, lantai, dan daftar ruangan.</p>
                </div>
                <div class="flex gap-3">
                    <a href="ruangan.php" class="flex items-center gap-2 bg-gray-600 hover:bg-gray-700 text-white px-5 py-2.5 rounded-xl font-medium shadow-md transition-all">
                        <i data-lucide="arrow-left"></i> Kembali ke Ruangan
                    </a>
                    <button @click="openTambah()" class="flex items-center gap-2 bg-[#d13b1f] hover:bg-[#b53118] text-white px-5 py-2.5 rounded-xl font-medium shadow-md transition-all">
                        <i data-lucide="plus"></i> Tambah Gedung
                    </button>
                </div>
            </div>

            <?php if($msg || isset($_GET['status'])): ?>
            <div class="<?= strpos($msg, '⚠️') !== false || strpos($msg, '❌') !== false ? 'bg-red-100 border-red-200 text-red-700' : 'bg-green-100 border-green-200 text-green-700' ?> border px-4 py-3 rounded-xl mb-6 flex items-center justify-between shadow-sm">
                <div class="flex items-center gap-2 font-medium">
                    <i data-lucide="<?= strpos($msg, '⚠️') !== false || strpos($msg, '❌') !== false ? 'alert-circle' : 'check-circle' ?>" size="18"></i> 
                    <?= $msg ?: "Gedung berhasil dihapus!" ?>
                </div>
                <button type="button" onclick="window.location.href='gedung.php'"><i data-lucide="x" size="16"></i></button>
            </div>
            <?php endif; ?>

            <!-- Form -->
            <div x-show="showForm" x-transition x-cloak class="relative bg-black/5 rounded-2xl shadow-xl border border-black/10 p-6 mb-8">
                <button type="button" 
                        @click="showForm = false"
                        class="absolute top-1 right-1 w-10 h-10 flex items-center justify-center bg-white hover:bg-red-50 text-gray-400 hover:text-red-500 rounded-xl border border-gray-200 hover:border-red-300 transition-all shadow-sm hover:shadow-md z-10 group">
                    <i data-lucide="x" class="w-5 h-5 group-hover:scale-110 transition-transform"></i>
                </button>
                <form method="POST" @submit.prevent="validateAndSubmit()">
                    <input type="hidden" name="action" value="save_gedung">
                    <input type="hidden" name="id" x-model="form.id">
                    <input type="hidden" name="daftar_ruangan" :value="JSON.stringify(form.daftarRuangan)">
                    
                    <div class="grid grid-cols-2 gap-5 mb-6">
                        <div>
                            <label class="text-[10px] font-bold text-gray-700 uppercase">Nama Gedung <span class="text-red-500">*</span></label>
                            <input type="text" name="nama_gedung" x-model="form.nama_gedung" required 
                                   class="w-full mt-1 px-3 py-2.5 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-1 focus:ring-[#d13b1f] focus:border-[#d13b1f]"
                                   placeholder="Contoh: Gedung A">
                            <p class="text-[11px] text-gray-500 mt-1 italic">Nama harus unik, tidak boleh duplikat</p>
                        </div>
                        <div>
                            <label class="text-[10px] font-bold text-gray-700 uppercase">Jumlah Lantai <span class="text-red-500">*</span></label>
                            <input type="number" name="jumlah_lantai" x-model="form.jumlah_lantai" min="1" max="20" required 
                                   @input="updateLantaiList()"
                                   class="w-full mt-1 px-3 py-2.5 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-1 focus:ring-[#d13b1f] focus:border-[#d13b1f]"
                                   placeholder="Contoh: 5">
                        </div>
                        <div class="col-span-2">
                            <label class="text-[10px] font-bold text-gray-700 uppercase">Keterangan (Opsional)</label>
                            <textarea name="keterangan" x-model="form.keterangan" rows="2" 
                                      class="w-full mt-1 px-3 py-2.5 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-1 focus:ring-[#d13b1f] focus:border-[#d13b1f]"
                                      placeholder="Tambahkan catatan atau keterangan gedung..."></textarea>
                        </div>
                    </div>

                    <!-- Daftar Ruangan Per Lantai -->
                    <div class="border-t pt-6">
                        <div class="flex items-center gap-2 mb-4">
                            <i data-lucide="list" class="w-5 h-5 text-[#d13b1f]"></i>
                            <h3 class="text-sm font-bold text-gray-700 uppercase">Daftar Ruangan Per Lantai</h3>
                        </div>
                        
                        <div class="space-y-4">
                            <template x-for="lantai in parseInt(form.jumlah_lantai)" :key="lantai">
                                <div class="bg-gray-50 rounded-xl p-4 border border-gray-200">
                                    <div class="flex items-center justify-between mb-3">
                                        <h4 class="font-bold text-gray-800 flex items-center gap-2">
                                            <i data-lucide="layers" class="w-4 h-4 text-[#d13b1f]"></i>
                                            Lantai <span x-text="lantai"></span>
                                        </h4>
                                        <button type="button" 
                                                @click="addRuangan(lantai)"
                                                class="text-xs bg-[#d13b1f] text-white px-3 py-1.5 rounded-lg hover:bg-[#b53118] transition-all flex items-center gap-1">
                                            <i data-lucide="plus" class="w-3 h-3"></i>
                                            Tambah Ruangan
                                        </button>
                                    </div>
                                    
                                    <div class="space-y-2">
                                        <template x-if="!form.daftarRuangan[lantai] || form.daftarRuangan[lantai].length === 0">
                                            <p class="text-xs text-gray-400 italic text-center py-4">Belum ada ruangan di lantai ini</p>
                                        </template>
                                        
                                        <template x-for="(ruangan, index) in form.daftarRuangan[lantai]" :key="index">
                                            <div class="bg-white rounded-lg p-3 border border-gray-200">
                                                <div class="flex items-center gap-3 mb-2">
                                                    <div class="w-8 h-8 bg-[#d13b1f]/10 rounded-lg flex items-center justify-center flex-shrink-0">
                                                        <i data-lucide="door-closed" class="w-4 h-4 text-[#d13b1f]"></i>
                                                    </div>
                                                    <input type="text" 
                                                           x-model="form.daftarRuangan[lantai][index].nama"
                                                           placeholder="Nama ruangan (contoh: R.401, Lab Komputer) *"
                                                           class="flex-1 px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-[#d13b1f]">
                                                    <button type="button" 
                                                            @click="removeRuangan(lantai, index)"
                                                            class="text-red-400 hover:bg-red-50 p-2 rounded-lg transition-colors">
                                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                                    </button>
                                                </div>
                                                <div class="flex items-center gap-3 ml-11">
                                                    <div class="flex-1 grid grid-cols-2 gap-2">
                                                        <div>
                                                            <label class="text-[9px] font-bold text-gray-600 uppercase mb-1 block">Panjang (m) <span class="text-red-500">*</span></label>
                                                            <input type="number" 
                                                                   x-model="form.daftarRuangan[lantai][index].panjang"
                                                                   @input="calculateLuas(lantai, index)"
                                                                   placeholder="Contoh: 8.0"
                                                                   min="0.1"
                                                                   step="0.1"
                                                                   class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-[#d13b1f]">
                                                        </div>
                                                        <div>
                                                            <label class="text-[9px] font-bold text-gray-600 uppercase mb-1 block">Lebar (m) <span class="text-red-500">*</span></label>
                                                            <input type="number" 
                                                                   x-model="form.daftarRuangan[lantai][index].lebar"
                                                                   @input="calculateLuas(lantai, index)"
                                                                   placeholder="Contoh: 6.0"
                                                                   min="0.1"
                                                                   step="0.1"
                                                                   class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-[#d13b1f]">
                                                        </div>
                                                    </div>
                                                    <div class="flex-shrink-0 px-3 py-2 bg-blue-50 border border-blue-200 rounded-lg min-w-[120px]">
                                                        <p class="text-[9px] font-bold text-gray-500 uppercase mb-1">Luas Total</p>
                                                        <p class="text-sm font-bold text-blue-600" 
                                                           x-text="getLuasDisplay(lantai, index)"></p>
                                                    </div>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
                        
                        <div class="mt-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                            <div class="flex items-start gap-2">
                                <i data-lucide="info" class="w-4 h-4 text-blue-600 mt-0.5 flex-shrink-0"></i>
                                <div class="text-xs text-blue-700">
                                    <p class="font-semibold mb-1">Informasi Penting:</p>
                                    <ul class="list-disc list-inside space-y-1 text-[11px]">
                                        <li>Daftar ruangan ini akan muncul sebagai pilihan dropdown di halaman Ruangan</li>
                                        <li>Nomor ruangan harus <strong>unik</strong> - sistem akan mendeteksi duplikat berdasarkan angka (contoh: "404", "R.404", "Lab 404" dianggap sama)</li>
                                        <li>Panjang dan Lebar ruangan wajib diisi dalam satuan meter (m)</li>
                                        <li>Luas ruangan akan dihitung otomatis (Panjang × Lebar)</li>
                                        <li>Anda bisa menambah atau mengurangi ruangan di setiap lantai</li>
                                        <li class="font-bold text-red-600">⚠️ Menghapus ruangan dari template akan otomatis menghapus data ruangan terkait di database!</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 mt-6 pt-4 border-t">
                        <button type="button" @click="showForm = false" class="text-gray-400 font-bold text-xs px-6 uppercase tracking-widest">Batal</button>
                        <button type="submit" class="bg-[#d13b1f] text-white px-10 py-2.5 rounded-xl font-bold shadow-lg uppercase tracking-tight">Simpan Data</button>
                    </div>
                </form>
            </div>

            <!-- Tabel -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="p-6 border-b border-gray-50">
                    <h2 class="text-sm font-bold text-gray-700 uppercase">Daftar Gedung</h2>
                    <p class="text-xs text-gray-500 mt-1">Total <?= count($daftar_gedung) ?> gedung terdaftar</p>
                </div>
            <div class="w-full overflow-x-auto"> 
                <table class="w-full text-left">
                    <thead class="bg-gray-50/50">
                        <tr>
                            <th class="p-4 text-[10px] font-black text-gray-400 uppercase border-b">Nama Gedung</th>
                            <th class="p-4 text-[10px] font-black text-gray-400 uppercase border-b text-center">Jumlah Lantai</th>
                            <th class="p-4 text-[10px] font-black text-gray-400 uppercase border-b text-center">Ruangan Terdaftar</th>
                            <th class="p-4 text-[10px] font-black text-gray-400 uppercase border-b text-center">Ruangan Template</th>
                            <th class="p-4 text-[10px] font-black text-gray-400 uppercase border-b">Keterangan</th>
                            <th class="p-4 text-[10px] font-black text-gray-400 uppercase border-b text-center">Tanggal Dibuat</th>
                            <th class="p-4 text-[10px] font-black text-gray-400 uppercase border-b text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php foreach($daftar_gedung as $gedung): 
                            $jumlah_ruangan = $ruangan_count[$gedung['id']] ?? 0;
                            $daftar_ruangan = $ruangan_list[$gedung['id']] ?? [];
                            $ruangan_template = !empty($gedung['daftar_ruangan']) ? json_decode($gedung['daftar_ruangan'], true) : [];
                            $total_template = 0;
                            if(is_array($ruangan_template)) {
                                foreach($ruangan_template as $lantai_rooms) {
                                    if(is_array($lantai_rooms)) {
                                        $total_template += count($lantai_rooms);
                                    }
                                }
                            }
                        ?>
                        <tr class="hover:bg-gray-50/80 transition-colors">
                            <td class="p-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 bg-[#d13b1f]/10 rounded-lg flex items-center justify-center">
                                        <i data-lucide="building-2" class="w-5 h-5 text-[#d13b1f]"></i>
                                    </div>
                                    <div>
                                        <p class="font-bold text-gray-800"><?= htmlspecialchars($gedung['nama_gedung']) ?></p>
                                        <p class="text-[9px] text-gray-400 uppercase font-bold">ID: <?= $gedung['id'] ?></p>
                                    </div>
                                </div>
                            </td>
                            <td class="p-4 text-center">
                                <span class="inline-flex items-center gap-1 px-3 py-1 bg-blue-50 text-blue-600 rounded-lg text-sm font-bold">
                                    <i data-lucide="layers" class="w-3 h-3"></i>
                                    <?= $gedung['jumlah_lantai'] ?> Lantai
                                </span>
                            </td>
                            <td class="p-4 text-center">
                                <?php if($jumlah_ruangan > 0): ?>
                                <button @click="toggleRoomList('<?= $gedung['id'] ?>')"
                                        class="inline-flex items-center gap-1 px-3 py-1 bg-green-50 text-green-600 rounded-lg text-sm font-bold hover:bg-green-100 transition-colors">
                                    <i data-lucide="door-open" class="w-3 h-3"></i>
                                    <?= $jumlah_ruangan ?> Ruangan
                                    <i data-lucide="chevron-down" class="w-3 h-3" :class="{'rotate-180': expandedRooms === '<?= $gedung['id'] ?>'}"></i>
                                </button>
                                <?php else: ?>
                                <span class="inline-flex items-center gap-1 px-3 py-1 bg-gray-50 text-gray-400 rounded-lg text-sm font-bold">
                                    <i data-lucide="door-open" class="w-3 h-3"></i>
                                    0 Ruangan
                                </span>
                                <?php endif; ?>
                            </td>
                            <td class="p-4 text-center">
                                <?php if($total_template > 0): ?>
                                <button @click="toggleTemplateList('<?= $gedung['id'] ?>')"
                                        class="inline-flex items-center gap-1 px-3 py-1 bg-purple-50 text-purple-600 rounded-lg text-sm font-bold hover:bg-purple-100 transition-colors">
                                    <i data-lucide="list-checks" class="w-3 h-3"></i>
                                    <?= $total_template ?> Template
                                    <i data-lucide="chevron-down" class="w-3 h-3" :class="{'rotate-180': expandedTemplate === '<?= $gedung['id'] ?>'}"></i>
                                </button>
                                <?php else: ?>
                                <span class="inline-flex items-center gap-1 px-3 py-1 bg-gray-50 text-gray-400 rounded-lg text-sm font-bold">
                                    <i data-lucide="list-checks" class="w-3 h-3"></i>
                                    0 Template
                                </span>
                                <?php endif; ?>
                            </td>
                            <td class="p-4">
                                <p class="text-xs text-gray-600 line-clamp-2">
                                    <?= !empty($gedung['keterangan']) ? htmlspecialchars($gedung['keterangan']) : '<span class="italic text-gray-400">Tidak ada keterangan</span>' ?>
                                </p>
                            </td>
                            <td class="p-4 text-center">
                                <?php if(!empty($gedung['created_at'])): ?>
                                <div class="text-xs text-gray-600">
                                    <p class="font-medium"><?= date('d/m/Y', strtotime($gedung['created_at'])) ?></p>
                                    <p class="text-[10px] text-gray-400"><?= date('H:i', strtotime($gedung['created_at'])) ?> WIB</p>
                                </div>
                                <?php else: ?>
                                <span class="text-xs italic text-gray-400">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-4">
                                <div class="flex justify-center gap-2">
                                    <button @click='openEdit(<?= json_encode($gedung) ?>)' 
                                            class="text-blue-500 hover:bg-blue-50 p-2 rounded-lg transition-colors"
                                            title="Edit Gedung">
                                        <i data-lucide="edit-3" class="w-4 h-4"></i>
                                    </button>
                                    <a href="?delete=<?= $gedung['id'] ?>" 
                                       onclick="return confirm('Hapus    <?= htmlspecialchars($gedung['nama_gedung']) ?>?\n\nPeringatan: Gedung tidak dapat dihapus jika masih memiliki ruangan terdaftar!')" 
                                       class="text-red-400 hover:bg-red-50 p-2 rounded-lg transition-colors"
                                       title="Hapus Gedung">
                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        
                        <!-- Daftar Ruangan Terdaftar (Expandable) -->
                        <?php if($jumlah_ruangan > 0): ?>
                        <tr x-show="expandedRooms === '<?= $gedung['id'] ?>'" 
                            x-transition
                            x-cloak
                            class="bg-gray-50/50">
                            <td colspan="7" class="p-0">
                                <div class="p-6">
                                    <div class="flex items-center gap-2 mb-4">
                                        <i data-lucide="list" class="w-4 h-4 text-gray-600"></i>
                                        <h3 class="font-bold text-gray-700">Ruangan Aktif di <?= htmlspecialchars($gedung['nama_gedung']) ?></h3>
                                    </div>
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                        <?php foreach($daftar_ruangan as $ruangan): ?>
                                        <div class="bg-white border border-gray-200 rounded-lg p-3 hover:shadow-md transition-shadow">
                                            <div class="flex items-start justify-between">
                                                <div class="flex-1">
                                                    <p class="font-bold text-gray-800 text-sm"><?= htmlspecialchars($ruangan['nama_ruangan']) ?></p>
                                                    <div class="flex items-center gap-3 mt-2">
                                                        <span class="text-[10px] text-gray-500 flex items-center gap-1">
                                                            <i data-lucide="layers" class="w-3 h-3"></i>
                                                            Lantai <?= $ruangan['lantai'] ?>
                                                        </span>
                                                        <span class="text-[10px] text-gray-500 flex items-center gap-1">
                                                            <i data-lucide="users" class="w-3 h-3"></i>
                                                            <?= $ruangan['kapasitas'] ?> orang
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                                    <i data-lucide="check" class="w-4 h-4 text-green-600"></i>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                        
                        <!-- Daftar Template Ruangan (Expandable) -->
                        <?php if($total_template > 0): ?>
                        <tr x-show="expandedTemplate === '<?= $gedung['id'] ?>'" 
                            x-transition
                            x-cloak
                            class="bg-purple-50/30">
                            <td colspan="7" class="p-0">
                                <div class="p-6">
                                    <div class="flex items-center gap-2 mb-4">
                                        <i data-lucide="list-checks" class="w-4 h-4 text-purple-600"></i>
                                        <h3 class="font-bold text-gray-700">Template Ruangan di <?= htmlspecialchars($gedung['nama_gedung']) ?></h3>
                                        <span class="text-xs text-purple-600 bg-purple-100 px-2 py-0.5 rounded-full font-bold">Template untuk registrasi</span>
                                    </div>
                                    
                                    <div class="space-y-4">
                                        <?php 
                                        if(is_array($ruangan_template)) {
                                            foreach($ruangan_template as $lantai => $rooms): 
                                                if(is_array($rooms) && count($rooms) > 0):
                                        ?>
                                        <div class="bg-white rounded-lg p-4 border border-purple-200">
                                            <h4 class="font-bold text-gray-700 text-sm mb-3 flex items-center gap-2">
                                                <i data-lucide="layers" class="w-4 h-4 text-purple-600"></i>
                                                Lantai <?= $lantai ?>
                                            </h4>
                                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2">
                                                <?php foreach($rooms as $room): 
                                                    $roomName = is_array($room) ? $room['nama'] : $room;
                                                    $roomPanjang = is_array($room) && isset($room['panjang']) ? $room['panjang'] : null;
                                                    $roomLebar = is_array($room) && isset($room['lebar']) ? $room['lebar'] : null;
                                                    $roomLuas = is_array($room) && isset($room['luas']) ? $room['luas'] : null;
                                                    
                                                    if($roomPanjang && $roomLebar && !$roomLuas) {
                                                        $roomLuas = $roomPanjang * $roomLebar;
                                                    }
                                                ?>
                                                <div class="bg-purple-50 border border-purple-200 rounded-lg p-2">
                                                    <div class="flex items-center gap-2 mb-1">
                                                        <div class="w-6 h-6 bg-purple-100 rounded flex items-center justify-center flex-shrink-0">
                                                            <i data-lucide="door-closed" class="w-3 h-3 text-purple-600"></i>
                                                        </div>
                                                        <p class="text-xs text-gray-700 font-medium"><?= htmlspecialchars($roomName) ?></p>
                                                    </div>
                                                    <?php if($roomPanjang && $roomLebar): ?>
                                                    <div class="ml-8 text-[10px] text-gray-500 flex items-center gap-1">
                                                        <i data-lucide="maximize-2" class="w-3 h-3"></i>
                                                        <?= $roomPanjang ?> × <?= $roomLebar ?> m (<?= number_format($roomLuas, 1) ?> m²)
                                                    </div>
                                                    <?php elseif($roomLuas): ?>
                                                    <div class="ml-8 text-[10px] text-gray-500 flex items-center gap-1">
                                                        <i data-lucide="maximize-2" class="w-3 h-3"></i>
                                                        <?= number_format($roomLuas, 1) ?> m²
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        <?php 
                                                endif;
                                            endforeach;
                                        }
                                        ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <?php endforeach; ?>
                        
                        <?php if(empty($daftar_gedung)): ?>
                        <tr>
                            <td colspan="7" class="p-20 text-center">
                                <div class="flex flex-col items-center gap-3">
                                    <i data-lucide="building-2" class="w-16 h-16 text-gray-300"></i>
                                    <p class="text-gray-400 italic">Belum ada gedung terdaftar</p>
                                    <button @click="openTambah()" class="text-[#d13b1f] hover:underline text-sm font-medium">
                                        + Tambah Gedung Pertama
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>
    </main>

    <script>
        function gedungApp() {
            return {
                showForm: false,
                expandedRooms: null,
                expandedTemplate: null,
                form: { 
                    id: '', 
                    nama_gedung: '', 
                    jumlah_lantai: 1, 
                    keterangan: '',
                    daftarRuangan: {}
                },
                
                updateLantaiList() {
                    const jumlahLantai = parseInt(this.form.jumlah_lantai);
                    const newDaftarRuangan = {};
                    
                    for(let i = 1; i <= jumlahLantai; i++) {
                        if(this.form.daftarRuangan[i]) {
                            newDaftarRuangan[i] = this.form.daftarRuangan[i];
                        } else {
                            newDaftarRuangan[i] = [];
                        }
                    }
                    
                    this.form.daftarRuangan = newDaftarRuangan;
                    setTimeout(() => lucide.createIcons(), 100);
                },
                
                addRuangan(lantai) {
                    if(!this.form.daftarRuangan[lantai]) {
                        this.form.daftarRuangan[lantai] = [];
                    }
                    this.form.daftarRuangan[lantai].push({
                        nama: '',
                        panjang: '',
                        lebar: '',
                        luas: 0
                    });
                    setTimeout(() => lucide.createIcons(), 100);
                },
                
                calculateLuas(lantai, index) {
                    const room = this.form.daftarRuangan[lantai][index];
                    const panjang = parseFloat(room.panjang) || 0;
                    const lebar = parseFloat(room.lebar) || 0;
                    room.luas = panjang * lebar;
                },
                
                getLuasDisplay(lantai, index) {
                    const room = this.form.daftarRuangan[lantai][index];
                    const panjang = parseFloat(room.panjang) || 0;
                    const lebar = parseFloat(room.lebar) || 0;
                    const luas = panjang * lebar;
                    
                    if(panjang > 0 && lebar > 0) {
                        return `${panjang.toFixed(1)} × ${lebar.toFixed(1)} m
(${luas.toFixed(1)} m²)`;
                    }
                    return '-';
                },
                
                extractRoomNumber(roomName) {
                    const numbers = roomName.match(/\d+/g);
                    return numbers ? numbers.join('') : '';
                },
                
                removeRuangan(lantai, index) {
                    if(this.form.daftarRuangan[lantai]) {
                        this.form.daftarRuangan[lantai].splice(index, 1);
                    }
                },
                
                validateAndSubmit() {
                    let emptyRoomNames = false;
                    let emptyRoomSizes = false;
                    let duplicateRooms = [];
                    let allRoomNumbers = {};
                    
                    for(let lantai in this.form.daftarRuangan) {
                        if(this.form.daftarRuangan[lantai] && Array.isArray(this.form.daftarRuangan[lantai])) {
                            for(let room of this.form.daftarRuangan[lantai]) {
                                if(!room.nama || room.nama.trim() === '') {
                                    emptyRoomNames = true;
                                }
                                
                                const panjang = parseFloat(room.panjang);
                                const lebar = parseFloat(room.lebar);
                                if(!room.panjang || !room.lebar || panjang <= 0 || lebar <= 0) {
                                    emptyRoomSizes = true;
                                }
                                
                                if(room.nama && room.nama.trim() !== '') {
                                    const roomNumber = this.extractRoomNumber(room.nama.trim());
                                    
                                    if(roomNumber !== '') {
                                        if(allRoomNumbers[roomNumber]) {
                                            if(!duplicateRooms.some(dup => dup.number === roomNumber)) {
                                                duplicateRooms.push({
                                                    number: roomNumber,
                                                    names: [allRoomNumbers[roomNumber], room.nama.trim()]
                                                });
                                            } else {
                                                const dupIndex = duplicateRooms.findIndex(dup => dup.number === roomNumber);
                                                if(!duplicateRooms[dupIndex].names.includes(room.nama.trim())) {
                                                    duplicateRooms[dupIndex].names.push(room.nama.trim());
                                                }
                                            }
                                        } else {
                                            allRoomNumbers[roomNumber] = room.nama.trim();
                                        }
                                    }
                                }
                            }
                        }
                    }
                    
                    if(emptyRoomNames) {
                        alert('⚠️ Peringatan!\n\nSemua nama ruangan harus diisi!\nSilakan lengkapi nama ruangan yang masih kosong.');
                        return false;
                    }
                    
                    if(emptyRoomSizes) {
                        alert('⚠️ Peringatan!\n\nPanjang dan Lebar ruangan harus diisi untuk semua ruangan!\nSilakan lengkapi ukuran ruangan dalam satuan meter (m).');
                        return false;
                    }
                    
                    if(duplicateRooms.length > 0) {
                        let message = '⚠️ Nomor Ruangan Duplikat!\n\nNomor ruangan berikut terdaftar lebih dari sekali:\n\n';
                        duplicateRooms.forEach(dup => {
                            message += `Nomor ${dup.number}:\n`;
                            dup.names.forEach(name => {
                                message += `  • ${name}\n`;
                            });
                            message += '\n';
                        });
                        message += 'Setiap nomor ruangan harus unik di seluruh gedung!\nContoh: "404", "R.404", "Lab 404" dianggap sama karena memiliki nomor 404.';
                        alert(message);
                        return false;
                    }
                    
                    // 🔥 Peringatan khusus saat UPDATE gedung
                    if(this.form.id) {
                        const confirmMsg = '⚠️ PERHATIAN!\n\n' +
                            'Anda sedang mengubah template ruangan.\n' +
                            'Ruangan yang dihapus dari template akan OTOMATIS DIHAPUS dari database, dan akan meghapus data Daftar Ruangan!\n\n' +
                            'Apakah Anda yakin ingin melanjutkan?';
                        
                        if(!confirm(confirmMsg)) {
                            return false;
                        }
                    }
                    
                    document.querySelector('form').submit();
                },
                
                openTambah() {
                    this.form = { 
                        id: '', 
                        nama_gedung: '', 
                        jumlah_lantai: 1, 
                        keterangan: '',
                        daftarRuangan: {1: []}
                    };
                    this.showForm = true;
                    window.scrollTo({top: 0, behavior: 'smooth'});
                    setTimeout(() => lucide.createIcons(), 100);
                },
                
                openEdit(data) {
                    let daftarRuangan = {};
                    try {
                        if(data.daftar_ruangan) {
                            const parsed = JSON.parse(data.daftar_ruangan);
                            for(let lantai in parsed) {
                                if(Array.isArray(parsed[lantai])) {
                                    daftarRuangan[lantai] = parsed[lantai].map(room => {
                                        if(typeof room === 'string') {
                                            return { nama: room, panjang: '', lebar: '', luas: 0 };
                                        } else if(room.ukuran) {
                                            return { nama: room.nama, panjang: '', lebar: '', luas: parseFloat(room.ukuran) || 0 };
                                        } else {
                                            return room;
                                        }
                                    });
                                }
                            }
                        }
                    } catch(e) {
                        console.error('Error parsing daftar_ruangan:', e);
                    }
                    
                    for(let i = 1; i <= data.jumlah_lantai; i++) {
                        if(!daftarRuangan[i]) {
                            daftarRuangan[i] = [];
                        }
                    }
                    
                    this.form = {
                        ...data,
                        daftarRuangan: daftarRuangan
                    };
                    
                    this.showForm = true;
                    window.scrollTo({top: 0, behavior: 'smooth'});
                    setTimeout(() => lucide.createIcons(), 100);
                },
                
                toggleRoomList(gedungId) {
                    this.expandedRooms = this.expandedRooms === gedungId ? null : gedungId;
                    setTimeout(() => lucide.createIcons(), 100);
                },
                
                toggleTemplateList(gedungId) {
                    this.expandedTemplate = this.expandedTemplate === gedungId ? null : gedungId;
                    setTimeout(() => lucide.createIcons(), 100);
                }
            }
        }
        document.addEventListener('DOMContentLoaded', () => lucide.createIcons());
    </script>
</body>
</html>