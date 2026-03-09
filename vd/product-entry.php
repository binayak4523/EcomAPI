<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

include 'db.php';
$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit;
}

// Retrieve all fields from the request
$item_name       = isset($_POST['item_name']) ? $_POST['item_name'] : '';
$category_id     = isset($_POST['category_id']) ? $_POST['category_id'] : '';
$subcategory_id  = isset($_POST['subcategory_id']) ? $_POST['subcategory_id'] : '';
$brand_id        = isset($_POST['BrandID']) ? $_POST['BrandID'] : '';
$vendor_id       = isset($_POST['VendorID']) ? $_POST['VendorID'] : '';
$mrp             = isset($_POST['mrp']) ? $_POST['mrp'] : 0;
$tax_p           = isset($_POST['tax_p']) ? $_POST['tax_p'] : 0;
$saleprice       = isset($_POST['saleprice']) ? $_POST['saleprice'] : 0;
$hsn             = isset($_POST['hsn']) ? $_POST['hsn'] : '';
$size_dimension  = isset($_POST['size_dimension']) ? $_POST['size_dimension'] : null;
$weight          = isset($_POST['weight']) ? $_POST['weight'] : null;
$color           = isset($_POST['color']) ? $_POST['color'] : null;
$packingtype     = isset($_POST['packingtype']) ? $_POST['packingtype'] : null;
$packingtime     = isset($_POST['packingtime']) ? $_POST['packingtime'] : null;
$description     = isset($_POST['description']) ? $_POST['description'] : '';
$status          = 'Active';

// Basic validation
if (empty($item_name) || empty($category_id) || empty($subcategory_id) || 
    empty($brand_id) || empty($vendor_id) || empty($saleprice)) {
    http_response_code(400);
    echo json_encode([
        "error" => "Required fields are missing.",
        "received" => [
            "item_name" => $item_name,
            "category_id" => $category_id,
            "subcategory_id" => $subcategory_id,
            "brand_id" => $brand_id,
            "vendor_id" => $vendor_id,
            "saleprice" => $saleprice
        ]
    ]);
    exit;
}

$conn->begin_transaction();

try {
    $stmt = $conn->prepare("INSERT INTO item_master (item_name, category_id, subcategory_id, BrandID, VendorID, mrp, tax_p, saleprice, hsn, size_dimension, weight, color, packingtype, packingtime, description, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    if (!$stmt) {
        throw new Exception("Database error: " . $conn->error);
    }

    $stmt->bind_param("siiiiiddssssssss", 
        $item_name,
        $category_id,
        $subcategory_id,
        $brand_id,
        $vendor_id,
        $mrp,
        $tax_p,
        $saleprice,
        $hsn,
        $size_dimension,
        $weight,
        $color,
        $packingtype,
        $packingtime,
        $description,
        $status
    );

    if (!$stmt->execute()) {
        throw new Exception("Failed to insert product: " . $stmt->error);
    }

    $product_id = $stmt->insert_id;
    $stmt->close();

    // Handle image uploads
    $uploadDir = "../productimage/";
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $uploadedFiles = [];

    if (isset($_FILES['images'])) {
        $files = $_FILES['images'];
        for ($i = 0; $i < count($files['name']); $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                $tmpName = $files['tmp_name'][$i];
                $originalName = basename($files['name'][$i]);
                $uniqueName = $product_id . "_" . time() . "_" . $originalName;
                $targetFile = $uploadDir . $uniqueName;
                
                if (move_uploaded_file($tmpName, $targetFile)) {
                    $isDefault = ($i === 0) ? 'y' : 'n';
                    
                    $stmtImg = $conn->prepare("INSERT INTO product_images (product_id, path_url, default_img) VALUES (?, ?, ?)");
                    if ($stmtImg) {
                        $stmtImg->bind_param("iss", $product_id, $uniqueName, $isDefault);
                        $stmtImg->execute();
                        $stmtImg->close();
                        $uploadedFiles[] = [
                            'name' => $uniqueName,
                            'isDefault' => $isDefault === 'y'
                        ];
                    }
                }
            }
        }
    }

    $conn->commit();

    echo json_encode([
        "success" => true,
        "id" => $product_id,
        "uploadedFiles" => $uploadedFiles
    ]);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}

$conn->close();