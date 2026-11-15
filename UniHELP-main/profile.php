<?php
require_once 'config.php';
requireLogin();

$u   = currentUser();
$db  = db();
$tab = $_GET['tab'] ?? 'profile';

/* ── Save profile ────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profile'])) {
    verifyCsrf();
    $name  = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone']     ?? '');
    $photo = null;
    $error = null;

    if (!empty($_FILES['photo']['name'])) {
        if ($_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
            $error = 'The profile photo could not be uploaded.';
        } elseif ($_FILES['photo']['size'] > 2 * 1024 * 1024) {
            $error = 'The profile photo must be 2 MB or smaller.';
        } else {
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['photo']['tmp_name']);
            $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            if (!isset($extensions[$mime])) {
                $error = 'Please upload a JPG, PNG, or WebP image.';
            } else {
                if (!is_dir(UPLOAD_DIR) && !mkdir(UPLOAD_DIR, 0755, true)) {
                    $error = 'The upload folder could not be created.';
                } else {
                    $filename = 'profile-' . $u['id'] . '-' . bin2hex(random_bytes(6)) . '.' . $extensions[$mime];
                    if (move_uploaded_file($_FILES['photo']['tmp_name'], UPLOAD_DIR . $filename)) {
                        $photo = UPLOAD_URL . $filename;
                    } else {
                        $error = 'The profile photo could not be saved.';
                    }
                }
            }
        }
    }

    if (!$name) {
        $error = 'Full name is required.';
    }

    if ($error) {
        setFlash('error', $error);
    } else {
        $sql = 'UPDATE users SET full_name = ?, phone = ?';
        $params = [$name, $phone ?: null];
        if ($photo) {
            $sql .= ', photo = ?';
            $params[] = $photo;
        }
        $sql .= ' WHERE id = ?';
        $params[] = $u['id'];
        $db->prepare($sql)->execute($params);
        $_SESSION['user_name'] = $name;
        setFlash('success', 'Profile updated successfully.');
    }
    redirect('profile.php');
}

/* ── Become volunteer ────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['become_volunteer'])) {
    verifyCsrf();
    $db->prepare("UPDATE users SET is_volunteer = 1, role = 'volunteer' WHERE id = ?")
       ->execute([$u['id']]);
    $_SESSION['is_volunteer'] = true;
    $_SESSION['user_role']    = 'volunteer';
    setFlash('success', 'You are now registered as a volunteer! Add your support skills.');
    redirect('profile.php?tab=skills');
}

/* ── Save skills ─────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_skills'])) {
    verifyCsrf();
    $skillOptions = ['academic', 'technology', 'new_student', 'general'];
    $db->prepare('DELETE FROM volunteer_skills WHERE user_id = ?')->execute([$u['id']]);
    $ins = $db->prepare('INSERT IGNORE INTO volunteer_skills (user_id, skill_type) VALUES (?,?)');
    foreach ($skillOptions as $sk) {
        if (!empty($_POST['skill_' . $sk])) {
            $ins->execute([$u['id'], $sk]);
        }
    }
    setFlash('success', 'Skills saved.');
    redirect('profile.php?tab=skills');
}

/* ── Save weekly availability ───────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_availability'])) {
    verifyCsrf();
    $days = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat'];
    $slots = ['08_10', '10_12', '12_14', '14_16', '16_18', '18_20'];
    $db->beginTransaction();
    try {
        $db->prepare('DELETE FROM volunteer_availability WHERE user_id = ?')->execute([$u['id']]);
        $insert = $db->prepare(
            'INSERT INTO volunteer_availability (user_id, day_of_week, time_slot) VALUES (?,?,?)'
        );
        foreach ($days as $day) {
            foreach ($slots as $slot) {
                if (!empty($_POST['availability'][$day][$slot])) {
                    $insert->execute([$u['id'], $day, $slot]);
                }
            }
        }
        $db->commit();
        setFlash('success', 'Weekly availability saved.');
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        setFlash('error', 'Availability could not be saved.');
    }
    redirect('profile.php?tab=skills');
}

// Load current data
$userData = $db->prepare('SELECT * FROM users WHERE id = ?');
$userData->execute([$u['id']]);
$userData = $userData->fetch();

$mySkills = $db->prepare('SELECT skill_type FROM volunteer_skills WHERE user_id = ?');
$mySkills->execute([$u['id']]);
$mySkills = array_column($mySkills->fetchAll(), 'skill_type');

$availabilityStmt = $db->prepare(
    'SELECT day_of_week, time_slot FROM volunteer_availability WHERE user_id = ?'
);
$availabilityStmt->execute([$u['id']]);
$myAvailability = [];
foreach ($availabilityStmt->fetchAll() as $availability) {
    $myAvailability[$availability['day_of_week'] . '-' . $availability['time_slot']] = true;
}

// Load volunteer feedback summary & recent reviews
$ratingSummaryStmt = $db->prepare(
    'SELECT COUNT(sf.id) AS total_reviews, COALESCE(ROUND(AVG(sf.rating), 1), 0) AS avg_rating
     FROM sessions s
     JOIN session_feedback sf ON sf.session_id = s.id
     WHERE s.volunteer_id = ?'
);
$ratingSummaryStmt->execute([$u['id']]);
$ratingSummary = $ratingSummaryStmt->fetch() ?: ['total_reviews' => 0, 'avg_rating' => 0];

$recentReviewsStmt = $db->prepare(
    'SELECT sf.rating, sf.tags, sf.comment, sf.created_at, stu.full_name AS student_name
     FROM session_feedback sf
     JOIN sessions s ON s.id = sf.session_id
     JOIN users stu ON stu.id = sf.author_id
     WHERE s.volunteer_id = ?
     ORDER BY sf.created_at DESC
     LIMIT 5'
);
$recentReviewsStmt->execute([$u['id']]);
$recentReviews = $recentReviewsStmt->fetchAll();

$pageTitle  = 'My Profile';
$currentNav = 'profile';
include 'includes/header.php';
?>
<meta name="csrf" content="<?= e(csrfToken()) ?>">

<div class="page-header">
    <h1>My Profile</h1>
    <p>Manage your personal information, volunteer settings, and feedback ratings.</p>
</div>

<!-- ── Tabs ─────────────────────────────────────────────── -->
<div class="tab-bar">
    <a href="profile.php?tab=profile" class="tab-btn <?= $tab==='profile'?'active':'' ?>">👤 My Profile</a>
    <a href="profile.php?tab=skills"  class="tab-btn <?= $tab==='skills' ?'active':'' ?>">🛠 Skills &amp; Availability</a>
    <a href="profile.php?tab=reviews" class="tab-btn <?= $tab==='reviews'?'active':'' ?>">★ Feedback &amp; Ratings (<?= (int)$ratingSummary['total_reviews'] ?>)</a>
</div>

<?php if ($tab === 'profile'): ?>
<!-- ══ Profile tab ════════════════════════════════════════ -->
<div style="display:grid;grid-template-columns:1fr 2fr;gap:24px;align-items:start;" class="profile-grid">

    <!-- Left: photo card -->
    <div class="card card-body" style="text-align:center;">
        <div class="profile-photo-wrap" style="display:inline-block;margin-bottom:16px;">
            <img src="<?= e($userData['photo'] ?? 'assets/default-avatar.png') ?>"
                 alt="Profile photo"
                 class="profile-photo"
                 onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($userData['full_name']) ?>&background=1F3864&color=fff&size=200'">
        </div>
        <h3 style="font-family:Nunito,sans-serif;font-weight:800;margin-bottom:4px;"><?= e($userData['full_name']) ?></h3>
        <p class="text-muted" style="font-size:.85rem;">ID: <?= e($userData['student_id']) ?></p>
        <span class="badge badge-blue" style="margin-top:8px;"><?= $userData['is_volunteer'] ? 'Volunteer' : 'Student' ?></span>
        <?php if ($ratingSummary['total_reviews'] > 0): ?>
        <div style="margin-top:10px;font-weight:700;color:#D97706;">
            ★ <?= e((string)$ratingSummary['avg_rating']) ?> / 5.0 (<?= (int)$ratingSummary['total_reviews'] ?> reviews)
        </div>
        <?php endif; ?>

        <?php if (!$userData['is_volunteer']): ?>
        <form method="POST" style="margin-top:20px;">
            <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="become_volunteer" value="1">
            <button type="submit" class="btn btn-success btn-block">🤝 Become a Volunteer</button>
        </form>
        <?php endif; ?>
    </div>

    <!-- Right: edit form -->
    <div class="card">
        <div class="card-header">Personal Information</div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf"         value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="save_profile" value="1">

                <div class="row">
                    <div class="col-half">
                        <div class="form-group">
                            <label>Full Name <span class="req">*</span></label>
                            <input class="form-control" type="text" name="full_name"
                                   value="<?= e($userData['full_name']) ?>" required>
                        </div>
                    </div>
                    <div class="col-half">
                        <div class="form-group">
                            <label>Phone Number</label>
                            <input class="form-control" type="tel" name="phone"
                                   placeholder="+60 12-xxx xxxx"
                                   value="<?= e($userData['phone'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="col-full">
                        <div class="form-group">
                            <label>Profile Photo</label>
                            <input class="form-control" type="file" name="photo"
                                   accept="image/jpeg,image/png,image/webp">
                            <div class="form-hint">JPG, PNG, or WebP. Maximum 2 MB.</div>
                        </div>
                    </div>
                    <div class="col-full">
                        <div class="form-group">
                            <label>University Email</label>
                            <input class="form-control" type="email"
                                   value="<?= e($userData['email']) ?>" readonly>
                        </div>
                    </div>
                    <div class="col-half">
                        <div class="form-group">
                            <label>Student ID</label>
                            <input class="form-control" value="<?= e($userData['student_id']) ?>" readonly>
                        </div>
                    </div>
                    <div class="col-half">
                        <div class="form-group">
                            <label>Role</label>
                            <input class="form-control" value="<?= e(ucfirst($userData['role'])) ?>" readonly>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">Save Changes</button>
            </form>
        </div>
    </div>
</div>

<?php elseif ($tab === 'skills'): ?>
<!-- ══ Volunteer skills tab ═══════════════════════════════ -->
<?php if (!$userData['is_volunteer']): ?>
<div class="card card-body" style="text-align:center;padding:40px;">
    <div style="font-size:2.5rem;margin-bottom:12px;">🤝</div>
    <h3 style="margin-bottom:8px;">Become a Volunteer First</h3>
    <p class="text-muted" style="margin-bottom:20px;">Register as a volunteer to add your support skills.</p>
    <form method="POST" style="display:inline;">
        <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="become_volunteer" value="1">
        <button class="btn btn-success btn-lg">🤝 Become a Volunteer</button>
    </form>
</div>
<?php else: ?>

<div class="skills-availability-grid">

    <!-- Skills -->
    <div class="card">
        <div class="card-header">Support Skills</div>
        <div class="card-body">
            <p class="text-muted" style="font-size:.875rem;margin-bottom:18px;">
                Select the areas where you can support fellow students.
            </p>
            <form method="POST">
                <input type="hidden" name="csrf"        value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="save_skills" value="1">

                <?php
                $skillDefs = [
                    'academic'    => ['📚 Academic Support',    'Tutoring, assignments, exam preparation'],
                    'technology'  => ['💻 Technology Support',  'Software, coding, IT troubleshooting'],
                    'new_student' => ['🗺 New Student Support', 'Campus orientation, registration guidance'],
                    'general'     => ['💬 General Support',     'Emotional support and general guidance'],
                ];
                foreach ($skillDefs as $key => [$label, $desc]):
                    $checked = in_array($key, $mySkills);
                ?>
                <label class="skill-item <?= $checked?'checked':'' ?>">
                    <input type="checkbox" name="skill_<?= $key ?>" <?= $checked?'checked':'' ?>>
                    <div>
                        <div class="skill-title"><?= e($label) ?></div>
                        <div class="skill-desc"><?= e($desc) ?></div>
                    </div>
                </label>
                <?php endforeach; ?>

                <button type="submit" class="btn btn-primary mt-3">Save Skills</button>
            </form>
        </div>
    </div>

    <!-- Availability -->
    <div class="card">
        <div class="card-header">Weekly Availability</div>
        <div class="card-body">
            <p class="text-muted" style="font-size:.875rem;margin-bottom:18px;">
                Select the time blocks when students can book your support.
            </p>
            <form method="POST">
                <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="save_availability" value="1">
                <?php
                $dayLabels = ['mon'=>'Mon','tue'=>'Tue','wed'=>'Wed','thu'=>'Thu','fri'=>'Fri','sat'=>'Sat'];
                $slotLabels = ['08_10'=>'8–10am','10_12'=>'10–12pm','12_14'=>'12–2pm','14_16'=>'2–4pm','16_18'=>'4–6pm','18_20'=>'6–8pm'];
                ?>
                <div class="availability-scroll">
                    <table class="avail-table">
                        <thead>
                            <tr><th>Time</th><?php foreach ($dayLabels as $label): ?><th><?= e($label) ?></th><?php endforeach; ?></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($slotLabels as $slot => $slotLabel): ?>
                            <tr>
                                <th><?= e($slotLabel) ?></th>
                                <?php foreach ($dayLabels as $day => $dayLabel):
                                    $active = !empty($myAvailability[$day . '-' . $slot]);
                                ?>
                                <td>
                                    <label class="avail-slot <?= $active ? 'on' : '' ?>" title="<?= e($dayLabel . ' ' . $slotLabel) ?>">
                                        <input type="checkbox" name="availability[<?= e($day) ?>][<?= e($slot) ?>]" <?= $active ? 'checked' : '' ?>>
                                        <span></span>
                                    </label>
                                </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="availability-legend">
                    <span><i class="legend-dot available"></i> Available</span>
                    <span><i class="legend-dot"></i> Unavailable</span>
                </div>
                <button type="submit" class="btn btn-primary mt-3">Save Availability</button>
            </form>
        </div>
    </div>

</div>
<?php endif; ?>

<?php else: ?>
<!-- ══ Feedback & Ratings tab ═════════════════════════════ -->
<div class="card">
    <div class="card-header">
        Session Feedback Received
        <?php if ($ratingSummary['total_reviews'] > 0): ?>
            <span class="badge badge-green" style="margin-left:8px;">★ <?= e((string)$ratingSummary['avg_rating']) ?> Average</span>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if ($recentReviews): ?>
            <?php foreach ($recentReviews as $rev): ?>
            <div style="border-bottom:1px solid #E2E8F0;padding:14px 0;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                    <strong><?= e($rev['student_name']) ?></strong>
                    <span style="color:#D97706;font-weight:700;"><?= str_repeat('★', (int)$rev['rating']) . str_repeat('☆', 5 - (int)$rev['rating']) ?> (<?= (int)$rev['rating'] ?>/5)</span>
                </div>
                <?php if (!empty($rev['tags'])): ?>
                <div style="margin-bottom:6px;">
                    <?php foreach (explode(',', $rev['tags']) as $tag): ?>
                    <span class="badge badge-blue"><?= e(trim($tag)) ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <?php if (!empty($rev['comment'])): ?>
                <p style="margin:4px 0;color:#334155;"><?= e($rev['comment']) ?></p>
                <?php endif; ?>
                <small class="text-muted"><?= e(date('d M Y, g:i A', strtotime($rev['created_at']))) ?></small>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state" style="padding:30px 10px;">
                <div class="empty-icon">★</div>
                <h3>No feedback reviews yet</h3>
                <p>Completed session ratings and student appreciation tags will appear here.</p>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<style>
@media (max-width:768px) {
    .profile-grid { grid-template-columns: 1fr !important; }
}
</style>

<?php include 'includes/footer.php'; ?>