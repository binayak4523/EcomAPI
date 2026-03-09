<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: *");
header("Content-Type: application/json");

include 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $data = json_decode(file_get_contents("php://input"));
    
    if (!isset($data->id)) {
        http_response_code(400);
        echo json_encode(['error' => 'Vendor ID is required']);
        exit;
    }

    $vendor_id = $data->id;
    
    try {
        $sql = "DELETE FROM vendors WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $vendor_id);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                echo json_encode(['success' => true, 'message' => 'Vendor deleted successfully']);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Vendor not found']);
            }
        } else {
            throw new Exception($stmt->error);
        }
    } catch(Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    
    $stmt->close();
}

$conn->close();