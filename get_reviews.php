<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=UTF-8');
require_once 'db.php';



$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}
// Get product_id from query parameter
$product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;

if ($product_id <= 0) {
    echo json_encode(["status" => "error", "message" => "Invalid product ID"]);
    exit();
}

// Fetch reviews for the given product_id
$sql = "SELECT review_id, product_id, user_id, reviewer, rating, comment FROM reviews WHERE product_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $product_id);
$stmt->execute();

$result = $stmt->get_result();
$reviews = [];

while ($row = $result->fetch_assoc()) {
    $reviews[] = $row;
}

echo json_encode([
    "status" => "success",
    "data" => $reviews
]);

$stmt->close();
$conn->close();
?>