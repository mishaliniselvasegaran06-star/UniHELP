<?php
include '../backend/db_connect.php';
$message = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $student_id = $conn->real_escape_string($_POST['student_id']);
    $full_name = $conn->real_escape_string($_POST['full_name']);
    $email = $conn->real_escape_string($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $otp_code = rand(100000, 999999);

    $sql = "INSERT INTO USERS (student_id, full_name, email, password_hash, otp_code, is_verified) 
            VALUES ('$student_id', '$full_name', '$email', '$password', '$otp_code', FALSE)";

    if ($conn->query($sql) === TRUE) {
        $message = '<div class="alert alert-success">Registration successful! OTP Code: <strong>'.$otp_code.'</strong>. <a href="verify_otp.php?email='.$email.'">Verify OTP</a></div>';
    } else {
        $message = '<div class="alert alert-danger">Error: ' . $conn->error . '</div>';
    }
}
?>
<!DOCTYPE html>
<html>
<head><title>Register</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="bg-light"><div class="container mt-5"><div class="col-md-6 mx-auto card p-4">
<h2>Register (Jaya)</h2><?php echo $message; ?>
<form method="POST">
<div class="mb-3"><label>Student ID</label><input type="text" name="student_id" class="form-control" required></div>
<div class="mb-3"><label>Full Name</label><input type="text" name="full_name" class="form-control" required></div>
<div class="mb-3"><label>Email</label><input type="email" name="email" class="form-control" required></div>
<div class="mb-3"><label>Password</label><input type="password" name="password" class="form-control" required></div>
<button type="submit" class="btn btn-primary w-100">Register</button>
</form></div></div></body></html>