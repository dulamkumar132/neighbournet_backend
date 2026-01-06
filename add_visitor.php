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
    
    // Extract visitor data
    $user_id = isset($data['user_id']) ? intval($data['user_id']) : 0;
    $visitor_name = isset($data['visitor_name']) ? trim($data['visitor_name']) : '';
    $visitor_phone = isset($data['visitor_phone']) ? trim($data['visitor_phone']) : '';
    $visit_date = isset($data['visit_date']) ? trim($data['visit_date']) : '';
    $visit_time = isset($data['visit_time']) ? trim($data['visit_time']) : '';
    $purpose = isset($data['purpose']) ? trim($data['purpose']) : '';
    
    // Validate required fields
    if (empty($user_id) || empty($visitor_name) || empty($visitor_phone) || empty($visit_date)) {
        echo json_encode([
            "status" => "error",
            "message" => "Missing required fields: user_id, visitor_name, visitor_phone, visit_date"
        ]);
        exit;
    }
    
    // Insert visitor into database
    $sql = "INSERT INTO visitors (user_id, visitor_name, visitor_phone, visit_date, visit_time, purpose, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())";
    
    $stmt = mysqli_prepare($conn, $sql);
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "isssss", $user_id, $visitor_name, $visitor_phone, $visit_date, $visit_time, $purpose);
        
        if (mysqli_stmt_execute($stmt)) {
            $visitor_id = mysqli_insert_id($conn);
            echo json_encode([
                "status" => "success",
                "message" => "Visitor added successfully",
                "visitor_id" => $visitor_id
            ]);
        } else {
            echo json_encode([
                "status" => "error",
                "message" => "Failed to add visitor: " . mysqli_error($conn)
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
