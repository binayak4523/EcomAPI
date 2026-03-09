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

    $response = [
        "products" => [],
        "trends" => [
            "labels" => [],
            "data" => []
        ],
        "searchWords" => []
    ];

    // For Top 5 Products with item names
    $productQuery = "
        SELECT 
            im.id as product_id,
            im.item_name,
            COUNT(*) as count,
            SUM(oi.quantity) as total_quantity
        FROM order_items oi
        JOIN item_master im ON oi.product_id = im.id
        GROUP BY oi.product_id, im.item_name
        ORDER BY count DESC
        LIMIT 5
    ";

    $result = $conn->query($productQuery);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $response["products"][] = [
                "product_id" => $row['product_id'],
                "name" => $row['item_name'],
                "count" => $row['count'],
                "quantity" => $row['total_quantity']
            ];
        }
    }

    // For Order Trends (last 6 months)
    $trendQuery = "
        SELECT 
            DATE_FORMAT(order_date, '%b') as month,
            YEAR(order_date) as year,
            COUNT(*) as count,
            SUM(total_price) as revenue
        FROM orders 
        WHERE order_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY YEAR(order_date), MONTH(order_date), DATE_FORMAT(order_date, '%b')
        ORDER BY YEAR(order_date) ASC, MONTH(order_date) ASC
    ";

    $result = $conn->query($trendQuery);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $response["trends"]["labels"][] = $row['month'] . " " . $row['year'];
            $response["trends"]["data"][] =                 
                (float)$row['revenue'];
        }
    }

    // For Recent Orders Status
    $statusQuery = "
        SELECT 
            order_status,
            COUNT(*) as count
        FROM orders
        WHERE order_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY order_status
    ";

    $result = $conn->query($statusQuery);
    if ($result) {
        $response["orderStatus"] = [];
        while ($row = $result->fetch_assoc()) {
            $response["orderStatus"][] = [
                "status" => $row['order_status'],
                "count" => (int)$row['count']
            ];
        }
    }

    // For Search Terms (if search_logs table exists)
    $searchQuery = "
        SELECT 
            search_term as word,
            COUNT(*) as count
        FROM search_logs
        WHERE search_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY search_term
        ORDER BY count DESC
        LIMIT 5
    ";

    // Try to execute search query only if the table exists
    $tableCheckQuery = "SHOW TABLES LIKE 'search_logs'";
    $tableExists = $conn->query($tableCheckQuery)->num_rows > 0;

    if ($tableExists) {
        $result = $conn->query($searchQuery);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $response["searchWords"][] = [
                    "word" => $row['word'],
                    "count" => (int)$row['count']
                ];
            }
        }
    }

    // Additional Summary Statistics
    $summaryQuery = "
        SELECT 
            COUNT(DISTINCT o.order_id) as total_orders,
            COUNT(DISTINCT o.user_id) as unique_customers,
            SUM(o.total_price) as total_revenue,
            AVG(o.total_price) as average_order_value
        FROM orders o
        WHERE o.order_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ";

    $result = $conn->query($summaryQuery);
    if ($result) {
        $summary = $result->fetch_assoc();
        $response["summary"] = [
            "totalOrders" => (int)$summary['total_orders'],
            "uniqueCustomers" => (int)$summary['unique_customers'],
            "totalRevenue" => (float)$summary['total_revenue'],
            "averageOrderValue" => (float)$summary['average_order_value']
        ];
    }

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "error" => true,
        "message" => $e->getMessage()
    ]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}