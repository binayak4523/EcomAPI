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
require 'db.php';

$conn = new mysqli($host, $user, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database connection failed: ' . $conn->connect_error
    ]);
    exit();
}

$conn->set_charset("utf8");

// Get parameters
$vendorId = isset($_GET['vendor_id']) ? intval($_GET['vendor_id']) : 0;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
$offset = ($page - 1) * $limit;
$searchQuery = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$startDate = isset($_GET['start_date']) ? $conn->real_escape_string($_GET['start_date']) : '';
$endDate = isset($_GET['end_date']) ? $conn->real_escape_string($_GET['end_date']) : '';

if (!$vendorId) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Vendor ID is required'
    ]);
    exit();
}

try {
    // Build WHERE clause
    $whereConditions = ["ih.vendorID = ?"];
    $params = [$vendorId];
    $paramTypes = 'i';

    if (!empty($searchQuery)) {
        $whereConditions[] = "(ih.InvNo LIKE ? OR ih.Party LIKE ?)";
        $searchTerm = '%' . $searchQuery . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $paramTypes .= 'ss';
    }

    if (!empty($startDate)) {
        $whereConditions[] = "ih.InvDate >= ?";
        $params[] = $startDate;
        $paramTypes .= 's';
    }

    if (!empty($endDate)) {
        $endDateFormatted = date('Y-m-d', strtotime($endDate . ' +1 day'));
        $whereConditions[] = "ih.InvDate < ?";
        $params[] = $endDateFormatted;
        $paramTypes .= 's';
    }

    $whereClause = implode(' AND ', $whereConditions);

    // Get total count
    $countQuery = "
        SELECT COUNT(DISTINCT ih.InvNo) as total
        FROM invoicehead ih
        LEFT JOIN orders o ON ih.OrderNo = o.order_id
        LEFT JOIN users u ON o.user_id = u.user_id
        WHERE $whereClause
    ";

    $countStmt = $conn->prepare($countQuery);
    if (!$countStmt) {
        throw new Exception("Prepare failed for count query: " . $conn->error);
    }

    $countStmt->bind_param($paramTypes, ...$params);
    if (!$countStmt->execute()) {
        throw new Exception("Execute failed for count query: " . $countStmt->error);
    }

    $countResult = $countStmt->get_result();
    $countData = $countResult->fetch_assoc();
    $totalRecords = $countData['total'] ?? 0;
    $totalPages = ceil($totalRecords / $limit);
    $countStmt->close();

    // Get invoice list with pagination
    $query = "
        SELECT 
            ih.InvNo as invNo,
            ih.InvDate as date,
            COALESCE(u.name, ih.Party) as customer,
            ih.TotalQty as totalQty,
            ih.TotalGross as totalAmount,
            (ih.TradeDiscount + ih.SpecialDiscount) as discount,
            ih.VatAmount as gstAmount,
            ih.Net as netAmount,
            'Complete' as status
        FROM invoicehead ih
        LEFT JOIN orders o ON ih.OrderNo = o.order_id
        LEFT JOIN users u ON o.user_id = u.user_id
        WHERE $whereClause
        ORDER BY ih.InvDate DESC, ih.InvNo DESC
        LIMIT ? OFFSET ?
    ";

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Prepare failed for invoice list query: " . $conn->error);
    }

    // Add limit and offset parameters
    $newParams = array_merge($params, [$limit, $offset]);
    $newParamTypes = $paramTypes . 'ii';

    $stmt->bind_param($newParamTypes, ...$newParams);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed for invoice list query: " . $stmt->error);
    }

    $result = $stmt->get_result();
    $invoices = [];

    while ($row = $result->fetch_assoc()) {
        $invoices[] = [
            'id' => $row['invNo'],
            'invNo' => $row['invNo'],
            'date' => $row['date'],
            'customer' => $row['customer'],
            'totalQty' => (int)$row['totalQty'],
            'totalAmount' => (float)$row['totalAmount'],
            'discount' => (float)$row['discount'],
            'gstAmount' => (float)$row['gstAmount'],
            'netAmount' => (float)$row['netAmount'],
            'status' => $row['status']
        ];
    }

    $stmt->close();

    // Return success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => [
            'invoices' => $invoices,
            'pagination' => [
                'currentPage' => $page,
                'totalPages' => $totalPages,
                'totalRecords' => $totalRecords,
                'limit' => $limit
            ]
        ]
    ]);

    $conn->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    $conn->close();
    exit();
}
?>
