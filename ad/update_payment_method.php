<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: PUT, OPTIONS');
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

    // Get ID from query parameter
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if ($id <= 0) {
        throw new Exception("Invalid payment method ID");
    }

    $data = json_decode(file_get_contents("php://input"), true);
    if (!$data) {
        throw new Exception("Invalid JSON data");
    }

    // Map React field names to PHP variables
    $provider_name = trim($data["provider_name"] ?? $data["providerName"] ?? "");
    $display_name  = trim($data["display_name"] ?? $data["displayName"] ?? "");
    $api_key       = trim($data["api_key"] ?? $data["apiKey"] ?? "");
    $api_secret    = trim($data["api_secret"] ?? $data["apiSecret"] ?? "");
    $api_base_url  = trim($data["api_base_url"] ?? $data["apiBaseUrl"] ?? "");
    $api_gateway_url = trim($data["api_gateway_url"] ?? $data["apiGatewayUrl"] ?? "");
    $merchant_id   = trim($data["merchant_id"] ?? $data["merchantId"] ?? "");
    $additional_field1 = trim($data["additional_field1"] ?? $data["additionalField1"] ?? "");
    $additional_field2 = trim($data["additional_field2"] ?? $data["additionalField2"] ?? "");
    $webhook_url   = trim($data["webhook_url"] ?? $data["webhookUrl"] ?? "");
    $is_test_mode  = isset($data["is_test_mode"]) ? intval($data["is_test_mode"]) : (isset($data["isTestMode"]) ? intval($data["isTestMode"]) : 1);
    $is_active     = isset($data["is_active"]) ? intval($data["is_active"]) : (isset($data["isActive"]) ? intval($data["isActive"]) : 1);
    $priority      = intval($data["priority"] ?? 0);

    // Validate required fields
    if (!$provider_name) {
        throw new Exception("Provider name is required");
    }

    if (!$display_name) {
        throw new Exception("Display name is required");
    }

    if (!$api_key) {
        throw new Exception("API Key is required");
    }

    if (!$api_secret) {
        throw new Exception("API Secret is required");
    }

    // Update SQL with all fields
    $sql = "UPDATE payment_methods 
            SET provider_name = ?, 
                display_name = ?, 
                api_key = ?, 
                api_secret = ?, 
                api_base_url = ?,
                api_gateway_url = ?,
                merchant_id = ?, 
                additional_field1 = ?, 
                additional_field2 = ?, 
                webhook_url = ?, 
                is_test_mode = ?, 
                is_active = ?, 
                priority = ?,
                updated_at = NOW()
            WHERE id = ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    // Bind parameters: 10 strings + 4 integers = "ssssssssssiiii"
    if (!$stmt->bind_param("ssssssssssiiii", 
        $provider_name, 
        $display_name, 
        $api_key, 
        $api_secret,
        $api_base_url,
        $api_gateway_url,
        $merchant_id, 
        $additional_field1, 
        $additional_field2, 
        $webhook_url, 
        $is_test_mode, 
        $is_active, 
        $priority,
        $id)) {
        throw new Exception("Bind failed: " . $stmt->error);
    }

    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    if ($stmt->affected_rows === 0) {
        throw new Exception("Payment method not found or no changes made");
    }

    echo json_encode([
        "success" => true,
        "message" => "Payment method updated successfully"
    ]);

    $stmt->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>