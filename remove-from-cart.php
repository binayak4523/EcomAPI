<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include("db.php");

// Get request body
$data = json_decode(file_get_contents("php://input"));

if (!isset($data->user_id) || !isset($data->cart_id)) {
    echo json_encode(["error" => "Missing required parameters"]);
    exit();
}

$user_id = intval($data->user_id);
$item_id = intval($data->cart_id);

if ($user_id <= 0 || $item_id <= 0) {
    echo json_encode(["error" => "Invalid parameters"]);
    exit();
}

$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode(["error" => "Connection failed: " . $conn->connect_error]);
    exit();
}

// Remove item from cart
$sql = "DELETE FROM cart WHERE cart_id = ? AND user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $item_id, $user_id);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["error" => "Failed to remove cart item"]);
}

$conn->close();