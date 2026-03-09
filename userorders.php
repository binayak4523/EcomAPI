<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=UTF-8');

include("db.php");

// Get user_id from query parameters
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if (!$user_id) {
    http_response_code(400);
    echo json_encode(["error" => "User ID is required"]);
    exit();
}

$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed"]);
    exit();
}

// Fetch company/vendor details (website owner, id=1)
$vendor_sql = "SELECT Name, Vaddress, ContactNo, Email, Bank_AC_No, IFSC FROM vendor WHERE id = 1 LIMIT 1";
$vendor_result = $conn->query($vendor_sql);
$company = null;
if ($vendor_result && $vendor_result->num_rows > 0) {
    $company = $vendor_result->fetch_assoc();
}

$sql = "SELECT 
    o.order_id,
    o.order_date,
    o.total_price,
    o.order_status,
    o.billingad_id,
    o.shippingad_id,
    i.id as item_id,
    i.item_name as name,
    oi.quantity,
    oi.price,
    pi.path_url as image_path,
    ba.name as billing_name,
    ba.address1 as billing_address,
    ba.city as billing_city,
    ba.state as billing_state,
    ba.pin as billing_pincode,
    ba.country as billing_country,
    sa.name as shipping_name,
    sa.address1 as shipping_address,
    sa.city as shipping_city,
    sa.state as shipping_state,
    sa.pin as shipping_pincode,
    sa.country as shipping_country
    FROM orders o
    LEFT JOIN order_items oi ON o.order_id = oi.order_id
    LEFT JOIN item_master i ON oi.product_id = i.id
    LEFT JOIN product_images pi ON i.id = pi.product_id AND pi.default_img = 'y'
    LEFT JOIN addresses ba ON o.billingad_id = ba.id
    LEFT JOIN addresses sa ON o.shippingad_id = sa.id
    WHERE o.user_id = ?
    ORDER BY o.order_date DESC";

try {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result) {
        $orders = array();
        $current_order = null;
        
        while ($row = $result->fetch_assoc()) {
            // Start a new order group
            if ($current_order === null || $current_order['order_id'] !== $row['order_id']) {
                // Add the previous order to the array if it exists
                if ($current_order !== null) {
                    $orders[] = $current_order;
                }
                
                // Start a new order
                $current_order = array(
                    'order_id' => $row['order_id'],
                    'order_date' => $row['order_date'],
                    'total_price' => $row['total_price'],
                    'order_status' => $row['order_status'],
                    'billingAddress' => array(
                        'name' => $row['billing_name'],
                        'address' => $row['billing_address'],
                        'city' => $row['billing_city'],
                        'state' => $row['billing_state'],
                        'pincode' => $row['billing_pincode'],
                        'country' => $row['billing_country']
                    ),
                    'shippingAddress' => array(
                        'name' => $row['shipping_name'],
                        'address' => $row['shipping_address'],
                        'city' => $row['shipping_city'],
                        'state' => $row['shipping_state'],
                        'pincode' => $row['shipping_pincode'],
                        'country' => $row['shipping_country']
                    ),
                    'items' => array()
                );
            }
            
            // Add item to current order
            if ($row['item_id']) {
                $current_order['items'][] = array(
                    'id' => $row['item_id'],
                    'name' => $row['name'],
                    'quantity' => $row['quantity'],
                    'price' => $row['price'],
                    'image_path' => $row['image_path']
                );
            }
        }
        
        // Add the last order if exists
        if ($current_order !== null) {
            $orders[] = $current_order;
        }

        // Output both orders and company details
        echo json_encode([
            "company" => $company,
            "orders" => $orders
        ]);
    } else {
        throw new Exception($conn->error);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Query failed: " . $e->getMessage()]);
}

$conn->close();