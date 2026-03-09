<?php
// get-stores.php
// Retrieve stores for a vendor with pagination and search

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include 'db.php';  // Make sure db.php contains your correct connection info
// Create connection
$conn = new mysqli($host, $user, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get search and pagination parameters from GET
$search   = isset($_GET['search']) ? trim($_GET['search']) : '';
$page     = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$pageSize = isset($_GET['pageSize']) ? (int)$_GET['pageSize'] : 10;
$vendorID = isset($_GET['vendorID']) ? (int)$_GET['vendorID'] : 0;
$offset   = ($page - 1) * $pageSize;

if ($vendorID === 0) {
    http_response_code(400);
    echo json_encode(["error" => "VendorID is required"]);
    exit;
}

// Base SQL query: fetch store info
$sql = "
    SELECT *
    FROM store
    WHERE VendorID = ?
";

// Build parameter arrays
$params = [$vendorID];
$types  = "i";

if (!empty($search)) {
    $sql .= "
        AND (
            Store_Name LIKE ?
            OR email LIKE ?
            OR saddress LIKE ?
            OR store_manager LIKE ?
            OR GSTNO LIKE ?
            OR PAN LIKE ?
        )
    ";
    $searchTerm = "%{$search}%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types   .= "ssssss";
}

// Count total records (for pagination)
$countSql = "
    SELECT COUNT(*) as total
    FROM store
    WHERE VendorID = ?
";

$countParams = [$vendorID];
$countTypes  = "i";

if (!empty($search)) {
    $countSql .= "
        AND (
            Store_Name LIKE ?
            OR email LIKE ?
            OR saddress LIKE ?
            OR store_manager LIKE ?
            OR GSTNO LIKE ?
            OR PAN LIKE ?
        )
    ";
    $searchTerm = "%{$search}%";
    $countParams[] = $searchTerm;
    $countParams[] = $searchTerm;
    $countParams[] = $searchTerm;
    $countParams[] = $searchTerm;
    $countParams[] = $searchTerm;
    $countParams[] = $searchTerm;
    $countTypes   .= "ssssss";
}

// Prepare and execute the count query
$stmtCount = $conn->prepare($countSql);
if (!$stmtCount) {
    http_response_code(500);
    echo json_encode(["error" => "Database error (countSql): " . $conn->error]);
    exit;
}

// Always bind parameters for count query
$stmtCount->bind_param($countTypes, ...$countParams);
$stmtCount->execute();
$resultCount = $stmtCount->get_result();
$totalRow = $resultCount->fetch_assoc();
$totalRecords = $totalRow['total'];
$totalPages = ceil($totalRecords / $pageSize);
$stmtCount->close();

// Append ORDER BY and LIMIT to the main query
$sql .= " ORDER BY ID DESC LIMIT ? OFFSET ?";
$params[] = $pageSize;
$params[] = $offset;
$types   .= "ii";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(["error" => "Database error (mainSql): " . $conn->error]);
    exit;
}

// Always bind parameters
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$stores = [];
while ($row = $result->fetch_assoc()) {
    $stores[] = $row;
}
$stmt->close();
$conn->close();

echo json_encode([
    "stores"       => $stores,
    "totalPages"   => $totalPages,
    "currentPage"  => $page,
    "totalRecords" => $totalRecords
]);
?>
