<?php
header("Content-Type: application/json");
include "db.php";

$user_id     = $_POST['user_id'] ?? '';
$full_name   = $_POST['full_name'] ?? '';
$mobile      = $_POST['mobile_number'] ?? '';
$email       = $_POST['email'] ?? '';
$flat_number = $_POST['flat_number'] ?? '';

if ($user_id == '' || $full_name == '' || $mobile == '') {
    echo json_encode([
        "status" => "error",
        "message" => "Required fields missing"
    ]);
    exit;
}

$query = "
    UPDATE users 
    SET 
        full_name = ?, 
        mobile_number = ?, 
        email = ?, 
        flat_number = ?
    WHERE id = ?
";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param(
    $stmt,
    "ssssi",
    $full_name,
    $mobile,
    $email,
    $flat_number,
    $user_id
);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode([
        "status" => "success",
        "message" => "Profile updated successfully"
    ]);
} else {
    echo json_encode([
        "status" => "error",
        "message" => "Update failed"
    ]);
}
