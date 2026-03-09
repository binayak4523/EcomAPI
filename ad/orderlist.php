<?php
// admin_orders.php

// 1. Handle OPTIONS requests for CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
    http_response_code(200);
    exit();
}

// 2. Set headers for the actual GET request
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// 3. Include database connection
include("db.php");

// 4. Create connection
$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed: " . $conn->connect_error]);
    exit();
}

// 5. Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["error" => "Method Not Allowed. Use GET."]);
    exit();
}

// 6. Fetch orders with user details
$orders = [];
$sqlOrders = "
    SELECT 
        o.*, 
        u.name AS customer_name,
        u.address AS customer_address,
        u.email AS customer_email,
        u.mobile AS customer_mobile
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.user_id
    ORDER BY o.order_date DESC
";

$resultOrders = $conn->query($sqlOrders);

if ($resultOrders) {
    while ($order = $resultOrders->fetch_assoc()) {
        $order_id = $order['order_id'];

        // 7. Retrieve order items with item details and images
        $sqlItems = "
            SELECT 
                oi.*,
                im.item_name,
                im.description,
                im.mrp,
                im.saleprice,
                im.dis_p,
                im.hsn,
                im.size_dimension,
                im.weight,
                im.color,
                pi.path_url AS image_path
            FROM order_items oi
            INNER JOIN item_master im ON oi.product_id = im.id
            LEFT JOIN product_images pi ON im.id = pi.product_id 
                AND pi.default_img = 'Y'
            WHERE oi.order_id = ?
        ";
        
        $stmtItems = $conn->prepare($sqlItems);
        if (!$stmtItems) {
            http_response_code(500);
            echo json_encode(["error" => "Failed to prepare statement: " . $conn->error]);
            exit();
        }

        $stmtItems->bind_param("i", $order_id);
        $stmtItems->execute();
        $resItems = $stmtItems->get_result();

        $items = [];
        while ($item = $resItems->fetch_assoc()) {
            // Calculate total price for the item
            $item_total_price = $item['quantity'] * $item['price'];
            
            // Format the item data
            $items[] = [
                'order_item_id' => $item['id'], // if exists in order_items
                'product_id' => $item['product_id'],
                'item_name' => $item['item_name'],
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'mrp' => $item['mrp'],
                'sale_price' => $item['saleprice'],
                'discount' => $item['dis_p'],
                'hsn' => $item['hsn'],
                'size' => $item['size_dimension'],
                'weight' => $item['weight'],
                'color' => $item['color'],
                'image_path' => $item['image_path'] ?? 'default-image.jpg', // provide default if no image
                'total_price' => $item_total_price
            ];
        }
        $stmtItems->close();

        // Attach items to order
        $order['items'] = $items;
        $orders[] = $order;
    }
}

$conn->close();

// 8. Return the orders as JSON
echo json_encode($orders);