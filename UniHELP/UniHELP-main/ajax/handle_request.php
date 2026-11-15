<?php
require_once '../config.php';
requireLogin();

header('Content-Type: application/json');

$raw     = json_decode(file_get_contents('php://input'), true) ?? [];
$action  = $raw['action']          ?? '';
$reqId   = (int)($raw['request_id'] ?? 0);
$sessId  = (int)($raw['session_id'] ?? 0);
$csrf    = $raw['csrf']            ?? '';
$sched   = $raw['scheduled_at']    ?? '';
$details = trim($raw['meeting_details'] ?? '');

if (!hash_equals(csrfToken(), $csrf)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'CSRF mismatch.']);
    exit;
}

$u  = currentUser();
$db = db();

/* ── Session Management AJAX Actions (Charmaine — Case 3) ── */
if (in_array($action, ['complete_session', 'cancel_session', 'reschedule_session'], true)) {
    if (!$sessId) {
        echo json_encode(['ok' => false, 'error' => 'Invalid session ID.']);
        exit;
    }

    $owned = $db->prepare(
        'SELECT id, request_id, status FROM sessions
         WHERE id = ? AND (student_id = ? OR volunteer_id = ?) LIMIT 1'
    );
    $owned->execute([$sessId, $u['id'], $u['id']]);
    $session = $owned->fetch();

    if (!$session) {
        echo json_encode(['ok' => false, 'error' => 'Session not found.']);
        exit;
    }

    if ($session['status'] !== 'upcoming') {
        echo json_encode(['ok' => false, 'error' => 'Past or closed sessions cannot be modified.']);
        exit;
    }

    if ($action === 'complete_session') {
        try {
            $db->beginTransaction();
            $db->prepare('UPDATE sessions SET status = "completed" WHERE id = ?')->execute([$sessId]);
            $db->prepare('UPDATE support_requests SET status = "completed" WHERE id = ?')->execute([$session['request_id']]);
            $db->commit();
            echo json_encode(['ok' => true, 'redirect' => 'feedback.php?session=' . $sessId]);
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            echo json_encode(['ok' => false, 'error' => 'Session could not be marked as completed.']);
        }
        exit;
    }

    if ($action === 'cancel_session') {
        try {
            $db->beginTransaction();
            $db->prepare('UPDATE sessions SET status = "cancelled" WHERE id = ?')->execute([$sessId]);
            $db->prepare('UPDATE support_requests SET status = "cancelled" WHERE id = ?')->execute([$session['request_id']]);
            $db->commit();
            echo json_encode(['ok' => true]);
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            echo json_encode(['ok' => false, 'error' => 'Session could not be cancelled.']);
        }
        exit;
    }

    if ($action === 'reschedule_session') {
        if (!$sched || strtotime($sched) <= time()) {
            echo json_encode(['ok' => false, 'error' => 'Choose a valid future date and time.']);
            exit;
        }
        $formattedSched = date('Y-m-d H:i:s', strtotime($sched));
        $db->prepare('UPDATE sessions SET scheduled_datetime = ? WHERE id = ?')
           ->execute([$formattedSched, $sessId]);
        echo json_encode(['ok' => true, 'scheduled_datetime' => $formattedSched]);
        exit;
    }
}

/* ── Volunteer Queue AJAX Actions ────────────────────────── */
if (empty($_SESSION['is_volunteer'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Volunteer access required.']);
    exit;
}

if (!$reqId) {
    echo json_encode(['ok' => false, 'error' => 'Invalid request.']);
    exit;
}

if ($action === 'reject') {
    $stmt = $db->prepare(
        'UPDATE support_requests
         SET status = "rejected"
         WHERE id = ? AND status IN ("open","selected")
           AND (selected_volunteer_id IS NULL OR selected_volunteer_id = ?)'
    );
    $stmt->execute([$reqId, $u['id']]);
    echo json_encode($stmt->rowCount()
        ? ['ok' => true]
        : ['ok' => false, 'error' => 'Request not found or already handled.']);
    exit;
}

if ($action !== 'accept' || !$sched || !$details || strtotime($sched) <= time()) {
    echo json_encode(['ok' => false, 'error' => 'Choose a valid future date and time.']);
    exit;
}
$sched = date('Y-m-d H:i:s', strtotime($sched));

// Verify the request is open and matches this volunteer's skills.
$req = $db->prepare(
    'SELECT sr.*
     FROM support_requests sr
     JOIN volunteer_skills vs
       ON vs.user_id = ? AND vs.skill_type = sr.category
     WHERE sr.id = ? AND sr.status IN ("open","selected") AND sr.student_id != ?
       AND (sr.selected_volunteer_id IS NULL OR sr.selected_volunteer_id = ?)
     LIMIT 1'
);
$req->execute([$u['id'], $reqId, $u['id'], $u['id']]);
$req = $req->fetch();

if (!$req) {
    echo json_encode(['ok' => false, 'error' => 'Request not found or already handled.']);
    exit;
}

try {
    $db->beginTransaction();
    $db->prepare(
        'INSERT INTO sessions
         (request_id, volunteer_id, student_id, scheduled_datetime, mode, meeting_details)
         VALUES (?, ?, ?, ?, ?, ?)'
    )->execute([$reqId, $u['id'], $req['student_id'], $sched, $req['preferred_mode'], $details]);

    $db->prepare('UPDATE support_requests SET status = "accepted", selected_volunteer_id = ? WHERE id = ? AND status IN ("open","selected")')
       ->execute([$u['id'], $reqId]);

    $db->commit();
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    echo json_encode(['ok' => false, 'error' => 'This request could not be accepted.']);
}