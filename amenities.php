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
    
    if ($action === 'book') {
        // Create amenity booking
        $user_id = isset($data['user_id']) ? intval($data['user_id']) : 0;
        $amenity_type = isset($data['amenity_type']) ? $data['amenity_type'] : '';
        $booking_date = isset($data['booking_date']) ? $data['booking_date'] : '';
        $start_time = isset($data['start_time']) ? $data['start_time'] : '';
        $end_time = isset($data['end_time']) ? $data['end_time'] : '';
        $purpose = isset($data['purpose']) ? trim($data['purpose']) : null;
        $guests_count = isset($data['guests_count']) ? intval($data['guests_count']) : 0;
        
        if (empty($amenity_type) || empty($booking_date) || empty($start_time) || empty($end_time)) {
            echo json_encode(["status" => "error", "message" => "All booking details are required", "data" => null]);
            exit;
        }
        
        // Validate amenity_type
        $valid_types = ['clubhouse', 'gym', 'pool', 'garden', 'parking', 'hall'];
        if (!in_array($amenity_type, $valid_types)) {
            echo json_encode(["status" => "error", "message" => "Invalid amenity type", "data" => null]);
            exit;
        }
        
        // Check for conflicting bookings
        $check_stmt = mysqli_prepare($conn, 
            "SELECT id FROM amenity_bookings 
             WHERE amenity_type = ? 
             AND booking_date = ? 
             AND status IN ('pending', 'confirmed')
             AND (
                 (start_time <= ? AND end_time > ?) OR
                 (start_time < ? AND end_time >= ?) OR
                 (start_time >= ? AND end_time <= ?)
             )");
        mysqli_stmt_bind_param($check_stmt, "ssssssss", 
            $amenity_type, $booking_date, 
            $start_time, $start_time,
            $end_time, $end_time,
            $start_time, $end_time
        );
        mysqli_stmt_execute($check_stmt);
        mysqli_stmt_store_result($check_stmt);
        
        if (mysqli_stmt_num_rows($check_stmt) > 0) {
            echo json_encode(["status" => "error", "message" => "This amenity is already booked for the selected time slot", "data" => null]);
            mysqli_stmt_close($check_stmt);
            exit;
        }
        mysqli_stmt_close($check_stmt);
        
        // Create booking
        $stmt = mysqli_prepare($conn, 
            "INSERT INTO amenity_bookings (user_id, amenity_type, booking_date, start_time, end_time, purpose, guests_count) 
             VALUES (?, ?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "isssssi", $user_id, $amenity_type, $booking_date, $start_time, $end_time, $purpose, $guests_count);
        
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(["status" => "success", "message" => "Booking created successfully", "data" => "Booking created"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Failed to create booking: " . mysqli_error($conn), "data" => null]);
        }
        
        mysqli_stmt_close($stmt);
        
    } elseif ($action === 'confirm') {
        // Confirm booking (admin action)
        $booking_id = isset($data['booking_id']) ? intval($data['booking_id']) : 0;
        
        $stmt = mysqli_prepare($conn, "UPDATE amenity_bookings SET status = 'confirmed' WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $booking_id);
        
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(["status" => "success", "message" => "Booking confirmed successfully", "data" => "Booking confirmed"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Failed to confirm booking", "data" => null]);
        }
        
        mysqli_stmt_close($stmt);
        
    } elseif ($action === 'cancel') {
        // Cancel booking
        $booking_id = isset($data['booking_id']) ? intval($data['booking_id']) : 0;
        
        $stmt = mysqli_prepare($conn, "UPDATE amenity_bookings SET status = 'cancelled' WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $booking_id);
        
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(["status" => "success", "message" => "Booking cancelled successfully", "data" => "Booking cancelled"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Failed to cancel booking", "data" => null]);
        }
        
        mysqli_stmt_close($stmt);
        
    } elseif ($action === 'complete') {
        // Mark booking as completed
        $booking_id = isset($data['booking_id']) ? intval($data['booking_id']) : 0;
        
        $stmt = mysqli_prepare($conn, "UPDATE amenity_bookings SET status = 'completed' WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $booking_id);
        
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(["status" => "success", "message" => "Booking marked as completed", "data" => "Booking completed"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Failed to update booking", "data" => null]);
        }
        
        mysqli_stmt_close($stmt);
    } else {
        echo json_encode(["status" => "error", "message" => "Invalid action", "data" => null]);
    }
    
} elseif ($method === 'GET') {
    
    if ($action === 'list') {
        // Get bookings list
        $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
        $amenity_type = isset($_GET['amenity_type']) ? $_GET['amenity_type'] : '';
        $status = isset($_GET['status']) ? $_GET['status'] : '';
        $booking_date = isset($_GET['booking_date']) ? $_GET['booking_date'] : '';
        
        $query = "SELECT a.*, u.full_name, u.flat_number, u.mobile_number
                  FROM amenity_bookings a
                  LEFT JOIN users u ON a.user_id = u.id";
        
        $params = [];
        $types = '';
        $conditions = [];
        
        if ($user_id > 0) {
            $conditions[] = "a.user_id = ?";
            $params[] = $user_id;
            $types .= 'i';
        }
        
        if (!empty($amenity_type)) {
            $conditions[] = "a.amenity_type = ?";
            $params[] = $amenity_type;
            $types .= 's';
        }
        
        if (!empty($status)) {
            $conditions[] = "a.status = ?";
            $params[] = $status;
            $types .= 's';
        }
        
        if (!empty($booking_date)) {
            $conditions[] = "a.booking_date = ?";
            $params[] = $booking_date;
            $types .= 's';
        }
        
        if (count($conditions) > 0) {
            $query .= " WHERE " . implode(" AND ", $conditions);
        }
        
        $query .= " ORDER BY a.booking_date DESC, a.start_time DESC";
        
        $stmt = mysqli_prepare($conn, $query);
        
        if (count($params) > 0) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $bookings = mysqli_fetch_all($result, MYSQLI_ASSOC);
        
        echo json_encode(["status" => "success", "message" => "Bookings retrieved successfully", "data" => $bookings]);
        mysqli_stmt_close($stmt);
        
    } elseif ($action === 'check_availability') {
        // Check availability for specific time slot
        $amenity_type = isset($_GET['amenity_type']) ? $_GET['amenity_type'] : '';
        $booking_date = isset($_GET['booking_date']) ? $_GET['booking_date'] : '';
        $start_time = isset($_GET['start_time']) ? $_GET['start_time'] : '';
        $end_time = isset($_GET['end_time']) ? $_GET['end_time'] : '';
        
        $stmt = mysqli_prepare($conn,
            "SELECT id FROM amenity_bookings 
             WHERE amenity_type = ? 
             AND booking_date = ? 
             AND status IN ('pending', 'confirmed')
             AND (
                 (start_time <= ? AND end_time > ?) OR
                 (start_time < ? AND end_time >= ?) OR
                 (start_time >= ? AND end_time <= ?)
             )");
        mysqli_stmt_bind_param($stmt, "ssssssss",
            $amenity_type, $booking_date,
            $start_time, $start_time,
            $end_time, $end_time,
            $start_time, $end_time
        );
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        
        $is_available = mysqli_stmt_num_rows($stmt) === 0;
        
        echo json_encode(["status" => "success", "message" => "Availability checked", "data" => ["available" => $is_available]]);
        mysqli_stmt_close($stmt);
    } else {
        echo json_encode(["status" => "error", "message" => "Invalid action", "data" => null]);
    }
    
} elseif ($method === 'DELETE') {
    
    $data = json_decode(file_get_contents("php://input"), true);
    $booking_id = isset($data['booking_id']) ? intval($data['booking_id']) : 0;
    
    $stmt = mysqli_prepare($conn, "DELETE FROM amenity_bookings WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $booking_id);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(["status" => "success", "message" => "Booking deleted successfully", "data" => "Booking deleted"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to delete booking", "data" => null]);
    }
    
    mysqli_stmt_close($stmt);
    
} else {
    echo json_encode(["status" => "error", "message" => "Method not allowed", "data" => null]);
}

mysqli_close($conn);
?>