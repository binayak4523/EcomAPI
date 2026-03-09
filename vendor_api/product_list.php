<?php
// product_list.php
// Updated to retrieve a product image from the product_images table
// (picking the default image if set, otherwise the first by img_seq).

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

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

// Base SQL query: fetch product info + one image
$sql = "
    SELECT
        im.*,
        c.category_name,
        sc.subcategory,
        b.Brand,
        (
            SELECT pi.path_url
            FROM product_images pi
            WHERE pi.product_id = im.id
            ORDER BY 
                CASE WHEN pi.default_img = 'y' THEN 0 ELSE 1 END, 
                pi.img_seq
            LIMIT 1
        ) AS image_path
    FROM item_master im
    LEFT JOIN category c ON im.category_id = c.category_id
    LEFT JOIN subcategory sc ON im.subcategory_id = sc.scategoryid
    LEFT JOIN brands b ON im.BrandID = b.BrandID
    WHERE im.VendorID = ?
";

// Build parameter arrays
$params = [$vendorID];
$types  = "i";

if (!empty($search)) {
    $sql .= "
        AND (
            im.item_name LIKE ?
            OR c.category_name LIKE ?
            OR sc.subcategory LIKE ?
            OR b.Brand LIKE ?
        )
    ";
    $searchTerm = "%{$search}%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types   .= "ssss";
}

// Count total records (for pagination)
$countSql = "
    SELECT COUNT(*) as total
    FROM item_master im
    LEFT JOIN category c ON im.category_id = c.category_id
    LEFT JOIN subcategory sc ON im.subcategory_id = sc.scategoryid
    LEFT JOIN brands b ON im.BrandID = b.BrandID
    WHERE im.VendorID = ?
";

$countParams = [$vendorID];
$countTypes  = "i";

if (!empty($search)) {
    $countSql .= "
        AND (
            im.item_name LIKE ?
            OR c.category_name LIKE ?
            OR sc.subcategory LIKE ?
            OR b.Brand LIKE ?
        )
    ";
    $searchTerm = "%{$search}%";
    $countParams[] = $searchTerm;
    $countParams[] = $searchTerm;
    $countParams[] = $searchTerm;
    $countParams[] = $searchTerm;
    $countTypes   .= "ssss";
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
$sql .= " ORDER BY im.id DESC LIMIT ? OFFSET ?";
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

$products = [];
while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}
$stmt->close();
$conn->close();

echo json_encode([
    "products"     => $products,
    "totalPages"   => $totalPages,
    "currentPage"  => $page,
    "totalRecords" => $totalRecords
]);
?>