<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit;
}

include 'db.php';
// Create connection
$conn = new mysqli($host, $user, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get the JSON payload
$data = json_decode(file_get_contents("php://input"), true);
if (!$data || !isset($data['id'])) {
    http_response_code(400);
    echo json_encode(["error" => "Offer ID is required"]);
    exit;
}

$offerId = $data['id'];

// First, get the image path to delete the file
$stmt = $conn->prepare("SELECT image_path FROM offers WHERE offer_id = ?");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(["error" => "Database error: " . $conn->error]);
    exit;
}

$stmt->bind_param("i", $offerId);
$stmt->execute();
$stmt->bind_result($imagePath);
$stmt->fetch();
$stmt->close();

// Delete the image file if it exists
if (!empty($imagePath)) {
    $filePath = "../uploads/offers/" . $imagePath;
    if (file_exists($filePath)) {
        unlink($filePath);
    }
}

// Delete the offer from the database
$stmt = $conn->prepare("DELETE FROM offers WHERE offer_id = ?");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(["error" => "Database error: " . $conn->error]);
    exit;
}

$stmt->bind_param("i", $offerId);
if ($stmt->execute()) {
    echo json_encode(["success" => "Offer deleted successfully"]);
} else {
    http_response_code(500);
    echo json_encode(["error" => "Failed to delete offer"]);
}

$stmt->close();
$conn->close();