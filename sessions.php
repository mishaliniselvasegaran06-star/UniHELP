<?php
require_once 'config.php';
requireLogin();

$u = currentUser();
$db = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $sessionId = (int)($_POST['session_id'] ?? 0);
    $action    = $_POST['action'] ?? '';

    $check = $db->prepare('SELECT * FROM sessions WHERE id = ? AND (student_id = ? OR volunteer_id = ?)');
    $check->execute([$sessionId, $u['id'], $u['id']]);
    $sess = $check->fetch();

    if (!$sess) {
        setFlash('error', 'Session not found.');
        redirect('sessions.php');
    }

    if ($action === 'reschedule' && $sess['status'] === 'upcoming') {
        $newDatetime = $_POST['new_datetime'] ?? '';
        $newMode     = $_POST['new_mode'] ?? $sess['mode'];
        if (!$newDatetime || strtotime($newDatetime) === false) {
            setFlash('error', 'Please choose a valid new date and time.');
        } else {
            $formatted = date('Y-m-d H:i:s', strtotime($newDatetime));
            $detail = $newMode === 'f2f'
                ? 'HELP University Subang 2 — Library Discussion Room 3'
                : 'https://meet.google.com/unihelp-session-' . $sess['request_id'];

            $db->prepare('UPDATE sessions SET scheduled_datetime = ?, mode = ?, meeting_details = ? WHERE id = ?')
               ->execute([$formatted, $newMode, $detail, $sessionId]);
            $db->prepare('UPDATE support_requests SET preferred_datetime = ?, preferred_mode = ? WHERE id = ?')
               ->execute([$formatted, $newMode, $sess['request_id']]);

            setFlash('success', 'Session rescheduled to ' . date('D, d M Y · g:i A', strtotime($formatted)) . '.');
        }
    } elseif ($action === 'cancel' && $sess['status'] === 'upcoming') {
        $db->prepare('UPDATE sessions SET status = "cancelled" WHERE id = ?')->execute([$sessionId]);
        $db->prepare('UPDATE support_requests SET status = "cancelled" WHERE id = ?')->execute([$sess['request_id']]);
        setFlash('info', 'Session cancelled.');
    } elseif ($action === 'complete' && $sess['status'] === 'upcoming') {
        $db->prepare('UPDATE sessions SET status = "completed" WHERE id = ?')->execute([$sessionId]);
        $db->prepare('UPDATE support_requests SET status = "completed" WHERE id = ?')->execute([$sess['request_id']]);
        setFlash('success', 'Session marked as completed!');
        if ((int)$sess['student_id'] === (int)$u['id']) {
            redirect('feedback.php?session=' . $sessionId);
        }
    }

    redirect('sessions.php');
}

$stmt = $db->prepare(
    'SELECT s.*, sr.category, sr.description,
            vol.full_name AS volunteer_name, vol.photo AS volunteer_photo,
            stu.full_name AS student_name, stu.photo AS student_photo,
            sf.id AS feedback_id, sf.rating AS feedback_rating
     FROM sessions s
     JOIN support_requests sr ON sr.id = s.request_id
     JOIN users vol ON vol.id = s.volunteer_id
     JOIN users stu ON stu.id = s.student_id
     LEFT JOIN session_feedback sf ON sf.session_id = s.id
     WHERE s.student_id = ? OR s.volunteer_id = ?
     ORDER BY s.scheduled_datetime DESC'
);
$stmt->execute([$u['id'], $u['id']]);
$allSessions = $stmt->fetchAll();

$upcoming = array_filter($allSessions, fn($s) => $s['status'] === 'upcoming');
$past     = array_filter($allSessions, fn($s) => $s['status'] !== 'upcoming');

$categoryLabels = [
    'academic'    => 'Academic Support',
    'technology'  => 'Technology Support',
    'new_student' => 'New Student Support',
    'general'     => 'General Support',
];

$pageTitle = 'Manage Sessions';
$currentNav = 'sessions';
include 'includes/header.php';
?>

<div class="page-header">
    <h1>Manage Sessions</h1>
    <p>View, reschedule, complete, or review your support sessions.</p>
</div>

<section class="sessions-section">
    <h2 class="section-title">Upcoming Sessions</h2>
    <?php if ($upcoming): ?>
        <?php foreach ($upcoming as $s):
            $isStudent = (int)$s['student_id'] === (int)$u['id'];
            $otherName = $isStudent ? $s['volunteer_name'] : $s['student_name'];
            $otherPhoto = $isStudent ? $s['volunteer_photo'] : $s['student_photo'];
            $dtValue = date('Y-m-d\TH:i', strtotime($s['scheduled_datetime']));
        ?>
        <div class="session-card">
            <div class="session-person">
                <img src="<?= e($otherPhoto ?? '') ?>" alt="<?= e($otherName) ?>" class="session-avatar"
                     onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($otherName) ?>&background=1F3864&color=fff&size=100'">
                <div class="session-main">
                    <div class="session-heading">
                        <strong><?= e($otherName) ?></strong>
                        <span class="badge badge-blue"><?= e($categoryLabels[$s['category']] ?? $s['category']) ?></span>
                        <span class="badge <?= $s['mode'] === 'f2f' ? 'badge-orange' : 'badge-slate' ?>">
                            <?= $s['mode'] === 'f2f' ? 'Face-to-Face' : 'Virtual' ?>
                        </span>
                    </div>
                    <div class="card-meta">
                        📅 <?= e(date('D, d M Y · g:i A', strtotime($s['scheduled_datetime']))) ?>
                    </div>
                    <?php if (!empty($s['meeting_details'])): ?>
                    <div class="card-meta" style="margin-top:4px;">
                        🔗 <?php if (str_starts_with($s['meeting_details'], 'http')): ?>
                            <a href="<?= e($s['meeting_details']) ?>" target="_blank"><?= e($s['meeting_details']) ?></a>
                        <?php else: ?>
                            <?= e($s['meeting_details']) ?>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-actions">
                <button type="button" class="btn btn-outline btn-sm open-reschedule-btn"
                        data-id="<?= (int)$s['id'] ?>"
                        data-datetime="<?= e($dtValue) ?>"
                        data-mode="<?= e($s['mode']) ?>"
                        data-partner="<?= e($otherName) ?>">
                    📅 Reschedule
                </button>
                <form method="POST" onsubmit="return confirm('Cancel this session?');">
                    <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="session_id" value="<?= (int)$s['id'] ?>">
                    <input type="hidden" name="action" value="cancel">
                    <button type="submit" class="btn btn-danger btn-sm">✕ Cancel</button>
                </form>
                <form method="POST">
                    <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="session_id" value="<?= (int)$s['id'] ?>">
                    <input type="hidden" name="action" value="complete">
                    <button type="submit" class="btn btn-success btn-sm">✓ Mark Completed</button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="card empty-state">
            <div class="empty-icon">📅</div>
            <h3>No upcoming sessions</h3>
            <p>Submit a support request to schedule a session with a peer volunteer.</p>
            <a href="requests.php" class="btn btn-primary mt-3">Request Support</a>
        </div>
    <?php endif; ?>
</section>

<section class="sessions-section" style="margin-top:28px;">
    <h2 class="section-title">Past Sessions</h2>
    <?php if ($past): ?>
        <?php foreach ($past as $s):
            $isStudent = (int)$s['student_id'] === (int)$u['id'];
            $otherName = $isStudent ? $s['volunteer_name'] : $s['student_name'];
            $otherPhoto = $isStudent ? $s['volunteer_photo'] : $s['student_photo'];
        ?>
        <div class="session-card">
            <div class="session-person">
                <img src="<?= e($otherPhoto ?? '') ?>" alt="<?= e($otherName) ?>" class="session-avatar"
                     onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($otherName) ?>&background=1F3864&color=fff&size=100'">
                <div class="session-main">
                    <div class="session-heading">
                        <strong><?= e($otherName) ?></strong>
                        <span class="badge badge-blue"><?= e($categoryLabels[$s['category']] ?? $s['category']) ?></span>
                        <span class="badge <?= $s['status'] === 'completed' ? 'badge-green' : 'badge-red' ?>">
                            <?= e(ucfirst($s['status'])) ?>
                        </span>
                    </div>
                    <div class="card-meta">
                        📅 <?= e(date('D, d M Y · g:i A', strtotime($s['scheduled_datetime']))) ?>
                    </div>
                </div>
            </div>
            <div class="card-actions">
                <?php if ($s['status'] === 'completed' && $isStudent): ?>
                    <?php if (!empty($s['feedback_id'])): ?>
                        <span class="badge badge-green">★ Rated <?= (int)$s['feedback_rating'] ?>/5</span>
                    <?php else: ?>
                        <a href="feedback.php?session=<?= (int)$s['id'] ?>" class="btn btn-primary btn-sm">⭐ Leave Feedback</a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="card empty-state">
            <div class="empty-icon">🗂️</div>
            <h3>No past sessions yet</h3>
            <p>Completed or cancelled sessions will appear here.</p>
        </div>
    <?php endif; ?>
</section>

<!-- ── Reschedule Modal ─────────────────────────────────── -->
<div id="rescheduleModal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.55);z-index:9999;align-items:center;justify-content:center;padding:16px;">
    <div class="card" style="width:100%;max-width:440px;padding:24px;box-shadow:0 20px 40px rgba(0,0,0,.2);">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
            <h3 style="margin:0;font-size:1.15rem;font-weight:800;">Reschedule Session</h3>
            <button type="button" id="closeRescheduleBtn" style="border:none;background:none;font-size:1.4rem;cursor:pointer;color:#64748B;">×</button>
        </div>
        <p class="text-muted" style="font-size:.875rem;margin-bottom:16px;">
            Update date, time, or mode for your session with <strong id="modalPartnerName"></strong>.
        </p>
        <form method="POST">
            <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="action" value="reschedule">
            <input type="hidden" name="session_id" id="modalSessionId" value="">

            <div class="form-group">
                <label>New Date &amp; Time <span class="req">*</span></label>
                <input class="form-control" type="datetime-local" name="new_datetime" id="modalDatetime" required>
            </div>

            <div class="form-group">
                <label>Support Mode <span class="req">*</span></label>
                <select class="form-control" name="new_mode" id="modalMode">
                    <option value="virtual">💻 Virtual (Google Meet)</option>
                    <option value="f2f">🏫 Face-to-Face (HELP Subang 2)</option>
                </select>
            </div>

            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:18px;">
                <button type="button" id="cancelRescheduleBtn" class="btn btn-outline">Cancel</button>
                <button type="submit" class="btn btn-primary">Save New Time</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    const modal = document.getElementById('rescheduleModal');
    const idInput = document.getElementById('modalSessionId');
    const dtInput = document.getElementById('modalDatetime');
    const modeSelect = document.getElementById('modalMode');
    const partnerEl = document.getElementById('modalPartnerName');

    document.querySelectorAll('.open-reschedule-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            idInput.value = btn.dataset.id;
            dtInput.value = btn.dataset.datetime;
            modeSelect.value = btn.dataset.mode;
            partnerEl.textContent = btn.dataset.partner;
            modal.style.display = 'flex';
        });
    });

    function closeModal() {
        modal.style.display = 'none';
    }

    document.getElementById('closeRescheduleBtn').addEventListener('click', closeModal);
    document.getElementById('cancelRescheduleBtn').addEventListener('click', closeModal);
    modal.addEventListener('click', (e) => {
        if (e.target === modal) closeModal();
    });
})();
</script>

<?php include 'includes/footer.php'; ?>