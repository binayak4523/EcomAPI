<?php
header('Content-Type: application/json');

// --- DB credentials ---
include("db.php");

//$dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
$dsn = "mysql:host=$host;dbname=$dbname;";

// --- Connect to DB ---
try {
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// --- Get `since` parameter ---
$since = isset($_GET['since']) ? $_GET['since'] : null;

// --- Fetch orders ---
$sql = "SELECT 
            o.order_id,
            o.product_id AS order_product_id,
            o.store_id,
            o.user_id,
            o.quantity AS order_quantity,
            o.total_price,
            o.shipping_address1 AS order_shipping_address1,
            o.shipping_address2 AS order_shipping_address2,
            o.order_date,
            o.order_status,
            o.InvoiceNo,
            o.pin_code AS order_pin_code,
            u.user_id,
            u.name,
            u.email,
            u.mobile,
            u.address AS user_address,
            ba.address1 AS billing_address1,
            ba.address2 AS billing_address2,
            ba.city AS billing_city,
            ba.state AS billing_state,
            ba.pin AS billing_pin,
            ba.country AS billing_country,
            ba.name AS billing_name,
            ba.phone_no AS billing_phone,
            ba.state_code AS billing_statecode,
            ba.landmark AS billing_landmark,
            ba.pin as billing_pin,
            sa.address1 AS shipping_address1,
            sa.address2 AS shipping_address2,
            sa.city AS shipping_city,
            sa.state AS shipping_state,
            sa.pin AS shipping_pin,
            sa.country AS shipping_country,
            sa.name AS shipping_name,
            sa.phone_no AS shipping_phone,
            sa.state_code AS shipping_statecode,
            sa.landmark AS shipping_landmark,
            sa.pin as shipping_pin
        FROM orders o
        INNER JOIN users u ON o.user_id = u.user_id
        LEFT JOIN addresses ba ON o.billingad_id = ba.id
        LEFT JOIN addresses sa ON o.shippingad_id = sa.id
        WHERE o.InvoiceNo = 0";
$stmt = $pdo->query($sql);

$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
$response = [];

foreach ($orders as $order) {
    // Fetch items for this order
    $itemStmt = $pdo->prepare("SELECT 
        oi.product_id, 
        oi.quantity, 
        oi.price, 
        im.item_name, 
        im.hsn, 
        im.size_dimension, 
        im.weight, 
        im.color, 
        im.packingtype, 
        im.tax_p, 
        im.saleprice, 
        im.dis_p 
        FROM order_items oi 
        INNER JOIN item_master im ON oi.product_id = im.id 
        WHERE oi.order_id = ?");
    $itemStmt->execute([$order['order_id']]);
    $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

    // Format order with items and address details
    $response[] = [
        'order_id'     => (int)$order['order_id'],
        'user_id'      => (int)$order['user_id'],
        'store_id'     => (int)$order['store_id'],
        'order_date'   => $order['order_date'],
        'order_status' => $order['order_status'],
        'InvoiceNo'    => (int)$order['InvoiceNo'],
        'total_qty'    => (int)$order['order_quantity'],
        'total_amount' => (float)$order['total_price'],
        'order_shipping_address' => [
            'address1' => $order['order_shipping_address1'],
            'address2' => $order['order_shipping_address2'],
            'pin_code' => (int)$order['order_pin_code']
        ],
        'user_details' => [
            'name'    => $order['name'],
            'email'   => $order['email'],
            'mobile'  => $order['mobile'],
            'address' => $order['user_address']
        ],
        'billing_address' => [
            'name'     => $order['billing_name'],
            'phone_no' => (int)$order['billing_phone'],
            'address1' => $order['billing_address1'],
            'address2' => $order['billing_address2'],
            'city'     => $order['billing_city'],
            'state'    => $order['billing_state'],
            'country'  => $order['billing_country'],
            'pin'      => (int)$order['billing_pin'],
            'statecode' => (int)$order['billing_statecode'],
            'landmark' => $order['billing_landmark']
        ],
        'shipping_address' => [
            'name'     => $order['shipping_name'],
            'phone_no' => (int)$order['shipping_phone'],
            'address1' => $order['shipping_address1'],
            'address2' => $order['shipping_address2'],
            'city'     => $order['shipping_city'],
            'state'    => $order['shipping_state'],
            'country'  => $order['shipping_country'],
            'pin'      => (int)$order['shipping_pin'],
            'statecode' => (int)$order['shipping_statecode'],
            'landmark' => $order['shipping_landmark']
        ],
        'items'        => $items
    ];
}

// --- Return JSON ---
echo json_encode($response);
?>