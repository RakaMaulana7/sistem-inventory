<?php
session_start();
require "../auth/auth_helper.php";
require "../config/database.php";

cek_kemanan_login($pdo);

// 1. PROTEKSI HALAMAN
if (!isset($_SESSION['login']) || $_SESSION['role'] !== 'user') {
    header("Location: ../login.html");
    exit;
}

// Ambil data user
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Ambil Kepala Program Studi untuk ditampilkan di profil jika tersedia
$kepala_prodi = null;
if (!empty($user['prodi'])) {
    $kpStmt = $pdo->prepare("SELECT kepala_prodi FROM prodi WHERE nama_prodi = ? LIMIT 1");
    $kpStmt->execute([$user['prodi']]);
    $kepala_prodi = $kpStmt->fetchColumn();
}

$error_message = "";
$success_message = "";

// Logika Hapus Foto
if (isset($_GET['delete_photo'])) {
    try {
        $stmt = $pdo->prepare("UPDATE users SET foto = NULL WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        header("Location: profile.php?success=photo_deleted");
        exit;
    } catch (Exception $e) {
        $error_message = "Gagal menghapus foto: " . $e->getMessage();
    }
}

// Logika Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $foto_data = $_POST['foto_base64'] ?? '';
    $email_input = isset($_POST['email']) ? trim($_POST['email']) : null;
    if ($email_input === '') $email_input = null;

    try {
        if (!empty($new_password) && $new_password !== $confirm_password) throw new Exception("Password tidak cocok!");

        if ($email_input !== null && !filter_var($email_input, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Format email tidak valid!");
        }

        if ($email_input !== null) {
            $check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id != ?");
            $check->execute([$email_input, $_SESSION['user_id']]);
            if ($check->fetchColumn() > 0) {
                throw new Exception("Email sudah digunakan oleh akun lain.");
            }
        }

        $setParts = [];
        $params = [];

        if (!empty($new_password)) {
            $setParts[] = "password = ?";
            $params[] = password_hash($new_password, PASSWORD_DEFAULT);
        }

        if (!empty($foto_data)) {
            $setParts[] = "foto = ?";
            $params[] = $foto_data;
        }

        if (isset($_POST['email'])) {
            $setParts[] = "email = ?";
            $params[] = $email_input;
        }

        if (count($setParts) > 0) {
            $sql = "UPDATE users SET " . implode(", ", $setParts) . " WHERE id = ?";
            $params[] = $_SESSION['user_id'];
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        }

        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();

        header("Location: profile.php?success=updated");
        exit;
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

if (isset($_GET['success'])) {
    if ($_GET['success'] === 'updated') {
        $success_message = "Profil berhasil diperbarui!";
    } else if ($_GET['success'] === 'photo_deleted') {
        $success_message = "Foto profil berhasil dihapus!";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../assets/kampusums.png" />
    <title>Profil Saya - Fakultas Teknik</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }
    </style>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: '#10b981',
                        brandHover: '#059669',
                    }
                }
            }
        }
    </script>
</head>
<body class="min-h-screen bg-gray-50" x-data="profileApp()" x-init="init()">

    <?php include "../components/UserNavbar.php"; ?>

    <!-- Error Modal -->
    <?php if ($error_message): ?>
    <div class="fixed inset-0 z-50 flex items-center justify-center px-4 bg-black/50 backdrop-blur-sm">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-6 text-center">
            <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i data-lucide="alert-circle" class="text-red-600 w-8 h-8"></i>
            </div>
            <h3 class="text-xl font-bold text-gray-900 mb-2">Gagal Menyimpan</h3>
            <p class="text-gray-600 mb-6"><?php echo $error_message; ?></p>
            <button onclick="window.location.reload()" class="w-full py-3 bg-red-600 text-white rounded-xl font-semibold hover:bg-red-700 transition-all">
                Coba Lagi
            </button>
        </div>
    </div>
    <?php endif; ?>

    <!-- Success Modal -->
    <?php if ($success_message): ?>
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 2000)" class="fixed inset-0 z-50 flex items-center justify-center px-4 bg-black/50 backdrop-blur-sm">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-6 text-center">
            <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i data-lucide="check-circle" class="text-green-600 w-8 h-8"></i>
            </div>
            <h3 class="text-xl font-bold text-gray-900 mb-2">Berhasil!</h3>
            <p class="text-gray-600"><?php echo $success_message; ?></p>
        </div>
    </div>
    <?php endif; ?>

    <main class="flex-1 pt-20 pb-20 px-4 md:px-6 lg:px-8 w-full max-w-6xl mx-auto">
        
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
            <div>
                <h1 class="text-2xl md:text-3xl font-bold text-gray-800">Profil Saya</h1>
                <p class="text-gray-500 mt-1 text-sm md:text-base">Kelola informasi akun dan keamanan</p>
            </div>
            <a href="dashboard.php" class="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-white border border-gray-200 text-gray-700 rounded-xl hover:bg-gray-50 hover:border-gray-300 transition-all font-medium shadow-sm">
                <i data-lucide="arrow-left" class="w-4 h-4"></i>
                <span>Kembali</span>
            </a>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 lg:gap-8">
            
            <!-- Sidebar Foto Profil -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 text-center lg:sticky lg:top-24">
                    
                    <!-- Foto Profil -->
                    <div class="relative mx-auto w-32 h-32 md:w-40 md:h-40 mb-5 group">
                        <div class="w-full h-full rounded-full overflow-hidden border-4 shadow-md transition-all duration-300" :class="showEdit ? 'border-brand' : 'border-gray-200'">
                            <img id="previewImg" :src="currentPhoto" class="w-full h-full object-cover">
                        </div>

                        <!-- Overlay Edit/Upload -->
                        <div x-show="showEdit" @click="$refs.fileInput.click()" class="absolute inset-0 bg-black/60 rounded-full flex flex-col items-center justify-center text-white opacity-0 group-hover:opacity-100 transition-opacity cursor-pointer">
                            <i :data-lucide="isDefaultPhoto ? 'upload' : 'camera'" class="w-6 h-6"></i>
                            <span class="text-xs font-semibold mt-1.5" x-text="isDefaultPhoto ? 'Upload Foto' : 'Ubah Foto'"></span>
                        </div>

                        <!-- Tombol Hapus -->
                        <button x-show="showEdit && !isDefaultPhoto" @click="confirmDeletePhoto()" type="button" class="absolute -bottom-1 -right-1 p-2.5 bg-red-500 text-white rounded-full shadow-lg hover:bg-red-600 transition-all opacity-0 group-hover:opacity-100">
                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                        </button>
                    </div>

                    <!-- Info User -->
                    <h2 class="text-lg md:text-xl font-bold text-gray-800 mb-1"><?php echo htmlspecialchars($user['nama']); ?></h2>
                    <p class="text-gray-500 text-sm mb-2"><?php echo htmlspecialchars($user['username']); ?></p>
                    <span class="inline-block px-3 py-1 bg-green-100 text-green-700 rounded-full text-xs font-semibold mb-6">
                        <?php echo ucfirst($user['role']); ?>
                    </span>

                    <!-- Tombol Edit -->
                    <button x-show="!showEdit" @click="toggleEdit()" class="w-full py-2.5 rounded-xl bg-brand text-white font-semibold hover:bg-brandHover transition-all flex items-center justify-center gap-2 shadow-md">
                        <i data-lucide="edit-3" class="w-4 h-4"></i> Edit Profil
                    </button>
                    <button x-show="showEdit" x-cloak @click="toggleEdit()" class="w-full py-2.5 rounded-xl border-2 border-red-300 text-red-600 font-semibold hover:bg-red-50 transition-all flex items-center justify-center gap-2">
                        <i data-lucide="x" class="w-4 h-4"></i> Batal
                    </button>
                </div>
            </div>

            <!-- Content Area -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 md:p-8">
                    
                    <!-- Informasi Pribadi -->
                    <div class="mb-8">
                        <h3 class="text-lg font-bold text-gray-800 mb-5 flex items-center gap-2">
                            <div class="p-1.5 bg-green-100 rounded-lg">
                                <i data-lucide="user" class="text-brand w-5 h-5"></i>
                            </div>
                            Informasi Pribadi
                        </h3>

                        <div class="space-y-3">
                            <!-- Nama -->
                            <div class="flex items-center gap-4 p-4 rounded-xl bg-gray-50 hover:bg-gray-100 transition-all">
                                <div class="p-2 bg-white rounded-lg shadow-sm">
                                    <i data-lucide="user" class="w-5 h-5 text-gray-600"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs text-gray-500 font-medium uppercase tracking-wide mb-0.5">Nama Lengkap</p>
                                    <p class="text-gray-800 font-semibold truncate"><?php echo htmlspecialchars($user['nama']); ?></p>
                                </div>
                            </div>

                            <!-- NIM -->
                            <div class="flex items-center gap-4 p-4 rounded-xl bg-gray-50 hover:bg-gray-100 transition-all">
                                <div class="p-2 bg-white rounded-lg shadow-sm">
                                    <i data-lucide="hash" class="w-5 h-5 text-gray-600"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs text-gray-500 font-medium uppercase tracking-wide mb-0.5">NIM / Username</p>
                                    <p class="text-gray-800 font-semibold font-mono"><?php echo htmlspecialchars($user['username']); ?></p>
                                </div>
                            </div>

                            <!-- Email -->
                            <div class="flex items-center gap-4 p-4 rounded-xl bg-gray-50 hover:bg-gray-100 transition-all">
                                <div class="p-2 bg-white rounded-lg shadow-sm">
                                    <i data-lucide="mail" class="w-5 h-5 text-gray-600"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs text-gray-500 font-medium uppercase tracking-wide mb-0.5">Email</p>
                                    <p class="text-gray-800 font-semibold truncate"><?php echo $user['email'] ? htmlspecialchars($user['email']) : '-'; ?></p>
                                </div>
                            </div>

                            <!-- Prodi & Kepala Prodi -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div class="flex items-center gap-4 p-4 rounded-xl bg-gray-50 hover:bg-gray-100 transition-all">
                                    <div class="p-2 bg-white rounded-lg shadow-sm">
                                        <i data-lucide="graduation-cap" class="w-5 h-5 text-gray-600"></i>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs text-gray-500 font-medium uppercase tracking-wide mb-0.5">Program Studi</p>
                                        <p class="text-gray-800 font-semibold text-sm truncate"><?php echo htmlspecialchars($user['prodi']); ?></p>
                                    </div>
                                </div>

                                <div class="flex items-center gap-4 p-4 rounded-xl bg-gray-50 hover:bg-gray-100 transition-all">
                                    <div class="p-2 bg-white rounded-lg shadow-sm">
                                        <i data-lucide="user-check" class="w-5 h-5 text-gray-600"></i>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs text-gray-500 font-medium uppercase tracking-wide mb-0.5">Kepala Prodi</p>
                                        <p class="text-gray-800 font-semibold text-sm truncate"><?php echo $kepala_prodi ? htmlspecialchars($kepala_prodi) : '-'; ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Form Edit (Conditional) -->
                    <div x-show="showEdit" x-cloak x-transition class="pt-8 border-t border-gray-200">
                        <form action="" method="POST" @submit="handleSubmit">
                            <input type="hidden" name="foto_base64" x-model="photoBase64">
                            <input type="file" x-ref="fileInput" accept="image/*" class="hidden" @change="handleFileSelect">

                            <h3 class="text-lg font-bold text-gray-800 mb-5 flex items-center gap-2">
                                <div class="p-1.5 bg-blue-100 rounded-lg">
                                    <i data-lucide="lock" class="text-blue-600 w-5 h-5"></i>
                                </div>
                                Keamanan Akun
                            </h3>
                            
                            <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-6 text-sm text-amber-800 flex items-start gap-3">
                                <i data-lucide="info" class="w-5 h-5 mt-0.5 flex-shrink-0"></i>
                                <span>Kosongkan kolom password jika hanya ingin mengubah email atau foto profil.</span>
                            </div>

                            <div class="space-y-4">
                                <!-- Email -->
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Email</label>
                                    <div class="relative">
                                        <i data-lucide="mail" class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 w-5 h-5"></i>
                                        <input type="email" name="email" x-model="email" class="w-full pl-11 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-brand focus:border-transparent outline-none transition-all" placeholder="email@example.com">
                                    </div>
                                </div>

                                <!-- Password Baru -->
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Password Baru</label>
                                    <div class="relative">
                                        <i data-lucide="lock" class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 w-5 h-5"></i>
                                        <input type="password" name="new_password" class="w-full pl-11 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-brand focus:border-transparent outline-none transition-all" placeholder="Masukkan password baru">
                                    </div>
                                </div>

                                <!-- Konfirmasi Password -->
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Konfirmasi Password</label>
                                    <div class="relative">
                                        <i data-lucide="lock" class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 w-5 h-5"></i>
                                        <input type="password" name="confirm_password" class="w-full pl-11 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-brand focus:border-transparent outline-none transition-all" placeholder="Ulangi password baru">
                                    </div>
                                </div>

                                <!-- Submit Button -->
                                <button type="submit" class="w-full mt-6 bg-brand text-white py-3 rounded-xl font-bold hover:bg-brandHover transition-all shadow-lg flex items-center justify-center gap-2">
                                    <i data-lucide="save" class="w-5 h-5"></i> Simpan Perubahan
                                </button>
                            </div>
                        </form>
                    </div>

                </div>
            </div>
        </div>
    </main>

    <?php include "../components/Footer.php"; ?>

    <script>
        function profileApp() {
            return {
                showEdit: false,
                currentPhoto: '<?php echo $user['foto'] ?: 'https://cdn-icons-png.flaticon.com/512/847/847969.png'; ?>',
                photoBase64: '',
                defaultPhoto: 'https://cdn-icons-png.flaticon.com/512/847/847969.png',
                email: '<?php echo htmlspecialchars($user['email'] ?? '') ?>',
                
                get isDefaultPhoto() {
                    return this.currentPhoto === this.defaultPhoto || !this.currentPhoto;
                },
                
                init() {
                    this.$nextTick(() => lucide.createIcons());
                },
                
                toggleEdit() {
                    this.showEdit = !this.showEdit;
                    this.$nextTick(() => lucide.createIcons());
                },
                
                handleFileSelect(event) {
                    const file = event.target.files[0];
                    if (file) {
                        if (file.size > 2 * 1024 * 1024) {
                            alert('Ukuran file terlalu besar! Maksimal 2MB.');
                            return;
                        }
                        
                        if (!file.type.startsWith('image/')) {
                            alert('File harus berupa gambar!');
                            return;
                        }
                        
                        const reader = new FileReader();
                        reader.onload = (e) => {
                            this.currentPhoto = e.target.result;
                            this.photoBase64 = e.target.result;
                            this.$nextTick(() => lucide.createIcons());
                        };
                        reader.readAsDataURL(file);
                    }
                },
                
                confirmDeletePhoto() {
                    if (confirm('Apakah Anda yakin ingin menghapus foto profil?')) {
                        window.location.href = 'profile.php?delete_photo=1';
                    }
                },
                
                handleSubmit(event) {
                    const newPassword = event.target.new_password.value;
                    const confirmPassword = event.target.confirm_password.value;
                    const emailVal = (this.email || '').trim();

                    if (emailVal && !/^\S+@\S+\.\S+$/.test(emailVal)) {
                        alert('Format email tidak valid!');
                        event.preventDefault();
                        return false;
                    }

                    if (!newPassword && !this.photoBase64 && !emailVal) {
                        alert('Tidak ada perubahan yang dilakukan!');
                        event.preventDefault();
                        return false;
                    }
                    
                    if (newPassword && newPassword !== confirmPassword) {
                        alert('Password dan konfirmasi password tidak cocok!');
                        event.preventDefault();
                        return false;
                    }
                    
                    if (newPassword && newPassword.length < 6) {
                        alert('Password minimal 6 karakter!');
                        event.preventDefault();
                        return false;
                    }
                    
                    return true;
                }
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            lucide.createIcons();
            let count = 0;
            const interval = setInterval(() => {
                lucide.createIcons();
                count++;
                if (count >= 3) clearInterval(interval);
            }, 500);
        });

        document.addEventListener('alpine:initialized', () => {
            lucide.createIcons();
        });
    </script>
</body>
</html>