<?php
header("Content-Type: application/json");
include "db.php";

$sql = "
SELECT 
    s.id,
    s.skill_name,
    s.description,
    u.full_name
FROM skills s
JOIN users u ON u.id = s.user_id
ORDER BY s.id DESC
";

$result = mysqli_query($conn, $sql);

$skills = [];

while ($row = mysqli_fetch_assoc($result)) {
    $skills[] = $row;
}

echo json_encode([
    "status" => "success",
    "data" => $skills
]);
