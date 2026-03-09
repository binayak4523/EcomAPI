<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: *");
header("Content-Type: application/json");

include 'db.php';

try {
    $conn = new mysqli($host, $user, $password, $dbname);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    $data = json_decode(file_get_contents("php://input"));
    
    if (!isset($data->id)) {
        throw new Exception("Shop ID is required");
    }

    $sql = "DELETE FROM store WHERE ID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $data->id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception("Error deleting shop");
    }

} catch(Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

if (isset($conn)) {
    $conn->close();
}