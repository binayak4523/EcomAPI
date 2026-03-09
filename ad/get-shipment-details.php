
<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Access-Control-Allow-Headers,Content-Type,Access-Control-Allow-Methods,Authorization,X-Requested-With');

include_once 'db.php';

try {
    
    $conn = new mysqli($host, $user, $password, $dbname);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    // Get ID parameter
    $id = isset($_GET['id']) ? $_GET['id'] : die();

    // Validate ID
    if (!filter_var($id, FILTER_VALIDATE_INT)) {
        throw new Exception("Invalid order ID");
    }

    // Prepare the SQL query
    $query = "
        SELECT 
            o.order_id,
            u.name as customer_name,
            a.address1 as shipping_address,
            a.phone_no as shipping_phone,
            a.city as shipping_city,
            a.state as shipping_state,
            a.pin as shipping_pincode,
            o.order_date,
            o.total_price as total_amount,
            o.payment_method as payment_mode,
            o.tracking_no,
            o.waybill_no,
            o.shipping_method as shipping_mode,
            o.shipping_weight as weight,
            o.dimensions,
            oi.product_id,
            oi.quantity,
            p.item_name as product_name,
            p.hsn as hsn_code
        FROM orders o
        LEFT JOIN order_items oi ON o.order_id = oi.order_id
        left JOIN users u ON o.user_id = u.user_id
        left JOIN addresses a ON o.shippingad_id = a.id
        LEFT JOIN item_master p ON oi.product_id = p.id        
        WHERE o.order_id = ?
    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $orderData = $result->fetch_assoc();

        // Format the response
        $response = [
            'success' => true,
            'shipment' => [
                'order_id' => $orderData['order_id'],
                'customer_name' => $orderData['customer_name'],
                'shipping_address' => $orderData['shipping_address'],
                'shipping_phone' => $orderData['shipping_phone'],
                'shipping_city' => $orderData['shipping_city'],
                'shipping_state' => $orderData['shipping_state'],
                'shipping_pincode' => $orderData['shipping_pincode'],
                'order_date' => $orderData['order_date'],
                'total_amount' => $orderData['total_amount'],
                'payment_mode' => $orderData['payment_mode'],
                'tracking_no' => $orderData['tracking_no'] ?? '',
                'waybill_no' => $orderData['waybill_no'] ?? '',
                'shipping_mode' => $orderData['shipping_mode'] ?? 'Surface',
                'weight' => $orderData['weight'] ?? '',
                'dimensions' => $orderData['dimensions'] ?? '',
                'products' => [
                    [
                        'product_id' => $orderData['product_id'],
                        'product_name' => $orderData['product_name'],
                        'quantity' => $orderData['quantity'],
                        'hsn_code' => $orderData['hsn_code']
                    ]
                ]
            ]
        ];

        // If there are multiple products, fetch them all
        while ($row = $result->fetch_assoc()) {
            $response['shipment']['products'][] = [
                'product_id' => $row['product_id'],
                'product_name' => $row['product_name'],
                'quantity' => $row['quantity'],
                'hsn_code' => $row['hsn_code']
            ];
        }

        echo json_encode($response);
    } else {
        throw new Exception("Order not found");
    }

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>