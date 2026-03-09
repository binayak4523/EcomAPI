<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Access-Control-Allow-Headers,Content-Type,Access-Control-Allow-Methods,Authorization,X-Requested-With');

include_once 'db.php';

try {
    $conn = new mysqli($host, $user, $password, $dbname);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }  

    // Get posted data
    $data = json_decode(file_get_contents("php://input"));

    if(!$data->id) {
        throw new Exception("Shipment ID is required");
    }

    // Prepare SQL
    $query = "UPDATE orders SET                                                 
                shipping_weight = ?,
                dimensions = ?,
                shipping_method = ?,
                tracking_no = ?,
                updated_at = NOW(),
                order_status = 'Processing',
                waybill_no = ?
              WHERE order_id = ?";

    $stmt = $conn->prepare($query);

    // Clean and bind data
    $stmt->bind_param("dssssi", $data->weight, $data->dimensions, $data->shipping_mode, $data->tracking_no, $data->waybill_no, $data->id);    
    if($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Shipment updated successfully'
        ]);
    } else {
        throw new Exception("Failed to update shipment");
    }

} catch(Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>