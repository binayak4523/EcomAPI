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

// Check if ID is provided
if (!isset($_POST['id'])) {
    http_response_code(400);
    echo json_encode(["error" => "Carousel ID is required"]);
    exit();
}

$carousel_id = $_POST['id'];
$title = isset($_POST['title']) ? $_POST['title'] : '';
$description = isset($_POST['description']) ? $_POST['description'] : '';
$button_text = isset($_POST['button_text']) ? $_POST['button_text'] : 'Shop Now';
$button_link = isset($_POST['link']) ? $_POST['link'] : '/shop';

// Check if image was uploaded
$image_path = null;
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = 'uploads/carousel/';
    
    // Create directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $file_name = time() . '_' . basename($_FILES['image']['name']);
    $target_path = $upload_dir . $file_name;
    
    // Move uploaded file
    if (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
        $image_path = 'http://' . $_SERVER['HTTP_HOST'] . '/allishan-react/HomKart/api/' . $target_path;
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Failed to upload image"]);
        exit();
    }
}

try {
    if ($image_path) {
        // Update with new image
        $sql = "UPDATE carousel SET 
                title = ?, 
                message = ?, 
                button_text = ?, 
                button_link = ?, 
                image_path = ? 
                WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssi", $title, $description, $button_text, $button_link, $image_path, $carousel_id);
    } else {
        // Update without changing image
        $sql = "UPDATE carousel SET 
                title = ?, 
                message = ?, 
                button_text = ?, 
                button_link = ? 
                WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssi", $title, $description, $button_text, $button_link, $carousel_id);
    }
    
    if ($stmt->execute()) {
        echo json_encode([
            "success" => true,
            "message" => "Carousel updated successfully"
        ]);
    } else {
        throw new Exception($stmt->error);
    }
    
    $stmt->close();
} catch (Exception $e) {
    error_log("Query failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "Query failed: " . $e->getMessage()]);
}

$conn->close();