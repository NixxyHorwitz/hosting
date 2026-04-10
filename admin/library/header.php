<?php
require_once __DIR__ . '/admin_session.php';

if (!function_exists('base_url')) {
    function base_url($path = '') {
        return '/' . ltrim($path, '/');
    }
}

$current_uri = $_SERVER['REQUEST_URI'] ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? $page_title . " - " : "" ?>CloudAdmin</title>
    
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    
    <!-- Font Awesome & Phosphor Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/regular/style.css" rel="stylesheet" />
    <link href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/fill/style.css" rel="stylesheet" />
    
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet" />
    
    <!-- Base Custom CSS -->
    <link rel="stylesheet" href="<?= base_url('admin/assets/base.css') ?>">
</head>
<body>

<div class="sb-overlay" id="sbOverlay" onclick="toggleSidebar()"></div>

<aside class="sidebar" id="sidebar">
    <a href="<?= base_url('admin/index') ?>" class="sb-brand">
        <div class="sb-logo"><i class="ph-fill ph-cloud-lightning"></i></div>
        <div class="sb-name">Cloud<span>Admin</span></div>
    </a>

    <div class="sb-scroll">
        <div class="sb-label">Main Menu</div>
        <a href="<?= base_url('admin/index') ?>" class="sb-link <?= (strpos($current_uri, 'index') !== false || $current_uri == '/admin' || $current_uri == '/admin/') ? 'active' : '' ?>">
            <i class="ph ph-squares-four"></i> Dashboard
        </a>
        <a href="<?= base_url('admin/hosting_plans') ?>" class="sb-link <?= (strpos($current_uri, 'hosting_plans') !== false) ? 'active' : '' ?>">
            <i class="ph ph-hard-drives"></i> Hosting Plans
        </a>

        <a href="<?= base_url('admin/orders') ?>" class="sb-link <?= (strpos($current_uri, 'orders') !== false) ? 'active' : '' ?>">
            <i class="ph ph-shopping-cart"></i> Orders
        </a>
        <a href="<?= base_url('admin/invoices') ?>" class="sb-link <?= (strpos($current_uri, 'invoices') !== false) ? 'active' : '' ?>">
            <i class="ph ph-receipt"></i> Invoices
        </a>
        
        <div class="sb-label mt-3">Users</div>
        <a href="<?= base_url('admin/users') ?>" class="sb-link <?= (strpos($current_uri, 'users') !== false) ? 'active' : '' ?>">
            <i class="ph ph-users"></i> Customers
        </a>
        <a href="<?= base_url('admin/tickets') ?>" class="sb-link <?= (strpos($current_uri, 'ticket') !== false) ? 'active' : '' ?>">
            <i class="ph ph-ticket"></i> Support Tickets
        </a>
        <div class="sb-label mt-3">System</div>
        <a href="<?= base_url('admin/whm_servers') ?>" class="sb-link <?= (strpos($current_uri, 'whm_servers') !== false) ? 'active' : '' ?>">
            <i class="ph ph-hard-drives"></i> WHM Servers
        </a>
        <a href="<?= base_url('admin/media') ?>" class="sb-link <?= (strpos($current_uri, 'media') !== false) ? 'active' : '' ?>">
            <i class="ph ph-images"></i> Media Library
        </a>
        <a href="<?= base_url('admin/traffic') ?>" class="sb-link <?= (strpos($current_uri, 'traffic') !== false) ? 'active' : '' ?>">
            <i class="ph ph-activity"></i> Traffic Monitor
        </a>
        <a href="<?= base_url('admin/website') ?>" class="sb-link <?= (strpos($current_uri, 'website') !== false) ? 'active' : '' ?>">
            <i class="ph ph-browser"></i> System Settings
        </a>
        <a href="<?= base_url('admin/landing_settings') ?>" class="sb-link <?= (strpos($current_uri, 'landing_settings') !== false) ? 'active' : '' ?>">
            <i class="ph ph-layout"></i> Landing Editor
        </a>
    </div>

    <div class="sb-user">
        <div class="sb-ava d-flex align-items-center justify-content-center bg-primary text-white font-weight-bold" style="font-size: 14px;">
            <?= substr($_SESSION['username'] ?? 'A', 0, 1) ?>
        </div>
        <div>
            <div class="sb-uname"><?= $_SESSION['username'] ?? 'Administrator' ?></div>
            <div class="sb-urole">Super Admin</div>
        </div>
        <a href="<?= base_url('admin/logout') ?>" class="sb-logout" title="Logout">
            <i class="ph ph-sign-out"></i>
        </a>
    </div>
</aside>

<header class="topbar">
    <button class="sb-toggle" onclick="toggleSidebar()">
        <i class="ph ph-list"></i>
    </button>
    <h1 class="tb-title"><?= isset($page_title) ? $page_title : "Dashboard" ?></h1>
    <div class="ms-auto d-flex align-items-center gap-2">
        <span class="d-none d-md-block text-muted small"><i class="ph ph-calendar-blank me-1"></i> <?= date('d M Y') ?></span>
    </div>
</header>

<main class="main-content">
<div class="content-wrap">
