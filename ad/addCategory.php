<?php

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

include 'db.php';
// Create connection
$conn = new mysqli($host, $user, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit;
}

$category_name = isset($_POST['category_name']) ? $_POST['category_name'] : '';
$description   = isset($_POST['description']) ? $_POST['description'] : '';

if (empty($category_name) || empty($description)) {
    http_response_code(400);
    echo json_encode(["error" => "Category name and description are required."]);
    exit;
}

$stmt = $conn->prepare("INSERT INTO category (category_name, description) VALUES (?, ?)");
$stmt->bind_param("ss", $category_name, $description);

if ($stmt->execute()) {
    echo json_encode(["category_id" => $stmt->insert_id, "category_name" => $category_name]);
} else {
    http_response_code(500);
    echo json_encode(["error" => "Failed to insert category"]);
}
$stmt->close();
$conn->close();
?>
