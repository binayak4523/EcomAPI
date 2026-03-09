<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
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

    // Get order_id from query parameter
    $order_id = $_GET['order_id'] ?? null;

    if (!$order_id) {
        throw new Exception("Order ID is required");
    }

    // Fetch order details
    $stmt = $conn->prepare("SELECT id, total_price, payment_status, order_status FROM orders WHERE razorpay_order_id = ?");

    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param('i', $order_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Order not found");
    }

    $order = $result->fetch_assoc();
    $stmt->close();

    echo json_encode([
        'success' => true,
        'order_id' => $order['order_id'],
        'total_price' => $order['total_price'],
        'payment_method' => $order['payment_method'],
        'razorpay_order_id' => $order['razorpay_order_id'],
        'razorpay_payment_id' => $order['razorpay_payment_id'],
        'payment_status' => $order['payment_status'] ?: 'pending',
        'order_status' => $order['order_status'] ?: 'pending',
        'created_at' => $order['created_at'],
        'updated_at' => $order['updated_at']
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>