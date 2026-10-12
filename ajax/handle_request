<?php
require_once '../config.php';
requireLogin();

header('Content-Type: application/json');

$raw    = json_decode(file_get_contents('php://input'), true);
$action = $raw['action']     ?? '';
$reqId  = (int)($raw['request_id'] ?? 0);
$csrf   = $raw['csrf']       ?? '';
$sched  = $raw['scheduled_at'] ?? date('Y-m-d H:i:s', strtotime('+2 days'));

if (!hash_equals(csrfToken(), $csrf)) {
    echo json_encode(['ok' => false, 'error' => 'CSRF mismatch.']);
    exit;
}

$u  = currentUser();
$db = db();

if (!$reqId) {
    echo json_encode(['ok' => false, 'error' => 'Invalid request.']);
    exit;
}

// Verify request is open
$req = $db->prepare('SELECT * FROM support_requests WHERE id = ? AND status = "open" LIMIT 1');
$req->execute([$reqId]);
$req = $req->fetch();

if (!$req) {
    echo json_encode(['ok' => false, 'error' => 'Request not found or already handled.']);
    exit;
}

if ($action === 'accept') {
    // Create session
    $db->prepare(
        'INSERT INTO sessions (request_id, volunteer_id, student_id, scheduled_datetime, mode)
         VALUES (?, ?, ?, ?, ?)'
    )->execute([$reqId, $u['id'], $req['student_id'], $sched, $req['mode']]);

    // Update request status
    $db->prepare('UPDATE support_requests SET status = "matched" WHERE id = ?')->execute([$reqId]);

    // Notify student
    $db->prepare(
        'INSERT INTO notifications (user_id, message, link)
         VALUES (?, ?, ?)'
    )->execute([
        $req['student_id'],
        "Your support request has been accepted by a volunteer!",
        'sessions.php',
    ]);

    echo json_encode(['ok' => true]);

} elseif ($action === 'reject') {
    // Just leave it open so another volunteer can take it; log the rejection
    // In a full app you'd track which volunteers rejected each request.
    echo json_encode(['ok' => true]);

} else {
    echo json_encode(['ok' => false, 'error' => 'Unknown action.']);
}
