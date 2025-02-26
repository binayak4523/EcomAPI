<?php
// Set CORS and JSON headers
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=UTF-8');

// Database connection credentials
include("db.php");

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed"]);
    exit();
}

$requestUri = $_SERVER['REQUEST_URI'];
$requestMethod = $_SERVER['REQUEST_METHOD'];

// If the request is GET /api/service-requests
if ($requestUri === '/api/service-requests' && $requestMethod === 'GET') {
    // Check if there's a contact_no query param
    if (isset($_GET['contact_no']) && !empty($_GET['contact_no'])) {
        $contactNo = $_GET['contact_no'];
        $sql = "SELECT * FROM service_requests WHERE contact_no = ? ORDER BY created_at DESC";
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$contactNo]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($results);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(["error" => "Query failed"]);
        }
    } else {
        // No contact_no param => return all requests
        $sql = "SELECT * FROM service_requests ORDER BY created_at DESC";
        try {
            $stmt = $pdo->query($sql);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($results);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(["error" => "Query failed"]);
        }
    }
}
// Handle other routes or POST for new requests, etc. (not shown)
else {
    http_response_code(404);
    echo json_encode(["error" => "Route Not Found"]);
}
?>
