<?php
// Test database connection for NeighbourNet app
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    include "db.php";
    
    // Test connection and get tables
    $result = mysqli_query($conn, "SHOW TABLES");
    $tables = [];
    
    if ($result) {
        while ($row = mysqli_fetch_array($result)) {
            $tables[] = $row[0];
        }
        mysqli_free_result($result);
    }
    
    // Test if users table exists
    $users_exists = in_array('users', $tables);
    
    echo json_encode([
        "status" => "success",
        "message" => "Database connection successful!",
        "database" => "neighbournet_db",
        "tables_count" => count($tables),
        "tables" => $tables,
        "users_table_exists" => $users_exists,
        "server_info" => mysqli_get_server_info($conn),
        "timestamp" => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Database connection failed",
        "error" => $e->getMessage(),
        "timestamp" => date('Y-m-d H:i:s')
    ]);
} finally {
    if (isset($conn) && $conn) {
        mysqli_close($conn);
    }
}
?>
