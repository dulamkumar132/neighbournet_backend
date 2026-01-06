<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, DELETE");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

include 'db.php';

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

// Handle preflight requests
if ($method === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Get action parameter
$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    $action = isset($data['action']) ? $data['action'] : '';
    
    if ($action === 'create') {
        // Create new poll
        $created_by = isset($data['created_by']) ? intval($data['created_by']) : 0;
        $question = isset($data['question']) ? trim($data['question']) : '';
        $option1 = isset($data['option1']) ? trim($data['option1']) : '';
        $option2 = isset($data['option2']) ? trim($data['option2']) : '';
        $option3 = isset($data['option3']) ? trim($data['option3']) : null;
        $option4 = isset($data['option4']) ? trim($data['option4']) : null;
        $ends_at = isset($data['ends_at']) ? $data['ends_at'] : null;
        
        if (empty($question) || empty($option1) || empty($option2)) {
            echo json_encode(["status" => "error", "message" => "Question and at least 2 options are required", "data" => null]);
            exit;
        }
        
        $stmt = mysqli_prepare($conn, "INSERT INTO polls (created_by, question, option1, option2, option3, option4, ends_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "issssss", $created_by, $question, $option1, $option2, $option3, $option4, $ends_at);
        
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(["status" => "success", "message" => "Poll created successfully", "data" => "Poll created"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Failed to create poll: " . mysqli_error($conn), "data" => null]);
        }
        
        mysqli_stmt_close($stmt);
        
    } elseif ($action === 'vote') {
        // Submit vote
        $poll_id = isset($data['poll_id']) ? intval($data['poll_id']) : 0;
        $user_id = isset($data['user_id']) ? intval($data['user_id']) : 0;
        $selected_option = isset($data['selected_option']) ? intval($data['selected_option']) : 0;
        
        if ($poll_id <= 0 || $user_id <= 0 || $selected_option < 1 || $selected_option > 4) {
            echo json_encode(["status" => "error", "message" => "Invalid vote data", "data" => null]);
            exit;
        }
        
        // Check if user already voted
        $check_stmt = mysqli_prepare($conn, "SELECT id FROM poll_votes WHERE poll_id = ? AND user_id = ?");
        mysqli_stmt_bind_param($check_stmt, "ii", $poll_id, $user_id);
        mysqli_stmt_execute($check_stmt);
        mysqli_stmt_store_result($check_stmt);
        
        if (mysqli_stmt_num_rows($check_stmt) > 0) {
            echo json_encode(["status" => "error", "message" => "You have already voted on this poll", "data" => null]);
            mysqli_stmt_close($check_stmt);
            exit;
        }
        mysqli_stmt_close($check_stmt);
        
        // Insert vote
        $stmt = mysqli_prepare($conn, "INSERT INTO poll_votes (poll_id, user_id, selected_option) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "iii", $poll_id, $user_id, $selected_option);
        
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(["status" => "success", "message" => "Vote submitted successfully", "data" => "Vote recorded"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Failed to submit vote: " . mysqli_error($conn), "data" => null]);
        }
        
        mysqli_stmt_close($stmt);
        
    } elseif ($action === 'close') {
        // Close poll
        $poll_id = isset($data['poll_id']) ? intval($data['poll_id']) : 0;
        
        $stmt = mysqli_prepare($conn, "UPDATE polls SET status = 'closed' WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $poll_id);
        
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(["status" => "success", "message" => "Poll closed successfully", "data" => "Poll closed"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Failed to close poll", "data" => null]);
        }
        
        mysqli_stmt_close($stmt);
    } else {
        echo json_encode(["status" => "error", "message" => "Invalid action", "data" => null]);
    }
    
} elseif ($method === 'GET') {
    
    if ($action === 'list') {
        // Get all polls with vote counts
        $status = isset($_GET['status']) ? $_GET['status'] : '';
        
        $query = "SELECT p.*, 
                  u.full_name as creator_name,
                  (SELECT COUNT(*) FROM poll_votes WHERE poll_id = p.id) as total_votes,
                  (SELECT COUNT(*) FROM poll_votes WHERE poll_id = p.id AND selected_option = 1) as votes_option1,
                  (SELECT COUNT(*) FROM poll_votes WHERE poll_id = p.id AND selected_option = 2) as votes_option2,
                  (SELECT COUNT(*) FROM poll_votes WHERE poll_id = p.id AND selected_option = 3) as votes_option3,
                  (SELECT COUNT(*) FROM poll_votes WHERE poll_id = p.id AND selected_option = 4) as votes_option4
                  FROM polls p
                  LEFT JOIN users u ON p.created_by = u.id";
        
        if (!empty($status)) {
            $query .= " WHERE p.status = ?";
            $stmt = mysqli_prepare($conn, $query . " ORDER BY p.created_at DESC");
            mysqli_stmt_bind_param($stmt, "s", $status);
        } else {
            $stmt = mysqli_prepare($conn, $query . " ORDER BY p.created_at DESC");
        }
        
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $polls = mysqli_fetch_all($result, MYSQLI_ASSOC);
        
        echo json_encode(["status" => "success", "message" => "Polls retrieved successfully", "data" => $polls]);
        mysqli_stmt_close($stmt);
        
    } elseif ($action === 'check_vote') {
        // Check if user has voted on a poll
        $poll_id = isset($_GET['poll_id']) ? intval($_GET['poll_id']) : 0;
        $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
        
        $stmt = mysqli_prepare($conn, "SELECT selected_option FROM poll_votes WHERE poll_id = ? AND user_id = ?");
        mysqli_stmt_bind_param($stmt, "ii", $poll_id, $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($row = mysqli_fetch_assoc($result)) {
            echo json_encode(["status" => "success", "message" => "Vote status checked", "data" => ["has_voted" => true, "selected_option" => $row['selected_option']]]);
        } else {
            echo json_encode(["status" => "success", "message" => "Vote status checked", "data" => ["has_voted" => false]]);
        }
        
        mysqli_stmt_close($stmt);
    } else {
        echo json_encode(["status" => "error", "message" => "Invalid action", "data" => null]);
    }
    
} elseif ($method === 'DELETE') {
    
    $data = json_decode(file_get_contents("php://input"), true);
    $poll_id = isset($data['poll_id']) ? intval($data['poll_id']) : 0;
    
    // Delete votes first
    $stmt1 = mysqli_prepare($conn, "DELETE FROM poll_votes WHERE poll_id = ?");
    mysqli_stmt_bind_param($stmt1, "i", $poll_id);
    mysqli_stmt_execute($stmt1);
    mysqli_stmt_close($stmt1);
    
    // Delete poll
    $stmt2 = mysqli_prepare($conn, "DELETE FROM polls WHERE id = ?");
    mysqli_stmt_bind_param($stmt2, "i", $poll_id);
    
    if (mysqli_stmt_execute($stmt2)) {
        echo json_encode(["status" => "success", "message" => "Poll deleted successfully", "data" => "Poll deleted"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to delete poll", "data" => null]);
    }
    
    mysqli_stmt_close($stmt2);
    
} else {
    echo json_encode(["status" => "error", "message" => "Method not allowed", "data" => null]);
}

mysqli_close($conn);
?>