<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

error_log("=== NEW REQUEST ===");
error_log("Method: " . $_SERVER['REQUEST_METHOD']);
error_log("URI: " . $_SERVER['REQUEST_URI']);
error_log("Headers: " . print_r(getallheaders(), true));

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include("db.php");

// Get JSON input
$jsonInput = file_get_contents('php://input');
error_log("JSON Input received: " . $jsonInput);

$input = json_decode($jsonInput, true);

// Check for JSON decode errors
if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("JSON decode error: " . json_last_error_msg());
    http_response_code(400);
    echo json_encode(["error" => "Invalid JSON: " . json_last_error_msg()]); 
    exit();
}

if (!$input) {
    http_response_code(400);
    echo json_encode(["error" => "No JSON data received or invalid JSON format"]); 
    exit();
}

error_log("Decoded input: " . print_r($input, true));

// Validate required fields
$required_fields = ['cat_id', 'sub_id', 'name'];
$missing_fields = [];

foreach ($required_fields as $field) {
    if (!isset($input[$field])) {
        $missing_fields[] = $field;
    }
}

if (!empty($missing_fields)) {
    http_response_code(400);
    echo json_encode([
        "error" => "Missing required fields", 
        "missing" => $missing_fields,
        "received" => array_keys($input)
    ]); 
    exit();
}

// Extract and sanitize input values
$cat_id = (int) $input['cat_id'];
$sub_id = (int) $input['sub_id'];
$name = trim($input['name']);
$required = isset($input['required']) ? (int) $input['required'] : 0;

error_log("Processing: cat_id=$cat_id, sub_id=$sub_id, name=$name, required=$required");

// Additional validation
if (empty($name)) {
    http_response_code(400);
    echo json_encode(["error" => "Name cannot be empty"]);
    exit();
}

if ($cat_id <= 0 || $sub_id <= 0) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid category or sub-category ID"]);
    exit();
}

// Database connection with error checking
if (!isset($host) || !isset($user) || !isset($password) || !isset($dbname)) {
    http_response_code(500);
    echo json_encode(["error" => "Database configuration missing"]);
    exit();
}

$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    http_response_code(500);
    echo json_encode(["error" => "Connection failed: " . $conn->connect_error]);
    exit();
}

// Check if table exists
$table_check = $conn->query("SHOW TABLES LIKE 'details_master'");
if ($table_check->num_rows == 0) {
    http_response_code(500);
    echo json_encode(["error" => "Table 'details_master' does not exist"]);
    $conn->close();
    exit();
}

// Prepare and execute statement
$sql = "INSERT INTO details_master (cat_id, sub_id, name, required) VALUES (?, ?, ?, ?)";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    error_log("Prepare failed: " . $conn->error);
    http_response_code(500);
    echo json_encode(["error" => "Prepare failed: " . $conn->error]);
    $conn->close();
    exit();
}

$stmt->bind_param("iisi", $cat_id, $sub_id, $name, $required);

if ($stmt->execute()) {
    echo json_encode([
        "success" => "Product details added successfully.",
        "id" => $stmt->insert_id,
        "data" => [
            "cat_id" => $cat_id,
            "sub_id" => $sub_id,
            "name" => $name,
            "required" => $required
        ]
    ]);
} else {
    error_log("Execute failed: " . $stmt->error);
    http_response_code(500);
    echo json_encode(["error" => "Failed to add product details: " . $stmt->error]);
}

$stmt->close();
$conn->close();
?>
