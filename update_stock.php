<?php
// Allow CORS if needed
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

include "db.php";

$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed: " . $conn->connect_error]);
    exit();
}

// Read input
$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['items']) || !is_array($data['items'])) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid input"]);
    exit;
}

$updated = 0;

foreach ($data['items'] as $item) {
    $id = intval($item['ProductCode']);
    $qty = floatval($item['Qty']);

    $stmt = $conn->prepare("UPDATE item_master SET Qty = ? WHERE id = ?");
    $stmt->bind_param("di", $qty, $id);

    if ($stmt->execute()) {
        $updated += $stmt->affected_rows;
    }

    $stmt->close();
}

$conn->close();

echo json_encode(["updated" => $updated]);
?>