<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

// Include the database connection
require_once 'db.php';

try {
    // Create connection using the credentials from db.php
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $user, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Query to get average ratings for all products
    $sql = "SELECT 
                reviews.product_id,
                AVG(reviews.rating) as average_rating,
                COUNT(reviews.review_id) as review_count
            FROM reviews 
            GROUP BY reviews.product_id";
            
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $ratings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($ratings);

} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}

$conn = null; // Close connection
?>