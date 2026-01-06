<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");

include "db.php";

$data = json_decode(file_get_contents("php://input"), true);
$action = $data['action'] ?? $_GET['action'] ?? 'list';

// First check if chats table exists, if not create it
$check_table = mysqli_query($conn, "SHOW TABLES LIKE 'chats'");
if (mysqli_num_rows($check_table) == 0) {
    // Create chats table
    $create_table = "CREATE TABLE chats (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sender_id INT NOT NULL,
        receiver_id INT NOT NULL,
        message TEXT NOT NULL,
        message_type VARCHAR(20) DEFAULT 'text',
        is_read BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (sender_id),
        INDEX (receiver_id),
        INDEX (created_at)
    )";
    
    if (!mysqli_query($conn, $create_table)) {
        echo json_encode([
            "status" => "error",
            "message" => "Failed to create chats table: " . mysqli_error($conn),
            "data" => null
        ]);
        exit;
    }
}

/* SEND MESSAGE */
if ($action === 'send') {
    $sender_id = $data['sender_id'] ?? 0;
    $receiver_id = $data['receiver_id'] ?? 0;
    $message = $data['message'] ?? '';
    $message_type = $data['message_type'] ?? 'text';
    
    if (empty($message) || $sender_id == 0 || $receiver_id == 0) {
        echo json_encode([
            "status" => "error",
            "message" => "Sender ID, receiver ID, and message are required",
            "data" => null
        ]);
        exit;
    }
    
    $stmt = mysqli_prepare($conn,
        "INSERT INTO chats (sender_id, receiver_id, message, message_type)
         VALUES (?, ?, ?, ?)");
    
    mysqli_stmt_bind_param($stmt, "iiss", $sender_id, $receiver_id, $message, $message_type);
    
    if (mysqli_stmt_execute($stmt)) {
        $message_id = mysqli_insert_id($conn);
        echo json_encode([
            "status" => "success",
            "message" => "Message sent successfully",
            "data" => ["message_id" => $message_id]
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Failed to send message: " . mysqli_error($conn),
            "data" => null
        ]);
    }
    mysqli_stmt_close($stmt);
}

/* GET MESSAGES BETWEEN TWO USERS */
else if ($action === 'messages') {
    $user1_id = $_GET['user1_id'] ?? 0;
    $user2_id = $_GET['user2_id'] ?? 0;
    $limit = $_GET['limit'] ?? 50;
    
    if ($user1_id == 0 || $user2_id == 0) {
        echo json_encode([
            "status" => "error",
            "message" => "Both user IDs are required",
            "data" => null
        ]);
        exit;
    }
    
    $stmt = mysqli_prepare($conn,
        "SELECT c.*, 
                s.full_name as sender_name, s.flat_number as sender_flat,
                r.full_name as receiver_name, r.flat_number as receiver_flat
         FROM chats c
         LEFT JOIN users s ON c.sender_id = s.id
         LEFT JOIN users r ON c.receiver_id = r.id
         WHERE (c.sender_id = ? AND c.receiver_id = ?) 
            OR (c.sender_id = ? AND c.receiver_id = ?)
         ORDER BY c.created_at ASC
         LIMIT ?");
    
    mysqli_stmt_bind_param($stmt, "iiiii", $user1_id, $user2_id, $user2_id, $user1_id, $limit);
    
    if (!mysqli_stmt_execute($stmt)) {
        echo json_encode([
            "status" => "error",
            "message" => "Database query failed: " . mysqli_error($conn),
            "data" => null
        ]);
        exit;
    }
    
    $result = mysqli_stmt_get_result($stmt);
    
    $messages = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $messages[] = $row;
    }
    
    echo json_encode([
        "status" => "success",
        "message" => "Messages retrieved successfully",
        "data" => $messages
    ]);
    mysqli_stmt_close($stmt);
}

/* GET CHAT LIST (CONVERSATIONS) */
else if ($action === 'list') {
    $user_id = $_GET['user_id'] ?? 0;
    
    if ($user_id == 0) {
        echo json_encode([
            "status" => "error",
            "message" => "User ID is required",
            "data" => null
        ]);
        exit;
    }
    
    $stmt = mysqli_prepare($conn,
        "SELECT DISTINCT 
                CASE 
                    WHEN c.sender_id = ? THEN c.receiver_id 
                    ELSE c.sender_id 
                END as other_user_id,
                u.full_name as other_user_name,
                u.flat_number as other_user_flat,
                (SELECT message FROM chats c2 
                 WHERE (c2.sender_id = ? AND c2.receiver_id = CASE WHEN c.sender_id = ? THEN c.receiver_id ELSE c.sender_id END)
                    OR (c2.receiver_id = ? AND c2.sender_id = CASE WHEN c.sender_id = ? THEN c.receiver_id ELSE c.sender_id END)
                 ORDER BY c2.created_at DESC LIMIT 1) as last_message,
                (SELECT created_at FROM chats c2 
                 WHERE (c2.sender_id = ? AND c2.receiver_id = CASE WHEN c.sender_id = ? THEN c.receiver_id ELSE c.sender_id END)
                    OR (c2.receiver_id = ? AND c2.sender_id = CASE WHEN c.sender_id = ? THEN c.receiver_id ELSE c.sender_id END)
                 ORDER BY c2.created_at DESC LIMIT 1) as last_message_time
         FROM chats c
         LEFT JOIN users u ON u.id = CASE WHEN c.sender_id = ? THEN c.receiver_id ELSE c.sender_id END
         WHERE c.sender_id = ? OR c.receiver_id = ?
         ORDER BY last_message_time DESC");
    
    mysqli_stmt_bind_param($stmt, "iiiiiiiiiiii", 
        $user_id, $user_id, $user_id, $user_id, $user_id, 
        $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id);
    
    if (!mysqli_stmt_execute($stmt)) {
        echo json_encode([
            "status" => "error",
            "message" => "Database query failed: " . mysqli_error($conn),
            "data" => null
        ]);
        exit;
    }
    
    $result = mysqli_stmt_get_result($stmt);
    
    $conversations = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $conversations[] = $row;
    }
    
    echo json_encode([
        "status" => "success",
        "message" => "Conversations retrieved successfully",
        "data" => $conversations
    ]);
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