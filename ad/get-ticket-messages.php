<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

include 'db.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

$ticketId = null;
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $ticketId = isset($_GET['ticketId']) ? intval($_GET['ticketId']) : null;
} else {
    $input = json_decode(file_get_contents('php://input'), true);
    $ticketId = isset($input['ticketId']) ? intval($input['ticketId']) : null;
}

if (empty($ticketId)) {
    http_response_code(400);
    echo json_encode(['error' => 'ticketId is required']);
    exit;
}

$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT ID, tid, customer, vendor, dateandtime FROM conversation WHERE tid = ? ORDER BY dateandtime ASC");
    if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);

    $stmt->bind_param("i", $ticketId);
    $stmt->execute();
    $res = $stmt->get_result();

    $messages = [];
    while ($row = $res->fetch_assoc()) {
        // determine sender and message content
        $msg = '';
        $sender = '';
        if (!is_null($row['customer']) && trim($row['customer']) !== '') {
            $msg = $row['customer'];
            $sender = 'customer';
        } elseif (!is_null($row['vendor']) && trim($row['vendor']) !== '') {
            $msg = $row['vendor'];
            $sender = 'vendor';
        } else {
            // empty row, skip
            continue;
        }

        $messages[] = [
            'id' => $row['ID'],
            'ticket_id' => $row['tid'],
            'sender' => $sender,
            'message' => $msg,
            'created_at' => $row['dateandtime']
        ];
    }

    echo json_encode(['messages' => $messages]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} finally {
    $conn->close();
}
?>