<?php
// Setup Account creation endpoint
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
    die(json_encode(["success" => false, "message" => "Connection failed: " . $conn->connect_error]));
}

// Set SQL mode to avoid strict mode issues
$conn->query("SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION'");

error_reporting(E_ALL);
ini_set('display_errors', 0);

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method not allowed"]);
    exit;
}

// Retrieve business fields
$business_name = isset($_POST['businessName']) ? trim($_POST['businessName']) : '';
$business_phone = isset($_POST['businessPhone']) ? trim($_POST['businessPhone']) : '';
$business_address = isset($_POST['businessAddress']) ? trim($_POST['businessAddress']) : '';
$business_email = isset($_POST['businessEmail']) ? trim($_POST['businessEmail']) : '';
$bank_ac = isset($_POST['bankAccount']) ? trim($_POST['bankAccount']) : '';
$ifsc_code = isset($_POST['ifscCode']) ? trim($_POST['ifscCode']) : '';

// Retrieve store fields
$username = isset($_POST['username']) ? trim($_POST['username']) : '';
$store_name = isset($_POST['storeName']) ? trim($_POST['storeName']) : '';
$store_phone = isset($_POST['storePhone']) ? trim($_POST['storePhone']) : '';
$store_address = isset($_POST['storeAddress']) ? trim($_POST['storeAddress']) : '';
$pin_code = isset($_POST['pinCode']) ? trim($_POST['pinCode']) : '';
$store_email = isset($_POST['storeEmail']) ? trim($_POST['storeEmail']) : '';
$gst_number = isset($_POST['gstNumber']) ? trim($_POST['gstNumber']) : '';
$pan_number = isset($_POST['panNumber']) ? trim($_POST['panNumber']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';

// Retrieve settings fields
$site_email = isset($_POST['siteEmail']) ? trim($_POST['siteEmail']) : '';
$phone_number = isset($_POST['phoneNumber']) ? trim($_POST['phoneNumber']) : '';
$facebook_link = isset($_POST['facebookLink']) ? trim($_POST['facebookLink']) : '';
$instagram_link = isset($_POST['instagramLink']) ? trim($_POST['instagramLink']) : '';
$twitter_link = isset($_POST['twitterLink']) ? trim($_POST['twitterLink']) : '';

// Basic validation
if (empty($business_name) || empty($business_phone) || empty($business_email) || 
    empty($username) || empty($store_name) || empty($store_phone) || empty($store_address) || 
    empty($pin_code) || empty($store_email) || empty($gst_number) || empty($password)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Required fields are missing."]);
    exit;
}

try {
    // Start transaction
    $conn->begin_transaction();

    // Use the username provided by the user instead of generating one
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert vendor record (ID, Name, ContactNo, Email, pwd, NoOfStores, Vaddress, Bank_AC_No, IFSC, vstatus, msg)
    $sql_vendor = "INSERT INTO vendor (Name, ContactNo, Email, pwd, NoOfStores, Vaddress, Bank_AC_No, IFSC, vstatus) 
                   VALUES (?, ?, ?, ?, 1, ?, ?, ?, 'Active')";
    $stmt_vendor = $conn->prepare($sql_vendor);
    if (!$stmt_vendor) {
        throw new Exception("Vendor prepare failed: " . $conn->error);
    }
    $stmt_vendor->bind_param("sssssss", $business_name, $business_phone, $business_email, $hashed_password, $business_address, $bank_ac, $ifsc_code);
    if (!$stmt_vendor->execute()) {
        throw new Exception("Vendor insert failed: " . $stmt_vendor->error);
    }
    $vendor_id = $stmt_vendor->insert_id;
    $stmt_vendor->close();

    // Insert store record (ID, VendorID, Store_Name, GSTNO, email, saddress, phoneno, pin_code, store_manager, logo, PAN)
    $sql_store = "INSERT INTO store (VendorID, Store_Name, GSTNO, email, saddress, phoneno, pin_code, PAN) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt_store = $conn->prepare($sql_store);
    if (!$stmt_store) {
        throw new Exception("Store prepare failed: " . $conn->error);
    }
    $stmt_store->bind_param("isssssss", $vendor_id, $store_name, $gst_number, $store_email, $store_address, $store_phone, $pin_code, $pan_number);
    if (!$stmt_store->execute()) {
        throw new Exception("Store insert failed: " . $stmt_store->error);
    }
    $store_id = $stmt_store->insert_id;
    $stmt_store->close();

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
        $uniqueFileName = "store_" . $store_id . "_" . time() . "." . $fileExt;
        $targetFile = $uploadDir . $uniqueFileName;

        if (move_uploaded_file($_FILES['logo']['tmp_name'], $targetFile)) {
            // Store relative path like save-update-settings.php does
            $logo_path = "uploads/logos/" . $uniqueFileName;
        }
    }

    // Update store with logo if uploaded
    if (!empty($logo_path)) {
        $sql_update = "UPDATE store SET logo = ? WHERE ID = ?";
        $stmt_update = $conn->prepare($sql_update);
        if ($stmt_update) {
            $stmt_update->bind_param("si", $logo_path, $store_id);
            $stmt_update->execute();
            $stmt_update->close();
        }
    }

    // Insert staff member (idstaffs, username, password, designation, email, phone, address, store_id, vendor_id, status, last_login, created_at, updated_at)
    $designation = "Global Administrator";
    $staff_address = $store_address;
    $status = "Active";
    $sql_staff = "INSERT INTO staffs (username, password, designation, email, phone, address, store_id, vendor_id, status, created_at, updated_at) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
    $stmt_staff = $conn->prepare($sql_staff);
    if (!$stmt_staff) {
        throw new Exception("Staff prepare failed: " . $conn->error);
    }
    $stmt_staff->bind_param("ssssssiis", $username, $hashed_password, $designation, $business_email, $business_phone, $staff_address, $store_id, $vendor_id, $status);
    if (!$stmt_staff->execute()) {
        throw new Exception("Staff insert failed: " . $stmt_staff->error);
    }
    $stmt_staff->close();

    // Insert settings record (id, siteName, siteEmail, phoneNumber, address, facebookLink, instagramLink, twitterLink, enableGuestCheckout, enableReviews, maintenanceMode, logo, invoice_sorce)
    $invoice_source = '';
    $sql_settings = "INSERT INTO settings (siteName, siteEmail, phoneNumber, address, facebookLink, instagramLink, twitterLink, enableGuestCheckout, enableReviews, maintenanceMode, logo, invoice_sorce) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, 0, 0, 0, ?, ?)";
    $stmt_settings = $conn->prepare($sql_settings);
    if (!$stmt_settings) {
        throw new Exception("Settings prepare failed: " . $conn->error);
    }
    $stmt_settings->bind_param("sssssss" . "ss", $store_name, $site_email, $phone_number, $store_address, $facebook_link, $instagram_link, $twitter_link, $logo_path, $invoice_source);
    if (!$stmt_settings->execute()) {
        throw new Exception("Settings insert failed: " . $stmt_settings->error);
    }
    $stmt_settings->close();

    // Commit transaction
    $conn->commit();

    echo json_encode([
        "success" => true,
        "message" => "Account setup completed successfully",
        "store_id" => $store_id,
        "vendor_id" => $vendor_id,
        "username" => $username
    ]);

} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Setup failed: " . $e->getMessage()]);
} finally {
    $conn->close();
}
?>

