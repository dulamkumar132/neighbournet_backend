<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT");

include "db.php";

$data = json_decode(file_get_contents("php://input"), true);
$action = $data['action'] ?? $_GET['action'] ?? 'list';

// First check if complaints table exists, if not create it
$check_table = mysqli_query($conn, "SHOW TABLES LIKE 'complaints'");
if (mysqli_num_rows($check_table) == 0) {
    // Create complaints table
    $create_table = "CREATE TABLE complaints (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        category VARCHAR(100) NOT NULL,
        description TEXT NOT NULL,
        status VARCHAR(50) DEFAULT 'open',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX (user_id),
        INDEX (status),
        INDEX (created_at)
    )";
    
    if (!mysqli_query($conn, $create_table)) {
        echo json_encode([
            "status" => "error",
            "message" => "Failed to create complaints table: " . mysqli_error($conn),
            "data" => null
        ]);
        exit;
    }
}

/* CREATE COMPLAINT */
if ($action === 'create') {
    $user_id = $data['user_id'] ?? 0;
    $category = $data['category'] ?? '';
    $description = $data['description'] ?? '';
    
    if (empty($category) || empty($description)) {
        echo json_encode([
            "status" => "error",
            "message" => "Category and description are required",
            "data" => null
        ]);
        exit;
    }
    
    $stmt = mysqli_prepare($conn,
        "INSERT INTO complaints (user_id, category, description)
         VALUES (?, ?, ?)");
    
    mysqli_stmt_bind_param($stmt, "iss", $user_id, $category, $description);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode([
            "status" => "success",
            "message" => "Complaint filed successfully",
            "data" => "Complaint created"
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Failed to file complaint: " . mysqli_error($conn),
            "data" => null
        ]);
    }
    mysqli_stmt_close($stmt);
}

/* LIST COMPLAINTS */
else if ($action === 'list') {
    $user_id = $_GET['user_id'] ?? 0;
    
    if ($user_id > 0) {
        // Get complaints for specific user
        $stmt = mysqli_prepare($conn,
            "SELECT * FROM complaints WHERE user_id = ? ORDER BY created_at DESC");
        mysqli_stmt_bind_param($stmt, "i", $user_id);
    } else {
        // Get all complaints (for admin)
        $stmt = mysqli_prepare($conn,
            "SELECT c.*, u.full_name, u.flat_number 
             FROM complaints c
             LEFT JOIN users u ON c.user_id = u.id
             ORDER BY c.created_at DESC");
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
    
    $complaints = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $complaints[] = $row;
    }
    
    echo json_encode([
        "status" => "success",
        "message" => "Complaints retrieved successfully",
        "data" => $complaints
    ]);
    mysqli_stmt_close($stmt);
}

/* UPDATE COMPLAINT STATUS */
else if ($action === 'update_status') {
    $complaint_id = $data['complaint_id'] ?? 0;
    $status = $data['status'] ?? 'open';
    
    $stmt = mysqli_prepare($conn,
        "UPDATE complaints SET status = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "si", $status, $complaint_id);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode([
            "status" => "success",
            "message" => "Complaint status updated",
            "data" => "Status updated"
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Failed to update status: " . mysqli_error($conn),
            "data" => null
        ]);
    }
    mysqli_stmt_close($stmt);
}

/* GET COMPLAINT DETAILS */
else if ($action === 'details') {
    $complaint_id = $_GET['complaint_id'] ?? 0;
    
    $stmt = mysqli_prepare($conn,
        "SELECT c.*, u.full_name, u.flat_number, u.mobile_number
         FROM complaints c
         LEFT JOIN users u ON c.user_id = u.id
         WHERE c.id = ?");
    mysqli_stmt_bind_param($stmt, "i", $complaint_id);
    
    if (!mysqli_stmt_execute($stmt)) {
        echo json_encode([
            "status" => "error",
            "message" => "Database query failed: " . mysqli_error($conn),
            "data" => null
        ]);
        exit;
    }
    
    $result = mysqli_stmt_get_result($stmt);
    
    if ($row = mysqli_fetch_assoc($result)) {
        echo json_encode([
            "status" => "success",
            "message" => "Complaint details retrieved successfully",
            "data" => $row
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Complaint not found",
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

mysqli_close($conn);
?>