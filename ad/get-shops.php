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

    $sql = "SELECT s.*, v.Name as vendor_name 
            FROM store s 
            LEFT JOIN vendor v ON s.VendorID = v.ID";
            
    $result = $conn->query($sql);

    $shops = array();
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $shops[] = $row;
        }
    }

    echo json_encode($shops);

} catch(Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

if (isset($conn)) {
    $conn->close();
}