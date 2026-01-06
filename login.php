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
    
    // Read JSON input
    $input = file_get_contents("php://input");
    $data = json_decode($input, true);
    
    // Check if JSON is valid
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON data");
    }
    
    $mobile = trim($data['mobile_number'] ?? '');
    $password = $data['password'] ?? '';
    
    // Validate input
    if (empty($mobile) || empty($password)) {
        throw new Exception("Mobile number and password are required");
    }
    
    // Fetch user by mobile number
    $stmt = mysqli_prepare($conn, "SELECT id, full_name, email, password FROM users WHERE mobile_number = ?");
    if (!$stmt) {
        throw new Exception("Database prepare failed: " . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($stmt, "s", $mobile);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($row = mysqli_fetch_assoc($result)) {
        // Verify password
        if (password_verify($password, $row['password'])) {
            mysqli_stmt_close($stmt);
            
            echo json_encode([
                "status" => "success",
                "message" => "Login successful",
                "data" => [
                    "user_id" => (int)$row['id'],
                    "name" => $row['full_name'],
                    "email" => $row['email'],
                    "token" => "dummy_token_" . $row['id'] // Simple token for now
                ]
            ]);
        } else {
            mysqli_stmt_close($stmt);
            // Add delay to prevent brute force attacks
            sleep(1);
            throw new Exception("Invalid credentials");
        }
    } else {
        mysqli_stmt_close($stmt);
        // Add delay to prevent user enumeration
        sleep(1);
        throw new Exception("Invalid credentials");
    }
    
} catch (Exception $e) {
    http_response_code(401);
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
