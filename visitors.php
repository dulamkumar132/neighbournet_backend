<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
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
    
    if ($action === 'add') {
        // Add expected visitor
        $user_id = isset($data['user_id']) ? intval($data['user_id']) : 0;
        $visitor_name = isset($data['visitor_name']) ? trim($data['visitor_name']) : '';
        $visitor_mobile = isset($data['visitor_mobile']) ? trim($data['visitor_mobile']) : '';
        $purpose = isset($data['purpose']) ? trim($data['purpose']) : null;
        $vehicle_number = isset($data['vehicle_number']) ? trim($data['vehicle_number']) : null;
        
        if (empty($visitor_name) || empty($visitor_mobile)) {
            echo json_encode(["status" => "error", "message" => "Visitor name and mobile are required", "data" => null]);
            exit;
        }
        
        $stmt = mysqli_prepare($conn, "INSERT INTO visitors (user_id, visitor_name, visitor_mobile, purpose, vehicle_number) VALUES (?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "issss", $user_id, $visitor_name, $visitor_mobile, $purpose, $vehicle_number);
        
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(["status" => "success", "message" => "Visitor added successfully", "data" => "Visitor added"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Failed to add visitor: " . mysqli_error($conn), "data" => null]);
        }
        
        mysqli_stmt_close($stmt);
        
    } elseif ($action === 'checkin') {
        // Check in visitor
        $visitor_id = isset($data['visitor_id']) ? intval($data['visitor_id']) : 0;
        
        $stmt = mysqli_prepare($conn, "UPDATE visitors SET status = 'checked_in', check_in = NOW() WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $visitor_id);
        
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(["status" => "success", "message" => "Visitor checked in successfully", "data" => "Visitor checked in"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Failed to check in visitor", "data" => null]);
        }
        
        mysqli_stmt_close($stmt);
        
    } elseif ($action === 'checkout') {
        // Check out visitor
        $visitor_id = isset($data['visitor_id']) ? intval($data['visitor_id']) : 0;
        
        $stmt = mysqli_prepare($conn, "UPDATE visitors SET status = 'checked_out', check_out = NOW() WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $visitor_id);
        
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(["status" => "success", "message" => "Visitor checked out successfully", "data" => "Visitor checked out"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Failed to check out visitor", "data" => null]);
        }
        
        mysqli_stmt_close($stmt);
    } else {
        echo json_encode(["status" => "error", "message" => "Invalid action", "data" => null]);
    }
    
} elseif ($method === 'GET') {
    
    if ($action === 'list') {
        // Get visitors list
        $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
        $status = isset($_GET['status']) ? $_GET['status'] : '';
        
        $query = "SELECT v.*, u.full_name, u.flat_number 
                  FROM visitors v
                  LEFT JOIN users u ON v.user_id = u.id";
        
        $params = [];
        $types = '';
        $conditions = [];
        
        if ($user_id > 0) {
            $conditions[] = "v.user_id = ?";
            $params[] = $user_id;
            $types .= 'i';
        }
        
        if (!empty($status)) {
            $conditions[] = "v.status = ?";
            $params[] = $status;
            $types .= 's';
        }
        
        if (count($conditions) > 0) {
            $query .= " WHERE " . implode(" AND ", $conditions);
        }
        
        $query .= " ORDER BY v.created_at DESC";
        
        $stmt = mysqli_prepare($conn, $query);
        
        if (count($params) > 0) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $visitors = mysqli_fetch_all($result, MYSQLI_ASSOC);
        
        echo json_encode(["status" => "success", "message" => "Visitors retrieved successfully", "data" => $visitors]);
        mysqli_stmt_close($stmt);
        
    } else {
        echo json_encode(["status" => "error", "message" => "Invalid action", "data" => null]);
    }
    
} elseif ($method === 'DELETE') {
    
    $data = json_decode(file_get_contents("php://input"), true);
    $visitor_id = isset($data['visitor_id']) ? intval($data['visitor_id']) : 0;
    
    $stmt = mysqli_prepare($conn, "DELETE FROM visitors WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $visitor_id);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(["status" => "success", "message" => "Visitor deleted successfully", "data" => "Visitor deleted"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to delete visitor", "data" => null]);
    }
    
    mysqli_stmt_close($stmt);
    
} else {
    echo json_encode(["status" => "error", "message" => "Method not allowed", "data" => null]);
}

mysqli_close($conn);
?>