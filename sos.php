<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

include 'db.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    $action = isset($data['action']) ? $data['action'] : '';
    
    if ($action === 'create') {
        // Create SOS alert
        $user_id = isset($data['user_id']) ? intval($data['user_id']) : 0;
        $alert_type = isset($data['alert_type']) ? $data['alert_type'] : 'other';
        $message = isset($data['message']) ? trim($data['message']) : null;
        $location = isset($data['location']) ? trim($data['location']) : null;
        
        if ($user_id <= 0) {
            echo json_encode(["status" => "error", "message" => "User ID is required", "data" => null]);
            exit;
        }
        
        // Validate alert_type
        $valid_types = ['medical', 'fire', 'security', 'other'];
        if (!in_array($alert_type, $valid_types)) {
            $alert_type = 'other';
        }
        
        $stmt = mysqli_prepare($conn, "INSERT INTO sos_alerts (user_id, alert_type, message, location) VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "isss", $user_id, $alert_type, $message, $location);
        
        if (mysqli_stmt_execute($stmt)) {
            $alert_id = mysqli_insert_id($conn);
            echo json_encode(["status" => "success", "message" => "SOS alert created successfully", "data" => ["alert_id" => $alert_id]]);
        } else {
            echo json_encode(["status" => "error", "message" => "Failed to create SOS alert: " . mysqli_error($conn), "data" => null]);
        }
        
        mysqli_stmt_close($stmt);
        
    } elseif ($action === 'resolve') {
        // Resolve SOS alert
        $alert_id = isset($data['alert_id']) ? intval($data['alert_id']) : 0;
        $resolved_by = isset($data['resolved_by']) ? intval($data['resolved_by']) : 0;
        
        $stmt = mysqli_prepare($conn, "UPDATE sos_alerts SET status = 'resolved', resolved_at = NOW(), resolved_by = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "ii", $resolved_by, $alert_id);
        
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(["status" => "success", "message" => "SOS alert resolved successfully", "data" => "Alert resolved"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Failed to resolve SOS alert", "data" => null]);
        }
        
        mysqli_stmt_close($stmt);
        
    } elseif ($action === 'false_alarm') {
        // Mark as false alarm
        $alert_id = isset($data['alert_id']) ? intval($data['alert_id']) : 0;
        
        $stmt = mysqli_prepare($conn, "UPDATE sos_alerts SET status = 'false_alarm', resolved_at = NOW() WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $alert_id);
        
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(["status" => "success", "message" => "SOS alert marked as false alarm", "data" => "Alert updated"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Failed to update SOS alert", "data" => null]);
        }
        
        mysqli_stmt_close($stmt);
    } else {
        echo json_encode(["status" => "error", "message" => "Invalid action", "data" => null]);
    }
    
} elseif ($method === 'GET') {
    
    if ($action === 'list') {
        // Get SOS alerts
        $status = isset($_GET['status']) ? $_GET['status'] : '';
        $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
        
        $query = "SELECT s.*, 
                  u.full_name as user_name, 
                  u.flat_number, 
                  u.mobile_number,
                  r.full_name as resolver_name
                  FROM sos_alerts s
                  LEFT JOIN users u ON s.user_id = u.id
                  LEFT JOIN users r ON s.resolved_by = r.id";
        
        $params = [];
        $types = '';
        $conditions = [];
        
        if (!empty($status)) {
            $conditions[] = "s.status = ?";
            $params[] = $status;
            $types .= 's';
        }
        
        if ($user_id > 0) {
            $conditions[] = "s.user_id = ?";
            $params[] = $user_id;
            $types .= 'i';
        }
        
        if (count($conditions) > 0) {
            $query .= " WHERE " . implode(" AND ", $conditions);
        }
        
        $query .= " ORDER BY s.created_at DESC";
        
        $stmt = mysqli_prepare($conn, $query);
        
        if (count($params) > 0) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $alerts = mysqli_fetch_all($result, MYSQLI_ASSOC);
        
        echo json_encode(["status" => "success", "message" => "SOS alerts retrieved successfully", "data" => $alerts]);
        mysqli_stmt_close($stmt);
        
    } elseif ($action === 'active_count') {
        // Get count of active alerts
        $stmt = mysqli_prepare($conn, "SELECT COUNT(*) as count FROM sos_alerts WHERE status = 'active'");
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        
        echo json_encode(["status" => "success", "message" => "Active alerts count retrieved", "data" => ["active_alerts" => $row['count']]]);
        mysqli_stmt_close($stmt);
    } else {
        echo json_encode(["status" => "error", "message" => "Invalid action", "data" => null]);
    }
    
} else {
    echo json_encode(["status" => "error", "message" => "Method not allowed", "data" => null]);
}

mysqli_close($conn);
?>