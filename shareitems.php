<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");

include "db.php";

$data = json_decode(file_get_contents("php://input"), true);
$action = $data['action'] ?? $_GET['action'] ?? 'list';

/* ADD ITEM */
if ($action === 'add') {
    // First check if items table exists, if not create it
    $check_table = mysqli_query($conn, "SHOW TABLES LIKE 'items'");
    if (mysqli_num_rows($check_table) == 0) {
        // Create items table
        $create_table = "CREATE TABLE items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            item_name VARCHAR(255) NOT NULL,
            description TEXT,
            condition_type VARCHAR(50) DEFAULT 'good',
            status VARCHAR(50) DEFAULT 'available',
            borrowed_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (user_id),
            INDEX (status)
        )";
        
        if (!mysqli_query($conn, $create_table)) {
            echo json_encode([
                "status" => "error",
                "message" => "Failed to create items table: " . mysqli_error($conn),
                "data" => null
            ]);
            exit;
        }
    }
    
    $user_id = $data['user_id'] ?? 0;
    $item_name = $data['item_name'] ?? '';
    $description = $data['description'] ?? '';
    $condition_type = $data['condition_type'] ?? 'good';
    
    if (empty($item_name)) {
        echo json_encode([
            "status" => "error",
            "message" => "Item name is required",
            "data" => null
        ]);
        exit;
    }
    
    $stmt = mysqli_prepare($conn,
        "INSERT INTO items (user_id, item_name, description, condition_type)
         VALUES (?, ?, ?, ?)");
    
    mysqli_stmt_bind_param($stmt, "isss", $user_id, $item_name, $description, $condition_type);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode([
            "status" => "success",
            "message" => "Item shared successfully",
            "data" => "Item added"
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Failed to share item: " . mysqli_error($conn),
            "data" => null
        ]);
    }
    mysqli_stmt_close($stmt);
}

/* LIST ITEMS */
else if ($action === 'list') {
    // First check if items table exists, if not create it
    $check_table = mysqli_query($conn, "SHOW TABLES LIKE 'items'");
    if (mysqli_num_rows($check_table) == 0) {
        // Create items table
        $create_table = "CREATE TABLE items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            item_name VARCHAR(255) NOT NULL,
            description TEXT,
            condition_type VARCHAR(50) DEFAULT 'good',
            status VARCHAR(50) DEFAULT 'available',
            borrowed_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (user_id),
            INDEX (status)
        )";
        
        if (!mysqli_query($conn, $create_table)) {
            echo json_encode([
                "status" => "error",
                "message" => "Failed to create items table: " . mysqli_error($conn),
                "data" => null
            ]);
            exit;
        }
    }
    
    $user_id = $_GET['user_id'] ?? null;
    $status = $_GET['status'] ?? null;
    
    if ($user_id) {
        // Get items for specific user
        $stmt = mysqli_prepare($conn,
            "SELECT i.*, u.full_name as owner_name, u.flat_number, u.mobile_number
             FROM items i
             LEFT JOIN users u ON i.user_id = u.id
             WHERE i.user_id = ?
             ORDER BY i.created_at DESC");
        mysqli_stmt_bind_param($stmt, "i", $user_id);
    } else if ($status) {
        // Get items by status (available/borrowed)
        $stmt = mysqli_prepare($conn,
            "SELECT i.*, u.full_name as owner_name, u.flat_number, u.mobile_number
             FROM items i
             LEFT JOIN users u ON i.user_id = u.id
             WHERE i.status = ?
             ORDER BY i.created_at DESC");
        mysqli_stmt_bind_param($stmt, "s", $status);
    } else {
        // Get all available items
        $stmt = mysqli_prepare($conn,
            "SELECT i.*, u.full_name as owner_name, u.flat_number, u.mobile_number
             FROM items i
             LEFT JOIN users u ON i.user_id = u.id
             WHERE i.status = 'available'
             ORDER BY i.created_at DESC");
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
    
    $items = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $items[] = $row;
    }
    
    echo json_encode([
        "status" => "success",
        "message" => "Items retrieved successfully",
        "data" => $items
    ]);
    mysqli_stmt_close($stmt);
}

/* REQUEST ITEM */
else if ($action === 'request') {
    $item_id = $data['item_id'] ?? 0;
    $borrower_id = $data['borrower_id'] ?? 0;
    
    $stmt = mysqli_prepare($conn,
        "UPDATE items SET status = 'borrowed', borrowed_by = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $borrower_id, $item_id);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode([
            "status" => "success",
            "message" => "Item requested successfully",
            "data" => "Item requested"
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Failed to request item",
            "data" => null
        ]);
    }
    mysqli_stmt_close($stmt);
}

/* RETURN ITEM */
else if ($action === 'return') {
    $item_id = $data['item_id'] ?? 0;
    
    $stmt = mysqli_prepare($conn,
        "UPDATE items SET status = 'available', borrowed_by = NULL WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $item_id);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode([
            "status" => "success",
            "message" => "Item returned successfully",
            "data" => "Item returned"
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Failed to return item",
            "data" => null
        ]);
    }
    mysqli_stmt_close($stmt);
}

/* DELETE ITEM */
else if ($action === 'delete') {
    $item_id = $data['item_id'] ?? 0;
    
    $stmt = mysqli_prepare($conn, "DELETE FROM items WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $item_id);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode([
            "status" => "success",
            "message" => "Item deleted",
            "data" => "Item deleted"
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Failed to delete item",
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