<?php
require_once __DIR__ . '/../library/admin_session.php';
if(!defined('NS1')) include __DIR__ . '/../../config/database.php';

$tickets_query = mysqli_query($conn, "SELECT t.*, u.nama as user_name, u.email as user_email,
    (SELECT COUNT(id) FROM ticket_replies tr WHERE tr.ticket_id = t.id) as reply_count,
    (SELECT message FROM ticket_replies tr WHERE tr.ticket_id = t.id ORDER BY id DESC LIMIT 1) as last_reply_text,
    (SELECT admin_id FROM ticket_replies tr WHERE tr.ticket_id = t.id ORDER BY id DESC LIMIT 1) as last_admin_id,
    (SELECT created_at FROM ticket_replies tr WHERE tr.ticket_id = t.id ORDER BY id DESC LIMIT 1) as last_reply_at
    FROM tickets t LEFT JOIN users u ON t.user_id = u.id ORDER BY
    CASE WHEN t.status='Customer-Reply' THEN 0 WHEN t.status='Open' THEN 1 WHEN t.status='Answered' THEN 2 ELSE 3 END ASC,
    t.id DESC");

// Stats
$tstats = mysqli_fetch_assoc(mysqli_query($conn, "SELECT
    COUNT(*) total,
    SUM(status='Open') s_open,
    SUM(status='Customer-Reply') s_reply,
    SUM(status='Answered') s_answered,
    SUM(status='Closed') s_closed
    FROM tickets"));

$page_title = "Support Tickets";
include __DIR__ . '/../library/header.php';

$active_tickets = [];
$closed_tickets = [];
if(mysqli_num_rows($tickets_query) > 0) {
    while($row = mysqli_fetch_assoc($tickets_query)) {
        if($row['status'] == 'Closed') $closed_tickets[] = $row;
        else $active_tickets[] = $row;
    }
}
?>

<style>
.quick-badge { display:inline-flex; align-items:center; gap:3px; padding:2px 7px; border-radius:5px; font-size:10px; font-weight:700; border:1px solid; white-space:nowrap; }
.qb-ok   { color:var(--ok);   background:var(--oks); border-color:var(--ob); }
.qb-err  { color:var(--err);  background:var(--es);  border-color:#3d1a1a; }
.qb-warn { color:var(--warn); background:var(--ws);  border-color:#3d2e0a; }
.qb-muted{ color:var(--sub);  background:var(--surface); border-color:var(--border); }
.qb-pur  { color:#a78bfa; background:rgba(167,139,250,.15); border-color:rgba(167,139,250,.3); }
.stat-mini { display:flex; align-items:center; gap:10px; padding:12px 16px; background:var(--card); border:1px solid var(--border); border-radius:10px; }
.stat-mini-icon { width:32px; height:32px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:16px; flex-shrink:0; }
.stat-mini-val { font-size:18px; font-weight:800; line-height:1; }
.stat-mini-lbl { font-size:10px; color:var(--mut); font-weight:600; text-transform:uppercase; letter-spacing:.5px; margin-top:1px; }

/* Ticket list row */
.tkt-row { display:flex; align-items:center; gap:14px; padding:12px 16px; border-bottom:1px solid var(--border); cursor:pointer; transition:background .15s; text-decoration:none; }
.tkt-row:last-child { border-bottom:none; }
.tkt-row:hover { background:var(--hover); }
.tkt-avatar { width:38px; height:38px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:15px; flex-shrink:0; }
.tkt-body { flex:1; min-width:0; }
.tkt-title { font-size:13px; font-weight:700; color:var(--text); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.tkt-meta { font-size:11px; color:var(--mut); margin-top:2px; display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
.tkt-preview { font-size:11.5px; color:var(--sub); margin-top:4px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:600px; }
.tkt-right { display:flex; flex-direction:column; align-items:flex-end; gap:6px; flex-shrink:0; }
.tkt-num { font-family:'JetBrains Mono',monospace; font-size:10px; color:var(--mut); background:var(--surface); border:1px solid var(--border); border-radius:4px; padding:1px 6px; }
.ftab { display:inline-flex; align-items:center; gap:5px; padding:5px 14px; border-radius:7px; font-size:12px; font-weight:600; border:none; cursor:pointer; transition:all .15s; background:var(--hover); color:var(--sub); border:1px solid var(--border); }
.ftab:hover { color:var(--text); }
.ftab.active { background:var(--accent); border-color:var(--accent); color:#fff; }
.urgent-dot { width:8px; height:8px; border-radius:50%; background:var(--warn); flex-shrink:0; animation:pulse 1.5s ease-in-out infinite; }
@keyframes pulse { 0%,100%{opacity:1;} 50%{opacity:.4;} }
</style>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1><i class="ph-fill ph-headset me-2" style="color:var(--accent);"></i> Support Tickets</h1>
        <nav aria-label="breadcrumb"><ol class="breadcrumb bc">
            <li class="breadcrumb-item"><a href="<?= base_url('admin/dashboard') ?>">Admin</a></li>
            <li class="breadcrumb-item active">Tickets</li>
        </ol></nav>
    </div>
    <?php if($tstats['s_reply'] > 0): ?>
    <div class="quick-badge qb-warn" style="padding:6px 14px;font-size:12px;">
        <div class="urgent-dot me-1"></div> <?= $tstats['s_reply'] ?> Butuh Balasan!
    </div>
    <?php endif; ?>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="stat-mini">
            <div class="stat-mini-icon" style="background:var(--as);color:var(--accent);"><i class="ph-fill ph-ticket"></i></div>
            <div><div class="stat-mini-val"><?= $tstats['total'] ?></div><div class="stat-mini-lbl">Total Tiket</div></div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-mini">
            <div class="stat-mini-icon" style="background:rgba(167,139,250,.15);color:#a78bfa;"><i class="ph-fill ph-chat-circle"></i></div>
            <div><div class="stat-mini-val" style="color:#a78bfa;"><?= $tstats['s_open'] ?></div><div class="stat-mini-lbl">Open</div></div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-mini">
            <div class="stat-mini-icon" style="background:var(--ws);color:var(--warn);"><i class="ph-fill ph-arrow-bend-double-up-right"></i></div>
            <div><div class="stat-mini-val" style="color:var(--warn);"><?= $tstats['s_reply'] ?></div><div class="stat-mini-lbl">Menunggu Balas</div></div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-mini">
            <div class="stat-mini-icon" style="background:var(--oks);color:var(--ok);"><i class="ph-fill ph-check-circle"></i></div>
            <div><div class="stat-mini-val" style="color:var(--ok);"><?= $tstats['s_answered'] ?></div><div class="stat-mini-lbl">Dijawab</div></div>
        </div>
    </div>
</div>

<!-- Filter Tabs -->
<div class="d-flex gap-2 mb-3 align-items-center">
    <button class="ftab active" data-tab="active">
        <i class="ph-fill ph-circle" style="font-size:8px;color:var(--warn);"></i> Berjalan
        <span style="opacity:.7;"><?= count($active_tickets) ?></span>
    </button>
    <button class="ftab" data-tab="closed">
        <i class="ph ph-archive"></i> Arsip
        <span style="opacity:.7;"><?= count($closed_tickets) ?></span>
    </button>
    <div class="ms-auto" style="position:relative;">
        <i class="ph ph-magnifying-glass" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--mut);font-size:14px;"></i>
        <input type="text" id="searchTickets" class="fc" placeholder="Cari tiket, user, dept..." style="padding-left:30px;font-size:12px;width:220px;">
    </div>
</div>

<div class="card-c">
    <!-- Tab Active -->
    <div id="tab-active" class="ticket-tab">
        <?php if(empty($active_tickets)): ?>
        <div class="text-center py-5">
            <div style="font-size:48px;color:var(--ok);opacity:.6;margin-bottom:12px;"><i class="ph-fill ph-check-circle"></i></div>
            <div style="font-weight:700;color:var(--text);">Luar Biasa!</div>
            <div style="font-size:13px;color:var(--mut);margin-top:4px;">Tidak ada tiket aktif saat ini.</div>
        </div>
        <?php else: ?>
        <?php foreach($active_tickets as $row): 
            $clean = strip_tags(str_replace(['<br>','</p>'], ' ', $row['last_reply_text'] ?? ''));
            $clean = trim(preg_replace('/\s+/', ' ', $clean)) ?: 'Belum ada balasan';
            $initial = strtoupper(substr($row['user_name'] ?? 'U', 0, 1));
            $is_urgent = ($row['status'] === 'Customer-Reply');
            $status_badge = match($row['status']) {
                'Open'            => '<span class="quick-badge qb-pur"><i class="ph-fill ph-circle" style="font-size:6px;"></i> OPEN</span>',
                'Customer-Reply'  => '<span class="quick-badge qb-warn"><i class="ph-fill ph-arrow-fat-up" style="font-size:9px;"></i> REPLY</span>',
                'Answered'        => '<span class="quick-badge qb-ok"><i class="ph-fill ph-check"></i> ANSWERED</span>',
                default           => '<span class="quick-badge qb-muted">—</span>',
            };
            $sender_label = $row['last_admin_id']
                ? '<span style="color:var(--accent);font-weight:600;"><i class="ph-fill ph-headset"></i> Support:</span>'
                : '<span style="color:var(--sub);font-weight:600;"><i class="ph-fill ph-user"></i> Client:</span>';
            $time_since = $row['last_reply_at'] ? human_time(strtotime($row['last_reply_at'])) : human_time(strtotime($row['created_at']));
        ?>
        <a href="<?= base_url('admin/tickets/detail/' . $row['id']) ?>" class="tkt-row ticket-item" style="<?= $is_urgent ? 'border-left:3px solid var(--warn);' : '' ?>">
            <div class="tkt-avatar" style="background:<?= $is_urgent ? 'var(--ws)' : 'var(--as)' ?>;color:<?= $is_urgent ? 'var(--warn)' : 'var(--accent)' ?>;"><?= $initial ?></div>
            <div class="tkt-body">
                <div class="tkt-title"><?= htmlspecialchars($row['title']) ?></div>
                <div class="tkt-meta">
                    <span><i class="ph ph-user me-1"></i><?= htmlspecialchars($row['user_name']) ?></span>
                    <span><i class="ph ph-tag me-1"></i><?= htmlspecialchars($row['department']) ?></span>
                    <span><i class="ph ph-chat-circle me-1"></i><?= $row['reply_count'] ?> balasan</span>
                    <span><i class="ph ph-clock me-1"></i><?= $time_since ?></span>
                </div>
                <div class="tkt-preview"><?= $sender_label ?> <?= htmlspecialchars(substr($clean, 0, 120)) ?></div>
            </div>
            <div class="tkt-right">
                <div class="tkt-num"><?= $row['ticket_number'] ?></div>
                <?= $status_badge ?>
                <?php if($is_urgent): ?><div class="urgent-dot"></div><?php endif; ?>
            </div>
        </a>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Tab Closed -->
    <div id="tab-closed" class="ticket-tab" style="display:none;">
        <?php if(empty($closed_tickets)): ?>
        <div class="text-center py-5">
            <div style="font-size:40px;color:var(--mut);margin-bottom:10px;"><i class="ph ph-archive"></i></div>
            <div style="color:var(--sub);">Belum ada tiket yang diarsipkan.</div>
        </div>
        <?php else: ?>
        <?php foreach($closed_tickets as $row):
            $initial = strtoupper(substr($row['user_name'] ?? 'U', 0, 1));
            $clean = strip_tags(str_replace(['<br>','</p>'], ' ', $row['last_reply_text'] ?? ''));
            $clean = trim(preg_replace('/\s+/', ' ', $clean)) ?: '—';
        ?>
        <a href="<?= base_url('admin/tickets/detail/' . $row['id']) ?>" class="tkt-row ticket-item" style="opacity:.65;">
            <div class="tkt-avatar" style="background:var(--surface);color:var(--mut);"><?= $initial ?></div>
            <div class="tkt-body">
                <div class="tkt-title" style="color:var(--sub);"><?= htmlspecialchars($row['title']) ?></div>
                <div class="tkt-meta">
                    <span><?= htmlspecialchars($row['user_name']) ?></span>
                    <span><?= htmlspecialchars($row['department']) ?></span>
                    <span><?= date('d M Y', strtotime($row['created_at'])) ?></span>
                </div>
            </div>
            <div class="tkt-right">
                <div class="tkt-num"><?= $row['ticket_number'] ?></div>
                <span class="quick-badge qb-muted">CLOSED</span>
            </div>
        </a>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php
function human_time(int $ts): string {
    $diff = time() - $ts;
    if($diff < 60) return $diff.'s lalu';
    if($diff < 3600) return round($diff/60).'m lalu';
    if($diff < 86400) return round($diff/3600).'j lalu';
    return date('d M', $ts);
}
?>

<script>
const tabs = document.querySelectorAll('.ftab');
tabs.forEach(tab => {
    tab.addEventListener('click', () => {
        tabs.forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        document.querySelectorAll('.ticket-tab').forEach(t => t.style.display = 'none');
        document.getElementById('tab-' + tab.dataset.tab).style.display = '';
    });
});

document.getElementById('searchTickets').addEventListener('keyup', function() {
    const val = this.value.toLowerCase();
    document.querySelectorAll('.ticket-item').forEach(item => {
        item.style.display = item.innerText.toLowerCase().includes(val) ? '' : 'none';
    });
});
</script>

<?php include __DIR__ . '/../library/footer.php'; ?>
