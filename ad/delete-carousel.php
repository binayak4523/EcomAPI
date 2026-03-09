<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

include("db.php");

// Get input data - handle both JSON and form data
$data = [];
if ($_SERVER['CONTENT_TYPE'] === 'application/json') {
    $data = json_decode(file_get_contents("php://input"), true);
} else {
    $data['id'] = isset($_POST['id']) ? $_POST['id'] : null;
}

if (!isset($data['id']) || empty($data['id'])) {
    http_response_code(400);
    echo json_encode(["error" => "Carousel ID is required"]);
    exit();
}
$carousel_id = $data['id'];

$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed: " . $conn->connect_error]);
    exit();
}

try {
    // First, get the image path to potentially delete the file
    $select_sql = "SELECT image_path FROM carousel WHERE id = ?";
    $select_stmt = $conn->prepare($select_sql);
    $select_stmt->bind_param("i", $carousel_id);
    $select_stmt->execute();
    $select_stmt->bind_result($image_path);
    $select_stmt->fetch();
    $select_stmt->close();
    
    // Delete from database
    $sql = "DELETE FROM carousel WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $carousel_id);
    
    if ($stmt->execute()) {
        // Try to delete the image file if it exists
        if ($image_path) {
            // Debug log the image path from database
            error_log("Image path from database: " . $image_path);
            
            // Remove any 'uploads/carousel/' prefix if it exists in the database path
            $filename = basename($image_path);
            $file_path = dirname(__DIR__) . '/uploads/carousel/' . $filename;
            
            // Debug log the constructed file path
            error_log("Attempting to delete file: " . $file_path);
            
            if (file_exists($file_path)) {
                if (unlink($file_path)) {
                    error_log("Successfully deleted file: " . $file_path);
                } else {
                    error_log("Failed to delete file: " . $file_path . ". Error: " . error_get_last()['message']);
                }
            } else {
                error_log("File does not exist: " . $file_path);
            }
        }
        
        echo json_encode([
            "success" => true,
            "message" => "Carousel deleted successfully"
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