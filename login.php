<?php
// 1. Handle OPTIONS requests for CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
    http_response_code(200);
    exit();
}

// 2. Set headers for the POST request
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// Turn off PHP warnings and notices for JSON output
error_reporting(E_ERROR);
ini_set('display_errors', 0);

// 3. Include the database connection parameters from db.php
include("db.php");

// 4. Create a new MySQLi connection
$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed: " . $conn->connect_error]);
    exit();
}

// 5. Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method Not Allowed. Use POST."]);
    exit();
}

// 6. Read JSON input
$data = json_decode(file_get_contents("php://input"), true);

// Check if this is a user info only request
$get_user_only = isset($data['get_user_only']) ? $data['get_user_only'] : false;

// 7. Validate required fields
if (!$data || !isset($data['identifier'])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing identifier field."]);
    exit();
}

// For regular login, password is required
if (!$get_user_only && !isset($data['password'])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing password field."]);
    exit();
}

$identifier = $conn->real_escape_string($data['identifier']);
$password = isset($data['password']) ? $data['password'] : null;

// Query the users table
$sql = "SELECT * FROM users WHERE (email = ? OR mobile = ?)";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(["error" => "Failed to prepare statement: " . $conn->error]);
    exit();
}

$stmt->bind_param("ss", $identifier, $identifier);

// Execute the query and check the results
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows == 0) {
    http_response_code(401);
    echo json_encode(["error" => "User not found."]);
    exit();
}
$user = $result->fetch_assoc();
$stmt->close();

// If this is just a user info request, return the user data without password verification
if ($get_user_only) {
    echo json_encode([
        "success" => true, 
        "user" => [
            "id" => $user['user_id'], // Use user_id field from the database
            "name" => $user['name'],
            "email" => $user['email'],
            "identifier" => $identifier
        ]
    ]);
    $conn->close();
    exit();
}

// 10. Verify the password
if ($password != $user['password']) {
    http_response_code(401);
    echo json_encode(["error" => "Invalid credentials."]);
    exit();
}

// 11. Successful login: start a session and store user data if needed
session_start();
$_SESSION['user'] = $user;

// 12. Return a success response with user data
echo json_encode([
    "success" => true, 
    "message" => "Login successful.",
    "user" => [
        "id" => $user['user_id'], // Use user_id field from the database
        "name" => $user['name'],
        "email" => $user['email'],
        "identifier" => $identifier
    ]
]);

$conn->close();
?>