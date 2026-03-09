<?php
// api/pg/handleJuspayReturn.php

header("Access-Control-Allow-Origin: *");
require_once __DIR__ . '/init.php';   // Juspay SDK init
require_once __DIR__ . '/vendor/autoload.php';

use Juspay\RequestOptions;
use Juspay\Model\Order;
use Juspay\Exception\JuspayException;

// 1. Read POST data
$order_id  = $_POST['order_id'] ?? '';
$status    = $_POST['status'] ?? '';
$status_id = $_POST['status_id'] ?? '';
$signature = $_POST['signature'] ?? '';
$algo      = $_POST['signature_algorithm'] ?? 'HMAC-SHA256';

// 2. Extract numeric order_id (our DB id)
preg_match('/(\d+)$/', $order_id, $matches);
$order_id_numeric = isset($matches[1]) ? intval($matches[1]) : 0;

if (!$order_id || !$status || !$order_id_numeric) {
    http_response_code(400);
    echo "Invalid return parameters";
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

// ---------------- SIGNATURE VERIFICATION ----------------
$config = \server\ServerEnv::$config;
$secret = $config["PRIVATE_KEY"];  // or shared secret if provided
if ($signature) {
    // Build string to verify
    $dataToVerify = "order_id=$order_id&status=$status&status_id=$status_id";
    $computedSig = base64_encode(hash_hmac('sha256', $dataToVerify, $secret, true));

    if (!hash_equals($computedSig, $signature)) {
        error_log("Signature verification failed for order $order_id");
        $status = "TAMPERED";
    }
}

// ---------------- DIRECT PG VERIFICATION ----------------
try {
    $reqOpts = (new RequestOptions())->withCustomerId("user-" . $order_id_numeric);
    $pgOrder = Order::status(["order_id" => $order_id], $reqOpts);

    $pgStatus = $pgOrder->status;
    $pgAmount = $pgOrder->amount;

    // Fetch amount from DB
    $stmt = $conn->prepare("SELECT total_price FROM orders WHERE order_id = ?");
    $stmt->bind_param("i", $order_id_numeric);
    $stmt->execute();
    $stmt->bind_result($dbAmount);
    $stmt->fetch();
    $stmt->close();

    // Final trust decision
    if (floatval($pgAmount) == floatval($dbAmount)) {
        $finalStatus = $pgStatus; // trusted
    } else {
        $finalStatus = "AMOUNT_MISMATCH";
    }
} catch (JuspayException $e) {
    error_log("Juspay API error: " . $e->getErrorMessage());
    $finalStatus = "VERIFICATION_FAILED";
}

// ---------------- UPDATE DB ----------------
$stmt = $conn->prepare("
    UPDATE orders 
    SET payment_method='ONLINE', 
        payment_status=?, 
        signature=? 
    WHERE order_id=?");
$stmt->bind_param("ssi", $finalStatus, $signature, $order_id_numeric);
$stmt->execute();
$stmt->close();
$conn->close();

// ---------------- REDIRECT TO FRONTEND ----------------
$redirect_url = "https://ctkart.com/payment-status?" . http_build_query([
    'order_id'  => $order_id,
    'status'    => $finalStatus,
    'status_id' => $status_id,
    'signature' => $signature
]);

header("Location: $redirect_url");
exit;
?>
