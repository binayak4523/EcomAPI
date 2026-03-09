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

// Modify the query to include subcategories
$sql = "SELECT c.category_id, c.category_name, s.scategoryid, s.subcategory
        FROM category c
        LEFT JOIN subcategory s ON c.category_id = s.categoryid
        ORDER BY c.category_name ASC, s.subcategory ASC";

try {
    $result = $conn->query($sql);
    
    if ($result) {
        $categories = [];
        while ($row = $result->fetch_assoc()) {
            $categoryId = $row['category_id'];
            if (!isset($categories[$categoryId])) {
                $categories[$categoryId] = [
                    'id' => $categoryId,
                    'category_name' => $row['category_name'],
                    'subcategories' => []
                ];
            }
            if ($row['subcategory']) {
                $categories[$categoryId]['subcategories'][] = [
                    'id' => $row['scategoryid'],
                    'name' => $row['subcategory']
                ];
            }
        }
        echo json_encode(["categories" => array_values($categories)]);
    } else {
        throw new Exception($conn->error);
    }
} catch (Exception $e) {
    error_log("Query failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "Query failed: " . $e->getMessage()]);
}

$conn->close();