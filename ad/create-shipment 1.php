<?php
header('Access-Control-Allow-Origin: *');

// Define log directory
define('LOG_DIR', __DIR__ . '/logs');

// Create log directory if it doesn't exist
if (!file_exists(LOG_DIR)) {
    mkdir(LOG_DIR, 0777, true);
}

// Function to log API interactions
function logApiInteraction($type, $data) {
    $timestamp = date('Y-m-d H:i:s');
    $logFile = LOG_DIR . '/delhivery_api_' . date('Y-m-d') . '.log';
    
    $logEntry = "\n[$timestamp] $type:\n";
    $logEntry .= "----------------------------------------\n";
    $logEntry .= is_string($data) ? $data : json_encode($data, JSON_PRETTY_PRINT);
    $logEntry .= "\n----------------------------------------\n";
    
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../db.php';

// Configuration
define('DELHIVERY_TOKEN', 'fc613ea049f63609e6be650bb73e0a63e016b11c');
define('DELHIVERY_URL', 'https://staging-express.delhivery.com/api/cmu/create.json');
define('DELHIVERY_API_DEBUG', true); // Enable debug mode

try {
    $conn = new mysqli($host, $user, $password, $dbname);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    // Get JSON input
    $jsonInput = file_get_contents('php://input');
    logApiInteraction('DEBUG', ['raw_input' => $jsonInput]);
    
    $input = json_decode($jsonInput, true);
    logApiInteraction('DEBUG', ['decoded_input' => $input]);

    if (!$input) {
        throw new Exception("Invalid JSON input");
    }

    // Get required fields from input
    $order_id = null;
    
    // Debug log for entire input
    logApiInteraction('DEBUG', [
        'full_input' => $input,
        'input_type' => gettype($input),
        'raw_order_id' => isset($input['order_id']) ? $input['order_id'] : 'not_set'
    ]);

    // Check if input is valid array
    if (!is_array($input)) {
        throw new Exception("Invalid input format: Expected array, got " . gettype($input));
    }

    // Check if order_id exists
    if (!isset($input['order_id'])) {
        throw new Exception("order_id field is missing in the request");
    }

    // Check if order_id is not empty
    if (empty($input['order_id'])) {
        throw new Exception("order_id cannot be empty");
    }

    // Convert and validate order_id
    $raw_order_id = $input['order_id'];
    $order_id = filter_var($raw_order_id, FILTER_VALIDATE_INT);
    
    if ($order_id === false || $order_id <= 0) {
        throw new Exception(sprintf(
            "Invalid order_id value. Expected positive integer, got: '%s' (type: %s)",
            $raw_order_id,
            gettype($raw_order_id)
        ));
    }
    
    logApiInteraction('DEBUG', [
        'raw_order_id' => $raw_order_id,
        'parsed_order_id' => $order_id,
        'validation_passed' => true
    ]);

    // Debug: Get table structures
    $tables = ['addresses', 'orders', 'store'];
    $tableStructures = [];
    
    foreach ($tables as $table) {
        $debugQuery = "SHOW COLUMNS FROM " . $table;
        $debugResult = $conn->query($debugQuery);
        if ($debugResult) {
            $columns = [];
            while($row = $debugResult->fetch_assoc()) {
                $columns[] = $row['Field'];
            }
            $tableStructures[$table] = $columns;
        } else {
            logApiInteraction('ERROR', [
                'message' => "Failed to get {$table} table structure",
                'sql_error' => $conn->error
            ]);
        }
    }
    
    logApiInteraction('DEBUG', ['table_structures' => $tableStructures]);

    // Get order details, shipping address, and store details
    $query = "
        SELECT 
            o.order_id,
            o.order_date,
            o.total_price,
            o.total_weight,
            a.name as shipping_name,
            CONCAT(a.address1, ' ', IFNULL(a.address2, '')) as shipping_address,
            a.city as shipping_city,
            a.state as shipping_state,
            a.pin as shipping_pincode,
            a.phone_no as shipping_phone,
            s.Store_Name as store_name,
            s.saddress as store_address,
            s.phoneno as store_phone,
            s.pin_code as store_pincode
        FROM orders o
        LEFT JOIN addresses a ON o.shippingad_id = a.id
        LEFT JOIN store s ON o.store_id = s.id
        WHERE o.order_id = ?
    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $orderData = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$orderData) {
        throw new Exception("Order not found");
    }

    // Get order items
    $itemsQuery = "
        SELECT 
            oi.*,
            im.item_name,
            im.hsn,
            im.size_dimension,
            im.weight
        FROM order_items oi
        JOIN item_master im ON oi.product_id = im.id
        WHERE oi.order_id = ?
    ";

    $stmt = $conn->prepare($itemsQuery);
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Prepare products description
    $productsDesc = implode(', ', array_map(function($item) {
        return $item['item_name'] . ' x ' . $item['quantity'];
    }, $items));

    // Map shipping method from frontend to Delhivery format
    $shippingMethodMap = [
        'standard' => 'Surface',
        'express' => 'Express',
        'priority' => 'Premium'
    ];

    // Use dimensions from frontend input, fallback to item dimensions if not provided
    $shipmentLength = isset($input['length']) ? (float)$input['length'] : 
        (!empty($items[0]['size_dimension']) ? explode('x', $items[0]['size_dimension'])[0] : 100);
    $shipmentWidth = isset($input['width']) ? (float)$input['width'] : 
        (!empty($items[0]['size_dimension']) ? explode('x', $items[0]['size_dimension'])[1] : 100);
    $shipmentHeight = isset($input['height']) ? (float)$input['height'] : 
        (!empty($items[0]['size_dimension']) ? explode('x', $items[0]['size_dimension'])[2] : 100);

    // Use weight from frontend input, fallback to order total weight
    $weight = isset($input['weight']) ? (float)$input['weight'] : ($orderData['total_weight'] / 1000);

    // Prepare return address
    $returnAddress = [
        'name' => $input['return_name'] ?? $orderData['store_name'],
        'add' => $input['return_add'] ?? $orderData['store_address'],
        'pin' => $input['return_pin'] ?? $orderData['store_pincode'],
        'city' => $input['return_city'] ?? '',  // Default to empty if not provided
        'state' => $input['return_state'] ?? '', // Default to empty if not provided
        'country' => 'India',
        'phone' => $input['return_phone'] ?? $orderData['store_phone']
    ];

    // Prepare shipment data according to Delhivery API format
    $shipmentData = [
        'shipments' => [
            [
                // Client and Order Details
                'client' => '2019ab-CTKartRetailIndia-do',  // Your client name
                'name' => $orderData['shipping_name'],
                'order' => (string)$order_id,  // Order number as string
                'products_desc' => $productsDesc,
                'order_date' => date('Y-m-d', strtotime($orderData['order_date'])),
                'payment_mode' => $input['payment_mode'] === 'COD' ? 'COD' : 'Prepaid',
                'total_amount' => number_format($orderData['total_price'], 2, '.', ''),
                
                // Delivery Address
                'add' => trim($orderData['shipping_address']),
                'address_type' => 'home',
                'city' => $orderData['shipping_city'],
                'state' => $orderData['shipping_state'],
                'country' => 'India',
                'phone' => preg_replace('/[^0-9]/', '', $orderData['shipping_phone']),
                'pin' => $orderData['shipping_pincode'],

                // Package Details
                'weight' => number_format($weight, 3, '.', ''),  // Weight in kg with 3 decimal places
                'shipment_length' => number_format($shipmentLength, 2, '.', ''),
                'shipment_width' => number_format($shipmentWidth, 2, '.', ''),
                'shipment_height' => number_format($shipmentHeight, 2, '.', ''),
                'quantity' => count($items),
                
                // COD Details if applicable
                'cod_amount' => $input['payment_mode'] === 'COD' ? 
                    number_format($input['cod_amount'] ?? $orderData['total_price'], 2, '.', '') : 
                    '0.00',
                
                // Return Details
                'return_pin' => $returnAddress['pin'],
                'return_city' => $returnAddress['city'],
                'return_phone' => preg_replace('/[^0-9]/', '', $returnAddress['phone']),
                'return_add' => trim($returnAddress['add']),
                'return_state' => $returnAddress['state'],
                'return_country' => 'India'
            ]
        ],
        'pickup_location' => [
            'name' => $input['pickup_location'] ?? $orderData['store_name'],
            'add' => trim($returnAddress['add']),
            'city' => $returnAddress['city'],
            'pin' => $returnAddress['pin'],
            'state' => $returnAddress['state'],
            'country' => 'India',
            'phone' => preg_replace('/[^0-9]/', '', $returnAddress['phone'])
        ]
    ];

    file_put_contents("createshipment.txt", json_encode($shipmentData) . "\n", FILE_APPEND);
    // Log the request data
    logApiInteraction('REQUEST', [
        'url' => DELHIVERY_URL,
        'data' => [
            'format' => 'json',
            'data' => $shipmentData
        ]
    ]);

    // Validate required fields in shipment data
    $requiredFields = ['name', 'add', 'pin', 'city', 'state', 'country', 'phone', 'order'];
    foreach ($shipmentData['shipments'][0] as $key => $value) {
        if (in_array($key, $requiredFields) && empty($value)) {
            throw new Exception("Missing required field: {$key}");
        }
    }

    // Ensure phone numbers are in correct format (10 digits)
    $shipmentData['shipments'][0]['phone'] = preg_replace('/[^0-9]/', '', $shipmentData['shipments'][0]['phone']);
    $shipmentData['pickup_location']['phone'] = preg_replace('/[^0-9]/', '', $shipmentData['pickup_location']['phone']);

    // Prepare request data according to Delhivery format
    $requestData = [
        'format' => 'json',
        'data' => json_encode($shipmentData)
    ];
    
    // Verify JSON encoding succeeded
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("JSON encoding failed: " . json_last_error_msg());
    }

    // Log the exact data being sent to Delhivery
    logApiInteraction('DEBUG', ['delhivery_request_data' => $requestData]);

    // Log request data if debug is enabled
    if (DELHIVERY_API_DEBUG) {
        logApiInteraction('DEBUG', ['formatted_request' => $shipmentData]);
    }

    // Prepare request data
    $postData = [
        'format' => 'json',
        'data' => json_encode($shipmentData)
    ];

    // Call Delhivery API
    $ch = curl_init(DELHIVERY_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded',
            'Authorization: Token ' . DELHIVERY_TOKEN,
            'Accept: application/json'
        ],
        CURLOPT_SSL_VERIFYPEER => false,  // For development/staging only
        CURLOPT_POSTFIELDS => http_build_query($postData)
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception("Failed to create shipment: " . $response);
    }

    $delhiveryResponse = json_decode($response, true);
    
    // Log the response
    logApiInteraction('RESPONSE', [
        'httpCode' => $httpCode,
        'response' => $delhiveryResponse
    ]);
    
    // Update order with waybill number and shipping details if available
    if (isset($delhiveryResponse['packages'][0]['waybill'])) {
        $waybill = $delhiveryResponse['packages'][0]['waybill'];
        $updateQuery = "
            UPDATE orders 
            SET 
                shipping_id = ?,
                shipping_method = ?,
                package_type = ?,
                shipping_weight = ?,
                cod_amount = ?
            WHERE order_id = ?
        ";
        $stmt = $conn->prepare($updateQuery);
        $shippingMethod = $input['shipping_method'];
        $packageType = $input['package_type'];
        $codAmount = $input['payment_mode'] === 'COD' ? ($input['cod_amount'] ?? $orderData['total_price']) : '0';
        $stmt->bind_param(
            "sssddi",
            $waybill,
            $shippingMethod,
            $packageType,
            $weight,
            $codAmount,
            $order_id
        );
        $stmt->execute();
        $stmt->close();
    }

    echo json_encode([
        'success' => true,
        'message' => 'Shipment created successfully',
        'data' => $delhiveryResponse
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