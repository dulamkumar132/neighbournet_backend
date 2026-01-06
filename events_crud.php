<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");

include "db.php";

$data = json_decode(file_get_contents("php://input"), true);
$action = $data['action'] ?? $_GET['action'] ?? 'list';

/* CREATE EVENT */
if ($action === 'create') {
    $title = $data['title'] ?? '';
    $description = $data['description'] ?? '';
    $event_date = $data['event_date'] ?? '';
    $location = $data['location'] ?? '';
    $created_by = $data['created_by'] ?? 0;
    
    if (empty($title) || empty($event_date)) {
        echo json_encode([
            "status" => "error",
            "message" => "Title and event date are required"
        ]);
        exit;
    }
    
    $stmt = mysqli_prepare($conn,
        "INSERT INTO events (title, description, event_date, location, created_by)
         VALUES (?, ?, ?, ?, ?)");
    
    mysqli_stmt_bind_param($stmt, "ssssi", $title, $description, $event_date, $location, $created_by);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode([
            "status" => "success",
            "message" => "Event created successfully"
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Failed to create event"
        ]);
    }
    mysqli_stmt_close($stmt);
}

/* LIST EVENTS */
else if ($action === 'list') {
    $status = $_GET['status'] ?? '';
    
    if (!empty($status)) {
        $stmt = mysqli_prepare($conn,
            "SELECT e.*, u.full_name as creator_name 
             FROM events e
             LEFT JOIN users u ON e.created_by = u.id
             WHERE e.status = ?
             ORDER BY e.event_date ASC");
        mysqli_stmt_bind_param($stmt, "s", $status);
    } else {
        $stmt = mysqli_prepare($conn,
            "SELECT e.*, u.full_name as creator_name 
             FROM events e
             LEFT JOIN users u ON e.created_by = u.id
             ORDER BY e.event_date ASC");
    }
    
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $events = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $events[] = $row;
    }
    
    echo json_encode($events);
    mysqli_stmt_close($stmt);
}

/* UPDATE EVENT */
else if ($action === 'update') {
    $event_id = $data['event_id'] ?? 0;
    $title = $data['title'] ?? '';
    $description = $data['description'] ?? '';
    $event_date = $data['event_date'] ?? '';
    $location = $data['location'] ?? '';
    
    $stmt = mysqli_prepare($conn,
        "UPDATE events 
         SET title = ?, description = ?, event_date = ?, location = ?
         WHERE id = ?");
    
    mysqli_stmt_bind_param($stmt, "ssssi", $title, $description, $event_date, $location, $event_id);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode([
            "status" => "success",
            "message" => "Event updated"
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Failed to update event"
        ]);
    }
    mysqli_stmt_close($stmt);
}

/* UPDATE EVENT STATUS (Approve/Reject) */
else if ($action === 'update_status') {
    $event_id = $data['event_id'] ?? 0;
    $status = $data['status'] ?? 'pending';
    
    $stmt = mysqli_prepare($conn,
        "UPDATE events SET status = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "si", $status, $event_id);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode([
            "status" => "success",
            "message" => "Event status updated"
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Failed to update status"
        ]);
    }
    mysqli_stmt_close($stmt);
}

/* DELETE EVENT */
else if ($action === 'delete') {
    $event_id = $data['event_id'] ?? 0;
    
    $stmt = mysqli_prepare($conn,
        "DELETE FROM events WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $event_id);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode([
            "status" => "success",
            "message" => "Event deleted"
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Failed to delete event"
        ]);
    }
    mysqli_stmt_close($stmt);
}

/* GET EVENT DETAILS */
else if ($action === 'details') {
    $event_id = $_GET['event_id'] ?? 0;
    
    $stmt = mysqli_prepare($conn,
        "SELECT e.*, u.full_name as creator_name, u.mobile_number as creator_mobile
         FROM events e
         LEFT JOIN users u ON e.created_by = u.id
         WHERE e.id = ?");
    mysqli_stmt_bind_param($stmt, "i", $event_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($row = mysqli_fetch_assoc($result)) {
        echo json_encode([
            "status" => "success",
            "data" => $row
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Event not found"
        ]);
    }
    mysqli_stmt_close($stmt);
}

else {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid action"
    ]);
}

mysqli_close($conn);
?>
