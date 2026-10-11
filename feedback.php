<?php
require_once 'config.php';
requireLogin();

$u         = currentUser();
$db        = db();
$sessionId = (int)($_GET['session_id'] ?? 0);
$submitted = false;

if (!$sessionId) redirect('sessions.php');

// Load session
$session = $db->prepare(
    'SELECT s.*, vol.full_name AS vol_name, vol.photo AS vol_photo,
            sr.category
     FROM sessions s
     JOIN users vol ON vol.id = s.volunteer_id
     JOIN support_requests sr ON sr.id = s.request_id
     WHERE s.id = ? AND s.student_id = ? AND s.status = "completed"
     LIMIT 1'
);
$session->execute([$sessionId, $u['id']]);
$session = $session->fetch();

if (!$session) {
    setFlash('error', 'Session not found or you cannot leave feedback for it.');
    redirect('sessions.php');
}

// Check already submitted
$check = $db->prepare('SELECT id FROM feedback WHERE session_id = ? LIMIT 1');
$check->execute([$sessionId]);
if ($check->fetch()) {
    setFlash('info', 'You have already submitted feedback for this session.');
    redirect('sessions.php');
}

/* ── Handle submission ───────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback'])) {
    verifyCsrf();
    $rating  = (int)($_POST['rating']  ?? 0);
    $comment = trim($_POST['comment'] ?? '');
    $tags    = trim($_POST['tags']    ?? '');
    $errors  = [];

    if ($rating < 1 || $rating > 5) $errors[] = 'Please select a rating from 1 to 5 stars.';

    if (!$errors) {
        $db->prepare(
            'INSERT INTO feedback (session_id, reviewer_id, rating, comment, tags)
             VALUES (?, ?, ?, ?, ?)'
        )->execute([$sessionId, $u['id'], $rating, $comment ?: null, $tags ?: null]);

        $submitted = true;
    }
}

$catLabels = ['academic'=>'Academic Support','technology'=>'Technology Support',
              'new_student'=>'New Student Support','general'=>'General Support'];

$pageTitle  = 'Session Feedback';
$currentNav = 'sessions';
include 'includes/header.php';
?>
<meta name="csrf" content="<?= e(csrfToken()) ?>">

<?php if ($submitted): ?>
<!-- ── Success ───────────────────────────────────────────── -->
<div class="success-screen">
    <div class="success-icon">
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#16A34A" stroke-width="2.5">
            <path d="M20 6L9 17l-5-5"/>
        </svg>
    </div>
    <h2 style="font-size:1.6rem;font-weight:800;margin-bottom:8px;color:#0f172a;">Feedback Submitted!</h2>
    <p class="text-muted" style="margin-bottom:28px;">
        Thank you for taking the time to rate your session.<br>
        Your feedback helps improve the UniHELP platform.
    </p>
    <a href="dashboard.php" class="btn btn-navy btn-lg">Back to Dashboard</a>
</div>

<?php else: ?>
<!-- ── Feedback form ─────────────────────────────────────── -->
<div class="page-header">
    <h1>Session Feedback</h1>
    <p>Rate your experience and help us improve the platform.</p>
</div>

<div style="max-width:600px;">

    <!-- Session summary -->
    <div style="display:flex;align-items:center;gap:14px;padding:16px;background:#f8fafc;border:1px solid var(--border);border-radius:12px;margin-bottom:24px;">
        <img src="<?= e($session['vol_photo'] ?? '') ?>"
             alt="<?= e($session['vol_name']) ?>"
             style="width:52px;height:52px;border-radius:50%;object-fit:cover;"
             onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($session['vol_name']) ?>&background=1F3864&color=fff&size=100'">
        <div>
            <p class="text-muted" style="font-size:.78rem;margin:0;">Your session with</p>
            <strong style="font-family:Nunito,sans-serif;font-size:1rem;"><?= e($session['vol_name']) ?></strong>
            <p class="text-muted" style="font-size:.78rem;margin:0;">
                <?= e($catLabels[$session['category']] ?? $session['category']) ?>
                &middot; <?= e(date('D, d M Y', strtotime($session['scheduled_datetime']))) ?>
                &middot; <?= e($session['mode']) ?>
            </p>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Leave Your Feedback</div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="csrf"            value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="submit_feedback" value="1">
                <input type="hidden" name="rating"          id="ratingValue" value="0">

                <!-- Star rating -->
                <div class="form-group">
                    <label>Overall Rating <span class="req">*</span></label>
                    <div class="star-rating" style="margin-top:6px;">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                        <button type="button" class="star-btn" data-val="<?= $i ?>">
                            <svg width="36" height="36" viewBox="0 0 24 24"
                                 fill="none" stroke="#CBD5E1" stroke-width="1.5">
                                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                            </svg>
                        </button>
                        <?php endfor; ?>
                        <span class="star-label"></span>
                    </div>
                </div>

                <!-- Quick tags -->
                <div class="form-group">
                    <label>Quick Tags <span class="text-muted" style="font-weight:400;">(optional)</span></label>
                    <div class="tags-wrap">
                        <?php
                        $tags = ['Helpful','Patient','Knowledgeable','On time','Clear explanations','Encouraging','Friendly','Professional'];
                        foreach ($tags as $tag): ?>
                        <button type="button" class="quick-tag" data-tag="<?= e($tag) ?>"><?= e($tag) ?></button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Comment -->
                <div class="form-group">
                    <label>Comments <span class="text-muted" style="font-weight:400;">(optional)</span></label>
                    <textarea class="form-control" name="comment" id="feedbackComment" rows="4"
                              placeholder="Share your experience — what went well, what could be better…"
                              data-maxlength="1000" data-counter="commentCounter"></textarea>
                    <div class="form-hint" style="text-align:right;">
                        <span id="commentCounter">0/1000</span>
                    </div>
                </div>

                <!-- Tags hidden field (populated by JS) -->
                <input type="hidden" name="tags" id="tagsField">

                <hr class="divider">

                <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                    <button type="submit" class="btn btn-success btn-lg"
                            onclick="
                                document.getElementById('tagsField').value =
                                    [...document.querySelectorAll('.quick-tag.selected')]
                                    .map(b=>b.dataset.tag).join(', ');
                                if (!parseInt(document.getElementById('ratingValue').value)) {
                                    alert('Please select a star rating.'); return false;
                                }">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6L9 17l-5-5"/></svg>
                        Submit Feedback
                    </button>
                    <a href="sessions.php" class="btn btn-outline">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
