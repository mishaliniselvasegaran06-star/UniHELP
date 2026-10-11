<?php
include '../backend/db_connect.php';
if (isset($_GET['complete_id'])) {
    $id = intval($_GET['complete_id']);
    $conn->query("UPDATE SESSIONS SET status = 'Completed' WHERE session_id = $id");
    header("Location: manage_sessions.php"); exit();
}
$result = $conn->query("SELECT S.session_id, SR.category, SR.description, S.status FROM SESSIONS S JOIN SUPPORT_REQUESTS SR ON S.request_id = SR.request_id");
?>
<!DOCTYPE html>
<html>
<head><title>Sessions</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="bg-light"><div class="container mt-5">
<h2>Manage Sessions (Charmaine)</h2><div class="row">
<?php while($row = $result->fetch_assoc()): ?>
<div class="col-md-6 mb-3"><div class="card p-3">
<h5><?php echo $row['category']; ?></h5><p><?php echo $row['description']; ?><br>Status: <b><?php echo $row['status']; ?></b></p>
<?php if($row['status'] != 'Completed'): ?>
<a href="manage_sessions.php?complete_id=<?php echo $row['session_id']; ?>" class="btn btn-success btn-sm">Mark Completed</a>
<?php else: ?>
<a href="submit_feedback.php?session_id=<?php echo $row['session_id']; ?>" class="btn btn-primary btn-sm">Give Feedback</a>
<?php endif; ?>
</div></div><?php endwhile; ?>
</div></div></body></html>