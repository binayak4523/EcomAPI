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

if (!isset($data['id'])) {
    http_response_code(400);
    echo json_encode(["error" => "Carousel ID is required"]);
    exit();
}

$carousel_id = $data['id'];
$active = isset($data['active']) ? $data['active'] : null;

$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed: " . $conn->connect_error]);
    exit();
}

try {
    if ($active === null) {
        // Toggle the current status
        $sql = "UPDATE carousel SET active = 1 - active WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $carousel_id);
    } else {
        // Set to specific status
        $status = $active ? 1 : 0;
        $sql = "UPDATE carousel SET active = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $status, $carousel_id);
    }
    
    if ($stmt->execute()) {
        echo json_encode([
            "success" => true,
            "message" => "Carousel status updated successfully"
        ]);
    } else {
        throw new Exception($stmt->error);
    }
    
    $stmt->close();
} catch (Exception $e) {
    error_log("Query failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "Query failed: " . $e->getMessage()]);
}

$conn->close();