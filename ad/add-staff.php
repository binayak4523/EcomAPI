<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include("db.php");

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed: " . $e->getMessage()]);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['username']) || !isset($data['password'])) {
    http_response_code(400);
    echo json_encode(["error" => "Username and password are required"]);
    exit();
}

// Check if username already exists
$checkSql = "SELECT COUNT(*) FROM staffs WHERE username = ?";
try {
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->execute([$data['username']]);
    if ($checkStmt->fetchColumn() > 0) {
        http_response_code(400);
        echo json_encode(["error" => "Username already exists"]);
        exit();
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Error checking username: " . $e->getMessage()]);
    exit();
}

$sql = "INSERT INTO staffs (username, password, designation) VALUES (?, ?, ?)";
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $data['username'],
        $data['password'],
        $data['designation'] ?? 'Staff'
    ]);
    
    http_response_code(201);
    echo json_encode([
        "success" => true,
        "message" => "Staff member created successfully",
        "id" => $pdo->lastInsertId()
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Insert failed: " . $e->getMessage()]);
}