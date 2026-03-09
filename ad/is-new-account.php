<?php
session_start();

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=UTF-8');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include DB credentials
include("db.php");

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed"]);
    exit();
}

// Check if this is a new account by checking if any stores exist
try {
    // Check vendor table
    $sqlVendor = "SELECT COUNT(*) as vendor_count FROM vendor";
    $stmtVendor = $pdo->prepare($sqlVendor);
    $stmtVendor->execute();
    $resultVendor = $stmtVendor->fetch(PDO::FETCH_ASSOC);
    $vendorCount = $resultVendor['vendor_count'];

    // Check store table
    $sqlStore = "SELECT COUNT(*) as store_count FROM store";
    $stmtStore = $pdo->prepare($sqlStore);
    $stmtStore->execute();
    $resultStore = $stmtStore->fetch(PDO::FETCH_ASSOC);
    $storeCount = $resultStore['store_count'];

    // If both tables are empty, it's a brand new account
    $isNewAccount = ($vendorCount == 0 && $storeCount == 0);

    echo json_encode([
        "success" => true,
        "isNewAccount" => $isNewAccount,
        "storeCount" => $storeCount,
        "vendorCount" => $vendorCount
    ]);
} catch (Exception $e) {
    // If tables don't exist or error occurs, it's definitely a new account
    echo json_encode([
        "success" => true,
        "isNewAccount" => true,
        "message" => "Database check - new account"
    ]);
}
?>
