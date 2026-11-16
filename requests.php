<?php
require_once 'config.php';
requireLogin(); 
feature/matching-requests-misha

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
$u = currentUser(); $db = db(); $errors = []; $step = 'form';
$requestId = (int)($_SESSION['last_request_id'] ?? 0);
$cats = ['academic' => 'Academic Support', 'technology' => 'Technology Support', 'new_student' => 'New Student Support', 'general' => 'General Support'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search'])) {
    verifyCsrf();
    $cat = $_POST['category'] ?? ''; $desc = trim($_POST['description'] ?? '');
    $date = $_POST['preferred_datetime'] ?? ''; $mode = $_POST['preferred_mode'] ?? 'virtual';
    if (!array_key_exists($cat, $cats)) $errors[] = 'Choose a valid category.';
    if (strlen($desc) < 5 || strlen($desc) > 500) $errors[] = 'Description must be 5-500 chars.';
    if (!$date || strtotime($date) === false) $errors[] = 'Choose a valid date/time.';
    if (!$errors) {
        $db->prepare('INSERT INTO support_requests (student_id, category, description, preferred_datetime, preferred_mode) VALUES (?, ?, ?, ?, ?)')->execute([$u['id'], $cat, $desc, date('Y-m-d H:i:s', strtotime($date)), $mode]);
        $_SESSION['last_request_id'] = $requestId = (int)$db->lastInsertId();
      main
        $step = 'results';
    }
}

feature/matching-requests-misha
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['select_volunteer'])) {
    verifyCsrf();
    $reqId = (int)($_POST['request_id'] ?? 0); $volId = (int)($_POST['volunteer_id'] ?? 0);
    $req = $db->prepare('SELECT * FROM support_requests WHERE id = ? AND student_id = ? AND status IN ("open","selected")');
    $req->execute([$reqId, $u['id']]); $rData = $req->fetch();
    $vCheck = $db->prepare('SELECT id FROM users WHERE id = ? AND is_volunteer = 1');
    $vCheck->execute([$volId]);

    if (!$rData || !$vCheck->fetch()) {
        $errors[] = 'Volunteer unavailable.'; $step = 'results';
    } else {
        $db->beginTransaction();
        try {
            $db->prepare('UPDATE support_requests SET selected_volunteer_id = ?, status = "accepted" WHERE id = ?')->execute([$volId, $reqId]);
            $detail = $rData['preferred_mode'] === 'f2f' ? 'HELP University Subang 2 — Library Discussion Room 3' : 'https://meet.google.com/unihelp-' . $reqId;
            $db->prepare('INSERT INTO sessions (request_id, student_id, volunteer_id, scheduled_datetime, mode, meeting_details, status) VALUES (?, ?, ?, ?, ?, ?, "upcoming")')
               ->execute([$reqId, $u['id'], $volId, $rData['preferred_datetime'], $rData['preferred_mode'], $detail]);
            $db->commit(); unset($_SESSION['last_request_id']);
            setFlash('success', 'Session scheduled!'); redirect('sessions.php');
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            $errors[] = 'Error: ' . $e->getMessage(); $step = 'results';
        }
    }
}

if (isset($_GET['view']) && $_GET['view'] === 'results' && $requestId) $step = 'results';
$volunteers = [];
if ($step === 'results' && $requestId) {
    $ar = $db->prepare('SELECT * FROM support_requests WHERE id = ? AND student_id = ?');
    $ar->execute([$requestId, $u['id']]); $activeReq = $ar->fetch();
    if ($activeReq) {
        $vQuery = $db->prepare('SELECT u.id, u.full_name, u.photo, GROUP_CONCAT(DISTINCT vs.skill_type) AS skills, COALESCE(ROUND(AVG(sf.rating), 1), 5.0) AS rating FROM users u JOIN volunteer_skills vs ON vs.user_id = u.id LEFT JOIN sessions s ON s.volunteer_id = u.id LEFT JOIN session_feedback sf ON sf.session_id = s.id WHERE u.is_volunteer = 1 AND u.id != ? AND vs.skill_type = ? GROUP BY u.id ORDER BY rating DESC');
        $vQuery->execute([$u['id'], $activeReq['category']]); $volunteers = $vQuery->fetchAll();
        if (!$volunteers) {
            $fb = $db->prepare('SELECT u.id, u.full_name, u.photo, COALESCE(GROUP_CONCAT(DISTINCT vs.skill_type), ?) AS skills, COALESCE(ROUND(AVG(sf.rating), 1), 5.0) AS rating FROM users u LEFT JOIN volunteer_skills vs ON vs.user_id = u.id LEFT JOIN sessions s ON s.volunteer_id = u.id LEFT JOIN session_feedback sf ON sf.session_id = s.id WHERE u.is_volunteer = 1 AND u.id != ? GROUP BY u.id ORDER BY rating DESC');
            $fb->execute([$activeReq['category'], $u['id']]); $volunteers = $fb->fetchAll();
        }
    } else { $step = 'form'; }
}

$selMode = $_POST['preferred_mode'] ?? 'virtual';
$pageTitle = 'Submit Request'; $currentNav = 'requests';
include 'includes/header.php';
?>
<meta name="csrf" content="<?= e(csrfToken()) ?>">
<?php if ($step === 'form') { ?>
<div class="page-header"><h1>Submit Support Request</h1><p>Find the right volunteer for your needs.</p></div>
<div class="request-form-wrap">
    <?php if ($errors) { ?><div class="flash flash-error"><?= e(implode(' ', $errors)) ?></div><?php } ?>
    <div class="card"><div class="card-body">
        <form method="POST">
            <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="search" value="1">
            <div class="form-group">
                <label>Category <span class="req">*</span></label>
                <select class="form-control" name="category" required>
                    <option value="">Select category…</option>
                    <?php foreach ($cats as $k => $v) { ?><option value="<?= e($k) ?>" <?= ($_POST['category'] ?? '') === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php } ?>
                </select>
            </div>
            <div class="form-group">
                <label>Description <span class="req">*</span></label>
                <textarea class="form-control" id="reqDesc" name="description" rows="4" maxlength="500" required><?= e($_POST['description'] ?? '') ?></textarea>
                <div class="form-hint"><span id="descCnt"><?= strlen($_POST['description'] ?? '') ?></span>/500</div>
            </div>
            <div class="row">
                <div class="col-half">
                    <div class="form-group">
                        <label>Date &amp; Time <span class="req">*</span></label>
                        <input class="form-control" type="datetime-local" name="preferred_datetime" value="<?= e($_POST['preferred_datetime'] ?? date('Y-m-d\TH:i', strtotime('+1 day'))) ?>" required>
                      main
                    </div>
                </div>
                <div class="col-half">
                    <div class="form-group">
feature/matching-requests-misha
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
/ /   C a s e   2 :   S u p p o r t   r e q u e s t s   a n d   v o l u n t e e r   m a t c h i n g   q u e r y   i m p l e m e n t e d   b y   M i s h a l i n i 
 
 
                        <label>Mode <span class="req">*</span></label>
                        <div class="mode-group" id="mGroup">
                            <button type="button" class="mode-btn <?= $selMode === 'virtual' ? 'active' : '' ?>" data-m="virtual">💻 Virtual</button>
                            <button type="button" class="mode-btn <?= $selMode === 'f2f' ? 'active' : '' ?>" data-m="f2f">🏫 F2F</button>
                        </div>
                        <input type="hidden" name="preferred_mode" id="mInput" value="<?= e($selMode) ?>">
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-lg">🔍 Search Volunteers</button>
        </form>
    </div></div>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const mInp = document.getElementById('mInput'), mBtns = document.querySelectorAll('#mGroup .mode-btn');
    mBtns.forEach(b => b.addEventListener('click', () => {
        mBtns.forEach(x => x.classList.remove('active'));
        b.classList.add('active'); mInp.value = b.dataset.m;
    }));
    const d = document.getElementById('reqDesc'), c = document.getElementById('descCnt');
    if (d && c) d.addEventListener('input', () => c.textContent = d.value.length);
});
</script>
<?php } else { ?>
<div class="results-heading"><a href="requests.php" class="btn btn-outline btn-sm">← Back</a><div><h1>Search Results</h1><p><?= count($volunteers) ?> volunteer(s) found</p></div></div>
<?php if ($volunteers) { ?>
<div class="vol-grid">
    <?php foreach ($volunteers as $vol) { ?>
    <div class="vol-card">
        <div class="vol-header">
            <img src="<?= e($vol['photo'] ?? '') ?>" class="vol-avatar" onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($vol['full_name']) ?>&background=1F3864&color=fff'">
            <div>
                <div class="vol-name"><?= e($vol['full_name']) ?></div>
                <div class="stars">★ <strong><?= e((string)$vol['rating']) ?></strong></div>
            </div>
        </div>
        <form method="POST">
            <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="select_volunteer" value="1">
            <input type="hidden" name="request_id" value="<?= (int)$requestId ?>">
            <input type="hidden" name="volunteer_id" value="<?= (int)$vol['id'] ?>">
            <button type="submit" class="btn btn-primary btn-block">Select &amp; Book</button>
        </form>
    </div>
    <?php } ?>
</div>
<?php } else { ?>
<div class="card empty-state"><h3>No volunteers found</h3><a href="requests.php" class="btn btn-primary mt-3">Try Again</a></div>
<?php } } ?>
<?php include 'includes/footer.php'; ?>
main
