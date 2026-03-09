<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../db.php';

// Configuration
define('DELHIVERY_TOKEN', 'fc613ea049f63609e6be650bb73e0a63e016b11c');
define('DELHIVERY_URL', 'https://staging-express.delhivery.com/api/kinko/v1/invoice/charges/.json');
define('CACHE_DURATION', 3600); // Cache for 1 hour
define('CACHE_PATH', __DIR__ . '/cache/shipping_rates/');

// Create cache directory if it doesn't exist
if (!is_dir(CACHE_PATH)) {
    mkdir(CACHE_PATH, 0777, true);
}

try {
    $conn = new mysqli($host, $user, $password, $dbname);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    // Get order_no from POST or GET
    $order_id = isset($_REQUEST['order_no']) ? (int)$_REQUEST['order_no'] : null;
    if (!$order_id) {
        throw new Exception("Order number is required");
    }

    // First check total_weight from orders table
    $weightQuery = "SELECT total_weight FROM orders WHERE order_id = ?";
    $stmt = $conn->prepare($weightQuery);
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $order = $result->fetch_assoc();
    $stmt->close();

    $totalWeight = 0;

    if (!$order || $order['total_weight'] == 0) {
        // Calculate weight from item_master
        $itemWeightQuery = "
            SELECT 
                SUM(im.weight * oi.quantity) as total_weight
            FROM order_items oi
            JOIN item_master im ON oi.product_id = im.id
            WHERE oi.order_id = ?
        ";
        
        $stmt = $conn->prepare($itemWeightQuery);
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $weightData = $result->fetch_assoc();
        $stmt->close();

        $totalWeight = $weightData['total_weight'] ?? 0;

        // Update the orders table with calculated weight
        if ($totalWeight > 0) {
            $updateQuery = "UPDATE orders SET total_weight = ? WHERE order_id = ?";
            $stmt = $conn->prepare($updateQuery);
            $stmt->bind_param("di", $totalWeight, $order_id);
            $stmt->execute();
            $stmt->close();
        }
    } else {
        $totalWeight = $order['total_weight'];
    }

    if ($totalWeight <= 0) {
        throw new Exception("Could not determine package weight");
    }

    // Convert weight from grams to kg for Delhivery API
    $weightInKg = $totalWeight / 1000; // Convert grams to kg

    // Get pickup and delivery pincodes
    $addressQuery = "
        SELECT 
            o.shippingad_id,
            a.pin as d_pin,  -- delivery pincode
            s.pin_code as o_pin   -- origin pincode
        FROM orders o
        LEFT JOIN addresses a ON o.shippingad_id = a.id
        LEFT JOIN store s ON o.store_id = s.id
        WHERE o.order_id = ?
    ";

    $stmt = $conn->prepare($addressQuery);
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $addressData = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$addressData || !$addressData['d_pin'] || !$addressData['o_pin']) {
        throw new Exception("Missing pickup or delivery pincode");
    }

    // Prepare Delhivery API request
    // Check cache first
    $cacheKey = md5($addressData['o_pin'] . $addressData['d_pin'] . $weightInKg);
    $cacheFile = CACHE_PATH . $cacheKey . '.json';
    
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < CACHE_DURATION) {
        $cachedData = json_decode(file_get_contents($cacheFile), true);
        $shippingCost = $cachedData['shipping_cost'];
    } else {
        // Define base rates and variables
        $baseRate = 50;
        $additionalRate = 20;
        $distanceMultiplier = 1.0;

        // Call Delhivery API
        $apiUrl = DELHIVERY_URL;
        $queryParams = http_build_query([
            'md' => 'E',                    // mode of transportation (E for Express)
            'ss' => 'Delivered',            // shipment status
            'd_pin' => $addressData['d_pin'], // delivery pincode
            'o_pin' => $addressData['o_pin'], // origin pincode
            'cgm' => round($weightInKg * 1000), // weight in grams
            'pt' => 'Pre-paid'              // payment type
        ]);

        $ch = curl_init($apiUrl . '?' . $queryParams);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Token ' . DELHIVERY_TOKEN
            ]
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            // Enhanced fallback calculation
            $baseRate = 50;
            $additionalRate = 20;
            $distanceMultiplier = 1.0;
            
            // Calculate distance-based multiplier using pincodes
            $firstTwoDigits1 = substr($addressData['o_pin'], 0, 2);
            $firstTwoDigits2 = substr($addressData['d_pin'], 0, 2);
            $pincodeDiff = abs((int)$firstTwoDigits1 - (int)$firstTwoDigits2);
            
            if ($pincodeDiff > 0) {
                $distanceMultiplier += ($pincodeDiff * 0.1); // 10% increase per region difference
            }
            
            // Calculate base cost
            $shippingCost = $baseRate;
            if ($weightInKg > 1) {
                $additionalWeight = ceil($weightInKg - 1);
                $shippingCost += ($additionalWeight * $additionalRate);
            }
            
            // Apply distance multiplier
            $shippingCost *= $distanceMultiplier;
            
            // Round to nearest 10
            $shippingCost = ceil($shippingCost / 10) * 10;
        } else {
            $delhiveryResponse = json_decode($response, true);
            $shippingCost = isset($delhiveryResponse['total_amount']) 
                ? $delhiveryResponse['total_amount'] 
                : ($baseRate + (ceil($weightInKg - 1) * $additionalRate));
                
            // Cache the successful API response
            file_put_contents($cacheFile, json_encode([
                'shipping_cost' => $shippingCost,
                'timestamp' => time()
            ]));
        }
    }

    // Return formatted response
    echo json_encode([
        'success' => true,
        'weight' => number_format($weightInKg, 3) . ' kg', // 3 decimal places for more precise kg conversion
        'shipping_cost' => number_format($shippingCost, 2)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>
