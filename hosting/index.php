<?php
require_once __DIR__ . '/../config/database.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../library/session.php';

$user_id   = $_SESSION['user_id'];
$user_nama = htmlspecialchars($_SESSION['user_nama'], ENT_QUOTES, 'UTF-8');

// ─── Auto-create tables if missing ────────────────────────────
$check_banners = mysqli_query($conn, "SHOW TABLES LIKE 'banners'");
if(mysqli_num_rows($check_banners) == 0) {
    mysqli_query($conn, "CREATE TABLE banners (id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(255), subtitle VARCHAR(255), image VARCHAR(255), button_text VARCHAR(100) DEFAULT 'Pesan Sekarang', button_url VARCHAR(255) DEFAULT '/hosting/services', bg_color VARCHAR(100) DEFAULT 'linear-gradient(135deg,#0a1628,#1e3a6e)', is_active TINYINT(1) DEFAULT 1, sort_order INT DEFAULT 0)");
    mysqli_query($conn, "INSERT INTO banners (title, subtitle, button_text, button_url, bg_color) VALUES ('Hosting Unlimited Terpercaya', 'Uptime 99.9% · Migrasi Gratis · Dukungan 24/7', 'Lihat Paket', '/hosting/services', 'linear-gradient(135deg,#0a1628 0%,#1e3a6e 100%)')");
}
$check_ann = mysqli_query($conn, "SHOW TABLES LIKE 'announcements'");
if(mysqli_num_rows($check_ann) == 0) {
    mysqli_query($conn, "CREATE TABLE announcements (id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(255) NOT NULL, content TEXT, category ENUM('info','warning','promo','maintenance') DEFAULT 'info', is_active TINYINT(1) DEFAULT 1, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
    mysqli_query($conn, "INSERT INTO announcements (title, content, category) VALUES ('Selamat datang di Panel Hosting', 'Kelola semua layanan hosting Anda dengan mudah di satu tempat.', 'info')");
}

// ─── Stats User ────────────────────────────────────────────────
$stat_active  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(id) c FROM orders WHERE user_id='$user_id' AND status='active'"))['c'];
$stat_pending = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(id) c FROM orders WHERE user_id='$user_id' AND status='pending'"))['c'];
$stat_tickets = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(id) c FROM tickets WHERE user_id='$user_id' AND status IN ('Open','Customer-Reply')"))['c'];
$stat_inv     = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(id) c FROM invoices WHERE user_id='$user_id' AND status='unpaid'"))['c'] ?? 0;

// ─── Pesanan Terakhir ──────────────────────────────────────────
$q_orders = mysqli_query($conn, "SELECT o.*, hp.nama_paket FROM orders o
    LEFT JOIN hosting_plans hp ON o.plan_id = hp.id
    WHERE o.user_id = '$user_id' ORDER BY o.id DESC LIMIT 5");

// ─── Running Text + Settings ───────────────────────────────────
$settings = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM settings WHERE id=1"));
$running_text         = $settings['running_text'] ?? '';
$running_text_enabled = !empty($settings['running_text_enabled']);

// ─── Banners ───────────────────────────────────────────────────
$q_banners = mysqli_query($conn, "SELECT * FROM banners WHERE is_active=1 ORDER BY sort_order ASC");
$banners   = mysqli_fetch_all($q_banners, MYSQLI_ASSOC);

// ─── Announcements ─────────────────────────────────────────────
$q_ann = mysqli_query($conn, "SELECT * FROM announcements WHERE is_active=1 ORDER BY created_at DESC LIMIT 5");
$announcements = mysqli_fetch_all($q_ann, MYSQLI_ASSOC);

// ─── Invoice Terdekat Jatuh Tempo ─────────────────────────────
$q_invoices = mysqli_query($conn, "SELECT * FROM invoices WHERE user_id='$user_id' AND status='unpaid' ORDER BY due_date ASC LIMIT 3");

$page_title = "Dashboard";
include __DIR__ . '/../library/header.php';

$ann_icons = ['info' => 'bi-info-circle-fill', 'warning' => 'bi-exclamation-triangle-fill', 'promo' => 'bi-tag-fill', 'maintenance' => 'bi-tools'];
$ann_colors = ['info' => '#3b82f6', 'warning' => '#f59e0b', 'promo' => '#10b981', 'maintenance' => '#8b5cf6'];
?>

<style>
/* ─── Running Text ────────────────────────────── */
.running-text-wrap {
    background: linear-gradient(90deg, var(--primary-blue), #0ea5e9);
    border-radius: 6px;
    overflow: hidden;
    display: flex;
    align-items: center;
    gap: 0;
    max-height: 34px;
    margin-bottom: 14px;
}
.running-text-label {
    background: rgba(0,0,0,.25);
    color: #fff;
    font-size: 10.5px;
    font-weight: 700;
    letter-spacing: .5px;
    padding: 0 12px;
    height: 34px;
    display: flex;
    align-items: center;
    white-space: nowrap;
    flex-shrink: 0;
}
.running-text-track {
    flex: 1;
    overflow: hidden;
    position: relative;
    height: 34px;
    mask-image: linear-gradient(90deg, transparent 0%, #000 5%, #000 95%, transparent 100%);
}
.running-text-inner {
    display: inline-flex;
    white-space: nowrap;
    animation: runningText 30s linear infinite;
    height: 34px;
    align-items: center;
    gap: 48px;
    color: #fff;
    font-size: 12px;
    font-weight: 500;
}
@keyframes runningText {
    from { transform: translateX(0); }
    to   { transform: translateX(-50%); }
}

/* ─── Banner Slider ───────────────────────────── */
.banner-slider {
    border-radius: 10px;
    overflow: hidden;
    position: relative;
    height: 160px;
    background: linear-gradient(135deg,#0a1628,#1e3a6e);
}
.banner-slide {
    position: absolute;
    inset: 0;
    opacity: 0;
    transition: opacity .5s ease;
    display: flex;
    align-items: center;
    padding: 20px 24px;
}
.banner-slide.active { opacity: 1; }
.banner-dots {
    position: absolute;
    bottom: 10px;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    gap: 5px;
}
.banner-dot {
    width: 6px; height: 6px;
    border-radius: 50%;
    background: rgba(255,255,255,.35);
    cursor: pointer;
    transition: all .2s;
}
.banner-dot.active { background: #fff; width: 18px; border-radius: 3px; }
.banner-nav {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(0,0,0,.3);
    border: none;
    color: #fff;
    width: 26px;
    height: 26px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 12px;
    transition: background .2s;
}
.banner-nav:hover { background: rgba(0,0,0,.55); }
.banner-nav.prev { left: 8px; }
.banner-nav.next { right: 8px; }

/* ─── Stat Cards ──────────────────────────────── */
.stat-card-new {
    background: white;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 14px 16px;
    display: flex;
    align-items: center;
    gap: 12px;
    transition: box-shadow .2s, transform .15s;
}
.stat-card-new:hover { box-shadow: 0 4px 15px rgba(0,0,0,.07); transform: translateY(-1px); }
.stat-icon {
    width: 40px; height: 40px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
}
.stat-val { font-size: 22px; font-weight: 800; line-height: 1; color: #1a1a2e; }
.stat-lbl { font-size: 11px; color: #6c757d; font-weight: 500; margin-top: 3px; }

/* ─── Announcement ────────────────────────────── */
.ann-item {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 10px 0;
    border-bottom: 1px solid var(--border-color);
    font-size: 13px;
}
.ann-item:last-child { border-bottom: none; padding-bottom: 0; }
.ann-icon {
    width: 28px; height: 28px;
    border-radius: 7px;
    display: flex; align-items: center; justify-content: center;
    font-size: 13px;
    flex-shrink: 0;
    margin-top: 1px;
}

/* ─── Order Badge ─────────────────────────────── */
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 9px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
}
.sb-active   { background: #e6f9f1; color: #10b981; }
.sb-pending  { background: #fff8e1; color: #f59e0b; }
.sb-suspended{ background: #fef2f2; color: #ef4444; }

/* ─── Quick Links ─────────────────────────────── */
.quick-link {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    padding: 14px 8px;
    border-radius: 10px;
    text-decoration: none;
    font-size: 11px;
    font-weight: 600;
    color: #555;
    background: white;
    border: 1px solid var(--border-color);
    transition: all .2s;
    text-align: center;
}
.quick-link:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,.08); color: #007bff; border-color: #007bff; }
.quick-link-icon { width: 36px; height: 36px; border-radius: 9px; display: flex; align-items: center; justify-content: center; font-size: 16px; }
</style>

<!-- ─── Running Text ──────────────────────────── -->
<?php if($running_text_enabled && $running_text): ?>
<div class="running-text-wrap">
    <div class="running-text-label"><i class="bi bi-megaphone-fill me-1"></i> INFO</div>
    <div class="running-text-track">
        <div class="running-text-inner">
            <span><?= htmlspecialchars($running_text) ?></span>
            <span><?= htmlspecialchars($running_text) ?></span>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ─── Main Grid ─────────────────────────────── -->
<div class="row g-3 mb-3">

    <!-- Kolom kiri: Banner + Quick Links + Announcements -->
    <div class="col-lg-8">

        <!-- Banner Slider -->
        <?php if(!empty($banners)): ?>
        <div class="banner-slider mb-3" id="bannerSlider">
            <?php foreach($banners as $idx => $b): ?>
            <div class="banner-slide <?= $idx === 0 ? 'active' : '' ?>" style="background: <?= htmlspecialchars($b['bg_color'] ?? 'linear-gradient(135deg,#0a1628,#1e3a6e)') ?>;">
                <?php if(!empty($b['image'])): ?>
                <img src="<?= base_url('uploads/'.$b['image']) ?>" alt="" style="position:absolute;right:0;top:0;height:100%;opacity:.18;object-fit:cover;">
                <?php endif; ?>
                <div style="position:relative;z-index:1;max-width:70%;">
                    <div style="font-size:10px;color:rgba(255,255,255,.65);font-weight:700;letter-spacing:1.5px;text-transform:uppercase;margin-bottom:6px;">Selamat datang, <?= $user_nama ?></div>
                    <h5 class="fw-bold mb-1" style="color:#fff;font-size:18px;line-height:1.3;"><?= htmlspecialchars($b['title']) ?></h5>
                    <?php if(!empty($b['subtitle'])): ?>
                    <p style="color:rgba(255,255,255,.75);font-size:12px;margin-bottom:14px;"><?= htmlspecialchars($b['subtitle']) ?></p>
                    <?php endif; ?>
                    <a href="<?= htmlspecialchars($b['button_url'] ?? '/hosting/services') ?>" class="btn btn-sm fw-bold" style="background:#fff;color:#1e3a6e;border-radius:20px;font-size:12px;padding:6px 18px;">
                        <?= htmlspecialchars($b['button_text'] ?? 'Pesan Sekarang') ?> <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if(count($banners) > 1): ?>
            <button class="banner-nav prev" onclick="slideBanner(-1)"><i class="bi bi-chevron-left"></i></button>
            <button class="banner-nav next" onclick="slideBanner(1)"><i class="bi bi-chevron-right"></i></button>
            <div class="banner-dots" id="bannerDots">
                <?php foreach($banners as $idx => $b): ?>
                <div class="banner-dot <?= $idx === 0 ? 'active' : '' ?>" onclick="goToSlide(<?= $idx ?>)"></div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <!-- Fallback Welcome Banner jika tidak ada banner -->
        <div class="banner-slider mb-3" style="background:linear-gradient(135deg,#0a1628 0%,#1e3a6e 100%);">
            <div class="banner-slide active" style="background:transparent;">
                <div style="position:relative;z-index:1;">
                    <div style="font-size:10px;color:rgba(255,255,255,.65);font-weight:700;letter-spacing:1.5px;text-transform:uppercase;margin-bottom:6px;">Selamat datang</div>
                    <h5 class="fw-bold mb-2" style="color:#fff;font-size:18px;">Halo, <?= $user_nama ?>! 👋</h5>
                    <p style="color:rgba(255,255,255,.7);font-size:12px;margin-bottom:14px;">Kelola semua layanan hosting Anda dari satu panel modern.</p>
                    <a href="<?= base_url('hosting/services') ?>" class="btn btn-sm fw-bold" style="background:#fff;color:#1e3a6e;border-radius:20px;font-size:12px;padding:6px 18px;">
                        Lihat Paket <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Quick Links -->
        <div class="row g-2 mb-3">
            <div class="col-3 col-sm-3">
                <a href="<?= base_url('hosting/services') ?>" class="quick-link">
                    <div class="quick-link-icon" style="background:#ebf5ff;color:#007bff;"><i class="bi bi-hdd-stack"></i></div>
                    <span>Pesan Hosting</span>
                </a>
            </div>
            <div class="col-3 col-sm-3">
                <a href="<?= base_url('hosting/billing') ?>" class="quick-link">
                    <div class="quick-link-icon" style="background:#fff8e1;color:#f59e0b;"><i class="bi bi-receipt"></i></div>
                    <span>Tagihan</span>
                </a>
            </div>
            <div class="col-3 col-sm-3">
                <a href="<?= base_url('hosting/tickets') ?>" class="quick-link">
                    <div class="quick-link-icon" style="background:#f0fdf4;color:#10b981;"><i class="bi bi-headset"></i></div>
                    <span>Buat Tiket</span>
                </a>
            </div>
            <div class="col-3 col-sm-3">
                <a href="<?= base_url('hosting/profile') ?>" class="quick-link">
                    <div class="quick-link-icon" style="background:#f5f0ff;color:#8b5cf6;"><i class="bi bi-person-gear"></i></div>
                    <span>Profil Akun</span>
                </a>
            </div>
        </div>

        <!-- Pesanan / Hosting Terakhir -->
        <div class="card border-0 shadow-sm" style="border-radius:8px;background:white;border:1px solid var(--border-color)!important;">
            <div class="card-body p-0">
                <div class="d-flex justify-content-between align-items-center px-4 py-3 border-bottom" style="border-color:var(--border-color)!important;">
                    <span class="fw-bold text-dark" style="font-size:14px;"><i class="bi bi-clock-history me-2" style="color:#48cae4;"></i> Layanan Hosting Saya</span>
                    <a href="<?= base_url('hosting/layanan') ?>" class="text-primary" style="font-size:12px;font-weight:600;">Lihat Semua →</a>
                </div>
                <?php if(mysqli_num_rows($q_orders) > 0): ?>
                <div class="table-responsive">
                    <table class="table mb-0" style="font-size:12.5px;">
                        <thead style="background:#f8f9fa;">
                            <tr>
                                <th class="px-4 py-2 fw-semibold text-muted border-0" style="font-size:10.5px;">PAKET / DOMAIN</th>
                                <th class="py-2 fw-semibold text-muted border-0 text-center" style="font-size:10.5px;">STATUS</th>
                                <th class="py-2 fw-semibold text-muted border-0 text-end pe-4" style="font-size:10.5px;">AKSI</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = mysqli_fetch_assoc($q_orders)):
                                $sb = match($row['status']) {
                                    'active'    => ['sb-active', 'bi-circle-fill', 'Aktif'],
                                    'pending'   => ['sb-pending', 'bi-clock-fill', 'Pending'],
                                    'suspended' => ['sb-suspended', 'bi-x-circle-fill', 'Suspended'],
                                    default     => ['sb-pending', 'bi-dash-circle', $row['status']],
                                };
                            ?>
                            <tr style="border-color:var(--border-color)!important;">
                                <td class="px-4 py-2 align-middle border-0 border-bottom" style="border-color:var(--border-color)!important;">
                                    <div class="fw-bold text-dark" style="font-size:13px;"><?= htmlspecialchars($row['nama_paket'] ?? 'N/A') ?></div>
                                    <div class="text-muted" style="font-size:11px;"><i class="bi bi-globe2 me-1"></i><?= htmlspecialchars($row['domain'] ?? '—') ?></div>
                                </td>
                                <td class="py-2 align-middle border-0 border-bottom text-center" style="border-color:var(--border-color)!important;">
                                    <span class="status-badge <?= $sb[0] ?>"><i class="bi <?= $sb[1] ?>" style="font-size:9px;"></i> <?= $sb[2] ?></span>
                                </td>
                                <td class="pe-4 py-2 align-middle border-0 border-bottom text-end" style="border-color:var(--border-color)!important;">
                                    <?php if($row['status'] === 'active'): ?>
                                    <a href="<?= base_url('hosting/manage/'.$row['id']) ?>" class="btn btn-sm fw-medium" style="font-size:11px;background:#ebf5ff;color:#007bff;border-radius:20px;padding:3px 12px;border:none;">Kelola</a>
                                    <?php else: ?>
                                    <a href="<?= base_url('hosting/invoice/'.$row['id']) ?>" class="btn btn-sm fw-medium" style="font-size:11px;background:#f8f9fa;color:#555;border-radius:20px;padding:3px 12px;border:1px solid #ddd;">Detail</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-5">
                    <div class="mb-3" style="font-size:40px;">🖥️</div>
                    <p class="text-muted small mb-3">Belum ada layanan hosting aktif.</p>
                    <a href="<?= base_url('hosting/services') ?>" class="btn btn-primary btn-sm px-4" style="border-radius:20px;font-size:13px;">Pesan Sekarang</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Kolom Kanan: Stats + Invoice + Announcements -->
    <div class="col-lg-4">

        <!-- Stat Cards -->
        <div class="row g-2 mb-3">
            <div class="col-6">
                <div class="stat-card-new">
                    <div class="stat-icon" style="background:#ebf5ff;"><i class="bi bi-hdd-stack" style="color:#007bff;"></i></div>
                    <div>
                        <div class="stat-val"><?= $stat_active ?></div>
                        <div class="stat-lbl">Hosting Aktif</div>
                    </div>
                </div>
            </div>
            <div class="col-6">
                <div class="stat-card-new">
                    <div class="stat-icon" style="background:#fff8e1;"><i class="bi bi-clock-history" style="color:#f59e0b;"></i></div>
                    <div>
                        <div class="stat-val"><?= $stat_pending ?></div>
                        <div class="stat-lbl">Menunggu Bayar</div>
                    </div>
                </div>
            </div>
            <div class="col-6">
                <div class="stat-card-new">
                    <div class="stat-icon" style="background:#fef2f2;"><i class="bi bi-receipt" style="color:#ef4444;"></i></div>
                    <div>
                        <div class="stat-val"><?= $stat_inv ?></div>
                        <div class="stat-lbl">Invoice Belum Lunas</div>
                    </div>
                </div>
            </div>
            <div class="col-6">
                <div class="stat-card-new">
                    <div class="stat-icon" style="background:#f0fdf4;"><i class="bi bi-headset" style="color:#10b981;"></i></div>
                    <div>
                        <div class="stat-val"><?= $stat_tickets ?></div>
                        <div class="stat-lbl">Tiket Terbuka</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Invoice Jatuh Tempo -->
        <?php if($stat_inv > 0): ?>
        <div class="card border-0 shadow-sm mb-3" style="border-radius:8px;background:white;border:1px solid #fde8e8!important;">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="fw-bold" style="font-size:13px;color:#ef4444;"><i class="bi bi-exclamation-triangle-fill me-1"></i> Invoice Belum Lunas</span>
                    <a href="<?= base_url('hosting/billing') ?>" style="font-size:11px;color:#ef4444;">Lihat Semua</a>
                </div>
                <?php while($inv = mysqli_fetch_assoc($q_invoices)): ?>
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom" style="font-size:12px;border-color:#fde8e8!important;">
                    <div>
                        <div class="fw-medium">#<?= $inv['id'] ?></div>
                        <div class="text-muted" style="font-size:10px;">Jatuh tempo: <?= !empty($inv['due_date']) ? date('d M Y', strtotime($inv['due_date'])) : '—' ?></div>
                    </div>
                    <div class="text-end">
                        <div class="fw-bold text-danger">Rp <?= number_format($inv['amount'] ?? 0, 0, ',', '.') ?></div>
                        <a href="<?= base_url('hosting/invoice/'.$inv['id']) ?>" class="btn btn-sm" style="font-size:10px;background:#ef4444;color:#fff;border-radius:12px;padding:2px 10px;border:none;margin-top:2px;">Bayar</a>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Announcements / Pengumuman -->
        <div class="card border-0 shadow-sm" style="border-radius:8px;background:white;border:1px solid var(--border-color)!important;">
            <div class="card-body p-3">
                <div class="fw-bold mb-3 d-flex align-items-center justify-content-between">
                    <span style="font-size:13px;"><i class="bi bi-megaphone me-2" style="color:#48cae4;"></i>Pengumuman</span>
                    <span style="font-size:10px;color:var(--text-muted);" class="text-muted"><?= count($announcements) ?> pesan</span>
                </div>
                <?php if(!empty($announcements)): ?>
                    <?php foreach($announcements as $ann):
                        $icon  = $ann_icons[$ann['category']]  ?? 'bi-info-circle-fill';
                        $color = $ann_colors[$ann['category']] ?? '#3b82f6';
                    ?>
                    <div class="ann-item">
                        <div class="ann-icon" style="background:<?= $color ?>20;color:<?= $color ?>;"><i class="bi <?= $icon ?>"></i></div>
                        <div>
                            <div class="fw-semibold text-dark" style="font-size:12.5px;margin-bottom:2px;"><?= htmlspecialchars($ann['title']) ?></div>
                            <?php if(!empty($ann['content'])): ?>
                            <div class="text-muted" style="font-size:11px;line-height:1.5;"><?= htmlspecialchars(mb_substr($ann['content'], 0, 80)) ?><?= mb_strlen($ann['content']) > 80 ? '...' : '' ?></div>
                            <?php endif; ?>
                            <div style="font-size:10px;color:#aaa;margin-top:3px;"><?= date('d M Y', strtotime($ann['created_at'])) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center text-muted py-3" style="font-size:12px;">
                        <i class="bi bi-bell-slash mb-2 d-block fs-4"></i>Tidak ada pengumuman saat ini.
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div><!-- col kanan -->
</div><!-- row -->

<script>
// ─── Banner Slider JS ──────────────────────────
let bannerIdx = 0;
const bSlides = document.querySelectorAll('.banner-slide');
const bDots   = document.querySelectorAll('.banner-dot');
let bannerTimer;

function goToSlide(n) {
    bSlides[bannerIdx]?.classList.remove('active');
    bDots[bannerIdx]?.classList.remove('active');
    bannerIdx = (n + bSlides.length) % bSlides.length;
    bSlides[bannerIdx]?.classList.add('active');
    bDots[bannerIdx]?.classList.add('active');
}

function slideBanner(dir) {
    clearInterval(bannerTimer);
    goToSlide(bannerIdx + dir);
    startBannerTimer();
}

function startBannerTimer() {
    if(bSlides.length > 1) {
        bannerTimer = setInterval(() => goToSlide(bannerIdx + 1), 5000);
    }
}

startBannerTimer();
</script>

<?php include __DIR__ . '/../library/footer.php'; ?>
