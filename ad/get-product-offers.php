<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

require_once 'db.php';

try {
    $sql = "
        SELECT 
            po.id,
            po.product_id,
            po.discount_percentage,
            po.start_date,
            po.end_date,
            po.status,
            im.name as product_name,
            im.sku
        FROM product_offers po
        JOIN item_master im ON po.product_id = im.ID
        ORDER BY po.start_date DESC
    ";
    
    $result = $conn->query($sql);

    if (!$result) {
        throw new Exception("Query failed: " . $conn->error);
    }

    $offers = [];
    while ($row = $result->fetch_assoc()) {
        $offers[] = $row;
    }

    echo json_encode($offers);
    $conn->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
