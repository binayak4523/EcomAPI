<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include 'db.php';

// Create connection
$conn = new mysqli($host, $user, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => "Connection failed: " . $conn->connect_error]);
    exit();
}

$sql = "SELECT * FROM brands";
$result = $conn->query($sql);
$brands = array();

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $brands[] = $row;
    }
    echo json_encode($brands);
} else {
    http_response_code(500);
    echo json_encode(['error' => "Error fetching brands: " . $conn->error]);
}

$conn->close();