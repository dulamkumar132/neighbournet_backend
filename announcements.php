<?php
// Prevent any output before JSON
error_reporting(0);
ini_set('display_errors', 0);

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Start output buffering to catch any unexpected output
ob_start();

try {
    include "db.php";
    
    // First check if announcements table exists, if not create it
    $check_table = mysqli_query($conn, "SHOW TABLES LIKE 'announcements'");
    if (mysqli_num_rows($check_table) == 0) {
        // Create announcements table
        $create_table = "CREATE TABLE announcements (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            content TEXT NOT NULL,
            user_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX (user_id),
            INDEX (created_at)
        )";
        
        if (!mysqli_query($conn, $create_table)) {
            throw new Exception("Failed to create announcements table: " . mysqli_error($conn));
        }
    }
    
    $action = $_GET['action'] ?? 'list';
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'list') {
        // Fetch announcements
        $stmt = mysqli_prepare($conn, 
            "SELECT a.id, a.title, a.content, a.created_at, u.full_name as author 
             FROM announcements a 
             LEFT JOIN users u ON a.user_id = u.id 
             ORDER BY a.created_at DESC 
             LIMIT 50"
        );
        
        if (!$stmt) {
            throw new Exception("Database prepare failed: " . mysqli_error($conn));
        }
        
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $announcements = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $announcements[] = [
                'id' => (int)$row['id'],
                'title' => $row['title'],
                'content' => $row['content'],
                'author' => $row['author'],
                'created_at' => $row['created_at']
            ];
        }
        
        mysqli_stmt_close($stmt);
        
        echo json_encode([
            "status" => "success",
            "message" => "Announcements retrieved successfully",
            "data" => $announcements
        ]);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Create new announcement
        $input = file_get_contents("php://input");
        $data = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON data");
        }
        
        $title = trim($data['title'] ?? '');
        $content = trim($data['content'] ?? '');
        $user_id = (int)($data['user_id'] ?? 0);
        
        if (empty($title) || empty($content) || $user_id <= 0) {
            throw new Exception("Title, content, and user_id are required");
        }
        
        $stmt = mysqli_prepare($conn, 
            "INSERT INTO announcements (title, content, user_id, created_at) 
             VALUES (?, ?, ?, NOW())"
        );
        
        if (!$stmt) {
            throw new Exception("Database prepare failed: " . mysqli_error($conn));
        }
        
        mysqli_stmt_bind_param($stmt, "ssi", $title, $content, $user_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $announcement_id = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);
            
            echo json_encode([
                "status" => "success",
                "message" => "Announcement created successfully",
                "data" => [
                    "announcement_id" => $announcement_id
                ]
            ]);
        } else {
            mysqli_stmt_close($stmt);
            throw new Exception("Failed to create announcement: " . mysqli_error($conn));
        }
        
    } else {
        http_response_code(405);
        throw new Exception("Method not allowed");
    }
    
} catch (Exception $e) {
    // Clear any output buffer and return error
    ob_clean();
    echo json_encode([
        "status" => "error",
        "message" => "Server error: " . $e->getMessage(),
        "data" => null
    ]);
}

// Clean output buffer and send response
ob_end_clean();

if (isset($conn) && $conn) {
    mysqli_close($conn);
}
?>