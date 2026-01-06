<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

include "db.php";

/* Get admin statistics for dashboard */

$stats = [];

// Total users
$result = mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE role = 'user'");
$stats['total_users'] = mysqli_fetch_assoc($result)['count'];

// Active users (logged in last 30 days - for now just active status)
$result = mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE status = 'active' AND role = 'user'");
$stats['active_users'] = mysqli_fetch_assoc($result)['count'];

// Pending approvals (complaints)
$result = mysqli_query($conn, "SELECT COUNT(*) as count FROM complaints WHERE status = 'open'");
$stats['pending_complaints'] = mysqli_fetch_assoc($result)['count'];

// Total announcements
$result = mysqli_query($conn, "SELECT COUNT(*) as count FROM announcements");
$stats['total_announcements'] = mysqli_fetch_assoc($result)['count'];

// Total events
$result = mysqli_query($conn, "SELECT COUNT(*) as count FROM events");
$stats['total_events'] = mysqli_fetch_assoc($result)['count'];

// Open help requests
$result = mysqli_query($conn, "SELECT COUNT(*) as count FROM help_requests WHERE status = 'open'");
$stats['open_help_requests'] = mysqli_fetch_assoc($result)['count'];

// Recent activity (last 10 actions)
$result = mysqli_query(
    $conn,
    "SELECT a.*, u.full_name as admin_name 
     FROM admin_activity_logs a
     LEFT JOIN users u ON a.admin_id = u.id
     ORDER BY a.created_at DESC
     LIMIT 10"
);

$recent_activity = [];
while ($row = mysqli_fetch_assoc($result)) {
    $recent_activity[] = $row;
}

$stats['recent_activity'] = $recent_activity;

// Chart data - Users joined per month (last 6 months)
$result = mysqli_query(
    $conn,
    "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count 
     FROM users 
     WHERE role = 'user' 
     GROUP BY month 
     ORDER BY month DESC 
     LIMIT 6"
);

$user_growth = [];
while ($row = mysqli_fetch_assoc($result)) {
    $user_growth[] = $row;
}

$stats['user_growth'] = array_reverse($user_growth);

echo json_encode([
    "status" => "success",
    "data" => $stats
]);

mysqli_close($conn);
?>
