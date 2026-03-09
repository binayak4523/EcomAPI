<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include("db.php");

// Read JSON POST data
$data = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (!$data || !isset($data['brand_name'])) {
    http_response_code(400);
    echo json_encode(["error" => "Brand name is required"]);
    exit();
}

$brand_name = trim($data['brand_name']);

// Check if brand already exists
$checkStmt = $conn->prepare("SELECT BrandID FROM brands WHERE Brand = ?");
$checkStmt->bind_param("s", $brand_name);
$checkStmt->execute();
if ($checkStmt->get_result()->num_rows > 0) {
    echo json_encode(["error" => "Brand already exists"]);
    exit();
}

// Insert new brand
$stmt = $conn->prepare("INSERT INTO brands (Brand) VALUES (?)");
$stmt->bind_param("s", $brand_name);

if ($stmt->execute()) {
    $newBrandId = $conn->insert_id;
    echo json_encode([
        "id" => $newBrandId,
        "message" => "Brand created successfully"
    ]);
} else {
    echo json_encode(["error" => "Failed to save brand"]);
}

$conn->close();