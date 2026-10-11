<?php
require_once 'config.php';
requireLogin();

$u  = currentUser();
$db = db();

$sessions = $db->prepare(
    'SELECT s.*,
            vol.full_name AS volunteer_name,
            vol.photo     AS volunteer_photo,
            stu.full_name AS student_name,
            stu.photo     AS student_photo,
            sr.category
     FROM sessions s
     JOIN users vol ON vol.id = s.volunteer_id
     JOIN users stu ON stu.id = s.student_id
     JOIN support_requests sr ON sr.id = s.request_id
     WHERE s.student_id = ? OR s.volunteer_id = ?
     ORDER BY s.scheduled_datetime DESC'
);
$sessions->execute([$u['id'], $u['id']]);
$all = $sessions->fetchAll();

$upcoming  = array_filter($all, fn($s) => $s['status'] === 'upcoming');
$past      = array_filter($all, fn($s) => $s['status'] !== 'upcoming');

$catLabels = ['academic'=>'Academic Support','technology'=>'Technology Support',
              'new_student'=>'New Student Support','general'=>'General Support'];

$pageTitle  = 'Manage Sessions';
$currentNav = 'sessions';
include 'includes/header.php';
?>
<meta name="csrf" content="<?= e(csrfToken()) ?>">

<div class="page-header">
    <h1>Manage Sessions</h1>
    <p>View, reschedule, or complete your booked support sessions.</p>
</div>

<!-- ── Reschedule modal ──────────────────────────────────── -->
<div class="modal-overlay" id="rescheduleModal" style="display:none;">
    <div class="modal-box">
        <div class="modal-title">📅 Reschedule Session</div>
        <p class="text-muted" style="font-size:.875rem;margin-bottom:16px;">
            Choose a new date and time. The other party will be notified.
        </p>
        <form method="POST" action="ajax/update_session.php">
            <input type="hidden" name="action"     value="reschedule">
            <input type="hidden" name="csrf"        value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="session_id"  id="rescheduleSessionId">
            <div class="form-group">
                <label>New Date &amp; Time</label>
                <input class="form-control" type="datetime-local" name="new_datetime"
                       min="<?= date('Y-m-d\TH:i') ?>" required>
            </div>
            <div class="modal-actions">
                <button type="submit" class="btn btn-primary">Confirm Reschedule</button>
                <button type="button" class="btn btn-outline" onclick="closeModal('rescheduleModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- ── Upcoming ──────────────────────────────────────────── -->
<h2 style="font-family:Nunito,sans-serif;font-size:1.15rem;font-weight:800;margin-bottom:16px;">
    Upcoming Sessions
</h2>

<?php if ($upcoming): ?>
<?php foreach ($upcoming as $s):
    $isVol     = $s['volunteer_id'] == $u['id'];
    $otherName = $isVol ? $s['student_name'] : $s['volunteer_name'];
    $otherPhoto= $isVol ? $s['student_photo'] : $s['volunteer_photo'];
?>
<div class="session-card" id="ses-<?= $s['id'] ?>">
    <div class="card-row">
        <div style="flex:1;">
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:8px;">
                <img src="<?= e($otherPhoto ?? '') ?>"
                     alt="<?= e($otherName) ?>"
                     style="width:38px;height:38px;border-radius:50%;object-fit:cover;border:2px solid var(--border);"
                     onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($otherName) ?>&background=1F3864&color=fff&size=80'">
                <div>
                    <strong style="font-family:Nunito,sans-serif;"><?= e($otherName) ?></strong>
                    <span class="text-muted" style="font-size:.78rem;margin-left:6px;"><?= $isVol ? '(Student)' : '(Volunteer)' ?></span>
                </div>
                <span class="badge badge-blue"><?= e($catLabels[$s['category']] ?? $s['category']) ?></span>
                <span class="badge badge-<?= $s['mode']==='Virtual'?'slate':'orange' ?>"><?= e($s['mode']) ?></span>
            </div>
            <div class="card-meta">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                <?= e(date('D, d M Y', strtotime($s['scheduled_datetime']))) ?>
                &nbsp;·&nbsp;
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                <?= e(date('g:i A', strtotime($s['scheduled_datetime']))) ?>
                <?php if ($s['meeting_link']): ?>
                &nbsp;·&nbsp; <a href="<?= e($s['meeting_link']) ?>" target="_blank"><?= e($s['meeting_link']) ?></a>
                <?php elseif ($s['location']): ?>
                &nbsp;·&nbsp; 📍 <?= e($s['location']) ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="card-actions">
            <button class="btn btn-outline btn-sm"
                    onclick="document.getElementById('rescheduleSessionId').value=<?= $s['id'] ?>;openModal('rescheduleModal')">
                📅 Reschedule
            </button>
            <button class="btn btn-danger btn-sm"
                    data-action="cancel-session"
                    data-id="<?= $s['id'] ?>">
                ✕ Cancel
            </button>
            <button class="btn btn-success btn-sm"
                    data-action="complete"
                    data-id="<?= $s['id'] ?>">
                ✓ Mark Completed
            </button>
        </div>
    </div>
</div>
<?php endforeach; ?>
<?php else: ?>
<div class="card card-body" style="text-align:center;padding:48px;margin-bottom:32px;">
    <div style="font-size:2.5rem;margin-bottom:12px;">📅</div>
    <h3 style="margin-bottom:8px;">No upcoming sessions</h3>
    <p class="text-muted">Submit a request to book a support session.</p>
    <a href="requests.php" class="btn btn-primary mt-3">Find a Volunteer</a>
</div>
<?php endif; ?>

<!-- ── Past sessions ─────────────────────────────────────── -->
<?php if ($past): ?>
<h2 style="font-family:Nunito,sans-serif;font-size:1.15rem;font-weight:800;margin:28px 0 16px;">
    Past Sessions
</h2>
<?php foreach ($past as $s):
    $isVol     = $s['volunteer_id'] == $u['id'];
    $otherName = $isVol ? $s['student_name'] : $s['volunteer_name'];

    $hasFeedback = $db->prepare('SELECT id FROM feedback WHERE session_id = ? LIMIT 1');
    $hasFeedback->execute([$s['id']]);
    $hasFeedback = (bool) $hasFeedback->fetch();
?>
<div class="session-card <?= e($s['status']) ?>" id="ses-<?= $s['id'] ?>" style="opacity:.75;">
    <div class="card-row">
        <div style="flex:1;">
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:6px;">
                <strong style="font-family:Nunito,sans-serif;"><?= e($otherName) ?></strong>
                <span class="badge badge-<?= e($catLabels[$s['category']] ? 'blue' : 'slate') ?>"><?= e($catLabels[$s['category']] ?? $s['category']) ?></span>
                <span class="badge badge-<?= $s['status']==='completed'?'green':'red' ?>">
                    <?= $s['status']==='completed' ? 'Completed' : 'Cancelled' ?>
                </span>
            </div>
            <p class="text-muted" style="font-size:.82rem;margin:0;">
                <?= e(date('D, d M Y g:ia', strtotime($s['scheduled_datetime']))) ?>
            </p>
        </div>
        <?php if ($s['status'] === 'completed' && !$hasFeedback): ?>
        <div class="card-actions">
            <a href="feedback.php?session_id=<?= $s['id'] ?>" class="btn btn-outline btn-sm">
                ⭐ Leave Feedback
            </a>
        </div>
        <?php elseif ($hasFeedback): ?>
        <span class="badge badge-green">Feedback Submitted</span>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
