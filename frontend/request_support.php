<?php
include '../backend/db_connect.php';
$message = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $category = $conn->real_escape_string($_POST['category']);
    $description = $conn->real_escape_string($_POST['description']);
    $datetime = $conn->real_escape_string($_POST['preferred_datetime']);
    $mode = $conn->real_escape_string($_POST['mode']);
    $sql = "INSERT INTO SUPPORT_REQUESTS (student_id, category, description, preferred_datetime, mode, status) VALUES (1, '$category', '$description', '$datetime', '$mode', 'Pending')";
    if ($conn->query($sql) === TRUE) {
        $message = '<div class="alert alert-success">Request Submitted!</div>';
    }
}
?>
<!DOCTYPE html>
<html>
<head><title>Request Support</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="bg-light"><div class="container mt-5"><div class="card p-4">
<h2>Request Support (Misha)</h2><?php echo $message; ?>
<form method="POST">
<div class="mb-3"><label>Category</label><select name="category" class="form-select"><option>Academic Support</option><option>Tech Support</option></select></div>
<div class="mb-3"><label>Description</label><textarea name="description" class="form-control" required></textarea></div>
<div class="mb-3"><label>Date & Time</label><input type="datetime-local" name="preferred_datetime" class="form-control" required></div>
<div class="mb-3"><label>Mode</label><select name="mode" class="form-select"><option>Virtual</option><option>F2F</option></select></div>
<button type="submit" class="btn btn-primary w-100">Submit</button>
</form></div></div></body></html>