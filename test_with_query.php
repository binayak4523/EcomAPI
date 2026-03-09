<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=UTF-8');

include("db.php");

$conn = new mysqli($host, $user, $password, $dbname);

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

echo "test";
?>
