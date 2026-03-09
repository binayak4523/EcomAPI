<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');
require_once 'db.php';

$data = json_decode(file_get_contents("php://input"), true);
$email = $data['email'] ?? '';

if (!$email) {
    echo json_encode(['success' => false, 'message' => 'Email is required.']);
    exit;
}

$conn = new mysqli($host, $user, $password, $dbname);
$stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Email not found.']);
    exit;
}

// Generate random 5-character code (letters and numbers)
$code = substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 5);

// Store code in DB (you can use a separate table or add a reset_code and reset_expiry column to users)
$expiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));
$stmt2 = $conn->prepare("UPDATE users SET reset_code = ?, reset_expiry = ? WHERE email = ?");
$stmt2->bind_param("sss", $code, $expiry, $email);
$stmt2->execute();

// Send email
$subject = "Your Password Reset Code";
$message = "Your password reset code is: $code\nThis code is valid for 15 minutes.";
$headers = "From: no-reply@yourdomain.com\r\n";
mail($email, $subject, $message, $headers);

echo json_encode(['success' => true, 'message' => 'Reset code sent to your email.']);