<?php
/**
 * SobatHosting - Profile Management
 */

require_once __DIR__ . '/../config/database.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . '/../library/session.php';

$user_id = $_SESSION['user_id'];
$message_type = "";
$message_text = "";

// Update profile
if (isset($_POST['update_profile'])) {
    $fields = ['nama', 'whatsapp', 'negara', 'provinsi', 'kota', 'kabupaten', 'kode_pos'];
    $data = [];
    foreach ($fields as $field) {
        $data[$field] = mysqli_real_escape_string($conn, $_POST[$field] ?? '');
    }
    $password_baru = $_POST['password_baru'] ?? '';

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

// Auto-create 2FA columns
$check_col = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'gauth_secret'");
if(mysqli_num_rows($check_col) == 0) {
    mysqli_query($conn, "ALTER TABLE users ADD COLUMN gauth_secret VARCHAR(50) DEFAULT NULL, ADD COLUMN is_2fa_enabled TINYINT(1) DEFAULT 0");
}

// 2FA AJAX Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_2fa'])) {
    ob_end_clean();
    header('Content-Type: application/json');
    require_once __DIR__ . '/../core/GoogleAuthenticator.php';
    $ga = new PHPGangsta_GoogleAuthenticator();
    
    // Need fresh user for 2FA
    $u_q = mysqli_query($conn, "SELECT * FROM users WHERE id='$user_id'");
    $u_2fa_user = mysqli_fetch_assoc($u_q);

    if ($_POST['action_2fa'] === 'generate') {
        $secret = $ga->createSecret();
        $q_set = mysqli_query($conn, "SELECT site_name FROM settings WHERE id=1");
        $ds = mysqli_fetch_assoc($q_set);
        $site = $ds['site_name'] ?? 'SobatHosting';
        $qrCodeUrl = $ga->getQRCodeGoogleUrl($u_2fa_user['email'], $secret, $site);
        echo json_encode(['success' => true, 'secret' => $secret, 'qr' => $qrCodeUrl]);
        exit;
    } 
    elseif ($_POST['action_2fa'] === 'verify_enable') {
        $secret = $_POST['secret'];
        $code = $_POST['code'];
        if ($ga->verifyCode($secret, $code, 2)) {
            $safesecret = mysqli_real_escape_string($conn, $secret);
            mysqli_query($conn, "UPDATE users SET gauth_secret='$safesecret', is_2fa_enabled=1 WHERE id='$user_id'");
            echo json_encode(['success' => true, 'msg' => '2FA berhasil diaktifkan!']);
        } else {
            echo json_encode(['success' => false, 'msg' => 'Kode OTP tidak valid. Coba lagi.']);
        }
        exit;
    }
    elseif ($_POST['action_2fa'] === 'disable') {
        $kode = $_POST['code'];
        if ($ga->verifyCode($u_2fa_user['gauth_secret'], $kode, 2)) {
            mysqli_query($conn, "UPDATE users SET gauth_secret=NULL, is_2fa_enabled=0 WHERE id='$user_id'");
            echo json_encode(['success' => true, 'msg' => '2FA telah dinonaktifkan.']);
        } else {
            echo json_encode(['success' => false, 'msg' => 'Kode OTP salah.']);
        }
        exit;
    }
    echo json_encode(['success' => false, 'msg' => 'Aksi tidak dikenal.']);
    exit;
}

// Load fresh user data
$query = mysqli_query($conn, "SELECT * FROM users WHERE id = '$user_id'");
$u = mysqli_fetch_assoc($query);

$page_title = "Profil Saya"; 
include __DIR__ . '/../library/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold text-dark m-0" style="font-size: 1.1rem;">Pengaturan Akun</h4>
        <p class="text-secondary mb-0 mt-1" style="font-size:13px;">Kelola informasi pribadi dan keamanan akun.</p>
    </div>
</div>

<div class="row g-3">

    <!-- Kolom Kiri: Info + Nav -->
    <div class="col-lg-3 col-md-4">
        <div class="card border-0 shadow-sm text-center" style="border-radius:8px;background:white;border:1px solid var(--border-color)!important;">
            <div class="card-body p-3">
                <!-- Avatar -->
                <div class="mx-auto mb-2 d-flex align-items-center justify-content-center fw-bold" style="width:54px;height:54px;border-radius:50%;background:#ebf5ff;color:#007bff;font-size:1.3rem;">
                    <?php echo strtoupper(substr($u['nama'], 0, 1)); ?>
                </div>
                <h6 class="fw-bold mb-0 text-dark" style="font-size:14px;"><?php echo htmlspecialchars($u['nama'] ?? ''); ?></h6>
                <p class="text-muted mb-2" style="font-size:12px;"><?php echo htmlspecialchars($u['email']); ?></p>
                
                <div class="d-flex justify-content-center flex-wrap gap-1">
                    <span class="badge rounded-pill" style="background:#e6f7eb;color:#20c997;font-size:11px;">
                        <i class="bi bi-shield-check me-1"></i><?php echo strtoupper($u['role']); ?>
                    </span>
                    <?php if(!empty($u['is_2fa_enabled'])): ?>
                    <span class="badge rounded-pill" style="background:#fff3e0;color:#f57c00;font-size:11px;">
                        <i class="bi bi-lock-fill me-1"></i>2FA ON
                    </span>
                    <?php endif; ?>
                </div>

                <hr class="my-3" style="border-color:#eaedf1;">

                <!-- Navigation Tabs -->
                <ul class="nav flex-column gap-1 text-start" id="profileTabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active d-flex align-items-center gap-2 py-2 px-3 rounded" id="tab-info" data-bs-toggle="pill" href="#pane-info" role="tab" style="font-size:13px;font-weight:500;">
                            <i class="bi bi-person-circle" style="font-size:15px;color:#007bff;"></i> Profil &amp; Password
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link d-flex align-items-center gap-2 py-2 px-3 rounded" id="tab-2fa" data-bs-toggle="pill" href="#pane-2fa" role="tab" style="font-size:13px;font-weight:500;">
                            <i class="bi bi-shield-lock" style="font-size:15px;color:#f57c00;"></i> Keamanan 2FA
                        </a>
                    </li>
                </ul>

                <hr class="my-3" style="border-color:#eaedf1;">

                <!-- Location Info -->
                <div class="text-start px-1">
                    <p class="text-muted mb-1" style="font-size:10px;font-weight:700;letter-spacing:.5px;">DOMISILI</p>
                    <p class="small text-dark m-0 d-flex align-items-center gap-1">
                        <i class="bi bi-geo-alt-fill" style="color:#007bff;font-size:12px;"></i>
                        <span><?php echo !empty($u['negara']) ? htmlspecialchars($u['negara']) : '<span class="text-muted fst-italic">Belum diisi</span>'; ?></span>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Kolom Kanan: Tab Content -->
    <div class="col-lg-9 col-md-8">
        <div class="tab-content" id="profileTabContent">

            <!-- PANE: PROFIL & PASSWORD -->
            <div class="tab-pane fade show active" id="pane-info" role="tabpanel">
                <div class="card border-0 shadow-sm" style="border-radius:8px;background:white;border:1px solid var(--border-color)!important;">
                    <div class="card-body p-4">
                        <form action="" method="POST">

                            <div class="d-flex align-items-center mb-3 pb-2 border-bottom">
                                <i class="bi bi-person-lines-fill me-2" style="color:#48cae4;font-size:16px;"></i>
                                <span class="fw-bold text-dark" style="font-size:14px;">Informasi Pribadi</span>
                            </div>

                            <div class="row g-2 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label text-muted fw-bold mb-1" style="font-size:11px;">NAMA LENGKAP</label>
                                    <input type="text" name="nama" class="form-control form-control-sm" value="<?php echo htmlspecialchars($u['nama'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-muted fw-bold mb-1" style="font-size:11px;">NO. WHATSAPP</label>
                                    <input type="text" name="whatsapp" class="form-control form-control-sm" value="<?php echo htmlspecialchars($u['no_whatsapp'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-muted fw-bold mb-1" style="font-size:11px;">NEGARA</label>
                                    <input type="text" name="negara" class="form-control form-control-sm" value="<?php echo htmlspecialchars($u['negara'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-muted fw-bold mb-1" style="font-size:11px;">PROVINSI</label>
                                    <input type="text" name="provinsi" class="form-control form-control-sm" value="<?php echo htmlspecialchars($u['provinsi'] ?? ''); ?>">
                                </div>
                                <div class="col-5">
                                    <label class="form-label text-muted fw-bold mb-1" style="font-size:11px;">KOTA</label>
                                    <input type="text" name="kota" class="form-control form-control-sm" value="<?php echo htmlspecialchars($u['kota'] ?? ''); ?>">
                                </div>
                                <div class="col-4">
                                    <label class="form-label text-muted fw-bold mb-1" style="font-size:11px;">KECAMATAN / KAB.</label>
                                    <input type="text" name="kabupaten" class="form-control form-control-sm" value="<?php echo htmlspecialchars($u['kabupaten'] ?? ''); ?>">
                                </div>
                                <div class="col-3">
                                    <label class="form-label text-muted fw-bold mb-1" style="font-size:11px;">KODE POS</label>
                                    <input type="text" name="kode_pos" class="form-control form-control-sm" value="<?php echo htmlspecialchars($u['kode_pos'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="d-flex align-items-center mb-3 pb-2 border-bottom">
                                <i class="bi bi-shield-lock-fill me-2" style="color:#48cae4;font-size:16px;"></i>
                                <span class="fw-bold text-dark" style="font-size:14px;">Ganti Password</span>
                            </div>

                            <div class="row g-2 mb-3">
                                <div class="col-md-7">
                                    <label class="form-label text-muted fw-bold mb-1" style="font-size:11px;">PASSWORD BARU</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-key text-muted"></i></span>
                                        <input type="password" name="password_baru" id="passInput" class="form-control border-start-0" placeholder="Kosongkan jika tidak ingin ubah">
                                        <button class="btn btn-outline-secondary bg-white border-start-0" type="button" onclick="togglePass()">
                                            <i class="bi bi-eye text-muted" id="toggleIcon"></i>
                                        </button>
                                    </div>
                                    <div class="form-text" style="font-size:11px;">Min. 6 karakter kombinasi huruf dan angka.</div>
                                </div>
                            </div>

                            <div class="text-end pt-3 border-top">
                                <button type="submit" name="update_profile" class="btn btn-primary btn-sm px-4 fw-medium">
                                    <i class="bi bi-save me-1"></i> Simpan Perubahan
                                </button>
                            </div>

                        </form>
                    </div>
                </div>
            </div>

            <!-- PANE: 2FA -->
            <div class="tab-pane fade" id="pane-2fa" role="tabpanel">
                <div class="card border-0 shadow-sm" style="border-radius:8px;background:white;border:1px solid var(--border-color)!important;">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center mb-3 pb-2 border-bottom">
                            <i class="bi bi-shield-check me-2" style="color:#48cae4;font-size:16px;"></i>
                            <span class="fw-bold text-dark" style="font-size:14px;">Autentikasi Dua Faktor (2FA)</span>
                        </div>

                        <?php if (!empty($u['is_2fa_enabled'])): ?>
                            <div class="alert alert-success d-flex align-items-center py-2 mb-3" style="font-size:13px;">
                                <i class="bi bi-check-circle-fill me-2 fs-5 flex-shrink-0"></i>
                                <div><strong>2FA Aktif!</strong> Akun Anda terlindungi Autentikasi Dua Faktor.</div>
                            </div>
                            <p class="text-secondary small mb-3">Untuk menonaktifkan, masukkan kode OTP 6 digit dari aplikasi Authenticator Anda.</p>
                            <div class="col-md-7">
                                <label class="form-label fw-bold text-muted mb-1" style="font-size:11px;">KODE OTP UNTUK NONAKTIFKAN</label>
                                <div class="input-group input-group-sm">
                                    <input type="text" id="offOTP" class="form-control text-center fw-bold" placeholder="123456" maxlength="6" style="letter-spacing:4px;font-size:15px;font-family:monospace;">
                                    <button class="btn btn-danger fw-bold" onclick="disable2FA()" style="font-size:12px;"><i class="bi bi-shield-x me-1"></i>Matikan 2FA</button>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning py-2 mb-3 d-flex align-items-center" style="font-size:13px;background:#fff8e1;border-color:#ffe082;border-left:4px solid #ffc107;">
                                <i class="bi bi-exclamation-triangle-fill me-2 flex-shrink-0"></i>
                                <div>Kami sangat menyarankan mengaktifkan 2FA untuk meningkatkan keamanan akun Anda.</div>
                            </div>
                            <p class="text-secondary small mb-3">Gunakan aplikasi seperti <b>Google Authenticator</b> atau <b>Authy</b> untuk menghasilkan kode TOTP unik setiap 30 detik.</p>

                            <button class="btn btn-primary btn-sm fw-medium px-4 mb-3" id="btnMulai2FA" onclick="start2FA()">
                                <i class="bi bi-shield-plus me-1"></i> Mulai Aktivasi 2FA
                            </button>

                            <div id="wizard2FA" style="display:none;" class="p-3 border rounded" style="background:#f8f9fa;">
                                <p class="fw-semibold text-dark mb-2" style="font-size:13px;">
                                    <i class="bi bi-1-circle-fill text-primary me-1"></i> Scan QR Code atau masukkan kunci rahasia di aplikasi Authenticator:
                                </p>
                                <div class="text-center mb-3">
                                    <img id="qr2fa" src="" alt="QR Code" class="border p-2 bg-white rounded" style="max-width:130px;">
                                    <div class="mt-2 fw-bold text-danger" id="secretText" style="letter-spacing:3px;font-size:13px;font-family:monospace;"></div>
                                </div>
                                <p class="fw-semibold text-dark mb-2" style="font-size:13px;">
                                    <i class="bi bi-2-circle-fill text-primary me-1"></i> Masukkan kode 6 digit dari aplikasi untuk verifikasi:
                                </p>
                                <div class="d-flex gap-2 align-items-center flex-wrap">
                                    <input type="text" id="code2fa" class="form-control form-control-sm text-center fw-bold" style="max-width:130px;letter-spacing:4px;font-size:15px;font-family:monospace;" placeholder="123456" maxlength="6">
                                    <button class="btn btn-success btn-sm px-3 fw-bold" onclick="verify2FA()">
                                        <i class="bi bi-check-circle me-1"></i> Verifikasi &amp; Aktifkan
                                    </button>
                                </div>
                            </div>
                        <?php endif; ?>

                    </div>
                </div>
            </div>

        </div><!-- end tab-content -->
    </div><!-- end col-right -->
</div><!-- end row -->

<style>
    .form-control, .input-group-text { border-radius: 6px; border: 1px solid #dee2e6; box-shadow: none; }
    .form-control:focus { border-color: #007bff; box-shadow: 0 0 0 3px rgba(0,123,255,.12); }
    .input-group .input-group-text { border-radius: 6px 0 0 6px; }
    .input-group .btn { border-radius: 0 6px 6px 0; }
    #profileTabs .nav-link { color: #555 !important; transition: all .15s; border-radius: 6px; }
    #profileTabs .nav-link:hover { background: #f0f5ff !important; color: #007bff !important; }
    #profileTabs .nav-link.active { background: #ebf5ff !important; color: #007bff !important; }
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
        const btn = document.getElementById('btnMulai2FA');
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Memuat...';
        btn.disabled = true;
        const fd = new FormData();
        fd.append('action_2fa', 'generate');
        fetch(window.location.href, {method:'POST', body:fd})
            .then(r => r.json())
            .then(res => {
                if(res.success) {
                    globalSecret = res.secret;
                    btn.style.display = 'none';
                    document.getElementById('wizard2FA').style.display = 'block';
                    document.getElementById('qr2fa').src = res.qr;
                    document.getElementById('secretText').innerText = res.secret;
                } else {
                    btn.innerHTML = '<i class="bi bi-shield-plus me-1"></i> Mulai Aktivasi 2FA';
                    btn.disabled = false;
                }
            })
            .catch(() => { btn.innerHTML = '<i class="bi bi-shield-plus me-1"></i> Mulai Aktivasi 2FA'; btn.disabled = false; });
    }

    function verify2FA() {
        const c = document.getElementById('code2fa').value;
        if(c.length < 6) { Swal.fire({icon:'warning', title:'Kode Kurang', text:'Masukkan 6 digit kode OTP!', confirmButtonColor:'#007bff'}); return; }
        const fd = new FormData();
        fd.append('action_2fa', 'verify_enable');
        fd.append('secret', globalSecret);
        fd.append('code', c);
        fetch(window.location.href, {method:'POST', body:fd})
            .then(r => r.json())
            .then(res => {
                if(res.success) {
                    Swal.fire({icon:'success', title:'Aktif!', text:res.msg, allowOutsideClick:false, confirmButtonColor:'#007bff'}).then(() => window.location.reload());
                } else {
                    Swal.fire({icon:'error', title:'Gagal', text:res.msg, confirmButtonColor:'#dc3545'});
                }
            });
    }

    function disable2FA() {
        const c = document.getElementById('offOTP').value;
        if(c.length < 6) { Swal.fire({icon:'warning', title:'Kode Kurang', text:'Masukkan 6 digit kode OTP!', confirmButtonColor:'#007bff'}); return; }
        const fd = new FormData();
        fd.append('action_2fa', 'disable');
        fd.append('code', c);
        fetch(window.location.href, {method:'POST', body:fd})
            .then(r => r.json())
            .then(res => {
                if(res.success) {
                    Swal.fire({icon:'success', title:'Dinonaktifkan!', text:res.msg, allowOutsideClick:false, confirmButtonColor:'#007bff'}).then(() => window.location.reload());
                } else {
                    Swal.fire({icon:'error', title:'OTP Salah', text:res.msg, confirmButtonColor:'#dc3545'});
                }
            });
    }

    <?php if($message_type): ?>
    Swal.fire({
        icon: '<?php echo $message_type; ?>',
        title: '<?php echo ($message_type == "success") ? "Berhasil!" : "Pemberitahuan"; ?>',
        text: '<?php echo addslashes($message_text); ?>',
        confirmButtonColor: '#007bff'
    });
    <?php endif; ?>
</script>

<?php include __DIR__ . '/../library/footer.php'; ?>
