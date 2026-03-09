<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'db.php';

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['orderId']) || !isset($data['action'])) {
        throw new Exception('Missing required parameters');
    }

    $conn = new mysqli($host, $user, $password, $dbname);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    // Map actions to status
    $statusMap = [
        'generate-label' => 'Processing',
        'mark-shipped' => 'Shipped',
        'track' => 'Shipped',
        'mark-delivered' => 'Delivered'
    ];

    if (!isset($statusMap[$data['action']])) {
        throw new Exception('Invalid action');
    }

    $newStatus = $statusMap[$data['action']];
    
    // Update order status
    $sql = "UPDATE orders SET order_status = ? WHERE order_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $newStatus, $data['orderId']);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Order status updated successfully'
        ]);
    } else {
        throw new Exception('Failed to update order status');
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} finally {
    if (isset($stmt)) $stmt->close();
    if (isset($conn)) $conn->close();
}