<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");

include "db.php";

$data = json_decode(file_get_contents("php://input"), true);
$action = $data['action'] ?? $_GET['action'] ?? 'list';

/* CREATE HELP REQUEST */
if ($action === 'create') {
    $user_id = $data['user_id'] ?? 0;
    $title = $data['title'] ?? '';
    $description = $data['description'] ?? '';
    
    if (empty($title) || empty($description)) {
        echo json_encode([
            "status" => "error",
            "message" => "Title and description are required"
        ]);
        exit;
    }
    
    $stmt = mysqli_prepare($conn,
        "INSERT INTO help_requests (user_id, title, description)
         VALUES (?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "iss", $user_id, $title, $description);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode([
            "status" => "success",
            "message" => "Help request created successfully"
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Failed to create help request"
        ]);
    }
    mysqli_stmt_close($stmt);
}

/* LIST HELP REQUESTS */
else if ($action === 'list') {
    $user_id = $_GET['user_id'] ?? null;
    $status = $_GET['status'] ?? null;
    
    if ($user_id) {
        // Get requests for specific user
        $stmt = mysqli_prepare($conn,
            "SELECT h.*, u.full_name as requester_name, u.flat_number, u.mobile_number,
                    a.full_name as helper_name
             FROM help_requests h
             LEFT JOIN users u ON h.user_id = u.id
             LEFT JOIN users a ON h.accepted_by = a.id
             WHERE h.user_id = ?
             ORDER BY h.created_at DESC");
        mysqli_stmt_bind_param($stmt, "i", $user_id);
    } else if ($status) {
        // Get requests by status
        $stmt = mysqli_prepare($conn,
            "SELECT h.*, u.full_name as requester_name, u.flat_number, u.mobile_number,
                    a.full_name as helper_name
             FROM help_requests h
             LEFT JOIN users u ON h.user_id = u.id
             LEFT JOIN users a ON h.accepted_by = a.id
             WHERE h.status = ?
             ORDER BY h.created_at DESC");
        mysqli_stmt_bind_param($stmt, "s", $status);
    } else {
        // Get all open requests
        $stmt = mysqli_prepare($conn,
            "SELECT h.*, u.full_name as requester_name, u.flat_number, u.mobile_number,
                    a.full_name as helper_name
             FROM help_requests h
             LEFT JOIN users u ON h.user_id = u.id
             LEFT JOIN users a ON h.accepted_by = a.id
             WHERE h.status = 'open'
             ORDER BY h.created_at DESC");
    }
    
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $requests = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $requests[] = $row;
    }
    
    echo json_encode([
        "status" => "success",
        "message" => "Help requests retrieved successfully",
        "data" => $requests
    ]);
    mysqli_stmt_close($stmt);
}

/* ACCEPT HELP REQUEST */
else if ($action === 'accept') {
    $request_id = $data['request_id'] ?? 0;
    $helper_id = $data['helper_id'] ?? 0;
    
    $stmt = mysqli_prepare($conn,
        "UPDATE help_requests
         SET status = 'accepted', accepted_by = ?
         WHERE id = ? AND status = 'open'");
    mysqli_stmt_bind_param($stmt, "ii", $helper_id, $request_id);
    
    if (mysqli_stmt_execute($stmt)) {
        if (mysqli_stmt_affected_rows($stmt) > 0) {
            echo json_encode([
                "status" => "success",
                "message" => "Help request accepted"
            ]);
        } else {
            echo json_encode([
                "status" => "error",
                "message" => "Request already accepted or not found"
            ]);
        }
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Failed to accept request"
        ]);
    }
    mysqli_stmt_close($stmt);
}

/* COMPLETE HELP REQUEST */
else if ($action === 'complete') {
    $request_id = $data['request_id'] ?? 0;
    
    $stmt = mysqli_prepare($conn,
        "UPDATE help_requests
         SET status = 'completed'
         WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $request_id);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode([
            "status" => "success",
            "message" => "Help request marked as completed"
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Failed to complete request"
        ]);
    }
    mysqli_stmt_close($stmt);
}

/* DELETE HELP REQUEST */
else if ($action === 'delete') {
    $request_id = $data['request_id'] ?? 0;
    
    $stmt = mysqli_prepare($conn, "DELETE FROM help_requests WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $request_id);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode([
            "status" => "success",
            "message" => "Help request deleted"
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Failed to delete request"
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