<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

include 'db.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

$input = json_decode(file_get_contents('php://input'), true);
$ticketId = isset($input['ticketId']) ? intval($input['ticketId']) : null;
$message = isset($input['message']) ? trim($input['message']) : null;
$sender = isset($input['sender']) ? strtolower(trim($input['sender'])) : 'customer';

if (!$ticketId || empty($message)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ticketId and message are required']);
    exit;
}

$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

try {
    $now = date('Y-m-d H:i:s');
    if ($sender === 'vendor' || $sender === 'admin') {
        // place into vendor column
        $stmt = $conn->prepare("INSERT INTO conversation (tid, customer, vendor, dateandtime) VALUES (?, ?, ?, ?)");
        $customerNull = null;
        $stmt->bind_param('isss', $ticketId, $customerNull, $message, $now);
    } else {
        // default to customer
        $stmt = $conn->prepare("INSERT INTO conversation (tid, customer, vendor, dateandtime) VALUES (?, ?, ?, ?)");
        $vendorNull = null;
        $stmt->bind_param('isss', $ticketId, $message, $vendorNull, $now);
    }

    if (!$stmt->execute()) throw new Exception($stmt->error);
    $stmt->close();

    // optionally update ticket status and opdate/closingdate if needed
    // e.g. set opdate to first message date and ticketstatus 'open' - skipping updates here

    echo json_encode(['success' => true, 'inserted_id' => $conn->insert_id]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} finally {
    $conn->close();
}
?>