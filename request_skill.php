<?php
header("Content-Type: application/json");
include "db.php";

/* Read JSON input */
$data = json_decode(file_get_contents("php://input"), true);

/* Get values */
$user_id     = $data['user_id'] ?? '';
$skill_name  = $data['skill_name'] ?? '';
$description = $data['description'] ?? '';

/* Validate */
if ($user_id === '' || $skill_name === '' || $description === '') {
    echo json_encode([
        "status" => "error",
        "message" => "All fields required"
    ]);
    exit;
}

/* Insert request */
$stmt = mysqli_prepare(
    $conn,
    "INSERT INTO skill_requests (user_id, skill_name, description)
     VALUES (?, ?, ?)"
);

mysqli_stmt_bind_param($stmt, "iss", $user_id, $skill_name, $description);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode([
        "status" => "success",
        "message" => "Skill request submitted"
    ]);
} else {
    echo json_encode([
        "status" => "error",
        "message" => "Database error"
    ]);
}
