<?php
// api/pg/handleJuspayReturn.php

header("Access-Control-Allow-Origin: *");

// 1. Read POST data
$order_id  = $_POST['order_id'] ?? '';
$status    = $_POST['status'] ?? '';
$status_id = $_POST['status_id'] ?? '';
$signature = $_POST['signature'] ?? '';

// 2. Validate
if (!$order_id || !$status) {
    http_response_code(400);
    echo "Missing order_id or status";
    exit;
}

// 3. Connect to DB
include('../db.php');
$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    echo "Database connection failed.";
    exit;
}
// Extract numeric ID from order_id like "order-47"
preg_match('/(\d+)$/', $order_id, $matches);
$order_id_numeric = isset($matches[1]) ? intval($matches[1]) : 0;

//Read the checkout_session cookie
$session_key = $_COOKIE['checkout_session'] ?? '';

$stmt = $conn->prepare("SELECT order_id, order_amount FROM checkoutsession WHERE order_id = ?");
$stmt->bind_param("i", $order_id_numeric);
$stmt->execute();
$result = $stmt->get_result();
$session = $result->fetch_assoc();
$stmt->close();

if (!$session) {
    die("Invalid checkout session");
}

// Verify order ID + amount against DB
if ($session['order_id'] != $order_id_numeric) {
    die("Payment tampering detected!");
}

// Update session status
$upd = $conn->prepare("UPDATE checkoutsession SET status = 'RETURNED' WHERE session_key = ?");
$upd->bind_param("s", $session_key);
$upd->execute();
$upd->close();

// 4. Update the orders table
$stmt = $conn->prepare("UPDATE orders SET payment_method='ONLINE', payment_status = ?, signature = ? WHERE order_id = ?");
$stmt->bind_param("ssi", $status, $signature, $order_id_numeric);

if (!$stmt->execute()) {
    error_log("Failed to update order status: " . $stmt->error);
}

$stmt->close();
$conn->close();

// 5. Redirect to frontend
$redirect_url = "https://ctkart.com/payment-status?" . http_build_query([
    'order_id' => $order_id,
    'status' => $status,
    'status_id' => $status_id,
    'signature' => $signature
]);

header("Location: $redirect_url");
exit;
?>
