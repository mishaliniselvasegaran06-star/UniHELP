<?php
require_once 'config.php';
requireLogin();

$u = currentUser();
$db = db();
$sessionId = (int)($_GET['session'] ?? $_POST['session_id'] ?? 0);
$errors = [];

$stmt = $db->prepare(
    'SELECT s.*, sr.category,
            vol.full_name AS volunteer_name, stu.full_name AS student_name
     FROM sessions s
     JOIN support_requests sr ON sr.id = s.request_id
     JOIN users vol ON vol.id = s.volunteer_id
     JOIN users stu ON stu.id = s.student_id
     WHERE s.id = ? AND s.status = "completed" AND s.student_id = ?'
);
$stmt->execute([$sessionId, $u['id']]);
$session = $stmt->fetch();
if (!$session) {
    setFlash('error', 'Completed session not found.');
    redirect('sessions.php');
}

$existing = $db->prepare('SELECT id FROM session_feedback WHERE session_id = ?');
$existing->execute([$sessionId]);
if ($existing->fetch()) {
    setFlash('info', 'Feedback has already been submitted for this session.');
    redirect('sessions.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $rating = (int)($_POST['rating'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');
    $allowedTags = ['Helpful', 'Patient', 'Punctual', 'Clear Explanation', 'Well Prepared'];
    $tags = array_values(array_intersect($allowedTags, $_POST['tags'] ?? []));
    if ($rating < 1 || $rating > 5) {
        $errors[] = 'Choose a rating from 1 to 5 stars.';
    }
    if (!$errors) {
        $db->prepare(
            'INSERT INTO session_feedback (session_id, author_id, rating, tags, comment)
             VALUES (?, ?, ?, ?, ?)'
        )->execute([$sessionId, $u['id'], $rating, implode(',', $tags), $comment ?: null]);
        setFlash('success', 'Thank you. Your feedback has been submitted.');
        redirect('sessions.php');
    }
}

$categoryLabels = [
    'academic'    => 'Academic Support',
    'technology'  => 'Technology Support',
    'new_student' => 'New Student Support',
    'general'     => 'General Support',
];
$otherName = $session['volunteer_name'];
$savedRating = (int)($_POST['rating'] ?? 0);
$savedTags = $_POST['tags'] ?? [];
$savedComment = $_POST['comment'] ?? '';
$pageTitle = 'Session Feedback';
$currentNav = 'sessions';
include 'includes/header.php';
?>

<style>
.star-rating { display: flex; align-items: center; gap: 6px; }
.star-btn {
    background: none; border: none; font-size: 2rem; line-height: 1;
    color: #CBD5E1; cursor: pointer; padding: 2px; transition: color .15s, transform .1s;
}
.star-btn:hover { transform: scale(1.12); }
.star-btn.selected, .star-btn.active { color: #F59E0B; }
.quick-tag { cursor: pointer; user-select: none; }
.quick-tag.selected, .quick-tag.active {
    background: #EFF6FF; border-color: #2563EB; color: #1D4ED8; font-weight: 600;
}
</style>

<div class="feedback-page">
    <div class="card feedback-card">
        <div class="feedback-header">
            <h1>Session Feedback</h1>
            <p>Rate your experience and help improve UniHELP.</p>
        </div>
        <?php if ($errors): ?><div class="flash flash-error panel-flash"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
        <div class="session-summary">
            <strong>Session with <?= e($otherName) ?></strong>
            <span><?= e($categoryLabels[$session['category']] ?? $session['category']) ?> · <?= e(date('d M Y', strtotime($session['scheduled_datetime']))) ?></span>
        </div>
        <form method="POST" id="feedbackForm">
            <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="session_id" value="<?= (int)$sessionId ?>">
            <input type="hidden" name="rating" id="ratingValue" value="<?= $savedRating ?>">
            <div class="form-group">
                <label>Overall Rating <span class="req">*</span></label>
                <div class="star-rating" id="starRatingBox">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                    <button type="button" class="star-btn <?= $i <= $savedRating ? 'selected' : '' ?>" data-rating="<?= $i ?>" aria-label="<?= $i ?> stars">★</button>
                    <?php endfor; ?>
                    <span class="star-label" id="ratingLabel"><?= $savedRating > 0 ? $savedRating . '/5 Stars' : 'Select a rating' ?></span>
                </div>
            </div>
            <div class="form-group">
                <label>Quick Appreciation</label>
                <div class="tags-wrap">
                    <?php foreach (['Helpful', 'Patient', 'Punctual', 'Clear Explanation', 'Well Prepared'] as $tag): ?>
                    <label class="quick-tag <?= in_array($tag, $savedTags, true) ? 'selected' : '' ?>">
                        <input type="checkbox" name="tags[]" value="<?= e($tag) ?>" <?= in_array($tag, $savedTags, true) ? 'checked' : '' ?>> <?= e($tag) ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="form-group">
                <label>Comment</label>
                <textarea class="form-control" name="comment" rows="4" placeholder="Share your experience with this volunteer…"><?= e($savedComment) ?></textarea>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-lg">Submit Feedback</button>
                <a href="sessions.php" class="btn btn-outline btn-lg">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    const box = document.getElementById('starRatingBox');
    const input = document.getElementById('ratingValue');
    const label = document.getElementById('ratingLabel');
    const buttons = Array.from(box.querySelectorAll('.star-btn'));
    const labels = ['', 'Poor', 'Fair', 'Good', 'Very Good', 'Excellent'];

    function paint(score) {
        buttons.forEach(btn => {
            btn.classList.toggle('selected', Number(btn.dataset.rating) <= score);
        });
    }

    buttons.forEach(btn => {
        btn.addEventListener('mouseenter', () => paint(Number(btn.dataset.rating)));
        btn.addEventListener('click', () => {
            const score = Number(btn.dataset.rating);
            input.value = String(score);
            paint(score);
            label.textContent = score + '/5 — ' + labels[score];
        });
    });

    box.addEventListener('mouseleave', () => paint(Number(input.value || 0)));

    document.querySelectorAll('.quick-tag input[type="checkbox"]').forEach(cb => {
        cb.addEventListener('change', () => {
            cb.closest('.quick-tag').classList.toggle('selected', cb.checked);
        });
    });
})();
</script>

<?php include 'includes/footer.php'; ?>