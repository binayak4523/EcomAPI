<?php
// add_to_cart.php

// 1. Handle OPTIONS requests first (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    // Set headers for the preflight request
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
    // Respond with 200 OK and exit
    http_response_code(200);
    exit();
}

// 2. For actual requests, set CORS headers again
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get the session ID
$session_id = session_id();

// Include the database connection parameters from db.php
include("db.php");

// Create a new MySQLi connection using the variables from db.php
$conn = new mysqli($host, $user, $password, $dbname);

// Check for a connection error
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed: " . $conn->connect_error]);
    exit();
}

// Read and decode the JSON input from the request body
$data = json_decode(file_get_contents("php://input"), true);

// Validate that product_id and quantity are provided
if (!isset($data['product_id']) || !isset($data['quantity'])) {
    http_response_code(400);
    echo json_encode(["error" => "Product ID and quantity are required"]);
    exit();
}

$product_id = intval($data['product_id']);
$quantity = intval($data['quantity']);
$user_id = isset($data['user_id']) ? intval($data['user_id']) : null;

// Check if the product already exists in the cart for this user/session
$check_sql = "SELECT * FROM cart WHERE product_id = ?";
$check_params = [$product_id];

if ($user_id) {
    $check_sql .= " AND user_id = ?";
    $check_params[] = $user_id;
} else {
    $check_sql .= " AND session_id = ?";
    $check_params[] = $session_id;
}

$check_stmt = $conn->prepare($check_sql);

// Bind parameters dynamically
$check_types = str_repeat('i', count($check_params));
$check_stmt->bind_param($check_types, ...$check_params);
$check_stmt->execute();
$result = $check_stmt->get_result();

if ($result->num_rows > 0) {
    // Update existing cart item
    $cart_item = $result->fetch_assoc();
    $new_quantity = $cart_item['quantity'] + $quantity;
    
    $update_sql = "UPDATE cart SET quantity = ? WHERE cart_id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("ii", $new_quantity, $cart_item['cart_id']);
    
    if ($update_stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Cart updated successfully", "cart_id" => $cart_item['cart_id']]);
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Failed to update cart: " . $conn->error]);
    }
    
    $update_stmt->close();
} else {
    // Insert a new row into the 'cart' table with user_id and session_id
    $sql = "INSERT INTO cart (product_id, quantity, user_id, session_id) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiis", $product_id, $quantity, $user_id, $session_id);

    if ($stmt->execute()) {
        $cart_id = $stmt->insert_id; // Get the new cart record ID
        echo json_encode(["success" => true, "cart_id" => $cart_id]);
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Failed to add to cart: " . $conn->error]);
    }
    
    $stmt->close();
}

// Close the check statement and the database connection
$check_stmt->close();
$conn->close();