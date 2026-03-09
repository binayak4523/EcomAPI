<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

include("db.php");

// Function to convert hex color to readable color name
function hexToColorName($hex) {
    $colorMap = [
        '#000000' => 'Black',
        '#ffffff' => 'White',
        '#ff0000' => 'Red',
        '#00ff00' => 'Green',
        '#0000ff' => 'Blue',
        '#ffff00' => 'Yellow',
        '#ff00ff' => 'Magenta',
        '#00ffff' => 'Cyan',
        '#800000' => 'Maroon',
        '#808000' => 'Olive',
        '#008000' => 'Dark Green',
        '#008080' => 'Teal',
        '#000080' => 'Navy',
        '#800080' => 'Purple',
        '#ffa500' => 'Orange',
        '#a52a2a' => 'Brown',
        '#808080' => 'Gray',
        '#c0c0c0' => 'Silver',
        '#ffc0cb' => 'Pink',
        '#ffd700' => 'Gold',
        '#4b0082' => 'Indigo',
        '#ee82ee' => 'Violet',
        '#daa520' => 'Goldenrod',
        '#ff6347' => 'Tomato',
        '#32cd32' => 'Lime Green',
        '#87ceeb' => 'Sky Blue',
        '#d2691e' => 'Chocolate',
    ];
    
    $hexLower = strtolower($hex);
    
    if (isset($colorMap[$hexLower])) {
        return $colorMap[$hexLower];
    }
    
    // If hex code is not in map, try to find closest match
    if (strlen($hexLower) === 7 && $hexLower[0] === '#') {
        // Extract RGB values
        $r = hexdec(substr($hexLower, 1, 2));
        $g = hexdec(substr($hexLower, 3, 2));
        $b = hexdec(substr($hexLower, 5, 2));
        
        // Determine color based on RGB values
        if ($r > 200 && $g > 200 && $b > 200) {
            return 'White';
        } elseif ($r < 50 && $g < 50 && $b < 50) {
            return 'Black';
        } elseif ($r > $g && $r > $b) {
            if ($r > 150) return 'Light Red';
            else return 'Red';
        } elseif ($g > $r && $g > $b) {
            if ($g > 150) return 'Light Green';
            else return 'Green';
        } elseif ($b > $r && $b > $g) {
            if ($b > 150) return 'Light Blue';
            else return 'Blue';
        } elseif ($r > 150 && $g > 150) {
            return 'Yellow';
        } elseif ($r > 150 && $b > 150) {
            return 'Purple';
        } elseif ($g > 150 && $b > 150) {
            return 'Cyan';
        } else {
            return 'Gray';
        }
    }
    
    return $hex; // Return original hex if can't determine
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$id) {
    echo json_encode(['error' => 'Invalid product ID']);
    exit;
}

$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    echo json_encode(['error' => 'Connection failed']);
    exit;
}

$sql = "SELECT i.*, 
        b.Brand,
        c.category_name,
        c.category_id,
        s.subcategory,
        GROUP_CONCAT(pi.path_url) as all_images
        FROM item_master i
        LEFT JOIN brands b ON i.BrandID = b.BrandID
        LEFT JOIN category c ON i.category_id = c.category_id
        LEFT JOIN subcategory s ON i.subcategory_id = s.scategoryid
        LEFT JOIN product_images pi ON i.id = pi.product_id
        WHERE i.id = ?
        GROUP BY i.id, b.Brand, c.category_name, c.category_id, s.subcategory";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();

// Get all products with the same name and their colors
$colors = [];
if ($product) {
    $colorsSql = "SELECT DISTINCT color, id FROM item_master WHERE item_name = ? AND color IS NOT NULL AND color != '' ORDER BY color";
    $colorsStmt = $conn->prepare($colorsSql);
    $colorsStmt->bind_param("s", $product['item_name']);
    $colorsStmt->execute();
    $colorsResult = $colorsStmt->get_result();
    while ($colorRow = $colorsResult->fetch_assoc()) {
        $colorName = hexToColorName($colorRow['color']);
        $colors[] = [
            'id' => $colorRow['id'],
            'color_code' => $colorRow['color'],
            'color_name' => $colorName
        ];
    }
    $colorsStmt->close();
}

// Get product details
$detailsSql = "SELECT name, detail, required FROM details WHERE productid = ?";
$detailsStmt = $conn->prepare($detailsSql);
$detailsStmt->bind_param("i", $id);
$detailsStmt->execute();
$detailsResult = $detailsStmt->get_result();
$details = [];
while ($detailRow = $detailsResult->fetch_assoc()) {
    $details[] = $detailRow;
}

if ($product) {
    // Calculate discounted price if applicable
    $original_price = floatval($product['saleprice']);
    $discount_percentage = floatval($product['discount_percentage']) ?: 0;
    $discounted_price = $original_price - ($original_price * ($discount_percentage / 100));
    
    // Determine final price based on discount
    $final_price = ($discount_percentage > 0) ? round($discounted_price, 2) : $original_price;
    
    // Format the product data
    $response = [
        'id' => $product['id'],
        'name' => $product['item_name'],
        'price' => $final_price,
        'original_price' => $original_price,
        'discount_percentage' => $discount_percentage,
        'ongoing_offer' => $product['ongoing_offer'],
        'mrp' => $product['mrp'],
        'description' => $product['description'],
        'brand' => $product['Brand'],
        'category' => $product['category_name'],
        'category_id' => $product['category_id'],
        'subcategory' => $product['subcategory'],
        'color' => $product['color'],
        'size' => $product['size_dimension'],
        'weight' => $product['weight'],
        'status' => $product['status'],
        'quantity' => $product['Qty'],
        'dimension' => $product['size_dimension'],
        'deliverytime' => $product['packingtime']+10,
        'all_images' => $product['all_images'] ? explode(',', $product['all_images']) : [],
        'image_path' => $product['all_images'] ? explode(',', $product['all_images'])[0] : null,
        'details' => $details,
        'available_colors' => $colors
    ];
    
    echo json_encode($response);
} else {
    echo json_encode(['error' => 'Product not found']);
}

$stmt->close();
$detailsStmt->close();
$conn->close();