<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        "status" => "error",
        "message" => "Method not allowed. Use POST.",
        "data" => null
    ]);
    exit();
}

try {
    include "db.php";
    
    // Get POST data (JSON)
    $input = file_get_contents("php://input");
    $data = json_decode($input, true);
    
    // Check if JSON is valid
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON data");
    }
    
    // Extract and validate required fields
    $name = trim($data['full_name'] ?? '');
    $mobile = trim($data['mobile_number'] ?? '');
    $email = trim($data['email'] ?? '');
    $flat = trim($data['flat_number'] ?? '');
    $pass = $data['password'] ?? '';
    
    // Validate required fields
    if (empty($name) || empty($mobile) || empty($pass)) {
        throw new Exception("Name, mobile number, and password are required");
    }
    
    // Validate mobile number format (basic validation)
    if (!preg_match('/^[0-9+\-\s()]{10,15}$/', $mobile)) {
        throw new Exception("Invalid mobile number format");
    }
    
    // Validate email if provided
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception("Invalid email format");
    }
    
    // Hash password
    $password = password_hash($pass, PASSWORD_DEFAULT);
    
    // Check if mobile already exists
    $check = mysqli_prepare($conn, "SELECT id FROM users WHERE mobile_number = ?");
    if (!$check) {
        throw new Exception("Database prepare failed: " . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($check, "s", $mobile);
    mysqli_stmt_execute($check);
    mysqli_stmt_store_result($check);
    
    if (mysqli_stmt_num_rows($check) > 0) {
        mysqli_stmt_close($check);
        throw new Exception("Mobile number already registered");
    }
    mysqli_stmt_close($check);
    
    // Insert new user
    $stmt = mysqli_prepare($conn, 
        "INSERT INTO users (full_name, mobile_number, email, flat_number, password, created_at) 
         VALUES (?, ?, ?, ?, ?, NOW())"
    );
    
    if (!$stmt) {
        throw new Exception("Database prepare failed: " . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($stmt, "sssss", $name, $mobile, $email, $flat, $password);
    
    if (mysqli_stmt_execute($stmt)) {
        $user_id = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);
        
        echo json_encode([
            "status" => "success",
            "message" => "Registration successful",
            "data" => "User registered successfully"
        ]);
    } else {
        mysqli_stmt_close($stmt);
        throw new Exception("Registration failed: " . mysqli_error($conn));
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage(),
        "data" => null
    ]);
} finally {
    if (isset($conn) && $conn) {
        mysqli_close($conn);
    }
}
?>
