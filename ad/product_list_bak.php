<?php
// product_list.php
// This file retrieves product list data using the database connection from db.php

header('Content-Type: application/json');

include 'db.php';  // db.php should contain your connection details (host, user, password, database)
// Create connection
$conn = new mysqli($host, $user, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
// Get search and pagination parameters from GET
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$pageSize = isset($_GET['pageSize']) ? (int)$_GET['pageSize'] : 10;
$offset = ($page - 1) * $pageSize;

// Build the base SQL query with joins to fetch product and related info
$sql = "SELECT im.*, c.category_name, sc.subcategory, b.Brand 
        FROM item_master im 
        LEFT JOIN category c ON im.category_id = c.category_id 
        LEFT JOIN subcategory sc ON im.subcategory_id = sc.scategoryid 
        LEFT JOIN brands b ON im.BrandID = b.BrandID 
        WHERE 1=1";

// If a search term is provided, add filtering conditions (using LIKE for case-insensitive search)
if (!empty($search)) {
    // Use prepared statements to prevent SQL injection
    $searchTerm = "%$search%";
    $sql .= " AND (im.item_name LIKE ? OR c.category_name LIKE ? OR sc.subcategory LIKE ? OR b.Brand LIKE ?)";
    $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
    $types = "ssss";
} else {
    $params = [];
    $types = "";
}

// Count total products matching the criteria (for pagination)
$countSql = "SELECT COUNT(*) as total FROM item_master im 
             LEFT JOIN category c ON im.category_id = c.category_id 
             LEFT JOIN subcategory sc ON im.subcategory_id = sc.scategoryid 
             LEFT JOIN brands b ON im.BrandID = b.BrandID 
             WHERE 1=1";
if (!empty($search)) {
    $countSql .= " AND (im.item_name LIKE ? OR c.category_name LIKE ? OR sc.subcategory LIKE ? OR b.Brand LIKE ?)";
}

$stmtCount = $conn->prepare($countSql);
if (!empty($search)) {
    $stmtCount->bind_param($types, ...$params);
}
$stmtCount->execute();
$resultCount = $stmtCount->get_result();
$totalRow = $resultCount->fetch_assoc();
$totalRecords = $totalRow['total'];
$totalPages = ceil($totalRecords / $pageSize);
$stmtCount->close();

// Append ORDER BY and LIMIT clause to the main query
$sql .= " ORDER BY im.id DESC LIMIT ? OFFSET ?";
$params[] = $pageSize;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    http_response_code(500);
    echo json_encode(["error" => $conn->error]);
    exit;
}
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$products = [];
while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}
$stmt->close();
$conn->close();

echo json_encode([
    "products"   => $products,
    "totalPages" => $totalPages,
    "currentPage"=> $page,
    "totalRecords" => $totalRecords
]);
?>
