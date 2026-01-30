<?php
// Ambil current path untuk deteksi menu aktif
$current_page = $_SERVER['REQUEST_URI'];
$admin_name = $_SESSION['nama'] ?? 'Administrator';
$admin_username = $_SESSION['username'] ?? '';
$admin_foto = $_SESSION['foto'] ?? null;

// Ambil email dari database jika belum ada di session
if (!isset($_SESSION['email']) || empty($_SESSION['email'])) {
    try {
        $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user_data = $stmt->fetch();
        $_SESSION['email'] = $user_data['email'] ?? null;
    } catch (Exception $e) {
        $_SESSION['email'] = null;
    }
}

$admin_email = $_SESSION['email'] ?? null;

function isActive($path, $current_page) {
    return strpos($current_page, $path) !== false
        ? 'bg-gradient-to-r from-yellow-50 to-green-50 text-green-700 shadow-sm border-r-4 border-green-600'
        : 'text-gray-600 hover:bg-gray-50';
}
?>

<style>
    [x-cloak] { display: none !important; }
</style>

<div x-data="{ 
    isSidebarOpen: false, 
    showLogoutModal: false, 
    isLoggingOut: false,
    isRuanganOpen: false,
    isSaranaOpen: false,
    isUsersOpen: false
}">
    
    <div :class="showLogoutModal ? 'blur-sm pointer-events-none transition-all duration-300' : 'transition-all duration-300'">
        
        <header class="md:hidden fixed top-0 left-0 right-0 h-16 flex items-center justify-between px-4 z-40 shadow-lg bg-white">
            <div class="flex items-center gap-3">
                <img src="../assets/umsura.png" alt="FT Logo" class="h-6 w-auto">
            </div>
            <button @click="isSidebarOpen = !isSidebarOpen" class="p-2 rounded-lg text-black hover:bg-gray-100 transition-all">
                <i x-show="!isSidebarOpen" data-lucide="menu"></i>
                <i x-show="isSidebarOpen" data-lucide="x"></i>
            </button>
        </header>

        <aside 
            class="fixed left-0 top-0 h-full w-60 bg-white border-r border-gray-200 flex flex-col justify-between z-30 transition-transform duration-300 md:translate-x-0"
            :class="isSidebarOpen ? 'translate-x-0 shadow-2xl' : '-translate-x-full'"
        >
            <div class="h-20 items-center gap-3 px-6 border-b border-gray-100 hidden md:flex">
                <img src="../assets/umsura.png" alt="FT Logo" class="h-8 w-auto">
            </div>
            
            <nav class="flex-1 overflow-y-auto px-4 py-6 space-y-1 md:pt-6 pt-20">
                <a href="dashboard.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium <?= isActive('dashboard.php', $current_page); ?>">
                    <i data-lucide="home" class="w-5 h-5"></i>
                    <span>Dashboard</span>
                </a>

                <div class="mb-6 mt-4">
                    <p class="px-3 text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-2 text-center">---  Master Data  ---</p>
                    <div class="space-y-1">
                        <!-- Kelola Ruangan dengan submenu -->
                        <div>
                            <button @click="isRuanganOpen = !isRuanganOpen" class="flex items-center justify-between w-full px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-200 group text-gray-600 hover:bg-gray-50">
                                <div class="flex items-center gap-3">
                                    <i data-lucide="building-2" class="w-5 h-5"></i>
                                    <span>Kelola Ruangan</span>
                                </div>
                                <i data-lucide="chevron-down" class="w-4 h-4 transition-transform duration-200" :class="isRuanganOpen ? 'rotate-180' : ''"></i>
                            </button>
                            <div x-show="isRuanganOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 max-h-0" x-transition:enter-end="opacity-100 max-h-96" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 max-h-96" x-transition:leave-end="opacity-0 max-h-0" class="ml-6 mt-1 space-y-1 overflow-hidden">
                                <a href="gedung.php" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-all duration-200 <?= isActive('gedung.php', $current_page); ?>">
                                    <i data-lucide="building" class="w-4 h-4"></i>
                                    <span>Kelola Gedung</span>
                                </a>
                                <a href="ruangan.php" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-all duration-200 <?= isActive('ruangan.php', $current_page); ?>">
                                    <i data-lucide="door-open" class="w-4 h-4"></i>
                                    <span>Daftar Ruangan</span>
                                </a>
                            </div>
                        </div>

                        <!-- Kelola Sarana dengan submenu -->
                        <div>
                            <button @click="isSaranaOpen = !isSaranaOpen" class="flex items-center justify-between w-full px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-200 group text-gray-600 hover:bg-gray-50">
                                <div class="flex items-center gap-3">
                                    <i data-lucide="wrench" class="w-5 h-5"></i>
                                    <span>Kelola Sarana</span>
                                </div>
                                <i data-lucide="chevron-down" class="w-4 h-4 transition-transform duration-200" :class="isSaranaOpen ? 'rotate-180' : ''"></i>
                            </button>
                            <div x-show="isSaranaOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 max-h-0" x-transition:enter-end="opacity-100 max-h-96" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 max-h-96" x-transition:leave-end="opacity-0 max-h-0" class="ml-6 mt-1 space-y-1 overflow-hidden">
                                <a href="kelola_sarana.php" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-all duration-200 <?= isActive('kelola_sarana.php', $current_page); ?>">
                                    <i data-lucide="settings" class="w-4 h-4"></i>
                                    <span>Kelola Sarana</span>
                                </a>
                                <a href="sarana.php" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-all duration-200 <?= isActive('sarana.php', $current_page); ?>">
                                    <i data-lucide="list" class="w-4 h-4"></i>
                                    <span>Daftar Sarana</span>
                                </a>
                            </div>
                        </div>

                        <a href="transportasi.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-200 group <?= isActive('kelola_transportasi.php', $current_page); ?>">
                            <i data-lucide="bus" class="w-5 h-5"></i>
                            <span>Kelola Transportasi</span>
                        </a>

                        <!-- Kelola Users dengan submenu -->
                        <div>
                            <button @click="isUsersOpen = !isUsersOpen" class="flex items-center justify-between w-full px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-200 group text-gray-600 hover:bg-gray-50">
                                <div class="flex items-center gap-3">
                                    <i data-lucide="users" class="w-5 h-5"></i>
                                    <span>Kelola Users</span>
                                </div>
                                <i data-lucide="chevron-down" class="w-4 h-4 transition-transform duration-200" :class="isUsersOpen ? 'rotate-180' : ''"></i>
                            </button>
                            <div x-show="isUsersOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 max-h-0" x-transition:enter-end="opacity-100 max-h-96" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 max-h-96" x-transition:leave-end="opacity-0 max-h-0" class="ml-6 mt-1 space-y-1 overflow-hidden">
                                <a href="kelola_prodi.php" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-all duration-200 <?= isActive('kelola_prodi.php', $current_page); ?>">
                                    <i data-lucide="graduation-cap" class="w-4 h-4"></i>
                                    <span>Kelola Prodi</span>
                                </a>
                                <a href="kelola_user.php" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-all duration-200 <?= isActive('kelola_user.php', $current_page); ?>">
                                    <i data-lucide="user-plus" class="w-4 h-4"></i>
                                    <span>Kelola Pengguna</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <br>
                <a href="peminjaman.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-200 mb-1 group <?= isActive('peminjaman.php', $current_page); ?>">
                    <i data-lucide="clipboard-list" class="w-5 h-5"></i>
                    <span>Peminjaman</span>
                </a>

                <a href="pengaturan.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-200 mb-1 group <?= isActive('pengaturan.php', $current_page); ?>">
                    <i data-lucide="settings" class="w-5 h-5"></i>
                    <span>Pengaturan</span>
                </a> 
            </nav>

            <div class="p-4 border-t border-gray-100 bg-gray-50">
                <div class="flex items-start gap-3 mb-4 px-2">
                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center text-white overflow-hidden flex-shrink-0 shadow-md">
                        <?php if ($admin_foto): ?>
                            <img src="<?= $admin_foto ?>" class="w-full h-full object-cover">
                        <?php else: ?>
                            <i data-lucide="user" class="w-5 h-5"></i>
                        <?php endif; ?>
                    </div>
                    <div class="overflow-hidden flex-1 min-w-0">
                        <p class="text-sm font-bold text-gray-800 truncate mb-0.5"><?= htmlspecialchars($admin_name) ?></p>
                        
                        <?php if ($admin_email): ?>
                            <div class="flex items-center gap-1.5 text-gray-500 mb-1">
                                <i data-lucide="mail" class="w-3 h-3 flex-shrink-0"></i>
                                <p class="text-[11px] truncate"><?= htmlspecialchars($admin_email) ?></p>
                            </div>
                        <?php else: ?>
                            <div class="flex items-center gap-1.5 text-gray-400 mb-1">
                                <i data-lucide="user" class="w-3 h-3 flex-shrink-0"></i>
                                <p class="text-[11px] truncate"><?= htmlspecialchars($admin_username) ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <button @click="showLogoutModal = true" class="flex items-center justify-center gap-2 w-full py-2.5 rounded-xl text-sm font-semibold text-white bg-red-500 hover:bg-red-600 transition-all shadow-md active:scale-95">
                    <i data-lucide="log-out" class="w-4 h-4"></i>
                    Keluar
                </button>
            </div>
        </aside>

        <div x-show="isSidebarOpen" @click="isSidebarOpen = false" class="fixed inset-0 bg-black/50 z-20 md:hidden" x-transition.opacity></div>
    </div>

    <div x-show="showLogoutModal" 
         class="fixed inset-0 z-100 flex items-center justify-center p-4" 
         x-cloak>
        
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" 
             @click="!isLoggingOut && (showLogoutModal = false)"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"></div>
        
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6 border border-gray-100"
             x-show="showLogoutModal"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 scale-95 translate-y-4"
             x-transition:enter-end="opacity-100 scale-100 translate-y-0">
            
            <div x-show="!isLoggingOut">
                <div class="w-16 h-16 rounded-full bg-red-50 flex items-center justify-center text-red-500 mb-4 mx-auto">
                    <i data-lucide="alert-triangle" class="w-8 h-8"></i>
                </div>
                <div class="text-center mb-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-2">Konfirmasi Keluar</h3>
                    <p class="text-sm text-gray-500">Apakah Anda yakin ingin mengakhiri sesi ini?</p>
                </div>
                <div class="flex gap-3">
                    <button @click="showLogoutModal = false" class="flex-1 py-2.5 rounded-lg text-sm font-semibold text-gray-600 bg-gray-100 hover:bg-gray-200 transition-colors">Batal</button>
                    <button @click="isLoggingOut = true; setTimeout(() => { window.location.href = '../auth/logout.php' }, 800)" 
                            class="flex-1 py-2.5 rounded-lg text-sm font-semibold text-white bg-red-600 hover:bg-red-700 shadow-lg shadow-red-200">
                        Ya, Keluar
                    </button>
                </div>
            </div>
            
            <div x-show="isLoggingOut" class="flex flex-col items-center justify-center py-8">
                <div class="w-10 h-10 border-4 border-blue-100 border-t-blue-600 rounded-full animate-spin mb-4"></div>
                <p class="text-blue-600 font-medium italic">Sedang keluar...</p>
            </div>
        </div>
    </div>
</div>

<script>
    lucide.createIcons();
    (function(){
        const activeClasses = ['bg-gradient-to-r','from-yellow-50','to-green-50','text-green-700','shadow-sm','border-r-4','border-green-600'];
        const navSelector = 'nav';

        function clearActive(){
            document.querySelectorAll(`${navSelector} a`).forEach(el => el.classList.remove(...activeClasses));
        }

        function setActive(el){
            if(!el) return;
            clearActive();
            el.classList.add(...activeClasses);

            const parentSub = el.closest('.ml-6');
            if(parentSub){
                const toggleBtn = parentSub.previousElementSibling;
                if(toggleBtn && toggleBtn.click) toggleBtn.click();
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll(`${navSelector} a`).forEach(a => {
                a.addEventListener('click', function(){
                    setActive(this);
                    try{ localStorage.setItem('admin-nav-active', this.getAttribute('href')); }catch(e){}
                });
            });

            const basename = window.location.pathname.split('/').pop();
            let activeEl = document.querySelector(`${navSelector} a[href="${basename}"]`);
            if(!activeEl){
                const stored = localStorage.getItem('admin-nav-active');
                if(stored) activeEl = document.querySelector(`${navSelector} a[href="${stored}"]`);
            }
            if(activeEl) setActive(activeEl);
        });
    })();
</script>