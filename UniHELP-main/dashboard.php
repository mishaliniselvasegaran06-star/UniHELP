<?php
require_once 'config.php';
requireLogin();

$u          = currentUser();
$pageTitle  = 'Dashboard';
$currentNav = 'dashboard';

// Fetch stats
$db = db();

$totalSessions = $db->prepare('SELECT COUNT(*) FROM sessions WHERE student_id = ? OR volunteer_id = ?');
$totalSessions->execute([$u['id'], $u['id']]);
$totalSessions = $totalSessions->fetchColumn();

$openRequests = $db->prepare('SELECT COUNT(*) FROM support_requests WHERE student_id = ? AND status = "open"');
$openRequests->execute([$u['id']]);
$openRequests = $openRequests->fetchColumn();

$upcoming = $db->prepare(
    'SELECT COUNT(*) FROM sessions
     WHERE (student_id = ? OR volunteer_id = ?) AND status = "upcoming"'
);
$upcoming->execute([$u['id'], $u['id']]);
$upcoming = $upcoming->fetchColumn();

// Recent activity
$activity = $db->prepare(
    'SELECT sr.category, sr.status, sr.created_at, sr.description
     FROM support_requests sr WHERE sr.student_id = ?
     ORDER BY sr.created_at DESC LIMIT 5'
);
$activity->execute([$u['id']]);
$recentRequests = $activity->fetchAll();

include 'includes/header.php';
?>
<meta name="csrf" content="<?= e(csrfToken()) ?>">

<div class="page-header">
    <h1>Welcome back, <?= e(explode(' ', $u['name'])[0]) ?>! 👋</h1>
    <p>Here's a summary of your UniHELP activity.</p>
</div>

<!-- ── Stat cards ────────────────────────────────────────── -->
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background:#e8edf7;">📋</div>
        <div>
            <div class="stat-value text-navy"><?= $openRequests ?></div>
            <div class="stat-label">Open Requests</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#f0fdf4;">📅</div>
        <div>
            <div class="stat-value" style="color:var(--success);"><?= $upcoming ?></div>
            <div class="stat-label">Upcoming Sessions</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#eff6ff;">✅</div>
        <div>
            <div class="stat-value text-primary"><?= $totalSessions ?></div>
            <div class="stat-label">Total Sessions</div>
        </div>
    </div>
</div>

<!-- ── Quick actions ────────────────────────────────────── -->
<div class="card card-body mb-4">
    <h3 style="margin-bottom:16px;">Quick Actions</h3>
    <div style="display:flex;gap:12px;flex-wrap:wrap;">
        <a href="requests.php"  class="btn btn-primary">📋 Submit Request</a>
        <a href="sessions.php"  class="btn btn-outline">📅 My Sessions</a>
        <a href="profile.php"   class="btn btn-outline">👤 Edit Profile</a>
        <?php if ($u['is_volunteer']): ?>
        <a href="volunteer_dashboard.php" class="btn btn-navy">🙋 My Queue</a>
        <?php else: ?>
        <a href="profile.php?tab=skills"  class="btn btn-success">🤝 Become Volunteer</a>
        <?php endif; ?>
    </div>
</div>

<!-- ── Recent requests ──────────────────────────────────── -->
<div class="card">
    <div class="card-header">Recent Support Requests</div>
    <div class="card-body" style="padding-top:0;">
        <?php if ($recentRequests): ?>
        <?php foreach ($recentRequests as $r): ?>
        <div style="padding:16px 0;border-bottom:1px solid var(--border);">
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:6px;">
                <strong style="font-family:Nunito,sans-serif;"><?= e(ucwords(str_replace('_',' ',$r['category']))) ?> Support</strong>
                <?php
                $bc = ['open'=>'badge-blue','matched'=>'badge-green','in_progress'=>'badge-orange','completed'=>'badge-slate','cancelled'=>'badge-red'];
                ?>
                <span class="badge <?= $bc[$r['status']] ?? 'badge-slate' ?>"><?= e(ucfirst($r['status'])) ?></span>
            </div>
            <p style="font-size:.85rem;color:var(--muted);margin:0;"><?= e(substr($r['description'],0,120)) ?>…</p>
            <p style="font-size:.75rem;color:#94a3b8;margin-top:4px;"><?= e(date('D, d M Y', strtotime($r['created_at']))) ?></p>
        </div>
        <?php endforeach; ?>
        <div style="padding-top:16px;">
            <a href="requests.php" class="btn btn-outline btn-sm">View All Requests →</a>
        </div>
        <?php else: ?>
        <div style="text-align:center;padding:40px 20px;">
            <div style="font-size:2.5rem;margin-bottom:12px;">📋</div>
            <p class="text-muted">You haven't submitted any support requests yet.</p>
            <a href="requests.php" class="btn btn-primary mt-3">Submit Your First Request</a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
