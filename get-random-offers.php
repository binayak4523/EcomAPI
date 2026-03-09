<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit;
}

include 'db.php';
// Create connection
$conn = new mysqli($host, $user, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get 2 random active offers
$stmt = $conn->prepare("
    SELECT offer_id, title, description, discount_percentage, image_path, status 
    FROM offers 
    WHERE status = 'active' 
    AND CURDATE() BETWEEN start_date AND end_date
    ORDER BY RAND() 
    LIMIT 2
");

if (!$stmt) {
    http_response_code(500);
    echo json_encode(["error" => "Database error: " . $conn->error]);
    exit;
}

$stmt->execute();
$result = $stmt->get_result();
$offers = [];

while ($row = $result->fetch_assoc()) {
    // Add full image path if image exists
    if (!empty($row['image_path'])) {
        $row['image_path'] = "productimage/" . $row['image_path'];
    } else {
        // Default image if none exists
        $row['image_path'] = "productimage/default-offer.jpg";
    }
    
    $offers[] = $row;
}

// If we don't have enough offers, fill with defaults
if (count($offers) < 2) {
    $defaultOffers = [
        [
            'offer_id' => 0,
            'title' => 'Special Offer',
            'description' => 'Shop now for great deals',
            'discount_percentage' => 20,
            'image_path' => "productimage/offer-1.jpg",
            'status' => 'active'
        ],
        [
            'offer_id' => 0,
            'title' => 'Special Offer',
            'description' => 'Shop now for great deals',
            'discount_percentage' => 20,
            'image_path' => "productimage/offer-2.jpg",
            'status' => 'active'
        ]
    ];
    
    // Fill missing offers with defaults
    while (count($offers) < 2) {
        $offers[] = $defaultOffers[count($offers)];
    }
}

// Return offers as JSON
echo json_encode($offers);

$stmt->close();
$conn->close();