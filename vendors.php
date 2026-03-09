<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include("db.php");

$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    die(json_encode(["error" => "Connection failed: " . $conn->connect_error]));
}

$sql = "SELECT ID, Name, ContactNo, Email, NoOfStores, Vaddress, Bank_AC_No, IFSC, vstatus, msg FROM vendor";
$result = $conn->query($sql);

$vendors = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $vendors[] = [
            "id" => $row["ID"],
            "name" => $row["Name"],
            "contact_no" => $row["ContactNo"],
            "email" => $row["Email"],
            "no_of_stores" => $row["NoOfStores"],
            "address" => $row["Vaddress"],
            "bank_account" => $row["Bank_AC_No"],
            "ifsc" => $row["IFSC"],
            "status" => $row["vstatus"],
            "message" => $row["msg"]
        ];
    }
}

echo json_encode($vendors);

$conn->close();