<?php 
header('Content-Type: application/json'); 
header("Access-Control-Allow-Origin: *"); 
header("Access-Control-Allow-Methods: GET, OPTIONS"); 
header("Access-Control-Allow-Headers: Content-Type, Authorization"); 

if ($_SERVER['REQUEST_METHOD'] !== 'GET') { 
    http_response_code(405); 
    echo json_encode(["error" => "Method not allowed"]); 
    exit; 
} 

include 'db.php'; 
$conn = new mysqli($host, $user, $password, $dbname); 

if ($conn->connect_error) { 
    die(json_encode(["error" => "Connection failed: " . $conn->connect_error])); 
} 

$currentDate = date('Y-m-d H:i:s'); 

// Query to get offers: Fetch all offers from offers table with product details
$query = ""
    . "SELECT o.*, im.id as product_id, im.item_name as product_name, im.discount_percentage, im.ongoing_offer, pi.path_url as image_path "
    . "FROM offers o "
    . "INNER JOIN item_master im ON o.product_id = im.id "
    . "LEFT JOIN product_images pi ON im.id = pi.product_id AND pi.default_img = 'y' "
    . "ORDER BY o.start_date DESC";

$stmt = $conn->prepare($query);
if (!$stmt) { 
    http_response_code(500); 
    echo json_encode(["error" => "Database error: " . $conn->error]); 
    exit; 
} 

$stmt->execute(); 
$result = $stmt->get_result(); 
$offers = []; 

while ($row = $result->fetch_assoc()) { 
    // Format dates for display 
    $row['start_date'] = date('Y-m-d', strtotime($row['start_date'])); 
    $row['end_date'] = date('Y-m-d', strtotime($row['end_date'])); 
    
    // Check if offer is active by comparing start_date and end_date from offers table
    $offerStartTime = strtotime($row['start_date']);
    $offerEndTime = strtotime($row['end_date']);
    $currentTime = strtotime($currentDate);
    
    if ($currentTime < $offerStartTime) {
        $row['status'] = 'upcoming';
        $row['message'] = 'Offer starts on ' . $row['start_date'];
    } elseif ($currentTime > $offerEndTime) {
        $row['status'] = 'expired';
        $row['message'] = 'Offer ended on ' . $row['end_date'];
    } else {
        $row['status'] = 'active';
        $row['message'] = 'Offer active until ' . $row['end_date'];
    }
    
    // Add product image URL
    if (!empty($row['image_path'])) { 
        $row['image_path'] = "http://localhost/api/productimage/" . $row['image_path']; 
    }
    
    $offers[] = $row; 
} 

echo json_encode($offers); 

$stmt->close(); 
$conn->close();