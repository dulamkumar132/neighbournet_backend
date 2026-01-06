<?php
// Prevent any output before JSON
error_reporting(0);
ini_set('display_errors', 0);

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");

// Start output buffering to catch any unexpected output
ob_start();

try {
    include "db.php";

$data = json_decode(file_get_contents("php://input"), true);
$action = $data['action'] ?? $_GET['action'] ?? 'list';

/* ADD SKILL */
if ($action === 'add') {
    // First check if skills table exists, if not create it
    $check_table = mysqli_query($conn, "SHOW TABLES LIKE 'skills'");
    if (mysqli_num_rows($check_table) == 0) {
        // Create skills table
        $create_table = "CREATE TABLE skills (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            skill_name VARCHAR(255) NOT NULL,
            description TEXT,
            status VARCHAR(50) DEFAULT 'available',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (user_id),
            INDEX (skill_name)
        )";
        
        if (!mysqli_query($conn, $create_table)) {
            echo json_encode([
                "status" => "error",
                "message" => "Failed to create skills table: " . mysqli_error($conn),
                "data" => null
            ]);
            exit;
        }
    }
    
    $user_id = $data['user_id'] ?? 0;
    $skill_name = $data['skill_name'] ?? '';
    $description = $data['description'] ?? '';
    
    if (empty($skill_name)) {
        echo json_encode([
            "status" => "error",
            "message" => "Skill name is required",
            "data" => null
        ]);
        exit;
    }
    
    $stmt = mysqli_prepare($conn,
        "INSERT INTO skills (user_id, skill_name, description)
         VALUES (?, ?, ?)");
    
    mysqli_stmt_bind_param($stmt, "iss", $user_id, $skill_name, $description);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode([
            "status" => "success",
            "message" => "Skill added successfully",
            "data" => "Skill added"
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Failed to add skill: " . mysqli_error($conn),
            "data" => null
        ]);
    }
    mysqli_stmt_close($stmt);
}

/* LIST ALL SKILLS */
else if ($action === 'list') {
    // First check if skills table exists, if not create it
    $check_table = mysqli_query($conn, "SHOW TABLES LIKE 'skills'");
    if (mysqli_num_rows($check_table) == 0) {
        // Create skills table
        $create_table = "CREATE TABLE skills (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            skill_name VARCHAR(255) NOT NULL,
            description TEXT,
            status VARCHAR(50) DEFAULT 'available',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (user_id),
            INDEX (skill_name)
        )";
        
        if (!mysqli_query($conn, $create_table)) {
            echo json_encode([
                "status" => "error",
                "message" => "Failed to create skills table: " . mysqli_error($conn),
                "data" => null
            ]);
            exit;
        }
    }
    
    $user_id = $_GET['user_id'] ?? null;
    
    if ($user_id) {
        // Get skills for specific user
        $stmt = mysqli_prepare($conn,
            "SELECT s.*, u.full_name as user_name, u.flat_number
             FROM skills s
             LEFT JOIN users u ON s.user_id = u.id
             WHERE s.user_id = ?
             ORDER BY s.created_at DESC");
        mysqli_stmt_bind_param($stmt, "i", $user_id);
    } else {
        // Get all skills
        $stmt = mysqli_prepare($conn,
            "SELECT s.*, u.full_name as user_name, u.flat_number
             FROM skills s
             LEFT JOIN users u ON s.user_id = u.id
             ORDER BY s.created_at DESC");
    }
    
    if (!mysqli_stmt_execute($stmt)) {
        echo json_encode([
            "status" => "error",
            "message" => "Database query failed: " . mysqli_error($conn),
            "data" => null
        ]);
        exit;
    }
    
    $result = mysqli_stmt_get_result($stmt);
    
    $skills = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $skills[] = $row;
    }
    
    echo json_encode([
        "status" => "success",
        "message" => "Skills retrieved successfully",
        "data" => $skills
    ]);
    mysqli_stmt_close($stmt);
}

/* SEARCH SKILLS */
else if ($action === 'search') {
    // First check if skills table exists, if not create it
    $check_table = mysqli_query($conn, "SHOW TABLES LIKE 'skills'");
    if (mysqli_num_rows($check_table) == 0) {
        // Create skills table
        $create_table = "CREATE TABLE skills (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            skill_name VARCHAR(255) NOT NULL,
            description TEXT,
            status VARCHAR(50) DEFAULT 'available',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (user_id),
            INDEX (skill_name)
        )";
        
        if (!mysqli_query($conn, $create_table)) {
            echo json_encode([
                "status" => "error",
                "message" => "Failed to create skills table: " . mysqli_error($conn),
                "data" => null
            ]);
            exit;
        }
    }
    
    $search_term = $_GET['query'] ?? '';
    
    if (!empty($search_term)) {
        $search_param = "%{$search_term}%";
        $stmt = mysqli_prepare($conn,
            "SELECT s.*, u.full_name as user_name, u.flat_number, u.mobile_number
             FROM skills s
             LEFT JOIN users u ON s.user_id = u.id
             WHERE s.skill_name LIKE ? OR s.description LIKE ?
             ORDER BY s.created_at DESC");
        mysqli_stmt_bind_param($stmt, "ss", $search_param, $search_param);
    } else {
        // Return all if no search term
        $stmt = mysqli_prepare($conn,
            "SELECT s.*, u.full_name as user_name, u.flat_number, u.mobile_number
             FROM skills s
             LEFT JOIN users u ON s.user_id = u.id
             ORDER BY s.created_at DESC");
    }
    
    if (!mysqli_stmt_execute($stmt)) {
        echo json_encode([
            "status" => "error",
            "message" => "Database query failed: " . mysqli_error($conn),
            "data" => null
        ]);
        exit;
    }
    
    $result = mysqli_stmt_get_result($stmt);
    
    $skills = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $skills[] = $row;
    }
    
    echo json_encode([
        "status" => "success",
        "message" => "Skills retrieved successfully",
        "data" => $skills
    ]);
    mysqli_stmt_close($stmt);
}

/* DELETE SKILL */
else if ($action === 'delete') {
    $skill_id = $data['skill_id'] ?? 0;
    
    $stmt = mysqli_prepare($conn, "DELETE FROM skills WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $skill_id);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode([
            "status" => "success",
            "message" => "Skill deleted",
            "data" => "Skill deleted"
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Failed to delete skill",
            "data" => null
        ]);
    }
    mysqli_stmt_close($stmt);
}

else {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid action",
        "data" => null
    ]);
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

mysqli_close($conn);
?>