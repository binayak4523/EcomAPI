<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include("db.php");

$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    die(json_encode(["error" => "Connection failed: " . $conn->connect_error]));
}

$sql = "SELECT ID, VendorID, Store_Name, GSTNO FROM store";
$result = $conn->query($sql);

$stores = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $stores[] = [
            "id" => $row["ID"],
            "vendor_id" => $row["VendorID"],
            "name" => $row["Store_Name"],
            "gst_no" => $row["GSTNO"]
        ];
    }
}

echo json_encode($stores);

$conn->close();