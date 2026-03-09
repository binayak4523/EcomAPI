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

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$vendor_id = isset($_GET['vendor_id']) ? (int)$_GET['vendor_id'] : 0;

if ($order_id <= 0 || $vendor_id <= 0) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid order ID or vendor ID"]);
    exit;
}

// Get store ID for this vendor
$storeSql = "SELECT ID FROM store WHERE VendorID = ?";
$storeStmt = $conn->prepare($storeSql);
$storeStmt->bind_param("i", $vendor_id);
$storeStmt->execute();
$storeResult = $storeStmt->get_result();
$store = $storeResult->fetch_assoc();
$storeStmt->close();

if (!$store) {
    http_response_code(404);
    echo json_encode(["error" => "Store not found"]);
    exit;
}

// Get order details
$sql = "SELECT 
            o.*,
            v.Name as customer_name,
            v.ContactNo as contact_no,
            v.Vaddress as delivery_address
        FROM 
            orders o
            LEFT JOIN vendor v ON o.user_id = v.ID
        WHERE 
            o.order_id = ? AND o.store_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $order_id, $store['ID']);
$stmt->execute();
$result = $stmt->get_result();
$order = $result->fetch_assoc();
$stmt->close();

if (!$order) {
    http_response_code(404);
    echo json_encode(["error" => "Order not found"]);
    exit;
}

// Get order items with product images
$itemsSql = "SELECT 
    oi.*,
    pi.path_url as image
    FROM order_items oi
    LEFT JOIN product_images pi ON oi.product_id = pi.product_id 
    AND pi.default_img = 'y'
    WHERE oi.order_id = ?";

$itemsStmt = $conn->prepare($itemsSql);
$itemsStmt->bind_param("i", $order_id);
$itemsStmt->execute();
$itemsResult = $itemsStmt->get_result();
$items = [];

while ($item = $itemsResult->fetch_assoc()) {
    $items[] = $item;
}
$itemsStmt->close();

$order['items'] = $items;

$conn->close();

echo json_encode($order);