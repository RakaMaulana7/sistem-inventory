<?php
session_start();
require "../config/database.php";
require "../auth/auth_helper.php";

cek_kemanan_login($pdo);

// 1. CEK LOGIN (Sederhana)
if (!isset($_SESSION['login']) || $_SESSION['role'] !== 'user') {
    header("Location: ../login.html");
    exit;
}

// 2. LOGIKA SEARCH
$search = $_GET['search'] ?? '';

// 3. QUERY DATABASE
$query = "SELECT * FROM transportasi WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (nama LIKE ? OR plat LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY id DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper untuk ikon jenis kendaraan
function getVehicleIcon($nama) {
    $namaLower = strtolower($nama);
    if (strpos($namaLower, 'truk') !== false || strpos($namaLower, 'pickup') !== false || strpos($namaLower, 'box') !== false) {
        return '<i data-lucide="truck" class="w-6 h-6"></i>';
    }
    return '<i data-lucide="car" class="w-6 h-6"></i>';
}

// Helper untuk status badge
function getStatusBadge($status) {
    switch($status) {
        case 'Tersedia':
            return '<span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-green-100 text-green-700 rounded-full text-xs font-bold"><i data-lucide="check-circle" class="w-3 h-3"></i> Tersedia</span>';
        case 'Dipinjam':
            return '<span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-yellow-100 text-yellow-700 rounded-full text-xs font-bold"><i data-lucide="clock" class="w-3 h-3"></i> Dipinjam</span>';
        case 'Tidak Tersedia':
            return '<span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-red-100 text-red-700 rounded-full text-xs font-bold"><i data-lucide="x-circle" class="w-3 h-3"></i> Tidak Tersedia</span>';
        default:
            return '<span class="text-xs text-gray-500">-</span>';
    }
}

// Helper untuk kondisi badge
function getKondisiBadge($kondisi) {
    switch($kondisi) {
        case 'Baik':
            return '<span class="inline-flex items-center gap-1 px-2.5 py-1 bg-blue-50 text-blue-700 rounded-lg text-xs font-semibold"><i data-lucide="thumbs-up" class="w-3 h-3"></i> Baik</span>';
        case 'Rusak Ringan':
            return '<span class="inline-flex items-center gap-1 px-2.5 py-1 bg-orange-50 text-orange-700 rounded-lg text-xs font-semibold"><i data-lucide="alert-triangle" class="w-3 h-3"></i> Rusak Ringan</span>';
        case 'Rusak Berat':
            return '<span class="inline-flex items-center gap-1 px-2.5 py-1 bg-red-50 text-red-700 rounded-lg text-xs font-semibold"><i data-lucide="alert-octagon" class="w-3 h-3"></i> Rusak Berat</span>';
        default:
            return '<span class="text-xs text-gray-500">-</span>';
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../assets/kampusums.png" />
    <title>Daftar Transportasi</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
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

    <main class="pt-7 pb-20 px-4 md:px-8 w-full max-w-[1440px] mx-auto flex-1">
        
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-6">
            <div>
                <h1 class="text-3xl md:text-4xl font-extrabold text-gray-900 tracking-tight">
                    Daftar <span class="bg-clip-text text-transparent bg-gradient-to-r from-brand to-brandSecondary">Transportasi</span>
                </h1>
                <p class="text-gray-500 mt-2 text-lg">
                    Armada operasional kampus siap melayani kebutuhan perjalanan Anda.
                </p>
            </div>
            <button onclick="location.href='dashboard.php'" class="px-6 py-2.5 bg-white border border-gray-200 text-gray-700 font-semibold rounded-full shadow-sm hover:shadow-md hover:bg-green-50 hover:border-brand transition-all">
                Kembali
            </button>
        </div>

        <form method="GET" class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 mb-8">
            <div class="relative group">
                <i data-lucide="search" class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 group-focus-within:text-brand transition-colors w-5 h-5"></i>
                <input
                    type="text"
                    name="search"
                    placeholder="Cari nama kendaraan atau plat nomor..."
                    value="<?= htmlspecialchars($search) ?>"
                    class="w-full pl-12 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl outline-none focus:bg-white focus:ring-2 focus:ring-brand/20 focus:border-brand transition-all"
                >
            </div>
        </form>

        <div class="flex flex-col gap-8">
            <?php if (empty($items)): ?>
                <div class="flex flex-col items-center justify-center py-20 bg-white rounded-3xl border border-dashed border-gray-300 text-center">
                    <div class="bg-gray-100 p-4 rounded-full mb-4">
                        <i data-lucide="car" class="text-gray-400 w-8 h-8"></i>
                    </div>
                    <p class="text-xl font-semibold text-gray-800">Transportasi tidak ditemukan</p>
                    <p class="text-sm text-gray-500 mt-2">Coba kata kunci pencarian yang berbeda</p>
                </div>
            <?php else: ?>
                <?php foreach ($items as $item): ?>
                <div class="group bg-white rounded-3xl overflow-hidden border border-gray-100 shadow-sm hover:shadow-xl transition-all duration-300 flex flex-col lg:flex-row">
                    
                    <div class="relative w-full lg:w-5/12 h-64 lg:h-auto bg-gray-200 overflow-hidden">
                        <?php if (!empty($item['fotoDepan'])): ?>
                            <img src="<?= htmlspecialchars($item['fotoDepan']) ?>" alt="Depan" class="w-full h-full object-cover transform group-hover:scale-105 transition-transform duration-700">
                        <?php else: ?>
                            <div class="flex flex-col items-center justify-center h-full text-gray-400">
                                <i data-lucide="car" class="mb-2 opacity-50 w-12 h-12"></i>
                                <span class="text-sm font-medium">Foto Utama N/A</span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="absolute inset-0 bg-gradient-to-t from-black/50 via-transparent to-transparent opacity-60"></div>
                        
                        <div class="absolute top-4 left-4 bg-black/60 backdrop-blur-md text-white text-[10px] uppercase font-bold px-2.5 py-1 rounded border border-white/20">
                            Tampak Depan
                        </div>

                        <div class="absolute top-4 right-4">
                            <?= getStatusBadge($item['status']) ?>
                        </div>

                        <div class="absolute bottom-4 left-4 bg-white/95 backdrop-blur text-gray-900 px-3 py-1.5 rounded-lg text-sm font-bold border border-gray-200 flex items-center gap-2 shadow-lg">
                            <div class="bg-black text-white px-1.5 rounded-[3px] text-[10px] tracking-wider">PLAT</div>
                            <?= htmlspecialchars($item['plat']) ?>
                        </div>
                    </div>

                    <div class="flex-1 p-6 lg:p-8 flex flex-col justify-between">
                        <div>
                            <div class="flex justify-between items-start mb-4">
                                <div class="flex-1">
                                    <h2 class="text-2xl lg:text-3xl font-bold text-gray-900 group-hover:text-brand transition-colors mb-3">
                                        <?= htmlspecialchars($item['nama']) ?>
                                    </h2>

                                    <div class="flex flex-wrap items-center gap-3 mb-3">
                                        <!-- Lokasi -->
                                        <div class="flex items-center gap-2 text-gray-600 text-sm font-medium">
                                            <i data-lucide="map-pin" class="text-brand w-4 h-4"></i>
                                            <span><?= htmlspecialchars($item['lokasi'] ?: 'Parkiran Kampus') ?></span>
                                        </div>

                                        <!-- Fuel type -->
                                        <div class="inline-flex items-center gap-2 bg-orange-50 border border-orange-200 rounded-full px-3 py-1 text-xs text-orange-700 font-semibold">
                                            <i data-lucide="fuel" class="w-3.5 h-3.5"></i>
                                            <span><?= htmlspecialchars($item['jenis_bensin'] ?: '-') ?></span>
                                        </div>

                                        <!-- Kondisi -->
                                        <?= getKondisiBadge($item['kondisi']) ?>
                                    </div>

                                    <!-- Fasilitas Interior -->
                                    <?php if(!empty($item['kondisi_dalam'])): ?>
                                    <div class="mb-3">
                                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Fasilitas Interior</p>
                                        <div class="flex flex-wrap gap-2">
                                            <?php 
                                            $parts = array_slice(array_map('trim', explode(',', $item['kondisi_dalam'])), 0, 5);
                                            foreach($parts as $p) { ?>
                                                <button type="button" onclick="openKondisiModal(<?= htmlspecialchars(json_encode($item['kondisi_dalam'])) ?>, <?= htmlspecialchars(json_encode($p)) ?>)" class="px-2.5 py-1 bg-gray-100 text-gray-700 text-[11px] font-semibold rounded-lg border border-gray-200 hover:bg-brand hover:text-white hover:border-brand transition-all cursor-pointer" tabindex="0">
                                                    <?= htmlspecialchars($p) ?>
                                                </button>
                                            <?php } ?>
                                            <?php if(count(explode(',', $item['kondisi_dalam'])) > 5): ?>
                                                <button type="button" onclick="openKondisiModal(<?= htmlspecialchars(json_encode($item['kondisi_dalam'])) ?>, '')" class="px-2.5 py-1 bg-brand text-white text-[11px] font-semibold rounded-lg hover:bg-brandHover transition-all cursor-pointer">
                                                    +<?= count(explode(',', $item['kondisi_dalam'])) - 5 ?> lainnya
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <!-- Catatan -->
                                    <?php if(!empty($item['catatan'])): ?>
                                    <div class="bg-amber-50 border border-amber-200 rounded-xl p-3 mb-3">
                                        <p class="text-[10px] font-bold text-amber-700 uppercase tracking-wider mb-1 flex items-center gap-1">
                                            <i data-lucide="info" class="w-3 h-3"></i> Catatan Penting
                                        </p>
                                        <p class="text-xs text-amber-800"><?= htmlspecialchars($item['catatan']) ?></p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="bg-gradient-to-br from-green-50 to-blue-50 p-3 rounded-xl text-brand ml-4">
                                    <?= getVehicleIcon($item['nama']) ?>
                                </div>
                            </div>

                            <div class="h-px bg-gray-100 w-full my-4"></div>

                            <div class="mb-6">
                                <p class="text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] mb-3 flex items-center gap-1.5">
                                    <i data-lucide="images" class="w-3.5 h-3.5"></i> Dokumentasi Visual
                                </p>
                                <div class="grid grid-cols-5 gap-3">
                                    <?php 
                                    $views = [
                                        ['key' => 'fotoDalam', 'label' => 'Dalam', 'icon' => 'layout'],
                                        ['key' => 'fotoKanan', 'label' => 'Kanan', 'icon' => 'arrow-right'],
                                        ['key' => 'fotoKiri', 'label' => 'Kiri', 'icon' => 'arrow-left'],
                                        ['key' => 'fotoBelakang', 'label' => 'Belakang', 'icon' => 'undo-2'],
                                        ['key' => 'fotoSpeedometer', 'label' => 'Speedo', 'icon' => 'gauge']
                                    ];
                                    foreach ($views as $v):
                                    ?>
                                    <div class="relative aspect-4/3 rounded-xl overflow-hidden bg-gray-100 border border-gray-200 group/thumb cursor-pointer" onclick="openImageModal('<?= htmlspecialchars($item[$v['key']]) ?>', '<?= $v['label'] ?>')">
                                        <?php if (!empty($item[$v['key']])): ?>
                                            <img src="<?= htmlspecialchars($item[$v['key']]) ?>" class="w-full h-full object-cover transition-transform group-hover/thumb:scale-110 duration-500">
                                            <div class="absolute inset-0 bg-black/0 group-hover/thumb:bg-black/20 transition-colors flex items-center justify-center">
                                                <i data-lucide="zoom-in" class="w-5 h-5 text-white opacity-0 group-hover/thumb:opacity-100 transition-opacity"></i>
                                            </div>
                                        <?php else: ?>
                                            <div class="w-full h-full flex items-center justify-center text-gray-300">
                                                <i data-lucide="<?= $v['icon'] ?>" class="w-5 h-5 opacity-30"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div class="absolute inset-x-0 bottom-0 bg-black/60 backdrop-blur-[1px] py-1 text-center">
                                            <span class="text-[8px] font-bold text-white uppercase"><?= $v['label'] ?></span>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 pt-4 lg:mt-0 lg:pt-0 border-t lg:border-t-0 border-gray-50">
                            <?php if($item['status'] === 'Tersedia'): ?>
                                <a href="peminjaman_transportasi.php?id=<?= $item['id'] ?>" 
                                   class="w-full py-4 rounded-xl font-bold text-base text-white bg-gradient-to-r from-brand to-brandSecondary hover:from-brandHover hover:to-brandSecondaryHover shadow-lg shadow-green-100 transition-all transform active:scale-[0.98] flex items-center justify-center gap-3">
                                    Ajukan Peminjaman <i data-lucide="arrow-right" class="w-5 h-5"></i>
                                </a>
                            <?php else: ?>
                                <button disabled class="w-full py-4 rounded-xl font-bold text-base text-gray-400 bg-gray-100 cursor-not-allowed flex items-center justify-center gap-3">
                                    <i data-lucide="lock" class="w-5 h-5"></i> Tidak Tersedia
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <?php include "../components/Footer.php"; ?>

    <!-- Modal: Detail Kondisi Interior -->
    <div id="kondisiModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4">
        <div onclick="event.stopPropagation()" class="bg-white rounded-2xl shadow-2xl w-full max-w-xl p-6">
            <div class="flex items-start justify-between mb-4">
                <div>
                    <h3 class="text-lg font-bold text-gray-800">Detail Fasilitas: <span id="kondisiModalLabel"></span></h3>
                    <p class="text-xs text-gray-500 mt-1">Daftar lengkap fasilitas dan kondisi interior kendaraan.</p>
                </div>
                <button onclick="closeKondisiModal()" class="text-gray-400 hover:text-gray-600">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <div id="kondisiModalList" class="space-y-3 max-h-[60vh] overflow-y-auto"></div>
        </div>
    </div>

    <!-- Modal: Image Viewer -->
    <div id="imageModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/90 p-4" onclick="closeImageModal()">
        <div class="relative max-w-4xl w-full" onclick="event.stopPropagation()">
            <button onclick="closeImageModal()" class="absolute -top-12 right-0 text-white hover:text-gray-300 transition-colors">
                <i data-lucide="x" class="w-8 h-8"></i>
            </button>
            <img id="imageModalImg" src="" alt="" class="w-full h-auto rounded-2xl shadow-2xl">
            <p id="imageModalLabel" class="text-white text-center mt-4 font-semibold"></p>
        </div>
    </div>

    <script>
        function escapeHtml(unsafe) {
            return String(unsafe)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function openKondisiModal(kondisiString, label) {
            const modal = document.getElementById('kondisiModal');
            const title = document.getElementById('kondisiModalLabel');
            const list = document.getElementById('kondisiModalList');
            title.textContent = label || 'Semua Fasilitas';
            list.innerHTML = '';

            if (!kondisiString) {
                list.innerHTML = '<div class="text-sm text-gray-500 italic text-center py-4">Tidak ada detail untuk item ini.</div>';
            } else {
                const items = kondisiString.split(',').map(s => s.trim()).filter(Boolean);
                const filtered = label ? items.filter(i => i.toLowerCase().includes(label.toLowerCase())) : items;

                if (filtered.length === 0) {
                    list.innerHTML = '<div class="text-sm text-gray-500 italic text-center py-4">Tidak ada detail untuk item ini.</div>';
                } else {
                    filtered.forEach((it, idx) => {
                        const row = document.createElement('div');
                        row.className = 'p-3 bg-gray-50 border border-gray-200 rounded-lg flex items-center justify-between hover:bg-gray-100 transition-colors';
                        row.innerHTML = '<div class="text-sm text-gray-700 font-medium">' + escapeHtml(it) + '</div><div class="text-xs text-gray-400 font-mono">#' + (idx + 1) + '</div>';
                        list.appendChild(row);
                    });
                }
            }

            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            setTimeout(() => lucide.createIcons(), 50);
        }

        function closeKondisiModal() {
            const modal = document.getElementById('kondisiModal');
            modal.classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

        function openImageModal(src, label) {
            if (!src) return;
            const modal = document.getElementById('imageModal');
            const img = document.getElementById('imageModalImg');
            const labelEl = document.getElementById('imageModalLabel');
            
            img.src = src;
            labelEl.textContent = label;
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            setTimeout(() => lucide.createIcons(), 50);
        }

        function closeImageModal() {
            const modal = document.getElementById('imageModal');
            modal.classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

        // Close modals on Escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeKondisiModal();
                closeImageModal();
            }
        });

        lucide.createIcons();
    </script>
</body>
</html>