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

    $sql = "SELECT o.Order_id, u.name as customer_name, 
            o.quantity as total_items, 
            o.total_price, o.payment_method, 
            o.order_date, o.order_status
            FROM orders o
            LEFT JOIN users u ON o.user_id = u.user_id
            WHERE 1=1";

    if ($startDate) {
        $sql .= " AND DATE(o.order_date) >= ?";
    }
    if ($endDate) {
        $sql .= " AND DATE(o.order_date) <= ?";
    }

    $sql .= " ORDER BY o.order_date DESC";

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