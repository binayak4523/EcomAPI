<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

include("db.php");

// Add error logging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Get JSON input
$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['items']) || !is_array($data['items'])) {
    http_response_code(400);
    echo json_encode(["error" => "Items array is required"]);
    exit();
}

$items = $data['items'];

$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed: " . $conn->connect_error]);
    exit();
}

try {
    $conn->begin_transaction();
    
    $sql = "UPDATE carousel SET display_order = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    
    foreach ($items as $index => $item) {
        $order = $index + 1;
        $id = $item['id'];
        
        $stmt->bind_param("ii", $order, $id);
        $stmt->execute();
    }
    
    $conn->commit();
    
    echo json_encode([
        "success" => true,
        "message" => "Carousel order updated successfully"
    ]);
    
    $stmt->close();
} catch (Exception $e) {
    $conn->rollback();
    error_log("Query failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "Query failed: " . $e->getMessage()]);
}

$conn->close();