<?php
include '../backend/db_connect.php';
$message = "";
$email = $_GET['email'] ?? '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $conn->real_escape_string($_POST['email']);
    $entered_otp = $conn->real_escape_string($_POST['otp_code']);
    $result = $conn->query("SELECT * FROM USERS WHERE email='$email' AND otp_code='$entered_otp'");
    if ($result->num_rows > 0) {
        $conn->query("UPDATE USERS SET is_verified = TRUE, otp_code = NULL WHERE email='$email'");
        $message = '<div class="alert alert-success">2FA Verified Successfully!</div>';
    } else {
        $message = '<div class="alert alert-danger">Invalid OTP code.</div>';
    }
}
?>
<!DOCTYPE html>
<html>
<head><title>2FA</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="bg-light"><div class="container mt-5"><div class="col-md-5 mx-auto card p-4 text-center">
<h3>2FA Verification</h3><?php echo $message; ?>
<form method="POST"><input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
<div class="mb-3"><input type="text" name="otp_code" class="form-control text-center fs-3" maxlength="6" placeholder="______" required></div>
<button type="submit" class="btn btn-success w-100">Verify</button></form></div></div></body></html>