<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");

include "db.php";

$data = json_decode(file_get_contents("php://input"), true);
$action = $data['action'] ?? $_GET['action'] ?? 'list';

/* CREATE EVENT */
if ($action === 'create') {
    // First ensure events table exists
    $check_table = mysqli_query($conn, "SHOW TABLES LIKE 'events'");
    if (mysqli_num_rows($check_table) == 0) {
        // Create events table
        $create_table = "CREATE TABLE events (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            event_date DATE NOT NULL,
            location VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        
        if (!mysqli_query($conn, $create_table)) {
            echo json_encode([
                "status" => "error",
                "message" => "Failed to create events table: " . mysqli_error($conn),
                "data" => null
            ]);
            exit;
        }
    }
    
    $title = $data['title'] ?? '';
    $description = $data['description'] ?? '';
    $event_date = $data['event_date'] ?? '';
    $location = $data['location'] ?? '';
    
    if (empty($title) || empty($event_date)) {
        echo json_encode([
            "status" => "error",
            "message" => "Title and event date are required",
            "data" => null
        ]);
        exit;
    }
    
    $stmt = mysqli_prepare($conn,
        "INSERT INTO events (title, description, event_date, location)
         VALUES (?, ?, ?, ?)");
    
    mysqli_stmt_bind_param($stmt, "ssss", $title, $description, $event_date, $location);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode([
            "status" => "success",
            "message" => "Event created successfully",
            "data" => "Event created successfully"
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Failed to create event: " . mysqli_error($conn),
            "data" => null
        ]);
    }
    mysqli_stmt_close($stmt);
}

/* LIST EVENTS */
else if ($action === 'list') {
    // First check if events table exists, if not create it
    $check_table = mysqli_query($conn, "SHOW TABLES LIKE 'events'");
    if (mysqli_num_rows($check_table) == 0) {
        // Create events table
        $create_table = "CREATE TABLE events (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            event_date DATE NOT NULL,
            location VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        
        if (!mysqli_query($conn, $create_table)) {
            echo json_encode([
                "status" => "error",
                "message" => "Failed to create events table: " . mysqli_error($conn),
                "data" => null
            ]);
            exit;
        }
    }
    
    $result = mysqli_query(
        $conn,
        "SELECT id, title, description, event_date, location, created_at
         FROM events
         ORDER BY event_date ASC"
    );

    if (!$result) {
        echo json_encode([
            "status" => "error",
            "message" => "Database query failed: " . mysqli_error($conn),
            "data" => null
        ]);
        exit;
    }

    $events = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $events[] = $row;
    }

    echo json_encode([
        "status" => "success",
        "message" => "Events retrieved successfully",
        "data" => $events
    ]);
}

else {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid action",
        "data" => null
    ]);
}