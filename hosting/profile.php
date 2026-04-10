<?php
/**
 * SobatHosting - Profile Management
 * Author: Gemini AI
 */

require_once __DIR__ . '/../config/database.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . '/../library/session.php';

$user_id = $_SESSION['user_id'];
$message_type = "";
$message_text = "";

// 2. Logika Update Profil & Password
if (isset($_POST['update_profile'])) {
    // Sanitasi Input Dasar
    $fields = ['nama', 'whatsapp', 'negara', 'provinsi', 'kota', 'kabupaten', 'kode_pos'];
    $data = [];
    foreach ($fields as $field) {
        $data[$field] = mysqli_real_escape_string($conn, $_POST[$field]);
    }

    $password_baru = $_POST['password_baru'];

    // Update Query Utama
    $sql_update = "UPDATE users SET 
                    nama='{$data['nama']}', 
                    no_whatsapp='{$data['whatsapp']}', 
                    negara='{$data['negara']}',
                    provinsi='{$data['provinsi']}', 
                    kota='{$data['kota']}', 
                    kabupaten='{$data['kabupaten']}', 
                    kode_pos='{$data['kode_pos']}' 
                  WHERE id='$user_id'";
    
    if (mysqli_query($conn, $sql_update)) {
        $_SESSION['user_nama'] = $data['nama']; 
        $message_type = "success";
        $message_text = "Data profil berhasil diperbarui!";
        
        // Logika Update Password (Hanya jika diisi)
        if (!empty($password_baru)) {
            if (strlen($password_baru) < 6) {
                $message_type = "warning";
                $message_text = "Profil diperbarui, namun password terlalu pendek (min. 6 karakter).";
            } else {
                $hash_baru = password_hash($password_baru, PASSWORD_DEFAULT);
                mysqli_query($conn, "UPDATE users SET password='$hash_baru' WHERE id='$user_id'");
                $message_text = "Profil dan password Anda telah diperbarui.";
            }
        }
    } else {
        $message_type = "error";
        $message_text = "Kesalahan sistem: Gagal memperbarui database.";
    }
}

// 3. Ambil Data Terbaru (Setelah update atau saat load halaman)
$query = mysqli_query($conn, "SELECT * FROM users WHERE id = '$user_id'");
$u = mysqli_fetch_assoc($query);

$page_title = "Profil Saya"; 
include __DIR__ . '/../library/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h4 class="fw-bold text-dark m-0" style="font-size: 1.2rem;">Pengaturan Akun</h4>
        <p class="text-secondary small mb-0 mt-1">Kelola informasi pribadi dan keamanan akun Anda.</p>
    </div>
</div>

<div class="row g-4">
    <!-- Kolom Kiri: Ringkasan Profil -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm text-center" style="border-radius: 4px; background: white; border: 1px solid var(--border-color) !important;">
            <div class="card-body p-4">
                <div class="avatar-circle mx-auto mb-3 shadow-sm d-flex align-items-center justify-content-center" style="width: 70px; height: 70px; border-radius: 50%; background: #ebf5ff; color: #007bff; font-size: 1.8rem; font-weight: 700;">
                    <?php echo strtoupper(substr($u['nama'], 0, 1)); ?>
                </div>
                <h5 class="fw-bold mb-1 text-dark fs-6"><?php echo htmlspecialchars($u['nama'] ?? '', ENT_QUOTES); ?></h5>
                <p class="text-muted small mb-3"><?php echo $u['email']; ?></p>
                
                <div class="d-inline-flex align-items-center px-3 py-1 rounded" style="background: #e6f7eb; color: #20c997; font-size: 0.75rem; font-weight: 600;">
                    <i class="bi bi-shield-check me-1"></i> Role: <?php echo strtoupper($u['role']); ?>
                </div>
                
                <hr class="my-4" style="border-color: #eaedf1;">
                
                <div class="text-start">
                    <label class="small fw-bold text-muted d-block mb-1" style="font-size: 0.7rem;">DOMISILI UTAMA:</label>
                    <p class="fw-medium small text-dark m-0 d-flex align-items-center">
                        <i class="bi bi-geo-alt-fill me-2" style="color: #007bff;"></i> 
                        <?php echo !empty($u['negara']) ? htmlspecialchars($u['negara']) : 'Belum diisi'; ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Kolom Kanan: Form Edit -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm" style="border-radius: 4px; background: white; border: 1px solid var(--border-color) !important;">
            <div class="card-body p-4 p-md-5">
                <form action="" method="POST">
                    
                    <div class="d-flex align-items-center mb-4 pb-2 border-bottom">
                        <i class="bi bi-person-lines-fill fs-5 me-2" style="color: #48cae4;"></i>
                        <h6 class="fw-bold m-0 text-dark">Informasi Pribadi</h6>
                    </div>

                    <div class="row g-3 mb-5">
                        <div class="col-md-6">
                            <label class="form-label text-muted fw-bold" style="font-size: 0.75rem;">NAMA LENGKAP</label>
                            <input type="text" name="nama" class="form-control form-control-sm" value="<?php echo htmlspecialchars($u['nama'] ?? '', ENT_QUOTES); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted fw-bold" style="font-size: 0.75rem;">WHATSAPP</label>
                            <input type="text" name="whatsapp" class="form-control form-control-sm" value="<?php echo htmlspecialchars($u['no_whatsapp'] ?? '', ENT_QUOTES); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted fw-bold" style="font-size: 0.75rem;">NEGARA</label>
                            <input type="text" name="negara" class="form-control form-control-sm" value="<?php echo htmlspecialchars($u['negara'] ?? '', ENT_QUOTES); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted fw-bold" style="font-size: 0.75rem;">PROVINSI</label>
                            <input type="text" name="provinsi" class="form-control form-control-sm" value="<?php echo htmlspecialchars($u['provinsi'] ?? '', ENT_QUOTES); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted fw-bold" style="font-size: 0.75rem;">KOTA</label>
                            <input type="text" name="kota" class="form-control form-control-sm" value="<?php echo htmlspecialchars($u['kota'] ?? '', ENT_QUOTES); ?>">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label text-muted fw-bold" style="font-size: 0.75rem;">KECAMATAN / KAB.</label>
                            <input type="text" name="kabupaten" class="form-control form-control-sm" value="<?php echo htmlspecialchars($u['kabupaten'] ?? '', ENT_QUOTES); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted fw-bold" style="font-size: 0.75rem;">KODE POS</label>
                            <input type="text" name="kode_pos" class="form-control form-control-sm" value="<?php echo htmlspecialchars($u['kode_pos'] ?? '', ENT_QUOTES); ?>">
                        </div>
                    </div>

                    <div class="d-flex align-items-center mb-4 pb-2 border-bottom">
                        <i class="bi bi-shield-lock-fill fs-5 me-2" style="color: #48cae4;"></i>
                        <h6 class="fw-bold m-0 text-dark">Keamanan Akun</h6>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label text-muted fw-bold" style="font-size: 0.75rem;">GANTI PASSWORD</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-key"></i></span>
                                <input type="password" name="password_baru" id="passInput" class="form-control border-start-0" placeholder="Biarkan kosong jika tidak diubah">
                                <button class="btn btn-outline-secondary border-start-0 bg-white" style="border-color: var(--border-color);" type="button" onclick="togglePass()">
                                    <i class="bi bi-eye text-muted" id="toggleIcon"></i>
                                </button>
                            </div>
                            <div class="form-text text-muted" style="font-size: 0.7rem;">Gunakan minimal 6 karakter kombinasi huruf dan angka.</div>
                        </div>
                    </div>

                    <div class="mt-5 pt-4 text-end border-top">
                        <button type="submit" name="update_profile" class="btn btn-sm btn-primary px-4 py-2 fw-medium" style="border-radius: 4px; font-size: 0.8rem; background: #007bff;">
                            <i class="bi bi-save me-1"></i> Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
    .form-control, .input-group-text { border-radius: 4px; border: 1px solid var(--border-color); box-shadow: none; padding: 0.5rem 0.75rem;}
    .form-control:focus { border-color: #007bff; box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25); }
    .input-group-text { border-radius: 4px 0 0 4px; }
    .btn-outline-secondary.border-start-0 { border-radius: 0 4px 4px 0; }
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function togglePass() {
        const p = document.getElementById('passInput');
        const i = document.getElementById('toggleIcon');
        p.type = p.type === 'password' ? 'text' : 'password';
        i.classList.toggle('bi-eye'); 
        i.classList.toggle('bi-eye-slash');
    }

    <?php if($message_type): ?>
    Swal.fire({
        icon: '<?php echo $message_type; ?>',
        title: '<?php echo ($message_type == "success") ? "Berhasil!" : "Pemberitahuan"; ?>',
        text: '<?php echo $message_text; ?>',
        confirmButtonColor: '#007bff',
        borderRadius: '4px'
    });
    <?php endif; ?>
</script>

<?php include __DIR__ . '/../library/footer.php'; ?>
