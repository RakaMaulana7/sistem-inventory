<footer class="bg-[#1a1a1a] text-gray-300 py-16 border-t-4 border-brand font-sans">
    <div class="max-w-7xl mx-auto grid grid-cols-1 md:grid-cols-3 gap-10 px-6 lg:px-8">
        
        <div class="space-y-4">
            <div class="flex items-center space-x-3 mb-4 bg-white/5 w-fit p-3 rounded-xl border border-white/10 backdrop-blur-sm">
                <img src="../assets/umsura.png" alt="UMS" class="h-8 sm:h-9 md:h-10 w-auto object-contain" />
                <img src="../assets/kampus.webp" alt="Kampus Merdeka" class="h-8 sm:h-9 md:h-10 w-auto object-contain" />
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


<style>
    @keyframes bounce-subtle {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-3px); }
    }
    .group:hover .group-hover\:animate-bounce-subtle {
        animation: bounce-subtle 0.6s ease-in-out infinite;
    }
</style>

<script>
    // Pastikan Lucide terinisialisasi setiap kali file ini dipanggil
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
</script>