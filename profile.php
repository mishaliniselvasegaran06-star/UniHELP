<?php
require_once 'config.php';
requireLogin();

$u    = currentUser();
$db   = db();
$tab  = $_GET['tab'] ?? 'profile';

/* ── Save profile ────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profile'])) {
    verifyCsrf();
    $name  = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone']     ?? '');
    if ($name) {
        $db->prepare('UPDATE users SET full_name = ?, phone = ? WHERE id = ?')
           ->execute([$name, $phone ?: null, $u['id']]);
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
    setFlash('success', 'You are now registered as a volunteer! Set up your skills and availability.');
    redirect('profile.php?tab=skills');
}

/* ── Save skills ─────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_skills'])) {
    verifyCsrf();
    $skillOptions = ['academic','technology','new_student','general'];
    $db->prepare('DELETE FROM volunteer_skills WHERE user_id = ?')->execute([$u['id']]);
    $ins = $db->prepare('INSERT IGNORE INTO volunteer_skills (user_id, skill_type) VALUES (?,?)');
    foreach ($skillOptions as $sk) {
        if (!empty($_POST['skill_' . $sk])) $ins->execute([$u['id'], $sk]);
    }
    setFlash('success', 'Skills saved.');
    redirect('profile.php?tab=skills');
}

/* ── Save availability ───────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_avail'])) {
    verifyCsrf();
    $db->prepare('DELETE FROM volunteer_availability WHERE user_id = ?')->execute([$u['id']]);
    $ins = $db->prepare('INSERT IGNORE INTO volunteer_availability (user_id, day_name, time_slot) VALUES (?,?,?)');
    $days  = ['Mon','Tue','Wed','Thu','Fri','Sat'];
    $slots = ['8–10am','10–12pm','12–2pm','2–4pm','4–6pm','6–8pm'];
    foreach ($days as $d) {
        foreach ($slots as $s) {
            $key = 'avail_' . $d . '_' . preg_replace('/[^a-z0-9]/i','_',$s);
            if (!empty($_POST[$key])) $ins->execute([$u['id'], $d, $s]);
        }
    }
    setFlash('success', 'Availability saved.');
    redirect('profile.php?tab=skills');
}

// Load current data
$userData = $db->prepare('SELECT * FROM users WHERE id = ?');
$userData->execute([$u['id']]);
$userData = $userData->fetch();

$mySkills = $db->prepare('SELECT skill_type FROM volunteer_skills WHERE user_id = ?');
$mySkills->execute([$u['id']]);
$mySkills = array_column($mySkills->fetchAll(), 'skill_type');

$myAvail = $db->prepare('SELECT day_name, time_slot FROM volunteer_availability WHERE user_id = ?');
$myAvail->execute([$u['id']]);
$availSet = [];
foreach ($myAvail->fetchAll() as $row) {
    $availSet[$row['day_name'] . '_' . $row['time_slot']] = true;
}

$pageTitle  = 'My Profile';
$currentNav = 'profile';
include 'includes/header.php';
?>
<meta name="csrf" content="<?= e(csrfToken()) ?>">

<div class="page-header">
    <h1>My Profile</h1>
    <p>Manage your personal information and volunteer settings.</p>
</div>

<!-- ── Tabs ─────────────────────────────────────────────── -->
<div class="tab-bar">
    <a href="profile.php?tab=profile" class="tab-btn <?= $tab==='profile'?'active':'' ?>">👤 My Profile</a>
    <a href="profile.php?tab=skills"  class="tab-btn <?= $tab==='skills' ?'active':'' ?>">🛠 Skills &amp; Availability</a>
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
            <button class="photo-edit-btn" onclick="alert('Photo upload would be implemented here.')">✏</button>
        </div>
        <h3 style="font-family:Nunito,sans-serif;font-weight:800;margin-bottom:4px;"><?= e($userData['full_name']) ?></h3>
        <p class="text-muted" style="font-size:.85rem;">ID: <?= e($userData['student_id']) ?></p>
        <span class="badge badge-blue" style="margin-top:8px;"><?= $userData['is_volunteer'] ? 'Volunteer' : 'Student' ?></span>

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
            <form method="POST">
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

<?php else: ?>
<!-- ══ Skills & Availability tab ══════════════════════════ -->
<?php if (!$userData['is_volunteer']): ?>
<div class="card card-body" style="text-align:center;padding:40px;">
    <div style="font-size:2.5rem;margin-bottom:12px;">🤝</div>
    <h3 style="margin-bottom:8px;">Become a Volunteer First</h3>
    <p class="text-muted" style="margin-bottom:20px;">Register as a volunteer to set up your skills and availability.</p>
    <form method="POST" style="display:inline;">
        <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="become_volunteer" value="1">
        <button class="btn btn-success btn-lg">🤝 Become a Volunteer</button>
    </form>
</div>
<?php else: ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;align-items:start;" class="profile-grid">

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
            <p class="text-muted" style="font-size:.875rem;margin-bottom:14px;">
                Click cells to mark when you're available.
            </p>
            <form method="POST">
                <input type="hidden" name="csrf"       value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="save_avail" value="1">

                <?php
                $days  = ['Mon','Tue','Wed','Thu','Fri','Sat'];
                $slots = ['8–10am','10–12pm','12–2pm','2–4pm','4–6pm','6–8pm'];
                ?>
                <table class="avail-table">
                    <thead>
                        <tr>
                            <th style="text-align:left;width:70px;">Time</th>
                            <?php foreach ($days as $d): ?>
                            <th><?= $d ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($slots as $slot): ?>
                        <tr>
                            <td style="font-size:.75rem;color:var(--muted);font-weight:600;white-space:nowrap;padding-right:8px;">
                                <?= e($slot) ?>
                            </td>
                            <?php foreach ($days as $d):
                                $key      = $d . '_' . $slot;
                                $postKey  = 'avail_' . $d . '_' . preg_replace('/[^a-z0-9]/i','_',$slot);
                                $isOn     = !empty($availSet[$key]);
                            ?>
                            <td>
                                <div class="avail-slot <?= $isOn?'on':'' ?>"
                                     data-hidden="<?= e('h_'.$postKey) ?>"
                                     title="<?= e($d.' '.$slot) ?>"></div>
                                <input type="hidden" id="<?= e('h_'.$postKey) ?>"
                                       name="<?= e($postKey) ?>"
                                       value="<?= $isOn?'1':'0' ?>">
                            </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div style="display:flex;gap:16px;margin-top:14px;font-size:.75rem;color:var(--muted);">
                    <span><span class="avail-slot on" style="display:inline-block;width:18px;height:18px;pointer-events:none;"></span> Available</span>
                    <span><span class="avail-slot"   style="display:inline-block;width:18px;height:18px;pointer-events:none;"></span> Unavailable</span>
                </div>

                <button type="submit" class="btn btn-primary mt-3">Save Availability</button>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<style>
@media (max-width:768px) {
    .profile-grid { grid-template-columns: 1fr !important; }
}
</style>

<?php include 'includes/footer.php'; ?>
