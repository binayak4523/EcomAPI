<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include("db.php");

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['request_id']) || !isset($data['staff_id'])) {
        throw new Exception('Missing required fields');
    }

    $stmt = $pdo->prepare("UPDATE service_requests SET assigned_staff = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$data['staff_id'], $data['request_id']]);

    echo json_encode(["success" => true, "message" => "Staff assigned successfully"]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}