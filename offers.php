<?php
// offers.php

// Allow cross-origin requests
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// Include db connection
include("db.php");

// Create connection
$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed: " . $conn->connect_error]);
    exit();
}

// Query the offers table
$sql = "SELECT * FROM offers ORDER BY start_date DESC";
$result = $conn->query($sql);

if (!$result) {
    http_response_code(500);
    echo json_encode(["error" => "Query error: " . $conn->error]);
    exit();
}

$offers = [];
while ($row = $result->fetch_assoc()) {
    $offers[] = [
        "offer_id" => $row["offer_id"],
        "title" => $row["title"],
        "description" => $row["description"],
        "discount_percentage" => floatval($row["discount_percentage"]),
        "start_date" => $row["start_date"],
        "end_date" => $row["end_date"],
        "image_path" => $row["image_path"]
    ];
}

echo json_encode($offers);

$conn->close();
