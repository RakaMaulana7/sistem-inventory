<?php
session_start();
require "../config/database.php";
require "../auth/auth_helper.php";

cek_kemanan_login($pdo);

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.html");
    exit();
}

// 1. LOGIKA FILTER & SEARCH
$filterJenis = $_GET['jenis'] ?? 'Semua';
$filterBarang = $_GET['barang'] ?? 'Semua'; // FILTER BARU
$search = $_GET['search'] ?? '';

// 1a. AMBIL DAFTAR NAMA BARANG UNIK UNTUK DROPDOWN
$queryBarang = "SELECT DISTINCT nama FROM sarana ORDER BY nama ASC";
$stmtBarang = $pdo->prepare($queryBarang);
$stmtBarang->execute();
$daftarBarang = $stmtBarang->fetchAll(PDO::FETCH_COLUMN);

// 2. QUERY DATABASE dengan JOIN untuk cek status peminjaman
$query = "
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
    WHERE 1=1
";

$params = [];

if ($filterJenis !== 'Semua') {
    $query .= " AND s.jenis = ?";
    $params[] = $filterJenis;
}

// FILTER BERDASARKAN NAMA BARANG
if ($filterBarang !== 'Semua') {
    $query .= " AND s.nama = ?";
    $params[] = $filterBarang;
}

if (!empty($search)) {
    $query .= " AND s.nama LIKE ?";
    $params[] = "%$search%";
}

$query .= " ORDER BY s.id DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 3. HELPER UNTUK STYLE KONDISI
function getConditionStyle($kondisi) {
    switch ($kondisi) {
        case "Baik": return "bg-emerald-100 text-emerald-700 border-emerald-200";
        case "Rusak Ringan": return "bg-amber-100 text-amber-700 border-amber-200";
        case "Rusak Berat": return "bg-red-100 text-red-700 border-red-200";
        default: return "bg-gray-100 text-gray-700";
    }
}

// 4. HELPER UNTUK ICON (Lucide disimulasikan dengan class atau SVG)
function getConditionIcon($kondisi) {
    if ($kondisi === "Baik") return '<i data-lucide="check-circle" class="w-3.5 h-3.5"></i>';
    if ($kondisi === "Rusak Ringan") return '<i data-lucide="alert-triangle" class="w-3.5 h-3.5"></i>';
    return '<i data-lucide="x-circle" class="w-3.5 h-3.5"></i>';
}

// 5. HELPER UNTUK STATUS PINJAM
function getStatusPinjamStyle($status) {
    if ($status === 'Tersedia') {
        return "bg-emerald-600 text-white";
    } else {
        return "bg-red-600 text-white";
    }
}

function getStatusPinjamIcon($status) {
    if ($status === 'Tersedia') {
        return '<div class="w-1.5 h-1.5 rounded-full bg-white animate-pulse"></div>';
    } else {
        return '<i data-lucide="lock" class="w-3 h-3"></i>';
    }
}

// 6. HELPER UNTUK STYLE TIPE BARANG
function getTipeBarangStyle($tipe) {
    switch ($tipe) {
        case "Inventaris": return "bg-indigo-50 text-indigo-700 border-indigo-200";
        case "Habis Pakai": return "bg-rose-50 text-rose-700 border-rose-200";
        default: return "bg-gray-50 text-gray-700 border-gray-200";
    }
}

function getTipeBarangIcon($tipe) {
    if ($tipe === "Inventaris") return '<i data-lucide="archive" class="w-3 h-3"></i>';
    if ($tipe === "Habis Pakai") return '<i data-lucide="package-x" class="w-3 h-3"></i>';
    return '<i data-lucide="package" class="w-3 h-3"></i>';
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../assets/kampusums.png" />
    <title>Daftar Sarana | Inventory FT</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .line-clamp-1 { display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; overflow: hidden; }
        .line-clamp-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .sticky-filter { position: sticky; top: 100px; z-index: 40; }
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
<body class="bg-gray-50 flex flex-col min-h-screen">

    <?php include "../components/UserNavbar.php"; ?>

    <main class="pt-7 pb-20 px-4 md:px-8 w-full max-w-[1400px] mx-auto flex-1">
        
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-10 gap-6">
            <div>
                <h1 class="text-3xl md:text-4xl font-extrabold text-gray-900 tracking-tight">
                    Daftar <span class="bg-clip-text text-transparent bg-gradient-to-r from-brand to-brandSecondary">Sarana</span>
                </h1>
                <p class="text-gray-500 mt-2 text-lg">
                    Temukan peralatan dan fasilitas penunjang kegiatan kampus.
                </p>
            </div>
            <button onclick="window.location.href='dashboard.php'" class="group flex items-center gap-2 bg-white border border-slate-200 px-6 py-3 rounded-2xl font-bold text-slate-700 hover:bg-green-50 hover:border-brand transition-all shadow-sm">
                <i data-lucide="arrow-left" class="w-4 h-4 group-hover:-translate-x-1 transition-transform"></i> Kembali
            </button>
        </div>

        <form method="GET" class="bg-white p-2 rounded-3xl shadow-sm border border-slate-200 mb-10 flex flex-col md:flex-row gap-2 sticky-filter">
            <div class="flex-1 relative group">
                <i data-lucide="search" class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 group-focus-within:text-brand transition-colors w-5 h-5"></i>
                <input
                    type="text"
                    name="search"
                    placeholder="Cari nama sarana..."
                    value="<?= htmlspecialchars($search) ?>"
                    class="w-full pl-12 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl outline-none focus:bg-white focus:ring-2 focus:ring-brand/20 focus:border-brand transition-all"
                >
            </div>

            <!-- FILTER JENIS -->
            <div class="relative min-w-[220px]">
                <i data-lucide="filter" class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-500 w-4 h-4"></i>
                <select
                    name="jenis"
                    onchange="this.form.submit()"
                    class="w-full pl-11 pr-10 py-3 bg-white border border-gray-200 rounded-xl appearance-none outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand cursor-pointer font-medium text-gray-700 transition-all"
                >
                    <?php 
                    $options = ["Semua", "Elektronik", "Furniture", "Peralatan Umum"];
                    foreach($options as $opt): 
                    ?>
                        <option value="<?= $opt ?>" <?= $filterJenis == $opt ? 'selected' : '' ?>><?= $opt ?></option>
                    <?php endforeach; ?>
                </select>
                <i data-lucide="chevron-down" class="absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-gray-400 w-4 h-4"></i>
            </div>

            <!-- FILTER NAMA BARANG (BARU) -->
            <div class="relative min-w-[220px]">
                <i data-lucide="package" class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-500 w-4 h-4"></i>
                <select
                    name="barang"
                    onchange="this.form.submit()"
                    class="w-full pl-11 pr-10 py-3 bg-white border border-gray-200 rounded-xl appearance-none outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand cursor-pointer font-medium text-gray-700 transition-all"
                >
                    <option value="Semua" <?= $filterBarang == 'Semua' ? 'selected' : '' ?>>Semua Barang</option>
                    <?php foreach($daftarBarang as $namaBarang): ?>
                        <option value="<?= htmlspecialchars($namaBarang) ?>" <?= $filterBarang == $namaBarang ? 'selected' : '' ?>>
                            <?= htmlspecialchars($namaBarang) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <i data-lucide="chevron-down" class="absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-gray-400 w-4 h-4"></i>
            </div>
        </form>

        <!-- INFO BADGE FILTER AKTIF -->
        <?php if ($filterJenis !== 'Semua' || $filterBarang !== 'Semua' || !empty($search)): ?>
        <div class="mb-6 flex flex-wrap items-center gap-2">
            <span class="text-sm font-semibold text-gray-600">Filter aktif:</span>
            
            <?php if ($filterJenis !== 'Semua'): ?>
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-100 text-blue-700 rounded-full text-xs font-bold">
                <i data-lucide="tag" class="w-3 h-3"></i>
                Jenis: <?= htmlspecialchars($filterJenis) ?>
                <a href="?jenis=Semua&barang=<?= urlencode($filterBarang) ?>&search=<?= urlencode($search) ?>" class="ml-1 hover:text-blue-900">
                    <i data-lucide="x" class="w-3 h-3"></i>
                </a>
            </span>
            <?php endif; ?>

            <?php if ($filterBarang !== 'Semua'): ?>
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-purple-100 text-purple-700 rounded-full text-xs font-bold">
                <i data-lucide="package" class="w-3 h-3"></i>
                Barang: <?= htmlspecialchars($filterBarang) ?>
                <a href="?jenis=<?= urlencode($filterJenis) ?>&barang=Semua&search=<?= urlencode($search) ?>" class="ml-1 hover:text-purple-900">
                    <i data-lucide="x" class="w-3 h-3"></i>
                </a>
            </span>
            <?php endif; ?>

            <?php if (!empty($search)): ?>
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-green-100 text-green-700 rounded-full text-xs font-bold">
                <i data-lucide="search" class="w-3 h-3"></i>
                Pencarian: "<?= htmlspecialchars($search) ?>"
                <a href="?jenis=<?= urlencode($filterJenis) ?>&barang=<?= urlencode($filterBarang) ?>" class="ml-1 hover:text-green-900">
                    <i data-lucide="x" class="w-3 h-3"></i>
                </a>
            </span>
            <?php endif; ?>

            <a href="?" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-red-100 text-red-700 rounded-full text-xs font-bold hover:bg-red-200 transition-colors">
                <i data-lucide="x-circle" class="w-3 h-3"></i>
                Hapus Semua Filter
            </a>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8">
            <?php if (empty($items)): ?>
                <div class="col-span-full flex flex-col items-center justify-center py-20 bg-white rounded-3xl border border-dashed border-gray-300 text-center">
                    <div class="bg-gray-100 p-4 rounded-full mb-4">
                        <i data-lucide="box" class="text-gray-400 w-8 h-8"></i>
                    </div>
                    <p class="text-xl font-semibold text-gray-800">Tidak ada sarana ditemukan</p>
                    <p class="text-gray-500 mt-1">Coba ubah kata kunci atau filter pencarian Anda.</p>
                </div>
            <?php else: ?>
                <?php foreach ($items as $item): ?>
                <div class="group bg-white rounded-3xl overflow-hidden border border-gray-100 shadow-sm hover:shadow-xl hover:-translate-y-2 transition-all duration-300 flex flex-col h-full <?= $item['status_pinjam'] === 'Dipinjam' ? 'opacity-75' : '' ?>">
                    
                    <div class="relative h-52 overflow-hidden bg-gray-100">
                        <?php if (!empty($item['photo'])): ?>
                            <img src="<?= htmlspecialchars($item['photo']) ?>" alt="<?= $item['nama'] ?>" class="object-cover w-full h-full transform group-hover:scale-110 transition-transform duration-700 <?= $item['status_pinjam'] === 'Dipinjam' ? 'grayscale' : '' ?>">
                        <?php else: ?>
                            <div class="flex flex-col items-center justify-center h-full text-gray-400">
                                <i data-lucide="info" class="mb-2 opacity-50 w-8 h-8"></i>
                                <span class="text-sm font-medium">Tidak ada foto</span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="absolute inset-0 bg-gradient-to-t from-black/50 via-transparent to-transparent opacity-60"></div>

                        <!-- BADGE KONDISI (Kanan Atas) -->
                        <div class="absolute top-4 right-4 flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[10px] font-black uppercase tracking-widest border shadow-sm backdrop-blur-md <?= getConditionStyle($item['kondisi']) ?>">
                            <?= getConditionIcon($item['kondisi']) ?>
                            <?= $item['kondisi'] ?>
                        </div>

                        <!-- BADGE TIPE BARANG (Kiri Atas) -->
                        <?php if (!empty($item['tipe_barang'])): ?>
                        <div class="absolute top-4 left-4 flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[10px] font-black uppercase tracking-widest border shadow-sm backdrop-blur-md <?= getTipeBarangStyle($item['tipe_barang']) ?>">
                            <?= getTipeBarangIcon($item['tipe_barang']) ?>
                            <?= htmlspecialchars($item['tipe_barang']) ?>
                        </div>
                        <?php endif; ?>

                        <!-- BADGE STATUS PEMINJAMAN (Kiri Bawah) -->
                        <div class="absolute bottom-4 left-4">
                            <div class="px-3 py-1 rounded-lg text-[10px] font-black text-white shadow-lg flex items-center gap-2 <?= getStatusPinjamStyle($item['status_pinjam']) ?> uppercase tracking-tighter">
                                <?= getStatusPinjamIcon($item['status_pinjam']) ?>
                                <?php if ($item['status_pinjam'] === 'Tersedia'): ?>
                                    Tersedia (<?= $item['jumlah'] ?> Unit)
                                <?php else: ?>
                                    Sedang Dipinjam
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="p-6 flex flex-col flex-1">
                        <div class="mb-3">
                            <h3 class="text-lg font-extrabold text-gray-900 mb-2 group-hover:text-brand transition-colors line-clamp-1 leading-tight">
                                <?= htmlspecialchars($item['nama']) ?>
                            </h3>
                            
                            <!-- BADGE KODE LABEL & TAHUN BELI -->
                            <div class="flex items-center gap-2 flex-wrap">
                                <?php if (!empty($item['kode_label'])): ?>
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-blue-50 border border-blue-200 rounded-lg text-[10px] font-bold text-blue-700 uppercase tracking-wider">
                                    <i data-lucide="hash" class="w-3 h-3"></i>
                                    <?= htmlspecialchars($item['kode_label']) ?>
                                </span>
                                <?php endif; ?>
                                
                                <?php if (!empty($item['tahun_beli'])): ?>
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-purple-50 border border-purple-200 rounded-lg text-[10px] font-bold text-purple-700 uppercase tracking-wider">
                                    <i data-lucide="calendar" class="w-3 h-3"></i>
                                    <?php
                                        // Format tanggal dari YYYY-MM-DD menjadi "dd MMM yyyy"
                                        $tanggalBeli = $item['tahun_beli'];
                                        if (strtotime($tanggalBeli)) {
                                            echo date('d M Y', strtotime($tanggalBeli));
                                        } else {
                                            echo htmlspecialchars($tanggalBeli);
                                        }
                                    ?>
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="space-y-3 mb-6">
                            <div class="flex items-center gap-2.5 text-sm text-gray-600 font-semibold">
                                <i data-lucide="box" class="w-4 h-4 text-brand"></i>
                                <span><?= $item['jenis'] ?></span>
                            </div>
                            <div class="flex items-center gap-2.5 text-sm text-gray-600 font-semibold">
                                <i data-lucide="map-pin" class="w-4 h-4 text-brandSecondary"></i>
                                <span class="break-all whitespace-normal"><?= $item['lokasi'] ?: '-' ?></span>
                            </div>
                        </div>

                        <p class="text-gray-500 text-sm line-clamp-2 mb-8 flex-1 italic">
                            "<?= $item['deskripsi'] ?: 'Tidak ada deskripsi tambahan.' ?>"
                        </p>

                        <?php if ($item['status_pinjam'] === 'Tersedia'): ?>
                            <a href="peminjaman_sarana.php?id=<?= $item['id'] ?>" 
                               class="w-full py-4 rounded-2xl font-bold text-xs text-white bg-gradient-to-r from-brand to-brandSecondary hover:from-brandHover hover:to-brandSecondaryHover shadow-lg shadow-green-100 text-center uppercase tracking-widest transition-all transform active:scale-95 flex items-center justify-center gap-2">
                                Lihat Detail <i data-lucide="arrow-right" class="w-4 h-4"></i>
                            </a>
                        <?php else: ?>
                            <button disabled 
                                    class="w-full py-4 rounded-2xl font-bold text-xs text-white bg-gray-400 cursor-not-allowed text-center uppercase tracking-widest flex items-center justify-center gap-2 opacity-60">
                                <i data-lucide="lock" class="w-4 h-4"></i>
                                Sedang Dipinjam
                            </button>
                        <?php endif; ?>
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