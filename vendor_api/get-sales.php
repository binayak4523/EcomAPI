<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: *");
header("Content-Type: application/json");

include 'db.php';

try {
    $conn = new mysqli($host, $user, $password, $dbname);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    $startDate = isset($_GET['startDate']) ? $_GET['startDate'] : null;
    $endDate = isset($_GET['endDate']) ? $_GET['endDate'] : null;

    $sql = "SELECT o.order_id, u.name as customer_name, 
            COUNT(oi.item_id) as total_items, 
            o.total_amount, o.payment_method, 
            o.created_at as order_date, o.status
            FROM orders o
            LEFT JOIN users u ON o.user_id = u.user_id
            LEFT JOIN order_items oi ON o.order_id = oi.order_id
            WHERE 1=1";

    if ($startDate) {
        $sql .= " AND DATE(o.created_at) >= ?";
    }
    if ($endDate) {
        $sql .= " AND DATE(o.created_at) <= ?";
    }

    $sql .= " GROUP BY o.order_id ORDER BY o.created_at DESC";

    $stmt = $conn->prepare($sql);

    if ($startDate && $endDate) {
        $stmt->bind_param("ss", $startDate, $endDate);
    } else if ($startDate) {
        $stmt->bind_param("s", $startDate);
    } else if ($endDate) {
        $stmt->bind_param("s", $endDate);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $sales = array();
    while ($row = $result->fetch_assoc()) {
        $sales[] = $row;
    }

    echo json_encode($sales);

} catch(Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

if (isset($conn)) {
    $conn->close();
}