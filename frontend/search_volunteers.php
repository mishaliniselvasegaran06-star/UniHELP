<?php
include '../backend/db_connect.php';
$search = $_GET['skill'] ?? '';
$sql = "SELECT U.full_name, V.skill_category, V.availability, V.preferred_mode FROM VOLUNTEER_PROFILES V JOIN USERS U ON V.user_id = U.user_id WHERE V.skill_category LIKE '%$search%'";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html>
<head><title>Search Volunteers</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="bg-light"><div class="container mt-5">
<h2>Find Volunteers (Misha)</h2>
<form method="GET" class="row g-3 mb-4">
<div class="col-auto"><input type="text" name="skill" class="form-control" placeholder="Filter by skill..." value="<?php echo htmlspecialchars($search); ?>"></div>
<div class="col-auto"><button type="submit" class="btn btn-primary">Search</button></div>
</form>
<div class="row">
<?php while($row = $result->fetch_assoc()): ?>
<div class="col-md-4 mb-3"><div class="card p-3">
<h5><?php echo htmlspecialchars($row['full_name']); ?></h5>
<p>Skill: <?php echo htmlspecialchars($row['skill_category']); ?><br>Availability: <?php echo htmlspecialchars($row['availability']); ?></p>
</div></div>
<?php endwhile; ?>
</div></div></body></html>