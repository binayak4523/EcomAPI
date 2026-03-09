<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = json_decode(file_get_contents("php://input"), true);

        // Validate required fields
        if (empty($data['productId']) || empty($data['discountPercentage']) || 
            empty($data['startDate']) || empty($data['endDate'])) {
            throw new Exception("Missing required fields");
        }

        $productId = $data['productId'];
        $discountPercentage = $data['discountPercentage'];
        $startDate = $data['startDate'];
        $endDate = $data['endDate'];
        $isActive = isset($data['isActive']) ? ($data['isActive'] ? 1 : 0) : 1;

        // Prepare statement to insert product offer
        $stmt = $conn->prepare("
            INSERT INTO product_offers (product_id, discount_percentage, start_date, end_date, status)
            VALUES (?, ?, ?, ?, ?)
        ");

        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $status = $isActive ? 'active' : 'inactive';
        $stmt->bind_param("iisss", $productId, $discountPercentage, $startDate, $endDate, $status);

        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }

        // Update the product's ongoing_offer field to 'yes'
        $updateStmt = $conn->prepare("UPDATE item_master SET ongoing_offer = 'yes' WHERE ID = ?");
        if (!$updateStmt) {
            throw new Exception("Update prepare failed: " . $conn->error);
        }

        $updateStmt->bind_param("i", $productId);
        if (!$updateStmt->execute()) {
            throw new Exception("Update execute failed: " . $updateStmt->error);
        }

        echo json_encode(['success' => true, 'id' => $conn->insert_id]);
        $stmt->close();
        $updateStmt->close();
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
    }
    $conn->close();
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>

