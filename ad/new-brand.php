<?php
// new-brand.php

// Set headers for CORS and JSON response
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=UTF-8');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include database credentials (adjust the path as needed)
include("db.php");

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed: " . $e->getMessage()]);
    exit();
}

// Read JSON POST data
$data = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (!$data || !isset($data['brand_name'])) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid request data"]);
    exit();
}

// Insert query for the new brand (adjust column names as needed)
$sql = "INSERT INTO brands (brand_name, description) VALUES (?, ?)";
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $data['brand_name'],
        isset($data['description']) ? $data['description'] : null
    ]);
    http_response_code(201);
    echo json_encode([
        "message" => "Brand created successfully",
        "id" => $pdo->lastInsertId()
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Insert failed: " . $e->getMessage()]);
}
?>
