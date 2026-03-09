<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=UTF-8');
require_once 'db.php';

$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}

$sql = "SELECT id, StateName, STCode FROM states ORDER BY StateName ASC";
$result = $conn->query($sql);

$states = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $states[] = $row;
    }
    echo json_encode(['success' => true, 'states' => $states]);
} else {
    echo json_encode(['success' => false, 'message' => 'Query failed.']);
}

$conn->close();