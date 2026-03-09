<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Include database configuration
require_once 'db.php';

try {
    // Check if order_id is provided
    if (!isset($_GET['order_id'])) {
        throw new Exception('Order ID is required');
    }else{
        $order_id = trim($_GET['order_id']);
    }

    // Create database connection
    $db = mysqli_connect($host, $user, $password, $dbname);

    // Check connection
    if (mysqli_connect_errno()) {
        throw new Exception('Failed to connect to MySQL: ' . mysqli_connect_error());
    }
    
    // Set charset to UTF-8
    mysqli_set_charset($db, 'utf8mb4');

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
    mysqli_stmt_bind_param($stmt, "s", $order_id);
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
        oi.product_id,
        oi.quantity,
        oi.price as price,
        p.item_name as product_name,
        p.description
    FROM order_items oi
    LEFT JOIN item_master p ON p.id = oi.product_id
    WHERE oi.order_id = ?";

    $items_stmt = mysqli_prepare($db, $items_query);
    mysqli_stmt_bind_param($items_stmt, "s", $order_id);
    mysqli_stmt_execute($items_stmt);
    $items_result = mysqli_stmt_get_result($items_stmt);

    if (!$items_result) {
        throw new Exception('Failed to fetch order items');
    }

    $items = [];
    while ($item = mysqli_fetch_assoc($items_result)) {
        // Cast numeric values to proper types
        $item['product_id'] = (int)$item['product_id'];
        $item['quantity'] = (float)$item['quantity'];
        $item['price'] = (float)$item['price'];
        $items[] = $item;
    }

    // Format the response with proper type casting
    $response = [
        'success' => true,
        'order' => [
            'id' => (int)$order['order_id'],
            'customer_name' => (string)($order['customer_name'] ?? ''),
            'customer_email' => (string)($order['customer_email'] ?? ''),
            'shipping_address' => (string)(($order['shipping_address1'] ?? '') . 
                                ($order['shipping_address2'] ? ', ' . $order['shipping_address2'] : '')),
            'shipping_phone' => (string)($order['shipping_phone'] ?? ''),
            'pin_code' => (string)($order['pin_code'] ?? ''),
            'weight' => (float)($order['weight'] ?? 0),
            'invoice_no' => (string)($order['invoice_no'] ?? ''),
            'status' => (string)($order['status'] ?? ''),
            'payment_method' => (string)($order['payment_method'] ?? ''),
            'payment_status' => (string)($order['payment_status'] ?? ''),
            'total_amount' => (float)($order['total_amount'] ?? 0),
            'created_at' => (string)($order['created_at'] ?? ''),
            'items' => array_map(function($item) {
                return [
                    'product_id' => $item['product_id'],
                    'product_name' => (string)($item['product_name'] ?? ''),
                    'description' => (string)($item['description'] ?? ''),
                    'quantity' => $item['quantity'],
                    'price' => $item['price']
                ];
            }, $items)
        ]
    ];

    $json = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        throw new Exception('JSON encoding error: ' . json_last_error_msg());
    }
    echo $json;

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