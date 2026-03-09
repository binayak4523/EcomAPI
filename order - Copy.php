<?php
// order.php

// 1. Handle OPTIONS requests for CORS preflight if necessary
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
    http_response_code(200);
    exit();
}

// 2. Set response headers for JSON
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// 3. Include database connection parameters
include("db.php");

// 4. Create a new MySQLi connection
$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed: " . $conn->connect_error]);
    exit();
}

// 5. Read and decode JSON input from the request body
$data = json_decode(file_get_contents("php://input"), true);
if (!$data) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid or missing JSON data"]);
    exit();
}

// 6. Validate that all required fields exist
if (
    !isset($data["user_id"]) ||        // Added user_id validation
    !isset($data["total_price"]) ||
    !isset($data["items"]) ||
    !is_array($data["items"]) ||
    count($data["items"]) === 0 ||
    !isset($data["billingad_id"]) ||
    !isset($data["shippingad_id"]) ||
    !isset($data["payment_method"])     // Added payment_method validation
) {
    http_response_code(400);
    echo json_encode(["error" => "Missing required fields: user_id, total_price, items, billingad_id, shippingad_id, payment_method"]);
    exit();
}

$user_id = intval($data["user_id"]);   // Get user_id
$total_price = floatval($data["total_price"]);
$items = $data["items"];
$billingad_id = intval($data["billingad_id"]);
$shippingad_id = intval($data["shippingad_id"]);
$payment_method = $conn->real_escape_string($data["payment_method"]);

// 7. Validate user exists
$userCheck = $conn->query("SELECT user_id FROM users WHERE user_id = $user_id");
if ($userCheck->num_rows === 0) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid user ID"]);
    exit();
}

// 8. Aggregate order-level data
$firstItem = $items[0];
if (!isset($firstItem["product_id"]) || !isset($firstItem["quantity"])) {
    http_response_code(400);
    echo json_encode(["error" => "Each item must include product_id and quantity"]);
    exit();
}

$product_id = intval($firstItem["product_id"]);
$total_quantity = 0;
foreach ($items as $item) {
    if (!isset($item["product_id"], $item["quantity"], $item["price"])) {
        http_response_code(400);
        echo json_encode(["error" => "Each item must include product_id, quantity, and price"]);
        exit();
    }
    $total_quantity += intval($item["quantity"]);
}

// Start transaction
$conn->begin_transaction();

try {
    // 9. Insert order into the orders table
    $sqlOrder = "INSERT INTO orders (user_id, store_id, total_price, billingad_id, shippingad_id, order_status, order_date, InvoiceNo) 
                 VALUES (?, ?, ?, ?, ?, 'New Order', CURRENT_TIMESTAMP, 0)";
    $stmtOrder = $conn->prepare($sqlOrder);
    if (!$stmtOrder) {
        throw new Exception("Prepare failed (orders): " . $conn->error);
    }
    
    // Assuming store_id is 1 for now, adjust if needed
    $store_id = 1;
    
    $stmtOrder->bind_param(
        "iiidi", 
        $data['user_id'],
        $store_id,
        $data['total_price'],
        $data['billingad_id'],
        $data['shippingad_id']
    );

    if (!$stmtOrder->execute()) {
        throw new Exception("Error inserting order: " . $stmtOrder->error);
    }

    $order_id = $conn->insert_id;
    $stmtOrder->close();

    // 10. Insert each order item into the order_items table
    $sqlItems = "INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)";
    $stmtItems = $conn->prepare($sqlItems);
    if (!$stmtItems) {
        throw new Exception("Prepare failed (order_items): " . $conn->error);
    }

    foreach ($data['items'] as $item) {
        $item_product_id = intval($item["product_id"]);
        $item_quantity = intval($item["quantity"]);
        $cart_id = intval($item["cart_id"]);
        $item_price = floatval($item["price"]);
        
        $stmtItems->bind_param(
            "iiid",
            $order_id,
            $item_product_id, // Changed from id to product_id
            $item_quantity,
            $item_price
        );
        if (!$stmtItems->execute()) {
            throw new Exception("Error inserting order item: " . $stmtItems->error);
        }else{
            $sqlCart = "DELETE FROM cart where cart_id = ? and user_id = ?";
            $stmtCart = $conn->prepare($sqlCart);
            $stmtCart->bind_param(
                "ii",
                $cart_id,
                $user_id
            );
            if (!$stmtCart->execute()) {
                throw new Exception("Error Processing Request", 1);
            }
            
        }
    }

    $stmtItems->close();

    // If we get here, commit the transaction
    $conn->commit();

    // 11. Return a success response with the new order ID
    echo json_encode([
        "success" => true,
        "order_id" => $order_id,
        "message" => "Order placed successfully"
    ]);

} catch (Exception $e) {
    // Roll back the transaction on error
    $conn->rollback();
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
} finally {
    $conn->close();
}
?>