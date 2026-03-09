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

    // Set SQL mode to avoid strict mode issues
    $conn->query("SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION'");

    // Get vendorID from query parameter
    $vendorID = isset($_GET['vendorID']) ? (int)$_GET['vendorID'] : 0;
    
    if ($vendorID === 0) {
        throw new Exception("VendorID is required");
    }

    $sql = "SELECT ID, VendorID, Store_Name, GSTNO, email, saddress, phoneno, pin_code, logo, PAN 
            FROM store 
            WHERE VendorID = ?";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $vendorID);
    $stmt->execute();
    $result = $stmt->get_result();

    $stores = array();
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $stores[] = $row;
        }
    }

    echo json_encode($stores);

} catch(Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

if (isset($stmt)) $stmt->close();
if (isset($conn)) $conn->close();
?>
