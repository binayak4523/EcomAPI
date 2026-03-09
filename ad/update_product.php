<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle preflight OPTIONS request for CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include 'db.php';
$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    die(json_encode(["error" => "Connection failed: " . $conn->connect_error]));
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

// For GET requests, return lookup data
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Fetch categories
    $categories = [];
    $categoryQuery = "SELECT category_id, category_name, slug, description, img FROM category";
    $result = $conn->query($categoryQuery);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }
    }

    // Fetch subcategories
    $subcategories = [];
    $subcategoryQuery = "SELECT scategoryid, categoryid, subcategory, img FROM subcategory";
    $result = $conn->query($subcategoryQuery);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $subcategories[] = $row;
        }
    }

    // Fetch brands
    $brands = [];
    $brandQuery = "SELECT BrandID, Brand FROM brands";
    $result = $conn->query($brandQuery);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $brands[] = $row;
        }
    }

    // Fetch vendors
    $vendors = [];
    $vendorQuery = "SELECT ID, Name, ContactNo, Email, pwd, NoOfStores, Vaddress, Bank_AC_No, IFSC, vstatus, msg FROM vendor";
    $result = $conn->query($vendorQuery);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $vendors[] = $row;
        }
    }

    echo json_encode([
        "categories" => $categories,
        "subcategories" => $subcategories,
        "brands" => $brands,
        "vendors" => $vendors
    ]);
    exit;
}

// Handle POST request for product updates
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit;
}

// Get product ID and validate
$product_id = isset($_POST['id']) ? $_POST['id'] : '';
if (empty($product_id)) {
    http_response_code(400);
    echo json_encode(["error" => "Product ID is required"]);
    exit;
}

// Update these lines to match the frontend field names
$product_name    = isset($_POST['product_name']) ? $_POST['product_name'] : '';
$category_id     = isset($_POST['category_id']) ? $_POST['category_id'] : '';
$subcategory_id  = isset($_POST['subcategory_id']) ? $_POST['subcategory_id'] : '';
$brand_id        = isset($_POST['brand_id']) ? $_POST['brand_id'] : '';
$mrp             = isset($_POST['mrp']) ? $_POST['mrp'] : 0;
$tax             = isset($_POST['tax']) ? $_POST['tax'] : 0;
$saleprice       = isset($_POST['price']) ? $_POST['price'] : 0;
$hsn             = isset($_POST['hsn']) ? $_POST['hsn'] : '';
$size_dimension  = isset($_POST['size_dimension']) ? $_POST['size_dimension'] : '';
$weight          = isset($_POST['weight']) ? $_POST['weight'] : '';
$color           = isset($_POST['color']) ? $_POST['color'] : '';
$packingtype     = isset($_POST['packingtype']) ? $_POST['packingtype'] : '';
$packingtime     = isset($_POST['packingtime']) ? $_POST['packingtime'] : '';
$description     = isset($_POST['description']) ? $_POST['description'] : '';
$status          = isset($_POST['status']) ? $_POST['status'] : 'Active';

// Basic validation
if (empty($product_name) || empty($category_id) || empty($subcategory_id) || empty($brand_id) || empty($saleprice)) {
    http_response_code(400);
    echo json_encode(["error" => "Required fields are missing."]);
    exit;
}

// Update the item_master table
$stmt = $conn->prepare("UPDATE item_master SET
    item_name = ?,
    category_id = ?,
    subcategory_id = ?,
    BrandID = ?,
    VendorID = ?,
    mrp = ?,
    tax_p = ?,
    saleprice = ?,
    hsn = ?,
    size_dimension = ?,
    weight = ?,
    color = ?,
    packingtype = ?,
    packingtime = ?,
    description = ?,
    status = ?
    WHERE id = ?");

if (!$stmt) {
    http_response_code(500);
    echo json_encode(["error" => "Database error: " . $conn->error]);
    exit;
}

$stmt->bind_param("siiidddsssssssssi",
    $product_name,
    $category_id,
    $subcategory_id,
    $brand_id,
    $vendor_id,
    $mrp,
    $tax,
    $saleprice,
    $hsn,
    $size_dimension,
    $weight,
    $color,
    $packingtype,
    $packingtime,
    $description,
    $status,
    $product_id
);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(["error" => "Failed to update product: " . $stmt->error]);
    exit;
}

$stmt->close();

// Handle product details/specifications updates
if (isset($_POST['details']) && is_array($_POST['details'])) {
    foreach ($_POST['details'] as $detailId => $detailValue) {
        // Only update if there's a value
        if (!empty($detailValue)) {
            // Fetch detail name from product_details table
            $detailStmt = $conn->prepare("SELECT name FROM product_details WHERE id = ?");
            if ($detailStmt) {
                $detailStmt->bind_param("i", $detailId);
                $detailStmt->execute();
                $detailResult = $detailStmt->get_result();
                
                if ($detailResult->num_rows > 0) {
                    $detailRow = $detailResult->fetch_assoc();
                    $detailName = $detailRow['name'];
                    
                    // Check if this detail already exists for the product
                    $checkStmt = $conn->prepare("SELECT id FROM details WHERE productid = ? AND name = ?");
                    if ($checkStmt) {
                        $checkStmt->bind_param("is", $product_id, $detailName);
                        $checkStmt->execute();
                        $checkResult = $checkStmt->get_result();
                        
                        if ($checkResult->num_rows > 0) {
                            // Update existing detail
                            $updateDetailStmt = $conn->prepare("UPDATE details SET detail = ? WHERE productid = ? AND name = ?");
                            if ($updateDetailStmt) {
                                $updateDetailStmt->bind_param("sis", $detailValue, $product_id, $detailName);
                                if (!$updateDetailStmt->execute()) {
                                    error_log("Failed to update detail: " . $updateDetailStmt->error);
                                }
                                $updateDetailStmt->close();
                            }
                        } else {
                            // Insert new detail
                            $insertDetailStmt = $conn->prepare("INSERT INTO details (productid, name, detail) VALUES (?, ?, ?)");
                            if ($insertDetailStmt) {
                                $insertDetailStmt->bind_param("iss", $product_id, $detailName, $detailValue);
                                if (!$insertDetailStmt->execute()) {
                                    error_log("Failed to insert detail: " . $insertDetailStmt->error);
                                }
                                $insertDetailStmt->close();
                            }
                        }
                        $checkStmt->close();
                    }
                }
                $detailStmt->close();
            }
        }
    }
}

// Handle new image uploads if any
$uploadedFiles = [];
if (isset($_FILES['new_images'])) {
    $files = $_FILES['new_images'];
    $uploadDir = "../productimage/";

    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    for ($i = 0; $i < count($files['name']); $i++) {
        if ($files['error'][$i] === UPLOAD_ERR_OK) {
            $tmpName = $files['tmp_name'][$i];
            $originalName = basename($files['name'][$i]);
            $uniqueName = $product_id . "_" . time() . "_" . $originalName;
            $targetFile = $uploadDir . $uniqueName;

            if (move_uploaded_file($tmpName, $targetFile)) {
                // Set as non-default for new images
                $isDefault = 'n';
                
                $stmtImg = $conn->prepare("INSERT INTO product_images (product_id, path_url, default_img) VALUES (?, ?, ?)");
                if ($stmtImg) {
                    $stmtImg->bind_param("iss", $product_id, $uniqueName, $isDefault);
                    if ($stmtImg->execute()) {
                        $uploadedFiles[] = [
                            'name' => $uniqueName,
                            'isDefault' => false
                        ];
                    }
                    $stmtImg->close();
                }
            }
        }
    }
}

echo json_encode([
    "success" => true,
    "message" => "Product updated successfully",
    "id" => $product_id,
    "uploadedFiles" => $uploadedFiles
]);

$conn->close();