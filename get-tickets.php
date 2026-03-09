<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

include 'db.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}
$u_id=$_REQUEST['user_id'] ?? null;
try {
    // fetch tickets ordered by opdate DESC
    $sql = "SELECT id, opdate, closingdate, ticketstatus FROM tickets WHERE user_id = ? ORDER BY opdate DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $u_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $tickets = [];

    if ($res && $res->num_rows > 0) {
        $stmtLatest = $conn->prepare("SELECT ID, tid, customer, vendor, dateandtime FROM conversation WHERE tid = ? ORDER BY dateandtime DESC LIMIT 1");
        while ($row = $res->fetch_assoc()) {
            $ticketId = intval($row['id']);
            $preview = '';
            $sender = null;
            $latestDate = $row['opdate'];

            if ($stmtLatest) {
                $stmtLatest->bind_param("i", $ticketId);
                $stmtLatest->execute();
                $r = $stmtLatest->get_result();
                if ($r && $r->num_rows > 0) {
                    $c = $r->fetch_assoc();
                    if (!empty($c['customer'])) {
                        $preview = $c['customer'];
                        $sender = 'customer';
                    } elseif (!empty($c['vendor'])) {
                        $preview = $c['vendor'];
                        $sender = 'vendor';
                    }
                    if (!empty($c['dateandtime'])) $latestDate = $c['dateandtime'];
                }
                $r->free();
            }

            $tickets[] = [
                'ticket_id'    => $ticketId,
                'opdate'       => $row['opdate'],
                'closingdate'  => $row['closingdate'],
                'ticketstatus' => $row['ticketstatus'],
                'preview'      => mb_strimwidth($preview, 0, 200, '...'),
                'latest_by'    => $latestDate,
                // friendly fields used by frontend
                'subject'      => "Ticket #{$ticketId}"
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