<?php
session_start();
require "../config/database.php";
require "../auth/auth_helper.php";

cek_kemanan_login($pdo);

$msg = "";

// Tambah/Update Template Sarana
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_template') {
    $id = !empty($_POST['id']) ? $_POST['id'] : null;
    $nama_kategori = trim($_POST['nama_kategori']);
    $jenis_barang = trim($_POST['jenis_barang']);
    $icon = trim($_POST['icon']);
    $keterangan = trim($_POST['keterangan']);
    
    // Ambil daftar tipe barang dari form
    $daftar_tipe_json = $_POST['daftar_tipe'] ?? '[]';

    try {
        // Validasi duplikat nama kategori
        if ($id) {
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM template_sarana WHERE nama_kategori = ? AND id != ?");
            $checkStmt->execute([$nama_kategori, $id]);
        } else {
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM template_sarana WHERE nama_kategori = ?");
            $checkStmt->execute([$nama_kategori]);
        }
        
        $count = $checkStmt->fetchColumn();
        
        if ($count > 0) {
            $msg = "⚠️ Nama kategori '$nama_kategori' sudah terdaftar! Gunakan nama lain.";
        } else {
            if ($id) {
                // 🔥 CASCADE DELETE untuk UPDATE template
                // Ambil template lama
                $stmtOld = $pdo->prepare("SELECT daftar_tipe FROM template_sarana WHERE id = ?");
                $stmtOld->execute([$id]);
                $oldData = $stmtOld->fetch(PDO::FETCH_ASSOC);
                $oldTipeList = !empty($oldData['daftar_tipe']) ? json_decode($oldData['daftar_tipe'], true) : [];
                $newTipeList = json_decode($daftar_tipe_json, true);
                
                // Cari tipe yang dihapus
                $deletedTipes = array_diff($oldTipeList, $newTipeList);
                
                // Hapus unit sarana yang menggunakan tipe yang dihapus
                $deletedCount = 0;
                if(!empty($deletedTipes)) {
                    foreach($deletedTipes as $tipe) {
                        $stmtDelete = $pdo->prepare("DELETE FROM sarana WHERE template_id = ? AND tipe_barang = ?");
                        $stmtDelete->execute([$id, $tipe]);
                        $deletedCount += $stmtDelete->rowCount();
                    }
                }
                
                // Update template
                $stmt = $pdo->prepare("UPDATE template_sarana SET nama_kategori=?, jenis_barang=?, icon=?, keterangan=?, daftar_tipe=?, updated_at=NOW() WHERE id=?");
                $stmt->execute([$nama_kategori, $jenis_barang, $icon, $keterangan, $daftar_tipe_json, $id]);
                
                if($deletedCount > 0) {
                    $msg = "✅ Template sarana berhasil diperbarui! ($deletedCount unit dihapus karena tipe tidak ada di template)";
                } else {
                    $msg = "✅ Template sarana berhasil diperbarui!";
                }
            } else {
                $stmt = $pdo->prepare("INSERT INTO template_sarana (nama_kategori, jenis_barang, icon, keterangan, daftar_tipe, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())");
                $stmt->execute([$nama_kategori, $jenis_barang, $icon, $keterangan, $daftar_tipe_json]);
                $msg = "✅ Template sarana baru berhasil ditambahkan!";
            }
        }
    } catch (PDOException $e) {
        $msg = "❌ Error: " . $e->getMessage();
    }
}

// Hapus Template Sarana
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    try {
        // 🔥 Cek apakah template masih digunakan oleh unit sarana
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM sarana WHERE template_id = ?");
        $checkStmt->execute([$id]);
        $count = $checkStmt->fetchColumn();
        
        if ($count > 0) {
            // ✅ TEMPLATE MASIH DIGUNAKAN - REDIRECT DENGAN PESAN ERROR
            $_SESSION['delete_error'] = "Template tidak dapat dihapus karena masih digunakan oleh $count unit sarana!";
            header("Location: kelola_sarana.php");
            exit;
        } else {
            // ✅ TEMPLATE TIDAK DIGUNAKAN - BOLEH DIHAPUS
            $stmt = $pdo->prepare("DELETE FROM template_sarana WHERE id = ?");
            $stmt->execute([$id]);
            header("Location: kelola_sarana.php?status=deleted");
            exit;
        }
    } catch (PDOException $e) {
        $_SESSION['delete_error'] = "Gagal menghapus: " . $e->getMessage();
        header("Location: kelola_sarana.php");
        exit;
    }
}

// Ambil semua template sarana
$stmt = $pdo->query("SELECT * FROM template_sarana ORDER BY jenis_barang ASC, nama_kategori ASC");
$daftar_template = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../assets/kampusums.png" />
    <title>Kelola Template Sarana</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-gray-50 font-sans flex" x-data="templateSaranaApp()">
    
    <?php include "../components/AdminNavbar.php"; ?>

    <main class="flex-1 lg:ml-60 p-4 md:p-5 mt-16 lg:mt-0 min-h-screen w-full overflow-x-hidden">
        <div class="max-w-7xl mx-auto">
            
            <div class="flex flex-col md:flex-row md:justify-between md:items-end gap-3 md:gap-4 mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800">Kelola Template Sarana</h1>
                    <p class="text-gray-500 mt-1">Manajemen kategori dan tipe barang untuk registrasi unit.</p>
                </div>
                <div class="flex gap-3">
                    <a href="sarana.php" class="flex items-center gap-2 bg-gray-600 hover:bg-gray-700 text-white px-5 py-2.5 rounded-xl font-medium shadow-md transition-all">
                        <i data-lucide="arrow-left"></i> Kembali ke Sarana
                    </a>
                    <button @click="openTambah()" class="flex items-center gap-2 bg-[#d13b1f] hover:bg-[#b53118] text-white px-5 py-2.5 rounded-xl font-medium shadow-md transition-all">
                        <i data-lucide="plus"></i> Tambah Template
                    </button>
                </div>
            </div>

            <?php 
            // Cek pesan error dari session
            if(isset($_SESSION['delete_error'])) {
                $msg = "⚠️ " . $_SESSION['delete_error'];
                unset($_SESSION['delete_error']);
            }
            
            if($msg || isset($_GET['status'])): 
            ?>
            <div class="<?= strpos($msg, '⚠️') !== false || strpos($msg, '❌') !== false ? 'bg-red-100 border-red-200 text-red-700' : 'bg-green-100 border-green-200 text-green-700' ?> border px-4 py-3 rounded-xl mb-6 flex items-center justify-between shadow-sm">
                <div class="flex items-center gap-2 font-medium">
                    <i data-lucide="<?= strpos($msg, '⚠️') !== false || strpos($msg, '❌') !== false ? 'alert-circle' : 'check-circle' ?>" size="18"></i> 
                    <?= $msg ?: "Template sarana berhasil dihapus!" ?>
                </div>
                <button type="button" onclick="window.location.href='kelola_sarana.php'"><i data-lucide="x" size="16"></i></button>
            </div>
            <?php endif; ?>

            <!-- Form -->
            <div x-show="showForm" x-transition x-cloak class="relative bg-black/5 rounded-2xl shadow-xl border border-black/10 p-6 mb-8">
                <!-- Close Button -->
                <button type="button" 
                        @click="showForm = false"
                        class="absolute top-4 right-4 w-10 h-10 flex items-center justify-center bg-white hover:bg-red-50 text-gray-400 hover:text-red-500 rounded-xl border border-gray-200 hover:border-red-300 transition-all shadow-sm hover:shadow-md z-10 group">
                    <i data-lucide="x" class="w-5 h-5 group-hover:scale-110 transition-transform"></i>
                </button>

                <form method="POST" @submit.prevent="validateAndSubmit()">
                    <input type="hidden" name="action" value="save_template">
                    <input type="hidden" name="id" x-model="form.id">
                    <input type="hidden" name="daftar_tipe" :value="JSON.stringify(form.daftarTipe)">
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-6">
                        <div>
                            <label class="text-[10px] font-bold text-gray-700 uppercase">Nama Kategori <span class="text-red-500">*</span></label>
                            <input type="text" name="nama_kategori" x-model="form.nama_kategori" required 
                                   class="w-full mt-1 px-3 py-2.5 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-1 focus:ring-[#d13b1f] focus:border-[#d13b1f]"
                                   placeholder="Contoh: Kursi">
                            <p class="text-[11px] text-gray-500 mt-1 italic">Nama harus unik</p>
                        </div>
                        
                        <div>
                            <label class="text-[10px] font-bold text-gray-700 uppercase">Jenis Barang <span class="text-red-500">*</span></label>
                            <select name="jenis_barang" x-model="form.jenis_barang" required
                                    class="w-full mt-1 px-3 py-2.5 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-1 focus:ring-[#d13b1f] focus:border-[#d13b1f]">
                                <option value="">Pilih Jenis</option>
                                <option value="Furniture">Furniture</option>
                                <option value="Elektronik">Elektronik</option>
                                <option value="Peralatan Umum">Peralatan Umum</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="text-[10px] font-bold text-gray-700 uppercase">Icon (Lucide) <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <input type="text" name="icon" x-model="form.icon" required 
                                       @input="refreshIcons()"
                                       class="w-full mt-1 px-3 py-2.5 pr-12 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-1 focus:ring-[#d13b1f] focus:border-[#d13b1f]"
                                       placeholder="armchair">
                                <div class="absolute right-3 top-1/2 -translate-y-1/2 mt-0.5 w-8 h-8 bg-gray-100 rounded-lg flex items-center justify-center">
                                    <i :data-lucide="form.icon || 'help-circle'" class="w-4 h-4 text-gray-600"></i>
                                </div>
                            </div>
                            <p class="text-[11px] text-gray-500 mt-1">
                                <a href="https://lucide.dev/icons/" target="_blank" class="text-blue-600 hover:underline">Lihat daftar icon →</a>
                            </p>
                        </div>
                        
                        <div class="md:col-span-3">
                            <label class="text-[10px] font-bold text-gray-700 uppercase">Keterangan (Opsional)</label>
                            <textarea name="keterangan" x-model="form.keterangan" rows="2" 
                                      class="w-full mt-1 px-3 py-2.5 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-1 focus:ring-[#d13b1f] focus:border-[#d13b1f]"
                                      placeholder="Contoh: Untuk ruang kuliah dan kantor..."></textarea>
                        </div>
                    </div>

                    <!-- Daftar Tipe Barang -->
                    <div class="border-t pt-6">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-2">
                                <i data-lucide="list" class="w-5 h-5 text-[#d13b1f]"></i>
                                <h3 class="text-sm font-bold text-gray-700 uppercase">Daftar Tipe / Varian</h3>
                            </div>
                            <button type="button" 
                                    @click="addTipe()"
                                    class="text-xs bg-[#d13b1f] text-white px-4 py-2 rounded-lg hover:bg-[#b53118] transition-all flex items-center gap-1">
                                <i data-lucide="plus" class="w-3 h-3"></i>
                                Tambah Tipe
                            </button>
                        </div>
                        
                        <div class="space-y-3">
                            <template x-if="form.daftarTipe.length === 0">
                                <div class="bg-gray-50 rounded-xl p-8 text-center border-2 border-dashed border-gray-200">
                                    <i data-lucide="package-open" class="w-12 h-12 text-gray-300 mx-auto mb-3"></i>
                                    <p class="text-sm text-gray-400 italic mb-3">Belum ada tipe barang</p>
                                    <button type="button" 
                                            @click="addTipe()"
                                            class="text-sm bg-gray-200 text-gray-600 px-4 py-2 rounded-lg hover:bg-gray-300 transition-all inline-flex items-center gap-2">
                                        <i data-lucide="plus" class="w-4 h-4"></i>
                                        Tambah Tipe Pertama
                                    </button>
                                </div>
                            </template>
                            
                            <template x-for="(tipe, index) in form.daftarTipe" :key="index">
                                <div class="bg-gradient-to-r from-gray-50 to-white rounded-xl p-4 border border-gray-200 hover:border-[#d13b1f] transition-all">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 bg-[#d13b1f]/10 rounded-lg flex items-center justify-center flex-shrink-0">
                                            <span class="text-sm font-bold text-[#d13b1f]" x-text="index + 1"></span>
                                        </div>
                                        <input type="text" 
                                               x-model="form.daftarTipe[index]"
                                               placeholder="Contoh: Kursi Mahasiswa, Kursi + Meja, Kursi Dekan..."
                                               class="flex-1 px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-[#d13b1f] focus:border-[#d13b1f]">
                                        <button type="button" 
                                                @click="removeTipe(index)"
                                                class="text-red-400 hover:bg-red-50 p-2 rounded-lg transition-colors flex-shrink-0">
                                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>
                        
                        <div class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                            <div class="flex items-start gap-2">
                                <i data-lucide="info" class="w-4 h-4 text-blue-600 mt-0.5 flex-shrink-0"></i>
                                <div class="text-xs text-blue-700">
                                    <p class="font-semibold mb-1">Informasi Template:</p>
                                    <ul class="list-disc list-inside space-y-1 text-[11px]">
                                        <li>Template ini akan muncul sebagai pilihan dropdown saat registrasi unit baru di halaman Sarana</li>
                                        <li>Setiap tipe akan ditampilkan sesuai dengan nama kategori yang dipilih</li>
                                        <li>Contoh: Kategori "Kursi" → Tipe: "Kursi Mahasiswa", "Kursi Dosen", "Kursi Dekan"</li>
                                        <li>Nama tipe harus jelas dan deskriptif agar mudah dipilih saat registrasi</li>
                                        <li class="font-bold text-red-600">⚠️ Menghapus tipe dari template akan otomatis menghapus unit sarana dengan tipe tersebut!</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 mt-6 pt-4 border-t">
                        <button type="button" @click="showForm = false" class="text-gray-400 font-bold text-xs px-6 uppercase tracking-widest">Batal</button>
                        <button type="submit" class="bg-[#d13b1f] text-white px-10 py-2.5 rounded-xl font-bold shadow-lg uppercase tracking-tight">Simpan Template</button>
                    </div>
                </form>
            </div>

            <!-- Tabel Template Sarana -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="p-6 border-b border-gray-50">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-sm font-bold text-gray-700 uppercase">Daftar Template Sarana</h2>
                            <p class="text-xs text-gray-500 mt-1">Total <?= count($daftar_template) ?> kategori template</p>
                        </div>
                    </div>
                </div>

                <?php if(!empty($daftar_template)): ?>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 sticky top-0 z-10">
                            <tr>
                                <th class="px-6 py-4 text-left text-[10px] font-black text-gray-400 uppercase tracking-wider border-b">Kategori</th>
                                <th class="px-6 py-4 text-left text-[10px] font-black text-gray-400 uppercase tracking-wider border-b">Jenis Barang</th>
                                <th class="px-6 py-4 text-left text-[10px] font-black text-gray-400 uppercase tracking-wider border-b">Daftar Tipe</th>
                                <th class="px-6 py-4 text-left text-[10px] font-black text-gray-400 uppercase tracking-wider border-b">Keterangan</th>
                                <th class="px-6 py-4 text-center text-[10px] font-black text-gray-400 uppercase tracking-wider border-b">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php foreach($daftar_template as $template): 
                                $daftar_tipe = !empty($template['daftar_tipe']) ? json_decode($template['daftar_tipe'], true) : [];
                                $jumlah_tipe = is_array($daftar_tipe) ? count($daftar_tipe) : 0;
                                
                                $badge_colors = [
                                    'Furniture' => 'bg-blue-100 text-blue-700 border-blue-300',
                                    'Elektronik' => 'bg-green-100 text-green-700 border-green-300',
                                    'Peralatan Umum' => 'bg-purple-100 text-purple-700 border-purple-300'
                                ];
                                $badge_color = $badge_colors[$template['jenis_barang']] ?? 'bg-gray-100 text-gray-700 border-gray-300';
                                
                                $jenis_icons = [
                                    'Furniture' => 'armchair',
                                    'Elektronik' => 'monitor',
                                    'Peralatan Umum' => 'wrench'
                                ];
                                $jenis_icon = $jenis_icons[$template['jenis_barang']] ?? 'package';
                            ?>
                            <tr class="hover:bg-gray-50/80 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-12 h-12 bg-gradient-to-br from-gray-100 to-gray-200 rounded-xl flex items-center justify-center shadow-sm">
                                            <i data-lucide="<?= htmlspecialchars($template['icon']) ?>" class="w-6 h-6 text-[#d13b1f]"></i>
                                        </div>
                                        <div>
                                            <p class="font-bold text-gray-800 text-sm"><?= htmlspecialchars($template['nama_kategori']) ?></p>
                                            <p class="text-[10px] text-gray-400 uppercase font-mono tracking-wide">ID: <?= $template['id'] ?></p>
                                        </div>
                                    </div>
                                </td>
                                
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center gap-2 px-3 py-1.5 <?= $badge_color ?> border-2 rounded-lg text-xs font-bold">
                                        <i data-lucide="<?= $jenis_icon ?>" class="w-4 h-4"></i>
                                        <?= htmlspecialchars($template['jenis_barang']) ?>
                                    </span>
                                </td>
                                
                                <td class="px-6 py-4">
                                    <?php if($jumlah_tipe > 0): ?>
                                    <div class="flex items-center gap-2">
                                        <button type="button"
                                                @click="toggleTipeList('<?= $template['id'] ?>')"
                                                class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-purple-50 text-purple-700 border border-purple-200 rounded-lg text-xs font-bold hover:bg-purple-100 transition-all">
                                            <i data-lucide="list" class="w-3 h-3"></i>
                                            <?= $jumlah_tipe ?> Tipe
                                            <i data-lucide="chevron-down" class="w-3 h-3 transition-transform" :class="{'rotate-180': expandedTipe === '<?= $template['id'] ?>'}"></i>
                                        </button>
                                    </div>
                                    
                                    <div x-show="expandedTipe === '<?= $template['id'] ?>'" 
                                         x-transition
                                         x-cloak
                                         class="mt-3 space-y-1.5">
                                        <?php foreach($daftar_tipe as $index => $tipe): ?>
                                        <div class="flex items-center gap-2 text-xs bg-white border-2 border-purple-200 rounded-lg px-3 py-2 shadow-sm">
                                            <div class="w-6 h-6 bg-purple-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                                <span class="text-[10px] font-bold text-purple-700"><?= $index + 1 ?></span>
                                            </div>
                                            <span class="text-gray-700 font-medium"><?= htmlspecialchars($tipe) ?></span>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php else: ?>
                                    <span class="text-xs text-gray-400 italic">Belum ada tipe</span>
                                    <?php endif; ?>
                                </td>
                                
                                <td class="px-6 py-4">
                                    <p class="text-xs text-gray-600 line-clamp-2 max-w-xs">
                                        <?= !empty($template['keterangan']) ? htmlspecialchars($template['keterangan']) : '<span class="italic text-gray-400">Tidak ada keterangan</span>' ?>
                                    </p>
                                </td>
                                
                                <td class="px-6 py-4">
                                    <div class="flex items-center justify-center gap-2">
                                        <button @click='openEdit(<?= json_encode($template) ?>)' 
                                                class="text-blue-500 hover:bg-blue-50 p-2 rounded-lg transition-colors"
                                                title="Edit Template">
                                            <i data-lucide="edit-3" class="w-4 h-4"></i>
                                        </button>
                                        <button onclick="confirmDeleteTemplate(<?= $template['id'] ?>, '<?= htmlspecialchars($template['nama_kategori']) ?>')" 
                                               class="text-red-400 hover:bg-red-50 p-2 rounded-lg transition-colors"
                                               title="Hapus Template">
                                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="p-20 text-center">
                    <i data-lucide="package-open" class="w-20 h-20 text-gray-300 mx-auto mb-4"></i>
                    <p class="text-gray-400 italic mb-4">Belum ada template sarana terdaftar</p>
                    <button @click="openTambah()" class="text-[#d13b1f] hover:underline text-sm font-medium">
                        + Tambah Template Pertama
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        function confirmDeleteTemplate(id, namaKategori) {
            Swal.fire({
                title: 'Apakah Anda yakin?',
                html: `Anda akan menghapus template:<br><strong>${namaKategori}</strong><br><br><span class="text-red-600 text-sm">⚠️ Template tidak dapat dihapus jika masih digunakan oleh unit sarana!</span>`,
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
                    window.location.href = `kelola_sarana.php?delete=${id}`;
                }
            });
        }

        function templateSaranaApp() {
            return {
                showForm: false,
                expandedTipe: null,
                form: { 
                    id: '', 
                    nama_kategori: '', 
                    jenis_barang: '',
                    icon: '',
                    keterangan: '',
                    daftarTipe: []
                },
                
                addTipe() {
                    this.form.daftarTipe.push('');
                    setTimeout(() => lucide.createIcons(), 100);
                },
                
                removeTipe(index) {
                    this.form.daftarTipe.splice(index, 1);
                },
                
                validateAndSubmit() {
                    let emptyTipe = false;
                    let duplicateTipe = [];
                    let tipeSet = new Set();
                    
                    for(let tipe of this.form.daftarTipe) {
                        const trimmedTipe = tipe.trim();
                        
                        if(trimmedTipe === '') {
                            emptyTipe = true;
                        }
                        
                        const lowerTipe = trimmedTipe.toLowerCase();
                        if(tipeSet.has(lowerTipe)) {
                            if(!duplicateTipe.includes(trimmedTipe)) {
                                duplicateTipe.push(trimmedTipe);
                            }
                        } else {
                            tipeSet.add(lowerTipe);
                        }
                    }
                    
                    if(emptyTipe) {
                        Swal.fire({
                            title: 'Peringatan!',
                            text: 'Semua tipe barang harus diisi! Hapus tipe yang kosong atau isi dengan nama tipe.',
                            icon: 'warning',
                            confirmButtonColor: '#d13b1f',
                            confirmButtonText: 'OK'
                        });
                        return false;
                    }
                    
                    if(duplicateTipe.length > 0) {
                        Swal.fire({
                            title: 'Tipe Duplikat!',
                            html: `Tipe berikut terdaftar lebih dari sekali:<br><br><strong>${duplicateTipe.join(', ')}</strong><br><br>Setiap tipe harus unik!`,
                            icon: 'warning',
                            confirmButtonColor: '#d13b1f',
                            confirmButtonText: 'OK'
                        });
                        return false;
                    }
                    
                    // Warning khusus untuk UPDATE template
                    if(this.form.id) {
                        Swal.fire({
                            title: '⚠️ PERHATIAN!',
                            html: 'Anda sedang mengubah template sarana.<br><br>Tipe yang dihapus dari template akan <strong>OTOMATIS MENGHAPUS</strong> unit sarana dengan tipe tersebut!<br><br>Apakah Anda yakin ingin melanjutkan?',
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#d13b1f',
                            cancelButtonColor: '#6b7280',
                            confirmButtonText: 'Ya, Lanjutkan!',
                            cancelButtonText: 'Batal',
                            reverseButtons: true
                        }).then((result) => {
                            if (result.isConfirmed) {
                                document.querySelector('form').submit();
                            }
                        });
                        return false;
                    }
                    
                    document.querySelector('form').submit();
                },
                
                openTambah() {
                    this.form = { 
                        id: '', 
                        nama_kategori: '', 
                        jenis_barang: '',
                        icon: '',
                        keterangan: '',
                        daftarTipe: []
                    };
                    this.showForm = true;
                    window.scrollTo({top: 0, behavior: 'smooth'});
                    setTimeout(() => lucide.createIcons(), 100);
                },
                
                openEdit(data) {
                    let daftarTipe = [];
                    try {
                        if(data.daftar_tipe) {
                            daftarTipe = JSON.parse(data.daftar_tipe);
                        }
                    } catch(e) {
                        console.error('Error parsing daftar_tipe:', e);
                    }
                    
                    this.form = {
                        id: data.id,
                        nama_kategori: data.nama_kategori,
                        jenis_barang: data.jenis_barang,
                        icon: data.icon,
                        keterangan: data.keterangan || '',
                        daftarTipe: Array.isArray(daftarTipe) ? daftarTipe : []
                    };
                    
                    this.showForm = true;
                    window.scrollTo({top: 0, behavior: 'smooth'});
                    setTimeout(() => lucide.createIcons(), 100);
                },
                
                toggleTipeList(templateId) {
                    this.expandedTipe = this.expandedTipe === templateId ? null : templateId;
                    setTimeout(() => lucide.createIcons(), 100);
                },
                
                refreshIcons() {
                    setTimeout(() => lucide.createIcons(), 50);
                }
            }
        }
        document.addEventListener('DOMContentLoaded', () => lucide.createIcons());
    </script>
</body>
</html>