<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: *");
header("Content-Type: application/json");

include 'db.php';

try {
    $conn = new mysqli($host, $user, $password, $dbname);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    $vendorId = isset($_GET['vendorId']) ? $_GET['vendorId'] : null;

    if (!$vendorId) {
        throw new Exception("Vendor ID is required");
    }

    $sql = "SELECT i.*, c.category_name, s.subcategory, b.Brand as brand_name 
            FROM item_master i 
            LEFT JOIN category c ON i.category_id = c.category_id 
            LEFT JOIN subcategory s ON i.subcategory_id = s.scategoryid 
            LEFT JOIN brands b ON i.BrandID = b.BrandID 
            WHERE i.VendorID = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $vendorId);
    $stmt->execute();
    $result = $stmt->get_result();

    $items = array();
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }

    echo json_encode($items);

} catch(Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

if (isset($conn)) {
    $conn->close();
}