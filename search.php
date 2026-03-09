<?php
// search.php

// Set headers for CORS and JSON output
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include("db.php");

// Add error logging
error_reporting(E_ALL);
ini_set('display_errors', 1);

$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed: " . $conn->connect_error]);
    exit();
}

// Retrieve query parameters
$q = isset($_GET['q']) ? $_GET['q'] : '';  // search keyword
$category_id = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;
$category = isset($_GET['category']) ? $_GET['category'] : '';
$subcategory_id = isset($_GET['subcategory_id']) ? intval($_GET['subcategory_id']) : 0;
$brand = isset($_GET['brand']) ? $_GET['brand'] : '';
$minPrice = isset($_GET['minPrice']) && $_GET['minPrice'] !== '' ? floatval($_GET['minPrice']) : 0;
$maxPrice = isset($_GET['maxPrice']) && $_GET['maxPrice'] !== '' ? floatval($_GET['maxPrice']) : 0;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$resultsPerPage = 10; // Number of results per page

$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;

    // Log the search term
    if (!empty($q)) {
        $logSQL = "INSERT INTO search_logs (search_term, user_id) VALUES (?, ?)";
        $stmt = $conn->prepare($logSQL);
        $stmt->bind_param("si", $q, $userId);
        $stmt->execute();
        $stmt->close();
    }

// Start with a base query that always evaluates to true
$sql = "SELECT SQL_CALC_FOUND_ROWS im.id, im.item_name, im.description, im.saleprice as price, 
        (SELECT pi.path_url FROM product_images pi WHERE pi.product_id = im.id AND pi.default_img = 'y' LIMIT 1) as image_path,
        c.category_name as category
        FROM item_master im 
        LEFT JOIN category c ON im.category_id = c.category_id
        WHERE 1=1";

// If a search keyword is provided, filter by name or description
if (!empty($q)) {
    $qEsc = $conn->real_escape_string($q);
    $sql .= " AND (im.item_name LIKE '%$qEsc%' OR im.description LIKE '%$qEsc%')";
}

// Append category filter if provided
if ($category_id > 0) {
    $sql .= " AND im.category_id = $category_id";
}
if (!empty($category) && $category !== 'All') {
    $categoryEsc = $conn->real_escape_string($category);
    $sql .= " AND c.category_name = '$categoryEsc'";
}
// Append subcategory filter if provided
if ($subcategory_id > 0) {
    $sql .= " AND im.subcategory_id = $subcategory_id";
}

// Append brand filter if provided
if (!empty($brand) && $brand !== 'All') {
    $brandEsc = $conn->real_escape_string($brand);
    $sql .= " AND im.BrandID = '$brandEsc'";
}

// Append minimum price filter if provided
if ($minPrice > 0) {
    $sql .= " AND im.saleprice >= $minPrice";
}

// Append maximum price filter if provided
if ($maxPrice > 0) {
    $sql .= " AND im.saleprice <= $maxPrice";
}

// Calculate the offset for pagination
$offset = ($page - 1) * $resultsPerPage;
$sql .= " LIMIT $offset, $resultsPerPage";

// Execute the query
$result = $conn->query($sql);
if (!$result) {
    http_response_code(500);
    echo json_encode(["error" => "Query error: " . $conn->error, "sql" => $sql]);
    exit();
}

// Fetch the products into an array
$products = [];
while ($row = $result->fetch_assoc()) {
    // Format the image path
    $imagePath = $row["image_path"];
    if ($imagePath && strpos($imagePath, 'http') !== 0) {
        $imagePath = $imagePath;
    }
    
    $products[] = [
        "id" => $row["id"],
        "name" => $row["item_name"],
        "price" => floatval($row["price"]),
        "image_path" => $imagePath,
        "description" => $row["description"],
        "category" => $row["category"]
    ];
}

// Retrieve the total number of matching rows (ignoring the LIMIT)
$totalRowsResult = $conn->query("SELECT FOUND_ROWS() as total");
$totalRows = 0;
if ($totalRowsResult) {
    $row = $totalRowsResult->fetch_assoc();
    $totalRows = intval($row["total"]);
}

// Return JSON response with products and total number of results
echo json_encode([
    "products" => $products,
    "totalResults" => $totalRows
]);

$conn->close();