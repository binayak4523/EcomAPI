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

$categoryid  = isset($_POST['category_id']) ? $_POST['category_id'] : '';
$subcategory = isset($_POST['subcategory']) ? $_POST['subcategory'] : '';

if (empty($categoryid) || empty($subcategory)) {
    http_response_code(400);
    echo json_encode(["error" => "Category and subcategory are required."]);
    exit;
}

$stmt = $conn->prepare("INSERT INTO subcategory (categoryid, subcategory) VALUES (?, ?)");
$stmt->bind_param("is", $categoryid, $subcategory);

if ($stmt->execute()) {
    echo json_encode(["scategoryid" => $stmt->insert_id, "subcategory" => $subcategory, "categoryid" => $categoryid]);
} else {
    http_response_code(500);
    echo json_encode(["error" => "Failed to insert subcategory"]);
}
$stmt->close();
$conn->close();
?>
