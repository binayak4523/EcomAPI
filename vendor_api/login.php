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
$email = isset($data['email']) ? $data['email'] : '';
$password = isset($data['password']) ? $data['password'] : '';

if (empty($email) || empty($password)) {
    echo json_encode(["success" => false, "message" => "Email or password not provided"]);
    exit();
}

// Query to find vendor
$sql = "SELECT ID, Name, Email, pwd, ContactNo, vstatus FROM vendor WHERE Email = ?";
// Using PDO
$stmt = $pdo->prepare($sql);
$stmt->execute([$email]);
$userData = $stmt->fetch(PDO::FETCH_ASSOC);

if ($userData) {
    // Check password (assuming pwd is stored as plain text or hash)
    if ($password === $userData['pwd']) {
        $_SESSION['userid'] = $userData['ID'];
        $_SESSION['username'] = $userData['Name'];
        $_SESSION['email'] = $userData['Email'];

        echo json_encode([
            "success" => true,
            "message" => "Login successful",
            "vendor" => [
                "id" => $userData['ID'],
                "name" => $userData['Name'],
                "email" => $userData['Email'],
                "contactNo" => $userData['ContactNo'],
                "status" => $userData['vstatus']
            ]
        ]);
    } else {
        echo json_encode(["success" => false, "message" => "Invalid email or password"]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Invalid email or password"]);
}