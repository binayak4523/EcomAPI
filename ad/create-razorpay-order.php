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

    // Get Razorpay credentials from payment_methods table
    $sql = "SELECT api_key, api_secret, merchant_id FROM payment_methods WHERE provider_name = 'Razorpay' AND is_active = 1 LIMIT 1";
    $result = $conn->query($sql);

    if (!$result) {
        throw new Exception("Query failed: " . $conn->error);
    }

    if ($result->num_rows === 0) {
        throw new Exception("Razorpay provider not found or not active in database");
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

    $orderData = [
        'receipt' => $input['receipt'] ?? 'order_' . time(),
        'amount' => (int)($input['amount'] * 100), // Amount in paise
        'currency' => $input['currency'] ?? 'INR',
        'description' => $input['description'] ?? 'Order Payment'
    ];

    $order = $api->order->create($orderData);

    // Save order to database using your table structure
    $order_data = $input['order_data'];
    $payment_method = $order_data['payment_method'];
    $user_id = $order_data['user_id'];
    $billingad_id = $order_data['billingad_id'];
    $shippingad_id = $order_data['shippingad_id'];
    $total_price = $order_data['total_price'];
    $razorpay_order_id = $order['id'];
    $order_status = 'pending';
    $payment_status = 'pending';

    // Fixed: Type string must match number of variables
    // i = int, d = decimal, s = string
    // Variables: user_id(i), billingad_id(i), shippingad_id(i), total_price(d), payment_method(s), razorpay_order_id(s), order_status(s), payment_status(s)
    $stmt = $conn->prepare("INSERT INTO orders (user_id, billingad_id, shippingad_id, total_price, payment_method, razorpay_order_id, order_status, payment_status, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");

    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    // Type string: iiiidsss (8 types for 8 variables)
    $stmt->bind_param('iiiidsss', 
        $user_id,
        $billingad_id,
        $shippingad_id,
        $total_price,
        $payment_method,
        $razorpay_order_id,
        $order_status,
        $payment_status
    );

    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    $order_id = $conn->insert_id;

    // Insert order items
    foreach ($order_data['items'] as $item) {
        $product_id = $item['product_id'];
        $quantity = $item['quantity'];
        $price = $item['price'];

        $item_stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");

        if (!$item_stmt) {
            throw new Exception("Prepare failed for order items: " . $conn->error);
        }

        $item_stmt->bind_param('iiii', $order_id, $product_id, $quantity, $price);

        if (!$item_stmt->execute()) {
            throw new Exception("Execute failed for order items: " . $item_stmt->error);
        }

        $item_stmt->close();
    }

    $stmt->close();

    // Return Razorpay order details
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'razorpay_order_id' => $razorpay_order_id,
        'order_id' => $order_id,
        'amount' => $order['amount'],
        'currency' => $order['currency'],
        'key_id' => $razorpay_key_id,
        'receipt' => $order['receipt']
    ]);

} catch (Exception $e) {
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