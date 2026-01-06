<?php
// Database configuration for XAMPP
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "neighbournet_db";

// Create mysqli connection
$conn = mysqli_connect($servername, $username, $password, $dbname);

// Check connection
if (!$conn) {
    http_response_code(500);
    die(json_encode([
        "status" => "error",
        "message" => "Database connection failed: " . mysqli_connect_error(),
        "error_code" => mysqli_connect_errno()
    ]));
}

// Set charset to UTF-8
mysqli_set_charset($conn, "utf8");
?>
