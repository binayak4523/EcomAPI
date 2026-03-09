<?php
session_start();

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=UTF-8');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include DB credentials
include("db.php");

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed"]);
    exit();
}

// Read JSON from request
$data = json_decode(file_get_contents("php://input"), true);
$username = isset($data['username']) ? $data['username'] : '';
$password = isset($data['password']) ? $data['password'] : '';

if (empty($username) || empty($password)) {
    echo json_encode(["success" => false, "message" => "Username or password not provided"]);
    exit();
}

// Query to find user with password hash
$sql = "SELECT idstaffs, username, password, designation, store_id, vendor_id FROM staffs WHERE username = ?";
// Using PDO
$stmt = $pdo->prepare($sql);
$stmt->execute([$username]);
$userData = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$userData) {
    echo json_encode(["success" => false, "message" => "User not found"]);
} elseif (!password_verify($password, $userData['password'])) {
    echo json_encode(["success" => false, "message" => "Password incorrect"]);
} elseif ($userData['designation'] !== 'Global Administrator') {
    echo json_encode(["success" => false, "message" => "Only Global Administrator can login. Your role: " . $userData['designation']]);
} else {
    // All checks passed - user is authenticated
    $_SESSION['userid'] = $userData['idstaffs'];
    $_SESSION['username'] = $userData['username'];
    $_SESSION['role'] = $userData['designation'];
    $_SESSION['store_id'] = $userData['store_id'] ?? null;
    $_SESSION['vendor_id'] = $userData['vendor_id'] ?? null;

    echo json_encode([
        "success" => true,
        "role" => $userData['designation'],
        "userid" => $userData['idstaffs'],
        "username" => $userData['username'],
        "store_id" => $userData['store_id'] ?? null,
        "vendor_id" => $userData['vendor_id'] ?? null
    ]);
}