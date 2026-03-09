<?php
// Add Shop endpoint
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
    http_response_code(500);
    die(json_encode(["success" => false, "message" => "Connection failed: " . $conn->connect_error]));
}

error_reporting(E_ALL);
ini_set('display_errors', 0);

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method not allowed"]);
    exit;
}

// Retrieve POST fields
$vendor_id = isset($_POST['vendor_id']) ? intval($_POST['vendor_id']) : 0;
$store_name = isset($_POST['store_name']) ? trim($_POST['store_name']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$saddress = isset($_POST['saddress']) ? trim($_POST['saddress']) : '';
$store_manager = isset($_POST['store_manager']) ? trim($_POST['store_manager']) : '';
$phoneno = isset($_POST['phoneno']) ? trim($_POST['phoneno']) : '';
$pin_code = isset($_POST['pin_code']) ? trim($_POST['pin_code']) : '';
$gstno = isset($_POST['gstno']) ? trim($_POST['gstno']) : '';
$pan = isset($_POST['pan']) ? trim($_POST['pan']) : '';

// Basic validation
if ($vendor_id <= 0) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Invalid or missing vendor ID"]);
    exit;
}

if (empty($store_name) || empty($email) || empty($saddress) || empty($phoneno) || empty($pin_code) || empty($gstno)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Required fields are missing"]);
    exit;
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Invalid email format"]);
    exit;
}

try {
    // Verify vendor exists
    $vendor_check = $conn->prepare("SELECT id FROM vendor WHERE id = ?");
    if (!$vendor_check) {
        throw new Exception("Prepare vendor check failed: " . $conn->error);
    }
    $vendor_check->bind_param("i", $vendor_id);
    $vendor_check->execute();
    $vendor_result = $vendor_check->get_result();
    
    if ($vendor_result->num_rows === 0) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Selected vendor does not exist"]);
        exit;
    }
    $vendor_check->close();

    // Handle logo upload if provided
    $logo_path = '';
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . "/../uploads/logos/";
        
        // Create directory if it doesn't exist
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileName = basename($_FILES['logo']['name']);
        $fileExt = pathinfo($fileName, PATHINFO_EXTENSION);
        $uniqueFileName = "shop_" . time() . "_" . rand(1000, 9999) . "." . $fileExt;
        $targetFile = $uploadDir . $uniqueFileName;

        // Validate file type
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($_FILES['logo']['type'], $allowedTypes)) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Invalid file type. Only JPG, PNG, GIF, and WebP are allowed"]);
            exit;
        }

        // Check file size (max 5MB)
        if ($_FILES['logo']['size'] > 5 * 1024 * 1024) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "File size exceeds 5MB limit"]);
            exit;
        }

        if (move_uploaded_file($_FILES['logo']['tmp_name'], $targetFile)) {
            // Store relative path
            $logo_path = "uploads/logos/" . $uniqueFileName;
        } else {
            http_response_code(500);
            echo json_encode(["success" => false, "message" => "Failed to upload logo"]);
            exit;
        }
    }

    // Insert store record
    // Columns: ID, VendorID, Store_Name, GSTNO, email, saddress, phoneno, pin_code, store_manager, logo, PAN
    $sql_insert = "INSERT INTO store (VendorID, Store_Name, GSTNO, email, saddress, phoneno, pin_code, store_manager, logo, PAN) 
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql_insert);
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Prepare statement failed: " . $conn->error]);
        exit;
    }

    // Bind parameters: i=int, s=string
    // vendor_id(i), store_name(s), gstno(s), email(s), saddress(s), phoneno(s), pin_code(s), store_manager(s), logo_path(s), pan(s)
    $stmt->bind_param("isssssssss", $vendor_id, $store_name, $gstno, $email, $saddress, $phoneno, $pin_code, $store_manager, $logo_path, $pan);
    
    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Execute failed: " . $stmt->error]);
        $stmt->close();
        exit;
    }

    $store_id = $stmt->insert_id;
    $stmt->close();

    http_response_code(201);
    echo json_encode([
        "success" => true,
        "message" => "Shop added successfully",
        "store_id" => $store_id,
        "vendor_id" => $vendor_id
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
} finally {
    $conn->close();
}
?>
