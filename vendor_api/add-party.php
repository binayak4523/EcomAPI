<?php
// add-party.php
// Add a new party/supplier for a vendor
// Creates both LedgerMaster entry (for accounting) and party information

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include 'db.php';

// Create connection
$conn = new mysqli($host, $user, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Connection failed: " . $conn->connect_error]);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields (using new field names)
$required_fields = ['Party', 'Email', 'Phone', 'Address', 'VendorID'];
$missing_fields = [];

foreach ($required_fields as $field) {
    if (empty($input[$field])) {
        $missing_fields[] = $field;
    }
}

if (!empty($missing_fields)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "error" => "Missing required fields: " . implode(', ', $missing_fields)
    ]);
    exit;
}

// Sanitize and validate input
$party_name = trim($input['Party']);
$email = trim($input['Email']);
$phone = trim($input['Phone']);
$address = trim($input['Address']);
$address2 = trim($input['Address2'] ?? '');
$city = trim($input['City'] ?? '');
$state = trim($input['State'] ?? '');
$pin = trim($input['Pin'] ?? '');
$zone = trim($input['Zone'] ?? '');
$mobile = trim($input['Mobile'] ?? '');
$fax = trim($input['Fax'] ?? '');
$gst_in = trim($input['GSTIN'] ?? '');
$credit_limit = floatval($input['CrLimit'] ?? 0);
$due_days = (int)($input['Duedays'] ?? 30);
$discount = floatval($input['Discount'] ?? 0);
$state_code = trim($input['StateCode'] ?? '');
$vendor_id = (int)$input['VendorID'];

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Invalid email format"]);
    exit;
}

// Validate phone format (basic check)
if (!preg_match('/^[0-9\-\+\(\)\s]{7,}$/', $phone)) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Invalid phone number format"]);
    exit;
}

// Check if party already exists in ledgerMaster
$check_sql = "SELECT AccID FROM ledgermaster WHERE TIN = ? AND CompanyId = ? and VendorID = ? LIMIT 1";
$check_stmt = $conn->prepare($check_sql);

if (!$check_stmt) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Database error: " . $conn->error]);
    exit;
}

$check_stmt->bind_param("sii", $email, $vendor_id, $vendor_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows > 0) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Party with this email already exists"]);
    $check_stmt->close();
    $conn->close();
    exit;
}
$check_stmt->close();

// Start transaction
$conn->begin_transaction();

try {
    // Step 1: Get GroupID and GroupNature for 'Sundry Creditor'
    $group_sql = "SELECT GroupID, groupnature FROM groups WHERE GroupName='Sundry Creditor' LIMIT 1";
    $group_result = $conn->query($group_sql);
    
    if (!$group_result || $group_result->num_rows === 0) {
        throw new Exception("Sundry Creditor group not found");
    }
    
    $group_row = $group_result->fetch_assoc();
    $group_id = $group_row['GroupID'];
    $group_nature = $group_row['groupnature'];
    
    // Step 2: Get next AccID from LedgerMaster
    $max_acc_sql = "SELECT MAX(AccID) as max_acc FROM ledgermaster";
    $max_acc_result = $conn->query($max_acc_sql);
    $max_acc_row = $max_acc_result->fetch_assoc();
    $next_acc_id = ($max_acc_row['max_acc'] !== null) ? ($max_acc_row['max_acc'] + 1) : 1;
    
    // Step 3: Insert into LedgerMaster (for accounting)
    $ledger_sql = "
        INSERT INTO ledgermaster (
            AccID, AccName, GroupID, Dr, Cr, TransactionType,
            OBalance, BalanceType, Groupname, Address1, Address2, TIN, Phone,
            CompanyId, StateCode, VendorID
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";
    
    $ledger_stmt = $conn->prepare($ledger_sql);
    if (!$ledger_stmt) {
        throw new Exception("Database error: " . $conn->error);
    }
    
    // Set default values for fields not provided by frontend
    $dr_debit = '-';
    $cr_credit = '+';
    $balance_type = 'Cr';
    $groupname = 'Sundry Creditor';
    $company_id = $vendor_id; // Using vendor_id as CompanyId
    
    $ledger_stmt->bind_param(
        "isissssssssssiii",
        $next_acc_id,
        $party_name,
        $group_id,
        $dr_debit,
        $cr_credit,
        $group_nature,
        $credit_limit,
        $balance_type,
        $groupname,
        $address,
        $address2,
        $email,
        $phone,
        $company_id,
        $state_code,
        $vendor_id
    );
    
    if (!$ledger_stmt->execute()) {
        throw new Exception("Failed to create ledger entry: " . $ledger_stmt->error);
    }
    $ledger_stmt->close();
    
    // Commit transaction
    $conn->commit();
    
    http_response_code(201);
    echo json_encode([
        "success" => true,
        "message" => "Party added successfully",
        "acc_id" => $next_acc_id
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
}

$conn->close();
?>
