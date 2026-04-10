<?php
require_once __DIR__ . '/library/admin_session.php';
if(!defined('NS1')) include __DIR__ . '/../config/database.php';

// ── Filters ──────────────────────────────────────────────────
$filter_type   = $_GET['type']   ?? '';   // registered | anonymous | bot
$filter_device = $_GET['device'] ?? '';
$filter_date   = $_GET['date']   ?? '';   // today | week | month
$search        = trim($_GET['q'] ?? '');
$page          = max(1, (int)($_GET['page'] ?? 1));
$per_page      = 40;
$offset        = ($page - 1) * $per_page;

// Date filter SQL
$date_sql = match($filter_date) {
    'today' => "AND DATE(t.created_at) = CURDATE()",
    'week'  => "AND t.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
    'month' => "AND t.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
    default => ''
};
$type_sql = match($filter_type) {
    'registered' => "AND t.is_logged = 1",
    'anonymous'  => "AND t.is_logged = 0 AND t.device_type != 'bot'",
    'bot'        => "AND t.device_type = 'bot'",
    default      => ''
};
$device_sql = $filter_device ? "AND t.device_type = '" . mysqli_real_escape_string($conn, $filter_device) . "'" : '';
$search_sql = '';
if ($search) {
    $s = mysqli_real_escape_string($conn, $search);
    $search_sql = "AND (t.ip LIKE '%$s%' OR t.country LIKE '%$s%' OR t.city LIKE '%$s%' OR t.page_url LIKE '%$s%' OR u.nama LIKE '%$s%')";
}

$wsql = "1=1 $date_sql $type_sql $device_sql $search_sql";

// Stats
$stats = mysqli_fetch_assoc(mysqli_query($conn, "SELECT
    COUNT(*) total,
    COUNT(DISTINCT ip) unique_ip,
    COUNT(DISTINCT CASE WHEN is_logged=1 THEN user_id END) reg_users,
    SUM(is_logged=0 AND device_type!='bot') anon_visitors,
    SUM(device_type='bot') bots,
    SUM(device_type='mobile') mobile_cnt,
    SUM(device_type='desktop') desktop_cnt,
    SUM(DATE(created_at)=CURDATE()) today_cnt
    FROM site_traffic"));

// Top pages
$top_pages = mysqli_fetch_all(mysqli_query($conn, "SELECT page_url, COUNT(*) cnt FROM site_traffic WHERE device_type!='bot' GROUP BY page_url ORDER BY cnt DESC LIMIT 8"), MYSQLI_ASSOC);
// Top countries
$top_countries = mysqli_fetch_all(mysqli_query($conn, "SELECT country, COUNT(*) cnt FROM site_traffic WHERE country!='' AND device_type!='bot' GROUP BY country ORDER BY cnt DESC LIMIT 6"), MYSQLI_ASSOC);
// Top referers
$top_refs = mysqli_fetch_all(mysqli_query($conn, "SELECT referer, COUNT(*) cnt FROM site_traffic WHERE referer!='' AND device_type!='bot' GROUP BY referer ORDER BY cnt DESC LIMIT 6"), MYSQLI_ASSOC);

// Traffic rows
$total_count = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM site_traffic t LEFT JOIN users u ON t.user_id=u.id WHERE $wsql"))['c'];
$total_pages = max(1, ceil($total_count / $per_page));
$rows = mysqli_fetch_all(mysqli_query($conn, "SELECT t.*, u.nama as user_nama, u.email as user_email FROM site_traffic t
    LEFT JOIN users u ON t.user_id=u.id WHERE $wsql ORDER BY t.created_at DESC LIMIT $per_page OFFSET $offset"), MYSQLI_ASSOC);

// Sparkline data (last 7 days)
$spark = mysqli_fetch_all(mysqli_query($conn, "SELECT DATE(created_at) d, COUNT(*) c FROM site_traffic WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND device_type!='bot' GROUP BY DATE(created_at) ORDER BY d"), MYSQLI_ASSOC);

$page_title = "Traffic Monitor";
include __DIR__ . '/library/header.php';
?>

<style>
.stat-mini { display:flex; align-items:center; gap:10px; padding:14px 18px; background:var(--card); border:1px solid var(--border); border-radius:10px; }
.stat-mini-icon { width:36px; height:36px; border-radius:9px; display:flex; align-items:center; justify-content:center; font-size:18px; flex-shrink:0; }
.stat-mini-val { font-size:20px; font-weight:800; line-height:1; }
.stat-mini-lbl { font-size:10px; color:var(--mut); font-weight:600; text-transform:uppercase; letter-spacing:.5px; margin-top:2px; }
.quick-badge { display:inline-flex; align-items:center; gap:3px; padding:2px 7px; border-radius:5px; font-size:10px; font-weight:700; border:1px solid; white-space:nowrap; }
.qb-ok   { color:var(--ok);   background:var(--oks); border-color:var(--ob); }
.qb-err  { color:var(--err);  background:var(--es);  border-color:#3d1a1a; }
.qb-warn { color:var(--warn); background:var(--ws);  border-color:#3d2e0a; }
.qb-muted{ color:var(--sub);  background:var(--surface); border-color:var(--border); }
.qb-acc  { color:var(--accent); background:var(--as); border-color:var(--ba); }
.qb-pur  { color:#a78bfa; background:rgba(167,139,250,.15); border-color:rgba(167,139,250,.3); }

/* Insight panels */
.insight-card { background:var(--card); border:1px solid var(--border); border-radius:10px; overflow:hidden; }
.insight-head { padding:12px 16px; border-bottom:1px solid var(--border); font-size:12px; font-weight:700; color:var(--sub); text-transform:uppercase; letter-spacing:.5px; }
.insight-row { display:flex; align-items:center; gap:10px; padding:8px 16px; border-bottom:1px solid var(--border); font-size:12px; }
.insight-row:last-child { border-bottom:none; }
.insight-bar-bg { flex:1; height:5px; border-radius:99px; background:var(--surface); overflow:hidden; }
.insight-bar-fill { height:100%; border-radius:99px; background:var(--accent); }
.insight-cnt { font-weight:700; color:var(--text); min-width:36px; text-align:right; font-size:11px; }

/* Traffic table specifics */
.ip-chip { font-family:'JetBrains Mono',monospace; font-size:11px; color:var(--accent); background:var(--as); border:1px solid var(--ba); border-radius:4px; padding:1px 6px; }
.dev-icon { font-size:16px; }
.page-url-cell { max-width:220px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-size:11px; font-family:monospace; color:var(--sub); }
.user-chip { display:inline-flex; align-items:center; gap:4px; font-size:11px; font-weight:600; color:var(--ok); background:var(--oks); border:1px solid var(--ob); border-radius:5px; padding:1px 7px; }
.anon-chip { font-size:11px; color:var(--mut); }

/* Filter tabs */
.ftab { display:inline-flex; align-items:center; gap:5px; padding:5px 12px; border-radius:7px; font-size:12px; font-weight:600; text-decoration:none; border:1px solid var(--border); color:var(--sub); background:var(--hover); transition:all .15s; }
.ftab:hover, .ftab.active { background:var(--accent); border-color:var(--accent); color:#fff; }

/* Sparkline */
.sparkline-wrap { display:flex; align-items:flex-end; gap:3px; height:40px; }
.spark-bar { flex:1; border-radius:3px 3px 0 0; background:var(--accent); opacity:.6; min-width:8px; transition:opacity .15s; }
.spark-bar:hover { opacity:1; }

/* Traffic table override (no DataTables, custom style) */
#trafficTable { border-collapse:collapse; width:100%; background:var(--card); }
#trafficTable thead th { background:var(--surface) !important; color:var(--sub); font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.4px; padding:10px 12px; border-bottom:1px solid var(--border) !important; border-top:none !important; white-space:nowrap; }
#trafficTable tbody td { padding:10px 12px; border-bottom:1px solid var(--border) !important; border-top:none !important; vertical-align:middle; color:var(--text); background:var(--card) !important; }
#trafficTable tbody tr:last-child td { border-bottom:none !important; }
#trafficTable tbody tr:hover td { background:var(--hover) !important; }
</style>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1><i class="ph-fill ph-activity me-2" style="color:var(--accent);"></i> Traffic Monitor</h1>
        <nav aria-label="breadcrumb"><ol class="breadcrumb bc">
            <li class="breadcrumb-item"><a href="<?= base_url('admin/dashboard') ?>">Admin</a></li>
            <li class="breadcrumb-item active">Traffic</li>
        </ol></nav>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <span class="quick-badge qb-ok"><i class="ph-fill ph-circle" style="font-size:8px;"></i> Live <?= $stats['today_cnt'] ?> hari ini</span>
    </div>
</div>

<!-- Stats Row -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-2">
        <div class="stat-mini">
            <div class="stat-mini-icon" style="background:var(--as);color:var(--accent);"><i class="ph-fill ph-eye"></i></div>
            <div><div class="stat-mini-val"><?= number_format($stats['total']) ?></div><div class="stat-mini-lbl">Total Hits</div></div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="stat-mini">
            <div class="stat-mini-icon" style="background:var(--oks);color:var(--ok);"><i class="ph-fill ph-users"></i></div>
            <div><div class="stat-mini-val" style="color:var(--ok);"><?= number_format($stats['unique_ip']) ?></div><div class="stat-mini-lbl">Unique IP</div></div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="stat-mini">
            <div class="stat-mini-icon" style="background:var(--as);color:var(--accent);"><i class="ph-fill ph-user-check"></i></div>
            <div><div class="stat-mini-val"><?= $stats['reg_users'] ?></div><div class="stat-mini-lbl">Terdaftar</div></div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="stat-mini">
            <div class="stat-mini-icon" style="background:var(--ws);color:var(--warn);"><i class="ph-fill ph-device-mobile"></i></div>
            <div><div class="stat-mini-val" style="color:var(--warn);"><?= $stats['mobile_cnt'] ?></div><div class="stat-mini-lbl">Mobile</div></div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="stat-mini">
            <div class="stat-mini-icon" style="background:var(--surface);color:var(--sub);"><i class="ph-fill ph-robot"></i></div>
            <div><div class="stat-mini-val" style="color:var(--sub);"><?= $stats['bots'] ?></div><div class="stat-mini-lbl">Bot</div></div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="stat-mini">
            <div class="stat-mini-icon" style="background:var(--oks);color:var(--ok);"><i class="ph-fill ph-sun"></i></div>
            <div><div class="stat-mini-val" style="color:var(--ok);"><?= $stats['today_cnt'] ?></div><div class="stat-mini-lbl">Hari Ini</div></div>
        </div>
    </div>
</div>

<!-- Sparkline + Insights -->
<div class="row g-3 mb-4">
    <div class="col-lg-5">
        <div class="insight-card p-3">
            <div style="font-size:12px;font-weight:700;color:var(--sub);text-transform:uppercase;letter-spacing:.5px;margin-bottom:12px;">
                <i class="ph ph-chart-bar me-1"></i> Traffic 7 Hari Terakhir
            </div>
            <?php
            $spark_vals = array_column($spark, 'c');
            $spark_max  = !empty($spark_vals) ? max(1, max($spark_vals)) : 1;
            // Build last 7 days array
            $spark_days = [];
            for ($i = 6; $i >= 0; $i--) {
                $d = date('Y-m-d', strtotime("-$i days"));
                $spark_days[$d] = 0;
            }
            foreach ($spark as $s) $spark_days[$s['d']] = (int)$s['c'];
            ?>
            <div class="sparkline-wrap mb-2">
                <?php foreach ($spark_days as $d => $cnt): 
                    $h = max(6, round(($cnt / $spark_max) * 40));
                ?>
                <div class="spark-bar" style="height:<?= $h ?>px;" title="<?= $d ?>: <?= $cnt ?> hits"></div>
                <?php endforeach; ?>
            </div>
            <div class="d-flex justify-content-between" style="font-size:10px;color:var(--mut);">
                <?php foreach (array_keys($spark_days) as $d): ?>
                <span><?= date('d/m', strtotime($d)) ?></span>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="insight-card h-100">
            <div class="insight-head"><i class="ph ph-file-text me-1"></i> Top Halaman</div>
            <?php 
            $max_page = max(1, max(array_column($top_pages ?: [[0,'cnt'=>1]], 'cnt')));
            foreach ($top_pages as $tp):
                $pct = round($tp['cnt']/$max_page*100);
                $label = strlen($tp['page_url']) > 30 ? '...'.substr($tp['page_url'],-28) : ($tp['page_url'] ?: '/');
            ?>
            <div class="insight-row">
                <div style="flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-family:monospace;font-size:11px;" title="<?= htmlspecialchars($tp['page_url']) ?>">
                    <?= htmlspecialchars($label) ?>
                </div>
                <div class="insight-bar-bg"><div class="insight-bar-fill" style="width:<?= $pct ?>%;"></div></div>
                <div class="insight-cnt"><?= $tp['cnt'] ?></div>
            </div>
            <?php endforeach; ?>
            <?php if(empty($top_pages)): ?><div class="insight-row" style="color:var(--mut);">Belum ada data</div><?php endif; ?>
        </div>
    </div>
    <div class="col-lg-3">
        <div class="insight-card mb-3">
            <div class="insight-head"><i class="ph ph-globe me-1"></i> Top Negara</div>
            <?php foreach($top_countries as $c): ?>
            <div class="insight-row">
                <div style="flex:1;font-size:12px;"><?= htmlspecialchars($c['country'] ?: '—') ?></div>
                <div class="insight-cnt"><?= $c['cnt'] ?></div>
            </div>
            <?php endforeach; ?>
            <?php if(empty($top_countries)): ?><div class="insight-row" style="color:var(--mut);">Belum ada data</div><?php endif; ?>
        </div>
    </div>
</div>

<!-- Filters + Table -->
<div class="card-c">
    <div class="cb" style="border-bottom:1px solid var(--border);padding-bottom:14px;">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <!-- Type filter -->
            <a href="?<?= http_build_query(array_merge($_GET, ['type'=>'', 'page'=>1])) ?>" class="ftab <?= !$filter_type ? 'active' : '' ?>">
                <i class="ph ph-list"></i> Semua <span style="opacity:.7;"><?= number_format($stats['total']) ?></span>
            </a>
            <a href="?<?= http_build_query(array_merge($_GET, ['type'=>'registered', 'page'=>1])) ?>" class="ftab <?= $filter_type=='registered' ? 'active' : '' ?>">
                <i class="ph-fill ph-user-check"></i> Terdaftar <span style="opacity:.7;"><?= $stats['reg_users'] ?></span>
            </a>
            <a href="?<?= http_build_query(array_merge($_GET, ['type'=>'anonymous', 'page'=>1])) ?>" class="ftab <?= $filter_type=='anonymous' ? 'active' : '' ?>">
                <i class="ph ph-user"></i> Anonim <span style="opacity:.7;"><?= $stats['anon_visitors'] ?></span>
            </a>
            <a href="?<?= http_build_query(array_merge($_GET, ['type'=>'bot', 'page'=>1])) ?>" class="ftab <?= $filter_type=='bot' ? 'active' : '' ?>">
                <i class="ph-fill ph-robot"></i> Bot <span style="opacity:.7;"><?= $stats['bots'] ?></span>
            </a>
            <div style="width:1px;height:20px;background:var(--border);"></div>
            <!-- Date filter -->
            <a href="?<?= http_build_query(array_merge($_GET, ['date'=>'today', 'page'=>1])) ?>" class="ftab <?= $filter_date=='today' ? 'active' : '' ?>">Hari Ini</a>
            <a href="?<?= http_build_query(array_merge($_GET, ['date'=>'week', 'page'=>1])) ?>" class="ftab <?= $filter_date=='week' ? 'active' : '' ?>">7 Hari</a>
            <a href="?<?= http_build_query(array_merge($_GET, ['date'=>'month', 'page'=>1])) ?>" class="ftab <?= $filter_date=='month' ? 'active' : '' ?>">30 Hari</a>
            <!-- Search -->
            <form method="GET" class="ms-auto d-flex gap-2" style="min-width:200px;">
                <?php foreach (['type','device','date'] as $k) if(!empty($_GET[$k])) echo "<input type='hidden' name='$k' value='".htmlspecialchars($_GET[$k])."'>"; ?>
                <input name="q" class="fc" placeholder="Cari IP, negara, halaman..." value="<?= htmlspecialchars($search) ?>" style="font-size:12px;padding:5px 10px;">
                <button class="btn btn-sm btn-outline-light"><i class="ph ph-magnifying-glass"></i></button>
            </form>
        </div>
    </div>

    <div class="cb p-0">
        <div class="table-responsive">
            <table class="table table-hover w-100" id="trafficTable" style="font-size:12px;">
                <thead>
                    <tr>
                        <th style="width:120px;">IP</th>
                        <th>Lokasi</th>
                        <th>Pengguna</th>
                        <th>Device / OS / Browser</th>
                        <th>Halaman</th>
                        <th>Referer</th>
                        <th style="width:120px;">Waktu</th>
                    </tr>
                </thead>
                <tbody>
                <?php if(empty($rows)): ?>
                <tr><td colspan="7" class="text-center py-5">
                    <div style="font-size:40px;color:var(--mut);"><i class="ph ph-chart-line-down"></i></div>
                    <div style="color:var(--sub);">Belum ada data traffic.</div>
                </td></tr>
                <?php else: ?>
                <?php foreach ($rows as $r):
                    $dev_icon = match($r['device_type']) {
                        'mobile'  => '<i class="ph-fill ph-device-mobile" style="color:var(--warn);"></i>',
                        'tablet'  => '<i class="ph-fill ph-device-tablet" style="color:var(--warn);"></i>',
                        'bot'     => '<i class="ph-fill ph-robot" style="color:var(--mut);"></i>',
                        'desktop' => '<i class="ph-fill ph-monitor" style="color:var(--accent);"></i>',
                        default   => '<i class="ph ph-question" style="color:var(--mut);"></i>',
                    };
                    $time_diff  = abs(time() - strtotime($r['created_at']));
                    $time_label = $time_diff < 60 ? $time_diff.'d lalu' : ($time_diff < 3600 ? round($time_diff/60).'m lalu' : ($time_diff < 86400 ? round($time_diff/3600).'j lalu' : date('d M H:i', strtotime($r['created_at']))));
                    $ref_label = $r['referer'] ? parse_url($r['referer'], PHP_URL_HOST) : '';
                ?>
                <tr>
                    <td><span class="ip-chip"><?= htmlspecialchars($r['ip']) ?></span></td>
                    <td>
                        <?php if($r['country'] || $r['city']): ?>
                        <div style="font-size:12px;font-weight:600;"><?= htmlspecialchars($r['city'] ?: $r['country']) ?></div>
                        <?php if($r['country'] && $r['city']): ?>
                        <div style="font-size:10px;color:var(--mut);"><?= htmlspecialchars($r['country']) ?></div>
                        <?php endif; ?>
                        <?php if($r['isp']): ?>
                        <div style="font-size:10px;color:var(--mut);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:140px;" title="<?= htmlspecialchars($r['isp']) ?>"><?= htmlspecialchars(substr($r['isp'],0,22)) ?></div>
                        <?php endif; ?>
                        <?php else: ?>
                        <span style="color:var(--mut);font-size:11px;">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if($r['is_logged'] && $r['user_nama']): ?>
                        <span class="user-chip view-user-detail" data-userid="<?= (int)$r['user_id'] ?>" style="cursor:pointer;" title="Lihat profil">
                            <i class="ph-fill ph-user-check"></i> <?= htmlspecialchars($r['user_nama']) ?>
                        </span>
                        <?php if($r['user_email']): ?>
                        <div style="font-size:10px;color:var(--mut);margin-top:2px;"><?= htmlspecialchars($r['user_email']) ?></div>
                        <?php endif; ?>
                        <?php else: ?>
                        <span class="anon-chip"><i class="ph ph-user-circle"></i> Anonim</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="d-flex align-items-center gap-1">
                            <?= $dev_icon ?> 
                            <span style="font-weight:600;"><?= htmlspecialchars($r['browser'] ?: '?') ?></span>
                        </div>
                        <div style="font-size:10px;color:var(--mut);margin-top:2px;"><?= htmlspecialchars($r['os'] ?: '') ?> · <?= htmlspecialchars(ucfirst($r['device_type'])) ?></div>
                    </td>
                    <td>
                        <div class="page-url-cell" title="<?= htmlspecialchars($r['page_url']) ?>">
                            <?= htmlspecialchars($r['page_url'] ?: '/') ?>
                        </div>
                    </td>
                    <td>
                        <?php if($ref_label): ?>
                        <span style="font-size:11px;color:var(--sub);" title="<?= htmlspecialchars($r['referer']) ?>">
                            <?= htmlspecialchars(substr($ref_label, 0, 24)) ?>
                        </span>
                        <?php else: ?>
                        <span style="color:var(--mut);font-size:11px;">Direct</span>
                        <?php endif; ?>
                    </td>
                    <td style="color:var(--mut);font-size:11px;"><?= $time_label ?></td>
                </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <?php if($total_pages > 1): ?>
        <div class="d-flex justify-content-between align-items-center p-3" style="border-top:1px solid var(--border);">
            <span style="font-size:12px;color:var(--sub);"><?= number_format($total_count) ?> record · Hal <?= $page ?> dari <?= $total_pages ?></span>
            <div class="d-flex gap-1">
                <?php if($page>1): ?><a href="?<?= http_build_query(array_merge($_GET,['page'=>$page-1])) ?>" class="pg">‹</a><?php endif; ?>
                <?php for($p=max(1,$page-2);$p<=min($total_pages,$page+2);$p++): ?>
                <a href="?<?= http_build_query(array_merge($_GET,['page'=>$p])) ?>" class="pg <?= $p==$page?'active':'' ?>"><?= $p ?></a>
                <?php endfor; ?>
                <?php if($page<$total_pages): ?><a href="?<?= http_build_query(array_merge($_GET,['page'=>$page+1])) ?>" class="pg">›</a><?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/library/footer.php'; ?>
