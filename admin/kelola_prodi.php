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
        // Cek apakah prodi masih digunakan oleh user
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE prodi = (SELECT nama_prodi FROM prodi WHERE id = ?)");
        $checkStmt->execute([$id]);
        $userCount = $checkStmt->fetchColumn();
        
        if ($userCount > 0) {
            $msg = "⚠️ Prodi tidak dapat dihapus karena masih digunakan oleh $userCount user!";
        } else {
            $stmt = $pdo->prepare("DELETE FROM prodi WHERE id = ?");
            $stmt->execute([$id]);
            header("Location: kelola_prodi.php?status=deleted");
            exit;
        }
    } catch (PDOException $e) { 
        $msg = "❌ Gagal menghapus: " . $e->getMessage(); 
    }
}

// 2. Proses Simpan/Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $nama_prodi = trim($_POST['nama_prodi'] ?? '');
    $kode_prodi = strtoupper(trim($_POST['kode_prodi'] ?? ''));
    $jenjang = $_POST['jenjang'] ?? 'S1';
    $kepala_prodi = trim($_POST['kepala_prodi'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $status = $_POST['status'] ?? 'Aktif';

    try {
        // Validasi duplikat nama prodi
        if ($id) {
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM prodi WHERE nama_prodi = ? AND id != ?");
            $checkStmt->execute([$nama_prodi, $id]);
        } else {
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM prodi WHERE nama_prodi = ?");
            $checkStmt->execute([$nama_prodi]);
        }
        
        $count = $checkStmt->fetchColumn();
        
        if ($count > 0) {
            $msg = "⚠️ Program Studi '$nama_prodi' sudah terdaftar!";
        } else {
            if ($id) {
                // Update
                $sql = "UPDATE prodi SET nama_prodi=?, kode_prodi=?, jenjang=?, kepala_prodi=?, deskripsi=?, status=?, updated_at=NOW() WHERE id=?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$nama_prodi, $kode_prodi, $jenjang, $kepala_prodi, $deskripsi, $status, $id]);
                $msg = "✅ Data program studi berhasil diperbarui!";
            } else {
                // Insert
                $sql = "INSERT INTO prodi (nama_prodi, kode_prodi, jenjang, kepala_prodi, deskripsi, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$nama_prodi, $kode_prodi, $jenjang, $kepala_prodi, $deskripsi, $status]);
                $msg = "✅ Program studi baru berhasil ditambahkan!";
            }
        }
    } catch (PDOException $e) {
        $msg = "❌ Error Database: " . $e->getMessage();
    }
}

// 3. Ambil Data dengan Search
$search = $_GET['search'] ?? '';
$filter_jenjang = $_GET['filter_jenjang'] ?? 'all';

$sql_query = "SELECT * FROM prodi WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql_query .= " AND (nama_prodi LIKE :s1 OR kode_prodi LIKE :s2 OR kepala_prodi LIKE :s3)";
    $params[':s1'] = "%$search%";
    $params[':s2'] = "%$search%";
    $params[':s3'] = "%$search%";
}

if ($filter_jenjang !== 'all') {
    $sql_query .= " AND jenjang = :jenjang";
    $params[':jenjang'] = $filter_jenjang;
}

$sql_query .= " ORDER BY jenjang ASC, nama_prodi ASC";
$stmt = $pdo->prepare($sql_query);
$stmt->execute($params);
$prodi_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Hitung jumlah user per prodi
$userCountStmt = $pdo->query("SELECT prodi, COUNT(*) as total FROM users GROUP BY prodi");
$userCounts = [];
while ($row = $userCountStmt->fetch()) {
    $userCounts[$row['prodi']] = $row['total'];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../assets/kampusums.png" />
    <title>Kelola Program Studi</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }
        html { scroll-behavior: smooth; }
    </style>
</head>
<body class="bg-gray-50 flex flex-col lg:flex-row" x-data="prodiApp()">

    <?php include "../components/AdminNavbar.php"; ?>

    <main class="flex-1 lg:ml-60 p-4 md:p-5 mt-16 lg:mt-0 min-h-screen w-full overflow-x-hidden">
        <div class="max-w-7xl mx-auto">
            
            <div class="flex flex-col md:flex-row md:justify-between md:items-end gap-4 mb-8">
                <div>
                    <h1 class="text-2xl md:text-3xl font-bold text-gray-800">Kelola Program Studi</h1>
                    <p class="text-sm md:text-base text-gray-500 mt-1">Manajemen daftar program studi Fakultas Teknik.</p>
                </div>
                <div class="flex flex-col sm:flex-row gap-2 sm:gap-3">
                    <a href="kelola_user.php" class="flex items-center justify-center gap-2 bg-gray-600 hover:bg-gray-700 text-white px-5 py-2.5 rounded-xl font-medium shadow-md transition-all">
                        <i data-lucide="users" class="w-4 h-4"></i> Kelola User
                    </a>
                    <button @click="openTambah()" class="flex items-center justify-center gap-2 bg-[#d13b1f] hover:bg-[#b53118] text-white px-5 py-2.5 rounded-xl font-medium shadow-md transition-all active:scale-95">
                        <i data-lucide="plus" class="w-4 h-4"></i> Tambah Prodi
                    </button>
                </div>
            </div>

            <?php if($msg || isset($_GET['status'])): ?>
            <div class="<?= strpos($msg, '⚠️') !== false || strpos($msg, '❌') !== false ? 'bg-red-100 border-red-200 text-red-700' : 'bg-green-100 border-green-200 text-green-700' ?> border px-4 py-3 rounded-xl mb-6 flex items-center justify-between shadow-sm">
                <div class="flex items-center gap-2 text-sm">
                    <i data-lucide="<?= strpos($msg, '⚠️') !== false || strpos($msg, '❌') !== false ? 'alert-circle' : 'check-circle' ?>" size="18"></i> 
                    <?= $msg ?: "Aksi berhasil diproses!" ?>
                </div>
                <button type="button" onclick="window.location.href='kelola_prodi.php'"><i data-lucide="x" size="16"></i></button>
            </div>
            <?php endif; ?>

            <div x-show="showForm" x-transition x-cloak class="bg-black/5 rounded-2xl shadow-xl border border-black/10 p-6 mb-8">
                <form action="kelola_prodi.php" method="POST">
                    <input type="hidden" name="id" x-model="form.id">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="md:col-span-2">
                            <label class="text-[10px] font-bold text-gray-400 uppercase">Nama Program Studi <span class="text-red-500">*</span></label>
                            <input type="text" name="nama_prodi" x-model="form.nama_prodi" required 
                                   class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3
         focus:border-[#d13b1f] focus:ring-2 focus:ring-[#d13b1f]/30
         outline-none transition-all font-semibold text-lg md:text-xl">
                        </div>

                        <div>
                            <label class="text-[10px] font-bold text-gray-400 uppercase">Kode Prodi <span class="text-red-500">*</span></label>
                            <input type="text" name="kode_prodi" x-model="form.kode_prodi" required maxlength="10"
                                   class="w-full bg-gray-50 px-3 py-2.5 rounded-lg mt-1 outline-none border border-transparent focus:border-[#d13b1f] text-sm uppercase">
                            <p class="text-[10px] text-gray-500 mt-1 italic">Contoh: TI, TS, TM</p>
                        </div>

                        <div>
                            <label class="text-[10px] font-bold text-gray-400 uppercase">Jenjang <span class="text-red-500">*</span></label>
                            <select name="jenjang" x-model="form.jenjang" required
                                    class="w-full bg-gray-50 px-3 py-2.5 rounded-lg mt-1 outline-none border border-transparent focus:border-[#d13b1f] text-sm">
                                <option value="D3">D3 (Diploma 3)</option>
                                <option value="D4">D4 (Diploma 4)</option>
                                <option value="S1">S1 (Sarjana)</option>
                                <option value="S2">S2 (Magister)</option>
                                <option value="S3">S3 (Doktor)</option>
                            </select>
                        </div>

                        <div class="md:col-span-2">
                            <label class="text-[10px] font-bold text-gray-400 uppercase">Kepala Program Studi</label>
                            <input type="text" name="kepala_prodi" x-model="form.kepala_prodi"
                                   class="w-full bg-gray-50 px-3 py-2.5 rounded-lg mt-1 outline-none border border-transparent focus:border-[#d13b1f] text-sm"
                                   placeholder="Nama Kepala Prodi">
                        </div>

                        <div class="md:col-span-2">
                            <label class="text-[10px] font-bold text-gray-400 uppercase">Deskripsi Singkat</label>
                            <textarea name="deskripsi" x-model="form.deskripsi" rows="3"
                                      class="w-full bg-gray-50 px-3 py-2.5 rounded-lg mt-1 outline-none border border-transparent focus:border-[#d13b1f] text-sm"
                                      placeholder="Deskripsi program studi..."></textarea>
                        </div>

                        <div>
                            <label class="text-[10px] font-bold text-gray-400 uppercase">Status</label>
                            <select name="status" x-model="form.status"
                                    class="w-full bg-gray-50 px-3 py-2.5 rounded-lg mt-1 outline-none border border-transparent focus:border-[#d13b1f] text-sm">
                                <option value="Aktif">Aktif</option>
                                <option value="Nonaktif">Nonaktif</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex flex-col-reverse md:flex-row justify-end gap-3 mt-8 pt-4 border-t">
                        <button type="button" @click="showForm = false" class="text-gray-400 font-bold text-sm px-6 py-2.5 md:py-0">BATAL</button>
                        <button type="submit" class="bg-[#d13b1f] text-white px-8 py-2.5 rounded-xl font-bold shadow-lg transform active:scale-95 transition-all text-sm uppercase">
                            <span x-text="editMode ? 'UPDATE DATA' : 'SIMPAN DATA'"></span>
                        </button>
                    </div>
                </form>
            </div>

            <div class="bg-white rounded-2xl md:rounded-3xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="p-5 md:p-6 border-b border-gray-50 flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
                    <div class="w-full lg:w-auto overflow-x-auto">
                        <div class="flex p-1 bg-gray-100 rounded-xl min-w-max">
                            <a href="?filter_jenjang=all<?= $search ? '&search=' . urlencode($search) : '' ?>" 
                               class="px-4 py-1.5 text-xs font-bold rounded-lg whitespace-nowrap transition-all <?= $filter_jenjang == 'all' ? 'bg-white text-[#d13b1f] shadow-sm' : 'text-gray-400 hover:text-gray-600' ?>">
                                Semua Jenjang
                            </a>
                            <a href="?filter_jenjang=D3<?= $search ? '&search=' . urlencode($search) : '' ?>" 
                               class="px-4 py-1.5 text-xs font-bold rounded-lg whitespace-nowrap transition-all <?= $filter_jenjang == 'D3' ? 'bg-white text-[#d13b1f] shadow-sm' : 'text-gray-400 hover:text-gray-600' ?>">
                                D3
                            </a>
                            <a href="?filter_jenjang=D4<?= $search ? '&search=' . urlencode($search) : '' ?>" 
                               class="px-4 py-1.5 text-xs font-bold rounded-lg whitespace-nowrap transition-all <?= $filter_jenjang == 'D4' ? 'bg-white text-[#d13b1f] shadow-sm' : 'text-gray-400 hover:text-gray-600' ?>">
                                D4
                            </a>
                            <a href="?filter_jenjang=S1<?= $search ? '&search=' . urlencode($search) : '' ?>" 
                               class="px-4 py-1.5 text-xs font-bold rounded-lg whitespace-nowrap transition-all <?= $filter_jenjang == 'S1' ? 'bg-white text-[#d13b1f] shadow-sm' : 'text-gray-400 hover:text-gray-600' ?>">
                                S1
                            </a>
                            <a href="?filter_jenjang=S2<?= $search ? '&search=' . urlencode($search) : '' ?>" 
                               class="px-4 py-1.5 text-xs font-bold rounded-lg whitespace-nowrap transition-all <?= $filter_jenjang == 'S2' ? 'bg-white text-[#d13b1f] shadow-sm' : 'text-gray-400 hover:text-gray-600' ?>">
                                S2
                            </a>
                            <a href="?filter_jenjang=S3<?= $search ? '&search=' . urlencode($search) : '' ?>" 
                               class="px-4 py-1.5 text-xs font-bold rounded-lg whitespace-nowrap transition-all <?= $filter_jenjang == 'S3' ? 'bg-white text-[#d13b1f] shadow-sm' : 'text-gray-400 hover:text-gray-600' ?>">
                                S3
                            </a>
                        </div>
                    </div>
                    
                    <div class="flex flex-col sm:flex-row gap-3 items-stretch sm:items-center w-full lg:w-auto">
                        <form method="GET" class="relative w-full sm:w-64 md:w-72">
                            <input type="hidden" name="filter_jenjang" value="<?= htmlspecialchars($filter_jenjang) ?>">
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                                   placeholder="Cari nama, kode prodi..." 
                                   class="w-full pl-10 pr-4 py-2 bg-gray-50 rounded-xl text-sm outline-none focus:ring-2 focus:ring-orange-100 border border-transparent">
                            <i data-lucide="search" class="absolute left-3 top-2.5 w-4 h-4 text-gray-400"></i>
                        </form>
                        <?php if($search || $filter_jenjang !== 'all'): ?>
                        <a href="kelola_prodi.php" class="flex items-center justify-center gap-1 text-xs text-gray-500 hover:text-[#d13b1f] transition-colors whitespace-nowrap">
                            <i data-lucide="x-circle" class="w-4 h-4"></i>
                            <span>Reset</span>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if($filter_jenjang !== 'all' || $search): ?>
                <div class="px-5 md:px-6 py-3 bg-blue-50 border-b border-blue-100 flex items-center gap-2 text-sm">
                    <i data-lucide="filter" class="w-4 h-4 text-blue-600"></i>
                    <span class="text-blue-700">
                        <?php if($filter_jenjang !== 'all' && $search): ?>
                            Menampilkan <strong><?= count($prodi_list) ?></strong> prodi jenjang <strong><?= htmlspecialchars($filter_jenjang) ?></strong> dengan kata kunci "<strong><?= htmlspecialchars($search) ?></strong>"
                        <?php elseif($filter_jenjang !== 'all'): ?>
                            Menampilkan <strong><?= count($prodi_list) ?></strong> prodi jenjang <strong><?= htmlspecialchars($filter_jenjang) ?></strong>
                        <?php else: ?>
                            Menampilkan <strong><?= count($prodi_list) ?></strong> prodi dengan kata kunci "<strong><?= htmlspecialchars($search) ?></strong>"
                        <?php endif; ?>
                    </span>
                </div>
                <?php else: ?>
                <div class="px-5 md:px-6 py-3 bg-gray-50 border-b border-gray-100">
                    <h3 class="font-bold text-gray-700 text-sm md:text-base">Daftar Program Studi (<?= count($prodi_list) ?> prodi)</h3>
                </div>
                <?php endif; ?>

                <div class="overflow-x-auto">
                    <table class="w-full text-left min-w-[800px]">
                        <thead>
                            <tr class="bg-gray-50/50">
                                <th class="p-5 text-[10px] font-black text-gray-400 uppercase">Nama Program Studi</th>
                                <th class="p-5 text-[10px] font-black text-gray-400 uppercase">Kode</th>
                                <th class="p-5 text-[10px] font-black text-gray-400 uppercase">Jenjang</th>
                                <th class="p-5 text-[10px] font-black text-gray-400 uppercase">Kepala Prodi</th>
                                <th class="p-5 text-[10px] font-black text-gray-400 uppercase">Jumlah Mahasiswa</th>
                                <th class="p-5 text-[10px] font-black text-gray-400 uppercase">Status</th>
                                <th class="p-5 text-[10px] font-black text-gray-400 uppercase text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <?php if(empty($prodi_list)): ?>
                            <tr>
                                <td colspan="7" class="p-10 text-center text-gray-400">
                                    <i data-lucide="inbox" class="w-12 h-12 mx-auto mb-2 opacity-30"></i>
                                    <p>Tidak ada data ditemukan</p>
                                    <?php if($filter_jenjang !== 'all' || $search): ?>
                                    <a href="kelola_prodi.php" class="inline-flex items-center gap-1 mt-3 text-sm text-[#d13b1f] hover:underline">
                                        <i data-lucide="arrow-left" class="w-4 h-4"></i>
                                        Tampilkan semua data
                                    </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach($prodi_list as $prodi): 
                                    $userCount = $userCounts[$prodi['nama_prodi']] ?? 0;
                                ?>
                                <tr class="hover:bg-gray-50/80 transition-colors">
                                    <td class="p-5">
                                        <div>
                                            <p class="font-bold text-gray-800 text-base"><?= htmlspecialchars($prodi['nama_prodi']) ?></p>
                                            <?php if(!empty($prodi['deskripsi'])): ?>
                                            <p class="text-xs text-gray-500 mt-1 line-clamp-1"><?= htmlspecialchars($prodi['deskripsi']) ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="p-5">
                                        <span class="px-3 py-1 bg-blue-100 text-blue-700 rounded-lg font-mono text-xs font-bold">
                                            <?= htmlspecialchars($prodi['kode_prodi']) ?>
                                        </span>
                                    </td>
                                    <td class="p-5">
                                        <?php 
                                        $jenjangColor = 'bg-gray-100 text-gray-700';
                                        if($prodi['jenjang'] == 'S1') $jenjangColor = 'bg-green-100 text-green-700';
                                        elseif($prodi['jenjang'] == 'S2') $jenjangColor = 'bg-purple-100 text-purple-700';
                                        elseif($prodi['jenjang'] == 'S3') $jenjangColor = 'bg-red-100 text-red-700';
                                        ?>
                                        <span class="px-3 py-1 <?= $jenjangColor ?> rounded-lg text-xs font-bold">
                                            <?= htmlspecialchars($prodi['jenjang']) ?>
                                        </span>
                                    </td>
                                    <td class="p-5">
                                        <p class="text-sm text-gray-700"><?= htmlspecialchars($prodi['kepala_prodi'] ?: '-') ?></p>
                                    </td>
                                    <td class="p-5">
                                        <div class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-50 rounded-lg">
                                            <i data-lucide="users" class="w-4 h-4 text-blue-600"></i>
                                            <span class="text-sm font-bold text-blue-700"><?= $userCount ?> User</span>
                                        </div>
                                    </td>
                                    <td class="p-5">
                                        <?php $statusColor = $prodi['status'] == 'Aktif' ? 'bg-green-50 text-green-600' : 'bg-gray-50 text-gray-600'; ?>
                                        <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase <?= $statusColor ?>">
                                            <?= $prodi['status'] ?>
                                        </span>
                                    </td>
                                    <td class="p-5">
                                        <div class="flex justify-center gap-1">
                                            <button @click='openEdit(<?= json_encode($prodi) ?>)' 
                                                    class="p-2 text-blue-500 hover:bg-blue-50 rounded-lg transition-colors">
                                                <i data-lucide="edit-3" class="w-4 h-4"></i>
                                            </button>
                                            <button onclick="confirmDelete(<?= $prodi['id'] ?>, '<?= htmlspecialchars($prodi['nama_prodi']) ?>', <?= $userCount ?>)" 
                                                    class="p-2 text-red-400 hover:bg-red-50 rounded-lg transition-colors">
                                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <script>
        function confirmDelete(id, namaProdi, userCount) {
            if (userCount > 0) {
                Swal.fire({
                    title: 'Tidak Dapat Dihapus!',
                    html: `
                        <div class="text-left">
                            <p class="mb-3">Program studi <strong>${namaProdi}</strong> masih digunakan oleh:</p>
                            <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-3">
                                <div class="flex items-center gap-2 text-red-700">
                                    <i data-lucide="users" class="w-5 h-5"></i>
                                    <span class="text-lg font-bold">${userCount} User Aktif</span>
                                </div>
                            </div>
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 text-xs text-blue-700">
                                <p class="font-bold mb-1 flex items-center gap-1">
                                    <i data-lucide="shield-alert" class="w-3 h-3"></i>
                                    Protected by Foreign Key Constraint
                                </p>
                                <p>Data ini tidak dapat dihapus karena dilindungi oleh database constraint (ON DELETE RESTRICT).</p>
                            </div>
                            <p class="mt-3 text-sm text-gray-600">Silakan hapus atau pindahkan user terlebih dahulu.</p>
                        </div>
                    `,
                    icon: 'error',
                    confirmButtonColor: '#d13b1f',
                    confirmButtonText: 'Mengerti',
                    width: '500px',
                    customClass: {
                        popup: 'rounded-2xl'
                    },
                    didOpen: () => {
                        lucide.createIcons();
                    }
                });
                return;
            }

            Swal.fire({
                title: 'Apakah Anda yakin?',
                html: `Anda akan menghapus program studi:<br><strong>${namaProdi}</strong>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d13b1f',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal',
                reverseButtons: true,
                customClass: {
                    popup: 'rounded-2xl'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `kelola_prodi.php?delete=${id}`;
                }
            });
        }

        function prodiApp() {
            return {
                showForm: false,
                editMode: false,
                form: {
                    id: '',
                    nama_prodi: '',
                    kode_prodi: '',
                    jenjang: 'S1',
                    kepala_prodi: '',
                    deskripsi: '',
                    status: 'Aktif'
                },

                openTambah() {
                    this.editMode = false;
                    this.form = {
                        id: '',
                        nama_prodi: '',
                        kode_prodi: '',
                        jenjang: 'S1',
                        kepala_prodi: '',
                        deskripsi: '',
                        status: 'Aktif'
                    };
                    this.showForm = true;
                    window.scrollTo({top: 0, behavior: 'smooth'});
                    setTimeout(() => lucide.createIcons(), 100);
                },

                openEdit(data) {
                    this.editMode = true;
                    this.form = {...data};
                    this.showForm = true;
                    window.scrollTo({top: 0, behavior: 'smooth'});
                    setTimeout(() => lucide.createIcons(), 100);
                }
            }
        }
        
        document.addEventListener('DOMContentLoaded', () => lucide.createIcons());
    </script>
</body>
</html>