<?php
require_once 'config.php';
requireLogin();

$u    = currentUser();
$db   = db();
$step = 'form';  // 'form' | 'results'

/* ── Handle request submission ───────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search'])) {
    verifyCsrf();
    $category = $_POST['category'] ?? '';
    $desc     = trim($_POST['description'] ?? '');
    $pdt      = $_POST['preferred_datetime'] ?? null;
    $mode     = in_array($_POST['mode'] ?? '', ['Virtual','F2F']) ? $_POST['mode'] : 'Virtual';

    if ($category && $desc) {
        // Save the request
        $stmt = $db->prepare(
            'INSERT INTO support_requests (student_id, category, description, preferred_datetime, mode)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$u['id'], $category, $desc, $pdt ?: null, $mode]);
        $_SESSION['last_request_id'] = $db->lastInsertId();
        $_SESSION['search_params']   = compact('category','mode');
        $step = 'results';
    }
}

// If coming back to results page
if (isset($_SESSION['search_params']) && isset($_GET['view']) && $_GET['view'] === 'results') {
    $step = 'results';
}

/* ── Load volunteers (for results) ───────────────────────── */
$volunteers = [];
if ($step === 'results') {
    $params = $_SESSION['search_params'] ?? [];
    $catMap  = ['academic'=>'academic','technology'=>'technology','new_student'=>'new_student','general'=>'general'];
    $skillType = $catMap[$params['category'] ?? ''] ?? null;

    $sql = 'SELECT DISTINCT u.id, u.full_name, u.photo,
                   GROUP_CONCAT(DISTINCT vs.skill_type ORDER BY vs.skill_type SEPARATOR ",") AS skills,
                   GROUP_CONCAT(DISTINCT CONCAT(va.day_name," ",va.time_slot) ORDER BY va.day_name, va.time_slot SEPARATOR ", ") AS avail_text,
                   (SELECT AVG(f.rating) FROM feedback f
                    JOIN sessions s ON f.session_id = s.id
                    WHERE s.volunteer_id = u.id) AS avg_rating,
                   (SELECT COUNT(*) FROM sessions s2 WHERE s2.volunteer_id = u.id AND s2.status="completed") AS session_count
            FROM users u
            JOIN volunteer_skills vs ON vs.user_id = u.id
            LEFT JOIN volunteer_availability va ON va.user_id = u.id
            WHERE u.is_volunteer = 1 AND u.id != ?';

    $execParams = [$u['id']];

    if ($skillType) {
        $sql .= ' AND vs.skill_type = ?';
        $execParams[] = $skillType;
    }

    $sql .= ' GROUP BY u.id ORDER BY avg_rating DESC, session_count DESC';

    $stmt = $db->prepare($sql);
    $stmt->execute($execParams);
    $volunteers = $stmt->fetchAll();
}

/* ── Select volunteer → create session ───────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['select_volunteer'])) {
    verifyCsrf();
    $volId     = (int) $_POST['volunteer_id'];
    $reqId     = (int) ($_SESSION['last_request_id'] ?? 0);
    $schedDt   = $_POST['scheduled_datetime'] ?? date('Y-m-d H:i:s', strtotime('+2 days'));

    if ($volId && $reqId) {
        $db->prepare(
            'INSERT INTO sessions (request_id, volunteer_id, student_id, scheduled_datetime, mode)
             VALUES (?, ?, ?, ?, ?)'
        )->execute([$reqId, $volId, $u['id'], $schedDt,
                    $_SESSION['search_params']['mode'] ?? 'Virtual']);

        $db->prepare('UPDATE support_requests SET status = "matched" WHERE id = ?')->execute([$reqId]);
        unset($_SESSION['search_params'], $_SESSION['last_request_id']);
        setFlash('success', 'Volunteer selected! Check your sessions for the booking details.');
        redirect('sessions.php');
    }
}

$pageTitle  = 'Submit Request';
$currentNav = 'requests';
include 'includes/header.php';
?>
<meta name="csrf" content="<?= e(csrfToken()) ?>">

<?php if ($step === 'form'): ?>
<!-- ══ Request Form ════════════════════════════════════════ -->
<div class="page-header">
    <h1>Submit Support Request</h1>
    <p>Tell us what kind of help you need and we'll find the right volunteer.</p>
</div>

<div style="max-width:680px;">
<div class="card">
    <div class="card-header">New Support Request</div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="csrf"   value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="search" value="1">

            <div class="form-group">
                <label>Support Category <span class="req">*</span></label>
                <select class="form-control" name="category" required>
                    <option value="">Select a support category…</option>
                    <option value="academic">📚 Academic Support</option>
                    <option value="technology">💻 Technology Support</option>
                    <option value="new_student">🗺 New Student Support</option>
                    <option value="general">💬 General Support</option>
                </select>
            </div>

            <div class="form-group">
                <label>Description <span class="req">*</span></label>
                <textarea class="form-control" name="description"
                          rows="4" maxlength="500" required
                          placeholder="Describe what you need help with in as much detail as possible…"
                          data-maxlength="500" data-counter="descCounter"></textarea>
                <div class="form-hint" style="text-align:right;">
                    <span id="descCounter">0/500</span> characters
                </div>
            </div>

            <div class="row">
                <div class="col-half">
                    <div class="form-group">
                        <label>Preferred Date &amp; Time</label>
                        <input class="form-control" type="datetime-local" name="preferred_datetime"
                               min="<?= date('Y-m-d\TH:i') ?>">
                    </div>
                </div>
                <div class="col-half">
                    <div class="form-group">
                        <label>Preferred Mode <span class="req">*</span></label>
                        <div class="mode-group">
                            <input type="hidden" name="mode" id="modeValue" value="Virtual">
                            <button type="button" class="mode-btn active" data-value="Virtual"
                                    onclick="setMode('Virtual',this)">💻 Virtual</button>
                            <button type="button" class="mode-btn" data-value="F2F"
                                    onclick="setMode('F2F',this)">🏫 F2F</button>
                        </div>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-lg">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
                Search Volunteers
            </button>
        </form>
    </div>
</div>
</div>

<script>
function setMode(val, btn) {
    document.getElementById('modeValue').value = val;
    document.querySelectorAll('.mode-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
}
</script>

<?php else: ?>
<!-- ══ Search Results ══════════════════════════════════════ -->
<div style="display:flex;align-items:center;gap:14px;margin-bottom:24px;">
    <a href="requests.php" class="btn btn-outline btn-sm">← Back</a>
    <div>
        <h1 style="font-family:Nunito,sans-serif;font-size:1.5rem;font-weight:800;margin:0;">
            Volunteer Search Results
        </h1>
        <p class="text-muted" style="font-size:.875rem;margin:4px 0 0;">
            <span id="volCount"><?= count($volunteers) ?></span> volunteer(s) match your request
        </p>
    </div>
</div>

<!-- Filters -->
<div class="filters-bar" id="filterForm">
    <div class="filter-group">
        <label>Filter by Skill</label>
        <select class="form-control" name="skill" onchange="">
            <option value="">All Skills</option>
            <option value="academic">Academic Support</option>
            <option value="technology">Technology Support</option>
            <option value="new_student">New Student Support</option>
            <option value="general">General Support</option>
        </select>
    </div>
    <div class="filter-group">
        <label>Mode</label>
        <select class="form-control" name="mode" onchange="">
            <option value="">All Modes</option>
            <option value="virtual">Virtual</option>
            <option value="f2f">Face-to-Face</option>
        </select>
    </div>
</div>

<form method="POST" id="selectForm">
    <input type="hidden" name="csrf"            value="<?= e(csrfToken()) ?>">
    <input type="hidden" name="select_volunteer" value="1">
    <input type="hidden" name="volunteer_id"     id="selectedVolId">
    <input type="hidden" name="scheduled_datetime"
           value="<?= e(date('Y-m-d\TH:i', strtotime('+2 days'))) ?>">

    <?php if ($volunteers): ?>
    <div class="vol-grid">
        <?php foreach ($volunteers as $vol):
            $rating = round((float)($vol['avg_rating'] ?? 0), 1);
            $skills = $vol['skills'] ? implode(', ', array_map(fn($s)=>ucwords(str_replace('_',' ',$s)), explode(',',$vol['skills']))) : '—';
        ?>
        <div class="vol-card"
             data-skill="<?= e($vol['skills'] ?? '') ?>"
             data-mode="virtual f2f">

            <div class="vol-header">
                <img src="<?= e($vol['photo'] ?? '') ?>"
                     alt="<?= e($vol['full_name']) ?>"
                     class="vol-avatar"
                     onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($vol['full_name']) ?>&background=1F3864&color=fff&size=100'">
                <div>
                    <div style="font-weight:800;font-family:Nunito,sans-serif;"><?= e($vol['full_name']) ?></div>
                    <div style="font-size:.78rem;color:var(--muted);">
                        <?= (int)($vol['session_count'] ?? 0) ?> sessions completed
                    </div>
                    <div class="stars" style="margin-top:2px;">
                        <?php for ($i=1;$i<=5;$i++) echo $i <= $rating ? '★' : '<span class="empty">★</span>'; ?>
                        <span style="font-size:.75rem;color:var(--muted);margin-left:4px;"><?= $rating > 0 ? $rating : 'New' ?></span>
                    </div>
                </div>
            </div>

            <div style="font-size:.8rem;color:#475569;display:flex;flex-direction:column;gap:6px;margin-bottom:14px;">
                <span>🎓 <?= e($skills) ?></span>
                <span>🕐 <?= e($vol['avail_text'] ?: 'Flexible') ?></span>
                <span class="badge badge-blue" style="align-self:flex-start;">Virtual &amp; F2F</span>
            </div>

            <button type="button" class="btn btn-outline btn-block select-vol-btn"
                    data-vol-id="<?= $vol['id'] ?>">
                Select Volunteer
            </button>
        </div>
        <?php endforeach; ?>
    </div>

    <div style="margin-top:28px;padding-top:20px;border-top:1px solid var(--border);">
        <p class="text-muted" style="font-size:.875rem;margin-bottom:12px;">
            Selected a volunteer? Click below to confirm your booking.
        </p>
        <button type="submit" class="btn btn-success btn-lg"
                onclick="return document.getElementById('selectedVolId').value || (alert('Please select a volunteer first.'),false)">
            ✓ Confirm Selection &amp; Book Session
        </button>
    </div>
    <?php else: ?>
    <div class="card card-body" style="text-align:center;padding:48px;">
        <div style="font-size:2.5rem;margin-bottom:12px;">🔍</div>
        <h3>No volunteers found</h3>
        <p class="text-muted">Try broadening your category or check back later.</p>
        <a href="requests.php" class="btn btn-primary mt-3">← Modify Request</a>
    </div>
    <?php endif; ?>
</form>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
