<?php
include 'db.php'; // Your DB connection

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=UTF-8');

$data = json_decode(file_get_contents('php://input'), true);

$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed: ' . $conn->connect_error]);
    exit;
}

// Check for UserID
if (!isset($data['UserID'])) {
    echo json_encode(['success' => false, 'error' => 'Missing required field: UserID']);
    exit;
}

$user_id = $data['UserID'];
$default_address = (isset($data['is_default']) && $data['is_default']) ? 'y' : 'n';

// Check if user already has addresses
$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM addresses WHERE UserID = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$has_address = $result['cnt'] > 0;

// If no address, force default
if (!$has_address) {
    $default_address = 'y';
} else if ($default_address === 'y') {
    // If user wants this as default, unset previous default
    $conn->query("UPDATE addresses SET default_address = 'n' WHERE UserID = $user_id");
}

// Insert new address
$stmt = $conn->prepare("INSERT INTO addresses (UserID, country, name, phone_no, pin, address1, address2, landmark, city, state, default_address) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param(
    "issssssssss",
    $user_id,
    $data['country'],
    $data['name'],
    $data['phone_no'],
    $data['pin'],
    $data['address1'],
    $data['address2'],
    $data['landmark'],
    $data['city'],
    $data['state'],
    $default_address
);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => $stmt->error]);
}
?>