<?php
// get-parties.php
// Retrieve parties/suppliers for a vendor with pagination and search

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include 'db.php';

// Create connection
$conn = new mysqli($host, $user, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "Connection failed: " . $conn->connect_error]);
    exit;
}

// Get search and pagination parameters from GET
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$pageSize = isset($_GET['pageSize']) ? (int)$_GET['pageSize'] : 10;
$vendorID = isset($_GET['vendorID']) ? (int)$_GET['vendorID'] : 0;
$party_type = isset($_GET['party_type']) ? trim($_GET['party_type']) : '';
$offset = ($page - 1) * $pageSize;

if ($vendorID === 0) {
    http_response_code(400);
    echo json_encode(["error" => "VendorID is required"]);
    exit;
}

// Base SQL query - Get suppliers from LedgerMaster (Sundry Creditor group)
$sql = "
    SELECT AccID as ID, AccName as Party, Address1 as Address, Phone, TIN as Email, 
           StateCode, 0 as Duedays, 0 as Discount, 0 as CrLimit, GroupID as gid
    FROM ledgermaster
    WHERE CompanyId = ? AND Groupname = 'Sundry Creditor'
";

// Build parameter arrays
$params = [$vendorID];
$types = "i";

if (!empty($search)) {
    $sql .= "
        AND (
            AccName LIKE ?
            OR TIN LIKE ?
            OR Phone LIKE ?
            OR Address1 LIKE ?
        )
    ";
    $searchTerm = "%{$search}%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= "ssss";
}

if (!empty($party_type)) {
    $sql .= " AND party_type = ?";
    $params[] = $party_type;
    $types .= "s";
}

// Count total records
$countSql = "
    SELECT COUNT(*) as total
    FROM ledgermaster
    WHERE CompanyId = ? AND Groupname = 'Sundry Creditor'
";

$countParams = [$vendorID];
$countTypes = "i";

if (!empty($search)) {
    $countSql .= "
        AND (
            AccName LIKE ?
            OR TIN LIKE ?
            OR Phone LIKE ?
            OR Address1 LIKE ?
        )
    ";
    $searchTerm = "%{$search}%";
    $countParams[] = $searchTerm;
    $countParams[] = $searchTerm;
    $countParams[] = $searchTerm;
    $countParams[] = $searchTerm;
    $countTypes .= "ssss";
}

if (!empty($party_type)) {
    $countSql .= " AND party_type = ?";
    $countParams[] = $party_type;
    $countTypes .= "s";
}

// Prepare and execute count query
$stmtCount = $conn->prepare($countSql);
if (!$stmtCount) {
    http_response_code(500);
    echo json_encode(["error" => "Database error: " . $conn->error]);
    exit;
}

$stmtCount->bind_param($countTypes, ...$countParams);
$stmtCount->execute();
$resultCount = $stmtCount->get_result();
$totalRow = $resultCount->fetch_assoc();
$totalRecords = $totalRow['total'];
$totalPages = ceil($totalRecords / $pageSize);
$stmtCount->close();

// Append ORDER BY and LIMIT to main query
$sql .= " ORDER BY AccID DESC LIMIT ? OFFSET ?";
$params[] = $pageSize;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(["error" => "Database error: " . $conn->error]);
    exit;
}

$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$parties = [];
while ($row = $result->fetch_assoc()) {
    $parties[] = $row;
}
$stmt->close();
$conn->close();

echo json_encode([
    "parties" => $parties,
    "totalPages" => $totalPages,
    "currentPage" => $page,
    "totalRecords" => $totalRecords
]);
?>
