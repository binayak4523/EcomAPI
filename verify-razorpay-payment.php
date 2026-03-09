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
require_once 'vendor/autoload.php';

try {
    $conn = new mysqli($host, $user, $password, $dbname);
    if ($conn->connect_error) {
        throw new Exception("Connection failed");
    }

    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['razorpay_order_id'], $input['razorpay_payment_id'], $input['razorpay_signature'])) {
        throw new Exception("Missing required payment parameters");
    }

    $razorpay_order_id = $input['razorpay_order_id'];
    $razorpay_payment_id = $input['razorpay_payment_id'];
    $razorpay_signature = $input['razorpay_signature'];

    // Get Razorpay credentials
    $sql = "SELECT api_key, api_secret FROM payment_methods WHERE provider_name = 'Razorpay' AND is_active = 1 LIMIT 1";
    $result = $conn->query($sql);
    
    if (!$result || $result->num_rows === 0) {
        throw new Exception("Razorpay configuration not found");
    }
    
    $provider = $result->fetch_assoc();

    // Verify signature
    $api = new Razorpay\Api\Api($provider['api_key'], $provider['api_secret']);
    
    $attributes = [
        'razorpay_order_id' => $razorpay_order_id,
        'razorpay_payment_id' => $razorpay_payment_id,
        'razorpay_signature' => $razorpay_signature
    ];
    
    try {
        $api->utility->verifyPaymentSignature($attributes);
        $verification_success = true;
    } catch (Exception $e) {
        $verification_success = false;
        throw new Exception("Payment signature verification failed");
    }

    // Update order status
    $stmt = $conn->prepare("
        UPDATE orders 
        SET payment_status = 'completed', 
            order_status = 'Processing', 
            razorpay_payment_id = ?, 
            updated_at = NOW() 
        WHERE razorpay_order_id = ?
    ");
    
    if (!$stmt) {
        throw new Exception("Database prepare failed");
    }
    
    $stmt->bind_param('ss', $razorpay_payment_id, $razorpay_order_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to update order status");
    }

    // Get order details
    $orderStmt = $conn->prepare("
        SELECT order_id, total_price, user_id 
        FROM orders 
        WHERE razorpay_order_id = ?
    ");
    $orderStmt->bind_param('s', $razorpay_order_id);
    $orderStmt->execute();
    $orderResult = $orderStmt->get_result();
    $orderDetails = $orderResult->fetch_assoc();

    $stmt->close();
    $orderStmt->close();

    echo json_encode([
        'success' => true,
        'message' => 'Payment verified successfully',
        'order_id' => $orderDetails['order_id'],
        'amount' => $orderDetails['total_price'],
        'status' => 'CHARGED'
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