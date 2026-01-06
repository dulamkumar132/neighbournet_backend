<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT");

include "db.php";

$data = json_decode(file_get_contents("php://input"), true);
$action = $data['action'] ?? $_GET['action'] ?? 'list';

/* LIST ALL USERS */
if ($action === 'list') {
    $search = $_GET['search'] ?? '';
    $status = $_GET['status'] ?? '';
    
    $query = "SELECT id, full_name, mobile_number, email, flat_number, role, status, created_at 
              FROM users 
              WHERE role = 'user'";
    
    if ($search !== '') {
        $search = mysqli_real_escape_string($conn, $search);
        $query .= " AND (full_name LIKE '%$search%' OR mobile_number LIKE '%$search%' OR email LIKE '%$search%')";
    }
    
    if ($status !== '') {
        $status = mysqli_real_escape_string($conn, $status);
        $query .= " AND status = '$status'";
    }
    
    $query .= " ORDER BY created_at DESC";
    
    $result = mysqli_query($conn, $query);
    $users = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        $users[] = $row;
    }
    
    echo json_encode([
        "status" => "success",
        "data" => $users
    ]);
}

/* GET USER DETAILS */
elseif ($action === 'details') {
    $user_id = $data['user_id'] ?? 0;
    
    $stmt = mysqli_prepare(
        $conn,
        "SELECT id, full_name, mobile_number, email, flat_number, role, status, created_at 
         FROM users 
         WHERE id = ? AND role = 'user'"
    );
    
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($user = mysqli_fetch_assoc($result)) {
        // Get user's skills
        $skills_result = mysqli_query($conn, "SELECT * FROM skills WHERE user_id = $user_id");
        $skills = [];
        while ($skill = mysqli_fetch_assoc($skills_result)) {
            $skills[] = $skill;
        }
        
        $user['skills'] = $skills;
        
        echo json_encode([
            "status" => "success",
            "data" => $user
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "User not found"
        ]);
    }
}

/* BLOCK USER */
elseif ($action === 'block') {
    $user_id = $data['user_id'] ?? 0;
    $admin_id = $data['admin_id'] ?? 0;
    
    $stmt = mysqli_prepare($conn, "UPDATE users SET status = 'blocked' WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    
    if (mysqli_stmt_execute($stmt)) {
        // Log activity
        $log_stmt = mysqli_prepare(
            $conn,
            "INSERT INTO admin_activity_logs (admin_id, action, target_type, target_id, description)
             VALUES (?, 'block_user', 'user', ?, 'Blocked user account')"
        );
        mysqli_stmt_bind_param($log_stmt, "ii", $admin_id, $user_id);
        mysqli_stmt_execute($log_stmt);
        
        echo json_encode([
            "status" => "success",
            "message" => "User blocked successfully"
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Failed to block user"
        ]);
    }
}

/* UNBLOCK USER */
elseif ($action === 'unblock') {
    $user_id = $data['user_id'] ?? 0;
    $admin_id = $data['admin_id'] ?? 0;
    
    $stmt = mysqli_prepare($conn, "UPDATE users SET status = 'active' WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    
    if (mysqli_stmt_execute($stmt)) {
        // Log activity
        $log_stmt = mysqli_prepare(
            $conn,
            "INSERT INTO admin_activity_logs (admin_id, action, target_type, target_id, description)
             VALUES (?, 'unblock_user', 'user', ?, 'Unblocked user account')"
        );
        mysqli_stmt_bind_param($log_stmt, "ii", $admin_id, $user_id);
        mysqli_stmt_execute($log_stmt);
        
        echo json_encode([
            "status" => "success",
            "message" => "User unblocked successfully"
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Failed to unblock user"
        ]);
    }
}

/* DELETE USER */
elseif ($action === 'delete') {
    $user_id = $data['user_id'] ?? 0;
    $admin_id = $data['admin_id'] ?? 0;
    
    $stmt = mysqli_prepare($conn, "DELETE FROM users WHERE id = ? AND role = 'user'");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    
    if (mysqli_stmt_execute($stmt)) {
        // Log activity
        $log_stmt = mysqli_prepare(
            $conn,
            "INSERT INTO admin_activity_logs (admin_id, action, target_type, target_id, description)
             VALUES (?, 'delete_user', 'user', ?, 'Deleted user account')"
        );
        mysqli_stmt_bind_param($log_stmt, "ii", $admin_id, $user_id);
        mysqli_stmt_execute($log_stmt);
        
        echo json_encode([
            "status" => "success",
            "message" => "User deleted successfully"
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Failed to delete user"
        ]);
    }
}

else {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid action"
    ]);
}

mysqli_close($conn);
?>
