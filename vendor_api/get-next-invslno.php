<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require 'db.php';

// Create connection
$conn = new mysqli($host, $user, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Connection failed: ' . $conn->connect_error]);
    exit();
}

try {
    // Get vendorID and companyId from query parameters
    $vendorID = isset($_GET['vendorID']) ? intval($_GET['vendorID']) : 0;
    $companyId = isset($_GET['companyId']) ? intval($_GET['companyId']) : 0;
    
    if (!$vendorID) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'vendorID required']);
        exit();
    }
    
    // If companyId not provided, use vendorID as companyId
    if (!$companyId) {
        $companyId = $vendorID;
    }
    
    // Query to get the maximum Slno from purchasehead table
    // Try with CompanyId first, then fallback to VendorID only
    $query = "SELECT MAX(InvNo) as maxSlno FROM invoicehead 
              WHERE VendorID = ? AND (CompanyId = ? OR CompanyId IS NULL OR CompanyId = 0)";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("ii", $vendorID, $companyId);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    $maxSlno = $row['maxSlno'] ? intval($row['maxSlno']) : 0;
    $nextSlno = $maxSlno + 1;
    
    $stmt->close();
    
    // Get count of records for debugging
    $count_query = "SELECT COUNT(*) as total FROM invoicehead WHERE VendorID = ?";
    $count_stmt = $conn->prepare($count_query);
    $count_stmt->bind_param("i", $vendorID);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $count_row = $count_result->fetch_assoc();
    $totalRecords = $count_row['total'];
    $count_stmt->close();
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'nextSlno' => $nextSlno,
        'currentMaxSlno' => $maxSlno,
        'vendorID' => $vendorID,
        'companyId' => $companyId,
        'totalRecords' => $totalRecords
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
