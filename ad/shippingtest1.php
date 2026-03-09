<?php

$url = 'https://staging-express.delhivery.com/api/cmu/create.json';
$token = 'fc613ea049f63609e6be650bb73e0a63e016b11c';

$data = [
    "shipments" => [
        [
            'client' => '2019ab-CTKartRetailIndia-do',
            "name" => "Binayak Panigraghi",
            "add" => "D2/703, Jambhul, Vadgaon",
            "pin" => 110042,
            "city" => "Pune",
            "state" => "Maharashtra",
            "country" => "India",
            "phone" => "9853818731",
            "order" => "01",
            "payment_mode" => "COD",
            "return_pin" => "",
            "return_city" => "",
            "return_phone" => "",
            "return_add" => "",
            "return_state" => "",
            "return_country" => "",
            "products_desc" => "XL Black Cotton T-Shirt",
            "hsn_code" => "61051090",
            "cod_amount" => 200,
            "order_date" => "14/10/2025",
            "total_amount" => 200,
            "seller_add" => "Plot no - 10-1-GH-565-C/50 , BHIKARI BAL BUILDING , ROAD / STREET - PARIS BAKERY LANE , LOCALITY - CDA SECTOR - 10 ",
            "seller_name" => "CT Kart Retail India",
            "seller_inv" => "01",
            "quantity" => "1",
            "waybill" => "000",
            "shipment_width" => 100,
            "shipment_height" => 100,
            "weight" => 1,
            "shipping_mode" => "Surface",
            "address_type" => ""
        ]
    ],
    "pickup_location" => [
        "name" => "Default"
    ]
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, 'format=json&data=' . json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Token ' . $token,
    'Accept: application/json',
    'Content-Type: application/json'
]);

try {
    $response = curl_exec($ch);
    if($error = curl_error($ch)) {
        throw new Exception($error);
    }
    echo $response;
} catch (Exception $ex) {
    echo $ex->getMessage();
} finally {
    curl_close($ch);
}