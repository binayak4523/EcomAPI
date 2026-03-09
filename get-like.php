<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

require_once 'db.php';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $user, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get likes count for each product
    $sql = "SELECT product_id, COUNT(*) as likes_count 
            FROM liked_items 
            GROUP BY product_id";

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $likes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // If user is logged in, get their liked products
    if (isset($_GET['user_id'])) {
        $userId = $_GET['user_id'];

        $userLikesSql = "SELECT product_id 
                         FROM liked_items 
                         WHERE UserID = ?";

        $userLikesStmt = $conn->prepare($userLikesSql);
        $userLikesStmt->execute([$userId]);
        $userLikes = $userLikesStmt->fetchAll(PDO::FETCH_COLUMN);

        echo json_encode([
            'likes' => $likes,
            'userLikes' => $userLikes
        ]);
    } else {
        echo json_encode([
            'likes' => $likes
        ]);
    }

} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}

$conn = null;
?>
