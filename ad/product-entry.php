<?php
// product-entry.php

// Set headers for CORS and JSON response
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=UTF-8');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include database credentials (ensure db.php defines $host, $dbname, $user, and $password)
include("db.php");

try {
    // Create PDO connection
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed: " . $e->getMessage()]);
    exit();
}

// Read JSON POST data
$data = json_decode(file_get_contents("php://input"), true);

// Validate required fields: product_name, product_type, brand, price
if (
    !$data ||
    !isset($data['product_name']) ||
    !isset($data['product_type']) ||
    !isset($data['brand']) ||
    !isset($data['price'])
) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid request data"]);
    exit();
}

// Prepare the INSERT query; adjust column names to match your 'products' table
$sql = "INSERT INTO products (product_name, product_type, brand, price, description, image_url)
        VALUES (?, ?, ?, ?, ?, ?)";
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $data['product_name'],
        $data['product_type'],
        $data['brand'],
        $data['price'],
        isset($data['description']) ? $data['description'] : null,
        isset($data['image_url']) ? $data['image_url'] : null
    ]);
    http_response_code(201);
    echo json_encode([
        "message" => "Product created successfully",
        "id" => $pdo->lastInsertId()
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Insert failed: " . $e->getMessage()]);
}
?>
