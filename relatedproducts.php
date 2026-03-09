<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

include("db.php");

$category_id = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;

if (!$category_id) {
    echo json_encode(['error' => 'Invalid category ID']);
    exit;
}

$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    echo json_encode(['error' => 'Connection failed']);
    exit;
}

$sql = "SELECT i.id, i.item_name, i.saleprice, pi.path_url AS image_path
        FROM item_master i
        LEFT JOIN product_images pi ON i.id = pi.product_id
        WHERE i.category_id = ?
        GROUP BY i.id";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $category_id);
$stmt->execute();
$result = $stmt->get_result();

$relatedProducts = [];
while ($row = $result->fetch_assoc()) {
    $relatedProducts[] = [
        'id' => $row['id'],
        'name' => $row['item_name'],
        'price' => $row['saleprice'],
        'image_path' => $row['image_path']
    ];
}

echo json_encode($relatedProducts);

$stmt->close();
$conn->close();