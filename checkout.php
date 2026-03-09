<?php
// checkout.php

// 1. Handle OPTIONS requests for CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
    http_response_code(200);
    exit();
}

// 2. Set headers for the actual POST request
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// Include the database connection parameters from db.php
include("db.php");

// Create a new MySQLi connection using variables from db.php
$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed: " . $conn->connect_error]);
    exit();
}

// Read and decode the JSON input
$data = json_decode(file_get_contents("php://input"), true);

// Check if JSON decoding failed or if required fields are missing
if (!$data || !isset($data['product_id'], $data['quantity'], $data['address'], $data['payment_method'], $data['total_amount'])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing required fields or invalid input"]);
    exit();
}

$product_id = intval($data['product_id']);
$quantity   = intval($data['quantity']);
$address    = $conn->real_escape_string($data['address']);
$payment_method = $conn->real_escape_string($data['payment_method']);
$total_amount = floatval($data['total_amount']);

// Insert a new record into the orders table
$sql = "INSERT INTO orders (product_id, quantity, total_price, shipping_address, payment_method) VALUES (?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iidss", $product_id, $quantity, $total_amount, $address, $payment_method);

if ($stmt->execute()) {
    $order_id = $stmt->insert_id;
    echo json_encode(["success" => true, "order_id" => $order_id]);
} else {
    http_response_code(500);
    echo json_encode(["error" => "Failed to create order: " . $conn->error]);
}

$stmt->close();
$conn->close();