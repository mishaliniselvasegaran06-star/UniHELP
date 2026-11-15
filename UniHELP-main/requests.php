<?php
require_once 'config.php';
requireLogin();
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
        $step = 'results';
    }
}

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
                    </div>
                </div>
                <div class="col-half">
                    <div class="form-group">
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