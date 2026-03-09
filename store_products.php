<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=UTF-8');

include("db.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);

$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed: " . $conn->connect_error]);
    exit();
}

$vendor_id = isset($_GET['vendor_id']) ? intval($_GET['vendor_id']) : null;

$sql = "SELECT 
    i.id,
    i.item_name as name,
    i.saleprice as price,
    i.description,
    i.status,
    i.VendorID,
    i.size_dimension,
    i.weight,
    i.color,
    i.packingtype,
    pi.path_url as image_path
    FROM item_master i
    LEFT JOIN product_images pi ON i.id = pi.product_id 
    WHERE i.status = 'active'
    AND i.VendorID = ?
    AND pi.default_img = 'y' 
    ORDER BY i.id DESC";

try {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $vendor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $products = array();
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $products[] = array(
                'id' => $row['id'],
                'name' => $row['name'],
                'price' => $row['price'],
                'description' => $row['description'],
                'size_dimension' => $row['size_dimension'],
                'weight' => $row['weight'],
                'color' => $row['color'],
                'packing_type' => $row['packingtype'],
                'image_path' => $row['image_path'] ?? 'placeholder.jpg'
            );
        }
    }
    echo json_encode($products);
    
} catch (Exception $e) {
    error_log("Query failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "Query failed: " . $e->getMessage()]);
}

$stmt->close();
$conn->close();