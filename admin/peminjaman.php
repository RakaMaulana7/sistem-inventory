<?php
session_start();
require "../config/database.php"; 
require "../auth/auth_helper.php";

cek_kemanan_login($pdo);

// Proteksi Admin
if (!isset($_SESSION['login']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.html");
    exit;
}

// Export Excel
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    $activeTab = $_GET['tab'] ?? 'ruangan';
    $month = $_GET['month'] ?? 'all';
    $search = $_GET['search'] ?? '';
    
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="arsip_peminjaman_' . $activeTab . '_' . date('Y-m-d') . '.xls"');
    
    // Dasar Query dengan JOIN untuk ambil nama admin dan data sarana
    $queryStr = "
        SELECT p.*, 
               u.nama as user_nama, 
               u.prodi, 
               usr_admin.nama as nama_admin,
        CASE 
            WHEN p.kategori = 'ruangan' THEN r.nama_ruangan
            WHEN p.kategori = 'sarana' THEN s.nama
            WHEN p.kategori = 'transportasi' THEN t.nama
        END as nama_aset,
        CASE 
            WHEN p.kategori = 'ruangan' THEN r.fasilitas
            ELSE NULL
        END as detail_unit_raw,
        -- Tambah data untuk sarana
        CASE 
            WHEN p.kategori = 'sarana' THEN s.kode_label
            ELSE NULL
        END as kode_label_sarana,
        CASE 
            WHEN p.kategori = 'sarana' THEN s.kondisi
            ELSE NULL
        END as kondisi_sarana
        FROM peminjaman p
        JOIN users u ON p.user_id = u.id
        LEFT JOIN users usr_admin ON p.admin_id = usr_admin.id
        LEFT JOIN ruangan r ON p.item_id = r.id AND p.kategori = 'ruangan'
        LEFT JOIN sarana s ON p.item_id = s.id AND p.kategori = 'sarana'
        LEFT JOIN transportasi t ON p.item_id = t.id AND p.kategori = 'transportasi'
        WHERE p.kategori = :kategori 
        AND p.status IN ('kembali', 'rejected', 'selesai')
    ";

    $bind_params = [':kategori' => $activeTab];
    
    if ($month !== 'all') {
        $queryStr .= " AND MONTH(p.tanggal_mulai) = :month";
        $bind_params[':month'] = $month;
    }

    if (!empty($search)) {
        $queryStr .= " AND (u.nama LIKE :search OR u.prodi LIKE :search OR p.tujuan LIKE :search)";
        $bind_params[':search'] = "%$search%";
    }

    $queryStr .= " ORDER BY p.created_at DESC";

    $stmt = $pdo->prepare($queryStr);
    $stmt->execute($bind_params);
    $riwayat = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Output Excel HTML
    echo '<html>';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<style>';
    echo 'table { border-collapse: collapse; width: 100%; }';
    echo 'th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }';
    echo 'th { background-color: #f2f2f2; font-weight: bold; }';
    echo '.badge { padding: 2px 6px; border-radius: 4px; font-size: 11px; }';
    echo '.badge-selesai { background-color: #d1fae5; color: #065f46; }';
    echo '.badge-ditolak { background-color: #fee2e2; color: #991b1b; }';
    echo '</style>';
    echo '</head>';
    echo '<body>';
    
    echo '<h2>ARSIP PEMINJAMAN ' . strtoupper($activeTab) . '</h2>';
    echo '<p>Tanggal Export: ' . date('d/m/Y H:i:s') . '</p>';
    
    echo '<table>';
    echo '<thead>';
    echo '<tr>';
    echo '<th>No</th>';
    echo '<th>Peminjam</th>';
    echo '<th>Prodi</th>';
    echo '<th>Telepon</th>';
    echo '<th>Aset</th>';
    echo '<th>Kode Label</th>';
    echo '<th>Tanggal Mulai</th>';
    echo '<th>Tanggal Selesai</th>';
    echo '<th>Tujuan</th>';
    echo '<th>Petugas</th>';
    echo '<th>Status</th>';
    echo '<th>Catatan</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    
    $no = 1;
    foreach ($riwayat as $item) {
        // Format status
        $status = strtolower($item['status']);
        if ($status === 'kembali' || $status === 'selesai') {
            $status_display = 'Selesai';
            $status_class = 'badge-selesai';
        } elseif ($status === 'rejected') {
            $status_display = 'Ditolak';
            $status_class = 'badge-ditolak';
        } else {
            $status_display = ucfirst($status);
            $status_class = '';
        }
        
        // Format tanggal
        $tanggal_mulai = date('d/m/Y', strtotime($item['tanggal_mulai']));
        $tanggal_selesai = date('d/m/Y', strtotime($item['tanggal_selesai']));
        
        echo '<tr>';
        echo '<td>' . $no++ . '</td>';
        echo '<td>' . htmlspecialchars($item['user_nama']) . '</td>';
        echo '<td>' . htmlspecialchars($item['prodi']) . '</td>';
        echo '<td>' . (!empty($item['telepon_utama']) ? htmlspecialchars($item['telepon_utama']) : '-') . '</td>';
        echo '<td>' . htmlspecialchars($item['nama_aset']) . '</td>';
        echo '<td>' . (!empty($item['kode_label_sarana']) ? htmlspecialchars($item['kode_label_sarana']) : '-') . '</td>';
        echo '<td>' . $tanggal_mulai . ' ' . (!empty($item['waktu_mulai']) ? $item['waktu_mulai'] : '') . '</td>';
        echo '<td>' . $tanggal_selesai . ' ' . (!empty($item['waktu_selesai']) ? $item['waktu_selesai'] : '') . '</td>';
        echo '<td>' . (!empty($item['tujuan']) ? htmlspecialchars($item['tujuan']) : '-') . '</td>';
        echo '<td>' . (!empty($item['nama_admin']) ? htmlspecialchars($item['nama_admin']) : '-') . '</td>';
        echo '<td><span class="badge ' . $status_class . '">' . $status_display . '</span></td>';
        echo '<td>' . (!empty($item['catatan_petugas']) ? htmlspecialchars($item['catatan_petugas']) : '-') . '</td>';
        echo '</tr>';
    }
    
    echo '</tbody>';
    echo '</table>';
    echo '</body>';
    echo '</html>';
    exit;
}

// Ambil parameter filter untuk tampilan normal
$activeTab = $_GET['tab'] ?? 'ruangan';
$month = $_GET['month'] ?? 'all';
$search = $_GET['search'] ?? '';

$kategori = $activeTab;

// Dasar Query dengan JOIN untuk ambil nama admin dan data sarana
$queryStr = "
    SELECT p.*, 
           u.nama as user_nama, 
           u.prodi, 
           usr_admin.nama as nama_admin,
    CASE 
        WHEN p.kategori = 'ruangan' THEN r.nama_ruangan
        WHEN p.kategori = 'sarana' THEN s.nama
        WHEN p.kategori = 'transportasi' THEN t.nama
    END as nama_aset,
    CASE 
        WHEN p.kategori = 'ruangan' THEN r.fasilitas
        ELSE NULL
    END as detail_unit_raw,
    -- Tambah data untuk sarana
    CASE 
        WHEN p.kategori = 'sarana' THEN s.kode_label
        ELSE NULL
    END as kode_label_sarana,
    CASE 
        WHEN p.kategori = 'sarana' THEN s.kondisi
        ELSE NULL
    END as kondisi_sarana
    FROM peminjaman p
    JOIN users u ON p.user_id = u.id
    LEFT JOIN users usr_admin ON p.admin_id = usr_admin.id
    LEFT JOIN ruangan r ON p.item_id = r.id AND p.kategori = 'ruangan'
    LEFT JOIN sarana s ON p.item_id = s.id AND p.kategori = 'sarana'
    LEFT JOIN transportasi t ON p.item_id = t.id AND p.kategori = 'transportasi'
    WHERE p.kategori = :kategori 
    AND p.status IN ('kembali', 'rejected', 'selesai')
";

// Siapkan array untuk binding parameters
$bind_params = [':kategori' => $kategori];

if ($month !== 'all') {
    $queryStr .= " AND MONTH(p.tanggal_mulai) = :month";
    $bind_params[':month'] = $month;
}

if (!empty($search)) {
    $queryStr .= " AND (u.nama LIKE :search OR u.prodi LIKE :search OR p.tujuan LIKE :search)";
    $bind_params[':search'] = "%$search%";
}

$queryStr .= " ORDER BY p.created_at DESC";

$stmt = $pdo->prepare($queryStr);
$stmt->execute($bind_params);
$riwayat = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Parse data fasilitas dan sarana untuk mengambil catatan
foreach ($riwayat as &$item) {
    $catatan_fasilitas = [];
    
    // UNTUK RUANGAN: Parse fasilitas dari string CSV
    if (!empty($item['detail_unit_raw']) && $item['kategori'] === 'ruangan') {
        $items = explode(', ', trim($item['detail_unit_raw']));
        
        foreach ($items as $facility) {
            $facility = trim($facility);
            if (empty($facility)) continue;
            
            // Parse "Nama Barang [KODE-LABEL]"
            if (preg_match('/^(.+?)\s*\[(.+?)\]$/', $facility, $matches)) {
                $namaBarang = trim($matches[1]);
                $kodeLabel = trim($matches[2]);
                
                // Ambil kondisi dan catatan dari database sarana
                $stmtFasilitas = $pdo->prepare("SELECT kondisi, catatan FROM sarana WHERE kode_label = ? LIMIT 1");
                $stmtFasilitas->execute([$kodeLabel]);
                $dataFasilitas = $stmtFasilitas->fetch(PDO::FETCH_ASSOC);
                
                if ($dataFasilitas && !empty($dataFasilitas['catatan']) && $dataFasilitas['catatan'] !== '-') {
                    $catatan_fasilitas[] = [
                        'label' => $kodeLabel,
                        'nama' => $namaBarang,
                        'kondisi' => $dataFasilitas['kondisi'],
                        'catatan' => $dataFasilitas['catatan']
                    ];
                }
            }
        }
    }
    
    // UNTUK SARANA: Ambil catatan dari sarana
    if (!empty($item['kode_label_sarana']) && $item['kategori'] === 'sarana') {
        $stmtSarana = $pdo->prepare("SELECT kondisi, catatan FROM sarana WHERE kode_label = ? LIMIT 1");
        $stmtSarana->execute([$item['kode_label_sarana']]);
        $dataSarana = $stmtSarana->fetch(PDO::FETCH_ASSOC);
        
        if ($dataSarana && !empty($dataSarana['catatan']) && $dataSarana['catatan'] !== '-') {
            $catatan_fasilitas[] = [
                'label' => $item['kode_label_sarana'],
                'nama' => $item['nama_aset'],
                'kondisi' => $dataSarana['kondisi'],
                'catatan' => $dataSarana['catatan']
            ];
        }
    }
    
    $item['catatan_fasilitas'] = $catatan_fasilitas;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../assets/kampusums.png" />
    <title>Arsip Peminjaman</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }
        .wrap-break-word { overflow-wrap: break-word; word-break: break-word; }
        
        @media print {
            /* Reset semua */
            body * { 
                visibility: hidden; 
            }
            
            /* Tampilkan hanya print-section */
            .print-section, .print-section * { 
                visibility: visible; 
            }
            
            .print-section { 
                position: absolute; 
                left: 0; 
                top: 0; 
                width: 100%; 
            }
            
            /* Sembunyikan elemen no-print */
            .no-print { 
                display: none !important; 
            }
            
            /* Styling khusus print */
            body {
                background: white;
                margin: 0;
                padding: 20px;
            }
            
            table {
                border-collapse: collapse;
                width: 100%;
                font-size: 10px;
            }
            
            th, td {
                border: 1px solid #ddd;
                padding: 8px;
                text-align: left;
            }
            
            th {
                background-color: #f3f4f6 !important;
                font-weight: bold;
            }
            
            /* Tambahkan header print */
            .print-section::before {
                content: "ARSIP PEMINJAMAN - <?= strtoupper($activeTab) ?>";
                display: block;
                text-align: center;
                font-size: 18px;
                font-weight: bold;
                margin-bottom: 20px;
                padding-bottom: 10px;
                border-bottom: 2px solid #333;
            }
            
            /* Page break */
            tr {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body class="bg-gray-50 font-sans" x-data="{ 
    isDeleteModal: false, 
    isDeleteAllModal: false,
    deleteId: null,
    isPhotoModal: false,
    photoUrl: '',
    isCatatanModal: false,
    catatanData: []
}">

    <?php include "../components/AdminNavbar.php"; ?>

    <main class="flex-1 ml-0 md:ml-60 p-4 md:p-8 transition-all">
        
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4 pt-20 md:pt-0">
            <div>
                <h1 class="text-3xl font-bold text-gray-800">Arsip Peminjaman</h1>
                <p class="text-gray-500 mt-1">Rekapitulasi data peminjaman yang telah selesai atau ditolak.</p>
            </div>
            
            <div class="bg-white p-1 rounded-xl border border-gray-200 flex shadow-sm no-print">
                <a href="?tab=ruangan" class="flex items-center gap-2 px-5 py-2 rounded-lg text-sm font-medium transition-all <?= $activeTab === 'ruangan' ? 'bg-[#d13b1f] text-white shadow' : 'text-gray-600 hover:bg-gray-50' ?>">
                    <i data-lucide="building-2" class="w-4 h-4"></i> Ruangan
                </a>
                <a href="?tab=sarana" class="flex items-center gap-2 px-5 py-2 rounded-lg text-sm font-medium transition-all <?= $activeTab === 'sarana' ? 'bg-blue-600 text-white shadow' : 'text-gray-600 hover:bg-gray-50' ?>">
                    <i data-lucide="wrench" class="w-4 h-4"></i> Sarana
                </a>
                <a href="?tab=transportasi" class="flex items-center gap-2 px-5 py-2 rounded-lg text-sm font-medium transition-all <?= $activeTab === 'transportasi' ? 'bg-green-600 text-white shadow' : 'text-gray-600 hover:bg-gray-50' ?>">
                    <i data-lucide="truck" class="w-4 h-4"></i> Transportasi
                </a>
            </div>
        </div>

        <form method="GET" class="bg-white p-4 rounded-2xl shadow-sm border border-gray-100 mb-6 flex flex-col xl:flex-row gap-4 justify-between items-center no-print">
            <input type="hidden" name="tab" value="<?= htmlspecialchars($activeTab) ?>">
            
            <div class="flex flex-col sm:flex-row gap-3 w-full xl:w-auto">
                <div class="relative group w-full sm:w-64">
                    <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 w-4 h-4"></i>
                    <input type="text" name="search" placeholder="Cari peminjam..." value="<?= htmlspecialchars($search) ?>" class="w-full pl-10 pr-4 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm outline-none focus:ring-2 focus:ring-blue-500/20">
                </div>

                <div class="relative w-full sm:w-48">
                    <i data-lucide="calendar" class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 w-4 h-4"></i>
                    <select name="month" onchange="this.form.submit()" class="w-full pl-10 pr-4 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm appearance-none cursor-pointer">
                        <option value="all">Semua Bulan</option>
                        <?php
                        $bulan = ["Januari", "Februari", "Maret", "April", "Mei", "Juni", "Juli", "Agustus", "September", "Oktober", "November", "Desember"];
                        foreach ($bulan as $idx => $m) {
                            $v = $idx + 1;
                            $selected = ($month == $v) ? 'selected' : '';
                            echo "<option value='$v' $selected>$m</option>";
                        }
                        ?>
                    </select>
                </div>
                
                <button type="submit" class="px-4 py-2 bg-[#d13b1f] text-white rounded-xl text-sm font-semibold hover:bg-[#b53118] shadow-sm transition-all flex items-center gap-2">
                    <i data-lucide="search" class="w-4 h-4"></i> Cari
                </button>
            </div>

            <div class="flex gap-3 w-full xl:w-auto justify-end">
                <?php if (!empty($riwayat)): ?>
                <button type="button" @click="isDeleteAllModal = true" class="flex items-center gap-2 px-4 py-2 bg-red-600 text-white rounded-xl text-sm font-semibold hover:bg-red-700 shadow-sm transition-all">
                    <i data-lucide="trash-2" class="w-4 h-4"></i> Hapus Semua
                </button>
                <?php endif; ?>

                <button type="button" onclick="window.print()" class="flex items-center gap-2 px-4 py-2 bg-red-50 text-red-700 border border-red-200 rounded-xl text-sm font-semibold hover:bg-red-100">
                    <i data-lucide="printer" class="w-4 h-4"></i> Print
                </button>
                <button type="button" onclick="exportExcel()" class="flex items-center gap-2 px-4 py-2 bg-green-600 text-white rounded-xl text-sm font-semibold hover:bg-green-700 shadow-sm transition-all">
                    <i data-lucide="file-spreadsheet" class="w-4 h-4"></i> Export Excel
                </button>
                <button type="button" onclick="exportPDF()" class="flex items-center gap-2 px-4 py-2 bg-red-600 text-white rounded-xl text-sm font-semibold hover:bg-red-700 shadow-sm transition-all">
                    <i data-lucide="file-text" class="w-4 h-4"></i> Export PDF
                </button>
            </div>
        </form>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden print-section">
            <?php if (!empty($search) || $month !== 'all'): ?>
            <div class="px-6 py-3 bg-blue-50 border-b border-blue-100 flex items-center justify-between">
                <div class="flex items-center gap-2 text-sm">
                    <i data-lucide="filter" class="w-4 h-4 text-blue-600"></i>
                    <span class="text-blue-700">
                        Filter aktif: 
                        <?php if ($month !== 'all'): ?>
                            Bulan <?= $bulan[$month-1] ?>
                        <?php endif; ?>
                        <?php if (!empty($search)): ?>
                            <?php if ($month !== 'all'): ?> • <?php endif; ?>
                            Pencarian: "<?= htmlspecialchars($search) ?>"
                        <?php endif; ?>
                    </span>
                </div>
                <a href="?tab=<?= htmlspecialchars($activeTab) ?>" class="text-xs text-blue-600 hover:text-blue-800 hover:underline flex items-center gap-1">
                    <i data-lucide="x-circle" class="w-3 h-3"></i> Hapus Filter
                </a>
            </div>
            <?php endif; ?>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm" id="arsipTable">
                    <thead>
                        <tr class="bg-gray-50 text-gray-500 border-b border-gray-200 text-xs uppercase tracking-wider">
                            <th class="py-4 px-6 font-semibold">Peminjam</th>
                            <th class="py-4 px-6 font-semibold">Petugas</th>
                            <th class="py-4 px-6 font-semibold">Prodi</th>
                            <th class="py-4 px-6 font-semibold">Kontak</th>
                            <th class="py-4 px-6 font-semibold">Aset</th>
                            <th class="py-4 px-6 font-semibold">Waktu Pemakaian</th>
                            <th class="py-4 px-6 font-semibold">Catatan</th>
                            <th class="py-4 px-6 font-semibold text-center no-print">Bukti</th>
                            <th class="py-4 px-6 font-semibold text-center">Status</th>
                            <th class="py-4 px-6 font-semibold text-center no-print">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if (empty($riwayat)): ?>
                            <tr>
                                <td colspan="10" class="py-20 text-center text-gray-500">
                                    <i data-lucide="inbox" class="w-12 h-12 mx-auto mb-3 opacity-30"></i>
                                    <p>Tidak ada riwayat ditemukan</p>
                                    <?php if (!empty($search) || $month !== 'all'): ?>
                                    <a href="?tab=<?= htmlspecialchars($activeTab) ?>" class="inline-flex items-center gap-1 mt-3 text-sm text-[#d13b1f] hover:underline">
                                        <i data-lucide="arrow-left" class="w-4 h-4"></i>
                                        Tampilkan semua data
                                    </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php else: foreach ($riwayat as $item): ?>
                            <tr class="hover:bg-gray-50/60 transition-colors">
                                <!-- KOLOM PEMINJAM -->
                                <td class="py-4 px-6">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
                                            <i data-lucide="user" class="w-4 h-4"></i>
                                        </div>
                                        <p class="font-semibold text-gray-800"><?= htmlspecialchars($item['user_nama']) ?></p>
                                    </div>
                                </td>

                                <!-- KOLOM PETUGAS (Admin yang melakukan aksi) -->
                                <td class="py-4 px-6">
                                    <?php if (!empty($item['nama_admin'])): ?>
                                        <div class="flex items-center gap-2">
                                            <div class="w-6 h-6 rounded-full bg-purple-50 text-purple-600 flex items-center justify-center shrink-0">
                                                <i data-lucide="shield-check" class="w-3 h-3"></i>
                                            </div>
                                            <div>
                                                <p class="text-xs font-bold text-gray-800"><?= htmlspecialchars($item['nama_admin']) ?></p>
                                                <?php
                                                $statusPetugas = '';
                                                $badgeColor = '';
                                                if ($item['status'] === 'rejected') {
                                                    $statusPetugas = 'Menolak';
                                                    $badgeColor = 'text-red-600';
                                                } elseif ($item['status'] === 'kembali' || $item['status'] === 'selesai') {
                                                    $statusPetugas = 'Memverifikasi';
                                                    $badgeColor = 'text-green-600';
                                                } else {
                                                    $statusPetugas = 'Menyetujui';
                                                    $badgeColor = 'text-blue-600';
                                                }
                                                ?>
                                                <p class="text-[9px] font-semibold uppercase tracking-wider <?= $badgeColor ?>">
                                                    <?= $statusPetugas ?>
                                                </p>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-gray-400 text-xs italic">Sistem</span>
                                    <?php endif; ?>
                                </td>

                                <!-- KOLOM PRODI -->
                                <td class="py-4 px-6 text-gray-600 text-xs font-medium">
                                    <?= htmlspecialchars($item['prodi']) ?>
                                </td>

                                <!-- KOLOM KONTAK -->
                                <td class="py-4 px-6">
                                    <p class="text-xs font-mono text-gray-700">
                                        <?= !empty($item['telepon_utama']) ? htmlspecialchars($item['telepon_utama']) : '-' ?>
                                    </p>
                                    <p class="text-[10px] text-gray-400">
                                        Darurat: <?= !empty($item['telepon_darurat']) ? htmlspecialchars($item['telepon_darurat']) : '-' ?>
                                    </p>
                                </td>

                                <!-- KOLOM ASET -->
                                <td class="py-4 px-6">
                                    <div class="flex flex-col gap-1">
                                        <p class="font-semibold text-gray-800"><?= htmlspecialchars($item['nama_aset']) ?></p>
                                        <?php if ($item['kategori'] === 'sarana' && !empty($item['kode_label_sarana'])): ?>
                                            <span class="font-mono text-[10px] font-bold text-blue-600 bg-blue-50 px-2 py-0.5 rounded inline-block w-fit">
                                                <?= htmlspecialchars($item['kode_label_sarana']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <!-- KOLOM WAKTU PEMAKAIAN -->
                                <td class="py-4 px-6 text-xs text-gray-600">
                                    <div class="space-y-1">
                                        <div class="flex items-center gap-2">
                                            <span class="text-blue-600 font-bold">
                                                <?= date('d/m/Y', strtotime($item['tanggal_mulai'])) ?>
                                            </span>
                                            <span class="text-gray-400 text-[10px]">
                                                <?= !empty($item['waktu_mulai']) ? $item['waktu_mulai'] : '' ?>
                                            </span>
                                        </div>
                                        <span class="text-gray-400 text-[10px]">s/d</span>
                                        <div class="flex items-center gap-2">
                                            <span class="text-orange-600 font-bold">
                                                <?= date('d/m/Y', strtotime($item['tanggal_selesai'])) ?>
                                            </span>
                                            <span class="text-gray-400 text-[10px]">
                                                <?= !empty($item['waktu_selesai']) ? $item['waktu_selesai'] : '' ?>
                                            </span>
                                        </div>
                                    </div>
                                </td>

                                <!-- KOLOM CATATAN FASILITAS/SARANA -->
                                <td class="py-4 px-6">
                                    <?php if (!empty($item['catatan_fasilitas']) && count($item['catatan_fasilitas']) > 0): ?>
                                        <button @click="catatanData = <?= htmlspecialchars(json_encode($item['catatan_fasilitas']), ENT_QUOTES, 'UTF-8') ?>; isCatatanModal = true"
                                                class="flex items-center gap-2 px-3 py-1.5 bg-orange-50 text-orange-600 rounded-lg hover:bg-orange-100 transition-colors text-[11px] font-semibold border border-orange-200">
                                            <i data-lucide="alert-circle" class="w-3 h-3"></i>
                                            <?= count($item['catatan_fasilitas']) ?> Catatan
                                        </button>
                                    <?php else: ?>
                                        <span class="text-gray-400 text-xs italic">Tidak ada catatan</span>
                                    <?php endif; ?>
                                </td>

                                <!-- KOLOM BUKTI -->
                                <td class="py-4 px-6 text-center no-print">
                                    <?php if (!empty($item['foto_pengembalian'])): ?>
                                        <button @click="photoUrl = '../<?= htmlspecialchars($item['foto_pengembalian']) ?>'; isPhotoModal = true" 
                                                class="p-2 bg-purple-50 text-purple-600 rounded-lg hover:bg-purple-100 transition-colors">
                                            <i data-lucide="eye" class="w-4 h-4"></i>
                                        </button>
                                    <?php else: ?>
                                        <span class="text-gray-400 text-xs italic">N/A</span>
                                    <?php endif; ?>
                                </td>

                                <!-- KOLOM STATUS -->
                                <td class="py-4 px-6 text-center">
                                    <?php
                                        $status = strtolower($item['status']);
                                        if ($status === 'kembali' || $status === 'selesai') {
                                            $color = 'bg-green-100 text-green-700 border-green-200';
                                            $displayStatus = 'Selesai';
                                        } elseif ($status === 'rejected') {
                                            $color = 'bg-red-100 text-red-700 border-red-200';
                                            $displayStatus = 'Ditolak';
                                        } else {
                                            $color = 'bg-gray-100 text-gray-700 border-gray-200';
                                            $displayStatus = ucfirst($status);
                                        }
                                    ?>
                                    <span class="inline-flex px-2.5 py-1 rounded-full text-[10px] font-bold uppercase border <?= $color ?>">
                                        <?= $displayStatus ?>
                                    </span>
                                </td>

                                <!-- KOLOM AKSI -->
                                <td class="py-4 px-6 text-center no-print">
                                    <button @click="deleteId = <?= $item['id'] ?>; isDeleteModal = true" 
                                            class="text-gray-400 hover:text-red-600 transition-colors">
                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if (!empty($riwayat)): ?>
            <div class="px-6 py-4 border-t border-gray-100 bg-gray-50 text-xs text-gray-500 flex justify-between items-center">
                <span>Total: <?= count($riwayat) ?> data ditemukan</span>
                <span>Terakhir diupdate: <?= date('d/m/Y H:i:s') ?></span>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- MODAL DELETE SINGLE -->
    <div x-show="isDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm" x-cloak>
        <div class="bg-white rounded-2xl p-6 w-full max-w-sm text-center shadow-2xl" @click.away="isDeleteModal = false">
            <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4 text-red-600">
                <i data-lucide="alert-triangle" class="w-8 h-8"></i>
            </div>
            <h3 class="text-xl font-bold mb-2">Hapus Riwayat?</h3>
            <p class="text-gray-500 text-sm mb-6">Tindakan ini permanen dan tidak dapat dibatalkan.</p>
            <div class="flex gap-3">
                <button @click="isDeleteModal = false" class="flex-1 px-4 py-2 border rounded-xl hover:bg-gray-50">Batal</button>
                <a :href="'hapus_riwayat.php?id=' + deleteId" class="flex-1 px-4 py-2 bg-red-600 text-white rounded-xl hover:bg-red-700">Ya, Hapus</a>
            </div>
        </div>
    </div>

    <!-- MODAL DELETE ALL -->
    <div x-show="isDeleteAllModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm" x-cloak>
        <div class="bg-white rounded-2xl p-6 w-full max-w-sm text-center shadow-2xl" @click.away="isDeleteAllModal = false">
            <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4 text-red-600">
                <i data-lucide="alert-octagon" class="w-8 h-8"></i>
            </div>
            <h3 class="text-xl font-bold mb-2">Hapus Semua Data?</h3>
            <p class="text-gray-500 text-sm mb-6">Seluruh arsip pada kategori <span class="font-bold text-red-600"><?= ucfirst($activeTab) ?></span> akan dihapus permanen.</p>
            <div class="flex gap-3">
                <button @click="isDeleteAllModal = false" class="flex-1 px-4 py-2 border rounded-xl hover:bg-gray-50">Batal</button>
                <a href="hapus_riwayat.php?bulk=true&kategori=<?= $activeTab ?>" class="flex-1 px-4 py-2 bg-red-600 text-white rounded-xl hover:bg-red-700">Ya, Hapus Semua</a>
            </div>
        </div>
    </div>

    <!-- MODAL PHOTO -->
    <div x-show="isPhotoModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm" x-cloak @click="isPhotoModal = false">
        <div class="bg-white rounded-xl max-w-2xl w-full overflow-hidden" @click.stop>
            <div class="p-4 border-b flex justify-between items-center bg-gray-50">
                <h3 class="font-bold flex items-center gap-2">
                    <i data-lucide="image" class="text-purple-600"></i> Bukti Pengembalian
                </h3>
                <button @click="isPhotoModal = false">
                    <i data-lucide="x" class="w-5 h-5 text-gray-500 hover:text-gray-700"></i>
                </button>
            </div>
            <div class="p-4 flex justify-center bg-gray-100">
                <img :src="photoUrl" alt="Bukti Pengembalian" class="max-h-[70vh] rounded-lg shadow-lg">
            </div>
        </div>
    </div>

    <!-- MODAL CATATAN FASILITAS/SARANA -->
    <div x-show="isCatatanModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" x-cloak @click="isCatatanModal = false">
        <div class="bg-white rounded-2xl max-w-2xl w-full overflow-hidden shadow-2xl" @click.stop>
            <div class="p-5 border-b flex justify-between items-center bg-gradient-to-r from-orange-50 to-white">
                <h3 class="font-bold flex items-center gap-2 text-gray-800">
                    <i data-lucide="clipboard-list" class="text-orange-600 w-5 h-5"></i> 
                    Catatan Kondisi <?= $activeTab === 'sarana' ? 'Sarana' : 'Fasilitas' ?>
                </h3>
                <button @click="isCatatanModal = false" class="text-gray-400 hover:text-gray-600">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            <div class="p-6 max-h-[60vh] overflow-y-auto">
                <template x-if="catatanData.length === 0">
                    <p class="text-center text-gray-400 py-8">Tidak ada catatan</p>
                </template>
                
                <div class="space-y-3">
                    <template x-for="(item, index) in catatanData" :key="index">
                        <div class="p-4 bg-gray-50 rounded-xl border border-gray-200 hover:border-orange-300 transition-colors">
                            <div class="flex items-start justify-between mb-3">
                                <div>
                                    <p class="text-xs text-gray-500 font-medium mb-1" x-text="item.nama"></p>
                                    <span class="font-mono text-xs font-bold text-blue-600" x-text="item.label"></span>
                                </div>
                                <span 
                                    :class="{
                                        'bg-emerald-100 text-emerald-700 border-emerald-200': item.kondisi === 'Baik',
                                        'bg-amber-100 text-amber-700 border-amber-200': item.kondisi === 'Rusak Ringan',
                                        'bg-rose-100 text-rose-700 border-rose-200': item.kondisi === 'Rusak Berat'
                                    }" 
                                    class="px-2 py-0.5 rounded-md text-[9px] font-bold uppercase border" 
                                    x-text="item.kondisi">
                                </span>
                            </div>
                            <div class="flex items-start gap-2">
                                <i data-lucide="message-square" class="w-4 h-4 text-orange-500 mt-0.5 shrink-0"></i>
                                <p class="text-sm text-gray-700 leading-relaxed" x-text="item.catatan"></p>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
            <div class="p-4 border-t bg-gray-50 flex justify-end">
                <button @click="isCatatanModal = false" 
                        class="px-5 py-2 bg-gray-200 text-gray-700 rounded-xl hover:bg-gray-300 transition-all font-semibold text-sm">
                    Tutup
                </button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            lucide.createIcons();
        });

        // Fungsi Export PDF
        function exportPDF() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF('landscape', 'mm', 'a4');
            
            // Header
            const kategori = '<?= strtoupper($activeTab) ?>';
            const tanggal = new Date().toLocaleDateString('id-ID', { 
                day: '2-digit', 
                month: 'long', 
                year: 'numeric' 
            });
            
            // Logo/Header
            doc.setFontSize(18);
            doc.setFont('helvetica', 'bold');
            doc.text('ARSIP PEMINJAMAN ' + kategori, doc.internal.pageSize.getWidth() / 2, 15, { align: 'center' });
            
            doc.setFontSize(10);
            doc.setFont('helvetica', 'normal');
            doc.text('Dicetak pada: ' + tanggal, doc.internal.pageSize.getWidth() / 2, 22, { align: 'center' });
            
            // Tambah filter info jika ada
            const month = '<?= $month ?>';
            const search = '<?= addslashes($search) ?>';
            const bulan = ["Januari", "Februari", "Maret", "April", "Mei", "Juni", "Juli", "Agustus", "September", "Oktober", "November", "Desember"];
            
            let filterInfo = '';
            if (month !== 'all' && search) {
                filterInfo = `Filter: Bulan ${bulan[month-1]} & Pencarian "${search}"`;
            } else if (month !== 'all') {
                filterInfo = `Filter: Bulan ${bulan[month-1]}`;
            } else if (search) {
                filterInfo = `Filter: Pencarian "${search}"`;
            }
            
            if (filterInfo) {
                doc.setFontSize(9);
                doc.text(filterInfo, doc.internal.pageSize.getWidth() / 2, 28, { align: 'center' });
            }
            
            // Garis pembatas
            doc.setLineWidth(0.5);
            doc.line(10, filterInfo ? 32 : 25, doc.internal.pageSize.getWidth() - 10, filterInfo ? 32 : 25);
            
            // Ambil data dari tabel
            const tableData = [];
            const rows = document.querySelectorAll('.print-section tbody tr');
            
            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                if (cells.length > 1) {
                    const rowData = [
                        cells[0]?.textContent.trim() || '-', // Peminjam
                        cells[1]?.textContent.trim() || '-', // Petugas
                        cells[2]?.textContent.trim() || '-', // Prodi
                        cells[3]?.textContent.trim().replace(/\s+/g, ' ') || '-', // Kontak
                        cells[4]?.textContent.trim() || '-', // Aset
                        cells[5]?.textContent.trim().replace(/\s+/g, ' ') || '-', // Waktu
                        cells[6]?.textContent.trim() || '-', // Catatan
                        cells[8]?.textContent.trim() || '-'  // Status
                    ];
                    tableData.push(rowData);
                }
            });
            
            // Generate tabel dengan autoTable
            doc.autoTable({
                startY: filterInfo ? 35 : 30,
                head: [['Peminjam', 'Petugas', 'Prodi', 'Kontak', 'Aset', 'Waktu', 'Catatan', 'Status']],
                body: tableData,
                styles: {
                    fontSize: 8,
                    cellPadding: 3,
                    overflow: 'linebreak',
                    halign: 'left'
                },
                headStyles: {
                    fillColor: [220, 38, 38],
                    textColor: 255,
                    fontStyle: 'bold',
                    halign: 'center'
                },
                alternateRowStyles: {
                    fillColor: [245, 245, 245]
                },
                columnStyles: {
                    0: { cellWidth: 30 },  // Peminjam
                    1: { cellWidth: 25 },  // Petugas
                    2: { cellWidth: 25 },  // Prodi
                    3: { cellWidth: 30 },  // Kontak
                    4: { cellWidth: 35 },  // Aset
                    5: { cellWidth: 35 },  // Waktu
                    6: { cellWidth: 40 },  // Catatan
                    7: { cellWidth: 20, halign: 'center' }  // Status
                },
                margin: { top: filterInfo ? 35 : 30, left: 10, right: 10 },
                didDrawPage: function(data) {
                    // Footer
                    const pageCount = doc.internal.getNumberOfPages();
                    const pageHeight = doc.internal.pageSize.getHeight();
                    
                    doc.setFontSize(8);
                    doc.setTextColor(150);
                    doc.text(
                        'Halaman ' + data.pageNumber + ' dari ' + pageCount,
                        doc.internal.pageSize.getWidth() / 2,
                        pageHeight - 10,
                        { align: 'center' }
                    );
                }
            });
            
            // Simpan PDF
            const filename = 'Arsip_Peminjaman_' + kategori + '_' + new Date().getTime() + '.pdf';
            doc.save(filename);
        }

        // Fungsi Export Excel
        function exportExcel() {
            const table = document.getElementById('arsipTable');
            if (!table) {
                alert('Tidak ada data untuk diexport');
                return;
            }
            
            // Clone tabel untuk menghapus kolom yang tidak perlu
            const clonedTable = table.cloneNode(true);
            
            // Hapus kolom Bukti (kolom 7) dan Aksi (kolom 9)
            const rows = clonedTable.querySelectorAll('tr');
            rows.forEach(row => {
                const cells = row.querySelectorAll('td, th');
                if (cells.length >= 10) {
                    // Hapus kolom 7 (Bukti) dan 9 (Aksi) - 0-index
                    if (cells[7]) cells[7].remove();
                    if (cells[8]) cells[8].remove(); // Setelah hapus kolom 7, kolom 9 menjadi kolom 8
                }
            });
            
            // Buat workbook
            const wb = XLSX.utils.book_new();
            const ws = XLSX.utils.table_to_sheet(clonedTable, {raw: true});
            
            // Tambah judul
            const title = 'ARSIP PEMINJAMAN <?= strtoupper($activeTab) ?>';
            const date = 'Tanggal Export: ' + new Date().toLocaleDateString('id-ID');
            const month = '<?= $month ?>';
            const search = '<?= addslashes($search) ?>';
            const bulan = ["Januari", "Februari", "Maret", "April", "Mei", "Juni", "Juli", "Agustus", "September", "Oktober", "November", "Desember"];
            
            let filterInfo = '';
            if (month !== 'all' && search) {
                filterInfo = `Filter: Bulan ${bulan[month-1]} & Pencarian "${search}"`;
            } else if (month !== 'all') {
                filterInfo = `Filter: Bulan ${bulan[month-1]}`;
            } else if (search) {
                filterInfo = `Filter: Pencarian "${search}"`;
            }
            
            // Sisipkan judul di atas tabel
            XLSX.utils.sheet_add_aoa(ws, [[title], [date], filterInfo ? [filterInfo] : [], []], { origin: 'A1' });
            
            // Format header
            const range = XLSX.utils.decode_range(ws['!ref']);
            const headerRow = filterInfo ? 4 : 3; // Baris header setelah judul
            
            for (let C = range.s.c; C <= range.e.c; ++C) {
                const cellAddress = XLSX.utils.encode_cell({r: headerRow, c: C});
                if (ws[cellAddress]) {
                    ws[cellAddress].s = {
                        font: { bold: true, color: { rgb: "FFFFFF" } },
                        fill: { fgColor: { rgb: "DC2626" } },
                        alignment: { horizontal: "center" }
                    };
                }
            }
            
            // Format status cells
            for (let R = headerRow + 1; R <= range.e.r; ++R) {
                const statusCol = 7; // Kolom status setelah hapus 2 kolom
                const cellAddress = XLSX.utils.encode_cell({r: R, c: statusCol});
                if (ws[cellAddress]) {
                    const status = ws[cellAddress].v;
                    if (status === 'Selesai') {
                        ws[cellAddress].s = {
                            font: { bold: true, color: { rgb: "065F46" } },
                            fill: { fgColor: { rgb: "D1FAE5" } }
                        };
                    } else if (status === 'Ditolak') {
                        ws[cellAddress].s = {
                            font: { bold: true, color: { rgb: "991B1B" } },
                            fill: { fgColor: { rgb: "FEE2E2" } }
                        };
                    }
                }
            }
            
            // Set column widths
            const colWidths = [
                {wch: 25}, // Peminjam
                {wch: 20}, // Petugas
                {wch: 15}, // Prodi
                {wch: 20}, // Kontak
                {wch: 30}, // Aset
                {wch: 30}, // Waktu
                {wch: 40}, // Catatan
                {wch: 15}  // Status
            ];
            ws['!cols'] = colWidths;
            
            // Tambah ke workbook
            XLSX.utils.book_append_sheet(wb, ws, "Arsip Peminjaman");
            
            // Export
            const filename = `Arsip_Peminjaman_<?= $activeTab ?>_<?= $month !== 'all' ? 'Bulan' . $month : '' ?><?= !empty($search) ? '_Search' : '' ?>_${new Date().toISOString().split('T')[0]}.xlsx`;
            XLSX.writeFile(wb, filename);
            
            // Tampilkan notifikasi
            showNotification('success', 'Export Berhasil!', 'File Excel telah berhasil diunduh.');
        }

        // Fungsi untuk menampilkan notifikasi
        function showNotification(type, title, message) {
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg border-l-4 ${
                type === 'success' ? 'bg-green-50 border-green-500 text-green-700' : 
                type === 'error' ? 'bg-red-50 border-red-500 text-red-700' : 
                'bg-blue-50 border-blue-500 text-blue-700'
            }`;
            
            notification.innerHTML = `
                <div class="flex items-center gap-3">
                    <i data-lucide="${type === 'success' ? 'check-circle' : type === 'error' ? 'alert-circle' : 'info'}" class="w-5 h-5"></i>
                    <div>
                        <p class="font-bold">${title}</p>
                        <p class="text-sm">${message}</p>
                    </div>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            // Refresh icons
            setTimeout(() => lucide.createIcons(), 100);
            
            // Hapus notifikasi setelah 3 detik
            setTimeout(() => {
                notification.style.opacity = '0';
                notification.style.transition = 'opacity 0.5s';
                setTimeout(() => document.body.removeChild(notification), 500);
            }, 3000);
        }
    </script>
</body>
</html>