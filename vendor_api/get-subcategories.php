<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

include 'db.php';

$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    die(json_encode(['error' => 'Connection failed: ' . $conn->connect_error]));
}

$sql = "SELECT s.*, c.category_name 
        FROM subcategory s 
        LEFT JOIN category c ON s.categoryid = c.category_id";
$result = $conn->query($sql);
$subcategories = array();

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $subcategories[] = $row;
    }
}

echo json_encode($subcategories);
$conn->close();