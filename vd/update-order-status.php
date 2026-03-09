<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

include 'db.php';
$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$order_id = isset($data['order_id']) ? (int)$data['order_id'] : 0;
$vendor_id = isset($data['vendor_id']) ? (int)$data['vendor_id'] : 0;
$status = isset($data['status']) ? $data['status'] : '';

if ($order_id <= 0 || $vendor_id <= 0 || empty($status)) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid input data"]);
    exit;
}

// Verify store ownership
$storeSql = "SELECT ID FROM store WHERE VendorID = ?";
$storeStmt = $conn->prepare($storeSql);
$storeStmt->bind_param("i", $vendor_id);
$storeStmt->execute();
$storeResult = $storeStmt->get_result();
$store = $storeResult->fetch_assoc();
$storeStmt->close();

if (!$store) {
    http_response_code(403);
    echo json_encode(["error" => "Unauthorized access"]);
    exit;
}

// Update order status
$updateSql = "UPDATE orders SET order_status = ? WHERE order_id = ? AND store_id = ?";
$updateStmt = $conn->prepare($updateSql);
$updateStmt->bind_param("sii", $status, $order_id, $store['ID']);

if ($updateStmt->execute()) {
    echo json_encode(["success" => true, "message" => "Status updated successfully"]);
} else {
    http_response_code(500);
    echo json_encode(["error" => "Failed to update status"]);
}

$updateStmt->close();
$conn->close();
?>