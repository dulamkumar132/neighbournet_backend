<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

include "db.php";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get JSON input
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    if (!$data) {
        echo json_encode([
            "status" => "error",
            "message" => "Invalid JSON data"
        ]);
        exit;
    }
    
    // Extract poll data
    $user_id = isset($data['user_id']) ? intval($data['user_id']) : 0;
    $question = isset($data['question']) ? trim($data['question']) : '';
    $option1 = isset($data['option1']) ? trim($data['option1']) : '';
    $option2 = isset($data['option2']) ? trim($data['option2']) : '';
    $option3 = isset($data['option3']) ? trim($data['option3']) : '';
    $option4 = isset($data['option4']) ? trim($data['option4']) : '';
    
    // Validate required fields
    if (empty($user_id) || empty($question) || empty($option1) || empty($option2)) {
        echo json_encode([
            "status" => "error",
            "message" => "Missing required fields: user_id, question, option1, option2"
        ]);
        exit;
    }
    
    // Insert poll into database
    $sql = "INSERT INTO polls (user_id, question, option1, option2, option3, option4, created_at, status) 
            VALUES (?, ?, ?, ?, ?, ?, NOW(), 'active')";
    
    $stmt = mysqli_prepare($conn, $sql);
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "isssss", $user_id, $question, $option1, $option2, $option3, $option4);
        
        if (mysqli_stmt_execute($stmt)) {
            $poll_id = mysqli_insert_id($conn);
            echo json_encode([
                "status" => "success",
                "message" => "Poll created successfully",
                "poll_id" => $poll_id
            ]);
        } else {
            echo json_encode([
                "status" => "error",
                "message" => "Failed to create poll: " . mysqli_error($conn)
            ]);
        }
        
        mysqli_stmt_close($stmt);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Database error: " . mysqli_error($conn)
        ]);
    }
} else {
    echo json_encode([
        "status" => "error",
        "message" => "Only POST method allowed"
    ]);
}

mysqli_close($conn);
?>
