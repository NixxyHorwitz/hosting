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

// Cek & Tambah Kolom otomatis untuk 2FA
$check_col = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'gauth_secret'");
if(mysqli_num_rows($check_col) == 0) {
    mysqli_query($conn, "ALTER TABLE users ADD COLUMN gauth_secret VARCHAR(50) DEFAULT NULL, ADD COLUMN is_2fa_enabled TINYINT(1) DEFAULT 0");
}

// Handler AJAX 2FA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_2fa'])) {
    header('Content-Type: application/json');
    require_once __DIR__ . '/../core/GoogleAuthenticator.php';
    $ga = new PHPGangsta_GoogleAuthenticator();
    
    if ($_POST['action_2fa'] === 'generate') {
        $secret = $ga->createSecret();
        $q_set = mysqli_query($conn, "SELECT site_name FROM settings WHERE id=1");
        $ds = mysqli_fetch_assoc($q_set);
        $site = $ds['site_name'] ?? 'SobatHosting';
        
        $qrCodeUrl = $ga->getQRCodeGoogleUrl($u['email'], $secret, $site);
        echo json_encode(['success'=>true, 'secret'=>$secret, 'qr'=>$qrCodeUrl]);
        exit;
    } 
    elseif ($_POST['action_2fa'] === 'verify_enable') {
        $secret = $_POST['secret'];
        $code = $_POST['code'];
        if ($ga->verifyCode($secret, $code, 2)) {
            $safesecret = mysqli_real_escape_string($conn, $secret);
            mysqli_query($conn, "UPDATE users SET gauth_secret='$safesecret', is_2fa_enabled=1 WHERE id='$user_id'");
            echo json_encode(['success'=>true, 'msg'=>'2FA Berhasil diaktifkan!']);
        } else {
            echo json_encode(['success'=>false, 'msg'=>'Kode OTP tidak valid. Coba lagi.']);
        }
        exit;
    }
    elseif ($_POST['action_2fa'] === 'disable') {
        $kode = $_POST['code'];
        $c_q = mysqli_query($conn, "SELECT gauth_secret FROM users WHERE id='$user_id'");
        $c_u = mysqli_fetch_assoc($c_q);
        if ($ga->verifyCode($c_u['gauth_secret'], $kode, 2)) {
            mysqli_query($conn, "UPDATE users SET gauth_secret=NULL, is_2fa_enabled=0 WHERE id='$user_id'");
            echo json_encode(['success'=>true, 'msg'=>'2FA telah dinonaktifkan.']);
        } else {
            echo json_encode(['success'=>false, 'msg'=>'Kode OTP salah.']);
        }
        exit;
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
                
                <div class="mt-4 pt-3 border-top text-start">
                    <ul class="nav nav-pills flex-column gap-2" id="profile-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active fw-medium fs-6 d-flex align-items-center" id="tab-profile" data-bs-toggle="pill" href="#pane-profile" role="tab" style="border-radius:6px; color:var(--text);"><i class="bi bi-person me-2 fs-5"></i> Profil & Password</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link fw-medium fs-6 d-flex align-items-center" id="tab-2fa" data-bs-toggle="pill" href="#pane-2fa" role="tab" style="border-radius:6px; color:var(--text);"><i class="bi bi-shield-lock me-2 fs-5"></i> Keamanan 2FA</a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Kolom Kanan: Form Edit -->
    <div class="col-lg-8">
        <div class="tab-content" id="pills-tabContent">
            
            <!-- PANE PROFILE -->
            <div class="tab-pane fade show active" id="pane-profile" role="tabpanel">
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
    
    <!-- PANE 2FA -->
    <div class="tab-pane fade" id="pane-2fa" role="tabpanel">
        <div class="card border-0 shadow-sm mb-4" style="border-radius: 4px; background: white; border: 1px solid var(--border-color) !important;">
            <div class="card-body p-4 p-md-5">
                <div class="d-flex align-items-center mb-4 pb-2 border-bottom">
                    <i class="bi bi-shield-check fs-5 me-2" style="color: #48cae4;"></i>
                    <h6 class="fw-bold m-0 text-dark">Autentikasi Dua Faktor (2FA)</h6>
                </div>
                
                <?php if ($u['is_2fa_enabled']): ?>
                    <div class="alert alert-success d-flex align-items-center">
                        <i class="bi bi-check-circle-fill fs-4 me-3"></i>
                        <div>
                            <strong>2FA Sudah Aktif!</strong><br>
                            <span class="small">Akun Anda saat ini terlindungi dengan Autentikasi Dua Faktor.</span>
                        </div>
                    </div>
                    <p class="text-secondary small mb-4">Jika Anda ingin menonaktifkan fitur keamanan ini, silakan masukkan kode OTP dari aplikasi Authenticator Anda (contoh: Google Authenticator atau Authy).</p>
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-muted small">KODE OTP SAAT INI</label>
                            <div class="input-group">
                                <input type="text" id="offOTP" class="form-control" placeholder="123456" maxlength="6">
                                <button class="btn btn-danger px-3 py-2 fw-bold small" onclick="disable2FA()">MATIKAN 2FA</button>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning" style="background:#fff3cd; border-color:#ffeeba;">
                        Kami sangat menyarankan Anda agar mengaktifkan Otentikasi Dua Faktor (Two-Factor Authentication) untuk meningkatkan keamanan akun Anda.
                    </div>
                    <p class="text-secondary small mb-4">Otentikasi Dua Faktor dengan TOTP (Time-Based One Time Password) meminta Anda menggunakan perangkat mobile untuk membuat kode akses unik melalui aplikasi seperti <b>Google Authenticator</b> atau <b>Authy</b>.</p>
                    
                    <button class="btn btn-primary fw-medium px-4 mb-4" id="btnMulai2FA" onclick="start2FA()">Mulai Konfigurasi 2FA</button>

                    <!-- WIZARD STEP KEDUA -->
                    <div id="wizard2FA" style="display:none;" class="p-3 border rounded bg-light">
                        <h6 class="fw-bold mb-3">Langkah Konfigurasi</h6>
                        <p class="small text-secondary mb-3">1. Gunakan aplikasi Google Authenticator untuk scan QR Code di bawah, atau masukkan Kunci Rahasia secara manual.</p>
                        
                        <div class="text-center mb-3">
                            <img id="qr2fa" src="" alt="QR Code" class="img-fluid border p-2 bg-white" style="max-width: 150px;">
                            <div class="mt-2 fw-bold text-danger fs-5" id="secretText" style="letter-spacing: 2px;"></div>
                        </div>
                        
                        <p class="small text-secondary mb-3">2. Masukkan kode 6 digit yang muncul di aplikasi Authenticator Anda untuk verifikasi.</p>
                        <div class="d-flex gap-2 justify-content-center">
                            <input type="text" id="code2fa" class="form-control form-control-sm text-center fw-bold fs-5" style="max-width:150px; letter-spacing:3px;" placeholder="123456" maxlength="6">
                            <button class="btn btn-success btn-sm px-4 fw-bold" onclick="verify2FA()">VERIFIKASI & AKTIFKAN</button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    </div> <!-- END TAB CONTENT -->
    </div> <!-- END COL RIGHT -->
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

    let globalSecret = '';
    function start2FA() {
        document.getElementById('btnMulai2FA').innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Loading...';
        const fd = new FormData(); fd.append('action_2fa', 'generate');
        fetch(window.location.href, {method:'POST', body:fd}).then(r=>r.json()).then(res=>{
            if(res.success) {
                globalSecret = res.secret;
                document.getElementById('btnMulai2FA').style.display = 'none';
                document.getElementById('wizard2FA').style.display = 'block';
                document.getElementById('qr2fa').src = res.qr;
                document.getElementById('secretText').innerText = res.secret;
            }
        });
    }

    function verify2FA() {
        const c = document.getElementById('code2fa').value;
        if(c.length < 6) return alert("Masukkan 6 digit kode!");
        const fd = new FormData(); fd.append('action_2fa', 'verify_enable'); fd.append('secret', globalSecret); fd.append('code', c);
        fetch(window.location.href, {method:'POST', body:fd}).then(r=>r.json()).then(res=>{
            if(res.success) {
                Swal.fire({icon:'success', title:'Aktif!', text:res.msg, allowOutsideClick:false}).then(()=>{ window.location.reload(); });
            } else {
                Swal.fire({icon:'error', title:'Gagal', text:res.msg});
            }
        });
    }

    function disable2FA() {
        const c = document.getElementById('offOTP').value;
        if(c.length < 6) return alert("Masukkan 6 digit kode!");
        const fd = new FormData(); fd.append('action_2fa', 'disable'); fd.append('code', c);
        fetch(window.location.href, {method:'POST', body:fd}).then(r=>r.json()).then(res=>{
            if(res.success) {
                Swal.fire({icon:'success', title:'Dinonaktifkan!', text:res.msg, allowOutsideClick:false}).then(()=>{ window.location.reload(); });
            } else {
                Swal.fire({icon:'error', title:'Gagal', text:res.msg});
            }
        });
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
