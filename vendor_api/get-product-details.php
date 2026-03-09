<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=UTF-8');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include("db.php");

$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    die(json_encode(["error" => "Connection failed: " . $conn->connect_error]));
}

$sql = "SELECT id, name, required 
        FROM details_master 
        ORDER BY id DESC";

$result = $conn->query($sql);

$details = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $details[] = [
            "id" => $row["id"],
            "detailName" => $row["name"],
            "isRequired" => $row["required"] == 1 ? true : false
        ];
    }
}

echo json_encode($details);

$conn->close();