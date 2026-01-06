<?php
header("Content-Type: application/json");
include "db.php";

/* Read JSON body */
$data = json_decode(file_get_contents("php://input"), true);

/* Validate */
$user_id = $data['user_id'] ?? '';
$skill_name = $data['skill_name'] ?? '';
$description = $data['description'] ?? '';

if ($user_id === '' || $skill_name === '' || $description === '') {
    echo json_encode([
        "status" => "error",
        "message" => "All fields required"
    ]);
    exit;
}

/* Insert skill */
$stmt = mysqli_prepare(
    $conn,
    "INSERT INTO skills (user_id, skill_name, description) VALUES (?, ?, ?)"
);

mysqli_stmt_bind_param($stmt, "iss", $user_id, $skill_name, $description);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode([
        "status" => "success",
        "message" => "Skill added successfully"
    ]);
} else {
    echo json_encode([
        "status" => "error",
        "message" => "Failed to add skill"
    ]);
}
