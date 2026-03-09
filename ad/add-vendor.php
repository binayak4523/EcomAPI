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

    $data = json_decode(file_get_contents("php://input"));

    $sql = "INSERT INTO vendor (Name, ContactNo, Email, NoOfStores, Vaddress, Bank_AC_No, IFSC) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssss", 
        $data->Name,
        $data->ContactNo,
        $data->Email,
        $data->NoOfStores,
        $data->Vaddress,
        $data->Bank_AC_No,
        $data->IFSC
    );

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Vendor added successfully']);
    } else {
        throw new Exception("Error adding vendor");
    }

} catch(Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

if (isset($conn)) {
    $conn->close();
}