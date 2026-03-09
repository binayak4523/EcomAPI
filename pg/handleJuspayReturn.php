<?php
// api/pg/handleJuspayReturn.php

// Allow cross-origin if needed
header("Access-Control-Allow-Origin: https://smartgatewayuat.hdfcbank.com");

// Read POST data
$order_id = $_POST['order_id'] ?? '';
$status = $_POST['status'] ?? '';
$status_id = $_POST['status_id'] ?? '';
$signature = $_POST['signature'] ?? '';

// Connect to DB
include('../db.php');
$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    echo "Database connection failed.";
    exit;
}

// Extract numeric ID from order_id like "order-8"
preg_match('/(\d+)$/', $order_id, $matches);
$order_id_numeric = isset($matches[1]) ? intval($matches[1]) : 0;

if ($order_id_numeric === 0) {
    http_response_code(400);
    die("Invalid order ID format");
}

// Try to find checkout session by order_id
$stmt = $conn->prepare("SELECT id, session_key, user_id, order_id, order_amount FROM checkoutsession WHERE order_id = ? LIMIT 1");
$stmt->bind_param("i", $order_id_numeric);
$stmt->execute();
$result = $stmt->get_result();
$session = $result->fetch_assoc();
$stmt->close();

if (!$session) {
    http_response_code(400);
    error_log("Invalid checkout session - no session found for order_id: " . $order_id_numeric);
    die("Invalid checkout session - order not found in checkout session");
}

// Verify order ID matches
if ($session['order_id'] != $order_id_numeric) {
    http_response_code(400);
    error_log("Payment tampering detected - order ID mismatch!");
    die("Payment tampering detected - order ID mismatch!");
}

// Determine session status based on payment status
$session_status = ($status === 'CHARGED') ? 'COMPLETED' : 'FAILED';

// Update checkoutsession status
$upd = $conn->prepare("UPDATE checkoutsession SET status = ? WHERE id = ?");
$upd->bind_param("si", $session_status, $session['id']);
$upd->execute();
$upd->close();

// Update the orders table with payment details
$order_status = ($status === 'CHARGED') ? 'Confirmed' : 'Payment Failed';
$stmt = $conn->prepare("UPDATE orders SET payment_method='ONLINE', payment_status = ?, order_status = ?, signature = ?, status_id = ? WHERE order_id = ?");
$stmt->bind_param("sssii", $status, $order_status, $signature, $status_id, $order_id_numeric);

if (!$stmt->execute()) {
    error_log("Failed to update order status: " . $stmt->error);
}

$stmt->close();
$conn->close();

// Redirect to frontend with query params
$redirect_url = "/payment-status?" .
    http_build_query([
        'order_id' => $order_id,
        'status' => $status,
        'status_id' => $status_id,
        'signature' => $signature
    ]);

header("Location: $redirect_url");
exit();
?>