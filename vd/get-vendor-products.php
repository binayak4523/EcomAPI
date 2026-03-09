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

// Get vendor ID from query parameter
$vendor_id = isset($_GET['vendor_id']) ? (int)$_GET['vendor_id'] : 0;

if ($vendor_id <= 0) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid vendor ID"]);
    exit;
}

// Query to get all products for the vendor with related information
$sql = "SELECT 
            im.id as item_id,
            im.item_name,
            im.category_id,
            im.subcategory_id,
            im.BrandID,
            im.mrp,
            im.tax_p,
            im.saleprice,
            im.status,
            c.category_name,
            sc.subcategory as subcategory_name,
            b.Brand as brand_name,
            pi.path_url as image_path
        FROM 
            item_master im
            LEFT JOIN category c ON im.category_id = c.category_id
            LEFT JOIN subcategory sc ON im.subcategory_id = sc.scategoryid
            LEFT JOIN brands b ON im.BrandID = b.BrandID
            LEFT JOIN (
                SELECT product_id, path_url
                FROM product_images
                WHERE default_img = 'y'
            ) pi ON im.id = pi.product_id
        WHERE 
            im.VendorID = ?
        ORDER BY 
            im.id DESC";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    http_response_code(500);
    echo json_encode(["error" => "Database error: " . $conn->error]);
    exit;
}

$stmt->bind_param("i", $vendor_id);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(["error" => "Query execution failed: " . $stmt->error]);
    exit;
}

$result = $stmt->get_result();
$products = [];

while ($row = $result->fetch_assoc()) {
    // Format the data
    $products[] = [
        'item_id' => $row['item_id'],
        'item_name' => $row['item_name'],
        'category_name' => $row['category_name'],
        'subcategory_name' => $row['subcategory_name'],
        'brand_name' => $row['brand_name'],
        'mrp' => $row['mrp'],
        'tax_p' => $row['tax_p'],
        'saleprice' => $row['saleprice'],
        'status' => $row['status'],
        'image_path' => $row['image_path'] ?? 'default-product-image.jpg' // Provide a default image if none exists
    ];
}

$stmt->close();
$conn->close();

echo json_encode($products);