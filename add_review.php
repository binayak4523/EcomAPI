<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: POST, OPTIONS, GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

include("db.php");

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate input
if (!$input) {
    echo json_encode(['error' => 'Invalid JSON input']);
    http_response_code(400);
    exit;
}

if (!isset($input['product_id']) || !isset($input['rating']) || !isset($input['review']) || !isset($input['user_id']) || !isset($input['username'])) {
    echo json_encode(['error' => 'Missing required fields: product_id, rating, review, user_id, username']);
    http_response_code(400);
    exit;
}

$product_id = intval($input['product_id']);
$rating = intval($input['rating']);
$comment = trim($input['review']);
$user_id = intval($input['user_id']);
$username = $input['username'];

$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    echo json_encode(['error' => 'Connection failed']);
    exit;
}

// Check if user has already reviewed this product
$check_sql = "SELECT review_id FROM reviews WHERE product_id = ? AND user_id = ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("ii", $product_id, $user_id);
$check_stmt->execute();
$result = $check_stmt->get_result();

if ($result->num_rows > 0) {
    echo json_encode(['error' => 'You have already reviewed this product']);
    $check_stmt->close();
    $conn->close();
    exit;
}

$sql = "INSERT INTO reviews (product_id, user_id, reviewer, rating, comment) VALUES (?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode(['error' => 'Prepare failed: ' . $conn->error]);
    http_response_code(500);
    exit;
}

$stmt->bind_param("iisis", $product_id, $user_id, $username, $rating, $comment);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Review submitted successfully!']);
} else {
    echo json_encode(['error' => 'Failed to submit review: ' . $stmt->error]);
    http_response_code(500);
}

$stmt->close();
$conn->close();
?>
