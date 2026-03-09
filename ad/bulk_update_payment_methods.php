<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: POST, PUT, OPTIONS');
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

    $data = json_decode(file_get_contents("php://input"), true);
    if (!$data || !is_array($data)) {
        throw new Exception("Invalid JSON data or not an array");
    }

    // Start transaction
    $conn->begin_transaction();

    // Clear table
    if (!$conn->query("TRUNCATE TABLE payment_methods")) {
        throw new Exception("Failed to clear table: " . $conn->error);
    }

    // Insert updated data
    $sql = "INSERT INTO payment_methods 
            (id, provider_name, display_name, api_key, api_secret, api_base_url, api_gateway_url, merchant_id, additional_field1, additional_field2, webhook_url, is_test_mode, is_active, priority) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    foreach ($data as $p) {
        $provider_name = $p["provider_name"] ?? $p["providerName"] ?? "";
        $display_name  = $p["display_name"] ?? $p["displayName"] ?? "";
        $api_key       = $p["api_key"] ?? $p["apiKey"] ?? "";
        $api_secret    = $p["api_secret"] ?? $p["apiSecret"] ?? "";
        $api_base_url  = $p["api_base_url"] ?? $p["apiBaseUrl"] ?? "";
        $api_gateway_url = $p["api_gateway_url"] ?? $p["apiGatewayUrl"] ?? "";
        $merchant_id   = $p["merchant_id"] ?? $p["merchantId"] ?? "";
        $additional_field1 = $p["additional_field1"] ?? $p["additionalField1"] ?? "";
        $additional_field2 = $p["additional_field2"] ?? $p["additionalField2"] ?? "";
        $webhook_url   = $p["webhook_url"] ?? $p["webhookUrl"] ?? "";
        $is_test_mode  = isset($p["is_test_mode"]) ? $p["is_test_mode"] : (isset($p["isTestMode"]) ? $p["isTestMode"] : 1);
        $is_active     = isset($p["is_active"]) ? $p["is_active"] : (isset($p["isActive"]) ? $p["isActive"] : 1);
        $priority      = $p["priority"] ?? 0;
        $id            = $p["id"] ?? 0;

        // Bind: 1 int + 10 strings + 3 ints = "isssssssssiii"
        $stmt->bind_param(
            "isssssssssiii",
            $id,
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
            $priority
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Insert failed: " . $stmt->error);
        }
    }

    // Commit transaction
    $conn->commit();

    echo json_encode(["success" => true, "message" => "Payment methods updated successfully"]);

    $stmt->close();

} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollback();
    }
    http_response_code(500);
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>