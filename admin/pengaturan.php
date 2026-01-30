<?php
session_start();
require "../config/database.php";
require "../auth/auth_helper.php";

cek_kemanan_login($pdo);

// ============================================================================
// PROTEKSI HALAMAN
// ============================================================================
if (!isset($_SESSION['login']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// ============================================================================
// AMBIL DATA USER
// ============================================================================
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// ============================================================================
// HELPER FUNCTIONS - PENGATURAN DATABASE
// ============================================================================

/**
 * Ambil nilai pengaturan dari database
 */
function getSetting($pdo, $nama_setting, $default = '') {
    $stmt = $pdo->prepare("SELECT nilai FROM pengaturan WHERE nama_setting = ?");
    $stmt->execute([$nama_setting]);
    $result = $stmt->fetch();
    return $result ? $result['nilai'] : $default;
}

/**
 * Update atau insert pengaturan ke database
 */
function updateSetting($pdo, $nama_setting, $nilai, $deskripsi = '') {
    $check = $pdo->prepare("SELECT id FROM pengaturan WHERE nama_setting = ?");
    $check->execute([$nama_setting]);
    
    if ($check->fetch()) {
        $stmt = $pdo->prepare("UPDATE pengaturan SET nilai = ? WHERE nama_setting = ?");
        return $stmt->execute([$nilai, $nama_setting]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO pengaturan (nama_setting, nilai, deskripsi) VALUES (?, ?, ?)");
        return $stmt->execute([$nama_setting, $nilai, $deskripsi]);
    }
}

/**
 * Hapus pengaturan dari database
 */
function deleteSetting($pdo, $nama_setting) {
    $stmt = $pdo->prepare("DELETE FROM pengaturan WHERE nama_setting = ?");
    return $stmt->execute([$nama_setting]);
}

/**
 * Hitung jumlah steps yang ada
 */
function getStepsCount($pdo) {
    $stmt = $pdo->query("SELECT COUNT(*) FROM pengaturan WHERE nama_setting LIKE 'panduan_step%_title'");
    return (int) $stmt->fetchColumn();
}

/**
 * Hitung jumlah FAQs yang ada
 */
function getFaqsCount($pdo) {
    $stmt = $pdo->query("SELECT COUNT(*) FROM pengaturan WHERE nama_setting LIKE 'panduan_faq%_question'");
    return (int) $stmt->fetchColumn();
}

/**
 * Render section template upload
 */
function renderTemplateSection($kategori, $template) {
    $colorMap = [
        'ruangan' => ['primary' => 'blue', 'secondary' => 'indigo'],
        'sarana' => ['primary' => 'green', 'secondary' => 'emerald'],
        'transportasi' => ['primary' => 'purple', 'secondary' => 'violet']
    ];
    
    $colors = $colorMap[$kategori];
    $primaryColor = $colors['primary'];
    $secondaryColor = $colors['secondary'];
    $kategoriLabel = ucfirst($kategori);
    ?>
    
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Form Upload Template -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 md:p-8">
            <div class="flex items-center gap-3 mb-6 pb-4 border-b border-gray-100">
                <div class="p-2 bg-<?= $primaryColor ?>-100 rounded-lg">
                    <i data-lucide="upload" class="text-<?= $primaryColor ?>-600 w-6 h-6"></i>
                </div>
                <div>
                    <h2 class="text-lg font-bold text-gray-800">Upload Template <?= $kategoriLabel ?></h2>
                    <p class="text-xs text-gray-500">File PDF, DOC, atau DOCX</p>
                </div>
            </div>

            <form method="POST" enctype="multipart/form-data" class="space-y-5">
                <input type="hidden" name="kategori" value="<?= $kategori ?>">
                
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">Nama Template</label>
                    <input 
                        type="text" 
                        name="template_name" 
                        value="<?= htmlspecialchars($template['name']) ?>"
                        placeholder="Contoh: Template Surat Peminjaman <?= $kategoriLabel ?>"
                        class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-<?= $primaryColor ?>-500 focus:ring-2 focus:ring-<?= $primaryColor ?>-100 outline-none transition-all"
                        required>
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">File Template</label>
                    <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-2xl hover:border-<?= $primaryColor ?>-500 transition-all bg-gray-50 relative">
                        <div class="space-y-1 text-center">
                            <i data-lucide="file-text" class="mx-auto h-12 w-12 text-gray-400"></i>
                            <div class="flex text-sm text-gray-600 justify-center">
                                <label for="template_file_<?= $kategori ?>" class="relative cursor-pointer rounded-md font-medium text-<?= $primaryColor ?>-600 hover:text-<?= $primaryColor ?>-500">
                                    <span>Pilih file</span>
                                </label>
                                <p class="pl-1">atau drag and drop</p>
                            </div>
                            <p class="text-xs text-gray-500">PDF, DOC, DOCX maksimal 5MB</p>
                        </div>
                        <input type="file" name="template_file" id="template_file_<?= $kategori ?>" accept=".pdf,.doc,.docx" class="absolute inset-0 opacity-0 cursor-pointer" required>
                    </div>
                </div>

                <button type="submit" name="upload_template"
                    class="w-full px-6 py-3 rounded-xl bg-gradient-to-r from-<?= $primaryColor ?>-500 to-<?= $secondaryColor ?>-600 text-white font-bold hover:from-<?= $primaryColor ?>-600 hover:to-<?= $secondaryColor ?>-700 shadow-lg hover:shadow-xl transition-all flex items-center justify-center gap-2">
                    <i data-lucide="upload" class="w-4 h-4"></i>
                    Upload Template
                </button>
            </form>
        </div>

        <!-- Preview Template Aktif -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 md:p-8">
            <div class="flex items-center gap-3 mb-6 pb-4 border-b border-gray-100">
                <div class="p-2 bg-green-100 rounded-lg">
                    <i data-lucide="file-check" class="text-green-600 w-6 h-6"></i>
                </div>
                <div>
                    <h2 class="text-lg font-bold text-gray-800">Template Aktif</h2>
                    <p class="text-xs text-gray-500">Template yang sedang digunakan untuk <?= $kategoriLabel ?></p>
                </div>
            </div>

            <?php if ($template['file']): ?>
                <div class="space-y-4">
                    <div class="p-5 bg-gradient-to-br from-green-50 to-blue-50 rounded-xl border border-green-200">
                        <div class="flex items-start gap-4">
                            <div class="p-3 bg-white rounded-lg shadow-sm">
                                <i data-lucide="<?= $template['icon'] ?>" class="w-8 h-8 text-<?= $primaryColor ?>-600"></i>
                            </div>
                            <div class="flex-1">
                                <h3 class="font-bold text-gray-800 mb-1"><?= htmlspecialchars($template['name']) ?></h3>
                                <p class="text-sm text-gray-600 font-mono"><?= htmlspecialchars($template['file']) ?></p>
                                
                                <?php 
                                $file_path = "../uploads/templates/" . $template['file'];
                                if (file_exists($file_path)):
                                    $file_size = filesize($file_path);
                                    $file_size_kb = round($file_size / 1024, 2);
                                    $file_ext = strtoupper(pathinfo($template['file'], PATHINFO_EXTENSION));
                                ?>
                                    <div class="flex items-center gap-3 mt-3 text-xs text-gray-500">
                                        <span class="px-2 py-1 bg-<?= $primaryColor ?>-100 text-<?= $primaryColor ?>-700 rounded-md font-bold"><?= $file_ext ?></span>
                                        <span><?= $file_size_kb ?> KB</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="flex gap-3">
                        <a href="../uploads/templates/<?= htmlspecialchars($template['file']) ?>" download
                            class="flex-1 px-4 py-2.5 rounded-xl border border-<?= $primaryColor ?>-500 text-<?= $primaryColor ?>-600 font-bold hover:bg-<?= $primaryColor ?>-50 transition-all flex items-center justify-center gap-2 text-sm">
                            <i data-lucide="download" class="w-4 h-4"></i>
                            Download
                        </a>
                        <button onclick="if(confirm('Yakin ingin menghapus template ini?')) window.location.href='pengaturan.php?delete_template=1&kategori=<?= $kategori ?>'"
                            class="px-4 py-2.5 rounded-xl bg-red-100 text-red-600 font-bold hover:bg-red-200 transition-all flex items-center gap-2 text-sm">
                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                            Hapus
                        </button>
                    </div>
                </div>
            <?php else: ?>
                <div class="text-center py-12">
                    <div class="inline-flex items-center justify-center w-16 h-16 bg-gray-100 rounded-full mb-4">
                        <i data-lucide="file-x" class="w-8 h-8 text-gray-400"></i>
                    </div>
                    <p class="text-gray-500 font-semibold mb-2">Belum ada template</p>
                    <p class="text-sm text-gray-400">Upload template untuk kategori <?= $kategoriLabel ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

// ============================================================================
// LOGIC HANDLERS
// ============================================================================

// --- VIDEO YOUTUBE ---
$youtube_id = getSetting($pdo, 'youtube_video_id', 'eZncsrYwiRI');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_video'])) {
    $new_video_id = trim($_POST['youtube_video_id']);
    
    if (preg_match('/^[a-zA-Z0-9_-]{11}$/', $new_video_id)) {
        if (updateSetting($pdo, 'youtube_video_id', $new_video_id, 'ID video YouTube untuk halaman panduan')) {
            $youtube_id = $new_video_id;
            header("Location: pengaturan.php?tab=website&status=video_updated");
            exit;
        } else {
            header("Location: pengaturan.php?tab=website&status=video_error");
            exit;
        }
    } else {
        header("Location: pengaturan.php?tab=website&status=video_invalid");
        exit;
    }
}

// --- UPLOAD TEMPLATE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_template'])) {
    $kategori = $_POST['kategori'] ?? 'ruangan';
    $target_dir = "../uploads/templates/";
    
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0755, true);
    }
    
    if (!empty($_FILES['template_file']['name'])) {
        $file_ext = strtolower(pathinfo($_FILES['template_file']['name'], PATHINFO_EXTENSION));
        $allowed_ext = ['pdf', 'doc', 'docx'];
        
        if (in_array($file_ext, $allowed_ext)) {
            $old_template = getSetting($pdo, "template_{$kategori}_file");
            if ($old_template && file_exists($target_dir . $old_template)) {
                unlink($target_dir . $old_template);
            }
            
            $file_name = "template_{$kategori}_" . time() . "." . $file_ext;
            
            if (move_uploaded_file($_FILES['template_file']['tmp_name'], $target_dir . $file_name)) {
                $template_name = $_POST['template_name'] ?: "Template Surat Peminjaman " . ucfirst($kategori);
                updateSetting($pdo, "template_{$kategori}_file", $file_name, "File template dokumen peminjaman {$kategori}");
                updateSetting($pdo, "template_{$kategori}_name", $template_name, "Nama template dokumen peminjaman {$kategori}");
                
                header("Location: pengaturan.php?tab=template&status=template_uploaded&cat={$kategori}");
                exit;
            } else {
                header("Location: pengaturan.php?tab=template&status=template_upload_failed&cat={$kategori}");
                exit;
            }
        } else {
            header("Location: pengaturan.php?tab=template&status=template_invalid_format&cat={$kategori}");
            exit;
        }
    } else {
        header("Location: pengaturan.php?tab=template&status=template_no_file&cat={$kategori}");
        exit;
    }
}

// --- HAPUS TEMPLATE ---
if (isset($_GET['delete_template'])) {
    $kategori = $_GET['kategori'] ?? 'ruangan';
    $template_file = getSetting($pdo, "template_{$kategori}_file");
    
    if ($template_file && file_exists("../uploads/templates/" . $template_file)) {
        unlink("../uploads/templates/" . $template_file);
    }
    
    deleteSetting($pdo, "template_{$kategori}_file");
    deleteSetting($pdo, "template_{$kategori}_name");
    
    header("Location: pengaturan.php?tab=template&status=template_deleted&cat={$kategori}");
    exit;
}

// --- DATA TEMPLATE ---
$templates = [
    'ruangan' => [
        'file' => getSetting($pdo, 'template_ruangan_file'),
        'name' => getSetting($pdo, 'template_ruangan_name', 'Template Surat Peminjaman Ruangan'),
        'icon' => 'door-open',
        'color' => 'blue'
    ],
    'sarana' => [
        'file' => getSetting($pdo, 'template_sarana_file'),
        'name' => getSetting($pdo, 'template_sarana_name', 'Template Surat Peminjaman Sarana'),
        'icon' => 'package',
        'color' => 'green'
    ],
    'transportasi' => [
        'file' => getSetting($pdo, 'template_transportasi_file'),
        'name' => getSetting($pdo, 'template_transportasi_name', 'Template Surat Peminjaman Transportasi'),
        'icon' => 'car',
        'color' => 'purple'
    ]
];

// --- HAPUS STEP ---
if (isset($_GET['delete_step'])) {
    $step_num = (int) $_GET['delete_step'];
    
    deleteSetting($pdo, "panduan_step{$step_num}_title");
    deleteSetting($pdo, "panduan_step{$step_num}_desc");
    deleteSetting($pdo, "panduan_step{$step_num}_icon");
    
    $total_steps = getStepsCount($pdo);
    for ($i = $step_num + 1; $i <= $total_steps + 1; $i++) {
        $new_num = $i - 1;
        
        $title = getSetting($pdo, "panduan_step{$i}_title");
        $desc = getSetting($pdo, "panduan_step{$i}_desc");
        $icon = getSetting($pdo, "panduan_step{$i}_icon");
        
        if ($title) {
            updateSetting($pdo, "panduan_step{$new_num}_title", $title);
            updateSetting($pdo, "panduan_step{$new_num}_desc", $desc);
            updateSetting($pdo, "panduan_step{$new_num}_icon", $icon);
            
            deleteSetting($pdo, "panduan_step{$i}_title");
            deleteSetting($pdo, "panduan_step{$i}_desc");
            deleteSetting($pdo, "panduan_step{$i}_icon");
        }
    }
    
    header("Location: pengaturan.php?tab=panduan&status=step_deleted");
    exit;
}

// --- HAPUS FAQ ---
if (isset($_GET['delete_faq'])) {
    $faq_num = (int) $_GET['delete_faq'];
    
    deleteSetting($pdo, "panduan_faq{$faq_num}_question");
    deleteSetting($pdo, "panduan_faq{$faq_num}_answer");
    
    $total_faqs = getFaqsCount($pdo);
    for ($i = $faq_num + 1; $i <= $total_faqs + 1; $i++) {
        $new_num = $i - 1;
        
        $question = getSetting($pdo, "panduan_faq{$i}_question");
        $answer = getSetting($pdo, "panduan_faq{$i}_answer");
        
        if ($question) {
            updateSetting($pdo, "panduan_faq{$new_num}_question", $question);
            updateSetting($pdo, "panduan_faq{$new_num}_answer", $answer);
            
            deleteSetting($pdo, "panduan_faq{$i}_question");
            deleteSetting($pdo, "panduan_faq{$i}_answer");
        }
    }
    
    header("Location: pengaturan.php?tab=panduan&status=faq_deleted");
    exit;
}

// --- UPDATE TEKS PANDUAN ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_panduan_text'])) {
    try {
        $settings_to_update = [
            'panduan_hero_title' => $_POST['hero_title'],
            'panduan_hero_subtitle' => $_POST['hero_subtitle'],
            'panduan_video_title' => $_POST['video_title'],
            'panduan_video_subtitle' => $_POST['video_subtitle'],
            'panduan_faq_title' => $_POST['faq_title'],
            'panduan_faq_subtitle' => $_POST['faq_subtitle'],
            'panduan_cta_title' => $_POST['cta_title'],
            'panduan_cta_subtitle' => $_POST['cta_subtitle'],
        ];
        
        // Hapus steps lama
        $old_steps_count = getStepsCount($pdo);
        for ($i = 1; $i <= $old_steps_count; $i++) {
            deleteSetting($pdo, "panduan_step{$i}_title");
            deleteSetting($pdo, "panduan_step{$i}_desc");
            deleteSetting($pdo, "panduan_step{$i}_icon");
        }
        
        // Tambah steps baru
        if (isset($_POST['steps']) && is_array($_POST['steps'])) {
            foreach ($_POST['steps'] as $index => $step) {
                $num = $index + 1;
                $settings_to_update["panduan_step{$num}_title"] = $step['title'];
                $settings_to_update["panduan_step{$num}_desc"] = $step['desc'];
                $settings_to_update["panduan_step{$num}_icon"] = $step['icon'];
            }
        }
        
        // Hapus FAQs lama
        $old_faqs_count = getFaqsCount($pdo);
        for ($i = 1; $i <= $old_faqs_count; $i++) {
            deleteSetting($pdo, "panduan_faq{$i}_question");
            deleteSetting($pdo, "panduan_faq{$i}_answer");
        }
        
        // Tambah FAQs baru
        if (isset($_POST['faqs']) && is_array($_POST['faqs'])) {
            foreach ($_POST['faqs'] as $index => $faq) {
                $num = $index + 1;
                $settings_to_update["panduan_faq{$num}_question"] = $faq['question'];
                $settings_to_update["panduan_faq{$num}_answer"] = $faq['answer'];
            }
        }
        
        // Update semua settings
        foreach ($settings_to_update as $key => $value) {
            updateSetting($pdo, $key, $value);
        }
        
        header("Location: pengaturan.php?tab=panduan&status=panduan_updated");
        exit;
    } catch (Exception $e) {
        header("Location: pengaturan.php?tab=panduan&status=panduan_error");
        exit;
    }
}

// --- AMBIL DATA STEPS DAN FAQS ---
$steps_count = getStepsCount($pdo);
$steps = [];
for ($i = 1; $i <= $steps_count; $i++) {
    $steps[] = [
        'num' => $i,
        'title' => getSetting($pdo, "panduan_step{$i}_title", "Langkah {$i}"),
        'desc' => getSetting($pdo, "panduan_step{$i}_desc", "Deskripsi langkah {$i}"),
        'icon' => getSetting($pdo, "panduan_step{$i}_icon", "circle")
    ];
}

$faqs_count = getFaqsCount($pdo);
$faqs = [];
for ($i = 1; $i <= $faqs_count; $i++) {
    $faqs[] = [
        'num' => $i,
        'question' => getSetting($pdo, "panduan_faq{$i}_question", "Pertanyaan {$i}?"),
        'answer' => getSetting($pdo, "panduan_faq{$i}_answer", "Jawaban {$i}")
    ];
}

// Default data jika kosong
if ($steps_count === 0) {
    $steps = [
        ['num' => 1, 'title' => '1. Login ke Sistem', 'desc' => 'Masuk menggunakan akun yang terdaftar', 'icon' => 'log-in'],
        ['num' => 2, 'title' => '2. Pilih Aset', 'desc' => 'Cari dan pilih aset yang ingin dipinjam', 'icon' => 'search'],
        ['num' => 3, 'title' => '3. Ajukan Peminjaman', 'desc' => 'Isi formulir peminjaman dengan lengkap', 'icon' => 'file-text'],
    ];
}

if ($faqs_count === 0) {
    $faqs = [
        ['num' => 1, 'question' => 'Bagaimana cara meminjam aset?', 'answer' => 'Login ke sistem, pilih aset yang diinginkan, lalu ajukan peminjaman.'],
        ['num' => 2, 'question' => 'Berapa lama proses persetujuan?', 'answer' => 'Biasanya 1-2 hari kerja setelah pengajuan.'],
    ];
}

// --- HAPUS FOTO PROFIL ---
if (isset($_GET['delete_photo'])) {
    $old_foto = $user['foto'];
    if ($old_foto && file_exists("../uploads/profile/" . $old_foto)) {
        unlink("../uploads/profile/" . $old_foto);
    }
    
    $stmt = $pdo->prepare("UPDATE users SET foto = NULL WHERE id = ?");
    $stmt->execute([$user_id]);
    
    header("Location: pengaturan.php?status=photo_deleted");
    exit;
}

// --- UPDATE PROFIL ---
$message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $nama = $_POST['nama'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    $foto_name = $user['foto']; 
    
    // Handle upload foto
    if (!empty($_FILES['foto']['name'])) {
        $target_dir = "../uploads/profile/";
        
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0755, true);
        }
        
        if ($user['foto'] && file_exists($target_dir . $user['foto'])) {
            unlink($target_dir . $user['foto']);
        }
        
        $file_ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($file_ext, $allowed_ext)) {
            $foto_name = time() . "_" . uniqid() . "." . $file_ext;
            
            if (move_uploaded_file($_FILES['foto']['tmp_name'], $target_dir . $foto_name)) {
                $message = "upload_success";
            } else {
                $message = "upload_failed";
                $foto_name = $user['foto'];
            }
        } else {
            $message = "invalid_format";
            $foto_name = $user['foto'];
        }
    }

    // Update database
    try {
        if (!empty($new_password)) {
            if ($new_password === $confirm_password) {
                if (strlen($new_password) >= 8) {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $update = $pdo->prepare("UPDATE users SET nama = ?, foto = ?, password = ? WHERE id = ?");
                    $update->execute([$nama, $foto_name, $hashed_password, $user_id]);
                    $message = "success_with_password";
                } else {
                    $message = "error_password_length";
                }
            } else {
                $message = "error_password_mismatch";
            }
        } else {
            $update = $pdo->prepare("UPDATE users SET nama = ?, foto = ? WHERE id = ?");
            $update->execute([$nama, $foto_name, $user_id]);
            $message = "success";
        }
        
        if (in_array($message, ["success", "success_with_password", "upload_success"])) {
            $_SESSION['nama'] = $nama;
            header("Location: pengaturan.php?status=" . $message);
            exit;
        }
    } catch (PDOException $e) {
        $message = "error_system";
    }
}

// Profile photo URL
$profile_photo = $user['foto'] 
    ? "../uploads/profile/" . $user['foto'] 
    : "https://ui-avatars.com/api/?name=" . urlencode($user['nama']) . "&background=d13b1f&color=fff&size=200";

$activeTab = $_GET['tab'] ?? 'profile';

// ============================================================================
// ALERT MESSAGES CONFIGURATION
// ============================================================================
$alerts = [
    'success' => ['color' => 'green', 'icon' => 'check-circle', 'text' => 'Profil berhasil diperbarui!'],
    'success_with_password' => ['color' => 'green', 'icon' => 'check-circle', 'text' => 'Profil dan password berhasil diperbarui!'],
    'photo_deleted' => ['color' => 'blue', 'icon' => 'trash-2', 'text' => 'Foto profil berhasil dihapus!'],
    'upload_success' => ['color' => 'green', 'icon' => 'image', 'text' => 'Foto profil berhasil diupload!'],
    'upload_failed' => ['color' => 'red', 'icon' => 'x-circle', 'text' => 'Gagal mengupload foto!'],
    'invalid_format' => ['color' => 'orange', 'icon' => 'alert-triangle', 'text' => 'Format foto tidak valid! (JPG, PNG, GIF)'],
    'error_password_mismatch' => ['color' => 'red', 'icon' => 'alert-circle', 'text' => 'Password dan konfirmasi tidak cocok!'],
    'error_password_length' => ['color' => 'red', 'icon' => 'alert-circle', 'text' => 'Password minimal 8 karakter!'],
    'error_system' => ['color' => 'red', 'icon' => 'x-octagon', 'text' => 'Terjadi kesalahan sistem!'],
    'video_updated' => ['color' => 'green', 'icon' => 'check-circle', 'text' => 'Video YouTube berhasil diperbarui!'],
    'video_error' => ['color' => 'red', 'icon' => 'x-circle', 'text' => 'Gagal menyimpan video ke database!'],
    'video_invalid' => ['color' => 'orange', 'icon' => 'alert-triangle', 'text' => 'Format YouTube Video ID tidak valid!'],
    'panduan_updated' => ['color' => 'green', 'icon' => 'check-circle', 'text' => 'Teks panduan berhasil diperbarui!'],
    'panduan_error' => ['color' => 'red', 'icon' => 'x-circle', 'text' => 'Gagal menyimpan teks panduan!'],
    'step_deleted' => ['color' => 'blue', 'icon' => 'trash-2', 'text' => 'Langkah berhasil dihapus!'],
    'faq_deleted' => ['color' => 'blue', 'icon' => 'trash-2', 'text' => 'FAQ berhasil dihapus!'],
    'template_uploaded' => ['color' => 'green', 'icon' => 'check-circle', 'text' => 'Template berhasil diupload!'],
    'template_upload_failed' => ['color' => 'red', 'icon' => 'x-circle', 'text' => 'Gagal mengupload template!'],
    'template_invalid_format' => ['color' => 'orange', 'icon' => 'alert-triangle', 'text' => 'Format file tidak valid! (PDF, DOC, DOCX)'],
    'template_no_file' => ['color' => 'orange', 'icon' => 'alert-circle', 'text' => 'Tidak ada file yang dipilih!'],
    'template_deleted' => ['color' => 'blue', 'icon' => 'trash-2', 'text' => 'Template berhasil dihapus!'],
];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../assets/kampusums.png" />
    <title>Pengaturan - <?= htmlspecialchars($user['nama']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-[#FAFAFA] font-sans" x-data="settingsApp('<?= $activeTab ?>')">

    <?php include "../components/AdminNavbar.php"; ?>

    <main class="flex-1 ml-0 md:ml-60 p-8 md:p-10">
        <div class="max-w-6xl mx-auto pt-16 md:pt-0">
            
            <!-- Alert Messages -->
            <?php if (isset($_GET['status'])): ?>
            <div x-data="{ show: true }" x-show="show" x-transition class="mb-6">
                <?php
                $status = $_GET['status'];
                $alert = $alerts[$status] ?? ['color' => 'gray', 'icon' => 'info', 'text' => 'Notifikasi'];
                $color = $alert['color'];
                ?>
                <div class="bg-<?= $color ?>-50 border border-<?= $color ?>-200 text-<?= $color ?>-700 px-6 py-4 rounded-2xl flex items-center justify-between shadow-sm">
                    <div class="flex items-center gap-3">
                        <i data-lucide="<?= $alert['icon'] ?>" class="w-5 h-5"></i>
                        <span class="font-semibold"><?= $alert['text'] ?></span>
                    </div>
                    <button @click="show = false" class="text-<?= $color ?>-400 hover:text-<?= $color ?>-600">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>
            </div>
            <?php endif; ?>

            <!-- Tab Navigation -->
            <div class="mb-8 border-b border-gray-200">
                <div class="flex gap-4 overflow-x-auto">
                    <button @click="activeTab = 'profile'" :class="activeTab === 'profile' ? 'border-[#d13b1f] text-[#d13b1f]' : 'border-transparent text-gray-500 hover:text-gray-700'" class="px-4 py-3 border-b-2 font-semibold transition-colors flex items-center gap-2 whitespace-nowrap">
                        <i data-lucide="user" class="w-4 h-4"></i>
                        Profil Saya
                    </button>
                    <button @click="activeTab = 'template'" :class="activeTab === 'template' ? 'border-[#d13b1f] text-[#d13b1f]' : 'border-transparent text-gray-500 hover:text-gray-700'" class="px-4 py-3 border-b-2 font-semibold transition-colors flex items-center gap-2 whitespace-nowrap">
                        <i data-lucide="file-down" class="w-4 h-4"></i>
                        Template Dokumen
                    </button>
                    <button @click="activeTab = 'website'" :class="activeTab === 'website' ? 'border-[#d13b1f] text-[#d13b1f]' : 'border-transparent text-gray-500 hover:text-gray-700'" class="px-4 py-3 border-b-2 font-semibold transition-colors flex items-center gap-2 whitespace-nowrap">
                        <i data-lucide="youtube" class="w-4 h-4"></i>
                        Video Tutorial
                    </button>
                    <button @click="activeTab = 'panduan'" :class="activeTab === 'panduan' ? 'border-[#d13b1f] text-[#d13b1f]' : 'border-transparent text-gray-500 hover:text-gray-700'" class="px-4 py-3 border-b-2 font-semibold transition-colors flex items-center gap-2 whitespace-nowrap">
                        <i data-lucide="file-text" class="w-4 h-4"></i>
                        Teks Panduan
                    </button>
                </div>
            </div>

            <!-- TAB 1: Profil Saya -->
            <div x-show="activeTab === 'profile'" x-transition>
                <form action="" method="POST" enctype="multipart/form-data" @submit="return validateForm()">
                    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-10">
                        <div>
                            <h1 class="text-3xl font-extrabold tracking-tight text-slate-900">
                                Pengaturan Akun
                            </h1>
                            <p class="text-slate-500 mt-2 font-medium">
                                Kelola profil, keamanan, dan informasi pribadi Anda.
                            </p>
                        </div>
                        
                        <div class="flex gap-3">
                            <button type="button" @click="toggleEdit()" 
                                class="px-5 py-2.5 rounded-xl font-bold flex items-center gap-2 transition-all duration-300 bg-white border border-slate-200 text-slate-700 hover:text-[#d13b1f] hover:border-[#d13b1f]">
                                <template x-if="!isEditing">
                                    <span class="flex items-center gap-2"><i data-lucide="edit-2" class="w-4 h-4"></i> Edit Profil</span>
                                </template>
                                <template x-if="isEditing">
                                    <span class="flex items-center gap-2"><i data-lucide="x" class="w-4 h-4"></i> Batal</span>
                                </template>
                            </button>

                            <button type="submit" name="save_settings" x-show="isEditing" x-cloak
                                class="bg-[#d13b1f] hover:bg-red-700 text-white px-6 py-2.5 rounded-xl font-bold shadow-lg flex items-center gap-2 transition-all transform active:scale-95">
                                <i data-lucide="save" class="w-4 h-4"></i> Simpan Perubahan
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                        
                        <!-- Sidebar Kiri: Foto Profil -->
                        <div class="lg:col-span-1 space-y-6">
                            <div class="rounded-2xl p-6 shadow-sm border bg-white border-slate-100 flex flex-col items-center text-center">
                                <div class="relative mb-4 mt-2 group">
                                    <div class="w-32 h-32 rounded-full border-4 overflow-hidden border-slate-100 shadow-lg">
                                        <img :src="previewPhoto" class="w-full h-full object-cover" alt="Profile Photo" />
                                    </div>
                                    
                                    <div x-show="isEditing" x-cloak class="absolute -bottom-2 left-1/2 -translate-x-1/2 flex gap-2">
                                        <input type="file" name="foto" id="fotoInput" class="hidden" accept="image/jpeg,image/jpg,image/png,image/gif" 
                                            @change="handlePhotoUpload($event)">
                                        <label for="fotoInput" class="bg-[#d13b1f] text-white p-2.5 rounded-full shadow-lg cursor-pointer hover:bg-red-700 transition-all transform hover:scale-110 flex items-center justify-center">
                                            <i data-lucide="camera" class="w-4 h-4"></i>
                                        </label>
                                        
                                        <button type="button" @click="confirmDeletePhoto()" 
                                            class="bg-red-500 text-white p-2.5 rounded-full shadow-lg hover:bg-red-600 transition-all transform hover:scale-110 flex items-center justify-center">
                                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <h3 class="text-xl font-bold text-slate-800 mb-1"><?= htmlspecialchars($user['nama']) ?></h3>
                                <p class="text-[10px] font-bold bg-red-50 text-[#d13b1f] px-3 py-1 rounded-full uppercase tracking-wider border border-red-100">
                                    <?= $user['role'] ?>
                                </p>

                                <div class="mt-6 w-full space-y-3 pt-6 border-t border-slate-100 text-left">
                                    <div class="flex justify-between items-center text-sm">
                                        <span class="text-slate-500 flex items-center gap-2">
                                            <i data-lucide="hash" class="w-3.5 h-3.5"></i> ID/NIM
                                        </span>
                                        <span class="font-semibold font-mono text-slate-700"><?= $user['username'] ?></span>
                                    </div>
                                    <div class="flex justify-between items-center text-sm">
                                        <span class="text-slate-500 flex items-center gap-2">
                                            <i data-lucide="graduation-cap" class="w-3.5 h-3.5"></i> Program Studi
                                        </span>
                                        <span class="font-semibold text-slate-700"><?= htmlspecialchars($user['prodi']) ?></span>
                                    </div>
                                </div>
                            </div>

                            <div class="rounded-2xl p-5 shadow-sm border bg-blue-50 border-blue-100">
                                <div class="flex items-start gap-3">
                                    <div class="p-2 bg-blue-100 rounded-lg">
                                        <i data-lucide="info" class="w-4 h-4 text-blue-600"></i>
                                    </div>
                                    <div class="text-sm text-blue-700">
                                        <p class="font-bold mb-1">Tips Keamanan</p>
                                        <p class="text-xs text-blue-600">Gunakan password minimal 8 karakter dengan kombinasi huruf dan angka.</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Konten Kanan: Form -->
                        <div class="lg:col-span-2 space-y-6">
                            <!-- Informasi Profil -->
                            <div class="rounded-2xl p-8 shadow-sm border bg-white border-slate-100 transition-all" :class="isEditing ? 'ring-2 ring-[#d13b1f]/20' : ''">
                                <div class="flex items-center justify-between mb-6 pb-4 border-b border-slate-100">
                                    <div class="flex items-center gap-3">
                                        <div class="p-2 bg-red-50 rounded-lg text-[#d13b1f]">
                                            <i data-lucide="user" class="w-5 h-5"></i>
                                        </div>
                                        <h3 class="text-lg font-bold text-slate-800">Informasi Profil</h3>
                                    </div>
                                    <span x-show="!isEditing" class="text-xs text-slate-400 bg-slate-50 px-3 py-1 rounded-full font-semibold">Read-Only</span>
                                </div>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div class="md:col-span-2">
                                        <label class="block text-sm font-bold mb-2 text-slate-700">Nama Lengkap <span class="text-red-500">*</span></label>
                                        <input type="text" name="nama" x-model="formData.nama" :disabled="!isEditing" required
                                            class="w-full px-4 py-3 rounded-xl outline-none transition-all font-medium bg-slate-50 border border-slate-200 focus:border-[#d13b1f] focus:bg-white disabled:opacity-60 disabled:cursor-not-allowed">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-bold mb-2 text-slate-500">Username / NIP</label>
                                        <input type="text" value="<?= $user['username'] ?>" disabled 
                                            class="w-full px-4 py-3 rounded-xl font-mono bg-slate-100 border border-slate-200 text-slate-500 cursor-not-allowed">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-bold mb-2 text-slate-500">Program Studi</label>
                                        <input type="text" value="<?= $user['prodi'] ?>" disabled 
                                            class="w-full px-4 py-3 rounded-xl bg-slate-100 border border-slate-200 text-slate-500 cursor-not-allowed">
                                    </div>
                                </div>
                            </div>

                            <!-- Keamanan & Password -->
                            <div class="rounded-2xl p-8 shadow-sm border bg-white border-slate-100 transition-all" 
                                :class="isEditing ? 'ring-2 ring-[#d13b1f]/20' : 'opacity-60'">
                                <div class="flex items-center gap-3 mb-6 pb-4 border-b border-slate-100">
                                    <div class="p-2 bg-red-50 rounded-lg text-[#d13b1f]">
                                        <i data-lucide="shield" class="w-5 h-5"></i>
                                    </div>
                                    <h3 class="text-lg font-bold text-slate-800">Keamanan & Password</h3>
                                </div>

                                <div x-show="isEditing" class="flex items-start gap-3 p-4 bg-orange-50 border border-orange-200 rounded-xl mb-6 text-orange-700 text-sm">
                                    <i data-lucide="alert-circle" class="w-5 h-5 mt-0.5 flex-shrink-0"></i>
                                    <p><strong>Info:</strong> Kosongkan kolom password jika tidak ingin mengubahnya.</p>
                                </div>

                                <div class="grid grid-cols-1 gap-6">
                                    <div>
                                        <label class="block text-sm font-bold mb-2 text-slate-700">Password Baru</label>
                                        <input type="password" name="new_password" x-model="formData.newPassword" :disabled="!isEditing" 
                                            placeholder="Minimal 8 karakter"
                                            class="w-full px-4 py-3 rounded-xl outline-none transition-all font-medium bg-slate-50 border border-slate-200 focus:border-[#d13b1f] focus:bg-white disabled:opacity-60">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-bold mb-2 text-slate-700">Konfirmasi Password</label>
                                        <input type="password" name="confirm_password" x-model="formData.confirmPassword" :disabled="!isEditing"
                                            placeholder="Ulangi password baru"
                                            class="w-full px-4 py-3 rounded-xl outline-none transition-all font-medium bg-slate-50 border border-slate-200 focus:border-[#d13b1f] focus:bg-white disabled:opacity-60">
                                    </div>
                                    
                                    <div x-show="formData.newPassword && isEditing" x-cloak class="space-y-2">
                                        <div class="flex items-center justify-between text-xs">
                                            <span class="text-slate-600 font-semibold">Kekuatan Password:</span>
                                            <span :class="{
                                                'text-red-500': passwordStrength === 'Lemah',
                                                'text-orange-500': passwordStrength === 'Sedang',
                                                'text-green-500': passwordStrength === 'Kuat'
                                            }" class="font-bold" x-text="passwordStrength"></span>
                                        </div>
                                        <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
                                            <div class="h-full transition-all duration-300" 
                                                :class="{
                                                    'bg-red-500 w-1/3': passwordStrength === 'Lemah',
                                                    'bg-orange-500 w-2/3': passwordStrength === 'Sedang',
                                                    'bg-green-500 w-full': passwordStrength === 'Kuat'
                                                }"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <!-- TAB 2: Template Dokumen -->
            <div x-show="activeTab === 'template'" x-transition x-data="{ selectedCategory: 'ruangan' }">
                <div class="mb-10">
                    <h1 class="text-3xl font-extrabold tracking-tight text-slate-900">
                        Template Dokumen Peminjaman
                    </h1>
                    <p class="text-slate-500 mt-2 font-medium">
                        Kelola template surat untuk setiap kategori peminjaman (Ruangan, Sarana, Transportasi)
                    </p>
                </div>

                <div class="mb-6 border-b border-gray-200">
                    <div class="flex gap-2 overflow-x-auto">
                        <button @click="selectedCategory = 'ruangan'" :class="selectedCategory === 'ruangan' ? 'border-blue-500 text-blue-600 bg-blue-50' : 'border-transparent text-gray-500 hover:text-gray-700'" 
                            class="px-4 py-3 border-b-2 font-semibold transition-colors flex items-center gap-2 whitespace-nowrap rounded-t-lg">
                            <i data-lucide="door-open" class="w-4 h-4"></i>
                            Ruangan
                        </button>
                        <button @click="selectedCategory = 'sarana'" :class="selectedCategory === 'sarana' ? 'border-green-500 text-green-600 bg-green-50' : 'border-transparent text-gray-500 hover:text-gray-700'" 
                            class="px-4 py-3 border-b-2 font-semibold transition-colors flex items-center gap-2 whitespace-nowrap rounded-t-lg">
                            <i data-lucide="package" class="w-4 h-4"></i>
                            Sarana
                        </button>
                        <button @click="selectedCategory = 'transportasi'" :class="selectedCategory === 'transportasi' ? 'border-purple-500 text-purple-600 bg-purple-50' : 'border-transparent text-gray-500 hover:text-gray-700'" 
                            class="px-4 py-3 border-b-2 font-semibold transition-colors flex items-center gap-2 whitespace-nowrap rounded-t-lg">
                            <i data-lucide="car" class="w-4 h-4"></i>
                            Transportasi
                        </button>
                    </div>
                </div>

                <!-- RUANGAN Template -->
                <div x-show="selectedCategory === 'ruangan'" x-transition>
                    <?php renderTemplateSection('ruangan', $templates['ruangan']); ?>
                </div>

                <!-- SARANA Template -->
                <div x-show="selectedCategory === 'sarana'" x-transition>
                    <?php renderTemplateSection('sarana', $templates['sarana']); ?>
                </div>

                <!-- TRANSPORTASI Template -->
                <div x-show="selectedCategory === 'transportasi'" x-transition>
                    <?php renderTemplateSection('transportasi', $templates['transportasi']); ?>
                </div>

                <!-- Informasi Penggunaan -->
                <div class="mt-6 bg-blue-50 border border-blue-200 rounded-2xl p-6">
                    <div class="flex items-start gap-4">
                        <div class="p-2 bg-blue-100 rounded-lg">
                            <i data-lucide="info" class="w-5 h-5 text-blue-600"></i>
                        </div>
                        <div class="flex-1">
                            <h3 class="font-bold text-blue-900 mb-2">Informasi Template</h3>
                            <ul class="space-y-2 text-sm text-blue-700">
                                <li class="flex items-start gap-2">
                                    <i data-lucide="check" class="w-4 h-4 mt-0.5 flex-shrink-0"></i>
                                    <span>Setiap kategori peminjaman dapat memiliki template yang berbeda</span>
                                </li>
                                <li class="flex items-start gap-2">
                                    <i data-lucide="check" class="w-4 h-4 mt-0.5 flex-shrink-0"></i>
                                    <span>Template akan muncul di halaman form peminjaman sesuai kategorinya</span>
                                </li>
                                <li class="flex items-start gap-2">
                                    <i data-lucide="check" class="w-4 h-4 mt-0.5 flex-shrink-0"></i>
                                    <span>Format yang didukung: PDF, DOC, DOCX dengan maksimal ukuran 5MB</span>
                                </li>
                                <li class="flex items-start gap-2">
                                    <i data-lucide="check" class="w-4 h-4 mt-0.5 flex-shrink-0"></i>
                                    <span>Upload template baru akan menggantikan template lama secara otomatis</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 3: Video Tutorial -->
            <div x-show="activeTab === 'website'" x-transition>
                <div class="mb-10">
                    <h1 class="text-3xl font-extrabold tracking-tight text-slate-900">
                        Video Tutorial YouTube
                    </h1>
                    <p class="text-slate-500 mt-2 font-medium">
                        Kelola video tutorial yang ditampilkan di halaman panduan.
                    </p>
                </div>

                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 md:p-8">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="p-2 bg-red-100 rounded-lg">
                            <i data-lucide="youtube" class="text-red-600 w-6 h-6"></i>
                        </div>
                        <div>
                            <h2 class="text-lg font-bold text-gray-800">Video Tutorial YouTube</h2>
                            <p class="text-xs text-gray-500">Video yang ditampilkan di halaman Panduan</p>
                        </div>
                    </div>

                    <form method="POST" class="space-y-6">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">YouTube Video ID</label>
                            <input 
                                type="text" 
                                name="youtube_video_id" 
                                value="<?= htmlspecialchars($youtube_id) ?>"
                                placeholder="Contoh: eZncsrYwiRI"
                                class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-red-500 focus:ring-2 focus:ring-red-100 outline-none transition-all font-mono"
                                required>
                            <p class="text-xs text-gray-500 mt-2">
                                <strong>Cara mendapatkan Video ID:</strong> Buka video YouTube, lihat URL: 
                                <code class="bg-gray-100 px-2 py-1 rounded">youtube.com/watch?v=<span class="text-red-600 font-bold">eZncsrYwiRI</span></code>
                            </p>
                        </div>

                        <div class="border border-gray-200 rounded-xl p-4 bg-gray-50">
                            <p class="text-sm font-bold text-gray-700 mb-3">Preview Video Saat Ini:</p>
                            <div class="aspect-video bg-black rounded-lg overflow-hidden">
                                <iframe 
                                    width="100%" 
                                    height="100%" 
                                    src="https://www.youtube.com/embed/<?= htmlspecialchars($youtube_id) ?>" 
                                    title="YouTube video player" 
                                    frameborder="0" 
                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                                    allowfullscreen>
                                </iframe>
                            </div>
                        </div>

                        <div class="flex justify-end gap-3">
                            <button type="button" onclick="window.open('https://youtube.com', '_blank')" 
                                class="px-5 py-2.5 rounded-xl border border-gray-300 text-gray-700 font-bold hover:bg-gray-50 transition-all flex items-center gap-2">
                                <i data-lucide="external-link" class="w-4 h-4"></i>
                                Buka YouTube
                            </button>
                            <button type="submit" name="update_video"
                                class="px-6 py-2.5 rounded-xl bg-gradient-to-r from-red-500 to-red-600 text-white font-bold hover:from-red-600 hover:to-red-700 shadow-lg hover:shadow-xl transition-all flex items-center gap-2">
                                <i data-lucide="save" class="w-4 h-4"></i>
                                Perbarui Video
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- TAB 4: Teks Panduan (DINAMIS) -->
            <div x-show="activeTab === 'panduan'" x-transition x-data="panduanManager()">
                <div class="mb-10">
                    <h1 class="text-3xl font-extrabold tracking-tight text-slate-900">
                        Kelola Teks Panduan
                    </h1>
                    <p class="text-slate-500 mt-2 font-medium">
                        Edit semua teks yang ditampilkan di halaman panduan pengguna.
                    </p>
                </div>

                <form method="POST" class="space-y-8">
                    <!-- Hero Section -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 md:p-8">
                        <div class="flex items-center gap-3 mb-6 pb-4 border-b border-gray-100">
                            <div class="p-2 bg-green-100 rounded-lg">
                                <i data-lucide="layout" class="text-green-600 w-6 h-6"></i>
                            </div>
                            <div>
                                <h2 class="text-lg font-bold text-gray-800">Hero Section</h2>
                                <p class="text-xs text-gray-500">Bagian pembuka halaman panduan</p>
                            </div>
                        </div>
                        
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">Judul Hero</label>
                                <input type="text" name="hero_title" 
                                    value="<?= htmlspecialchars(getSetting($pdo, 'panduan_hero_title', 'Maksimalkan Fasilitas <br/> Kampus untuk Karyamu')) ?>"
                                    class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-green-500 focus:ring-2 focus:ring-green-100 outline-none transition-all"
                                    placeholder="Judul utama...">
                                <p class="text-xs text-gray-500 mt-1">Tips: Gunakan &lt;br/&gt; untuk line break</p>
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">Subtitle Hero</label>
                                <textarea name="hero_subtitle" rows="3"
                                    class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-green-500 focus:ring-2 focus:ring-green-100 outline-none transition-all resize-none"
                                    placeholder="Deskripsi singkat..."><?= htmlspecialchars(getSetting($pdo, 'panduan_hero_subtitle', 'Panduan lengkap meminjam alat lab, ruangan, dan kendaraan operasional.')) ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Video Section -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 md:p-8">
                        <div class="flex items-center gap-3 mb-6 pb-4 border-b border-gray-100">
                            <div class="p-2 bg-red-100 rounded-lg">
                                <i data-lucide="video" class="text-red-600 w-6 h-6"></i>
                            </div>
                            <div>
                                <h2 class="text-lg font-bold text-gray-800">Video Section</h2>
                                <p class="text-xs text-gray-500">Teks di sekitar video tutorial</p>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">Judul Video</label>
                                <input type="text" name="video_title" 
                                    value="<?= htmlspecialchars(getSetting($pdo, 'panduan_video_title', 'Tutorial Singkat')) ?>"
                                    class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-green-500 focus:ring-2 focus:ring-green-100 outline-none transition-all">
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">Subtitle Video</label>
                                <input type="text" name="video_subtitle" 
                                    value="<?= htmlspecialchars(getSetting($pdo, 'panduan_video_subtitle', 'Pahami alur sistem dalam 2 menit.')) ?>"
                                    class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-green-500 focus:ring-2 focus:ring-green-100 outline-none transition-all">
                            </div>
                        </div>
                    </div>

                    <!-- 6 Langkah (DINAMIS) -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 md:p-8">
                        <div class="flex items-center justify-between mb-6 pb-4 border-b border-gray-100">
                            <div class="flex items-center gap-3">
                                <div class="p-2 bg-blue-100 rounded-lg">
                                    <i data-lucide="list-ordered" class="text-blue-600 w-6 h-6"></i>
                                </div>
                                <div>
                                    <h2 class="text-lg font-bold text-gray-800">Langkah Penggunaan</h2>
                                    <p class="text-xs text-gray-500">Tutorial step-by-step untuk pengguna</p>
                                </div>
                            </div>
                            <button type="button" @click="addStep()" 
                                class="px-4 py-2 bg-blue-500 text-white rounded-xl font-bold hover:bg-blue-600 transition-all flex items-center gap-2 text-sm">
                                <i data-lucide="plus" class="w-4 h-4"></i>
                                Tambah Langkah
                            </button>
                        </div>

                        <div class="space-y-4">
                            <template x-for="(step, index) in steps" :key="index">
                                <div class="p-5 bg-gray-50 rounded-xl border border-gray-200 relative">
                                    <button type="button" @click="removeStep(index)" 
                                        class="absolute top-3 right-3 p-2 bg-red-100 text-red-600 rounded-lg hover:bg-red-200 transition-all"
                                        x-show="steps.length > 1">
                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                    </button>
                                    
                                    <div class="flex items-center gap-2 mb-4">
                                        <div class="w-8 h-8 bg-gradient-to-r from-green-500 to-blue-500 text-white rounded-lg flex items-center justify-center font-bold text-sm" x-text="index + 1"></div>
                                        <h3 class="font-bold text-gray-800">Langkah <span x-text="index + 1"></span></h3>
                                    </div>
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div>
                                            <label class="block text-xs font-bold text-gray-600 mb-2">Judul</label>
                                            <input type="text" :name="'steps[' + index + '][title]'" x-model="step.title"
                                                class="w-full px-3 py-2 rounded-lg border border-gray-300 focus:border-green-500 focus:ring-2 focus:ring-green-100 outline-none text-sm"
                                                required>
                                        </div>
                                        <div class="md:col-span-2">
                                            <label class="block text-xs font-bold text-gray-600 mb-2">Deskripsi</label>
                                            <input type="text" :name="'steps[' + index + '][desc]'" x-model="step.desc"
                                                class="w-full px-3 py-2 rounded-lg border border-gray-300 focus:border-green-500 focus:ring-2 focus:ring-green-100 outline-none text-sm"
                                                required>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-3">
                                        <label class="block text-xs font-bold text-gray-600 mb-2">Icon (Lucide)</label>
                                        <input type="text" :name="'steps[' + index + '][icon]'" x-model="step.icon"
                                            class="w-full px-3 py-2 rounded-lg border border-gray-300 focus:border-green-500 focus:ring-2 focus:ring-green-100 outline-none text-sm font-mono"
                                            placeholder="contoh: user, search, check-circle"
                                            required>
                                        <p class="text-xs text-gray-500 mt-1">Lihat icon: <a href="https://lucide.dev/icons" target="_blank" class="text-blue-600 hover:underline">lucide.dev/icons</a></p>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- FAQ Section (DINAMIS) -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 md:p-8">
                        <div class="flex items-center justify-between mb-6 pb-4 border-b border-gray-100">
                            <div class="flex items-center gap-3">
                                <div class="p-2 bg-yellow-100 rounded-lg">
                                    <i data-lucide="help-circle" class="text-yellow-600 w-6 h-6"></i>
                                </div>
                                <div>
                                    <h2 class="text-lg font-bold text-gray-800">FAQ Section</h2>
                                    <p class="text-xs text-gray-500">Pertanyaan yang sering diajukan</p>
                                </div>
                            </div>
                            <button type="button" @click="addFaq()" 
                                class="px-4 py-2 bg-yellow-500 text-white rounded-xl font-bold hover:bg-yellow-600 transition-all flex items-center gap-2 text-sm">
                                <i data-lucide="plus" class="w-4 h-4"></i>
                                Tambah FAQ
                            </button>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">Judul FAQ</label>
                                <input type="text" name="faq_title" 
                                    value="<?= htmlspecialchars(getSetting($pdo, 'panduan_faq_title', 'Pertanyaan Umum')) ?>"
                                    class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-green-500 focus:ring-2 focus:ring-green-100 outline-none transition-all">
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">Subtitle FAQ</label>
                                <input type="text" name="faq_subtitle" 
                                    value="<?= htmlspecialchars(getSetting($pdo, 'panduan_faq_subtitle', 'Jawaban atas hal-hal yang sering ditanyakan.')) ?>"
                                    class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-green-500 focus:ring-2 focus:ring-green-100 outline-none transition-all">
                            </div>
                        </div>

                        <div class="space-y-4">
                            <template x-for="(faq, index) in faqs" :key="index">
                                <div class="p-5 bg-yellow-50 rounded-xl border border-yellow-200 relative">
                                    <button type="button" @click="removeFaq(index)" 
                                        class="absolute top-3 right-3 p-2 bg-red-100 text-red-600 rounded-lg hover:bg-red-200 transition-all"
                                        x-show="faqs.length > 1">
                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                    </button>
                                    
                                    <div class="flex items-center gap-2 mb-3">
                                        <div class="w-6 h-6 bg-yellow-500 text-white rounded-full flex items-center justify-center font-bold text-xs" x-text="index + 1"></div>
                                        <h3 class="font-bold text-gray-800">FAQ <span x-text="index + 1"></span></h3>
                                    </div>
                                    
                                    <div class="space-y-3">
                                        <div>
                                            <label class="block text-xs font-bold text-gray-600 mb-2">Pertanyaan</label>
                                            <input type="text" :name="'faqs[' + index + '][question]'" x-model="faq.question"
                                                class="w-full px-3 py-2 rounded-lg border border-gray-300 focus:border-yellow-500 focus:ring-2 focus:ring-yellow-100 outline-none text-sm"
                                                required>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-bold text-gray-600 mb-2">Jawaban</label>
                                            <textarea :name="'faqs[' + index + '][answer]'" x-model="faq.answer" rows="2"
                                                class="w-full px-3 py-2 rounded-lg border border-gray-300 focus:border-yellow-500 focus:ring-2 focus:ring-yellow-100 outline-none text-sm resize-none"
                                                required></textarea>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- CTA Section -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 md:p-8">
                        <div class="flex items-center gap-3 mb-6 pb-4 border-b border-gray-100">
                            <div class="p-2 bg-purple-100 rounded-lg">
                                <i data-lucide="megaphone" class="text-purple-600 w-6 h-6"></i>
                            </div>
                            <div>
                                <h2 class="text-lg font-bold text-gray-800">Call-to-Action</h2>
                                <p class="text-xs text-gray-500">Ajakan di akhir halaman</p>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">Judul CTA</label>
                                <input type="text" name="cta_title" 
                                    value="<?= htmlspecialchars(getSetting($pdo, 'panduan_cta_title', 'Siap Meminjam Aset?')) ?>"
                                    class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-green-500 focus:ring-2 focus:ring-green-100 outline-none transition-all">
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">Subtitle CTA</label>
                                <input type="text" name="cta_subtitle" 
                                    value="<?= htmlspecialchars(getSetting($pdo, 'panduan_cta_subtitle', 'Pastikan Anda sudah memiliki akun yang terdaftar.')) ?>"
                                    class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-green-500 focus:ring-2 focus:ring-green-100 outline-none transition-all">
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="flex justify-end gap-3">
                        <button type="button" onclick="window.open('../panduan.php', '_blank')" 
                            class="px-6 py-3 rounded-xl border border-gray-300 text-gray-700 font-bold hover:bg-gray-50 transition-all flex items-center gap-2">
                            <i data-lucide="eye" class="w-4 h-4"></i>
                            Preview Halaman
                        </button>
                        <button type="submit" name="update_panduan_text"
                            class="px-8 py-3 rounded-xl bg-gradient-to-r from-green-500 to-blue-500 text-white font-bold hover:from-green-600 hover:to-blue-600 shadow-lg hover:shadow-xl transition-all flex items-center gap-2">
                            <i data-lucide="save" class="w-4 h-4"></i>
                            Simpan Semua Perubahan
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </main>

    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        function settingsApp(initialTab) {
            return {
                activeTab: initialTab,
                isEditing: false,
                previewPhoto: '<?= $profile_photo ?>',
                formData: {
                    nama: '<?= htmlspecialchars($user['nama']) ?>',
                    newPassword: '',
                    confirmPassword: ''
                },
                
                toggleEdit() {
                    this.isEditing = !this.isEditing;
                    if (!this.isEditing) {
                        this.formData.nama = '<?= htmlspecialchars($user['nama']) ?>';
                        this.formData.newPassword = '';
                        this.formData.confirmPassword = '';
                        this.previewPhoto = '<?= $profile_photo ?>';
                    }
                },
                
                handlePhotoUpload(event) {
                    const file = event.target.files[0];
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = (e) => {
                            this.previewPhoto = e.target.result;
                        };
                        reader.readAsDataURL(file);
                    }
                },
                
                confirmDeletePhoto() {
                    if (confirm('Yakin ingin menghapus foto profil?')) {
                        window.location.href = 'pengaturan.php?delete_photo=1';
                    }
                },
                
                validateForm() {
                    if (this.formData.newPassword) {
                        if (this.formData.newPassword !== this.formData.confirmPassword) {
                            alert('Password dan konfirmasi password tidak cocok!');
                            return false;
                        }
                        if (this.formData.newPassword.length < 8) {
                            alert('Password minimal 8 karakter!');
                            return false;
                        }
                    }
                    return true;
                },
                
                get passwordStrength() {
                    const pwd = this.formData.newPassword;
                    if (pwd.length === 0) return '';
                    if (pwd.length < 8) return 'Lemah';
                    
                    const hasNumber = /\d/.test(pwd);
                    const hasLetter = /[a-zA-Z]/.test(pwd);
                    const hasSpecial = /[!@#$%^&*]/.test(pwd);
                    
                    if (hasNumber && hasLetter && hasSpecial && pwd.length >= 12) {
                        return 'Kuat';
                    } else if ((hasNumber && hasLetter) || pwd.length >= 10) {
                        return 'Sedang';
                    } else {
                        return 'Lemah';
                    }
                }
            }
        }
        
        function panduanManager() {
            return {
                steps: <?= json_encode($steps) ?>,
                faqs: <?= json_encode($faqs) ?>,
                
                addStep() {
                    const newNum = this.steps.length + 1;
                    this.steps.push({
                        num: newNum,
                        title: `${newNum}. Langkah ${newNum}`,
                        desc: `Deskripsi langkah ${newNum}`,
                        icon: 'circle'
                    });
                    this.$nextTick(() => lucide.createIcons());
                },
                
                removeStep(index) {
                    if (this.steps.length > 1) {
                        if (confirm('Yakin ingin menghapus langkah ini?')) {
                            this.steps.splice(index, 1);
                            this.$nextTick(() => lucide.createIcons());
                        }
                    } else {
                        alert('Minimal harus ada 1 langkah!');
                    }
                },
                
                addFaq() {
                    const newNum = this.faqs.length + 1;
                    this.faqs.push({
                        num: newNum,
                        question: `Pertanyaan ${newNum}?`,
                        answer: `Jawaban ${newNum}`
                    });
                    this.$nextTick(() => lucide.createIcons());
                },
                
                removeFaq(index) {
                    if (this.faqs.length > 1) {
                        if (confirm('Yakin ingin menghapus FAQ ini?')) {
                            this.faqs.splice(index, 1);
                            this.$nextTick(() => lucide.createIcons());
                        }
                    } else {
                        alert('Minimal harus ada 1 FAQ!');
                    }
                }
            }
        }
        
        lucide.createIcons();
        
        document.addEventListener('alpine:initialized', () => {
            lucide.createIcons();
        });
    </script>

</body>
</html>