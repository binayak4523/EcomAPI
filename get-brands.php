<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=UTF-8');

include("db.php");

// Add error logging
error_reporting(E_ALL);
ini_set('display_errors', 1);

$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed: " . $conn->connect_error]);
    exit();
}

$sql = "SELECT BrandID, Brand FROM brands ORDER BY Brand ASC";

try {
    $result = $conn->query($sql);
    
    if ($result) {
        $brands = array();
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $brands[] = array(
                    'id' => $row['BrandID'],  // Map to 'id' for frontend compatibility
                    'brand_name' => $row['Brand']
                );
            }
        }
        echo json_encode(["brands" => $brands]);
    } else {
        throw new Exception($conn->error);
    }
} catch (Exception $e) {
    error_log("Query failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "Query failed: " . $e->getMessage()]);
}

$conn->close();