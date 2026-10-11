<?php
require_once '../config.php';
requireLogin();

$u = currentUser();

// Support both JSON (AJAX) and form POST
if ($_SERVER['CONTENT_TYPE'] === 'application/json' || strpos($_SERVER['CONTENT_TYPE'] ?? '', 'json') !== false) {
    $raw = json_decode(file_get_contents('php://input'), true);
    $isJson = true;
} else {
    $raw = $_POST;
    $isJson = false;
}

$action    = $raw['action']       ?? '';
$sessionId = (int)($raw['session_id'] ?? 0);
$csrf      = $raw['csrf']         ?? '';
$newDt     = $raw['new_datetime'] ?? null;

if (!hash_equals(csrfToken(), $csrf)) {
    if ($isJson) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'CSRF mismatch.']);
    } else {
        setFlash('error', 'Security error. Please try again.');
        header('Location: ../sessions.php');
    }
    exit;
}

$db  = db();

// Verify session belongs to user
$session = $db->prepare(
    'SELECT * FROM sessions WHERE id = ? AND (student_id = ? OR volunteer_id = ?) LIMIT 1'
);
$session->execute([$sessionId, $u['id'], $u['id']]);
$session = $session->fetch();

if (!$session) {
    $msg = 'Session not found.';
    if ($isJson) { header('Content-Type: application/json'); echo json_encode(['ok'=>false,'error'=>$msg]); exit; }
    setFlash('error', $msg); redirect('../sessions.php');
}

switch ($action) {
    case 'complete':
        $db->prepare('UPDATE sessions SET status = "completed" WHERE id = ?')->execute([$sessionId]);
        break;

    case 'cancel':
        $db->prepare('UPDATE sessions SET status = "cancelled" WHERE id = ?')->execute([$sessionId]);
        $db->prepare('UPDATE support_requests SET status = "open" WHERE id = ?')->execute([$session['request_id']]);
        break;

    case 'reschedule':
        if (!$newDt) {
            $msg = 'New date/time required.';
            if ($isJson) { header('Content-Type: application/json'); echo json_encode(['ok'=>false,'error'=>$msg]); exit; }
            setFlash('error', $msg); redirect('../sessions.php');
        }
        $db->prepare('UPDATE sessions SET scheduled_datetime = ?, status = "upcoming" WHERE id = ?')
           ->execute([$newDt, $sessionId]);
        break;

    default:
        $msg = 'Unknown action.';
        if ($isJson) { header('Content-Type: application/json'); echo json_encode(['ok'=>false,'error'=>$msg]); exit; }
        setFlash('error', $msg); redirect('../sessions.php');
}

if ($isJson) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
} else {
    setFlash('success', ucfirst($action) . ' successful.');
    header('Location: ../sessions.php');
}
