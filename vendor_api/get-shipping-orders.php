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

    // Get parameters
    $storeID = isset($_GET['storeID']) ? (int)$_GET['storeID'] : 0;
    $status = isset($_GET['status']) ? $_GET['status'] : null;
    
    if ($storeID === 0) {
        throw new Exception("StoreID is required");
    }
    
    // Map frontend status to database status
    $statusMap = [
        'pending' => 'Invoiced',
        'processing' => 'Processing',
        'in-transit' => 'Packed',
        'shipped' => 'Shipped',
        'delivered' => 'Delivered'
    ];

    // Get the requested status, default to showing all if not specified
    $dbStatus = ($status !== null && isset($statusMap[$status])) ? $statusMap[$status] : null;

    // Main query to get orders with all necessary details, filtered by storeID
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
        WHERE o.store_id = ?";
    
    if ($dbStatus !== null) {
        $sql .= " AND UPPER(o.order_status) = UPPER(?)";
    }
    
    $sql .= " GROUP BY o.order_id
        ORDER BY o.order_date DESC
    ";

    $stmt = $conn->prepare($sql);
    if ($dbStatus !== null) {
        $stmt->bind_param("is", $storeID, $dbStatus);
    } else {
        $stmt->bind_param("i", $storeID);
    }
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
        unset($row['city'], $row['state'], $row['pin']);
        
        $orders[] = $row;
    }

    // Get counts for dashboard
    $counts = [
        'pending' => 0,
        'processing' => 0,
        'shipped' => 0,
        'in-transit' => 0,
        'delivered' => 0,
        'total' => 0
    ];

    $countSql = "SELECT o.order_status, COUNT(*) as count 
                 FROM orders o 
                 WHERE o.store_id = ?
                 GROUP BY o.order_status";
    $countStmt = $conn->prepare($countSql);
    $countStmt->bind_param("i", $storeID);
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    
    while ($row = $countResult->fetch_assoc()) {
        // Map database status to frontend status
        $dbStatusUpper = strtoupper($row['order_status']);
        $foundStatus = null;
        
        // Check against all possible database statuses
        foreach ($statusMap as $frontendStatus => $mappedDbStatus) {
            if ($dbStatusUpper === strtoupper($mappedDbStatus)) {
                $foundStatus = $frontendStatus;
                break;
            }
        }
        
        if ($foundStatus !== null) {
            $counts[$foundStatus] += (int)$row['count'];
        }
        
        $counts['total'] += (int)$row['count'];
    }

    echo json_encode([
        'orders' => $orders,
        'counts' => $counts
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
} finally {
    if (isset($stmt)) $stmt->close();
    if (isset($countStmt)) $countStmt->close();
    if (isset($conn)) $conn->close();
}
?>