<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
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
    die("Connection failed: " . $conn->connect_error);
}

// Get JSON input
$json = file_get_contents('php://input');
$data = json_decode($json, true);

error_log($brand = isset($data['brand']) ? trim($data['brand']) : '');

if (empty($brand)) {
    http_response_code(400);
    echo json_encode(["error" => "Brand name is required."]);
    exit;
}

// Check if brand already exists
$checkStmt = $conn->prepare("SELECT BrandID FROM brands WHERE Brand = ?");
$checkStmt->bind_param("s", $brand);
$checkStmt->execute();
if ($checkStmt->get_result()->num_rows > 0) {
    http_response_code(400);
    echo json_encode(["error" => "Brand already exists"]);
    exit;
}
$checkStmt->close();

$stmt = $conn->prepare("INSERT INTO brands (Brand) VALUES (?)");
$stmt->bind_param("s", $brand);

if ($stmt->execute()) {
    echo json_encode(["BrandID" => $stmt->insert_id, "Brand" => $brand]);
} else {
    http_response_code(500);
    echo json_encode(["error" => "Failed to insert brand"]);
}

$stmt->close();
$conn->close();