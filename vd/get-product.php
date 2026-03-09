<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

include 'db.php';
$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get product ID from query parameter
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($product_id <= 0) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid product ID"]);
    exit;
}

// Query to get product details
$sql = "SELECT 
            im.id as item_id,
            im.item_name,
            im.category_id,
            im.subcategory_id,
            im.BrandID,
            im.mrp,
            im.tax_p,
            im.saleprice,
            im.description,
            c.category_name,
            sc.subcategory as subcategory_name,
            b.Brand as brand_name
        FROM 
            item_master im
            LEFT JOIN category c ON im.category_id = c.category_id
            LEFT JOIN subcategory sc ON im.subcategory_id = sc.scategoryid
            LEFT JOIN brands b ON im.BrandID = b.BrandID
        WHERE 
            im.id = ?";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    http_response_code(500);
    echo json_encode(["error" => "Database error: " . $conn->error]);
    exit;
}

$stmt->bind_param("i", $product_id);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(["error" => "Query execution failed: " . $stmt->error]);
    exit;
}

$result = $stmt->get_result();
$product = $result->fetch_assoc();
$stmt->close();

if (!$product) {
    http_response_code(404);
    echo json_encode(["error" => "Product not found"]);
    exit;
}

// Retrieve all images for this product
$imgSql = "SELECT path_url, default_img FROM product_images WHERE product_id = ?";
$stmtImg = $conn->prepare($imgSql);
$stmtImg->bind_param("i", $product_id);
$stmtImg->execute();
$imgResult = $stmtImg->get_result();
$images = [];

while ($row = $imgResult->fetch_assoc()) {
    $images[] = $row;
}
$stmtImg->close();

$product['images'] = $images;

echo json_encode($product);
$conn->close();