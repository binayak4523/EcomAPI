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
    $whereConditions = ["ph.vendorID = ?"];
    $params = [$vendorId];
    $paramTypes = 'i';

    if (!empty($searchQuery)) {
        $whereConditions[] = "(ph.Supplier LIKE ? OR ph.InvNo LIKE ? OR ph.LrNo LIKE ?)";
        $searchTerm = '%' . $searchQuery . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $paramTypes .= 'sss';
    }

    if (!empty($startDate)) {
        $whereConditions[] = "ph.Purchasedate >= ?";
        $params[] = $startDate;
        $paramTypes .= 's';
    }

    if (!empty($endDate)) {
        $endDateFormatted = date('Y-m-d', strtotime($endDate . ' +1 day'));
        $whereConditions[] = "ph.Purchasedate < ?";
        $params[] = $endDateFormatted;
        $paramTypes .= 's';
    }

    $whereClause = implode(' AND ', $whereConditions);

    // Get total count
    $countQuery = "
        SELECT COUNT(*) as total
        FROM purchasehead ph
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

    // Get purchase list with pagination
    $query = "
        SELECT 
            ph.Slno as id,
            ph.Slno,
            ph.Purchasedate as date,
            ph.Supplier as supplier,
            ph.InvNo as invNo,
            ph.InvDate as invDate,
            ph.LrNo as lrNo,
            ph.TotalQty as totalQty,
            ph.TotalGross as totalGross,
            ph.lessDiscount as discount,
            ph.VatAmount as gstAmount,
            ph.NetAmount as netAmount,
            'Completed' as status
        FROM purchasehead ph
        WHERE $whereClause
        ORDER BY ph.Purchasedate DESC, ph.Slno DESC
        LIMIT ? OFFSET ?
    ";

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Prepare failed for purchase list query: " . $conn->error);
    }

    // Add limit and offset parameters
    $newParams = array_merge($params, [$limit, $offset]);
    $newParamTypes = $paramTypes . 'ii';

    $stmt->bind_param($newParamTypes, ...$newParams);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed for purchase list query: " . $stmt->error);
    }

    $result = $stmt->get_result();
    $purchases = [];

    while ($row = $result->fetch_assoc()) {
        $purchases[] = [
            'id' => $row['id'],
            'slNo' => $row['Slno'],
            'date' => $row['date'],
            'supplier' => $row['supplier'],
            'invNo' => $row['invNo'],
            'invDate' => $row['invDate'],
            'lrNo' => $row['lrNo'],
            'totalQty' => (float)$row['totalQty'],
            'totalGross' => (float)$row['totalGross'],
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
            'purchases' => $purchases,
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
