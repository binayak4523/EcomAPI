<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle preflight OPTIONS request for CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include 'db.php';
// Create connection
$conn = new mysqli($host, $user, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die(json_encode(["error" => "Connection failed: " . $conn->connect_error]));
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit;
}

// Retrieve product fields from the request
$product_name    = isset($_POST['product_name']) ? $_POST['product_name'] : '';
$category_id     = isset($_POST['category_id']) ? $_POST['category_id'] : '';
$subcategory_id  = isset($_POST['subcategory_id']) ? $_POST['subcategory_id'] : '';
$brand_id        = isset($_POST['brand_id']) ? $_POST['brand_id'] : '';
$mrp             = isset($_POST['mrp']) ? $_POST['mrp'] : 0;
$tax             = isset($_POST['tax']) ? $_POST['tax'] : 0;
$saleprice       = isset($_POST['saleprice']) ? $_POST['saleprice'] : 0;
$hsn             = isset($_POST['hsn']) ? $_POST['hsn'] : '';
$size_dimension  = isset($_POST['size_dimension']) ? $_POST['size_dimension'] : '';
$weight          = isset($_POST['weight']) ? $_POST['weight'] : '';
$color           = isset($_POST['color']) ? $_POST['color'] : '';
$packingtype     = isset($_POST['packingtype']) ? $_POST['packingtype'] : '';
$packingtime     = isset($_POST['packingtime']) ? $_POST['packingtime'] : '';
$description     = isset($_POST['description']) ? $_POST['description'] : '';
$storeID         = isset($_POST['storeID']) ? (int)$_POST['storeID'] : 0;
$vendorID        = isset($_POST['vendorID']) ? (int)$_POST['vendorID'] : 0;

// Basic validation
if (empty($product_name) || empty($category_id) || empty($subcategory_id) || empty($brand_id) || empty($saleprice)) {
    http_response_code(400);
    echo json_encode(["error" => "Required fields are missing."]);
    exit;
}

if ($storeID === 0 || $vendorID === 0) {
    http_response_code(400);
    echo json_encode(["error" => "Store ID and Vendor ID are required."]);
    exit;
}

// Prepare and execute the insert query for item_master
$stmt = $conn->prepare("INSERT INTO item_master (item_name, category_id, subcategory_id, BrandID, mrp, tax_p, saleprice, hsn, size_dimension, weight, color, packingtype, packingtime, description, store_id, VendorID, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active')");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(["error" => "Database error: " . $conn->error]);
    exit;
}

// Updated bind_param string to include storeID and vendorID (16 parameters total)
$stmt->bind_param("siiidd" . "ssssssss" . "ii", $product_name, $category_id, $subcategory_id, $brand_id, $mrp, $tax, $saleprice, $hsn, $size_dimension, $weight, $color, $packingtype, $packingtime, $description, $storeID, $vendorID);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(["error" => "Failed to insert product: " . $stmt->error]);
    exit;
}

$product_id = $stmt->insert_id;
$stmt->close();

// Handle product details
if (isset($_POST['details'])) {
    $details = $_POST['details'];
    foreach ($details as $detailId => $detailValue) {
        if (!empty($detailValue)) {
            // Get the detail name and required status from product_details table
            $detailStmt = $conn->prepare("SELECT name, required FROM product_details WHERE id = ?");
            $detailStmt->bind_param("i", $detailId);
            $detailStmt->execute();
            $detailResult = $detailStmt->get_result();
            
            if ($detailResult->num_rows > 0) {
                $detailRow = $detailResult->fetch_assoc();
                $detailName = $detailRow['name'];
                $isRequired = $detailRow['required'];
                
                // Insert into details table
                $insertDetailStmt = $conn->prepare("INSERT INTO details (productid, name, detail, required) VALUES (?, ?, ?, ?)");
                if ($insertDetailStmt) {
                    $insertDetailStmt->bind_param("issi", $product_id, $detailName, $detailValue, $isRequired);
                    if (!$insertDetailStmt->execute()) {
                        error_log("Failed to insert product detail: " . $insertDetailStmt->error);
                    }
                    $insertDetailStmt->close();
                }
            }
            $detailStmt->close();
        }
    }
}

// Handling multiple image uploads
$uploadDir = __DIR__ . "/../productimage/";  // Use absolute path
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$uploadedFiles = [];

// Check if files are uploaded
if (isset($_FILES['images'])) {
    $files = $_FILES['images'];
    // Add debug logging
    error_log("Files received: " . print_r($files, true));
    
    // If isDefault[] is sent, use it; otherwise, first image is default
    $isDefaultArr = isset($_POST['isDefault']) ? $_POST['isDefault'] : [];
    for ($i = 0; $i < count($files['name']); $i++) {
        if ($files['error'][$i] === UPLOAD_ERR_OK) {
            $tmpName = $files['tmp_name'][$i];
            $originalName = basename($files['name'][$i]);
            $uniqueName = $product_id . "_" . time() . "_" . $originalName;
            $targetFile = $uploadDir . $uniqueName;
            
            // Add debug logging
            error_log("Attempting to move file from {$tmpName} to {$targetFile}");
            
            if (move_uploaded_file($tmpName, $targetFile)) {
                // Set default_img to 'y' for the first image or according to isDefault[]
                $isDefault = (isset($isDefaultArr[$i]) && $isDefaultArr[$i] === 'y') || $i === 0 ? 'y' : 'n';

                $stmtImg = $conn->prepare("INSERT INTO product_images (product_id, path_url, default_img) VALUES (?, ?, ?)");
                if ($stmtImg) {
                    $stmtImg->bind_param("iss", $product_id, $uniqueName, $isDefault);
                    if ($stmtImg->execute()) {
                        $uploadedFiles[] = [
                            'name' => $uniqueName,
                            'isDefault' => $isDefault === 'y'
                        ];
                    } else {
                        error_log("Failed to insert image record: " . $stmtImg->error);
                    }
                    $stmtImg->close();
                } else {
                    error_log("Failed to prepare statement for image insertion: " . $conn->error);
                }
            } else {
                error_log("Failed to move uploaded file from {$tmpName} to {$targetFile}. PHP Error: " . error_get_last()['message']);
            }
        } else {
            error_log("File upload error code for index {$i}: " . $files['error'][$i]);
        }
    }
} else {
    error_log("No files were uploaded - _FILES['images'] is not set");
}

echo json_encode([
    "id" => $product_id,
    "uploadedFiles" => $uploadedFiles
]);
$conn->close();