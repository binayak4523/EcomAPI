<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Include database configuration
require_once '../db.php';

try {
    // Check if order_id is provided
    if (!isset($_GET['order_id'])) {
        throw new Exception('Order ID is required');
    }

    // Create database connection
    $db = mysqli_connect($host, $user, $password, $dbname);

    // Check connection
    if (mysqli_connect_errno()) {
        throw new Exception('Failed to connect to MySQL: ' . mysqli_connect_error());
    }

    // Get order details including customer information and shipping details
    $query = "SELECT 
        o.order_id,
        u.name as customer_name,
        u.email as customer_email,
        o.shipping_address1,
        o.shipping_address2,
        u.mobile as shipping_phone,
        o.total_weight as weight,
        o.InvoiceNo as invoice_no,
        o.order_status as status,
        o.total_price as total_amount,
        o.order_date as created_at,
        o.pin_code,
        o.payment_method,
        o.payment_status
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.user_id
    WHERE o.order_id = ?
    LIMIT 1";

    $stmt = mysqli_prepare($db, $query);
    mysqli_stmt_bind_param($stmt, "s", $_GET['order_id']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (!$result) {
        throw new Exception('Database query failed');
    }

    $order = mysqli_fetch_assoc($result);

    if (!$order) {
        throw new Exception('Order not found');
    }

    // Get product details with prepared statement
    $items_query = "SELECT 
        o.product_id,
        o.quantity,
        o.total_price as price,
        p.item_name as product_name,
        p.description
    FROM orders o
    LEFT JOIN item_master p ON p.id = o.product_id
    WHERE o.order_id = ?";

    $items_stmt = mysqli_prepare($db, $items_query);
    mysqli_stmt_bind_param($items_stmt, "s", $_GET['order_id']);
    mysqli_stmt_execute($items_stmt);
    $items_result = mysqli_stmt_get_result($items_stmt);

    if (!$items_result) {
        throw new Exception('Failed to fetch order items');
    }

    $items = [];
    while ($item = mysqli_fetch_assoc($items_result)) {
        $items[] = $item;
    }

    // Format the response
    $response = [
        'success' => true,
        'order' => [
            'id' => $order['order_id'],
            'customer_name' => $order['customer_name'],
            'customer_email' => $order['customer_email'],
            'shipping_address' => $order['shipping_address1'] . 
                                ($order['shipping_address2'] ? ', ' . $order['shipping_address2'] : ''),
            'shipping_phone' => $order['shipping_phone'],
            'pin_code' => $order['pin_code'],
            'weight' => $order['weight'],
            'invoice_no' => $order['invoice_no'],
            'status' => $order['status'],
            'payment_method' => $order['payment_method'],
            'payment_status' => $order['payment_status'],
            'total_amount' => $order['total_amount'],
            'created_at' => $order['created_at'],
            'items' => array_map(function($item) {
                return [
                    'product_id' => $item['product_id'],
                    'product_name' => $item['product_name'],
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price']
                ];
            }, $items)
        ]
    ];

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    if (isset($db)) {
        mysqli_close($db);
    }
}
?>