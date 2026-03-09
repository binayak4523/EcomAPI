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
include 'db.php';

$conn = new mysqli($host, $user, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]);
    exit();
}

$conn->set_charset("utf8");

// Get the vendor ID from the query parameter or request
$vendorId = isset($_GET['vendorID']) ? intval($_GET['vendorID']) : (isset($_POST['vendorID']) ? intval($_POST['vendorID']) : 0);

if (!$vendorId) {
    http_response_code(400);
    echo json_encode(['error' => 'Vendor ID is required']);
    exit;
}

try {
    // Get all orders for the vendor's stores
    $ordersQuery = "
        SELECT 
            o.order_id,
            o.total_price,
            o.order_date,
            o.order_status,
            o.payment_status,
            u.name as customer_name,
            u.user_id as customer_id,
            u.email as customer_email
        FROM orders o
        INNER JOIN store s ON o.store_id = s.ID
        INNER JOIN users u ON o.user_id = u.user_id
        WHERE s.VendorID = ? and o.order_status = 'New Order'
        ORDER BY o.order_date DESC
    ";
    
    $stmt = $conn->prepare($ordersQuery);
    $stmt->bind_param('i', $vendorId);
    $stmt->execute();
    $ordersResult = $stmt->get_result();
    $orders = $ordersResult->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Calculate Total Revenue
    $revenueQuery = "
        SELECT SUM(o.total_price) as total_revenue
        FROM orders o
        INNER JOIN store s ON o.store_id = s.ID
        WHERE s.VendorID = ? AND o.payment_status IN ('Completed', 'Success', 'completed')
    ";
    
    $stmt = $conn->prepare($revenueQuery);
    $stmt->bind_param('i', $vendorId);
    $stmt->execute();
    $revenueResult = $stmt->get_result();
    $revenueData = $revenueResult->fetch_assoc();
    $totalRevenue = $revenueData['total_revenue'] ?? 0;
    $stmt->close();

    // Calculate Total Orders
    $totalOrders = count($orders);

    // Get Products Sold
    $productsSoldQuery = "
        SELECT SUM(oi.quantity) as total_products_sold
        FROM order_items oi
        INNER JOIN orders o ON oi.order_id = o.order_id
        INNER JOIN store s ON o.store_id = s.ID
        WHERE s.VendorID = ?
    ";
    
    $stmt = $conn->prepare($productsSoldQuery);
    $stmt->bind_param('i', $vendorId);
    $stmt->execute();
    $productsSoldResult = $stmt->get_result();
    $productsSoldData = $productsSoldResult->fetch_assoc();
    $totalProductsSold = $productsSoldData['total_products_sold'] ?? 0;
    $stmt->close();

    // Get Canceled Orders
    $canceledOrdersQuery = "
        SELECT COUNT(*) as canceled_orders
        FROM orders o
        INNER JOIN store s ON o.store_id = s.ID
        WHERE s.VendorID = ? AND o.order_status = 'Cancelled'
    ";
    
    $stmt = $conn->prepare($canceledOrdersQuery);
    $stmt->bind_param('i', $vendorId);
    $stmt->execute();
    $canceledOrdersResult = $stmt->get_result();
    $canceledOrdersData = $canceledOrdersResult->fetch_assoc();
    $canceledOrders = $canceledOrdersData['canceled_orders'] ?? 0;
    $stmt->close();

    // Get Top Products by Sales
    $topProductsQuery = "
        SELECT 
            im.item_name as product_name,
            im.id as product_id,
            SUM(oi.quantity) as sales_count,
            SUM(oi.quantity * oi.price) as total_revenue_product
        FROM order_items oi
        INNER JOIN orders o ON oi.order_id = o.order_id
        INNER JOIN item_master im ON oi.product_id = im.id
        INNER JOIN store s ON o.store_id = s.ID
        WHERE s.VendorID = ? AND im.VendorID = ?
        GROUP BY im.id, im.item_name
        ORDER BY sales_count DESC
        LIMIT 5
    ";
    
    $stmt = $conn->prepare($topProductsQuery);
    $stmt->bind_param('ii', $vendorId, $vendorId);
    $stmt->execute();
    $topProductsResult = $stmt->get_result();
    $topProducts = $topProductsResult->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Get Recent Orders with product details (limit 10)
    $recentOrdersQuery = "
        SELECT 
            o.order_id,
            o.total_price,
            o.order_date,
            o.order_status,
            u.name as customer_name,
            u.user_id as customer_id,
            u.email as customer_email,
            GROUP_CONCAT(im.item_name SEPARATOR ', ') as products,
            SUM(oi.quantity) as total_quantity
        FROM orders o
        INNER JOIN users u ON o.user_id = u.user_id
        INNER JOIN order_items oi ON o.order_id = oi.order_id
        INNER JOIN item_master im ON oi.product_id = im.id
        INNER JOIN store s ON o.store_id = s.ID
        WHERE s.VendorID = ? and o.order_status = 'New Order'
        GROUP BY o.order_id, o.total_price, o.order_date, o.order_status, u.name, u.email
        ORDER BY o.order_date DESC
        LIMIT 10
    ";
    
    $stmt = $conn->prepare($recentOrdersQuery);
    $stmt->bind_param('i', $vendorId);
    $stmt->execute();
    $recentOrdersResult = $stmt->get_result();
    $recentOrders = $recentOrdersResult->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Get Sales Overview (order status breakdown)
    $salesOverviewQuery = "
        SELECT 
            o.order_status,
            COUNT(*) as count,
            SUM(o.total_price) as revenue
        FROM orders o
        INNER JOIN store s ON o.store_id = s.ID
        WHERE s.VendorID = ?
        GROUP BY o.order_status
        ORDER BY count DESC
    ";
    
    $stmt = $conn->prepare($salesOverviewQuery);
    $stmt->bind_param('i', $vendorId);
    $stmt->execute();
    $salesOverviewResult = $stmt->get_result();
    $salesOverview = $salesOverviewResult->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Return all data
    echo json_encode([
        'success' => true,
        'data' => [
            'totalRevenue' => floatval($totalRevenue),
            'totalOrders' => intval($totalOrders),
            'totalProductsSold' => intval($totalProductsSold),
            'canceledOrders' => intval($canceledOrders),
            'topProducts' => $topProducts,
            'recentOrders' => $recentOrders,
            'salesOverview' => $salesOverview
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error fetching order list: ' . $e->getMessage()]);
}
?>
