<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');
require_once 'db.php';

$data = json_decode(file_get_contents("php://input"), true);
$email = $data['email'] ?? '';
$code = $data['code'] ?? '';

if (!$email || !$code) {
    echo json_encode(['success' => false, 'message' => 'Email and code are required.']);
    exit;
}

$conn = new mysqli($host, $user, $password, $dbname);
$stmt = $conn->prepare("SELECT reset_code, reset_expiry FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->bind_result($db_code, $db_expiry);
$stmt->fetch();

if (!$db_code || !$db_expiry) {
    echo json_encode(['success' => false, 'message' => 'No reset request found.']);
    exit;
}

if ($db_code !== $code) {
    echo json_encode(['success' => false, 'message' => 'Invalid code.']);
    exit;
}

if (strtotime($db_expiry) < time()) {
    echo json_encode(['success' => false, 'message' => 'Code expired.']);
    exit;
}

echo json_encode(['success' => true, 'message' => 'Code verified.']);