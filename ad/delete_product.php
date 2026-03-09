<?php
// delete_product.php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: http://localhost:3000');
header('Access-Control-Allow-Methods: DELETE, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

// Handle OPTIONS preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit;
}

include 'db.php';
// Create connection
$conn = new mysqli($host, $user, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
// Get the JSON payload
$data = json_decode(file_get_contents("php://input"), true);
if (!$data || !isset($data['id'])) {
    http_response_code(400);
    echo json_encode(["error" => "Product ID is required"]);
    exit;
}

$productId = $data['id'];

// Delete associated product images first
$stmt = $conn->prepare("DELETE FROM product_images WHERE product_id = ?");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(["error" => "Database error: " . $conn->error]);
    exit;
}
$stmt->bind_param("i", $productId);
$stmt->execute();
$stmt->close();

// Delete the product from item_master
$stmt = $conn->prepare("DELETE FROM item_master WHERE id = ?");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(["error" => "Database error: " . $conn->error]);
    exit;
}
$stmt->bind_param("i", $productId);
if ($stmt->execute()) {
    echo json_encode(["success" => "Product deleted successfully"]);
} else {
    http_response_code(500);
    echo json_encode(["error" => "Failed to delete product"]);
}
$stmt->close();
$conn->close();
?>
