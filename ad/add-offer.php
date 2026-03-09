<?php 
header('Content-Type: application/json'); 
header("Access-Control-Allow-Origin: *"); 
header("Access-Control-Allow-Methods: POST, OPTIONS"); 
header("Access-Control-Allow-Headers: Content-Type, Authorization"); 

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { 
    http_response_code(200); 
    exit; 
} 

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { 
    http_response_code(405); 
    echo json_encode(["error" => "Method not allowed"]); 
    exit; 
} 

include 'db.php'; 
$conn = new mysqli($host, $user, $password, $dbname); 

if ($conn->connect_error) { 
    die(json_encode(["error" => "Connection failed: " . $conn->connect_error])); 
} 

$productId = ""; 
$startDate = ""; 
$endDate = ""; 
$discountPercentage = ""; 

$data = json_decode(file_get_contents("php://input"), true); 

if (!$data) { 
    http_response_code(400); 
    echo json_encode(["error" => "Invalid data format"]); 
    exit; 
} 

$productId = isset($data['product_id']) ? $data['product_id'] : ""; 
$startDate = isset($data['start_date']) ? $data['start_date'] : ""; 
$endDate = isset($data['end_date']) ? $data['end_date'] : ""; 
$discountPercentage = isset($data['discount_percentage']) ? $data['discount_percentage'] : ""; 

// Validation
if (empty($productId)) { 
    http_response_code(400); 
    echo json_encode(["error" => "Product ID is required"]); 
    exit; 
} 

if (empty($startDate)) { 
    http_response_code(400); 
    echo json_encode(["error" => "Start date is required"]); 
    exit; 
} 

if (empty($endDate)) { 
    http_response_code(400); 
    echo json_encode(["error" => "End date is required"]); 
    exit; 
} 

if (empty($discountPercentage)) { 
    http_response_code(400); 
    echo json_encode(["error" => "Discount percentage is required"]); 
    exit; 
} 

// Validate discount percentage is a valid number between 0 and 100
if (!is_numeric($discountPercentage) || $discountPercentage < 0 || $discountPercentage > 100) { 
    http_response_code(400); 
    echo json_encode(["error" => "Discount percentage must be a number between 0 and 100"]); 
    exit; 
} 

// Validate that end date is after start date
if (strtotime($endDate) <= strtotime($startDate)) { 
    http_response_code(400); 
    echo json_encode(["error" => "End date must be after start date"]); 
    exit; 
} 

// Check if product exists
$stmt = $conn->prepare("SELECT id FROM item_master WHERE id = ?"); 
if (!$stmt) { 
    http_response_code(500); 
    echo json_encode(["error" => "Database error: " . $conn->error]); 
    $conn->close(); 
    exit; 
} 

$stmt->bind_param("i", $productId); 
$stmt->execute(); 
$result = $stmt->get_result(); 

if ($result->num_rows === 0) { 
    http_response_code(400); 
    echo json_encode(["error" => "Product not found"]); 
    $stmt->close(); 
    $conn->close(); 
    exit; 
} 
$stmt->close(); 

// Check if offer has expired
$currentDate = date('Y-m-d H:i:s'); 
if (strtotime($endDate) < strtotime($currentDate)) { 
    http_response_code(400); 
    echo json_encode(["error" => "Offer ended", "message" => "The offer end date is in the past. Please provide a future date."]); 
    $conn->close(); 
    exit; 
} 

$conn->begin_transaction(); 

try { 
    // Insert into offers table with product_id, start_date, end_date
    $stmt = $conn->prepare("INSERT INTO offers (product_id, start_date, end_date) VALUES (?, ?, ?)"); 
    if (!$stmt) { 
        throw new Exception("Database error: " . $conn->error); 
    } 

    $stmt->bind_param("iss", $productId, $startDate, $endDate); 

    if (!$stmt->execute()) { 
        throw new Exception("Failed to add offer: " . $stmt->error); 
    } 

    $stmt->close(); 

    // Update item_master to set ongoing_offer = 'yes' and discount_percentage for this product
    $stmt = $conn->prepare("UPDATE item_master SET ongoing_offer = 'yes', discount_percentage = ? WHERE id = ?"); 
    if (!$stmt) { 
        throw new Exception("Database error: " . $conn->error); 
    } 

    $stmt->bind_param("di", $discountPercentage, $productId); 
    if (!$stmt->execute()) { 
        throw new Exception("Failed to update product offer status: " . $stmt->error); 
    } 
    $stmt->close(); 

    $conn->commit(); 
    echo json_encode(["success" => true, "message" => "Product offer added successfully"]); 

} catch (Exception $e) { 
    $conn->rollback(); 
    http_response_code(500); 
    echo json_encode(["error" => $e->getMessage()]); 
} finally { 
    $conn->close(); 
} 