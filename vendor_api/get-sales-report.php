<?php
// get-sales-report.php
// Fetches sales report data with support for order-wise and product-wise views

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

include 'db.php';

// Create connection
$conn = new mysqli($host, $user, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get parameters
$vendorID = isset($_GET['vendorID']) ? (int)$_GET['vendorID'] : 0;
$storeID = isset($_GET['storeID']) ? (int)$_GET['storeID'] : 0;
$viewMode = isset($_GET['view_mode']) ? $_GET['view_mode'] : 'order';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$pageSize = isset($_GET['pageSize']) ? (int)$_GET['pageSize'] : 10;
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$startDate = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$endDate = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

$offset = ($page - 1) * $pageSize;

// Validate required parameters
if ($vendorID === 0) {
    http_response_code(400);
    echo json_encode(["error" => "VendorID is required"]);
    exit;
}

if ($storeID === 0) {
    http_response_code(400);
    echo json_encode(["error" => "StoreID is required"]);
    exit;
}

// Initialize response
$response = [
    "sales" => [],
    "product_sales" => [],
    "stats" => [
        "totalSales" => 0,
        "totalRevenue" => 0,
        "totalOrders" => 0,
        "averageOrderValue" => 0
    ],
    "totalPages" => 0,
    "totalRecords" => 0,
    "totalProductRecords" => 0
];

if ($viewMode === 'order') {
    // ORDER WISE VIEW
    
    // Build the base SQL query for order-wise sales
    $sql = "
        SELECT
            o.Order_id,
            o.product_id,
            oi.product_id as item_product_id,
            im.item_name as product_name,
            u.name as customer_name,
            oi.quantity,
            oi.price,
            o.total_price as total,
            o.order_date as sale_date,
            o.order_status as status,
            o.store_id,
            o.tracking_no,
            o.waybill_no,
            o.payment_method
        FROM orders o
        LEFT JOIN order_items oi ON o.Order_id = oi.order_id
        LEFT JOIN item_master im ON oi.product_id = im.id
        LEFT JOIN users u ON o.user_id = u.user_id
        WHERE o.store_id = ?
    ";
    
    $params = [$storeID];
    $types = "i";
    
    // Add search filter (order_id)
    if (!empty($search)) {
        $sql .= " AND o.Order_id LIKE ?";
        $searchTerm = "%{$search}%";
        $params[] = $searchTerm;
        $types .= "s";
    }
    
    // Add date range filter
    if (!empty($startDate)) {
        $sql .= " AND DATE(o.order_date) >= ?";
        $params[] = $startDate;
        $types .= "s";
    }
    
    if (!empty($endDate)) {
        $sql .= " AND DATE(o.order_date) <= ?";
        $params[] = $endDate;
        $types .= "s";
    }
    
    // Add status filter
    if (!empty($status)) {
        $sql .= " AND o.order_status = ?";
        $params[] = $status;
        $types .= "s";
    }
    
    // Count total records
    $countSql = "
        SELECT COUNT(DISTINCT o.Order_id) as total
        FROM orders o
        WHERE o.store_id = ?
    ";
    
    $countParams = [$storeID];
    $countTypes = "i";
    
    if (!empty($search)) {
        $countSql .= " AND o.Order_id LIKE ?";
        $countParams[] = "%{$search}%";
        $countTypes .= "s";
    }
    
    if (!empty($startDate)) {
        $countSql .= " AND DATE(o.order_date) >= ?";
        $countParams[] = $startDate;
        $countTypes .= "s";
    }
    
    if (!empty($endDate)) {
        $countSql .= " AND DATE(o.order_date) <= ?";
        $countParams[] = $endDate;
        $countTypes .= "s";
    }
    
    // Add status filter to count
    if (!empty($status)) {
        $countSql .= " AND o.order_status = ?";
        $countParams[] = $status;
        $countTypes .= "s";
    }
    
    // Execute count query
    $stmtCount = $conn->prepare($countSql);
    if (!$stmtCount) {
        http_response_code(500);
        echo json_encode(["error" => "Database error (countSql): " . $conn->error]);
        exit;
    }
    
    if (!empty($countParams)) {
        $stmtCount->bind_param($countTypes, ...$countParams);
    }
    $stmtCount->execute();
    $resultCount = $stmtCount->get_result();
    $totalRow = $resultCount->fetch_assoc();
    $totalRecords = $totalRow['total'];
    $response['totalRecords'] = $totalRecords;
    $response['totalPages'] = ceil($totalRecords / $pageSize);
    $stmtCount->close();
    
    // Add ORDER BY and LIMIT
    $sql .= " ORDER BY o.order_date DESC LIMIT ? OFFSET ?";
    $params[] = $pageSize;
    $params[] = $offset;
    $types .= "ii";
    
    // Execute main query
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(["error" => "Database error (mainSql): " . $conn->error]);
        exit;
    }
    
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $sales = [];
    while ($row = $result->fetch_assoc()) {
        $sales[] = $row;
    }
    $stmt->close();
    
    $response['sales'] = $sales;
    
    // Calculate statistics for order-wise view
    $statsSql = "
        SELECT
            COUNT(DISTINCT o.Order_id) as totalOrders,
            SUM(o.quantity) as totalSales,
            SUM(o.total_price) as totalRevenue,
            AVG(o.total_price) as averageOrderValue
        FROM orders o
        WHERE o.store_id = ?
    ";
    
    $statsParams = [$storeID];
    $statsTypes = "i";
    
    if (!empty($search)) {
        $statsSql .= " AND o.Order_id LIKE ?";
        $statsParams[] = "%{$search}%";
        $statsTypes .= "s";
    }
    
    if (!empty($startDate)) {
        $statsSql .= " AND DATE(o.order_date) >= ?";
        $statsParams[] = $startDate;
        $statsTypes .= "s";
    }
    
    if (!empty($endDate)) {
        $statsSql .= " AND DATE(o.order_date) <= ?";
        $statsParams[] = $endDate;
        $statsTypes .= "s";
    }
    
    // Add status filter to stats
    if (!empty($status)) {
        $statsSql .= " AND o.order_status = ?";
        $statsParams[] = $status;
        $statsTypes .= "s";
    }
    
    $stmtStats = $conn->prepare($statsSql);
    if (!$stmtStats) {
        http_response_code(500);
        echo json_encode(["error" => "Database error (statsSql): " . $conn->error]);
        exit;
    }
    
    if (!empty($statsParams)) {
        $stmtStats->bind_param($statsTypes, ...$statsParams);
    }
    $stmtStats->execute();
    $resultStats = $stmtStats->get_result();
    $statsRow = $resultStats->fetch_assoc();
    
    $response['stats'] = [
        "totalSales" => (int)($statsRow['totalSales'] ?? 0),
        "totalRevenue" => (float)($statsRow['totalRevenue'] ?? 0),
        "totalOrders" => (int)($statsRow['totalOrders'] ?? 0),
        "averageOrderValue" => (float)($statsRow['averageOrderValue'] ?? 0)
    ];
    
    $stmtStats->close();
    
} else if ($viewMode === 'product') {
    // PRODUCT WISE VIEW
    
    // Build SQL for product-wise aggregated sales
    $sql = "
        SELECT
            oi.product_id,
            im.item_name as product_name,
            im.category_id,
            SUM(oi.quantity) as total_quantity,
            SUM(oi.price * oi.quantity) as total_revenue,
            AVG(oi.price) as average_price,
            COUNT(DISTINCT o.Order_id) as order_count
        FROM orders o
        JOIN order_items oi ON o.Order_id = oi.order_id
        LEFT JOIN item_master im ON oi.product_id = im.id
        WHERE o.store_id = ?
    ";
    
    $params = [$storeID];
    $types = "i";
    
    // Add filters
    if (!empty($search)) {
        $sql .= " AND o.Order_id LIKE ?";
        $searchTerm = "%{$search}%";
        $params[] = $searchTerm;
        $types .= "s";
    }
    
    if (!empty($startDate)) {
        $sql .= " AND DATE(o.order_date) >= ?";
        $params[] = $startDate;
        $types .= "s";
    }
    
    if (!empty($endDate)) {
        $sql .= " AND DATE(o.order_date) <= ?";
        $params[] = $endDate;
        $types .= "s";
    }
    
    // Add status filter
    if (!empty($status)) {
        $sql .= " AND o.order_status = ?";
        $params[] = $status;
        $types .= "s";
    }
    $groupByClause = " GROUP BY oi.product_id";
    
    // Count total product records (for pagination)
    $countSql = "
        SELECT COUNT(*) as total FROM (
            SELECT DISTINCT oi.product_id
            FROM orders o
            JOIN order_items oi ON o.Order_id = oi.order_id
            WHERE o.store_id = ?
    ";
    
    $countParams = [$storeID];
    $countTypes = "i";
    
    if (!empty($search)) {
        $countSql .= " AND o.Order_id LIKE ?";
        $countParams[] = "%{$search}%";
        $countTypes .= "s";
    }
    
    if (!empty($startDate)) {
        $countSql .= " AND DATE(o.order_date) >= ?";
        $countParams[] = $startDate;
        $countTypes .= "s";
    }
    
    if (!empty($endDate)) {
        $countSql .= " AND DATE(o.order_date) <= ?";
        $countParams[] = $endDate;
        $countTypes .= "s";
    }
    
    // Add status filter to count
    if (!empty($status)) {
        $countSql .= " AND o.order_status = ?";
        $countParams[] = $status;
        $countTypes .= "s";
    }
    
    // Execute count query
    $stmtCount = $conn->prepare($countSql);
    if (!$stmtCount) {
        http_response_code(500);
        echo json_encode(["error" => "Database error (countSql): " . $conn->error]);
        exit;
    }
    
    if (!empty($countParams)) {
        $stmtCount->bind_param($countTypes, ...$countParams);
    }
    $stmtCount->execute();
    $resultCount = $stmtCount->get_result();
    $totalRow = $resultCount->fetch_assoc();
    $totalRecords = $totalRow['total'];
    $response['totalProductRecords'] = $totalRecords;
    $response['totalPages'] = ceil($totalRecords / $pageSize);
    $stmtCount->close();
    
    // Add GROUP BY, ORDER BY and LIMIT
    $sql .= $groupByClause . " ORDER BY total_revenue DESC LIMIT ? OFFSET ?";
    $params[] = $pageSize;
    $params[] = $offset;
    $types .= "ii";
    
    // Execute main query
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(["error" => "Database error (mainSql): " . $conn->error]);
        exit;
    }
    
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $productSales = [];
    while ($row = $result->fetch_assoc()) {
        $productSales[] = $row;
    }
    $stmt->close();
    
    $response['product_sales'] = $productSales;
    
    // Calculate statistics for product-wise view
    $statsSql = "
        SELECT
            SUM(oi.quantity) as totalSales,
            SUM(oi.price * oi.quantity) as totalRevenue,
            COUNT(DISTINCT o.Order_id) as totalOrders,
            AVG(o.total_price) as averageOrderValue
        FROM orders o
        JOIN order_items oi ON o.Order_id = oi.order_id
        WHERE o.store_id = ?
    ";
    
    $statsParams = [$storeID];
    $statsTypes = "i";
    
    if (!empty($search)) {
        $statsSql .= " AND o.Order_id LIKE ?";
        $statsParams[] = "%{$search}%";
        $statsTypes .= "s";
    }
    
    if (!empty($startDate)) {
        $statsSql .= " AND DATE(o.order_date) >= ?";
        $statsParams[] = $startDate;
        $statsTypes .= "s";
    }
    
    if (!empty($endDate)) {
        $statsSql .= " AND DATE(o.order_date) <= ?";
        $statsParams[] = $endDate;
        $statsTypes .= "s";
    }
    
    // Add status filter to stats
    if (!empty($status)) {
        $statsSql .= " AND o.order_status = ?";
        $statsParams[] = $status;
        $statsTypes .= "s";
    }
    
    $stmtStats = $conn->prepare($statsSql);
    if (!$stmtStats) {
        http_response_code(500);
        echo json_encode(["error" => "Database error (statsSql): " . $conn->error]);
        exit;
    }
    
    if (!empty($statsParams)) {
        $stmtStats->bind_param($statsTypes, ...$statsParams);
    }
    $stmtStats->execute();
    $resultStats = $stmtStats->get_result();
    $statsRow = $resultStats->fetch_assoc();
    
    $response['stats'] = [
        "totalSales" => (int)($statsRow['totalSales'] ?? 0),
        "totalRevenue" => (float)($statsRow['totalRevenue'] ?? 0),
        "totalOrders" => (int)($statsRow['totalOrders'] ?? 0),
        "averageOrderValue" => (float)($statsRow['averageOrderValue'] ?? 0)
    ];
    
    $stmtStats->close();
}

$conn->close();

// Return response
http_response_code(200);
echo json_encode($response);
?>
