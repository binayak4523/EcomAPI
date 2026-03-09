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

if (!$ticketId || $message === null || $message === '') {
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

    // Admin/vendor messages should be stored in vendor column; customer messages in customer column
    if ($sender === 'vendor' || $sender === 'admin') {
        $stmt = $conn->prepare("INSERT INTO conversation (tid, customer, vendor, dateandtime) VALUES (?, NULL, ?, ?)");
        if (!$stmt) throw new Exception($conn->error);
        $stmt->bind_param('iss', $ticketId, $message, $now);
    } else {
        $stmt = $conn->prepare("INSERT INTO conversation (tid, customer, vendor, dateandtime) VALUES (?, ?, NULL, ?)");
        if (!$stmt) throw new Exception($conn->error);
        $stmt->bind_param('iss', $ticketId, $message, $now);
    }

    if (!$stmt->execute()) throw new Exception($stmt->error);
    $insertId = $stmt->insert_id;
    $stmt->close();

    // Optionally update ticket opdate or status; example: set ticketstatus = 'open' and update opdate
    $upd = $conn->prepare("UPDATE tickets SET opdate = ? , ticketstatus = ? WHERE id = ?");
    if ($upd) {
        $status = 'open';
        $upd->bind_param('ssi', $now, $status, $ticketId);
        $upd->execute();
        $upd->close();
    }

    echo json_encode(['success' => true, 'inserted_id' => $insertId]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} finally {
    $conn->close();
}
?>