<?php
include("db.php");

$conn = new mysqli($host, $user, $password, $dbname);

// check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// read values from request (sent from VB6)
$orderId     = isset($_POST['orderid']) ? intval($_POST['orderid']) : 0;
$invoiceNo   = isset($_POST['invoiceno']) ? $conn->real_escape_string($_POST['invoiceno']) : '';
$invoiceDate = isset($_POST['invoicedate']) ? $conn->real_escape_string($_POST['invoicedate']) : '';

// validate
if ($orderId > 0 && $invoiceNo != '' && $invoiceDate != '') {
    $mysqldate = convertToMySQLDateTime($invoiceDate);
    $sql = "UPDATE orders 
            SET invoiceno = '$invoiceNo', invoicedate = '$mysqldate',order_status='Invoiced' 
            WHERE order_id = $orderId";

    if ($conn->query($sql) === TRUE) {
        echo "SUCCESS";
    } else {
        echo "ERROR: " . $conn->error;
    }
} else {
    echo "INVALID INPUT";
}

$conn->close();
function convertToMySQLDateTime($inputDate) {
    // Validate input format (dd/mm/yyyy)
    if (!preg_match("/^(\d{2})\/(\d{2})\/(\d{4})$/", $inputDate, $matches)) {
        return false; // Invalid date format
    }

    $day = $matches[1];
    $month = $matches[2];
    $year = $matches[3];

    // Check if date is valid
    if (!checkdate($month, $day, $year)) {
        return false; // Invalid date
    }

    // Get current time
    $currentTime = date('H:i:s');
    
    // Create MySQL datetime format
    $mysqlDateTime = "$year-$month-$day $currentTime";
    
    return $mysqlDateTime;
}
?>
