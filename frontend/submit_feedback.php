<?php
include '../backend/db_connect.php';
$message = ""; $session_id = $_GET['session_id'] ?? 1;
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $sid = intval($_POST['session_id']); $rating = intval($_POST['rating']); $comments = $conn->real_escape_string($_POST['comments']);
    $res = $conn->query("SELECT request_id FROM SESSIONS WHERE session_id = $sid");
    if($res->num_rows > 0) {
        $req_id = $res->fetch_assoc()['request_id'];
        $conn->query("INSERT INTO FEEDBACK (request_id, rating, comments) VALUES ($req_id, $rating, '$comments')");
        $message = '<div class="alert alert-success">Feedback Saved!</div>';
    }
}
?>
<!DOCTYPE html>
<html>
<head><title>Feedback</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="bg-light"><div class="container mt-5"><div class="col-md-6 mx-auto card p-4">
<h3>Feedback (Charmaine)</h3><?php echo $message; ?>
<form method="POST"><input type="hidden" name="session_id" value="<?php echo $session_id; ?>">
<div class="mb-3"><label>Rating</label><select name="rating" class="form-select"><option value="5">5 - Excellent</option><option value="4">4 - Good</option></select></div>
<div class="mb-3"><label>Comments</label><textarea name="comments" class="form-control"></textarea></div>
<button type="submit" class="btn btn-primary w-100">Submit</button></form></div></div></body></html>