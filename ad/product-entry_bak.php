<?php
include 'db.php';
// Create connection
$conn = new mysqli($host, $user, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
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

// Basic validation
if (empty($product_name) || empty($category_id) || empty($subcategory_id) || empty($brand_id) || empty($saleprice)) {
    http_response_code(400);
    echo json_encode(["error" => "Required fields are missing."]);
    exit;
}

// Prepare and execute the insert query for item_master
$stmt = $conn->prepare("INSERT INTO item_master (item_name, category_id, subcategory_id, BrandID, mrp, tax_p, saleprice, hsn, size_dimension, weight, color, packingtype, packingtime, description, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active')");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(["error" => "Database error: " . $conn->error]);
    exit;
}

$stmt->bind_param("siiiddssdssdss", $product_name, $category_id, $subcategory_id, $brand_id, $mrp, $tax, $saleprice, $hsn, $size_dimension, $weight, $color, $packingtype, $packingtime, $description);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(["error" => "Failed to insert product: " . $stmt->error]);
    exit;
}

$product_id = $stmt->insert_id;
$stmt->close();

// Handling multiple image uploads
$uploadDir = "../productimage/";  // Adjust this path if needed
$uploadedFiles = [];

if (isset($_FILES['images'])) {
    $files = $_FILES['images'];
    for ($i = 0; $i < count($files['name']); $i++) {
        if ($files['error'][$i] === UPLOAD_ERR_OK) {
            $tmpName = $files['tmp_name'][$i];
            $originalName = basename($files['name'][$i]);
            // Create a unique file name
            $uniqueName = time() . "_" . $originalName;
            $targetFile = $uploadDir . $uniqueName;
            if (move_uploaded_file($tmpName, $targetFile)) {
                // Insert file info into product_images table
                $stmtImg = $conn->prepare("INSERT INTO product_images (product_id, image_path) VALUES (?, ?)");
                if ($stmtImg) {
                    $stmtImg->bind_param("is", $product_id, $uniqueName);
                    $stmtImg->execute();
                    $stmtImg->close();
                    $uploadedFiles[] = $uniqueName;
                }
            }
        }
    }
}

echo json_encode(["id" => $product_id, "uploadedFiles" => $uploadedFiles]);
$conn->close();
?>
