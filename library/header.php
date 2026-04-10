<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
// Fetch standard settings
$_site_name = 'SobatHosting';
$_site_logo = '';
$_site_favicon = '';
if (isset($conn)) {
    $r_set = @mysqli_query($conn, "SELECT site_name, site_logo, site_favicon FROM settings LIMIT 1");
    if ($r_set && $row = mysqli_fetch_assoc($r_set)) {
        if (!empty($row['site_name']))    $_site_name    = htmlspecialchars($row['site_name']);
        if (!empty($row['site_logo']))    $_site_logo    = htmlspecialchars($row['site_logo']);
        if (!empty($row['site_favicon'])) $_site_favicon = htmlspecialchars($row['site_favicon']);
    }
}

// Fetch user data
$hosting_aktif = 0;
if (isset($_SESSION['user_id']) && isset($conn)) {
    $user_id = mysqli_real_escape_string($conn, $_SESSION['user_id']);
    $query = mysqli_query($conn, "SELECT nama FROM users WHERE id = '$user_id'");
    $data = mysqli_fetch_assoc($query);
    $user_nama = $data['nama'] ?? "User";
    
    // Count active hosting
    $q_act = @mysqli_query($conn, "SELECT COUNT(id) as c FROM orders WHERE user_id='$user_id' AND status='active'");
    if ($q_act) $hosting_aktif = mysqli_fetch_assoc($q_act)['c'] ?? 0;
} else {
    $user_nama = "Tamu"; 
}

if (!function_exists('base_url')) {
    function base_url($path = '') {
        return '/' . ltrim($path, '/');
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? $page_title . " - " : "" ?><?= $_site_name ?></title>
    <?php
    // Favicon: use site_favicon first, fall back to site_logo
    $_fav_file = !empty($_site_favicon) ? $_site_favicon : $_site_logo;
    if (!empty($_fav_file)):
    ?>
    <link rel="icon" href="<?= base_url('uploads/' . $_fav_file) ?>" type="image/x-icon">
    <?php endif; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root { 
            --bg-body: #f4f6f9;
            --sidebar-bg: #ffffff;
            --text-main: #333333;
            --text-muted: #6c757d;
            --border-color: #eaedf1;
            
            --primary-blue: #007bff;
            --active-blue-bg: #ebf5ff;
            --badge-green: #20c997; 
            
            --sidebar-width: 250px;
        }
        
        body { 
            background-color: var(--bg-body); 
            font-family: 'Inter', sans-serif; 
            color: var(--text-main);
            overflow-x: hidden;
            font-size: 0.85rem; 
        }

        a { text-decoration: none; }

        /* Sidebar Styling */
        .sidebar { 
            width: var(--sidebar-width); 
            height: 100vh; 
            background: var(--sidebar-bg); 
            position: fixed; 
            left: 0; 
            top: 0; 
            z-index: 1050; 
            border-right: 1px solid var(--border-color);
            transition: all 0.3s ease;
            overflow-y: auto;
        }

        .sidebar-brand {
            padding: 1.5rem;
            font-weight: 800;
            font-size: 1.25rem;
            color: #00b4d8;
            border-bottom: 1px solid transparent;
            display: flex;
            align-items: center;
            gap: 10px;
            letter-spacing: -0.5px;
        }

        .sidebar-brand i { 
            font-size: 1.5rem;
        }

        .sidebar-section-title {
            padding: 1.5rem 1.5rem 0.5rem;
            font-size: 0.65rem;
            font-weight: 700;
            color: #b0b8c1;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .nav-link { 
            color: var(--text-muted); 
            padding: 0.6rem 1.2rem; 
            margin: 0.15rem 1rem; 
            border-radius: 6px; 
            display: flex; 
            align-items: center; 
            font-weight: 500;
            font-size: 0.85rem;
            transition: all 0.2s; 
        }

        .nav-link i { font-size: 1rem; margin-right: 12px; color: #a1aab3; transition: color 0.2s; }

        .nav-link:hover { 
            background: #f8f9fa; 
            color: var(--text-main); 
        }
        
        .nav-link.active {
            background: var(--active-blue-bg);
            color: var(--primary-blue);
            font-weight: 600;
        }

        .nav-link.active i { color: var(--primary-blue); }

        .nav-badge {
            margin-left: auto;
            background: var(--primary-blue);
            color: white;
            font-size: 0.65rem;
            padding: 2px 6px;
            border-radius: 4px;
        }

        /* Sub-menu styling */
        .collapse-inner {
            background: #fdfdfd;
            border-left: 2px solid var(--border-color);
            margin-left: 1.5rem;
        }

        .nav-link.sub-item {
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
            margin: 0.25rem 0.5rem;
        }

        /* Main Content Area */
        .main-content { 
            margin-left: var(--sidebar-width); 
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Topbar */
        .topbar {
            background: #ffffff;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 1020;
            border-bottom: 1px solid var(--border-color);
            box-shadow: 0 4px 12px rgba(0,0,0,0.03);
        }

        /* Top action buttons */
        .btn-top {
            font-size: 0.8rem;
            font-weight: 500;
            padding: 0.4rem 0.8rem;
            border: 1px solid #d1d8e0;
            background: white;
            color: var(--text-muted);
            border-radius: 4px;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }
        .btn-top:hover {
            background: #f8f9fa;
            color: var(--text-main);
        }
        
        .btn-top.active-action {
            color: var(--primary-blue);
            border-color: #bbd6f5;
        }

        /* User badge */
        .user-badge {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 0.4rem 0.5rem 0.4rem 1rem;
            background: transparent;
            cursor: pointer;
            transition: all 0.2s;
            color: var(--text-muted);
        }
        .user-avatar {
            width: 28px; height: 28px;
            background: #e2e8f0;
            color: var(--text-main);
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.85rem;
        }

        .dropdown-menu { font-size: 0.85rem; border: 1px solid var(--border-color); box-shadow: 0 4px 15px rgba(0,0,0,0.05); }

        /* Layout Container */
        .content-container {
            padding: 2rem;
            flex-grow: 1;
        }

        /* Mobile View Adjustments */
        .sidebar-overlay {
            position: fixed;
            top: 0; left: 0; width: 100vw; height: 100vh;
            background: rgba(0, 0, 0, 0.4);
            z-index: 1040;
            display: none;
        }

        .mobile-toggle { 
            display: none; background: transparent; border: none; color: var(--text-main); 
            font-size: 1.5rem; cursor: pointer; padding: 0;
        }

        @media (max-width: 991.98px) {
            .sidebar { left: calc(-1 * var(--sidebar-width)); }
            .sidebar.active { left: 0; }
            .main-content { margin-left: 0; }
            .sidebar-overlay.active { display: block; }
            .mobile-toggle { display: block; }
            .content-container { padding: 1.5rem 1rem 1rem 1rem; }
            .topbar { padding: 1rem; }
        }
    </style>
</head>
<body>

<div class="sidebar-overlay" id="overlay" onclick="toggleMenu()"></div>

<nav class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <?php if(!empty($_site_logo)): ?>
        <img src="<?= base_url('uploads/' . $_site_logo) ?>" alt="<?= $_site_name ?>" style="max-height:32px;max-width:130px;object-fit:contain;">
        <?php else: ?>
        <i class="bi bi-clouds-fill"></i> <?= $_site_name ?>
        <?php endif; ?>
    </div>
    
    <div class="nav flex-column mt-2">
        <?php
        $current_uri = $_SERVER['REQUEST_URI'] ?? '';
        $is_dashboard = ($current_uri === '/' || rtrim($current_uri, '/') === '' || rtrim($current_uri, '/') === '/hosting');
        $is_hosting_layanan = (strpos($current_uri, '/hosting/layanan') !== false);
        $is_hosting_index = (strpos($current_uri, '/hosting/services') !== false);
        $is_hosting_section = ($is_hosting_index || $is_hosting_layanan);
        $is_invoice = (strpos($current_uri, '/billing') !== false || strpos($current_uri, '/invoice') !== false);
        ?>
        <a href="<?= base_url('hosting') ?>" class="nav-link <?= $is_dashboard ? 'active' : '' ?>">
            <i class="bi bi-house-door"></i> Dashboard
        </a>

        <div class="sidebar-section-title">Product & Service</div>
    

        <a class="nav-link <?= $is_hosting_section ? 'active' : '' ?>" data-bs-toggle="collapse" href="#menuHosting" role="button" aria-expanded="<?= $is_hosting_section ? 'true' : 'false' ?>">
            <i class="bi bi-hdd-network"></i> Hosting <span class="nav-badge"><?= $hosting_aktif ?></span>
        </a>
        <div class="collapse <?= $is_hosting_section ? 'show' : '' ?>" id="menuHosting">
            <div class="collapse-inner py-1">
                <a href="<?= base_url('hosting/services') ?>" class="nav-link sub-item <?= $is_hosting_index ? 'active' : '' ?>">Pesan Baru</a>
                <a href="<?= base_url('hosting/layanan') ?>" class="nav-link sub-item <?= $is_hosting_layanan ? 'active' : '' ?>">Layanan Anda</a>
            </div>
        </div>

    
        <div class="sidebar-section-title">Billing</div>
        
        <a href="<?= base_url('hosting/billing') ?>" class="nav-link <?= $is_invoice ? 'active' : '' ?>">
            <i class="bi bi-receipt"></i> Invoices
        </a>

        <div class="sidebar-section-title">Support</div>
        <a href="<?= base_url('hosting/tickets') ?>" class="nav-link <?= (strpos($current_uri, 'ticket') !== false) ? 'active' : '' ?>">
            <i class="bi bi-ticket-perforated"></i> Trouble Ticket
        </a>
   

        <?php if(isset($_SESSION['user_id'])): ?>
            <a href="<?= base_url('hosting/profile') ?>" class="nav-link">
                <i class="bi bi-person"></i> Akun Saya
            </a>
            <a href="<?= base_url('logout') ?>" class="nav-link text-danger">
                <i class="bi bi-box-arrow-left text-danger"></i> Keluar
            </a>
        <?php else: ?>
            <a href="<?= base_url('auth') ?>" class="nav-link">
                <i class="bi bi-box-arrow-in-right"></i> Login Area
            </a>
        <?php endif; ?>
    </div>
</nav>

<div class="main-content">
    <!-- Topbar -->
    <div class="topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="mobile-toggle" onclick="toggleMenu()">
                <i class="bi bi-list"></i>
            </button>
            <div class="d-none d-md-flex align-items-center">
                <span class="text-secondary fw-medium me-2" style="font-size: 0.85rem;">Produk <i class="bi bi-chevron-right ms-1 mx-1" style="font-size: 0.6rem;"></i></span>
                <span class="badge" style="background:#20c997; color:white; padding: 5px 12px; font-weight: 500; font-size: 0.8rem; border-radius: 4px;">Hosting</span>
            </div>
        </div>
        
        <div class="d-flex align-items-center gap-2">
            <a href="<?= base_url('hosting/services') ?>" class="btn-top active-action d-none d-md-flex"><i class="bi bi-cart"></i> Pesan</a>
            
            <div class="dropdown">
                <div class="user-badge" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="d-none d-md-block fs-6 px-1 fw-medium">Hi, <?= explode(' ', trim($user_nama))[0] ?></span>
                    <div class="user-avatar text-uppercase">
                        <?= substr($user_nama, 0, 1) ?>
                    </div>
                </div>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="border-radius: 8px;">
                    <li><a class="dropdown-item" href="<?= base_url('hosting/profile') ?>">Profil Akun</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="<?= base_url('logout') ?>">Keluar</a></li>
                </ul>
            </div>
        </div>
    </div>

    <script>
        function toggleMenu() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        }
    </script>
    
    <div class="content-container">
