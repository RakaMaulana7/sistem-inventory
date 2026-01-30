<?php
session_start();
require "../config/database.php";
require "../auth/auth_helper.php";

cek_kemanan_login($pdo);

// 🔐 PROTEKSI LOGIN
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.html");
    exit();
}

// 📡 AMBIL DATA GEDUNG DARI DATABASE
$stmtGedung = $pdo->query("SELECT * FROM gedung ORDER BY nama_gedung ASC");
$gedungList = $stmtGedung->fetchAll(PDO::FETCH_ASSOC);

// 🏢 BUILD OPTIONS MAP DARI DATABASE
$optionsMap = ["Semua" => ["Semua"]];
$gedungNames = ["Semua"];

foreach ($gedungList as $gedung) {
    $namaGedung = $gedung['nama_gedung'];
    $jumlahLantai = (int)$gedung['jumlah_lantai'];
    
    $gedungNames[] = $namaGedung;
    $optionsMap[$namaGedung] = ["Semua"];
    
    for ($i = 1; $i <= $jumlahLantai; $i++) {
        $optionsMap[$namaGedung][] = "Lantai " . $i;
    }
}

// 📡 CONFIG & FILTER
$filterGedung = $_GET['gedung'] ?? 'Semua';
$filterLantai = $_GET['lantai'] ?? 'Semua';

// 🔍 LOGIKA QUERY DINAMIS
$query = "SELECT r.*, g.nama_gedung, g.jumlah_lantai 
          FROM ruangan r 
          INNER JOIN gedung g ON r.gedung_id = g.id 
          WHERE 1=1";
$params = [];

if ($filterGedung !== 'Semua') {
    $query .= " AND g.nama_gedung = ?";
    $params[] = $filterGedung;
}

if ($filterLantai !== 'Semua') {
    $query .= " AND r.lantai = ?";
    $params[] = $filterLantai;
}

$query .= " ORDER BY r.nama_ruangan ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 🛠️ HELPER FUNCTIONS
function cekStatusRuangan($room) {
    $statusBooking = $room['status'] ?? 'Tersedia'; 
    $statusFisik = $room['status_fisik'] ?? 'Baik'; 

    if ($statusFisik === "Perlu Perbaikan") return "Diperbaiki";
    if ($statusBooking === "Dipakai") return "Dipakai";
    
    return "Tersedia";
}

function getStatusStyle($status) {
    return match($status) {
        "Tersedia"   => "bg-emerald-500 text-white shadow-emerald-100",
        "Diperbaiki" => "bg-amber-400 text-amber-900 shadow-amber-100",
        "Dipakai"    => "bg-red-600 text-white shadow-red-100",
        default      => "bg-gray-400 text-white",
    };
}

// 🎯 FUNGSI GROUPING FASILITAS
function groupFacilities($fasilitasString) {
    if (empty($fasilitasString)) {
        return [];
    }
    
    $items = array_map('trim', explode(',', $fasilitasString));
    $grouped = [];
    
    foreach ($items as $item) {
        // Pisahkan nama fasilitas dari kode (misal: kursiKRS-1 → kursi)
        $baseName = preg_replace('/[A-Z0-9\-_].*/u', '', $item);
        
        // Jika hasil kosong, ambil kata pertama
        if (empty($baseName)) {
            $baseName = preg_split('/[A-Z0-9\-_]/', $item)[0];
        }
        
        // Normalisasi: huruf pertama kapital
        $baseName = ucfirst(strtolower(trim($baseName)));
        
        if (isset($grouped[$baseName])) {
            $grouped[$baseName]++;
        } else {
            $grouped[$baseName] = 1;
        }
    }
    
    return $grouped;
}

$lantaiOptions = $optionsMap[$filterGedung] ?? ["Semua"];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../assets/kampusums.png" />
    <title>Daftar Ruangan | Inventory FT</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap');
        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            scroll-behavior: smooth;
        }
        nav, header { z-index: 100 !important; }
        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .sticky-filter {
            position: sticky;
            top: 100px;
            z-index: 40;
        }
    </style>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: '#10b981',
                        brandHover: '#059669',
                        brandSecondary: '#3b82f6',
                        brandSecondaryHover: '#2563eb',
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-[#f8fafc] text-slate-900">

    <?php include "../components/UserNavbar.php"; ?>

    <main class="pt-7 pb-20 px-4 md:px-8 max-w-[1440px] mx-auto relative z-10">    
        <div class="flex flex-col md:flex-row justify-between items-start md:items-end mb-10 gap-6">
            <div>
                <h1 class="text-4xl font-extrabold tracking-tight text-slate-900">
                    Daftar <span class="bg-clip-text text-transparent bg-gradient-to-r from-brand to-brandSecondary">Ruangan</span>
                </h1>
                <p class="text-slate-500 mt-2 text-lg">Temukan dan pinjam ruangan untuk kegiatan Anda.</p>
            </div>
            <button onclick="window.location.href='dashboard.php'" class="group flex items-center gap-2 bg-white border border-slate-200 px-6 py-3 rounded-2xl font-bold text-slate-700 hover:bg-green-50 hover:border-brand transition-all shadow-sm">
                <i data-lucide="arrow-left" class="w-4 h-4 group-hover:-translate-x-1 transition-transform"></i> Kembali
            </button>
        </div>

        <form method="GET" class="bg-white p-2 rounded-3xl shadow-sm border border-slate-200 mb-10 flex flex-col md:flex-row gap-2 sticky-filter">
            <div class="flex-1 p-4">
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1 ml-1">Pilih Gedung</label>
                <select name="gedung" onchange="this.form.submit()" class="w-full bg-transparent font-bold text-slate-700 outline-none cursor-pointer">
                    <?php foreach ($gedungNames as $g): ?>
                        <option value="<?= htmlspecialchars($g) ?>" <?= $filterGedung == $g ? 'selected' : '' ?>>
                            <?= htmlspecialchars($g) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="hidden md:block w-px h-12 bg-slate-100 self-center"></div>
            <div class="flex-1 p-4">
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1 ml-1">Pilih Lantai</label>
                <select name="lantai" onchange="this.form.submit()" class="w-full bg-transparent font-bold text-slate-700 outline-none cursor-pointer">
                    <?php foreach ($lantaiOptions as $l): ?>
                        <option value="<?= htmlspecialchars($l) ?>" <?= $filterLantai == $l ? 'selected' : '' ?>>
                            <?= htmlspecialchars($l) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8">
            <?php if (empty($rooms)): ?>
                <div class="col-span-full py-24 bg-white rounded-[40px] border-2 border-dashed border-slate-200 flex flex-col items-center">
                    <i data-lucide="search-x" class="text-slate-300 w-16 h-16 mb-4"></i>
                    <p class="text-xl font-bold text-slate-400">Ruangan tidak ditemukan</p>
                    <a href="ruangan.php" class="mt-4 text-brand font-bold hover:underline">Reset Filter</a>
                </div>
            <?php else: ?>
                <?php foreach ($rooms as $r): 
                    $status = cekStatusRuangan($r);
                    $locked = ($status !== "Tersedia");
                    $fasilitasGrouped = groupFacilities($r['fasilitas'] ?? '');
                ?>
                <div class="group bg-white rounded-4xl border border-slate-100 shadow-sm hover:shadow-2xl hover:-translate-y-2 transition-all duration-500 flex flex-col overflow-hidden">
                    <div class="relative h-60 overflow-hidden bg-slate-200">
                        <img src="<?= htmlspecialchars($r['photo'] ?: '../assets/img/default-room.jpg') ?>" 
                             alt="Room" 
                             class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700">
                        
                        <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-transparent opacity-60"></div>
                        
                        <div class="absolute top-4 right-4 shadow-xl">
                            <span class="px-4 py-1.5 rounded-full text-[10px] font-black uppercase tracking-widest <?= getStatusStyle($status) ?>">
                                <?= $status ?>
                            </span>
                        </div>

                        <div class="absolute bottom-4 left-4">
                            <div class="flex items-center gap-2 bg-white/20 backdrop-blur-md px-3 py-1.5 rounded-xl border border-white/30 text-white text-xs font-bold uppercase">
                                <i data-lucide="map-pin" class="w-3.5 h-3.5"></i>
                                <?= htmlspecialchars($r['nama_gedung']) ?> • <?= htmlspecialchars($r['lantai']) ?>
                            </div>
                        </div>
                    </div>

                    <div class="p-6 flex flex-col flex-1">
                        <div class="flex justify-between items-start mb-4">
                            <h3 class="text-xl font-extrabold text-slate-800 leading-tight group-hover:text-brand">
                                <?= htmlspecialchars($r['nama_ruangan']) ?>
                            </h3>
                            <div class="flex items-center gap-1.5 bg-slate-50 px-2.5 py-1 rounded-lg border border-slate-100 text-sm font-bold text-slate-700">
                                <i data-lucide="users" class="w-4 h-4 text-brand"></i>
                                <?= $r['kapasitas'] ?>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2 mb-6">
                            <?php 
                            if (!empty($fasilitasGrouped)):
                                $count = 0;
                                foreach ($fasilitasGrouped as $fasilitas => $jumlah): 
                                    if ($count < 3): ?>
                                        <span class="text-[10px] font-bold text-slate-500 bg-slate-50 border border-slate-100 px-2.5 py-1 rounded-md uppercase tracking-tighter">
                                            <?= htmlspecialchars($fasilitas) ?> (<?= $jumlah ?>)
                                        </span>
                                    <?php 
                                    $count++;
                                    endif;
                                endforeach;
                                
                                if (count($fasilitasGrouped) > 3): 
                                    $remaining = count($fasilitasGrouped) - 3;
                                ?>
                                    <span class="text-[10px] font-bold text-brand bg-green-50 px-2 py-1 rounded-md">+<?= $remaining ?></span>
                                <?php endif;
                            else: ?>
                                <span class="text-[10px] font-bold text-slate-300 italic uppercase">Fasilitas Standar</span>
                            <?php endif; ?>
                        </div>

                        <p class="text-slate-500 text-sm mb-8 line-clamp-2 italic">
                            "<?= htmlspecialchars($r['deskripsi'] ?: 'Ruangan serbaguna untuk kegiatan akademik.') ?>"
                        </p>

                        <div class="mt-auto">
                            <?php if ($locked): ?>
                                <button disabled class="w-full py-4 bg-slate-300 text-slate-400 rounded-2xl font-bold text-xs uppercase tracking-widest cursor-not-allowed border border-slate-200">
                                    Sedang <?= $status ?>
                                </button>
                            <?php else: ?>
                                <a href="peminjaman_ruangan.php?id=<?= $r['id'] ?>" class="block w-full py-4 bg-gradient-to-r from-brand to-brandSecondary hover:from-brandHover hover:to-brandSecondaryHover text-white rounded-2xl font-bold text-xs text-center uppercase tracking-widest transition-all shadow-lg shadow-green-100 active:scale-95">
                                    Ajukan Pinjaman
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <?php include "../components/Footer.php"; ?>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>