<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

include "db.php";

/* Read JSON input */
$data = json_decode(file_get_contents("php://input"), true);

$mobile   = $data['mobile_number'] ?? '';
$password = $data['password'] ?? '';

/* Validate input */
if ($mobile === '' || $password === '') {
    echo json_encode([
        "status" => "error",
        "message" => "Mobile number and password required"
    ]);
    exit;
}

/* Fetch admin user by mobile number */
$stmt = mysqli_prepare(
    $conn,
    "SELECT u.id, u.full_name, u.email, u.password, u.role, u.status, 
            a.admin_level, a.permissions
     FROM users u
     LEFT JOIN admin_users a ON u.id = a.user_id
     WHERE u.mobile_number = ? AND u.role = 'admin'"
);

mysqli_stmt_bind_param($stmt, "s", $mobile);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

/* Check admin user */
if ($row = mysqli_fetch_assoc($result)) {

    // Check if account is blocked
    if ($row['status'] === 'blocked') {
        echo json_encode([
            "status"  => "error",
            "message" => "Your account has been blocked. Contact support."
        ]);
        exit;
    }

    // Verify password
    if (password_verify($password, $row['password'])) {
        
        // Generate session token (simple version - use JWT in production)
        $token = bin2hex(random_bytes(32));
        
        // Log admin login activity
        $log_stmt = mysqli_prepare(
            $conn,
            "INSERT INTO admin_activity_logs (admin_id, action, description)
             VALUES (?, 'login', 'Admin logged in')"
        );
        mysqli_stmt_bind_param($log_stmt, "i", $row['id']);
        mysqli_stmt_execute($log_stmt);
        
        echo json_encode([
            "status"      => "success",
            "message"     => "Admin login successful",
            "admin_id"    => $row['id'],
            "name"        => $row['full_name'],
            "email"       => $row['email'],
            "role"        => $row['role'],
            "admin_level" => $row['admin_level'] ?? 'moderator',
            "permissions" => json_decode($row['permissions'] ?? '[]'),
            "token"       => $token
        ]);
        
    } else {
        echo json_encode([
            "status"  => "error",
            "message" => "Invalid password"
        ]);
    }

} else {
    echo json_encode([
        "status"  => "error",
        "message" => "Admin account not found"
    ]);
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
?>
