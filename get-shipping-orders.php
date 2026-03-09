<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'db.php';

try {
    $conn = new mysqli($host, $user, $password, $dbname);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    // Get status from query parameter
    $status = isset($_GET['status']) ? $_GET['status'] : 'pending';
    
    // Map frontend status to database status
    $statusMap = [
        'pending' => 'Invoiced',
        'processing' => 'Processing',
        'shipped' => 'Shipped',
        'in-transit' => 'In Transit',
        'delivered' => 'Delivered'
    ];

    $dbStatus = $statusMap[$status];

    // Main query to get orders with all necessary details
    $sql = "
        SELECT 
            o.order_id as id,
            o.order_date,
            o.total_price,
            o.order_status as status,
            o.InvoiceNo as invoice_no,
            o.invoicedate,
            u.name as customer_name,
            u.mobile as shipping_phone,
            a.address1 as shipping_address,
            a.city,
            a.state,
            a.pin,
            COUNT(oi.id) as items_count
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.user_id
        LEFT JOIN addresses a ON o.shippingad_id = a.id
        LEFT JOIN order_items oi ON o.order_id = oi.order_id
        WHERE o.order_status = ?
        GROUP BY o.order_id
        ORDER BY o.order_date DESC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $dbStatus);
    $stmt->execute();
    $result = $stmt->get_result();

    $orders = [];
    while ($row = $result->fetch_assoc()) {
        // Format shipping address
        $row['shipping_address'] = implode(', ', array_filter([
            $row['shipping_address'],
            $row['city'],
            $row['state'],
            $row['pin']
        ]));

        // Remove unnecessary fields
        unset($row['city'], $row['state'], $row['pincode']);
        
        $orders[] = $row;
    }

    // Get counts for dashboard
    $counts = [
        'pending' => 0,
        'processing' => 0,
        'shipped' => 0,
        'total' => 0
    ];

    $countSql = "SELECT order_status, COUNT(*) as count FROM orders GROUP BY order_status";
    $countResult = $conn->query($countSql);
    while ($row = $countResult->fetch_assoc()) {
        $frontendStatus = array_search($row['order_status'], $statusMap);
        if ($frontendStatus !== false) {
            $counts[$frontendStatus] = (int)$row['count'];
            $counts['total'] += (int)$row['count'];
        }
    }

    echo json_encode([
        'orders' => $orders,
        'counts' => $counts
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        '