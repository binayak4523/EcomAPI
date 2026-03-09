<?php
// db.php should connect to your MySQL database
include "db.php";

header('Content-Type: application/json');
$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed: " . $conn->connect_error]);
    exit();
}
$sql = "
SELECT 
    c.category_name AS category,
    c.category_id AS categoryid,
    sc.subcategory AS subcategory,
    sc.scategoryid AS subcategoryid,
    b.Brand AS brand,
    b.BrandID AS brandid,
    im.item_name,
    im.id,
    im.hsn,
    im.mrp,
    im.tax_p,
    im.dis_p,
    im.description,
    im.size_dimension
FROM item_master im
JOIN category c ON im.category_id = c.category_id
JOIN subcategory sc ON im.subcategory_id = sc.scategoryid
JOIN brands b ON im.BrandID = b.BrandID
WHERE im.status = 'active'
";

$result = $conn->query($sql);

$data = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
}

echo json_encode($data);
?>
