<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'db.php';

try {
    $conn = new mysqli($host, $user, $password, $dbname);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        throw new Exception("No input data received");
    }

    // Get Razorpay credentials
    $sql = "SELECT api_key, api_secret FROM payment_methods WHERE provider_name = 'Razorpay' AND is_active = 1 LIMIT 1";
    $result = $conn->query($sql);

    if (!$result || $result->num_rows === 0) {
        throw new Exception("Razorpay provider not found or not active");
    }

    $provider = $result->fetch_assoc();
    $razorpay_key_id = $provider['api_key'];
    $razorpay_key_secret = $provider['api_secret'];

    // Check if Razorpay SDK is installed
    if (!file_exists('vendor/autoload.php')) {
        throw new Exception("Razorpay SDK not installed. Run: composer require razorpay/razorpay");
    }

    require_once 'vendor/autoload.php';

    $api = new Razorpay\Api\Api($razorpay_key_id, $razorpay_key_secret);

    // Create Razorpay order
    $orderData = [
        'receipt' => 'order_' . time(),
        'amount' => (int)($input['amount'] * 100), // Convert to paise
        'currency' => $input['currency'] ?? 'INR',
    ];

    $razorpayOrder = $api->order->create($orderData);

    // Extract order data from input
    $order_data = $input['order_data'];
    $user_id = intval($order_data['user_id']);
    $billingad_id = intval($order_data['billingad_id']);
    $shippingad_id = intval($order_data['shippingad_id']);
    $shipping_postal = $order_data['shipping_postal'] ?? '';
    $total_price = floatval($order_data['total_price']);
    $payment_method = $order_data['payment_method'];
    $razorpay_order_id = $razorpayOrder['id'];

    // Get first product details for the main order record (required by your schema)
    $first_item = $order_data['items'][0] ?? null;
    if (!$first_item) {
        throw new Exception("No items in order");
    }
    
    $product_id = intval($first_item['product_id']);
    $quantity = intval($first_item['quantity']);
    
    // Get store_id from item_master table
    $store_stmt = $conn->prepare("SELECT store_id FROM item_master WHERE id = ? LIMIT 1");
    $store_stmt->bind_param("i", $product_id);
    $store_stmt->execute();
    $store_result = $store_stmt->get_result();
    $store_row = $store_result->fetch_assoc();
    $store_id = $store_row ? intval($store_row['store_id']) : 1; // Default to 1 if not found
    $store_stmt->close();

    // Start transaction
    $conn->begin_transaction();

    // Insert main order - matching your exact table structure
    $stmt = $conn->prepare("
        INSERT INTO orders (
            product_id,
            store_id,
            user_id,
            quantity,
            total_price,
            shipping_id,
            shipping_method,
            package_type,
            shipping_address1,
            shipping_address2,
            order_status,
            billingad_id,
            shippingad_id,
            pin_code,
            payment_method,
            signature,
            payment_status,
            updated_at,
            razorpay_order_id
        ) VALUES (?, ?, ?, ?, ?, 0, '', '', '', '', 'New Order', ?, ?, ?, ?, '', 'pending', NOW(), ?)
    ");

    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    // Types: i=int, d=double, s=string
    // product_id(i), store_id(i), user_id(i), quantity(i), total_price(d), 
    // billingad_id(i), shippingad_id(i), pin_code(s), payment_method(s), razorpay_order_id(s)
    $stmt->bind_param(
        'iiiidiisss',
        $product_id,
        $store_id,
        $user_id,
        $quantity,
        $total_price,
        $billingad_id,
        $shippingad_id,
        $shipping_postal,
        $payment_method,
        $razorpay_order_id
    );

    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    $order_id = $conn->insert_id;
    $stmt->close();

    // Insert order items (all items including the first one)
    $item_stmt = $conn->prepare("
        INSERT INTO order_items (order_id, product_id, quantity, price) 
        VALUES (?, ?, ?, ?)
    ");

    if (!$item_stmt) {
        throw new Exception("Prepare failed for order items: " . $conn->error);
    }

    foreach ($order_data['items'] as $item) {
        $item_product_id = intval($item['product_id']);
        $item_quantity = intval($item['quantity']);
        $item_price = floatval($item['price']);

        $item_stmt->bind_param('iiid', $order_id, $item_product_id, $item_quantity, $item_price);

        if (!$item_stmt->execute()) {
            throw new Exception("Execute failed for order items: " . $item_stmt->error);
        }
    }

    $item_stmt->close();

    // Commit transaction
    $conn->commit();

    // Return response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'razorpay_order_id' => $razorpay_order_id,
        'order_id' => $order_id,
        'amount' => $razorpayOrder['amount'],
        'currency' => $razorpayOrder['currency'],
        'key_id' => $razorpay_key_id,
        'receipt' => $razorpayOrder['receipt']
    ]);

} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollback();
    }
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}