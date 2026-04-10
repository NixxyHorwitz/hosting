<?php
if(!defined('NS1')) include __DIR__ . '/../config/database.php';

// Fetch site name dari DB
$_admin_site_name = 'Admin Panel';
$_admin_site_logo = '';
$_admin_site_favicon = '';
$_r = @mysqli_query($conn, "SELECT site_name, site_logo, site_favicon FROM settings LIMIT 1");
if ($_r && $_row = mysqli_fetch_assoc($_r)) {
    if (!empty($_row['site_name']))    $_admin_site_name    = htmlspecialchars($_row['site_name']);
    if (!empty($_row['site_logo']))    $_admin_site_logo    = htmlspecialchars($_row['site_logo']);
    if (!empty($_row['site_favicon'])) $_admin_site_favicon = htmlspecialchars($_row['site_favicon']);
}
$_admin_fav = !empty($_admin_site_favicon) ? $_admin_site_favicon : $_admin_site_logo;

// Jika sudah login admin, langsung ke dashboard admin
if (isset($_SESSION['admin_id'])) {
    header("Location: dashboard.php"); 
    exit();
}

$message_type = "";
$message_text = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password'];

    // Cari di tabel admins
    $query = mysqli_query($conn, "SELECT * FROM admins WHERE username = '$username'");
    
    if (mysqli_num_rows($query) > 0) {
        $admin = mysqli_fetch_assoc($query);
        
        // Verifikasi Password Hash
        if (password_verify($password, $admin['password'])) {
            // Set session khusus admin
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['username'] = $admin['username'];
            
            $message_type = "success_login";
        } else {
            $message_type = "error";
            $message_text = "Password salah.";
        }
    } else {
        $message_type = "error";
        $message_text = "Username tidak ditemukan.";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - <?= $_admin_site_name ?></title>
    <?php if(!empty($_admin_fav)): ?>
    <link rel="icon" href="/uploads/<?= $_admin_fav ?>" type="image/x-icon">
    <?php endif; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <link href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/regular/style.css" rel="stylesheet" />
    <link href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/fill/style.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <style>
        body { 
            background-color: #090d18; 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            height: 100vh; 
            display: flex; 
            align-items: center; 
            color: #e2e8f0;
        }
        .auth-card { 
            border: 1px solid rgba(255, 255, 255, 0.06); 
            border-radius: 12px; 
            background: #131d30; 
            padding: 40px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.4);
        }
        .form-control { 
            background: #192035; 
            border: 1px solid rgba(255, 255, 255, 0.06); 
            color: #e2e8f0; 
            border-radius: 8px;
            padding: 12px 14px;
            font-size: 13.5px;
        }
        .form-control:focus { 
            background: #192035; 
            border-color: #3b82f6; 
            color: #fff; 
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3); 
        }
        .form-control::placeholder {
            color: #4b5e7a;
        }
        .btn-primary { 
            background: #3b82f6; 
            border: none; 
            border-radius: 8px; 
            padding: 12px; 
            font-weight: 600;
            font-size: 13.5px;
        }
        .btn-primary:hover { background: #2563eb; }
        .input-group-text { 
            background: #192035; 
            border: 1px solid rgba(255, 255, 255, 0.06); 
            color: #7a90b0; 
            border-right: none;
        }
        .form-control { border-left: none; }
        .input-group:focus-within .input-group-text {
            border-color: #3b82f6;
        }
        .brand-logo {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, #3b82f6, #06b6d4);
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: #fff;
            box-shadow: 0 4px 18px rgba(59, 130, 246, 0.3);
            margin-bottom: 12px;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-5 col-lg-4">
            <div class="text-center mb-4">
                <?php if(!empty($_admin_site_logo)): ?>
                <div class="brand-logo" style="background:transparent;">
                    <img src="/uploads/<?= $_admin_site_logo ?>" alt="<?= $_admin_site_name ?>" style="max-height:42px;max-width:120px;object-fit:contain;">
                </div>
                <?php else: ?>
                <div class="brand-logo"><i class="ph-fill ph-cloud-lightning"></i></div>
                <?php endif; ?>
                <h4 class="fw-bold text-white mb-1" style="font-size: 20px;"><?= $_admin_site_name ?> <span style="color:#3b82f6;font-weight:400;font-size:13px;">Admin</span></h4>
                <p style="font-size: 13px; color: #7a90b0;">Secure Control Panel Access</p>
            </div>
            
            <div class="card auth-card">
                <form action="" method="POST">
                    <div class="mb-3">
                        <label class="form-label fw-bold" style="font-size: 11px; color: #7a90b0; text-transform: uppercase;">Username</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="ph ph-user"></i></span>
                            <input type="text" name="username" class="form-control" placeholder="Admin username" required>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-bold" style="font-size: 11px; color: #7a90b0; text-transform: uppercase;">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="ph ph-lock-key"></i></span>
                            <input type="password" name="password" id="adminPass" class="form-control" placeholder="••••••••" required>
                            <button class="btn btn-outline-secondary border-secondary-subtle" type="button" onclick="togglePassword()" style="border: 1px solid rgba(255, 255, 255, 0.06); border-left: none; border-radius: 0 8px 8px 0; background: #192035; color: #7a90b0;">
                                <i class="ph ph-eye" id="eyeIcon"></i>
                            </button>
                        </div>
                    </div>
                    <button type="submit" name="login" class="btn btn-primary w-100">MASUK KE PANEL</button>
                </form>
            </div>
            <p class="text-center mt-4" style="color: #4b5e7a; font-size: 12px;">
                &copy; <?php echo date('Y'); ?> <?= $_admin_site_name ?> &mdash; Secure Access
            </p>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function togglePassword() {
        const pass = document.getElementById('adminPass');
        const icon = document.getElementById('eyeIcon');
        if (pass.type === "password") {
            pass.type = "text";
            icon.classList.replace('ph-eye', 'ph-eye-slash');
        } else {
            pass.type = "password";
            icon.classList.replace('ph-eye-slash', 'ph-eye');
        }
    }

    // Mengikuti logika notifikasi script Anda
    <?php if($message_type == "success_login"): ?>
        Swal.fire({
            icon: 'success',
            title: 'Akses Diterima',
            text: 'Membuka panel admin...',
            timer: 1500,
            showConfirmButton: false,
            background: '#18181b',
            color: '#fff'
        }).then(() => { window.location.href = 'dashboard.php'; });
    <?php elseif($message_type == "error"): ?>
        Swal.fire({
            icon: 'error',
            title: 'Akses Ditolak',
            text: '<?php echo $message_text; ?>',
            confirmButtonColor: '#3b82f6',
            background: '#18181b',
            color: '#fff'
        });
    <?php endif; ?>
</script>
</body>
</html>