<?php
header("Content-Type: application/json");
include "db.php";

$data = json_decode(file_get_contents("php://input"), true);

$title = $data['title'] ?? '';
$message = $data['message'] ?? '';
$created_by = $data['created_by'] ?? 0;

if (empty($title) || empty($message)) {
    echo json_encode([
        "status" => "error",
        "message" => "Title and message are required"
    ]);
    exit;
}

$stmt = mysqli_prepare($conn,
    "INSERT INTO announcements (title, message, created_by)
     VALUES (?, ?, ?)");

mysqli_stmt_bind_param($stmt, "ssi", $title, $message, $created_by);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode([
        "status" => "success",
        "message" => "Announcement created successfully"
    ]);
} else {
    echo json_encode([
        "status" => "error",
        "message" => "Failed to create announcement"
    ]);
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
?>
