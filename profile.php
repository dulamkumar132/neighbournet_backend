<?php
header("Content-Type: application/json");
include "db.php";

$user_id = $_POST['user_id'] ?? '';

if ($user_id == '') {
    echo json_encode([
        "status" => "error",
        "message" => "User ID required"
    ]);
    exit;
}

$query = "
    SELECT 
        full_name,
        mobile_number,
        email,
        flat_number,
        created_at
    FROM users
    WHERE id = ?
";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($row = mysqli_fetch_assoc($result)) {
    echo json_encode([
        "status" => "success",
        "data" => $row
    ]);
} else {
    echo json_encode([
        "status" => "error",
        "message" => "User not found"
    ]);
}
