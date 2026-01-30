<?php
require "config/database.php";

// Fungsi helper untuk mengambil pengaturan
function getSetting($pdo, $nama_setting, $default = '') {
    $stmt = $pdo->prepare("SELECT nilai FROM pengaturan WHERE nama_setting = ?");
    $stmt->execute([$nama_setting]);
    $result = $stmt->fetch();
    return $result ? $result['nilai'] : $default;
}

// Fungsi untuk mendapatkan jumlah steps dan FAQs
function getStepsCount($pdo) {
    $stmt = $pdo->query("SELECT COUNT(*) FROM pengaturan WHERE nama_setting LIKE 'panduan_step%_title'");
    return (int) $stmt->fetchColumn();
}

function getFaqsCount($pdo) {
    $stmt = $pdo->query("SELECT COUNT(*) FROM pengaturan WHERE nama_setting LIKE 'panduan_faq%_question'");
    return (int) $stmt->fetchColumn();
}

// Ambil semua pengaturan yang dibutuhkan
$youtube_video_id = getSetting($pdo, 'youtube_video_id', 'eZncsrYwiRI');

// Hero Section
$hero_title = getSetting($pdo, 'panduan_hero_title', 'Maksimalkan Fasilitas <br/> Kampus untuk Karyamu');
$hero_subtitle = getSetting($pdo, 'panduan_hero_subtitle', 'Panduan lengkap meminjam alat lab, ruangan, dan kendaraan operasional.');

// Video Section
$video_title = getSetting($pdo, 'panduan_video_title', 'Tutorial Singkat');
$video_subtitle = getSetting($pdo, 'panduan_video_subtitle', 'Pahami alur sistem dalam 2 menit.');

// FAQ Section
$faq_title = getSetting($pdo, 'panduan_faq_title', 'Pertanyaan Umum');
$faq_subtitle = getSetting($pdo, 'panduan_faq_subtitle', 'Jawaban atas hal-hal yang sering ditanyakan.');

// CTA Section
$cta_title = getSetting($pdo, 'panduan_cta_title', 'Siap Meminjam Aset?');
$cta_subtitle = getSetting($pdo, 'panduan_cta_subtitle', 'Pastikan Anda sudah memiliki akun yang terdaftar.');

// Ambil Steps (Dinamis)
$steps_count = getStepsCount($pdo);
$steps = [];
for ($i = 1; $i <= $steps_count; $i++) {
    $title = getSetting($pdo, "panduan_step{$i}_title");
    if ($title) {
        $steps[] = [
            'title' => $title,
            'desc' => getSetting($pdo, "panduan_step{$i}_desc", ""),
            'icon' => getSetting($pdo, "panduan_step{$i}_icon", "circle")
        ];
    }
}

// Jika tidak ada steps, gunakan default
if (empty($steps)) {
    $steps = [
        ['title' => '1. Login ke Sistem', 'desc' => 'Masuk menggunakan akun yang terdaftar', 'icon' => 'log-in'],
        ['title' => '2. Pilih Aset', 'desc' => 'Cari dan pilih aset yang ingin dipinjam', 'icon' => 'search'],
        ['title' => '3. Ajukan Peminjaman', 'desc' => 'Isi formulir peminjaman dengan lengkap', 'icon' => 'file-text'],
    ];
}

// Ambil FAQs (Dinamis)
$faqs_count = getFaqsCount($pdo);
$faqs = [];
for ($i = 1; $i <= $faqs_count; $i++) {
    $question = getSetting($pdo, "panduan_faq{$i}_question");
    if ($question) {
        $faqs[] = [
            'question' => $question,
            'answer' => getSetting($pdo, "panduan_faq{$i}_answer", "")
        ];
    }
}

// Jika tidak ada FAQs, gunakan default
if (empty($faqs)) {
    $faqs = [
        ['question' => 'Bagaimana cara meminjam aset?', 'answer' => 'Login ke sistem, pilih aset yang diinginkan, lalu ajukan peminjaman.'],
        ['question' => 'Berapa lama proses persetujuan?', 'answer' => 'Biasanya 1-2 hari kerja setelah pengajuan.'],
    ];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="assets/kampusums.png" />
    <title>Pusat Panduan - Inventori FT</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
    .page-transition {
        animation: pageFadeOut 0.6s ease forwards;
    }

    @keyframes pageFadeOut {
        from {
        opacity: 1;
        transform: translateY(0);
        }
        to {
        opacity: 0;
        transform: translateY(-10px);
        }
    }

    .faq-content {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease-out, padding 0.3s ease;
    }
    .faq-active .faq-content {
        max-height: 200px;
        padding-bottom: 1.25rem;
    }
    .faq-active .chevron-icon {
        transform: rotate(180deg);
    }
    </style>
    <script>
    function smoothRedirect(event, url) {
        event.preventDefault();
        document.body.classList.add('page-transition');
        setTimeout(() => {
        window.location.href = url;
        }, 600);
    }

    tailwind.config = {
        theme: {
            extend: {
                colors: {
                    brand: '#10b981',
                    brandHover: '#059669',
                    brandSecondary: '#3b82f6',
                    brandSecondaryHover: '#2563eb',
                },
                keyframes: {
                    'slide-up': {
                        '0%': { opacity: '0', transform: 'translateY(20px)' },
                        '100%': { opacity: '1', transform: 'translateY(0)' },
                    },
                    'fade-in': {
                        '0%': { opacity: '0' },
                        '100%': { opacity: '1' },
                    }
                },
                animation: {
                    'slide-up': 'slide-up 0.6s ease-out forwards',
                    'fade-in': 'fade-in 0.8s ease-out forwards',
                }
            }
        }
    }
    </script>
</head>
<body class="flex flex-col min-h-screen bg-white font-sans text-gray-800 overflow-x-hidden">

    <nav class="fixed top-0 left-0 w-full bg-white/90 backdrop-blur-md border-b border-gray-100 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16 md:h-20">
                <a href="index.html" onclick="smoothRedirect(event, 'index.html')" class="group flex items-center gap-2 text-gray-500 hover:text-brand transition-colors font-medium">
                    <div class="p-1.5 rounded-full bg-gray-100 group-hover:bg-green-50 transition-colors">
                        <i data-lucide="arrow-left" class="w-4 h-4"></i>
                    </div>
                    <span class="text-sm font-semibold">Beranda</span>
                </a>
                <a href="index.html">
                    <img src="assets/umsura.png" alt="Logo" class="h-8 w-auto" />
                </a>
            </div>
        </div>
    </nav>

    <main class="grow pt-28 pb-20 px-4 sm:px-6 lg:px-8">
        <div class="max-w-7xl mx-auto space-y-24">
            
            <!-- Hero Section -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                <div class="text-left animate-slide-up">
                    <div class="inline-flex items-center gap-2 px-3 py-1 bg-green-50 text-brand text-xs font-bold uppercase tracking-wider rounded-lg mb-4">
                        <i data-lucide="book-open" class="w-3.5 h-3.5"></i> Pusat Bantuan
                    </div>
                    <h1 class="text-4xl md:text-5xl font-extrabold text-gray-900 leading-tight mb-6">
                        <?= $hero_title ?>
                    </h1>
                    <p class="text-lg text-gray-500 mb-8 leading-relaxed max-w-lg">
                        <?= htmlspecialchars($hero_subtitle) ?>
                    </p>
                    <div class="flex flex-wrap gap-4">
                        <button onclick="scrollToVideo()" class="px-6 py-3 rounded-xl bg-gray-100 text-gray-700 font-bold hover:bg-gray-200 transition-all flex items-center gap-2">
                            <i data-lucide="play" class="w-4 h-4 opacity-50 fill-current"></i> Tonton Video
                        </button>
                        <a href="login.html" onclick="smoothRedirect(event, 'login.html')" class="px-6 py-3 rounded-xl bg-gradient-to-r from-brand to-brandSecondary text-white font-bold hover:from-brandHover hover:to-brandSecondaryHover shadow-lg hover:shadow-green-200 transition-all flex items-center gap-2">
                            Mulai Peminjaman <i data-lucide="arrow-right" class="w-4 h-4"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Video Section -->
            <div id="video-section" class="border-t border-gray-100 pt-16">
                <div class="mb-8">
                    <h2 class="text-2xl font-bold text-gray-900"><?= htmlspecialchars($video_title) ?></h2>
                    <p class="text-gray-500 mt-1"><?= htmlspecialchars($video_subtitle) ?></p>
                </div>
                
                <div id="video-container" class="relative w-full bg-black rounded-2xl shadow-xl overflow-hidden aspect-video group cursor-pointer" onclick="playVideo()">
                    <div id="video-thumbnail" class="absolute inset-0 flex items-center justify-center bg-gray-900 z-10">
                        <img src="https://images.unsplash.com/photo-1516321318423-f06f85e504b3?q=80&w=2070&auto=format&fit=crop" 
                             class="absolute inset-0 w-full h-full object-cover opacity-50 group-hover:scale-105 transition-transform duration-700" />
                        <div class="relative z-10 w-16 h-16 bg-white/20 backdrop-blur-md rounded-full flex items-center justify-center border border-white/30 group-hover:scale-110 transition-transform">
                            <div class="w-12 h-12 bg-gradient-to-r from-brand to-brandSecondary rounded-full flex items-center justify-center pl-1 shadow-lg">
                                <i data-lucide="play" class="text-white fill-current w-5 h-5"></i>
                            </div>
                        </div>
                    </div>
                    <div id="video-iframe" class="hidden absolute inset-0 bg-black"></div>
                </div>
            </div>

            <!-- Steps Section (Dinamis) -->
            <div class="border-t border-gray-100 pt-16">
                <h2 class="text-2xl font-bold text-gray-900 mb-10">Langkah Penggunaan</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($steps as $index => $step): ?>
                    <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm hover:shadow-md hover:border-<?= $index % 2 == 0 ? 'green' : 'blue' ?>-100 transition-all group">
                        <div class="w-12 h-12 bg-gray-50 text-gray-600 rounded-xl flex items-center justify-center mb-4 group-hover:bg-gradient-to-r group-hover:from-<?= $index % 2 == 0 ? 'brand' : 'brandSecondary' ?> group-hover:to-<?= $index % 2 == 0 ? 'brandSecondary' : 'blue-600' ?> group-hover:text-white transition-all duration-300">
                            <i data-lucide="<?= htmlspecialchars($step['icon']) ?>" class="w-6 h-6"></i>
                        </div>
                        <h3 class="font-bold text-lg text-gray-900 mb-2"><?= htmlspecialchars($step['title']) ?></h3>
                        <p class="text-sm text-gray-500 leading-relaxed"><?= htmlspecialchars($step['desc']) ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- FAQ Section (Dinamis) -->
            <div class="border-t border-gray-100 pt-16 grid lg:grid-cols-3 gap-12">
                <div class="lg:col-span-1">
                    <div class="w-12 h-12 bg-yellow-50 text-yellow-600 rounded-2xl flex items-center justify-center mb-4">
                        <i data-lucide="help-circle" class="w-6 h-6"></i>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-900 mb-3"><?= htmlspecialchars($faq_title) ?></h2>
                    <p class="text-gray-500 text-sm leading-relaxed mb-6">
                        <?= htmlspecialchars($faq_subtitle) ?>
                    </p>
                    <a href="https://wa.me/6281130877100"
                    target="_blank"
                    class="inline-flex items-center gap-2 px-4 py-2 
                            bg-brand text-white text-sm font-semibold 
                            rounded-lg shadow-md 
                            hover:bg-brand/90 hover:shadow-lg 
                            transition-all duration-300">
                        Hubungi Admin
                        <i data-lucide="arrow-right" class="w-4 h-4"></i>
                    </a>
                </div>

                <div class="lg:col-span-2 space-y-4">
                    <?php foreach ($faqs as $faq): ?>
                    <div class="faq-item bg-white rounded-xl border border-gray-200 transition-all overflow-hidden">
                        <button onclick="toggleFaq(this)" class="w-full flex justify-between items-center p-5 text-left focus:outline-none">
                            <span class="font-semibold text-gray-700"><?= htmlspecialchars($faq['question']) ?></span>
                            <i data-lucide="chevron-down" class="chevron-icon w-5 h-5 text-gray-400 transition-transform"></i>
                        </button>
                        <div class="faq-content px-5 text-gray-600 text-sm leading-relaxed">
                            <div class="pt-2"><?= htmlspecialchars($faq['answer']) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- CTA Section -->
            <div class="bg-gradient-to-r from-brand to-brandSecondary rounded-3xl p-8 md:p-12 text-center md:text-left flex flex-col md:flex-row items-center justify-between gap-8 shadow-2xl">
                <div class="space-y-2">
                    <h2 class="text-2xl md:text-3xl font-bold text-white"><?= htmlspecialchars($cta_title) ?></h2>
                    <p class="text-white/80"><?= htmlspecialchars($cta_subtitle) ?></p>
                </div>
                <a href="login.html" class="whitespace-nowrap px-8 py-4 rounded-xl bg-white text-brand font-bold hover:bg-gray-50 transition-all duration-300 shadow-lg">
                    Login Sekarang
                </a>
            </div>

        </div>
    </main>

    <footer class="bg-[#1a1a1a] text-gray-300 py-16 border-t-4 border-brand font-sans">
    <div class="max-w-7xl mx-auto grid grid-cols-1 md:grid-cols-3 gap-10 px-6 lg:px-8">
        
        <div class="space-y-4">
            <div class="flex items-center space-x-3 mb-4 bg-white/5 w-fit p-3 rounded-xl border border-white/10 backdrop-blur-sm">
                <img src="assets/umsura.png" alt="UMS" class="h-8 sm:h-9 md:h-10 w-auto object-contain" />
                <img src="assets/kampus.webp" alt="Kampus Merdeka" class="h-8 sm:h-9 md:h-10 w-auto object-contain" />
            </div>
            <p class="text-sm leading-relaxed text-gray-400">
                <span class="text-white font-semibold">Sistem Informasi Inventori Berbasis Online</span>
                <br />
                Fakultas Teknik Universitas Muhammadiyah Surabaya
            </p>
        </div>

        <div>
            <h3 class="text-sm font-bold text-white uppercase tracking-widest mb-6 border-b-2 border-brand w-fit pb-1">
                Info Terkait
            </h3>
            <ul class="space-y-3 text-sm">
                <li>
                    <a href="https://www.um-surabaya.ac.id/" class="flex items-center gap-2 hover:text-brand hover:translate-x-1 transition-all duration-300">
                        <i data-lucide="globe" class="w-4 h-4 text-brand"></i>
                        Universitas Muhammadiyah Surabaya
                    </a>
                </li>
                <li>
                    <a href="https://cybercampus.um-surabaya.ac.id/login" class="flex items-center gap-2 hover:text-brandSecondary hover:translate-x-1 transition-all duration-300">
                        <i data-lucide="shield-check" class="w-4 h-4 text-brandSecondary"></i>
                        Cybercampus
                    </a>
                </li>
                <li>
                    <a href="https://baa.um-surabaya.ac.id/" class="flex items-center gap-2 hover:text-brand hover:translate-x-1 transition-all duration-300">
                        <i data-lucide="external-link" class="w-4 h-4 text-brand"></i>
                        Biro Administrasi Akademik
                    </a>
                </li>
            </ul>
        </div>

        <div>
            <h3 class="text-sm font-bold text-white uppercase tracking-widest mb-6 border-b-2 border-brandSecondary w-fit pb-1">
                Contact Us
            </h3>
            <div class="space-y-4 text-sm text-gray-400">
                <div class="flex items-start gap-3">
                    <i data-lucide="map-pin" class="text-brand w-5 h-5 mt-1 shrink-0"></i>
                    <p>
                        <strong class="text-white">Universitas Muhammadiyah Surabaya</strong> <br />
                        Jalan Sutorejo No 59, Surabaya, Jawa Timur, Indonesia 60113
                    </p>
                </div>
                
                <p class="flex items-center gap-3">
                    <i data-lucide="phone" class="text-brandSecondary w-5 h-5"></i>
                    <a href="https://wa.me/6281130877100" class="hover:text-white transition-colors border-b border-transparent hover:border-white">
                        0811-3087-7100 (WA ONLY)
                    </a>
                </p>
                
            </div>
        </div>
    </div>

    <div class="text-center text-gray-500 mt-12 border-t border-white/10 pt-8 px-6">
        <p class="text-xs sm:text-sm leading-relaxed flex flex-col sm:flex-row justify-center items-center gap-2">
            <span>
                Copyright © 2025 —
                <a href="https://um-surabaya.ac.id" target="_blank" rel="noreferrer" class="text-white hover:text-brand font-medium transition-colors duration-200 ml-1">
                    Universitas Muhammadiyah Surabaya
                </a>
            </span>
            <span class="hidden sm:inline text-gray-700">|</span>
            <span>
                Powered by 
                <a href="https://www.instagram.com/himatifa_umsurabaya?igsh=dXR1NjEzNjlycGZo" class="text-white hover:text-brandSecondary font-medium transition-colors duration-200">
                    Informatika
                </a>
            </span>
        </p>
    </div>
</footer>

<script src="https://unpkg.com/lucide@latest"></script>
<script>
    lucide.createIcons();
    
    const YOUTUBE_VIDEO_ID = '<?= $youtube_video_id ?>';

    function scrollToVideo() {
        document.getElementById('video-section').scrollIntoView({ behavior: 'smooth' });
    }

    function playVideo() {
        const container = document.getElementById('video-iframe');
        const thumbnail = document.getElementById('video-thumbnail');
        
        container.innerHTML = `
            <iframe width="100%" height="100%" 
                    src="https://www.youtube.com/embed/${YOUTUBE_VIDEO_ID}?autoplay=1" 
                    title="Tutorial" frameborder="0" 
                    allow="autoplay; encrypted-media" allowfullscreen>
            </iframe>
            <button onclick="stopVideo(event)" class="absolute top-4 right-4 bg-black/50 text-white p-2 rounded-full hover:bg-green-600 transition-colors z-20">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        `;
        
        thumbnail.classList.add('hidden');
        container.classList.remove('hidden');
        lucide.createIcons();
    }

    function stopVideo(event) {
        event.stopPropagation();
        document.getElementById('video-iframe').classList.add('hidden');
        document.getElementById('video-iframe').innerHTML = '';
        document.getElementById('video-thumbnail').classList.remove('hidden');
    }

    function toggleFaq(button) {
        const item = button.parentElement;
        const isActive = item.classList.contains('faq-active');
        
        document.querySelectorAll('.faq-item').forEach(el => el.classList.remove('faq-active'));
        
        if (!isActive) {
            item.classList.add('faq-active');
        }
    }
</script>

</body>
</html>