<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=UTF-8');

include("db.php");

// Add error logging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Get POST data
$data = json_decode(file_get_contents("php://input"), true);
$email = $data['email'] ?? '';
$loginPassword = $data['password'] ?? '';

// Use database credentials from db.php
$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Database connection failed"
    ]);
    exit();
}

// Add debug logging
error_log("Email: " . $email);
error_log("Password: " . $loginPassword);

// Modified query to check email with LOWER() function
$stmt = $conn->prepare("SELECT ID, Name, ContactNo, Email, NoOfStores, Vaddress, Bank_AC_No, IFSC FROM vendor WHERE LOWER(Email) = LOWER(?)");
$stmt->bind_param("s", $email);

try {
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $vendor = $result->fetch_assoc();
        
        // Check plain text password
        $checkPwdStmt = $conn->prepare("SELECT pwd FROM vendor WHERE LOWER(Email) = LOWER(?)");
        $checkPwdStmt->bind_param("s", $email);
        $checkPwdStmt->execute();
        $pwdResult = $checkPwdStmt->get_result();
        $pwdRow = $pwdResult->fetch_assoc();
        
        // Add debug output
        error_log("Input Email: " . $email);
        error_log("DB Email: " . $vendor['Email']);
        error_log("Input Password: " . $loginPassword);
        error_log("DB Password: " . $pwdRow['pwd']);
        
        if ($loginPassword === $pwdRow['pwd']) {
            echo json_encode([
                "success" => true,
                "message" => "Login successful",
                "vendor" => [
                    "id" => $vendor['ID'],
                    "name" => $vendor['Name'],
                    "contactNo" => $vendor['ContactNo'],
                    "email" => $vendor['Email'],
                    "noOfStores" => $vendor['NoOfStores'],
                    "address" => $vendor['Vaddress'],
                    "bankAccountNo" => $vendor['Bank_AC_No'],
                    "ifsc" => $vendor['IFSC']
                ]
            ]);
        } else {
            echo json_encode([
                "success" => false,
                "message" => "Invalid password"
            ]);
        }
    } else {
        echo json_encode([
            "success" => false,
            "message" => "Email not found"
        ]);
    }
} catch (Exception $e) {
    error_log("Login failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "An error occurred during login"
    ]);
}

$stmt->close();
$conn->close();