<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
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

$sql = "SELECT DISTINCT
    i.id,
    i.item_name as name,
    i.saleprice as price,
    i.description,
    i.status,
    i.discount_percentage,
    pi.path_url as image_path,
    o.start_date,
    o.end_date
    FROM item_master i
    LEFT JOIN product_images pi ON i.id = pi.product_id AND pi.default_img = 'y'
    LEFT JOIN offers o ON i.id = o.product_id
    WHERE i.ongoing_offer = 'yes'
    AND i.status = 'active'
    AND (o.start_date IS NULL OR CURRENT_DATE BETWEEN o.start_date AND o.end_date)
    ORDER BY i.id DESC";

try {
    $result = $conn->query($sql);
    
    if ($result) {
        $deals = array();
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                // Use discount from item_master table
                $original_price = floatval($row['price']);
                $discount_percentage = floatval($row['discount_percentage']) ?: 0;
                $discounted_price = $original_price - ($original_price * ($discount_percentage / 100));
                
                $deals[] = array(
                    'id' => $row['id'],
                    'name' => $row['name'],
                    'price' => round($discounted_price, 2),
                    'original_price' => $original_price,
                    'discount_percentage' => $discount_percentage,
                    'description' => $row['description'],
                    'start_date' => $row['start_date'],
                    'end_date' => $row['end_date'],
                    'image_path' => $row['image_path']
                );
            }
        }
        echo json_encode(['deals' => $deals]);
    } else {
        throw new Exception($conn->error);
    }
} catch (Exception $e) {
    error_log("Query failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "Query failed: " . $e->getMessage()]);
}

$conn->close();