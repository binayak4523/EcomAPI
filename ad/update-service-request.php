<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

include("db.php");

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['id'])) {
        throw new Exception('ID is required');
    }

    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = "UPDATE service_requests SET ";
    $params = [];
    $updates = [];

    if (isset($data['status'])) {
        $updates[] = "status = :status";
        $params[':status'] = $data['status'];
    }

    if (isset($data['assigned_staff'])) {
        $updates[] = "assigned_staff = :assigned_staff";
        $params[':assigned_staff'] = $data['assigned_staff'];
    }

    if (empty($updates)) {
        throw new Exception('No fields to update');
    }

    $sql .= implode(", ", $updates);
    $sql .= ", updated_at = CURRENT_TIMESTAMP WHERE id = :id";
    $params[':id'] = $data['id'];

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}