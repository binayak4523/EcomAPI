<?php
// Update CORS headers to be more specific
header('Access-Control-Allow-Origin: *');  // Your React app URL
header('Access-Control-Allow-Methods: OPTIONS, GET, POST, DELETE');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

header('Content-Type: application/json');

require_once 'db.php';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $user, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Decode incoming JSON request
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['user_id']) || !isset($data['product_id'])) {
        throw new Exception('Missing required parameters');
    }

    $userId = $data['user_id'];
    $productId = $data['product_id'];

    // Check if like already exists
    $checkSql = "SELECT id FROM liked_items WHERE UserID = ? AND product_id = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->execute([$userId, $productId]);
    $existing = $checkStmt->fetch();

    if ($existing) {
        // Unlike the product
        $deleteSql = "DELETE FROM liked_items WHERE UserID = ? AND product_id = ?";
        $stmt = $conn->prepare($deleteSql);
        $stmt->execute([$userId, $productId]);
    } else {
        // Like the product
        $insertSql = "INSERT INTO liked_items (UserID, product_id) VALUES (?, ?)";
        $stmt = $conn->prepare($insertSql);
        $stmt->execute([$userId, $productId]);
    }

    // Get updated like count
    $countSql = "SELECT COUNT(*) as likes_count FROM liked_items WHERE product_id = ?";
    $countStmt = $conn->prepare($countSql);
    $countStmt->execute([$productId]);
    $likesCount = $countStmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'likes_count' => $likesCount['likes_count']
    ]);

} catch(Exception $e) {
    error_log("like.php error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal Server Error']);
}

$conn = null;