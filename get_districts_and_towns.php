<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=UTF-8');
require_once 'db.php';

$stateCode = $_GET['state_code'] ?? '';

if (!$stateCode) {
    echo json_encode(['success' => false, 'message' => 'State code is required.']);
    exit;
}

$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}

// Get unique districts for the state
$districts = [];
$townsByDistrict = [];

$districtSql = "SELECT DISTINCT District_Code, District_Name FROM stateanddist WHERE State_Code = ? ORDER BY District_Name ASC";
$stmt = $conn->prepare($districtSql);
$stmt->bind_param("i", $stateCode);
$stmt->execute();
$districtResult = $stmt->get_result();

while ($row = $districtResult->fetch_assoc()) {
    $districts[] = $row;
    // Get towns for each district
    $townSql = "SELECT id, Town_Name FROM stateanddist WHERE State_Code = ? AND District_Code = ? ORDER BY Town_Name ASC";
    $townStmt = $conn->prepare($townSql);
    $townStmt->bind_param("ii", $stateCode, $row['District_Code']);
    $townStmt->execute();
    $townResult = $townStmt->get_result();
    $towns = [];
    while ($townRow = $townResult->fetch_assoc()) {
        $towns[] = $townRow;
    }
    $townsByDistrict[$row['District_Code']] = $towns;
    $townStmt->close();
}

echo json_encode([
    'success' => true,
    'districts' => $districts,
    'townsByDistrict' => $townsByDistrict
]);

$stmt->close();
$conn->close();