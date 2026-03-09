<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: *");
header("Content-Type: application/json");

include 'db.php';

try {
    // Get POST data
    $id = $_POST['id'] ?? null;
    $vstatus = $_POST['vstatus'] ?? null;
    $msg = $_POST['msg'] ?? '';

    if (!$id || !$vstatus) {
        throw new Exception("Missing required fields");
    }

    $conn = new mysqli($host, $user, $password, $dbname);

    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    $sql = "UPDATE vendor SET vstatus = ?, msg = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $vstatus, $msg, $id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception("Database update failed");
    }

    $stmt->close();
    $conn->close();
    
} catch(Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>