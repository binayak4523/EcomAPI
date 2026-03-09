<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

include("db.php");

// Add error logging
error_reporting(E_ALL);
ini_set('display_errors', 1);

$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed: " . $conn->connect_error]);
    exit();
}

// Check if image was uploaded
if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(["error" => "Image upload failed"]);
    exit();
}

// Get form data
$title = isset($_POST['title']) ? $_POST['title'] : '';
$description = isset($_POST['description']) ? $_POST['description'] : '';
$button_text = isset($_POST['button_text']) ? $_POST['button_text'] : 'Shop Now';
$button_link = isset($_POST['link']) ? $_POST['link'] : '/shop';

// Process image upload
$upload_dir = dirname(__DIR__) . '/uploads/carousel/';  // Changed to use parent directory
$relative_upload_dir = 'uploads/carousel/';    // Keep relative path for database storage

// Create directory if it doesn't exist
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

$file_name = time() . '_' . basename($_FILES['image']['name']);
$target_path = $upload_dir . $file_name;
$relative_path = $relative_upload_dir . $file_name;

// Move uploaded file
if (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
    // Get the highest display_order
    $order_sql = "SELECT MAX(display_order) as max_order FROM carousel";
    $order_result = $conn->query($order_sql);
    $display_order = 0;
    
    if ($order_result && $row = $order_result->fetch_assoc()) {
        $display_order = $row['max_order'] + 1;
    }
    
    // Insert into database with relative path
    $image_path = $relative_path;  // Store only the relative path
    
    $sql = "INSERT INTO carousel (image_path, title, message, button_text, button_link, display_order, active) 
            VALUES (?, ?, ?, ?, ?, ?, 1)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssi", $image_path, $title, $description, $button_text, $button_link, $display_order);
    
    try {
        if ($stmt->execute()) {
            $carousel_id = $conn->insert_id;
            echo json_encode([
                "success" => true,
                "message" => "Carousel image added successfully",
                "id" => $carousel_id
            ]);
        } else {
            throw new Exception($stmt->error);
        }
    } catch (Exception $e) {
        error_log("Query failed: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(["error" => "Query failed: " . $e->getMessage()]);
    }
    
    $stmt->close();
} else {
    http_response_code(500);
    echo json_encode(["error" => "Failed to upload image"]);
}

$conn->close();