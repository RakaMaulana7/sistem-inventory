<?php
session_start();
require "../config/database.php";
require "../auth/auth_helper.php";

// 1. PROTEKSI & KEAMANAN
cek_kemanan_login($pdo);

if (!isset($_SESSION['login']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// 2. LOGIKA PROSES (Create, Update, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $nama = $_POST['nama'];
    $plat = strtoupper($_POST['plat']);
    $jenis_bensin = $_POST['jenis_bensin'] ?? '';
    $kondisi_dalam = $_POST['kondisi_dalam'] ?? '';
    $lokasi = $_POST['lokasi'] ?? 'Parkiran Kampus';
    
    // Ambil data foto (Base64 dari Canvas JS)
    $fDepan = $_POST['fotoDepan'] ?? '';
    $fDalam = $_POST['fotoDalam'] ?? '';
    $fKanan = $_POST['fotoKanan'] ?? '';
    $fKiri  = $_POST['fotoKiri'] ?? '';
    $fBelakang = $_POST['fotoBelakang'] ?? '';
    $fSpeed = $_POST['fotoSpeedometer'] ?? '';

    if ($action === 'add') {
        $sql = "INSERT INTO transportasi (nama, plat, jenis_bensin, kondisi_dalam, lokasi, fotoDepan, fotoDalam, fotoKanan, fotoKiri, fotoBelakang, fotoSpeedometer) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nama, $plat, $jenis_bensin, $kondisi_dalam, $lokasi, $fDepan, $fDalam, $fKanan, $fKiri, $fBelakang, $fSpeed]);
        header("Location: transportasi.php?msg=success"); exit;
    } elseif ($action === 'edit') {
        $id = $_POST['id'];
        $sql = "UPDATE transportasi SET nama=?, plat=?, jenis_bensin=?, kondisi_dalam=?, lokasi=?, fotoDepan=?, fotoDalam=?, fotoKanan=?, fotoKiri=?, fotoBelakang=?, fotoSpeedometer=? WHERE id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nama, $plat, $jenis_bensin, $kondisi_dalam, $lokasi, $fDepan, $fDalam, $fKanan, $fKiri, $fBelakang, $fSpeed, $id]);
        header("Location: transportasi.php?msg=updated"); exit;
    }
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM transportasi WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: transportasi.php?msg=deleted"); exit;
}

// 3. LOGIKA PAGINASI & SEARCH
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;
$search = $_GET['search'] ?? '';

$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM transportasi WHERE nama LIKE ? OR plat LIKE ?");
$stmtCount->execute(["%$search%", "%$search%"]);
$totalRows = $stmtCount->fetchColumn();
$totalPages = ceil($totalRows / $limit);

$stmt = $pdo->prepare("SELECT * FROM transportasi WHERE nama LIKE ? OR plat LIKE ? ORDER BY id DESC LIMIT $limit OFFSET $offset");
$stmt->execute(["%$search%", "%$search%"]);
$vehicles = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../assets/kampusums.png" />
    <title>Kelola Transportasi | Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }
        .line-clamp-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    </style>
</head>
<body class="bg-gray-50 font-sans flex" x-data="transportasiApp()">

    <?php include "../components/AdminNavbar.php"; ?>

    <main class="flex-1 lg:ml-60 p-4 md:p-5 mt-16 lg:mt-0 min-h-screen w-full overflow-x-hidden">
        <div class="max-w-7xl mx-auto">
            
            <div class="flex flex-col md:flex-row justify-between items-start md:items-end mb-8 gap-4">
                <div>
                    <h1 class="text-2xl md:text-3xl font-bold text-gray-800">Manajemen Transportasi</h1>
                    <p class="text-sm text-gray-500 mt-1">Registrasi dan monitoring kelayakan armada unit.</p>
                </div>
                <div class="flex flex-col sm:flex-row gap-3 w-full md:w-auto">
                    <form method="GET" class="relative w-full sm:w-64">
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Cari plat atau nama..." class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-200 focus:ring-2 focus:ring-[#d13b1f] outline-none text-sm">
                        <i data-lucide="search" class="absolute left-3 top-3 w-4 h-4 text-gray-400"></i>
                    </form>
                    <button @click="resetForm()" class="flex items-center justify-center gap-2 bg-[#d13b1f] hover:bg-[#b53118] text-white px-5 py-2.5 rounded-xl font-medium shadow-md transition-all text-sm">
                        <i data-lucide="plus" class="w-4 h-4"></i> Tambah Armada
                    </button>
                </div>
            </div>

            <div x-show="showForm" x-transition x-cloak class="bg-white border border-gray-200 rounded-2xl shadow-sm p-4 md:p-6 mb-8">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="font-bold text-gray-800 flex items-center gap-2 text-lg">
                        <i data-lucide="car" class="text-[#d13b1f]"></i>
                        <span x-text="editingData ? 'Edit Data Kendaraan' : 'Registrasi Armada Baru'"></span>
                    </h3>
                    <button @click="showForm = false" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="w-5 h-5"></i></button>
                </div>

                <div class="mb-6 bg-blue-50 border-l-4 border-blue-500 p-4 rounded-r-xl">
                    <p class="text-xs text-blue-700 leading-relaxed">
                        <strong>Panduan Pendataan:</strong> Pastikan Nomor Plat sesuai STNK. Pada kolom <strong>Kondisi Dalam</strong>, informasikan fasilitas yang tersedia (misal: Tempat sampah, AC, P3K, Charger) untuk memudahkan operasional peminjaman.
                    </p>
                </div>

                <form action="" method="POST">
                    <input type="hidden" name="action" :value="editingData ? 'edit' : 'add'">
                    <input type="hidden" name="id" x-model="formData.id">
                    
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                        <div class="space-y-4">
                            <div>
                                <label class="text-xs font-bold text-gray-500 uppercase">Nama Kendaraan</label>
                                <input type="text" name="nama" x-model="formData.nama" placeholder="Contoh: Toyota Hiace" class="w-full border p-3 rounded-xl focus:ring-2 focus:ring-[#d13b1f] outline-none mt-1 text-sm" required>
                            </div>
                            <div>
                                <label class="text-xs font-bold text-gray-500 uppercase">Nomor Plat</label>
                                <input type="text" name="plat" x-model="formData.plat" placeholder="L 1234 ABC" class="w-full border p-3 rounded-xl focus:ring-2 focus:ring-[#d13b1f] outline-none mt-1 uppercase text-sm font-mono" required>
                            </div>
                            <div>
                                <label class="text-xs font-bold text-gray-500 uppercase flex items-center gap-1">
                                    <i data-lucide="fuel" class="w-3 h-3"></i> Jenis Bahan Bakar
                                </label>
                                <select name="jenis_bensin" x-model="formData.jenis_bensin" class="w-full border p-3 rounded-xl focus:ring-2 focus:ring-[#d13b1f] outline-none mt-1 text-sm" required>
                                    <option value="">Pilih Jenis Bensin</option>
                                    <option value="Pertalite">Pertalite</option>
                                    <option value="Pertamax">Pertamax</option>
                                    <option value="Pertamax Turbo">Pertamax Turbo</option>
                                    <option value="Dexlite">Dexlite (Solar)</option>
                                    <option value="Pertamina Dex">Pertamina Dex (Solar)</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-xs font-bold text-gray-500 uppercase flex items-center gap-1">
                                    <i data-lucide="map-pin" class="w-3 h-3"></i> Lokasi Parkir
                                </label>
                                <input type="text" name="lokasi" x-model="formData.lokasi" placeholder="Contoh: Parkiran Gedung A" class="w-full border p-3 rounded-xl focus:ring-2 focus:ring-[#d13b1f] outline-none mt-1 text-sm">
                                <p class="text-[10px] text-gray-400 mt-1">Lokasi default: Parkiran Kampus</p>
                            </div>
                            <div>
                                <label class="text-xs font-bold text-gray-500 uppercase flex items-center gap-1">
                                    <i data-lucide="clipboard-list" class="w-3 h-3"></i> Fasilitas & Kondisi Interior
                                </label>
                                <textarea name="kondisi_dalam" x-model="formData.kondisi_dalam" rows="3" class="w-full border p-3 rounded-xl focus:ring-2 focus:ring-[#d13b1f] outline-none mt-1 text-sm resize-none" placeholder="Sebutkan: Tempat sampah, AC, jenis jok, ketersediaan P3K/APAR..."></textarea>
                            </div>
                        </div>

                        <div class="lg:col-span-2 bg-gray-50 p-4 rounded-xl border border-dashed border-gray-300">
                            <p class="text-[10px] font-bold text-gray-400 uppercase mb-4 text-center">Dokumentasi Visual (6 Sudut Pandang)</p>
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                                <template x-for="item in uploadFields" :key="item.name">
                                    <div class="text-center">
                                        <div @click="document.getElementById('file-' + item.name).click()" class="h-24 md:h-28 w-full bg-white border-2 rounded-xl flex items-center justify-center cursor-pointer hover:border-[#d13b1f] transition-all overflow-hidden relative group shadow-sm">
                                            <template x-if="formData[item.name]">
                                                <img :src="formData[item.name]" class="w-full h-full object-cover">
                                            </template>
                                            <template x-if="!formData[item.name]">
                                                <div class="flex flex-col items-center gap-1">
                                                    <i :data-lucide="item.icon" class="w-6 h-6 text-gray-300"></i>
                                                    <span class="text-[8px] text-gray-400">Pilih Foto</span>
                                                </div>
                                            </template>
                                            <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity">
                                                <i data-lucide="upload" class="w-5 h-5 text-white"></i>
                                            </div>
                                        </div>
                                        <span class="text-[10px] text-gray-600 mt-2 block font-semibold uppercase tracking-wider" x-text="item.label"></span>
                                        <input type="hidden" :name="item.name" x-model="formData[item.name]">
                                        <input type="file" :id="'file-' + item.name" @change="handleFileUpload($event, item.name)" class="hidden" accept="image/*">
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col-reverse sm:flex-row justify-end gap-3 pt-4 border-t">
                        <button type="button" @click="showForm = false" class="text-gray-500 font-medium px-4 py-2 text-sm hover:underline">Batal</button>
                        <button type="submit" class="bg-[#d13b1f] text-white px-10 py-3 rounded-xl font-bold shadow-lg hover:bg-[#b53118] transition-all text-sm">
                            <span x-text="editingData ? 'Simpan Perubahan' : 'Daftarkan Kendaraan'"></span>
                        </button>
                    </div>
                </form>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left min-w-[1000px]">
                        <thead class="bg-gray-50 text-gray-400 text-[10px] uppercase tracking-widest font-bold border-b">
                            <tr>
                                <th class="p-5">Unit Kendaraan</th>
                                <th class="p-5">Plat Nomor</th>
                                <th class="p-5">BBM</th>
                                <th class="p-5">Lokasi</th>
                                <th class="p-5">Kondisi Interior</th>
                                <th class="p-5">Dokumentasi</th>
                                <th class="p-5 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if(empty($vehicles)): ?>
                                <tr><td colspan="7" class="p-10 text-center text-gray-400 italic">Belum ada data kendaraan.</td></tr>
                            <?php endif; ?>
                            <?php foreach($vehicles as $v): ?>
                            <tr class="hover:bg-gray-50/50 transition-colors">
                                <td class="p-5">
                                    <div class="flex items-center gap-4">
                                        <div class="w-12 h-12 rounded-xl border bg-gray-100 shrink-0 overflow-hidden shadow-sm">
                                            <img src="<?= $v['fotoDepan'] ?: 'https://placehold.co/100x100?text=No+Img' ?>" class="w-full h-full object-cover">
                                        </div>
                                        <span class="font-bold text-gray-800"><?= htmlspecialchars($v['nama']) ?></span>
                                    </div>
                                </td>
                                <td class="p-5">
                                    <span class="font-mono text-xs bg-orange-50 text-[#d13b1f] px-3 py-1.5 rounded-lg border border-orange-100 font-bold"><?= htmlspecialchars($v['plat']) ?></span>
                                </td>
                                <td class="p-5">
                                    <span class="inline-flex items-center gap-1.5 text-xs bg-blue-50 text-blue-700 px-3 py-1 rounded-lg font-semibold">
                                        <i data-lucide="fuel" class="w-3 h-3"></i> <?= htmlspecialchars($v['jenis_bensin'] ?: '-') ?>
                                    </span>
                                </td>
                                <td class="p-5">
                                    <div class="flex items-center gap-2">
                                        <i data-lucide="map-pin" class="w-4 h-4 text-green-600"></i>
                                        <span class="text-sm text-gray-700"><?= htmlspecialchars($v['lokasi'] ?: 'Parkiran Kampus') ?></span>
                                    </div>
                                </td>
                                <td class="p-5">
                                    <div class="flex flex-wrap gap-1.5 mb-2">
                                        <?php if(!empty($v['kondisi_dalam'])) {
                                            $parts = array_map('trim', explode(',', $v['kondisi_dalam']));
                                            foreach($parts as $p) { ?>
                                                <button type="button" @click.stop="openKondisiModal(<?= json_encode($v['kondisi_dalam']) ?>, <?= json_encode($p) ?>)" class="px-2 py-0.5 bg-gray-100 text-gray-600 text-[10px] font-bold rounded border border-gray-200 hover:bg-[#d13b1f] hover:text-white hover:border-[#d13b1f] transition-all cursor-pointer" tabindex="0">
                                                    <?= htmlspecialchars($p) ?>
                                                </button>
                                            <?php } 
                                        } else { 
                                            echo '<span class="text-xs text-gray-400 italic">Tidak ada deskripsi</span>'; 
                                        } ?>
                                    </div>
                                </td>
                                <td class="p-5">
                                    <div class="flex -space-x-2">
                                        <?php 
                                        $fotos = ['fotoDepan', 'fotoDalam', 'fotoKanan', 'fotoKiri', 'fotoBelakang', 'fotoSpeedometer'];
                                        foreach($fotos as $f): 
                                            if(!empty($v[$f])): ?>
                                                <div class="w-7 h-7 rounded-full border-2 border-white bg-gray-200 overflow-hidden shadow-sm">
                                                    <img src="<?= $v[$f] ?>" class="w-full h-full object-cover">
                                                </div>
                                            <?php endif; 
                                        endforeach; ?>
                                    </div>
                                </td>
                                <td class="p-5 text-right">
                                    <div class="flex justify-end gap-2">
                                        <button @click="editData(<?= htmlspecialchars(json_encode($v)) ?>)" class="p-2.5 text-blue-600 bg-blue-50 rounded-xl hover:bg-blue-100 transition-all shadow-sm"><i data-lucide="edit-3" class="w-4 h-4"></i></button>
                                        <a href="?delete=<?= $v['id'] ?>" onclick="return confirm('Hapus armada ini dari sistem?')" class="p-2.5 text-red-600 bg-red-50 rounded-xl hover:bg-red-100 transition-all shadow-sm"><i data-lucide="trash-2" class="w-4 h-4"></i></a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Modal: Detail Kondisi Interior -->
            <div x-show="showKondisiModal" x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" x-cloak>
                <div @click.stop class="bg-white rounded-2xl shadow-2xl w-full max-w-xl p-6">
                    <div class="flex items-start justify-between mb-4">
                        <div>
                            <h3 class="text-lg font-bold text-gray-800">Detail Kondisi: <span x-text="modalKondisiData.label"></span></h3>
                            <p class="text-xs text-gray-500 mt-1">Daftar barang / fasilitas yang tercatat pada kondisi interior.</p>
                        </div>
                        <button @click="showKondisiModal = false" class="text-gray-400 hover:text-gray-600">
                            <i data-lucide="x" class="w-5 h-5"></i>
                        </button>
                    </div>

                    <div class="space-y-3 max-h-[60vh] overflow-y-auto">
                        <template x-for="(it, idx) in modalKondisiData.items" :key="idx">
                            <div class="p-3 border rounded-lg flex items-center justify-between">
                                <div class="text-sm text-gray-700" x-text="it"></div>
                                <div class="text-xs text-gray-400">#<span x-text="idx + 1"></span></div>
                            </div>
                        </template>
                        <div x-show="modalKondisiData.items.length === 0" class="text-sm text-gray-500 italic">Tidak ada detail untuk item ini.</div>
                    </div>
                </div>
            </div>

            <?php if ($totalPages > 1): ?>
            <div class="mt-8 flex justify-center items-center gap-2">
                <?php for($i=1; $i<=$totalPages; $i++): ?>
                    <a href="?page=<?= $i ?>&search=<?= $search ?>" class="w-10 h-10 flex items-center justify-center rounded-xl text-sm <?= $page == $i ? 'bg-[#d13b1f] text-white shadow-md' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50' ?> font-bold transition-all">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>

        </div>
    </main>

    <script>
        function transportasiApp() {
            return {
                showForm: false,
                editingData: null,
                formData: {
                    id: '', nama: '', plat: '', jenis_bensin: '', kondisi_dalam: '', lokasi: '',
                    fotoDepan: '', fotoDalam: '', fotoKanan: '',
                    fotoKiri: '', fotoBelakang: '', fotoSpeedometer: ''
                },
                uploadFields: [
                    { name: 'fotoDepan', label: 'Tampak Depan', icon: 'image' },
                    { name: 'fotoDalam', label: 'Interior Dalam', icon: 'armchair' },
                    { name: 'fotoKanan', label: 'Sisi Kanan', icon: 'arrow-right' },
                    { name: 'fotoKiri', label: 'Sisi Kiri', icon: 'arrow-left' },
                    { name: 'fotoBelakang', label: 'Tampak Belakang', icon: 'package' },
                    { name: 'fotoSpeedometer', label: 'Speedometer', icon: 'gauge' }
                ],
                handleFileUpload(event, fieldName) {
                    const file = event.target.files[0];
                    if (!file) return;

                    const reader = new FileReader();
                    reader.onload = (e) => {
                        const img = new Image();
                        img.onload = () => {
                            const canvas = document.createElement('canvas');
                            const MAX_WIDTH = 800;
                            let width = img.width;
                            let height = img.height;

                            if (width > height) {
                                if (width > MAX_WIDTH) { height *= MAX_WIDTH / width; width = MAX_WIDTH; }
                            } else {
                                if (height > MAX_WIDTH) { width *= MAX_WIDTH / height; height = MAX_WIDTH; }
                            }
                            canvas.width = width;
                            canvas.height = height;
                            const ctx = canvas.getContext('2d');
                            ctx.drawImage(img, 0, 0, width, height);
                            this.formData[fieldName] = canvas.toDataURL('image/jpeg', 0.7);
                        };
                        img.src = e.target.result;
                    };
                    reader.readAsDataURL(file);
                },
                editData(data) {
                    this.editingData = data;
                    this.formData = { ...data };
                    this.showForm = true;
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                    setTimeout(() => lucide.createIcons(), 100);
                },
                resetForm() {
                    this.editingData = null;
                    this.formData = {
                        id: '', nama: '', plat: '', jenis_bensin: '', kondisi_dalam: '', lokasi: '',
                        fotoDepan: '', fotoDalam: '', fotoKanan: '',
                        fotoKiri: '', fotoBelakang: '', fotoSpeedometer: ''
                    };
                    this.showForm = true;
                    setTimeout(() => lucide.createIcons(), 100);
                },

                // Modal for showing kondisi interior details
                showKondisiModal: false,
                modalKondisiData: { label: '', items: [] },
                openKondisiModal(kondisiString, label) {
                    if (!kondisiString) {
                        this.modalKondisiData = { label: label || 'Kondisi', items: [] };
                        this.showKondisiModal = true;
                        return;
                    }

                    const items = kondisiString.split(',').map(s => s.trim()).filter(Boolean);
                    const filtered = label ? items.filter(i => i.toLowerCase().includes(label.toLowerCase())) : items;
                    this.modalKondisiData = { label: label || 'Kondisi', items: filtered };
                    this.showKondisiModal = true;
                    setTimeout(() => lucide.createIcons(), 100);
                }
            }
        }
        document.addEventListener('DOMContentLoaded', () => lucide.createIcons());
    </script>
</body>
</html>