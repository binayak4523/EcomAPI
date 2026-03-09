<?php
// api/pg/getOrderStatus.php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/init.php';

use Juspay\Model\Order;
use Juspay\RequestOptions;
use Juspay\Exception\JuspayException;

header('Content-Type: application/json');

// Replace with a real order_id from your system (example: 'order-123')
$order_id = $_GET['order_id'] ?? '';

if (!$order_id) {
    http_response_code(400);
    echo json_encode(['error' => 'order_id is required in query param']);
    exit;
}

try {
    $params = ['order_id' => $order_id];
    $requestOption = new RequestOptions();
    $requestOption->withCustomerId("testing-customer-one"); // Use your actual customer_id if available

    $order = Order::status($params, $requestOption);

    echo json_encode([
        'order_id' => $order->orderId,
        'status' => $order->status,
        'status_id' => $order->statusId,
        'amount' => $order->amount,
        'currency' => $order->currency,
        'merchant_id' => $order->merchantId,
        'payment_links' => $order->paymentLinks,
        'timestamp' => $order->timestamp
    ], JSON_PRETTY_PRINT);

} catch (JuspayException $e) {
    http_response_code($e->getHttpResponseCode());
    echo json_encode([
        'error_message' => $e->getErrorMessage(),
        'error_code' => $e->getErrorCode()
    ]);
}
