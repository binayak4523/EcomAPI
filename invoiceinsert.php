<?php
// mysql_insert.php
header("Content-Type: application/json");
require_once 'db.php';
$conn = new mysqli($host,$user, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => $conn->connect_error]);
    exit;
}


// Read JSON input
$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid JSON"]);
    exit;
}

$invoiceHead = $data["invoiceHead"] ?? null;
$invoiceDetails = $data["invoiceDetails"] ?? [];

if (!$invoiceHead) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "InvoiceHead missing"]);
    exit;
}
// Delete from InvoiceHead
    $deleteHeadStmt = $conn->prepare("DELETE FROM InvoiceHead WHERE InvNo = ?");
    $deleteHeadStmt->bind_param("i", $invoiceHead["InvNo"]);
    $deleteHeadStmt->execute();
    $deleteHeadStmt->close();

    // Delete from InvoiceDetails
    $deleteDetailsStmt = $conn->prepare("DELETE FROM InvoiceDetails WHERE InvNo = ?");
    $deleteDetailsStmt->bind_param("i", $invoiceHead["InvNo"]);
    $deleteDetailsStmt->execute();
    $deleteDetailsStmt->close();
$stmt = $conn->prepare("
    REPLACE INTO InvoiceHead
    (InvNo, InvDate, ChalanNo, OrderNo, InvType, AccId, LrNo, Party, TotalQty, TotalGross, 
     TradeDiscount, SpecialDiscount, VatAmount, Net, RndUp, GrandTotal, AmountInText, Freight, 
     MrpAmount, ChalanDate, Fromdt, todt, BillType, TotalCase, add_ded, outstanding, CompanyID, 
     Pushed, INVPF, billtime, paid, `return`, exchangeamt, exchangeqty, qrimage, `user`, 
     ReceiverName, ShippingAddress1, ShippingAddress2, ShippingCity, ShippingState, ShippingPINCode)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
");

$stmt->bind_param(
    "issisissdddddddddsssssisdssssddddbsssssssi",
    $invoiceHead["InvNo"],             // i
    $invoiceHead["InvDate"],           // s
    $invoiceHead["ChalanNo"],          // s
    $invoiceHead["OrderNo"],           // i
    $invoiceHead["InvType"],           // s
    $invoiceHead["AccId"],             // i
    $invoiceHead["LrNo"],              // s
    $invoiceHead["Party"],             // s
    $invoiceHead["TotalQty"],          // d
    $invoiceHead["TotalGross"],        // d
    $invoiceHead["TradeDiscount"],     // d
    $invoiceHead["SpecialDiscount"],   // d
    $invoiceHead["VatAmount"],         // d
    $invoiceHead["Net"],               // d
    $invoiceHead["RndUp"],             // d
    $invoiceHead["GrandTotal"],        // d
    $invoiceHead["AmountInText"],      // s
    $invoiceHead["Freight"],           // d
    $invoiceHead["MrpAmount"],         // d
    $invoiceHead["ChalanDate"],        // s
    $invoiceHead["Fromdt"],            // s
    $invoiceHead["todt"],              // s
    $invoiceHead["BillType"],          // s
    $invoiceHead["TotalCase"],         // i
    $invoiceHead["add_ded"],           // s
    $invoiceHead["outstanding"],       // d
    $invoiceHead["CompanyID"],         // i
    $invoiceHead["Pushed"],            // s
    $invoiceHead["INVPF"],             // s
    $invoiceHead["billtime"],          // s
    $invoiceHead["paid"],              // d
    $invoiceHead["return"],            // d
    $invoiceHead["exchangeamt"],       // d
    $invoiceHead["exchangeqty"],       // d
    $invoiceHead["qrimage"],           // b
    $invoiceHead["user"],              // s
    $invoiceHead["ReceiverName"],      // s
    $invoiceHead["ShippingAddress1"],  // s
    $invoiceHead["ShippingAddress2"],  // s
    $invoiceHead["ShippingCity"],      // s
    $invoiceHead["ShippingState"],     // s
    $invoiceHead["ShippingPINCode"]    // i
);


$stmt->execute();
$stmt->close();

$stmtDetails = $conn->prepare("
    REPLACE INTO InvoiceDetails
    (InvNo, InvType, ProductType, ItemType, Brandname, Itemname, Size, Units, MRP, SaleRate, 
     Qty, Gross, SpecialDiscount, Tradediscount, DiscountAmount, Vat, VatAmount, Net, ProductCode, 
     Free_Qty, Tax_type, Pack, Slno, HSN, MfgDate, ExpDate, BatchNo, adapterslno, batteryslno)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
");
if (!$stmtDetails) {
    error_log("Prepare failed: " . $mysqli->error);
    throw new Exception("Prepare failed: " . $mysqli->error);
}
foreach ($invoiceDetails as $detail) {

    // Fallbacks for NULL values
    $InvNo          = $detail["InvNo"]          ?? 0;
    $InvType        = $detail["InvType"]        ?? "";
    $ProductType    = $detail["ProductType"]    ?? "";
    $ItemType       = $detail["ItemType"]       ?? "";
    $Brandname      = $detail["Brandname"]      ?? "";
    $Itemname       = $detail["Itemname"]       ?? "";
    $Size           = $detail["Size"]           ?? "";
    $Units          = $detail["Units"]          ?? "";
    $MRP            = $detail["MRP"]            ?? 0.0;
    $SaleRate       = $detail["SaleRate"]       ?? 0.0;
    $Qty            = $detail["Qty"]            ?? 0.0;
    $Gross          = $detail["Gross"]          ?? 0.0;
    $SpecialDiscount= $detail["SpecialDiscount"]?? 0.0;
    $Tradediscount  = $detail["Tradediscount"]  ?? 0.0;
    $DiscountAmount = $detail["DiscountAmount"] ?? 0.0;
    $Vat            = $detail["Vat"]            ?? 0.0;
    $VatAmount      = $detail["VatAmount"]      ?? 0.0;
    $Net            = $detail["Net"]            ?? 0.0;
    $ProductCode    = $detail["ProductCode"]    ?? 0;
    $Free_Qty       = $detail["Free_Qty"]       ?? 0.0;
    $Tax_type       = $detail["Tax_type"]       ?? "";
    $Pack           = $detail["Pack"]           ?? "";
    $Slno           = $detail["Slno"]           ?? 0;
    $HSN            = $detail["HSN"]            ?? "";
    $MfgDate        = $detail["MfgDate"]        ?? "";
    $ExpDate        = $detail["ExpDate"]        ?? "";
    $BatchNo        = $detail["BatchNo"]        ?? "";
    $adapterslno    = $detail["adapterslno"]    ?? "";
    $batteryslno    = $detail["batteryslno"]    ?? "";
    
    $InvNo = (int)$InvNo; // Ensure integer
    $InvType = (string)$InvType;
    $ProductType = (string)$ProductType;
    $ItemType = (string)$ItemType;
    $Brandname = (string)$Brandname;
    $Itemname = (string)$Itemname;
    $Size = (string)$Size;
    $Units = (string)$Units;
    $MRP = (float)$MRP; // Ensure double/float
    $SaleRate = (float)$SaleRate;
    $Qty = (float)$Qty;
    $Gross = (float)$Gross;
    $SpecialDiscount = (float)$SpecialDiscount;
    $Tradediscount = (float)$Tradediscount;
    $DiscountAmount = (float)$DiscountAmount;
    $Vat = (float)$Vat;
    $VatAmount = (float)$VatAmount;
    $Net = (float)$Net;
    $ProductCode = (float)$ProductCode; // Ensure double/float
    $Free_Qty = (float)$Free_Qty; // Ensure double/float
    $Tax_type = (string)$Tax_type;
    $Pack = (int)$Pack; // Ensure integer
    $Slno = (int)$Slno; // Ensure integer
    $HSN = (string)$HSN;
    $MfgDate = (string)$MfgDate;
    $ExpDate = (string)$ExpDate;
    $BatchNo = (string)$BatchNo;
    $adapterslno = (string)$adapterslno;
    $batteryslno = (string)$batteryslno; // Ensure string, not integer

    $stmtDetails->bind_param(
    "isssssssddddddddddddsiissssss",
    $InvNo,
    $InvType,
    $ProductType,
    $ItemType,
    $Brandname,
    $Itemname,
    $Size,
    $Units,
    $MRP,
    $SaleRate,
    $Qty,
    $Gross,
    $SpecialDiscount,
    $Tradediscount,
    $DiscountAmount,
    $Vat,
    $VatAmount,
    $Net,
    $ProductCode,
    $Free_Qty,
    $Tax_type,
    $Pack,
    $Slno,
    $HSN,
    $MfgDate,
    $ExpDate,
    $BatchNo,
    $adapterslno,
    $batteryslno
);


    $stmtDetails->execute();
}

$stmtDetails->close();

$conn->close();

echo json_encode(["status" => "success", "message" => "Invoice synced"]);

?>
