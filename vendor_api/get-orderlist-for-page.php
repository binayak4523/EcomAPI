<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Database connection
$host = 'localhost';
$dbname = 'multi_vender';
$user = 'root';
$password = '';

$conn = new mysqli($host, $user, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]);
    exit();
}

$conn->set_charset("utf8");

// Get the vendor ID from the query parameter or request
$vendorId = isset($_GET['vendor_id']) ? intval($_GET['vendor_id']) : (isset($_POST['vendor_id']) ? intval($_POST['vendor_id']) : 0);
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
$offset = ($page - 1) * $limit;
$status = isset($_GET['status']) ? $conn->real_escape_string($_GET['status']) : '';
$searchQuery = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';

if (!$vendorId) {
    http_response_code(400);
    echo json_encode(['error' => 'Vendor ID is required']);
    exit;
}

try {
    // Build WHERE clause
    $whereConditions = ["s.VendorID = ?"];
    $params = [$vendorId];
    $paramTypes = 'i';

    if (!empty($status)) {
        $whereConditions[] = "o.order_status = ?";
        $params[] = $status;
        $paramTypes .= 's';
    }

    if (!empty($searchQuery)) {
        $whereConditions[] = "(o.order_id LIKE ? OR u.name LIKE ? OR u.email LIKE ?)";
        $searchTerm = '%' . $searchQuery . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $paramTypes .= 'sss';
    }

    $whereClause = implode(' AND ', $whereConditions);

    // Get total count
    $countQuery = "
        SELECT COUNT(*) as total
        FROM orders o
        INNER JOIN store s ON o.store_id = s.ID
        WHERE $whereClause
    ";

    $countStmt = $conn->prepare($countQuery);
    $countStmt->bind_param($paramTypes, ...$params);
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $countData = $countResult->fetch_assoc();
    $totalOrders = $countData['total'] ?? 0;
    $countStmt->close();

    // Get orders with details
    $ordersQuery = "
        SELECT 
            o.order_id,
            o.total_price,
            o.order_date,
            o.order_status,
            o.payment_status,
            o.billingad_id,
            o.shippingad_id,
            u.name as customer_name,
            u.email as customer_email,
            u.mobile as customer_phone,
            ba.address1 as billing_address1,
            ba.address2 as billing_address2,
            ba.city as billing_city,
            ba.state as billing_state,
            ba.pin as billing_pin,
            ba.landmark as billing_landmark,
            sa.address1 as shipping_address1,
            sa.address2 as shipping_address2,
            sa.city as shipping_city,
            sa.state as shipping_state,
            sa.pin as shipping_pin,
            sa.landmark as shipping_landmark,
            COUNT(oi.id) as item_count
        FROM orders o
        INNER JOIN store s ON o.store_id = s.ID
        INNER JOIN users u ON o.user_id = u.user_id
        LEFT JOIN addresses ba ON o.billingad_id = ba.id
        LEFT JOIN addresses sa ON o.shippingad_id = sa.id
        LEFT JOIN order_items oi ON o.order_id = oi.order_id
        WHERE $whereClause
        GROUP BY o.order_id, o.total_price, o.order_date, o.order_status, o.payment_status, o.billingad_id, o.shippingad_id,
                 u.name, u.email, u.mobile, ba.address1, ba.address2, ba.city, ba.state, ba.pin, ba.landmark,
                 sa.address1, sa.address2, sa.city, sa.state, sa.pin, sa.landmark
        ORDER BY o.order_date DESC
        LIMIT ? OFFSET ?
    ";

    $params[] = $limit;
    $params[] = $offset;
    $paramTypes .= 'ii';

    $ordersStmt = $conn->prepare($ordersQuery);
    $ordersStmt->bind_param($paramTypes, ...$params);
    $ordersStmt->execute();
    $ordersResult = $ordersStmt->get_result();
    $orders = $ordersResult->fetch_all(MYSQLI_ASSOC);
    $ordersStmt->close();

    // Get order status summary
    $statusQuery = "
        SELECT 
            o.order_status,
            COUNT(*) as count
        FROM orders o
        INNER JOIN store s ON o.store_id = s.ID
        WHERE s.VendorID = ?
        GROUP BY o.order_status
        ORDER BY count DESC
    ";

    $statusStmt = $conn->prepare($statusQuery);
    $statusStmt->bind_param('i', $vendorId);
    $statusStmt->execute();
    $statusResult = $statusStmt->get_result();
    $statusSummary = $statusResult->fetch_all(MYSQLI_ASSOC);
    $statusStmt->close();

    // Return data
    echo json_encode([
        'success' => true,
        'data' => [
            'orders' => $orders,
            'pagination' => [
                'total' => $totalOrders,
                'page' => $page,
                'limit' => $limit,
                'totalPages' => ceil($totalOrders / $limit)
            ],
            'statusSummary' => $statusSummary
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error fetching orders: ' . $e->getMessage()]);
}

$conn->close();
?>
