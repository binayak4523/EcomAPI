<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../db.php';

try {
    $conn = new mysqli($host, $user, $password, $dbname);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    // Select all fields including new ones
    $sql = "SELECT 
                id,
                provider_name,
                display_name,
                api_key,
                api_secret,
                api_base_url,
                api_gateway_url,
                merchant_id,
                additional_field1,
                additional_field2,
                webhook_url,
                is_test_mode,
                is_active,
                priority,
                created_at,
                updated_at
            FROM payment_methods 
            ORDER BY priority DESC, created_at DESC";
    
    $result = $conn->query($sql);

    if (!$result) {
        throw new Exception("Query failed: " . $conn->error);
    }

    $methods = [];
    while ($row = $result->fetch_assoc()) {
        $methods[] = $row;
    }

    echo json_encode(["success" => true, "data" => $methods]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>