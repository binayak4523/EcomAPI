<?php
// CORS headers to allow cross-origin requests from the admin app
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// Short-circuit for preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    // return only the CORS headers and exit
    http_response_code(200);
    exit;
}

// Include DB credentials
include("db.php");

// Read raw input (support JSON body) and fallback to POST
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

$orderId = 0;
if (is_array($data) && isset($data['orderId'])) {
    $orderId = intval($data['orderId']);
} elseif (isset($_POST['orderId'])) {
    $orderId = intval($_POST['orderId']);
} elseif (isset($_POST['orderid'])) {
    $orderId = intval($_POST['orderid']);
}

if ($orderId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid orderId']);
    exit;
}

// Connect to database using mysqli
$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Prepare and execute update
$stmt = $conn->prepare("UPDATE orders SET order_status = ? WHERE order_id = ?");
$status = 'In Transit';
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to prepare statement']);
    $conn->close();
    exit;
}

$stmt->bind_param('si', $status, $orderId);
if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
    $stmt->close();
    $conn->close();
    exit;
}

if ($stmt->affected_rows > 0) {
    echo json_encode(['success' => true, 'message' => 'Order updated to In Transit']);
} else {
    // No rows updated — maybe invalid id or already same status
    echo json_encode(['success' => false, 'message' => 'No order updated (invalid id or status already In Transit)']);
}

$stmt->close();
$conn->close();
?>
