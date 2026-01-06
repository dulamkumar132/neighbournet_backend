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
        // Create payment/due
        $user_id = isset($data['user_id']) ? intval($data['user_id']) : 0;
        $payment_type = isset($data['payment_type']) ? $data['payment_type'] : 'maintenance';
        $amount = isset($data['amount']) ? floatval($data['amount']) : 0;
        $due_date = isset($data['due_date']) ? $data['due_date'] : '';
        $description = isset($data['description']) ? trim($data['description']) : null;
        
        if ($user_id <= 0 || $amount <= 0 || empty($due_date)) {
            echo json_encode(["status" => "error", "message" => "User ID, amount, and due date are required", "data" => null]);
            exit;
        }
        
        // Validate payment_type
        $valid_types = ['maintenance', 'amenity', 'penalty', 'other'];
        if (!in_array($payment_type, $valid_types)) {
            $payment_type = 'other';
        }
        
        $stmt = mysqli_prepare($conn, "INSERT INTO payments (user_id, payment_type, amount, due_date, description) VALUES (?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "isdss", $user_id, $payment_type, $amount, $due_date, $description);
        
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(["status" => "success", "message" => "Payment record created successfully", "data" => "Payment created"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Failed to create payment: " . mysqli_error($conn), "data" => null]);
        }
        
        mysqli_stmt_close($stmt);
        
    } elseif ($action === 'pay') {
        // Record payment
        $payment_id = isset($data['payment_id']) ? intval($data['payment_id']) : 0;
        $transaction_id = isset($data['transaction_id']) ? trim($data['transaction_id']) : null;
        
        $stmt = mysqli_prepare($conn, "UPDATE payments SET status = 'paid', payment_date = CURDATE(), transaction_id = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "si", $transaction_id, $payment_id);
        
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(["status" => "success", "message" => "Payment recorded successfully", "data" => "Payment recorded"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Failed to record payment", "data" => null]);
        }
        
        mysqli_stmt_close($stmt);
    } else {
        echo json_encode(["status" => "error", "message" => "Invalid action", "data" => null]);
    }
    
} elseif ($method === 'GET') {
    
    if ($action === 'list') {
        // Get payments list
        $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
        $status = isset($_GET['status']) ? $_GET['status'] : '';
        $payment_type = isset($_GET['payment_type']) ? $_GET['payment_type'] : '';
        
        $query = "SELECT p.*, u.full_name, u.flat_number
                  FROM payments p
                  LEFT JOIN users u ON p.user_id = u.id";
        
        $params = [];
        $types = '';
        $conditions = [];
        
        if ($user_id > 0) {
            $conditions[] = "p.user_id = ?";
            $params[] = $user_id;
            $types .= 'i';
        }
        
        if (!empty($status)) {
            $conditions[] = "p.status = ?";
            $params[] = $status;
            $types .= 's';
        }
        
        if (!empty($payment_type)) {
            $conditions[] = "p.payment_type = ?";
            $params[] = $payment_type;
            $types .= 's';
        }
        
        if (count($conditions) > 0) {
            $query .= " WHERE " . implode(" AND ", $conditions);
        }
        
        $query .= " ORDER BY p.due_date DESC";
        
        $stmt = mysqli_prepare($conn, $query);
        
        if (count($params) > 0) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $payments = mysqli_fetch_all($result, MYSQLI_ASSOC);
        
        // Update overdue status
        foreach ($payments as &$payment) {
            if ($payment['status'] === 'pending' && strtotime($payment['due_date']) < strtotime('today')) {
                $payment['status'] = 'overdue';
                // Update in database
                $update_stmt = mysqli_prepare($conn, "UPDATE payments SET status = 'overdue' WHERE id = ?");
                mysqli_stmt_bind_param($update_stmt, "i", $payment['id']);
                mysqli_stmt_execute($update_stmt);
                mysqli_stmt_close($update_stmt);
            }
        }
        
        echo json_encode(["status" => "success", "message" => "Payments retrieved successfully", "data" => $payments]);
        mysqli_stmt_close($stmt);
        
    } elseif ($action === 'summary') {
        // Get payment summary for a user
        $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
        
        $stmt = mysqli_prepare($conn,
            "SELECT 
                SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as pending_amount,
                SUM(CASE WHEN status = 'overdue' THEN amount ELSE 0 END) as overdue_amount,
                SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) as paid_amount,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
                COUNT(CASE WHEN status = 'overdue' THEN 1 END) as overdue_count,
                COUNT(CASE WHEN status = 'paid' THEN 1 END) as paid_count
             FROM payments 
             WHERE user_id = ?");
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $summary = mysqli_fetch_assoc($result);
        
        echo json_encode(["status" => "success", "message" => "Payment summary retrieved successfully", "data" => $summary]);
        mysqli_stmt_close($stmt);
    } else {
        echo json_encode(["status" => "error", "message" => "Invalid action", "data" => null]);
    }
    
} else {
    echo json_encode(["status" => "error", "message" => "Method not allowed", "data" => null]);
}

mysqli_close($conn);
?>