<?php
// Allow cross-origin requests
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Include database connection
include("db.php");

// Get the posted data
$data = json_decode(file_get_contents("php://input"), true);

// Check if data is valid
if (!isset($data) || empty($data)) {
    echo json_encode(["success" => false, "error" => "No data received"]);
    exit();
}

// Create database connection
$conn = new mysqli($host, $user, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    echo json_encode(["success" => false, "error" => "Connection failed: " . $conn->connect_error]);
    exit();
}

// Get common data
$account_type = $conn->real_escape_string($data['account_type']);
$name = $conn->real_escape_string($data['name'] ?? '');
$email = $conn->real_escape_string($data['email'] ?? '');
$password = $conn->real_escape_string($data['password'] ?? '');

// Insert into users table

if ($account_type === 'customer') {
    $password = $conn->real_escape_string($data['password']); // Consider using password_hash()
    // Check if email already exists in users table
    $email = $conn->real_escape_string($data['email']);
    $mobile = $conn->real_escape_string($data['mobile']);
    $address = $conn->real_escape_string($data['address'] ?? '');    
    $check_email_sql = "SELECT * FROM users WHERE email = ? and mobile = ?";
    $check_stmt = $conn->prepare($check_email_sql);
    $check_stmt->bind_param("ss", $email, $mobile);
    $check_stmt->execute();
    $result = $check_stmt->get_result();

    if ($result->num_rows > 0) {
        echo json_encode(["success" => false, "error" => "Email/Mobile already registered"]);
        $check_stmt->close();
        $conn->close();
        exit();
    }
    $check_stmt->close();
    // Insert user into users table
    $insert_user_sql = "INSERT INTO users (name, address,email, mobile, password) VALUES (?, ?, ?, ?,?)";
    $user_stmt = $conn->prepare($insert_user_sql);
    $user_stmt->bind_param("sssss", $name, $address, $email, $mobile, $password);
    if (!$user_stmt->execute()) {
        echo json_encode(["success" => false, "error" => "Error inserting user: " . $user_stmt->error]);
        $user_stmt->close();
        $conn->close();
        exit();
    }
    $user_id = $user_stmt->insert_id;
    $user_stmt->close();
    // Address related data
    $country = $conn->real_escape_string($data['country'] ?? '');
    $phone_no = $conn->real_escape_string($data['mobile'] ?? '0');
    $pin = $conn->real_escape_string($data['pin'] ?? '0');
    $address1 = $conn->real_escape_string($data['address'] ?? '');
    $address2 = $conn->real_escape_string($data['address2'] ?? '');
    $landmark = $conn->real_escape_string($data['landmark'] ?? '');
    $city = $conn->real_escape_string($data['city'] ?? '');
    $state = $conn->real_escape_string($data['state'] ?? '');
    $state_code = intval($data['state_code'] ?? 0);
    $default_address = 'y';

    $insert_address_sql = "INSERT INTO addresses 
        (UserID, country, name, phone_no, pin, address1, address2, landmark, city, state, default_address, state_code)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $address_stmt = $conn->prepare($insert_address_sql);
    $address_stmt->bind_param(
        "issssssssssi",
        $user_id, $country, $name, $phone_no, $pin,
        $address1, $address2, $landmark, $city,
        $state, $default_address, $state_code
    );

    if ($address_stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Customer registered with address"]);
    } else {
        echo json_encode(["success" => false, "error" => "User saved but address failed: " . $address_stmt->error]);
    }

    $address_stmt->close();
    // Insert into ledgermaster table for customer (debtor) ledger entry
    // Get the maximum AccID from ledgermaster
    $max_accid_sql = "SELECT MAX(AccID) as maxAccID FROM ledgermaster";
    $maxAccID_result = $conn->query($max_accid_sql);
    $maxAccID_row = $maxAccID_result->fetch_assoc();
    $DrAccID = intval($maxAccID_row['maxAccID']) + 1;

    $ledger_group_id = 17; // GroupID for Sundry Debtors
    $dr_symbol = '+'; // Debit symbol
    $cr_symbol = '-'; // Credit symbol
    $transaction_type = 'Asset'; // TransactionType
    $opening_balance = 0; // OBalance
    $balance_type = 'Dr'; // BalanceType
    $tin = $conn->real_escape_string($data['tin'] ?? 'NULL'); // Tax Identification Number
    $from_dt = date('d/m/Y'); // Current date
    $to_dt = '31/12/2099'; // Closing date (far future)
    $dr_op = 0; // Opening balance debit
    $cr_op = 0; // Opening balance credit
    
    $insert_ledger_sql = "INSERT INTO ledgermaster (AccID,AccName, GroupID, Dr, Cr, TransactionType, OBalance, BalanceType, Groupname, From_Dt, To_Dt, Dr_Op, Cr_Op, Address1, Address2, TIN, StateCode, userid) 
                          VALUES (?,?, ?, ?, ?, ?, ?, ?, 'Sundry Debtor', ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $ledger_stmt = $conn->prepare($insert_ledger_sql);    
    $ledger_stmt->bind_param("isssssisssiissssi", $DrAccID, $name, $ledger_group_id, $dr_symbol, $cr_symbol, $transaction_type, $opening_balance, $balance_type, $from_dt, $to_dt, $dr_op, $cr_op, $address1, $address2, $tin, $state_code, $user_id);
    if (!$ledger_stmt->execute()) {
        echo json_encode(["success" => false, "error" => "Failed to insert ledger account: " . $ledger_stmt->error]);
        $ledger_stmt->close();
        $conn->close();
        exit();
    }
    $ledger_stmt->close();  


} elseif ($account_type === 'vendor') {
    // Vendor specific data
    $email = $conn->real_escape_string($data['email'] ?? '');
    $password = $conn->real_escape_string($data['password'] ?? '');
    $contactNo = $conn->real_escape_string($data['mobile'] ?? '');
    $noOfStores = intval($data['no_of_stores'] ?? 1);
    $vaddress = $conn->real_escape_string($data['vaddress'] ?? '');
    $bank_ac_no = $conn->real_escape_string($data['bank_ac_no'] ?? '');
    $ifsc = $conn->real_escape_string($data['ifsc'] ?? '');
    $vstatus = 'Pending Review';
    $msg = $conn->real_escape_string($data['msg'] ?? '');

    // Check if vendor email already exists
    $check_vendor_sql = "SELECT * FROM vendor WHERE Email = ?";
    $check_vendor_stmt = $conn->prepare($check_vendor_sql);
    $check_vendor_stmt->bind_param("s", $email);
    $check_vendor_stmt->execute();
    $vendor_result = $check_vendor_stmt->get_result();

    if ($vendor_result->num_rows > 0) {
        echo json_encode(["success" => false, "error" => "Vendor email already registered"]);
        $check_vendor_stmt->close();
        $conn->close();
        exit();
    }
    $check_vendor_stmt->close();

    // Insert vendor data
    $insert_vendor_sql = "INSERT INTO vendor 
        (Name, ContactNo, Email, pwd, NoOfStores, Vaddress, Bank_AC_No, IFSC, vstatus, msg)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $vendor_stmt = $conn->prepare($insert_vendor_sql);
    $vendor_stmt->bind_param(
        "ssssisssss",
        $name, $contactNo, $email, $password,
        $noOfStores, $vaddress, $bank_ac_no,
        $ifsc, $vstatus, $msg
    );

    if (!$vendor_stmt->execute()) {
        echo json_encode(["success" => false, "error" => "Vendor registration failed: " . $vendor_stmt->error]);
        $vendor_stmt->close();
        $conn->close();
        exit();
    }

    $vendor_id = $vendor_stmt->insert_id;
    $vendor_stmt->close();

    // Create default ledger accounts for the vendor
    $ledger_accounts = [
        [1, 'SALES ACCOUNT', 20, '+', '-', 'Income', 0.0000, 'Cr', 'Sales Account', '01/01/2025', '09/03/2025', 2000.0000, 0.0000, '', '', '', ''],
        [2, 'PURCHASE ACCOUNT', 22, '+', '-', 'Expences', 0.0000, 'Dr', 'Purchase Account', '01/01/2025', '09/03/2025', 2000.0000, 0.0000, '', '', '', ''],
        [3, 'VAT TAX ACCOUNT', 8, '+', '-', 'Liabilites', 0.0000, 'Cr', 'Duties & Taxes', '01/01/2025', '09/03/2025', 2000.0000, 0.0000, '', '', '', ''],
        [4, 'ENTRY TAX ACCOUNT', 8, '+', '-', 'Liabilites', 0.0000, 'Dr', 'Duties & Taxes', '01/01/2025', '09/03/2025', 2000.0000, 0.0000, '', '', '', ''],
        [6, 'FREIGHT, ENTRY TAX & MAJURI A/C', 24, '+', '-', 'Expences', 0.0000, 'Dr', 'Direct Expenses', '01/01/2025', '09/03/2025', 2000.0000, 0.0000, '', '', '', ''],
        [7, 'OFFICE STATIONERY', 28, '+', '-', 'Expences', 0.0000, 'Dr', 'INDIRECT EXPENSES', '01/01/2025', '09/03/2025', 2000.0000, 0.0000, '', '', '', ''],
        [10, 'CASH ACCOUNT', 15, '+', '-', 'Asset', 0.0000, 'Dr', 'Cash-In-Hand', '01/01/2025', '09/03/2025', 2000.0000, 0.0000, '', '', '', ''],
        [1027, 'SGST', 8, '-', '+', 'Liabilites', 0.0000, 'Dr', 'Duties & Taxes', '01/01/2025', '09/03/2025', 2000.0000, 0.0000, '', '', null, null],
        [1028, 'CGST', 8, '-', '+', 'Liabilites', 0.0000, 'Dr', 'Duties & Taxes', '01/01/2025', '09/03/2025', 2000.0000, 0.0000, '', '', null, null],
        [1029, 'IGST', 8, '-', '+', 'Liabilites', 0.0000, 'Dr', 'Duties & Taxes', '01/01/2025', '09/03/2025', 2000.0000, 0.0000, '', '', null, null],
        [137, 'SALES RETURN ACCOUNT', 20, '+', '-', 'Income', 0.0000, 'Dr', 'Sales Account', '01/01/2025', '09/03/2025', 2000.0000, 0.0000, '', '', '', ''],
        [138, 'PURCHASE RETURN ACCOUNT', 22, '+', '-', 'Expences', 0.0000, 'Dr', 'Purchase Account', '01/01/2025', '09/03/2025', 2000.0000, 0.0000, '', '', '', ''],
        [139, 'DISCOUNT ON PURCHASE', 29, '+', '-', 'Income', 0.0000, 'Dr', 'INDIRECT INCOME', '01/01/2025', '09/03/2025', 2000.0000, 0.0000, '', '', '', ''],
        [142, 'STOCK IN TRADE A/C', 23, '+', '-', 'Asset', 0.0000, 'Dr', 'Stock-In-Hand', '01/01/2025', '09/03/2025', 2000.0000, 0.0000, '', '', '', ''],
        [143, 'OPENING STOCK A/C', 23, '+', '-', 'Asset', 0.0000, 'Dr', 'Stock-In-Hand', '01/01/2025', '09/03/2025', 2000.0000, 0.0000, '', '', '', ''],
        [161, 'VAT TAX A/C', 28, '+', '-', 'Expences', 0.0000, 'Cr', 'INDIRECT EXPENSES', '01/01/2025', '09/03/2025', 2000.0000, 0.0000, '', '', '', ''],
        [173, 'RENT A/C', 28, '+', '-', 'Expences', 0.0000, 'Dr', 'Direct Expenses', '01/01/2025', '09/03/2025', 2000.0000, 0.0000, '', '', '', ''],
        [183, 'COMPUTER,PRINTER & CVT', 28, '+', '-', 'Expences', 0.0000, 'Dr', 'INDIRECT EXPENSES', '01/01/2025', '09/03/2025', 2000.0000, 0.0000, '', '', '', ''],
        [193, 'FIXED ASSETS', 11, '+', '-', 'Asset', 0.0000, 'Dr', 'Fixed Assets', '01/01/2025', '09/03/2025', 2000.0000, 0.0000, '', '', '', ''],        
        [201, 'ADVERTISEMENT A/C', 28, '+', '-', 'Expences', 0.0000, 'Dr', 'INDIRECT EXPENSES', '01/01/2025', '09/03/2025', 2000.0000, 0.0000, '', '', '', ''],
        [212, 'SHORT/EXCESS', 28, '+', '-', 'Expences', 0.0000, 'Dr', 'INDIRECT EXPENSES', '01/01/2025', '09/03/2025', 2000.0000, 0.0000, '', '', '', ''],
        [229, 'INTREST', 28, '+', '-', 'Expences', 0.0000, 'Dr', 'INDIRECT EXPENSES', '01/01/2025', '09/03/2025', 2000.0000, 0.0000, '', '', '', ''],
        [247, 'CLOSING STOCK', 23, '+', '-', 'Asset', 0.0000, 'Dr', 'Stock-In-Hand', '01/01/2025', '09/03/2025', 2000.0000, 0.0000, '', '', '', ''],
        [1573, 'Interest Income', 29, '-', '+', 'Income', 0.0000, 'Dr', 'INDIRECT INCOME', '01/01/2025', '09/03/2025', 2000.0000, 0.0000, '', '', null, null],
    ];

    // Get the maximum AccID from ledgermaster
    $max_accid_sql = "SELECT MAX(AccID) as maxAccID FROM ledgermaster";
    $maxAccID_result = $conn->query($max_accid_sql);
    $maxAccID_row = $maxAccID_result->fetch_assoc();
    $baseAccID = intval($maxAccID_row['maxAccID']) + 1;

    // Insert ledger accounts
    $ledger_insert_sql = "INSERT INTO ledgermaster 
        (AccID, AccName, GroupID, Dr, Cr, TransactionType, OBalance, BalanceType, Groupname, From_Dt, To_Dt, Dr_Op, Cr_Op, Address1, Address2, TIN, Phone, VendorID)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $ledger_stmt = $conn->prepare($ledger_insert_sql);
    $ledger_insert_failed = false;

    foreach ($ledger_accounts as $account) {
        $accID = $baseAccID++;
        $accName = $account[1];
        $groupID = $account[2];
        $dr = $account[3];
        $cr = $account[4];
        $transactionType = $account[5];
        $oBalance = $account[6];
        $balanceType = $account[7];
        $groupname = $account[8];
        $fromDt = $account[9];
        $toDt = $account[10];
        $drOp = $account[11];
        $crOp = $account[12];
        $address1 = $account[13];
        $address2 = $account[14];
        $tin = $account[15];
        $phone = $account[16];

        $ledger_stmt->bind_param(
            "isisssdssssddssssi",
            $accID, $accName, $groupID, $dr, $cr, $transactionType, $oBalance, $balanceType, $groupname,
            $fromDt, $toDt, $drOp, $crOp, $address1, $address2, $tin, $phone, $vendor_id
        );

        if (!$ledger_stmt->execute()) {
            $ledger_insert_failed = true;
            break;
        }
    }

    $ledger_stmt->close();

    if ($ledger_insert_failed) {
        echo json_encode(["success" => false, "error" => "Vendor created but ledger account creation failed: " . $ledger_stmt->error]);
        $conn->close();
        exit();
    }

    // Store details data
    $store_name = $conn->real_escape_string($data['store_name'] ?? '');
    $gst_no = $conn->real_escape_string($data['gst_no'] ?? '');
    $pan = $conn->real_escape_string($data['pan'] ?? '');
    $store_email = $conn->real_escape_string($data['store_email'] ?? '');
    $pin_code = $conn->real_escape_string($data['pin_code'] ?? '');
    $store_address = $conn->real_escape_string($data['store_address'] ?? '');
    $phone_no = $conn->real_escape_string($data['store_phone'] ?? '');
    $logo = $conn->real_escape_string($data['logo'] ?? '');

    // Insert store data
    $insert_store_sql = "INSERT INTO store 
        (VendorID, Store_Name, GSTNO, PAN, email, pin_code, saddress, phoneno, logo)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $store_stmt = $conn->prepare($insert_store_sql);
    $store_stmt->bind_param(
        "issssssss",
        $vendor_id, $store_name, $gst_no, $pan,
        $store_email, $pin_code, $store_address,
        $phone_no, $logo
    );

    if ($store_stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Vendor and store registered successfully with default ledger accounts"]);
    } else {
        echo json_encode(["success" => false, "error" => "Vendor and ledger created but store registration failed: " . $store_stmt->error]);
    }

    $store_stmt->close();

} else {
    echo json_encode(["success" => true, "message" => "User registered (no specific role action)"]);
}

$conn->close();
?>