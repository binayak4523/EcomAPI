<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');
require_once 'db.php';

$data = json_decode(file_get_contents("php://input"), true);
$email = $data['email'] ?? '';
$new_password = $data['new_password'] ?? '';

if (!$email || !$new_password) {
    echo json_encode(['success' => false, 'message' => 'All fields are required.']);
    exit;
}

$conn = new mysqli($host, $user, $password, $dbname);

// Check if email exists
$stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Email not found.']);
    exit;
}
$stmt->close();

// Save password in plain text (NOT SECURE)
$stmt2 = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
$stmt2->bind_param("ss", $new_password, $email);
$stmt2->execute();

echo json_encode(['success' => true, 'message' => 'Password reset successful.']);
$stmt2->close();
$conn->close();