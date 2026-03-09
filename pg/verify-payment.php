<?php
// api/pg/verify-payment.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

// Include dependencies
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/../db.php'; // Fixed path with proper separator

use Juspay\RequestOptions;
use Juspay\Model\Order;
use Juspay\Exception\JuspayException;

// 1. Connect DB
$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed"]);
    exit();
}

// 2. Get session_key from cookie
$session_key = $_COOKIE['checkout_session'] ?? '';
if (!$session_key) {
    http_response_code(400);
    echo json_encode(["error" => "Checkout session missing"]);
    exit();
}

// 3. Look up checkoutsession
$stmt = $conn->prepare("SELECT order_id, order_amount, status FROM checkoutsession WHERE session_key = ?");
$stmt->bind_param("s", $session_key);
$stmt->execute();
$res = $stmt->get_result();
$session = $res->fetch_assoc();
$stmt->close();

if (!$session) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid checkout session"]);
    exit();
}

$order_id = "order-" . $session['order_id'];

try {
    $ORDERSTATUS = "New Order";
    // 4. Call Juspay Order Status API
    $reqOpts = (new RequestOptions())->withCustomerId("user-" . $session['order_id']);
    $order = Order::status(["order_id" => $order_id], $reqOpts);
    
    // Fixed if condition syntax
    if ($order->status !== 'CHARGED') {
        $ORDERSTATUS = "CANCELLED";
    }
    
    // 5. Update DB
    $stmt = $conn->prepare("UPDATE orders SET payment_status = ?, updated_at = NOW(), order_status = ? WHERE order_id = ?");
    $stmt->bind_param("ssi", $order->status, $ORDERSTATUS, $session['order_id']);
    $stmt->execute();
    $stmt->close();

    $stmt2 = $conn->prepare("UPDATE checkoutsession SET status = 'VERIFIED', updated_at = NOW() WHERE session_key = ?");
    $stmt2->bind_param("s", $session_key);
    $stmt2->execute();
    $stmt2->close();

    // Only delete cart items if order is successful
    if ($ORDERSTATUS == "New Order") {
        $delStmt = $conn->prepare("DELETE FROM cart WHERE temp_order_id = ?");
        $delStmt->bind_param("i", $session['order_id']);
        $delStmt->execute();
        $delStmt->close();
    }

    echo json_encode([
        "success" => true,
        "order_id" => $order_id,
        "amount" => $order->amount,
        "status" => $ORDERSTATUS
    ]);

} catch (JuspayException $e) {
    http_response_code(500);
    echo json_encode([
        "error" => "Juspay API error",
        "message" => $e->getErrorMessage()
    ]);
}

$conn->close();