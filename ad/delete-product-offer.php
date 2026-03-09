<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = json_decode(file_get_contents("php://input"), true);

        if (empty($data['id'])) {
            throw new Exception("Offer ID is required");
        }

        $id = $data['id'];

        // First, get the product_id before deleting the offer
        $getStmt = $conn->prepare("SELECT product_id FROM product_offers WHERE id = ?");
        if (!$getStmt) {
            throw new Exception("Get prepare failed: " . $conn->error);
        }

        $getStmt->bind_param("i", $id);
        $getStmt->execute();
        $result = $getStmt->get_result();
        $offer = $result->fetch_assoc();
        $getStmt->close();

        if (!$offer) {
            throw new Exception("Offer not found");
        }

        $productId = $offer['product_id'];

        // Delete the offer
        $stmt = $conn->prepare("DELETE FROM product_offers WHERE id = ?");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $stmt->bind_param("i", $id);
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }

        // Check if there are any remaining active offers for this product
        $checkStmt = $conn->prepare("SELECT COUNT(*) as count FROM product_offers WHERE product_id = ? AND status = 'active'");
        if (!$checkStmt) {
            throw new Exception("Check prepare failed: " . $conn->error);
        }

        $checkStmt->bind_param("i", $productId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        $countRow = $checkResult->fetch_assoc();
        $checkStmt->close();

        // If no active offers remain, set ongoing_offer to 'no'
        if ($countRow['count'] == 0) {
            $updateStmt = $conn->prepare("UPDATE item_master SET ongoing_offer = 'no' WHERE ID = ?");
            if (!$updateStmt) {
                throw new Exception("Update prepare failed: " . $conn->error);
            }

            $updateStmt->bind_param("i", $productId);
            if (!$updateStmt->execute()) {
                throw new Exception("Update execute failed: " . $updateStmt->error);
            }
            $updateStmt->close();
        }

        echo json_encode(['success' => true]);
        $stmt->close();
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
