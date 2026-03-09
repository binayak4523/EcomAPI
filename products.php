<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=UTF-8');

include("db.php");

// Add error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed: " . $conn->connect_error]);
    exit();
}

$sql = "SELECT 
    i.id,
    i.item_name as name,
    i.saleprice as price,
    i.description,
    i.status,
    i.ongoing_offer,
    i.discount_percentage,
    pi.path_url as image_path
    FROM item_master i
    LEFT JOIN product_images pi ON i.id = pi.product_id AND pi.default_img = 'y'
    WHERE i.status = 'active' 
    ORDER BY i.id DESC";

try {
    $result = $conn->query($sql);
    
    if ($result) {
        $products = array();
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                // Clean UTF-8 encoding - fix malformed characters
                $row = array_map(function($value) {
                    if (is_string($value)) {
                        return mb_convert_encoding($value, 'UTF-8', 'UTF-8');
                    }
                    return $value;
                }, $row);
                
                // Calculate discounted price if applicable
                $original_price = floatval($row['price']);
                $discount_percentage = floatval($row['discount_percentage']) ?: 0;
                $discounted_price = $original_price - ($original_price * ($discount_percentage / 100));
                
                // Format each product
                $products[] = array(
                    'id' => $row['id'],
                    'name' => $row['name'],
                    'price' => $row['ongoing_offer'] === 'yes' ? round($discounted_price, 2) : $original_price,
                    'original_price' => $original_price,
                    'discount_percentage' => $discount_percentage,
                    'ongoing_offer' => $row['ongoing_offer'],
                    'description' => $row['description'],
                    'image_path' => $row['image_path'] ?? 'http://localhost/allishan-react/HomKart/api/ad/uploads/placeholder.jpg'
                );
            }
        }
        echo json_encode($products, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } else {
        throw new Exception($conn->error);
    }
} catch (Exception $e) {
    error_log("Query failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "Query failed: " . $e->getMessage()]);
}

$conn->close();
?>