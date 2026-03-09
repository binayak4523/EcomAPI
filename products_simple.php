<?php
file_put_contents("/tmp/debug.log", "Starting products_simple.php\n", FILE_APPEND);

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=UTF-8');

file_put_contents("/tmp/debug.log", "About to include db.php\n", FILE_APPEND);
include("db.php");
file_put_contents("/tmp/debug.log", "Included db.php\n", FILE_APPEND);

$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    file_put_contents("/tmp/debug.log", "Connection failed: " . $conn->connect_error . "\n", FILE_APPEND);
    echo json_encode(["error" => "Connection failed: " . $conn->connect_error]);
    exit();
}

file_put_contents("/tmp/debug.log", "Connected to database\n", FILE_APPEND);

$sql = "SELECT 
    i.id,
    i.item_name as name,
    i.saleprice as price,
    i.description,
    i.status,
    i.ongoing_offer,
    i.discount_percentage,
    pi.path_url as image_path
    FROM item_master i
    LEFT JOIN product_images pi ON i.id = pi.product_id AND pi.default_img = 'y'
    WHERE i.status = 'active' 
    ORDER BY i.id DESC";

$result = $conn->query($sql);

if (!$result) {
    file_put_contents("/tmp/debug.log", "Query failed: " . $conn->error . "\n", FILE_APPEND);
    echo json_encode(["error" => "Query failed: " . $conn->error]);
    exit();
}

file_put_contents("/tmp/debug.log", "Query successful, rows: " . $result->num_rows . "\n", FILE_APPEND);

$products = array();
while ($row = $result->fetch_assoc()) {
    $original_price = floatval($row['price']);
    $discount_percentage = floatval($row['discount_percentage']) ?: 0;
    $discounted_price = $original_price - ($original_price * ($discount_percentage / 100));
    
    $products[] = array(
        'id' => $row['id'],
        'name' => $row['name'],
        'price' => $row['ongoing_offer'] === 'yes' ? round($discounted_price, 2) : $original_price,
        'original_price' => $original_price,
        'discount_percentage' => $discount_percentage,
        'ongoing_offer' => $row['ongoing_offer'],
        'description' => $row['description'],
        'image_path' => $row['image_path'] ?? 'http://localhost/api/uploads/placeholder.jpg'
    );
}

file_put_contents("/tmp/debug.log", "About to close connection and echo JSON with " . count($products) . " products\n", FILE_APPEND);
$conn->close();
$json = json_encode($products);
file_put_contents("/tmp/debug.log", "JSON length: " . strlen($json) . "\n", FILE_APPEND);
echo $json;
file_put_contents("/tmp/debug.log", "Done echoing\n", FILE_APPEND);
?>
