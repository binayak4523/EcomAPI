<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

include 'db.php';
$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get vendor ID from query parameter
$vendor_id = isset($_GET['vendor_id']) ? (int)$_GET['vendor_id'] : 0;

if ($vendor_id <= 0) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid vendor ID"]);
    exit;
}

// First get store ID for this vendor
$storeSql = "SELECT ID FROM store WHERE VendorID = ?";
$storeStmt = $conn->prepare($storeSql);

if (!$storeStmt) {
    http_response_code(500);
    echo json_encode(["error" => "Database error: " . $conn->error]);
    exit;
}

$storeStmt->bind_param("i", $vendor_id);

if (!$storeStmt->execute()) {
    http_response_code(500);
    echo json_encode(["error" => "Query execution failed: " . $storeStmt->error]);
    exit;
}

$storeResult = $storeStmt->get_result();
$store = $storeResult->fetch_assoc();
$storeStmt->close();

if (!$store) {
    http_response_code(404);
    echo json_encode(["error" => "Store not found"]);
    exit;
}

// Get orders for this store
$sql = "SELECT 
            o.order_id,
            v.Name as customer_name,
            o.order_date,
            o.total_price as total_amount,
            o.order_status as status,
            GROUP_CONCAT(CONCAT(oi.quantity, 'x Item #', oi.product_id) SEPARATOR ', ') as order_items
        FROM 
            orders o
            LEFT JOIN vendor v ON o.user_id = v.ID
            LEFT JOIN order_items oi ON o.order_id = oi.order_id
        WHERE 
            o.store_id = ?
        GROUP BY 
            o.order_id, o.order_date, o.total_price, o.order_status, v.Name
        ORDER BY 
            o.order_date DESC";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    http_response_code(500);
    echo json_encode(["error" => "Database error: " . $conn->error]);
    exit;
}

$stmt->bind_param("i", $store['ID']);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(["error" => "Query execution failed: " . $stmt->error]);
    exit;
}

$result = $stmt->get_result();
$orders = [];

while ($row = $result->fetch_assoc()) {
    $orders[] = $row;
}

$stmt->close();
$conn->close();

echo json_encode($orders);