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

    // Get payment details from request
    $razorpay_order_id = $input['razorpay_order_id'] ?? null;
    $razorpay_payment_id = $input['razorpay_payment_id'] ?? null;
    $razorpay_signature = $input['razorpay_signature'] ?? null;
    $db_order_id = $input['db_order_id'] ?? null;

    if (!$razorpay_order_id || !$razorpay_payment_id || !$razorpay_signature) {
        throw new Exception("Missing payment details");
    }

    // Get Razorpay credentials from database
    $sql = "SELECT api_key, api_secret FROM payment_methods WHERE provider_name = 'Razorpay' AND is_active = 1 LIMIT 1";
    $result = $conn->query($sql);

    if (!$result || $result->num_rows === 0) {
        throw new Exception("Razorpay provider not found");
    }

    $provider = $result->fetch_assoc();
    $razorpay_key_secret = $provider['api_secret'];

    // Verify signature
    $generated_signature = hash_hmac('sha256', $razorpay_order_id . "|" . $razorpay_payment_id, $razorpay_key_secret);

    if ($generated_signature !== $razorpay_signature) {
        throw new Exception("Invalid payment signature");
    }

    // Signature is valid - update order in database
    if ($db_order_id) {
        // Update existing order using order_id (not id)
        $update_stmt = $conn->prepare("UPDATE orders SET 
            razorpay_order_id = ?, 
            razorpay_payment_id = ?, 
            payment_status = 'completed', 
            order_status = 'confirmed',
            updated_at = NOW() 
            WHERE order_id = ?");
        
        if (!$update_stmt) {
            throw new Exception("Prepare update failed: " . $conn->error);
        }

        $update_stmt->bind_param('ssi', $razorpay_order_id, $razorpay_payment_id, $db_order_id);
        
        if (!$update_stmt->execute()) {
            throw new Exception("Failed to update order: " . $update_stmt->error);
        }

        if ($update_stmt->affected_rows === 0) {
            throw new Exception("Order not found or already updated");
        }

        $update_stmt->close();
    } else {
        // Find order by razorpay_order_id if db_order_id not provided
        $find_stmt = $conn->prepare("UPDATE orders SET 
            razorpay_payment_id = ?, 
            payment_status = 'completed', 
            order_status = 'confirmed',
            updated_at = NOW() 
            WHERE razorpay_order_id = ?");
        
        if (!$find_stmt) {
            throw new Exception("Prepar