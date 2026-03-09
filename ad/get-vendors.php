<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: *");
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=UTF-8');

include 'db.php';

try {
    $conn = new mysqli($host, $user, $password, $dbname);

    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    // Updated SQL query to include all relevant fields except password
    $sql = "SELECT id, name, ContactNo, Email, NoOfStores, Vaddress, Bank_AC_No, IFSC, vstatus, msg FROM vendor";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $vendors = array();
        while($row = $result->fetch_assoc()) {
            $vendors[] = array(
                'id' => $row['id'],
                'name' => $row['name'],
                'contact_no' => $row['ContactNo'],
                'email' => $row['Email'],
                'no_of_stores' => $row['NoOfStores'],
                'address' => $row['Vaddress'],
                'bank_ac_no' => $row['Bank_AC_No'],
                'ifsc' => $row['IFSC'],
                'vstatus' => $row['vstatus'],
                'msg' => $row['msg']
            );
        }
        echo json_encode($vendors);
    } else {
        echo json_encode([]);
    }
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

if (isset($conn)) {
    $conn->close();
}