<?php

// 1. Handle OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
    http_response_code(200);
    exit();
}

// 2. Set JSON headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/pg/init.php';  // path to the SDK setup

use Juspay\RequestOptions;
use Juspay\Model\OrderSession;
use Juspay\Exception\JuspayException;
require("db.php");

// 3. Create DB connection
$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed"]);
    exit();
}

// 4. Get POST input
$data = json_decode(file_get_contents("php://input"), true);
if (!$data) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid JSON"]);
    exit();
}

// 5. Basic validation
if (
    !isset($data["user_id"], $data["items"], $data["billingad_id"], $data["shippingad_id"], $data["payment_method"])
    || !is_array($data["items"]) || count($data["items"]) === 0
) {
    http_response_code(400);
    echo json_encode(["error" => "Missing required fields"]);
    exit();
}

$user_id = intval($data["user_id"]);
$items = $data["items"];
$billingad_id = intval($data["billingad_id"]);
$shippingad_id = intval($data["shippingad_id"]);
$payment_method = $conn->real_escape_string($data["payment_method"]);
$customer_name='';
$customer_phone='';
$customer_email='';

// 6. Check if user exists
$result = $conn->query("SELECT * FROM users WHERE user_id = $user_id");
if ($result->num_rows === 0) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid user ID"]);
    exit();
}else{
    $user = $result->fetch_assoc();
    $customer_name = $user['name'];
    $customer_phone = $user['mobile'] ;
    $customer_email = $user['email'] ;
}

// ✅ 7. Calculate total_price on server (ignore frontend amount)
$total_price = 0.0;
foreach ($items as $item) {
    $pid = intval($item["product_id"]);
    $qty = intval($item["quantity"]);

    $res = $conn->query("SELECT saleprice FROM item_master WHERE id = $pid");
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $total_price += $row["saleprice"] * $qty;
    } else {
        http_response_code(400);
        echo json_encode(["error" => "Invalid product ID: $pid"]);
        exit();
    }
}

// 8. Save order to DB
$conn->begin_transaction();
try {
    $stmt = $conn->prepare("INSERT INTO orders (user_id, store_id, total_price, billingad_id, shippingad_id, order_status, order_date, InvoiceNo)
                            VALUES (?, ?, ?, ?, ?, 'New Order', CURRENT_TIMESTAMP, 0)");
    $store_id = 1;
    $stmt->bind_param("iiidi", $user_id, $store_id, $total_price, $billingad_id, $shippingad_id);
    $stmt->execute();
    $order_id = $conn->insert_id;
    $stmt->close();

    // Save items
    $stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
    foreach ($items as $item) {
        $pid = intval($item["product_id"]);
        $qty = intval($item["quantity"]);
        
        // get DB price again to be 100% sure
        $res = $conn->query("SELECT saleprice FROM item_master WHERE id = $pid");
        $row = $res->fetch_assoc();
        $price = $row["saleprice"];

        $stmt->bind_param("iiid", $order_id, $pid, $qty, $price);
        $stmt->execute();

        $cart_id = intval($item["cart_id"] ?? 0);
        if ($cart_id > 0) {
            //$delStmt = $conn->prepare("DELETE FROM cart WHERE cart_id = ? AND user_id = ?");
            $delStmt = $conn->prepare("UPDATE cart SET temp_order_id = ? WHERE cart_id = ? AND user_id = ?");
            $delStmt->bind_param("iii", $order_id, $cart_id, $user_id);
            $delStmt->execute();
            $delStmt->close();
        }
    }
    $stmt->close();
    $conn->commit();
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(["error" => "Order save failed: " . $e->getMessage()]);
    exit();
}
// Create checkout session
$session_key = bin2hex(random_bytes(32));

$stmt = $conn->prepare("INSERT INTO checkoutsession (session_key, user_id, order_id, order_amount) VALUES (?, ?, ?, ?)");
$stmt->bind_param("siid", $session_key, $user_id, $order_id, $total_price);
$stmt->execute();
$stmt->close();

// Set HttpOnly cookie
setcookie("checkout_session", $session_key, [
    "httponly" => true,
    "secure" => true,   // only over HTTPS
    "samesite" => "Strict"
]);

// 9. Check if payment method is online
if (in_array($payment_method, ["credit_card", "debit_card", "net_banking"])) {
    try {
        $config = \server\ServerEnv::$config;
        $params = [
            "amount" => $total_price,  // ✅ only DB calculated amount is sent
            "currency" => "INR",
            "order_id" => "order-" . $order_id,
            "merchant_id" => $config["MERCHANT_ID"],
            "customer_id" => "user-$user_id",
            "customer_name" => $customer_name,
            "customer_email" => $customer_email,
            "customer_phone" => $customer_phone,
            "payment_page_client_id" => $config["PAYMENT_PAGE_CLIENT_ID"],
            "action" => "paymentPage",
            "return_url" => "https://ctkart.com/api/pg/handleJuspayReturn.php"
        ];

        $reqOpts = (new RequestOptions())->withCustomerId("user-$user_id");
        $session = OrderSession::create($params, $reqOpts);

        if ($session->status === "NEW") {
            echo json_encode([
                "success" => true,
                "order_id" => $order_id,
                "payment_url" => $session->paymentLinks["web"]
            ]);
        } else {
            http_response_code(500);
            echo json_encode(["error" => "Payment session creation failed"]);
        }
    } catch (Exception $ex) {
        http_response_code(500);
        echo json_encode(["error" => "Juspay error: " . $ex->getMessage()]);
    }
} else {
    // COD or offline payment
    echo json_encode([
        "success" => true,
        "order_id" => $order_id,
        "message" => "Order placed successfully"
    ]);
}

?>
