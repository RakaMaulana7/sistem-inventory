<?php
session_start();
require "../config/database.php";
require "../auth/auth_helper.php";

cek_kemanan_login($pdo);

// 1. Proteksi Halaman
if (!isset($_SESSION['login']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// 2. Logika Search & Filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filterProdi = isset($_GET['filter_prodi']) ? trim($_GET['filter_prodi']) : '';
$sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'nim'; // Default sort by NIM

// 3. Logika Paginasi
$usersPerPage = 15;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $usersPerPage;

// 4. Query dengan Search, Filter, dan Sorting
$whereConditions = [];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(nama LIKE ? OR username LIKE ? OR prodi LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($filterProdi)) {
    $whereConditions[] = "prodi = ?";
    $params[] = $filterProdi;
}

$whereClause = '';
if (count($whereConditions) > 0) {
    $whereClause = "WHERE " . implode(" AND ", $whereConditions);
}

// Tentukan ORDER BY berdasarkan sorting
$orderBy = "ORDER BY role ASC, ";
switch($sortBy) {
    case 'nim':
        // Sort berdasarkan NIM (username) secara numerik
        $orderBy .= "CAST(username AS UNSIGNED) ASC, username ASC";
        break;
    case 'prodi':
        // Sort berdasarkan Prodi, lalu NIM
        $orderBy .= "prodi ASC, CAST(username AS UNSIGNED) ASC, username ASC";
        break;
    case 'nama':
        // Sort berdasarkan Nama
        $orderBy .= "nama ASC";
        break;
    default:
        $orderBy .= "CAST(username AS UNSIGNED) ASC, username ASC";
}

// Ambil Total User (dengan filter search)
$countQuery = "SELECT COUNT(*) FROM users $whereClause";
$countStmt = $pdo->prepare($countQuery);
$countStmt->execute($params);
$totalUsers = $countStmt->fetchColumn();
$totalPages = ceil($totalUsers / $usersPerPage);

// Ambil Data User (dengan filter search, sorting, dan pagination)
$query = "SELECT * FROM users $whereClause $orderBy LIMIT ? OFFSET ?";
$stmt = $pdo->prepare($query);

// Execute dengan parameter yang sesuai
$executeParams = array_merge($params, [$usersPerPage, $offset]);
$stmt->execute($executeParams);
$users = $stmt->fetchAll();

// 5. Ambil Daftar Prodi Unik untuk Filter (dari users yang ada)
$prodiQuery = "SELECT DISTINCT prodi FROM users WHERE prodi IS NOT NULL AND prodi != '' ORDER BY prodi ASC";
$prodiStmt = $pdo->query($prodiQuery);
$daftarProdi = $prodiStmt->fetchAll(PDO::FETCH_COLUMN);

// 5b. Ambil Daftar Prodi dari tabel prodi (untuk dropdown form)
try {
    $prodiDbQuery = "SELECT nama_prodi FROM prodi WHERE status = 'Aktif' ORDER BY jenjang ASC, nama_prodi ASC";
    $prodiDbStmt = $pdo->query($prodiDbQuery);
    $daftarProdiDb = $prodiDbStmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Jika tabel prodi tidak ada atau kosong, gunakan data default
    if (empty($daftarProdiDb)) {
        $daftarProdiDb = [
            'Teknik Informatika',
            'Teknik Sipil',
            'Teknik Mesin',
            'Teknik Elektro',
            'Teknik Perkapalan',
            'Teknik Industri',
            'Teknik Arsitektur'
        ];
    }
} catch (PDOException $e) {
    // Jika tabel prodi belum ada, gunakan data default
    $daftarProdiDb = [
        'Teknik Informatika',
        'Teknik Sipil',
        'Teknik Mesin',
        'Teknik Elektro',
        'Teknik Perkapalan',
        'Teknik Industri',
        'Teknik Arsitektur'
    ];
}

// 6. Helper Style Badge
function getRoleBadgeStyle($role) {
    switch ($role) {
        case 'admin': return 'bg-purple-100 text-purple-700 border-purple-200';
        default: return 'bg-green-100 text-green-700 border-green-200';
    }
}

// 7. Helper untuk build query string
function buildQueryString($params) {
    return http_build_query(array_filter($params, function($value) {
        return $value !== '' && $value !== null;
    }));
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../assets/kampusums.png" />
    <title>Kelola User</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-gray-50 font-sans flex flex-col lg:flex-row" x-data="userApp()">

    <?php include "../components/AdminNavbar.php"; ?>

    <main class="flex-1 lg:ml-60 p-4 md:p-8 mt-16 lg:mt-0 w-full">
        <div class="max-w-7xl mx-auto">
            
            <div class="flex flex-col md:flex-row justify-between items-start md:items-end gap-4 mb-8">
                <div>
                    <h1 class="text-2xl md:text-3xl font-bold text-gray-800">Kelola Users</h1>
                    <p class="text-sm md:text-base text-gray-500 mt-1">Manajemen data akses pengguna dan import massal.</p>
                </div>

                <div class="flex flex-wrap gap-3 w-full md:w-auto">
                    <button @click="showImport = !showImport; showForm = false" class="flex-1 md:flex-none justify-center flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-xl text-sm font-medium shadow-md transition-all">
                        <i data-lucide="upload" class="w-4 h-4"></i> Import CSV
                    </button>

                    <button @click="showForm = !showForm; showImport = false; editingUser = null; resetForm(); $nextTick(() => { if(showForm) { document.getElementById('formSection').scrollIntoView({ behavior: 'smooth', block: 'start' }); setTimeout(() => lucide.createIcons(), 100); } })" class="flex-1 md:flex-none justify-center flex items-center gap-2 bg-[#d13b1f] hover:bg-[#b53118] text-white px-5 py-2.5 rounded-xl text-sm font-medium shadow-md transition-all">
                        <i data-lucide="plus" class="w-4 h-4"></i> Tambah User
                    </button>
                </div>
            </div>

            <?php if(isset($_GET['msg'])):
                $m = $_GET['msg'];
                $alertClass = 'bg-white border-l-4 border-green-500 text-gray-700';
                $icon = 'check-circle';
                $text = 'Berhasil memproses data pengguna!';

                if ($m === 'deleted') {
                    $text = 'Pengguna berhasil dihapus.';
                } elseif (strpos($m, 'import_done') === 0) {
                    // import_done&s=10&e=2
                    $text = 'Import selesai.';
                } elseif ($m === 'error_email_exists') {
                    $alertClass = 'bg-red-50 border-l-4 border-red-500 text-red-700';
                    $icon = 'alert-circle';
                    $text = 'Email sudah terdaftar.';
                } elseif ($m === 'error_username_exists') {
                    $alertClass = 'bg-red-50 border-l-4 border-red-500 text-red-700';
                    $icon = 'alert-circle';
                    $text = 'Username / NIM sudah terdaftar.';
                } elseif ($m === 'error_invalid_email') {
                    $alertClass = 'bg-red-50 border-l-4 border-red-500 text-red-700';
                    $icon = 'alert-circle';
                    $text = 'Format email tidak valid.';
                }
            ?>
                <div class="<?= $alertClass ?> px-4 md:px-6 py-4 rounded-xl mb-6 shadow-sm flex items-center justify-between">
                    <div class="flex items-center gap-3 text-sm">
                        <i data-lucide="<?= $icon ?>" class="w-5 h-5"></i>
                        <span><?= $text ?></span>
                    </div>
                    <button onclick="window.location.href='kelola_user.php'" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="w-4 h-4"></i></button>
                </div>
            <?php endif; ?>

            <div x-show="showImport" x-transition x-cloak class="bg-white border border-gray-200 rounded-2xl shadow-sm mb-8 overflow-hidden">
                <div class="p-5 border-b border-gray-100 bg-gray-50/50 flex justify-between items-center">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-blue-100 text-blue-600 rounded-lg"><i data-lucide="file-up" class="w-5 h-5"></i></div>
                        <h3 class="text-sm font-bold text-gray-800">Import Massal via CSV</h3>
                    </div>
                    <button @click="showImport = false" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="w-5 h-5"></i></button>
                </div>
                <div class="p-6 md:p-8" x-data="{ fileName: '' }">
                    <form action="import_user.php" method="POST" enctype="multipart/form-data">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-center">
                            
                            <label class="flex flex-col items-center justify-center w-full h-36 md:h-44 border-2 border-dashed border-gray-300 rounded-2xl cursor-pointer transition-all"
                                :class="fileName ? 'bg-green-50 border-green-400' : 'bg-gray-50 hover:bg-blue-50 hover:border-blue-400'">
                                
                                <template x-if="!fileName">
                                    <div class="flex flex-col items-center text-center p-4">
                                        <i data-lucide="upload-cloud" class="w-8 h-8 md:w-10 md:h-10 text-blue-400 mb-2"></i>
                                        <p class="text-xs md:text-sm text-gray-700 font-medium">Klik untuk pilih file .csv</p>
                                    </div>
                                </template>

                                <template x-if="fileName">
                                    <div class="flex flex-col items-center text-center px-4">
                                        <i data-lucide="file-check" class="w-8 h-8 md:w-10 md:h-10 text-green-500 mb-2"></i>
                                        <p class="text-xs md:text-sm text-green-700 font-bold break-all" x-text="fileName"></p>
                                    </div>
                                </template>

                                <input type="file" name="file_csv" accept=".csv" required class="hidden" @change="fileName = $event.target.files[0].name" />
                            </label>

                            <div class="space-y-4">
                                <div class="bg-amber-50 border border-amber-100 rounded-xl p-4 text-xs text-amber-700">
                                    <p class="font-bold mb-1 uppercase tracking-wider flex items-center gap-2">
                                        <i data-lucide="info" class="w-3.5 h-3.5"></i> Urutan Kolom:
                                    </p>
                                    <p>1. Nama | 2. Username | 3. Email | 4. Prodi | 5. Role</p>
                                </div>
                                <button type="submit" name="import" class="w-full bg-blue-600 text-white py-3 rounded-xl font-bold hover:bg-blue-700 shadow-md flex justify-center items-center gap-2 transition-all">
                                    <i data-lucide="play" class="w-4 h-4"></i> Proses Sekarang
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div x-show="showForm" x-transition x-cloak class="bg-white border border-gray-200 rounded-2xl shadow-sm p-5 md:p-6 mb-8" id="formSection">
                <h3 class="font-bold text-gray-800 mb-6 flex items-center gap-2">
                    <i data-lucide="user-plus" class="text-[#d13b1f]"></i>
                    <span x-text="editingUser ? 'Edit Pengguna' : 'Tambah Pengguna Baru'"></span>
                </h3>
                <form action="proses_user.php" method="POST">
                    <input type="hidden" name="action" :value="editingUser ? 'edit' : 'add'">
                    <input type="hidden" name="old_username" :value="editingUser ? editingUser.username : ''">

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                        
                        <div class="flex flex-col gap-1">
                            <label class="text-[10px] font-bold uppercase text-gray-400 ml-1">Nama Lengkap</label>
                            <input type="text" name="nama" x-model="formData.nama" placeholder="Nama Lengkap" 
                                class="border p-3 rounded-xl text-sm focus:ring-2 focus:ring-[#d13b1f] outline-none transition-all" required>
                        </div>

                        <div class="flex flex-col gap-1">
                            <label class="text-[10px] font-bold uppercase text-gray-400 ml-1">NIM / Username</label>
                            <div class="relative">
                                <input type="text" name="username" x-model="formData.username" :readonly="editingUser" 
                                    placeholder="Minimal 8 karakter" 
                                    class="border p-3 rounded-xl text-sm outline-none w-full transition-all"
                                    :class="[
                                        editingUser ? 'bg-gray-100 text-gray-500 cursor-not-allowed' : 'focus:ring-2',
                                        usernameError ? 'border-red-500 focus:ring-red-500' : 'focus:ring-[#d13b1f]'
                                    ]"
                                    @blur="validateUsername()" minlength="8" required>
                                
                                <div x-show="usernameError" x-transition 
                                    class="absolute -bottom-5 left-0 text-[10px] text-red-600 font-medium flex items-center gap-1">
                                    <i data-lucide="alert-circle" class="w-3 h-3"></i>
                                    <span x-text="usernameError"></span>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-col gap-1">
                            <label class="text-[10px] font-bold uppercase text-gray-400 ml-1">Email <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <input type="email" name="email" x-model="formData.email" placeholder="contoh@gmail.com" 
                                    class="w-full border p-3 rounded-xl text-sm outline-none transition-all"
                                    :class="emailError ? 'border-red-500 focus:ring-red-500' : 'focus:ring-2 focus:ring-[#d13b1f]'"
                                    @blur="validateEmail()" 
                                    @input="validateEmail()" required>
                                
                                <div x-show="emailError" x-transition 
                                    class="absolute -bottom-5 left-0 text-[10px] text-red-600 font-medium flex items-center gap-1">
                                    <i data-lucide="alert-circle" class="w-3 h-3"></i>
                                    <span x-text="emailError"></span>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-col gap-1">
                            <label class="text-[10px] font-bold uppercase text-gray-400 ml-1">Program Studi</label>
                            <select name="prodi" x-model="formData.prodi" 
                                class="border p-3 rounded-xl text-sm focus:ring-2 focus:ring-[#d13b1f] outline-none bg-white transition-all" required>
                                <option value="">-- Pilih Prodi --</option>
                                <?php foreach($daftarProdiDb as $prodi): ?>
                                    <option value="<?= htmlspecialchars($prodi) ?>"><?= htmlspecialchars($prodi) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="flex flex-col gap-1 mt-2">
                            <label class="text-[10px] font-bold uppercase text-gray-400 ml-1">Role Akses</label>
                            <select name="role" x-model="formData.role" 
                                class="border p-3 rounded-xl text-sm focus:ring-2 focus:ring-[#d13b1f] outline-none bg-white transition-all">
                                <option value="user">User / Mahasiswa</option>
                                <option value="admin">Administrator</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex flex-col sm:flex-row justify-between items-center mt-10 pt-6 border-t border-gray-100 gap-4">
                        <div class="w-full sm:w-auto">
                            <template x-if="editingUser">
                                <button type="submit" name="reset_password" value="1" 
                                    onclick="return confirm('Apakah Anda yakin ingin mereset password user ini ke default?')"
                                    class="w-full sm:w-auto group flex items-center justify-center gap-2 px-5 py-2.5 text-[10px] font-black uppercase tracking-widest text-amber-700 bg-amber-50 border border-amber-200 rounded-xl hover:bg-amber-600 hover:text-white transition-all duration-300">
                                    <i data-lucide="key-round" class="w-3.5 h-3.5"></i> Reset Password
                                </button>
                            </template>
                        </div>

                        <div class="flex items-center gap-4 w-full sm:w-auto">
                            <button type="button" @click="showForm = false" 
                                class="flex-1 sm:flex-none text-gray-400 hover:text-gray-600 font-bold px-6 py-3 text-sm transition-colors">
                                Batal
                            </button>
                            
                            <button type="submit" 
                                class="flex-1 sm:flex-none bg-[#d13b1f] text-white px-10 py-3 rounded-xl font-bold shadow-lg shadow-red-200 hover:bg-[#b53118] hover:-translate-y-1 active:scale-95 transition-all duration-300 text-sm">
                                Simpan Data
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Filter & Search Section -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 md:p-5 mb-6">
                <form method="GET" class="space-y-4">
                    <!-- Search Bar -->
                    <div class="flex flex-col lg:flex-row items-stretch lg:items-center gap-3">
                        <div class="relative flex-1">
                            <i data-lucide="search" class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400"></i>
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Cari nama, username, atau prodi..." 
                                class="w-full pl-12 pr-4 py-3 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-[#d13b1f] outline-none">
                        </div>
                        
                        <!-- Filter Prodi Dropdown -->
                        <div class="flex-shrink-0 w-full lg:w-64">
                            <div class="relative">
                                <i data-lucide="filter" class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400"></i>
                                <select name="filter_prodi" class="w-full pl-12 pr-10 py-3 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-[#d13b1f] outline-none appearance-none bg-white">
                                    <option value="">Semua Prodi</option>
                                    <?php foreach($daftarProdi as $prodi): ?>
                                    <option value="<?= htmlspecialchars($prodi) ?>" <?= $filterProdi === $prodi ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($prodi) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <i data-lucide="chevron-down" class="absolute right-4 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none"></i>
                            </div>
                        </div>

                        <!-- Sort Dropdown -->
                        <div class="flex-shrink-0 w-full lg:w-48">
                            <div class="relative">
                                <i data-lucide="arrow-up-down" class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400"></i>
                                <select name="sort" class="w-full pl-12 pr-10 py-3 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-[#d13b1f] outline-none appearance-none bg-white">
                                    <option value="nim" <?= $sortBy === 'nim' ? 'selected' : '' ?>>Urutkan: NIM</option>
                                    <option value="prodi" <?= $sortBy === 'prodi' ? 'selected' : '' ?>>Urutkan: Prodi</option>
                                    <option value="nama" <?= $sortBy === 'nama' ? 'selected' : '' ?>>Urutkan: Nama</option>
                                </select>
                                <i data-lucide="chevron-down" class="absolute right-4 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none"></i>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex gap-2 flex-shrink-0">
                            <button type="submit" class="flex-1 lg:flex-none px-6 py-3 bg-[#d13b1f] text-white rounded-xl font-medium hover:bg-[#b53118] transition-all flex items-center justify-center gap-2 shadow-md whitespace-nowrap">
                                <i data-lucide="search" class="w-4 h-4"></i> 
                                <span class="hidden sm:inline">Cari</span>
                            </button>
                            <?php if(!empty($search) || !empty($filterProdi) || $sortBy !== 'nim'): ?>
                            <a href="kelola_user.php" class="flex-1 lg:flex-none px-6 py-3 bg-gray-100 text-gray-700 rounded-xl font-medium hover:bg-gray-200 transition-all flex items-center justify-center gap-2 whitespace-nowrap">
                                <i data-lucide="x" class="w-4 h-4"></i>
                                <span class="hidden sm:inline">Reset</span>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
                
                <!-- Active Filters Info -->
                <?php if(!empty($search) || !empty($filterProdi)): ?>
                <div class="mt-4 pt-4 border-t border-gray-100">
                    <div class="flex flex-wrap items-center gap-2 text-xs md:text-sm">
                        <div class="flex items-center gap-2 text-gray-600">
                            <i data-lucide="info" class="w-4 h-4 flex-shrink-0"></i>
                            <span class="font-medium">Filter aktif:</span>
                        </div>
                        
                        <?php if(!empty($search)): ?>
                        <span class="px-3 py-1 bg-blue-100 text-blue-700 rounded-lg font-medium flex items-center gap-2">
                            <i data-lucide="search" class="w-3 h-3"></i>
                            "<?= htmlspecialchars($search) ?>"
                        </span>
                        <?php endif; ?>
                        
                        <?php if(!empty($filterProdi)): ?>
                        <span class="px-3 py-1 bg-green-100 text-green-700 rounded-lg font-medium flex items-center gap-2">
                            <i data-lucide="graduation-cap" class="w-3 h-3"></i>
                            <?= htmlspecialchars($filterProdi) ?>
                        </span>
                        <?php endif; ?>
                        
                        <span class="text-gray-500">
                            (<?= $totalUsers ?> user ditemukan)
                        </span>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left min-w-[700px]">
                        <thead class="bg-gray-50 text-gray-500 text-[10px] uppercase tracking-widest font-bold">
                            <tr>
                                <th class="p-5">Nama Lengkap</th>
                                <th class="p-5">Username / NIM</th>
                                <th class="p-5">Email</th>
                                <th class="p-5">Program Studi</th>
                                <th class="p-5 text-center">Akses</th>
                                <th class="p-5 text-right">Tindakan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if(empty($users)): ?>
                            <tr>
                                <td colspan="6" class="p-16 text-center">
                                    <div class="flex flex-col items-center gap-3">
                                        <i data-lucide="search-x" class="w-12 h-12 text-gray-300"></i>
                                        <p class="text-gray-400 italic text-sm">Tidak ada data user</p>
                                        <?php if(!empty($search) || !empty($filterProdi)): ?>
                                        <a href="kelola_user.php" class="text-[#d13b1f] text-sm font-medium hover:underline">
                                            Reset filter dan coba lagi
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach($users as $u): ?>
                                <tr class="hover:bg-gray-50/50 transition-colors">
                                    <td class="p-5 font-semibold text-gray-800 text-sm"><?= htmlspecialchars($u['nama']) ?></td>
                                    <td class="p-5 font-mono text-xs text-gray-500"><?= htmlspecialchars($u['username']) ?></td>
                                    <td class="p-5 text-sm text-gray-700"><?= htmlspecialchars($u['email'] ?: '-') ?></td>
                                    <td class="p-5 text-gray-600 text-sm"><?= htmlspecialchars($u['prodi']) ?></td>
                                    <td class="p-5 text-center">
                                        <span class="px-3 py-1 rounded-lg text-[9px] font-black uppercase border <?= getRoleBadgeStyle($u['role']) ?>">
                                            <?= $u['role'] ?>
                                        </span>
                                    </td>
                                    <td class="p-5 text-right">
                                        <div class="flex justify-end gap-2">
                                            <button @click="openEdit(<?= htmlspecialchars(json_encode($u)) ?>)" 
                                                class="p-2 text-blue-600 bg-blue-50 rounded-lg hover:bg-blue-100 transition-all"><i data-lucide="edit-3" class="w-4 h-4"></i></button>
                                            
                                            <?php if($u['username'] !== 'admin'): ?>
                                            <button onclick="confirmDelete('<?= htmlspecialchars($u['username']) ?>', '<?= htmlspecialchars($u['nama']) ?>')" 
                                                class="p-2 text-red-600 bg-red-50 rounded-lg hover:bg-red-100 transition-all"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if($totalPages > 1): ?>
                <div class="p-5 border-t border-gray-50 bg-gray-50/30 flex flex-col sm:flex-row justify-between items-center gap-4">
                    <p class="text-[10px] text-gray-500 font-medium">
                        Halaman <?= $page ?> dari <?= $totalPages ?> (Total: <?= $totalUsers ?> user)
                    </p>
                    <div class="flex items-center gap-2">
                        <?php 
                        $queryParams = [
                            'search' => $search,
                            'filter_prodi' => $filterProdi,
                            'sort' => $sortBy
                        ];
                        $queryString = buildQueryString($queryParams);
                        $separator = !empty($queryString) ? '&' : '';
                        ?>
                        
                        <a href="?<?= $queryString ?><?= $separator ?>page=<?= max(1, $page-1) ?>" 
                           class="p-2 rounded-lg bg-white border text-gray-600 hover:bg-gray-50 transition-all <?= $page == 1 ? 'opacity-50 pointer-events-none' : '' ?>">
                            <i data-lucide="chevron-left" class="w-4 h-4"></i>
                        </a>
                        
                        <div class="flex gap-1">
                            <?php
                            $startPage = max(1, $page - 1);
                            $endPage = min($totalPages, $page + 1);
                            for($i = $startPage; $i <= $endPage; $i++):
                            ?>
                            <a href="?<?= $queryString ?><?= $separator ?>page=<?= $i ?>" 
                               class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all <?= $i == $page ? 'bg-[#d13b1f] text-white' : 'bg-white border text-gray-600 hover:bg-gray-50' ?>">
                                <?= $i ?>
                            </a>
                            <?php endfor; ?>
                        </div>
                        
                        <a href="?<?= $queryString ?><?= $separator ?>page=<?= min($totalPages, $page+1) ?>" 
                           class="p-2 rounded-lg bg-white border text-gray-600 hover:bg-gray-50 transition-all <?= $page == $totalPages ? 'opacity-50 pointer-events-none' : '' ?>">
                            <i data-lucide="chevron-right" class="w-4 h-4"></i>
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        // Fungsi konfirmasi hapus dengan SweetAlert2
        function confirmDelete(username, nama) {
            Swal.fire({
                title: 'Apakah Anda yakin?',
                html: `Anda akan menghapus user:<br><strong>${nama}</strong> (${username})`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d13b1f',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    // Redirect ke halaman proses hapus
                    window.location.href = `proses_user.php?action=delete&username=${encodeURIComponent(username)}`;
                }
            });
        }

        function userApp() {
            return {
                showForm: false,
                showImport: false,
                editingUser: null,
                usernameError: '',
                formData: {
                    nama: '',
                    username: '',
                    email: '',
                    prodi: '',
                    role: 'user'
                },

                resetForm() {
                    this.formData = {
                        nama: '',
                        username: '',
                        email: '',
                        prodi: '',
                        role: 'user'
                    };
                    this.usernameError = '';
                    this.emailError = '';
                    this.editingUser = null;
                },

                emailError: '',

                validateEmail() {
                    if (!this.formData.email) {
                        this.emailError = 'Email wajib diisi';
                        return;
                    }
                    const email = this.formData.email.trim();
                    
                    // Validasi format email dasar
                    const re = /^(([^<>()[\]\\.,;:\s@"]+(\.[^<>()[\]\\.,;:\s@"]+)*)|(\".+\"))@(([^<>()[\]\\.,;:\s@"]+\.)+[^<>()[\]\\.,;:\s@"]{2,})$/i;
                    if (!re.test(email)) {
                        this.emailError = 'Format email tidak valid';
                        return;
                    }
                    
                        // Validasi domain email (harus menggunakan domain email yang valid)
                        const validDomains = ['gmail.com', 'yahoo.com', 'outlook.com', 'hotmail.com', 'icloud.com', 'ft.um-surabaya.ac.id', 'um-surabaya.ac.id'];
                        const domain = email.split('@')[1]?.toLowerCase();
                        
                        if (!validDomains.includes(domain)) {
                            this.emailError = 'Gunakan email dari Gmail, Yahoo, Outlook, atau email institusi';
                            return;
                        }
                        
                        this.emailError = '';
                    },



                validateUsername() {
                    // Hanya validasi jika mode tambah baru (bukan edit)
                    if (!this.editingUser) {
                        if (this.formData.username.length > 0 && this.formData.username.length < 8) {
                            this.usernameError = 'Username/NIM minimal 8 karakter';
                        } else {
                            this.usernameError = '';
                        }
                    }
                    // Re-init icons after validation
                    setTimeout(() => lucide.createIcons(), 50);
                },

                openEdit(user) {
                    this.showForm = true;
                    this.showImport = false;
                    this.editingUser = user;
                    this.formData = {
                        nama: user.nama,
                        username: user.username,
                        email: user.email ?? '',
                        prodi: user.prodi,
                        role: user.role
                    };
                    this.usernameError = ''; // Reset error saat edit
                    
                    this.$nextTick(() => {
                        document.getElementById('formSection').scrollIntoView({ behavior: 'smooth', block: 'start' });
                        setTimeout(() => lucide.createIcons(), 100);
                    });
                }
            }
        }

        // Initialize Lucide icons
        document.addEventListener('DOMContentLoaded', () => {
            lucide.createIcons();
        });
    </script>
</body>
</html>