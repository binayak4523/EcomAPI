<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include("db.php");

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (!isset($_GET['user_id'])) {
            throw new Exception('User ID is required');
        }

        $user_id = $_GET['user_id'];
        
        $stmt = $pdo->prepare("
            SELECT 
                id,
                UserID,
                name,
                phone_no,
                pin as postal_code,
                address1 as street_address,
                address2,
                landmark,
                city,
                state,
                country,
                default_address as is_default
            FROM addresses 
            WHERE UserID = ?
            ORDER BY is_default DESC, id DESC
        ");
        
        $stmt->execute([$user_id]);
        $addresses = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Transform the data to match both components' expectations
        $formattedAddresses = array_map(function($address) {
            return [
                'id' => $address['id'],
                'User_ID' => $address['UserID'],
                'name' => $address['name'],
                'full_name' => $address['name'], // For Checkout.js
                'phone_no' => $address['phone_no'],
                'phone' => $address['phone_no'], // For addresslist.js
                'postal_code' => $address['postal_code'],
                'pin' => $address['postal_code'], // For AddressForm.js
                'street_address' => $address['street_address'],
                'address' => $address['street_address'] . 
                            ($address['address2'] ? ', ' . $address['address2'] : ''), // For Checkout.js
                'address1' => $address['street_address'],
                'address2' => $address['address2'],
                'landmark' => $address['landmark'],
                'city' => $address['city'],
                'state' => $address['state'],
                'country' => $address['country'] ?: 'India',
                'is_default' => (bool)$address['is_default']
            ];
        }, $addresses);

        echo json_encode($formattedAddresses);

    } else {
        throw new Exception('Method not allowed');
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
}