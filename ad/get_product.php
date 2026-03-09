<?php
// get_product.php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
include 'db.php';
// Create connection
$conn = new mysqli($host, $user, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid product ID"]);
    exit;
}

// Retrieve product info
$sql = "SELECT im.*, c.category_name, sc.subcategory, b.Brand
        FROM item_master im
        LEFT JOIN category c ON im.category_id = c.category_id
        LEFT JOIN subcategory sc ON im.subcategory_id = sc.scategoryid
        LEFT JOIN brands b ON im.BrandID = b.BrandID
        WHERE im.id = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(["error" => "Database error: " . $conn->error]);
    exit;
}
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();
$stmt->close();

if (!$product) {
    http_response_code(404);
    echo json_encode(["error" => "Product not found"]);
    exit;
}

// Retrieve all images for this product
$imgSql = "SELECT path_url, default_img, img_seq FROM product_images WHERE product_id = ? ORDER BY img_seq ASC";
$stmtImg = $conn->prepare($imgSql);
$stmtImg->bind_param("i", $id);
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
?>
