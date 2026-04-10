<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/api_helper.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (isset($_SESSION['user_id'])) { header("Location: /hosting"); exit(); }

$_site_name_db = 'SobatHosting';
$_site_logo_db = '';
$r_set = @mysqli_query($conn, "SELECT site_name, site_logo FROM settings LIMIT 1");
if ($r_set && $row = mysqli_fetch_assoc($r_set)) {
    if (!empty($row['site_name'])) $_site_name_db = htmlspecialchars($row['site_name']);
    if (!empty($row['site_logo'])) $_site_logo_db = htmlspecialchars($row['site_logo']);
}

// Load all CMS data
$cms = [];
$q = mysqli_query($conn, "SELECT * FROM landing_content");
if ($q) { while ($r = mysqli_fetch_assoc($q)) { $cms[$r['section_name']] = json_decode($r['content_json'], true); } }

$hero    = $cms['hero']    ?? ['heading'=>'#ThinkBig #GrowBigger','subheading'=>'Onlinekan Bisnismu','description'=>'Hosting terbaik Indonesia.','button_text'=>'MULAI','image_url'=>'https://www.rumahweb.com/assets/img/hero/orang-rumahweb-v2.webp'];
$navbar  = $cms['navbar']  ?? ['site_name'=>'sobathosting','tagline'=>'Painless hosting solution','logo_url'=>'','nav_links'=>[]];
$dombar  = $cms['domain_bar'] ?? ['title'=>'Website beken dari domain keren','tlds'=>[]];
$trust   = $cms['trust']   ?? ['title'=>'Lebih dari 100.000 pelanggan memilih kami','logos'=>[]];
$feats   = $cms['features'] ?? [];
$styl    = $cms['styles']  ?? ['color_primary'=>'#0d6efd','color_secondary'=>'#0dcaf0','color_accent'=>'#f39c12','color_domain_bar'=>'#8cc63f','font_family'=>"'Montserrat', sans-serif"];
$ft      = $cms['footer']  ?? ['company_name'=>'sobathosting','description'=>'Hosting terbaik.','email'=>'support@sobathosting.com','phone'=>'+62 812 3456 789'];

$cp = htmlspecialchars($styl['color_primary'] ?? '#0d6efd');
$cs = htmlspecialchars($styl['color_secondary'] ?? '#0dcaf0');
$ca = htmlspecialchars($styl['color_accent'] ?? '#f39c12');
$cd = htmlspecialchars($styl['color_domain_bar'] ?? '#8cc63f');
$ff = htmlspecialchars($styl['font_family'] ?? "'Montserrat', sans-serif");

// Smart icon renderer: supports fa-*, ti-*, lucide:*, material:*
function renderLandingIcon(string $icon, string $extraClass = ''): string {
    $icon = htmlspecialchars(trim($icon));
    if (empty($icon)) return '<i class="fa-solid fa-star ' . $extraClass . '"></i>';
    if (str_starts_with($icon, 'lucide:')) {
        $name = substr($icon, 7);
        return "<i data-lucide=\"{$name}\" class=\"{$extraClass}\" style=\"width:1em;height:1em;\"></i>";
    } elseif (str_starts_with($icon, 'material:')) {
        $name = substr($icon, 9);
        return "<span class=\"material-symbols-outlined {$extraClass}\" style=\"font-size:inherit;\">{$name}</span>";
    } elseif (str_starts_with($icon, 'ti-') || str_starts_with($icon, 'ti ')) {
        $cls = preg_replace('/^ti-/', 'ti-', $icon);
        return "<i class=\"ti {$cls} {$extraClass}\"></i>";
    } else {
        // FontAwesome: fa-server, fa-brands fa-google, etc.
        if (str_contains($icon, 'fa-brands')) return "<i class=\"{$icon} {$extraClass}\"></i>";
        $cls = preg_replace('/^(fa-solid|fa-regular)\s+/', '', $icon);
        return "<i class=\"fa-solid {$cls} {$extraClass}\"></i>";
    }
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $_site_name_db ?> — <?= htmlspecialchars($hero['subheading'] ?? '') ?></title>
    <?php if(!empty($_site_logo_db)): ?>
    <link rel="icon" href="/uploads/<?= $_site_logo_db ?>" type="image/x-icon">
    <?php endif; ?>
    <meta name="description" content="<?= htmlspecialchars($hero['description'] ?? '') ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Icon Libraries: FontAwesome, Tabler, Material Symbols -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.3.0/dist/tabler-icons.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap">
    <style>
        :root {
            --cp: <?= $cp ?>;
            --cs: <?= $cs ?>;
            --ca: <?= $ca ?>;
            --cd: <?= $cd ?>;
        }
        *, *::before, *::after { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body { font-family: <?= $ff ?>; background: #fff; color: #2d3436; margin: 0; }

        /* ─── NAVBAR ─── */
        .lp-nav {
            background: linear-gradient(90deg, var(--cp) 0%, var(--cs) 100%);
            padding: 0; position: sticky; top: 0; z-index: 999;
            box-shadow: 0 2px 20px rgba(0,0,0,0.15);
        }
        .lp-nav .container { display: flex; align-items: center; height: 68px; gap: 24px; }
        .lp-brand { display: flex; align-items: center; gap: 10px; text-decoration: none; flex-shrink: 0; }
        .lp-brand-icon { font-size: 2rem; color: white; }
        .lp-brand-text { line-height: 1; }
        .lp-brand-name { font-size: 1.3rem; font-weight: 900; color: white; letter-spacing: -0.5px; }
        .lp-brand-tag { font-size: 0.6rem; font-weight: 500; color: rgba(255,255,255,0.8); margin-top: 1px; }
        .lp-nav-links { display: flex; align-items: center; gap: 4px; margin-left: auto; }
        .lp-nav-link { color: rgba(255,255,255,0.9); text-decoration: none; font-weight: 600; font-size: 0.85rem; padding: 8px 14px; border-radius: 6px; transition: all 0.2s; white-space: nowrap; }
        .lp-nav-link:hover { background: rgba(255,255,255,0.15); color: white; }
        .lp-nav-link.highlight { color: #fff176; }
        .btn-login { border: 1.5px solid rgba(255,255,255,0.7); color: white; border-radius: 6px; padding: 7px 18px; font-weight: 700; font-size: 0.82rem; text-decoration: none; transition: all 0.2s; white-space: nowrap; }
        .btn-login:hover { background: white; color: var(--cp); }

        /* ─── HERO ─── */
        .hero-section {
            background: linear-gradient(135deg, var(--cp) 0%, var(--cs) 100%);
            padding: 80px 0 160px;
            color: white;
            overflow: hidden;
            position: relative;
        }
        .hero-section::after {
            content: '';
            position: absolute;
            bottom: -2px; left: 0; right: 0;
            height: 60px;
            background: white;
            clip-path: ellipse(55% 100% at 50% 100%);
        }
        .hero-title { font-size: clamp(2rem, 5vw, 3.5rem); font-weight: 900; letter-spacing: -1.5px; line-height: 1.1; margin-bottom: 16px; }
        .hero-sub   { font-size: clamp(1rem, 2vw, 1.25rem); font-weight: 500; opacity: 0.95; margin-bottom: 14px; }
        .hero-desc  { font-size: 0.9rem; line-height: 1.7; opacity: 0.9; margin-bottom: 32px; max-width: 520px; }
        .btn-cta { background: var(--ca); color: white; font-weight: 800; border-radius: 8px; padding: 14px 34px; font-size: 1.05rem; border: none; cursor: pointer; transition: all 0.25s; text-decoration: none; display: inline-flex; align-items: center; gap: 10px; }
        .btn-cta:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(0,0,0,0.2); color: white; filter: brightness(1.1); }
        .hero-img { width: 100%; max-height: 460px; object-fit: contain; filter: drop-shadow(0 20px 40px rgba(0,0,0,0.2)); position: relative; z-index: 1; }

        /* ─── DOMAIN BAR ─── */
        .domain-bar {
            background: var(--cd);
            padding: 36px 0 40px;
            margin-top: -100px;
            position: relative;
            z-index: 10;
        }
        .domain-bar-title { color: white; font-weight: 800; font-size: 1.4rem; line-height: 1.2; }
        .domain-search { background: white; border-radius: 10px; padding: 6px; display: flex; box-shadow: 0 8px 24px rgba(0,0,0,0.15); }
        .domain-search input { flex: 1; border: none; padding: 12px 18px; font-size: 1rem; outline: none; border-radius: 8px; font-family: inherit; }
        .domain-search .btn-search { background: var(--ca); color: white; border: none; border-radius: 7px; padding: 12px 28px; font-weight: 800; font-size: 0.9rem; cursor: pointer; transition: 0.2s; white-space: nowrap; }
        .domain-search .btn-search:hover { filter: brightness(1.1); }
        .tld-chips { display: flex; gap: 14px; flex-wrap: wrap; margin-top: 16px; }
        .tld-chip { color: rgba(255,255,255,0.95); }
        .tld-chip strong { font-size: 1.05rem; font-weight: 800; }
        .tld-chip small { font-size: 0.75rem; font-weight: 500; display: block; opacity: 0.85; }

        /* ─── FEATURES GRID ─── */
        .features-section { padding: 60px 0 80px; background: #f8faff; }
        .feature-card {
            background: white; border-radius: 16px; padding: 30px 24px; text-align: center;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06); height: 100%; transition: all 0.3s; border: 1px solid #eef2ff;
        }
        .feature-card:hover { transform: translateY(-8px); box-shadow: 0 16px 40px rgba(0,0,0,0.12); border-color: var(--cp); }
        .feature-icon-wrap { width: 64px; height: 64px; background: linear-gradient(135deg, var(--cp), var(--cs)); border-radius: 18px; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; color: white; font-size: 1.7rem; box-shadow: 0 6px 16px rgba(0,0,0,0.15); }
        .feature-title { font-weight: 800; font-size: 1rem; color: #1e272e; margin-bottom: 10px; }
        .feature-desc { color: #636e72; font-size: 0.8rem; line-height: 1.6; }

        /* ─── TRUST SECTION ─── */
        .trust-section { background: linear-gradient(135deg, var(--cp), var(--cs)); padding: 70px 0; color: white; text-align: center; }
        .trust-title { font-size: clamp(1.4rem, 3vw, 2.1rem); font-weight: 900; margin-bottom: 50px; text-shadow: 0 2px 6px rgba(0,0,0,0.15); }
        .trust-title::after { content: ''; display: block; width: 60px; height: 4px; background: var(--ca); border-radius: 99px; margin: 16px auto 0; }
        .trust-logos { display: flex; justify-content: center; gap: 36px; flex-wrap: wrap; }
        .trust-item { display: flex; flex-direction: column; align-items: center; gap: 10px; }
        .trust-icon { width: 72px; height: 72px; background: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2rem; color: var(--cp); box-shadow: 0 6px 20px rgba(0,0,0,0.15); transition: transform 0.2s; }
        .trust-icon:hover { transform: scale(1.1); }
        .trust-item span { font-weight: 700; font-size: 0.75rem; line-height: 1.3; max-width: 90px; }

        /* ─── PRICING CARDS ─── */
        .pricing-section { padding: 80px 0; background: white; }
        .pricing-title { text-align: center; font-size: clamp(1.5rem, 3vw, 2.2rem); font-weight: 900; color: #2d3436; margin-bottom: 50px; }
        .pricing-card { border: 2px solid #edf2f7; border-radius: 16px; padding: 32px 28px; height: 100%; transition: all 0.3s; background: white; }
        .pricing-card:hover { border-color: var(--cp); box-shadow: 0 12px 35px rgba(0,0,0,0.1); transform: translateY(-4px); }
        .pricing-card.featured { background: linear-gradient(145deg, var(--cp), var(--cs)); border-color: transparent; color: white; }
        .pricing-card.featured .text-muted, .pricing-card.featured .feature-list-item { color: rgba(255,255,255,0.8) !important; }
        .pricing-badge { font-size: 10px; font-weight: 700; padding: 3px 10px; border-radius: 99px; background: rgba(255,255,255,0.2); color: white; display: inline-block; margin-bottom: 12px; }
        .pricing-name { font-size: 1.3rem; font-weight: 800; margin-bottom: 4px; }
        .pricing-price { font-size: 2.2rem; font-weight: 900; margin: 16px 0 4px; letter-spacing: -1px; }
        .pricing-period { font-size: 0.85rem; font-weight: 500; opacity: 0.7; }
        .pricing-divider { border-color: rgba(255,255,255,0.2); margin: 20px 0; }
        .pricing-divider.dark { border-color: #edf2f7; }
        .feature-list-item { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; font-size: 0.85rem; color: #636e72; }
        .feature-list-item i { color: #10b981; flex-shrink: 0; }
        .pricing-card.featured .feature-list-item i { color: rgba(255,255,255,0.9); }
        .btn-pricing { display: block; text-align: center; padding: 13px; border-radius: 10px; font-weight: 800; font-size: 0.95rem; text-decoration: none; transition: 0.2s; margin-top: 24px; }
        .btn-pricing-outline { border: 2px solid var(--cp); color: var(--cp); background: none; }
        .btn-pricing-outline:hover { background: var(--cp); color: white; }
        .btn-pricing-solid { background: white; color: var(--cp); }
        .btn-pricing-solid:hover { background: rgba(255,255,255,0.9); }

        /* ─── FOOTER ─── */
        .lp-footer { background: #1a1d27; color: #adb5bd; padding: 60px 0 24px; }
        .footer-name { font-size: 1.5rem; font-weight: 900; color: white; margin-bottom: 12px; }
        .footer-desc { font-size: 0.85rem; line-height: 1.7; max-width: 320px; }
        .footer-link { color: #adb5bd; text-decoration: none; display: block; margin-bottom: 8px; font-size: 0.85rem; transition: color 0.2s; }
        .footer-link:hover { color: white; }
        .footer-divider { border-color: rgba(255,255,255,0.07); margin: 40px 0 20px; }
        .footer-copy { text-align: center; font-size: 0.78rem; color: rgba(255,255,255,0.35); }

        /* Domain check result */
        .domain-result { padding: 12px 16px; border-radius: 8px; margin-top: 12px; font-size: 0.9rem; }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="lp-nav">
    <div class="container">
        <a href="/" class="lp-brand">
            <?php if(!empty($navbar['logo_url'])): ?>
                <img src="<?= htmlspecialchars($navbar['logo_url']) ?>" alt="Logo" style="height: 38px;">
            <?php else: ?>
                <i class="fa-solid fa-cloud-bolt lp-brand-icon"></i>
            <?php endif; ?>
            <div class="lp-brand-text">
                <div class="lp-brand-name"><?= htmlspecialchars($navbar['site_name'] ?? 'sobathosting') ?></div>
                <div class="lp-brand-tag"><?= htmlspecialchars($navbar['tagline'] ?? '') ?></div>
            </div>
        </a>
        
        <div class="lp-nav-links d-none d-lg-flex">
            <?php foreach(($navbar['nav_links'] ?? []) as $link): ?>
            <a href="<?= htmlspecialchars($link['url'] ?? '#') ?>" class="lp-nav-link"><?= htmlspecialchars($link['label'] ?? '') ?></a>
            <?php endforeach; ?>
        </div>
        
        <div class="ms-auto ms-lg-2 d-flex gap-2">
            <a href="/auth/login" class="btn-login">Log In</a>
            <a href="/auth/register" class="btn-login" style="background: rgba(255,255,255,0.2)">Daftar</a>
        </div>
    </div>
</nav>

<!-- HERO -->
<section class="hero-section">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6" data-aos="fade-right">
                <h1 class="hero-title"><?= htmlspecialchars($hero['heading'] ?? '') ?></h1>
                <div class="hero-sub"><?= htmlspecialchars($hero['subheading'] ?? '') ?></div>
                <p class="hero-desc"><?= nl2br(htmlspecialchars($hero['description'] ?? '')) ?></p>
                <a href="#hosting" class="btn-cta"><?= htmlspecialchars($hero['button_text'] ?? 'MULAI') ?> <i class="fa-solid fa-arrow-right"></i></a>
            </div>
            <div class="col-lg-6 text-center d-none d-lg-block" data-aos="fade-left">
                <?php if(!empty($hero['image_url'])): ?>
                <img src="<?= htmlspecialchars($hero['image_url']) ?>" alt="Hero Illustration" class="hero-img">
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>


<!-- FEATURES -->
<?php if(!empty($feats)): ?>
<section class="features-section">
    <div class="container">
        <div class="row g-4 justify-content-center">
            <?php foreach($feats as $f): ?>
            <div class="col-md-3 col-6">
                <div class="feature-card">
                    <div class="feature-icon-wrap"><?= renderLandingIcon($f['icon'] ?? 'fa-star') ?></div>
                    <div class="feature-title"><?= htmlspecialchars($f['title'] ?? '') ?></div>
                    <div class="feature-desc"><?= htmlspecialchars($f['description'] ?? '') ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- TRUST -->
<?php if(!empty($trust['logos'])): ?>
<section class="trust-section">
    <div class="container">
        <div class="trust-title"><?= htmlspecialchars($trust['title'] ?? '') ?></div>
        <div class="trust-logos">
            <?php foreach($trust['logos'] as $l): ?>
            <div class="trust-item">
                <div class="trust-icon"><?= renderLandingIcon($l['icon'] ?? 'fa-check') ?></div>
                <span><?= htmlspecialchars($l['name'] ?? '') ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- HOSTING PLANS -->
<section class="pricing-section" id="hosting">
    <div class="container">
        <h2 class="pricing-title">Pilih Paket <span style="color: var(--cp);">Cloud Hosting</span></h2>
        <div class="row g-4 justify-content-center">
        <?php
        $plans_q = mysqli_query($conn, "SELECT * FROM hosting_plans ORDER BY harga_per_bulan ASC");
        $plan_idx = 0;
        while($h = mysqli_fetch_assoc($plans_q)):
            $plan_idx++;
            $is_featured = ($plan_idx == 2);
        ?>
            <div class="col-md-4">
                <div class="pricing-card <?= $is_featured ? 'featured' : '' ?>">
                    <?php if($is_featured): ?>
                    <div class="pricing-badge">⭐ Best Seller</div>
                    <?php endif; ?>
                    <div class="pricing-name"><?= htmlspecialchars($h['nama_paket']) ?></div>
                    <div class="text-muted" style="font-size: 0.82rem; <?= $is_featured ? 'color: rgba(255,255,255,0.7) !important;' : '' ?>">Cocok untuk startup & bisnis</div>
                    <div class="pricing-price">Rp <?= number_format($h['harga_per_bulan'],0,',','.') ?></div>
                    <div class="pricing-period">/bulan</div>
                    <hr class="pricing-divider <?= !$is_featured ? 'dark' : '' ?>">
                    <div class="feature-list-item"><i class="fa-solid fa-check-circle"></i> <?= htmlspecialchars($h['disk_limit']) ?> NVMe SSD</div>
                    <div class="feature-list-item"><i class="fa-solid fa-check-circle"></i> <?= htmlspecialchars($h['bandwidth_limit']) ?> Bandwidth</div>
                    <div class="feature-list-item"><i class="fa-solid fa-check-circle"></i> Free SSL & Daily Backup</div>
                    <div class="feature-list-item"><i class="fa-solid fa-check-circle"></i> LiteSpeed Web Server</div>
                    <a href="<?= base_url('hosting/order/' . $h['id']) ?>" class="btn-pricing <?= $is_featured ? 'btn-pricing-solid' : 'btn-pricing-outline' ?>">Pesan Sekarang</a>
                </div>
            </div>
        <?php endwhile; ?>
        </div>
    </div>
</section>

<!-- FOOTER -->
<footer class="lp-footer">
    <div class="container">
        <div class="row">
            <div class="col-md-4 mb-4 mb-md-0">
                <div class="footer-name"><?= htmlspecialchars($ft['company_name'] ?? 'sobathosting') ?></div>
                <p class="footer-desc"><?= htmlspecialchars($ft['description'] ?? '') ?></p>
            </div>
            <div class="col-md-4 mb-4 mb-md-0">
                <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: rgba(255,255,255,0.4); margin-bottom: 16px;">Layanan</div>
                <a href="#hosting" class="footer-link">Cloud Hosting</a>
                <a href="#" class="footer-link">Domain Registration</a>
                <a href="#" class="footer-link">VPS Server</a>
            </div>
            <div class="col-md-4">
                <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: rgba(255,255,255,0.4); margin-bottom: 16px;">Bantuan</div>
                <a href="mailto:<?= htmlspecialchars($ft['email'] ?? '') ?>" class="footer-link"><i class="fa-solid fa-envelope me-2" style="color: var(--ca);"></i><?= htmlspecialchars($ft['email'] ?? '') ?></a>
                <a href="https://wa.me/<?= preg_replace('/\D/','',$ft['phone']??'') ?>" class="footer-link"><i class="fa-brands fa-whatsapp me-2" style="color: #25d366;"></i><?= htmlspecialchars($ft['phone'] ?? '') ?></a>
            </div>
        </div>
        <hr class="footer-divider">
        <div class="footer-copy">&copy; <?= date('Y') ?> <?= htmlspecialchars($ft['company_name'] ?? 'SobatHosting') ?>. All Rights Reserved.</div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Lucide & Feather JS Icon Libraries -->
<script src="https://unpkg.com/lucide@latest"></script>
<script src="https://unpkg.com/feather-icons/dist/feather.min.js"></script>
<script>
    // Initialize JS-based icon libs
    if(typeof lucide !== 'undefined') lucide.createIcons();
    if(typeof feather !== 'undefined') feather.replace();
</script>
</body>
</html>
