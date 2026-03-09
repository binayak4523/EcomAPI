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

$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]);
    exit;
}

try {
    // fetch tickets
    $sql = "SELECT id, opdate, closingdate, ticketstatus FROM tickets ORDER BY opdate DESC";
    $res = $conn->query($sql);
    $tickets = [];

    if ($res && $res->num_rows > 0) {
        // prepare once
        $stmtLatest = $conn->prepare("SELECT customer, vendor, dateandtime FROM conversation WHERE tid = ? ORDER BY dateandtime DESC LIMIT 1");
        while ($row = $res->fetch_assoc()) {
            $ticketId = (int)$row['id'];
            $preview = '';
            $sender = '';
            $created_at = $row['opdate'];

            if ($stmtLatest) {
                $stmtLatest->bind_param("i", $ticketId);
                $stmtLatest->execute();
                $r = $stmtLatest->get_result();
                if ($r && $r->num_rows > 0) {
                    $c = $r->fetch_assoc();
                    if (!is_null($c['customer']) && trim($c['customer']) !== '') {
                        $preview = $c['customer'];
                        $sender = 'customer';
                    } elseif (!is_null($c['vendor']) && trim($c['vendor']) !== '') {
                        $preview = $c['vendor'];
                        $sender = 'vendor';
                    }
                    if (!empty($c['dateandtime'])) $created_at = $c['dateandtime'];
                }
                if ($r) $r->free();
            }

            $tickets[] = [
                'ticket_id'    => $ticketId,
                'opdate'       => $row['opdate'],
                'closingdate'  => $row['closingdate'],
                'ticketstatus' => $row['ticketstatus'],
                // friendly fields used by frontend
                'subject'      => "Ticket #{$ticketId}",
                'customer_name'=> ($sender === 'customer' ? 'Customer' : 'Vendor'),
                'created_at'   => $created_at,
                'preview'      => mb_strimwidth($preview, 0, 200, '...')
            ];
        }
        if ($stmtLatest) $stmtLatest->close();
    }

    echo json_encode(['tickets' => $tickets]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} finally {
    $conn->close();
}
?>