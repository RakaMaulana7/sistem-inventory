<?php
session_start();

// 1. PROTEKSI HALAMAN
if (!isset($_SESSION['login']) || $_SESSION['role'] !== 'user') {
    header("Location: ../login.html");
    exit;
}

require "../config/database.php";
require "../auth/auth_helper.php";

cek_kemanan_login($pdo);

// Ambil data user lengkap
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$notifs = []; 
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../assets/kampusums.png" />
    <title>Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; overflow-x: hidden; }
        
        .animate-fade-in-up { animation: fadeInUp 0.8s ease-out forwards; opacity: 0; }
        .stagger-1 { animation-delay: 0.1s; }
        .stagger-2 { animation-delay: 0.2s; }
        .stagger-3 { animation-delay: 0.3s; }
        .stagger-4 { animation-delay: 0.4s; }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        /* Penyesuaian ukuran blur agar tidak berat di mobile */
        .bg-blur-green { background: rgba(16, 185, 129, 0.08); filter: blur(80px); }
        .bg-blur-blue { background: rgba(59, 130, 246, 0.06); filter: blur(80px); }
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
<body class="min-h-screen flex flex-col bg-[#FAFAFA] relative">

    <?php include "../components/UserNavbar.php"; ?>   
    
    <div class="absolute top-0 left-1/2 -translate-x-1/2 w-full max-w-[800px] h-[300px] md:h-[500px] bg-blur-green rounded-full pointer-events-none -z-10"></div>
    <div class="absolute bottom-0 right-0 w-[300px] md:w-[600px] h-[300px] md:h-[600px] bg-blur-blue rounded-full pointer-events-none -z-10"></div>

    <main class="flex-1 pt-6 md:pt-12 pb-20 px-4 md:px-8 w-full max-w-6xl mx-auto z-10">
        
        <div class="flex flex-col justify-between items-start mb-10 md:mb-16 gap-6 animate-fade-in-up stagger-1">
            <div class="w-full">
                <div class="flex flex-wrap items-center gap-3 text-gray-500 text-[10px] md:text-xs font-bold mb-4 uppercase tracking-widest">
                    <span class="bg-gradient-to-r from-brand to-brandSecondary text-white px-2.5 py-1 rounded-lg shadow-sm shadow-green-100">FAKULTAS TEKNIK</span>
                    <span class="flex items-center gap-1.5 bg-white px-2.5 py-1 rounded-lg border border-gray-100 shadow-sm">
                        <i data-lucide="calendar" class="w-3.5 h-3.5 text-brand"></i>
                        <span id="currentDate"><?php echo date('d M Y'); ?></span>
                    </span>
                </div>
                
                <h1 class="text-3xl md:text-5xl lg:text-6xl font-extrabold text-gray-900 leading-[1.1] mb-4">
                    <span id="greeting" class="text-gray-400 font-medium">Selamat...</span> <br class="hidden md:block" />
                    <span class="bg-clip-text text-transparent bg-gradient-to-r from-brand via-emerald-500 to-brandSecondary">
                        <?php echo htmlspecialchars($user['nama']); ?>
                    </span> 👋
                </h1>
                <p class="text-gray-500 text-base md:text-xl max-w-xl leading-relaxed">
                    Sistem peminjaman fasilitas terpadu untuk civitas akademika Teknik.
                </p>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 md:gap-8">
            
            <div onclick="location.href='ruangan.php'" class="animate-fade-in-up stagger-2 group bg-white rounded-4xl p-6 md:p-8 shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-gray-50 cursor-pointer transition-all duration-500 hover:-translate-y-2 hover:shadow-2xl hover:border-green-100">
                <div class="w-14 h-14 md:w-16 md:h-16 rounded-2xl bg-gradient-to-br from-blue-600 to-cyan-400 flex items-center justify-center text-white mb-6 transition-all duration-500 group-hover:scale-110 group-hover:rotate-3 shadow-lg shadow-blue-100">
                    <i data-lucide="building-2" class="w-7 h-7 md:w-8 md:h-8"></i>
                </div>
                <h3 class="text-xl md:text-2xl font-bold text-gray-800 group-hover:text-brand transition-colors">Ruangan</h3>
                <p class="text-gray-500 text-sm md:text-base mt-2 mb-8">Peminjaman ruang kelas, laboratorium, dan aula.</p>
                <div class="flex items-center gap-2 text-xs md:text-sm font-bold text-gray-400 group-hover:text-brand transition-all">
                    <span>LIHAT DAFTAR</span>
                    <div class="bg-gray-50 group-hover:bg-brand group-hover:text-white p-1.5 rounded-full transition-all group-hover:translate-x-2">
                        <i data-lucide="chevron-right" class="w-4 h-4"></i>
                    </div>
                </div>
            </div>

            <div onclick="location.href='transportasi.php'" class="animate-fade-in-up stagger-3 group bg-white rounded-4xl p-6 md:p-8 shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-gray-50 cursor-pointer transition-all duration-500 hover:-translate-y-2 hover:shadow-2xl hover:border-blue-100">
                <div class="w-14 h-14 md:w-16 md:h-16 rounded-2xl bg-gradient-to-br from-emerald-600 to-teal-400 flex items-center justify-center text-white mb-6 transition-all duration-500 group-hover:scale-110 group-hover:rotate-3 shadow-lg shadow-emerald-100">
                    <i data-lucide="car" class="w-7 h-7 md:w-8 md:h-8"></i>
                </div>
                <h3 class="text-xl md:text-2xl font-bold text-gray-800 group-hover:text-brandSecondary transition-colors">Transportasi</h3>
                <p class="text-gray-500 text-sm md:text-base mt-2 mb-8">Akses kendaraan bus dan mobil dinas fakultas.</p>
                <div class="flex items-center gap-2 text-xs md:text-sm font-bold text-gray-400 group-hover:text-brandSecondary transition-all">
                    <span>LIHAT DAFTAR</span>
                    <div class="bg-gray-50 group-hover:bg-brandSecondary group-hover:text-white p-1.5 rounded-full transition-all group-hover:translate-x-2">
                        <i data-lucide="chevron-right" class="w-4 h-4"></i>
                    </div>
                </div>
            </div>

            <div onclick="location.href='sarana.php'" class="animate-fade-in-up stagger-4 group bg-white rounded-4xl p-6 md:p-8 shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-gray-50 cursor-pointer transition-all duration-500 hover:-translate-y-2 hover:shadow-2xl hover:border-green-100 sm:col-span-2 lg:col-span-1">
                <div class="w-14 h-14 md:w-16 md:h-16 rounded-2xl bg-gradient-to-br from-brand to-emerald-400 flex items-center justify-center text-white mb-6 transition-all duration-500 group-hover:scale-110 group-hover:rotate-3 shadow-lg shadow-green-100">
                    <i data-lucide="package" class="w-7 h-7 md:w-8 md:h-8"></i>
                </div>
                <h3 class="text-xl md:text-2xl font-bold text-gray-800 group-hover:text-brand transition-colors">Sarana</h3>
                <p class="text-gray-500 text-sm md:text-base mt-2 mb-8">Peminjaman alat pendukung kegiatan (proyektor, dll).</p>
                <div class="flex items-center gap-2 text-xs md:text-sm font-bold text-gray-400 group-hover:text-brand transition-all">
                    <span>LIHAT DAFTAR</span>
                    <div class="bg-gray-50 group-hover:bg-brand group-hover:text-white p-1.5 rounded-full transition-all group-hover:translate-x-2">
                        <i data-lucide="chevron-right" class="w-4 h-4"></i>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <?php include "../components/Footer.php"; ?>

    <script>
        lucide.createIcons();

        document.addEventListener('DOMContentLoaded', () => {
            const hour = new Date().getHours();
            let greeting = "Selamat Malam";
            if (hour < 11) greeting = "Selamat Pagi";
            else if (hour < 15) greeting = "Selamat Siang";
            else if (hour < 18) greeting = "Selamat Sore";
            
            const greetingElement = document.getElementById('greeting');
            if (greetingElement) greetingElement.innerText = greeting;
        });
    </script>
</body>
</html>