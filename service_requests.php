<?php
// Set headers for CORS and JSON response
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=UTF-8');

// If you have a separate file with $host, $dbname, $user, $password, include it:
include("db.php");

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed"]);
    exit();
}

// Handle preflight (OPTIONS) requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST to insert data
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Helper function to read JSON from request body
    $data = json_decode(file_get_contents('php://input'), true);

    // Validate required fields
    if (
        !$data ||
        !isset($data['contact_no']) ||
        !isset($data['contact_name']) ||
        !isset($data['address']) ||
        !isset($data['product_type']) ||
        !isset($data['model_name']) ||
        !isset($data['brand']) ||
        !isset($data['complain']) ||
        !isset($data['date_time'])
    ) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid request data"]);
        exit();
    }

    // Insert query: Adjust column names to match your table structure
    $sql = "INSERT INTO service_requests 
        (contact_no, contact_name, address, product_type, model_name, brand, complain, date_time, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Open')";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['contact_no'],
            $data['contact_name'],
            $data['address'],
            $data['product_type'],
            $data['model_name'],
            $data['brand'],
            $data['complain'],
            $data['date_time']
        ]);

        http_response_code(201);
        echo json_encode([
            "message" => "Service request created",
            "id" => $pdo->lastInsertId()
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Insert failed"]);
    }
} else {
    http_response_code(404);
    echo json_encode(["error" => "Invalid route or method. Only POST is allowed."]);
}