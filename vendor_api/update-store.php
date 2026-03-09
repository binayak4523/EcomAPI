<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Content-Type: application/json");

// Handle CORS preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

include 'db.php';

try {
    $conn = new mysqli($host, $user, $password, $dbname);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    $data = json_decode(file_get_contents("php://input"), true);
    
    // Validate required fields
    if (!isset($data['id']) || empty($data['id'])) {
        throw new Exception("Store ID is required");
    }
    if (!isset($data['Store_Name']) || empty($data['Store_Name'])) {
        throw new Exception("Store Name is required");
    }
    if (!isset($data['email']) || empty($data['email'])) {
        throw new Exception("Email is required");
    }
    if (!isset($data['saddress']) || empty($data['saddress'])) {
        throw new Exception("Address is required");
    }

    $ID = (int)$data['id'];
    $Store_Name = $data['Store_Name'];
    $email = $data['email'];
    $saddress = $data['saddress'];
    $GSTNO = isset($data['GSTNO']) ? $data['GSTNO'] : '';
    $phoneno = isset($data['phoneno']) ? $data['phoneno'] : '';
    $pin_code = isset($data['pin_code']) ? $data['pin_code'] : '';
    $store_manager = isset($data['store_manager']) ? $data['store_manager'] : '';
    $PAN = isset($data['PAN']) ? $data['PAN'] : '';

    // Prepare update statement
    $sql = "UPDATE store SET Store_Name = ?, GSTNO = ?, email = ?, saddress = ?, phoneno = ?, pin_code = ?, store_manager = ?, PAN = ? WHERE ID = ?";
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Database error: " . $conn->error);
    }

    $stmt->bind_param("ssssssssi", $Store_Name, $GSTNO, $email, $saddress, $phoneno, $pin_code, $store_manager, $PAN, $ID);

    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Store updated successfully'
        ]);
    } else {
        throw new Exception("Error updating store: " . $stmt->error);
    }

    $stmt->close();

} catch(Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

if (isset($conn)) {
    $conn->close();
}
?>
