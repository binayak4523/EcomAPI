<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include("db.php");

// Get request body
$data = json_decode(file_get_contents("php://input"));

if (!isset($data->user_id) || !isset($data->item_id) || !isset($data->quantity)) {
    echo json_encode(["error" => "Missing required parameters"]);
    exit();
}

$user_id = intval($data->user_id);
$item_id = intval($data->item_id);
$quantity = intval($data->quantity);

if ($user_id <= 0 || $item_id <= 0 || $quantity <= 0) {
    echo json_encode(["error" => "Invalid parameters"]);
    exit();
}

$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode(["error" => "Connection failed: " . $conn->connect_error]);
    exit();
}

// Update cart item quantity
$sql = "UPDATE cart SET quantity = ? WHERE id = ? AND user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iii", $quantity, $item_id, $user_id);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["error" => "Failed to update cart item"]);
}

$conn->close();