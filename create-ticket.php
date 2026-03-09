<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

include 'db.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

// read JSON body
$input = json_decode(file_get_contents('php://input'), true);
$subject = isset($input['subject']) ? trim($input['subject']) : null;
$message = isset($input['message']) ? trim($input['message']) : null;
$user_id = isset($input['user_id']) ? $input['user_id'] : null; // optional

if (empty($message)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Message is required']);
    exit;
}

$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'DB connection failed']);
    exit;
}

try {
    $now = date('Y-m-d H:i:s');
    
    if (empty($user_id)) {
        http_response_code(400);
        throw new Exception('User ID is required');
    }
    
    // Insert ticket with user_id
    $stmt = $conn->prepare("INSERT INTO tickets (user_id, opdate, ticketstatus) VALUES (?, ?, ?)");
    $status = 'open';
    $stmt->bind_param('iss', $user_id, $now, $status);
    if (!$stmt->execute()) throw new Exception($stmt->error);
    $ticketId = $stmt->insert_id;
    $stmt->close();

    // Insert initial message into conversation for customer only (tid, customer, dateandtime)
    $stmt2 = $conn->prepare("INSERT INTO conversation (tid, customer, dateandtime) VALUES (?, ?, ?)");
    if (!$stmt2) throw new Exception($conn->error);
    $stmt2->bind_param('iss', $ticketId, $message, $now);
    if (!$stmt2->execute()) throw new Exception($stmt2->error);
    $stmt2->close();

    echo json_encode(['success' => true, 'ticket_id' => $ticketId]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} finally {
    $conn->close();
}
?>