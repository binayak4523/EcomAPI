<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require 'db.php';

// Create connection
$conn = new mysqli($host, $user, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Connection failed: ' . $conn->connect_error]);
    exit();
}

try {
    // Get JSON data from request body
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    if (!$data) {
        throw new Exception('Invalid JSON data');
    }
    
    // Extract purchase header data
    $vendorID = isset($data['vendorID']) ? intval($data['vendorID']) : 0;
    $slno = isset($data['slno']) ? intval($data['slno']) : 0;
    $purchasedate = isset($data['purchasedate']) ? $data['purchasedate'] : '';
    $invNo = isset($data['invNo']) ? $data['invNo'] : '';
    $invDate = isset($data['invDate']) ? $data['invDate'] : '';
    $supplier = isset($data['supplier']) ? $data['supplier'] : '';
    $accId = isset($data['accId']) ? intval($data['accId']) : 0;
    $lrNo = isset($data['lrNo']) ? $data['lrNo'] : '';
    $waybill = isset($data['waybill']) ? $data['waybill'] : '';
    $totalQty = isset($data['totalQty']) ? floatval($data['totalQty']) : 0;
    $totalGross = isset($data['totalGross']) ? floatval($data['totalGross']) : 0;
    $vatAmount = isset($data['vatAmount']) ? floatval($data['vatAmount']) : 0;
    $netAmount = isset($data['netAmount']) ? floatval($data['netAmount']) : 0;
    $rValue = isset($data['rValue']) ? floatval($data['rValue']) : 0;
    $grandTotal = isset($data['grandTotal']) ? floatval($data['grandTotal']) : 0;
    $freight = isset($data['freight']) ? floatval($data['freight']) : 0;
    $totalMrp = isset($data['totalMrp']) ? floatval($data['totalMrp']) : 0;
    $lessDiscount = isset($data['lessDiscount']) ? floatval($data['lessDiscount']) : 0;
    $cst = isset($data['cst']) ? floatval($data['cst']) : 0;
    $cstAmount = isset($data['cstAmount']) ? floatval($data['cstAmount']) : 0;
    $eTax = isset($data['eTax']) ? floatval($data['eTax']) : 0;
    $eTaxAmount = isset($data['eTaxAmount']) ? floatval($data['eTaxAmount']) : 0;
    $stateCode = isset($data['stateCode']) ? intval($data['stateCode']) : 0;
    $items = isset($data['items']) ? $data['items'] : [];
    $username = isset($data['username']) ? $data['username'] : 'system';
    
    if (!$vendorID || !$slno) {
        throw new Exception('VendorID and Slno are required');
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    // Check if purchase header record exists
    $check_head = $conn->prepare("SELECT AccId FROM PurchaseHead WHERE Slno = ? AND vendorID = ?");
    $check_head->bind_param("ii", $slno, $vendorID);
    $check_head->execute();
    $head_result = $check_head->get_result();
    $head_exists = $head_result->num_rows > 0;
    
    if ($head_exists) {
        // Get existing AccId
        $head_row = $head_result->fetch_assoc();
        $accId = $head_row['AccId'];
        
        // Update PurchaseHead
        $query_head = "UPDATE PurchaseHead SET 
                       Purchasedate=?, InvNo=?, InvDate=?, Supplier=?, AccId=?, LrNo=?, 
                       TotalQty=?, TotalGross=?, VatAmount=?, NetAmount=?, RValue=?, 
                       GrandTotal=?, Freight=?, TotalMrp=?, lessDiscount=?, Waybill=?, 
                       CST=?, CSTAmount=?, ETax=?, ETaxAmount=? 
                       WHERE Slno=?";
        
        $stmt_head = $conn->prepare($query_head);
        $stmt_head->bind_param("ssssisissddddddisddii",
            $purchasedate, $invNo, $invDate, $supplier, $accId, $lrNo,
            $totalQty, $totalGross, $vatAmount, $netAmount, $rValue,
            $grandTotal, $freight, $totalMrp, $lessDiscount, $waybill,
            $cst, $cstAmount, $eTax, $eTaxAmount, $slno
        );
        
        if (!$stmt_head->execute()) {
            throw new Exception("Execute failed for PurchaseHead UPDATE: " . $stmt_head->error);
        }
        $stmt_head->close();
        
        // Delete existing purchase details and reverse stock
        $check_details = $conn->prepare("SELECT * FROM PurchaseDetails WHERE Slno = ?");
        $check_details->bind_param("i", $slno);
        $check_details->execute();
        $details_result = $check_details->get_result();
        
        if ($details_result->num_rows > 0) {
            while ($detail_row = $details_result->fetch_assoc()) {
                $productCode = $detail_row['ProductCode'];
                $prevQty = $detail_row['Qty'] + $detail_row['Free_Qty'];
                $mfgdate = $detail_row['mfgdate'];
                $expdate = $detail_row['expdate'];
                $batchno = $detail_row['batchno'];
                
                // Reverse stock quantity
                $conn->query("UPDATE stock SET Qty = Qty - $prevQty WHERE ProductCode = $productCode");
                $conn->query("UPDATE stockdetails SET Qty = Qty - $prevQty WHERE ProductCode = $productCode 
                             AND mfgdate = '$mfgdate' AND expdate = '$expdate' AND batchno = '$batchno'");
            }
            
            // Delete old details
            $conn->query("DELETE FROM PurchaseDetails WHERE Slno = $slno");
        }
        $check_details->close();
    } else {
        // Insert new PurchaseHead
        $query_head = "INSERT INTO PurchaseHead 
                       (Slno, Purchasedate, InvNo, InvDate, Supplier, AccId, LrNo, TotalQty, 
                        TotalGross, VatAmount, NetAmount, RValue, GrandTotal, Freight, 
                        TotalMrp, lessDiscount, Waybill, CST, CSTAmount, ETax, ETaxAmount, vendorID)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt_head = $conn->prepare($query_head);
        $stmt_head->bind_param("issssiissddddsdisddddi",
            $slno, $purchasedate, $invNo, $invDate, $supplier, $accId, $lrNo, $totalQty,
            $totalGross, $vatAmount, $netAmount, $rValue, $grandTotal, $freight,
            $totalMrp, $lessDiscount, $waybill, $cst, $cstAmount, $eTax, $eTaxAmount, $vendorID
        );
        
        if (!$stmt_head->execute()) {
            throw new Exception("Execute failed for PurchaseHead INSERT: " . $stmt_head->error);
        }
        $stmt_head->close();
    }
    $check_head->close();
    
    // Process each item from Temp_Stockin (or items array)
    foreach ($items as $item) {
        $itemName = isset($item['itemName']) ? $item['itemName'] : '';
        $qty = floatval($item['qty']) ?? 0;
        $freeQty = floatval($item['free']) ?? 0;
        $rate = floatval($item['rate']) ?? 0;
        $productCode = isset($item['code']) ? intval($item['code']) : 0;
        $productType = isset($item['productType']) ? $item['productType'] : '';
        $itemtype = isset($item['itemtype']) ? $item['itemtype'] : '';
        $brand = isset($item['brand']) ? $item['brand'] : '';
        $barcode = isset($item['barcode']) ? $item['barcode'] : '';
        $size = isset($item['size']) ? $item['size'] : '';
        $mrp = floatval($item['mrp']) ?? 0;
        $vat = floatval($item['gst']) ?? 0;
        $unitType = isset($item['unit']) ? $item['unit'] : '';
        $hsn = isset($item['hsn']) ? $item['hsn'] : '';
        $mfgDate = isset($item['mfgDate']) ? $item['mfgDate'] : '';
        $expDate = isset($item['expDate']) ? $item['expDate'] : '';
        $batchNo = isset($item['batchNo']) ? $item['batchNo'] : '';
        $discount = floatval($item['dis']) ?? 0;
        $spDiscount = floatval($item['sDis']) ?? 0;
        $discountAmount = isset($item['discountAmount']) ? floatval($item['discountAmount']) ?? 0 : 0;
        $cd = isset($item['cd']) ? floatval($item['cd']) ?? 0 : 0;
        
        // Calculate amounts
        $amount = $qty * $rate;
        $vatAmount = ($amount * $vat) / 100;
        $netAmt = $amount + $vatAmount;
        
        // Insert into PurchaseDetails
        $query_details = "INSERT INTO PurchaseDetails 
                          (Slno, ItemName, Units, Pack, Qty, MRP, PrRate, Amount, ProductCode, 
                           Vat, VatAmount, Net, Free_Qty, Discount, SpDiscount, Discount_amount, 
                           cd, mfgdate, expdate, batchno, vendorID)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $pack = isset($item['pack']) ? floatval($item['pack']) : 0;
        $stmt_details = $conn->prepare($query_details);
        if (!$stmt_details) {
            throw new Exception("Prepare failed for PurchaseDetails: " . $conn->error);
        }
        
        $stmt_details->bind_param("isidddddiddddddddsssi",
            $slno, $itemName, $unitType, $pack, $qty, $mrp, $rate, $amount, $productCode,
            $vat, $vatAmount, $netAmt, $freeQty, $discount, $spDiscount, $discountAmount,
            $cd, $mfgDate, $expDate, $batchNo, $vendorID
        );
        
        if (!$stmt_details->execute()) {
            throw new Exception("Execute failed for PurchaseDetails: " . $stmt_details->error);
        }
        $stmt_details->close();
        // Update Stock in item_master
        $check_stock = $conn->prepare("update item_master set Qty = Qty + ? WHERE id = ? and vendorID=?");
        $check_stock->bind_param("iii", $qty, $productCode, $vendorID);
        $check_stock->execute();
        // Update or Insert into Stock
        $check_stock = $conn->prepare("SELECT Qty FROM stock WHERE ProductCode = ? and vendorID=?");
        $check_stock->bind_param("ii", $productCode, $vendorID);
        $check_stock->execute();
        $stock_result = $check_stock->get_result();
        
        $totalQtyToAdd = $qty + $freeQty;
        
        if ($stock_result->num_rows > 0) {
            // Update existing stock
            $conn->query("UPDATE stock SET Qty = Qty + $totalQtyToAdd WHERE ProductCode = $productCode and vendorID=$vendorID");
        } else {
            // Insert new stock
            $query_stock_insert = "INSERT INTO stock (itemname, MRP, PRate, Qty, ProductCode, vendorID) 
                                   VALUES (?, ?, ?, ?, ?, ?)";
            $stmt_stock_insert = $conn->prepare($query_stock_insert);
            $stmt_stock_insert->bind_param("sdddii", $itemName, $mrp, $rate, $totalQtyToAdd, $productCode, $vendorID);
            $stmt_stock_insert->execute();
            $stmt_stock_insert->close();
        }
        $check_stock->close();
        
        // Update or Insert into Stockdetails
        $check_stockdetails = $conn->prepare("SELECT Qty FROM stockdetails WHERE ProductCode = ? 
                                              AND mfgdate = ? AND expdate = ? AND batchno = ? and vendorID=?");
        $check_stockdetails->bind_param("isssi", $productCode, $mfgDate, $expDate, $batchNo, $vendorID);
        $check_stockdetails->execute();
        $stockdetails_result = $check_stockdetails->get_result();
        
        if ($stockdetails_result->num_rows > 0) {
            // Update existing stockdetails
            $conn->query("UPDATE stockdetails SET Qty = Qty + $totalQtyToAdd 
                         WHERE ProductCode = $productCode AND mfgdate = '$mfgDate' 
                         AND expdate = '$expDate' AND batchno = '$batchNo' and vendorID=$vendorID");
        } else {
            // Get item details from itemmaster
            $get_itemmaster = $conn->prepare("SELECT item_master.*,c.category_name as ProductType, sc.subcategory as itemtype,b.brand as brand FROM item_master inner join category c on item_master.category_id = c.category_id inner join subcategory sc on item_master.subcategory_id = sc.scategoryid inner join brands b on item_master.BrandID = b.BrandID WHERE id = ? and vendorID=?");
            $get_itemmaster->bind_param("ii", $productCode, $vendorID);
            $get_itemmaster->execute();
            $itemmaster_result = $get_itemmaster->get_result();
            
            if ($itemmaster_result->num_rows > 0) {
                $itemmaster = $itemmaster_result->fetch_assoc();
                $purchaseinfo = "$slno-$purchasedate-$productCode";
                
                $query_stockdetails = "INSERT INTO Stockdetails 
                                       (ProductType, itemtype, itemname, brand, barcode, size, MRP, PRate, Qty, 
                                        ProductCode, Vat, Lose, UniyType, SaleRate, mfgdate, batchno, expdate, hsn, purchaseinfo, vendorID)
                                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $stmt_stockdetails = $conn->prepare($query_stockdetails);
                $stmt_stockdetails->bind_param("ssssssdddiidssssssi",
                    $itemmaster['ProductType'], $itemmaster['itemtype'], $itemName,
                    $itemmaster['brand'], $itemmaster['barcode'], $itemmaster['size'],
                    $mrp, $rate, $totalQtyToAdd, $productCode, $vat,
                    $itemmaster['Lose'], $itemmaster['unittype'], $itemmaster['SaleRate'],
                    $mfgDate, $batchNo, $expDate, $itemmaster['HSN'], $purchaseinfo, $vendorID
                );
                
                if (!$stmt_stockdetails->execute()) {
                    throw new Exception("Execute failed for Stockdetails: " . $stmt_stockdetails->error);
                }
                $stmt_stockdetails->close();
            }
            $get_itemmaster->close();
        }
        $check_stockdetails->close();
        
        // Handle Stock Register
        $check_register = $conn->prepare("SELECT id, closing FROM stockregister 
                                          WHERE VoucherType='Purchase' AND VoucherNo=? AND ProductCode=? and vendorID=?");
        $check_register->bind_param("iiii", $slno, $productCode, $vendorID);
        $check_register->execute();
        $register_result = $check_register->get_result();
        
        if ($register_result->num_rows > 0) {
            // Update existing register entry
            $register_row = $register_result->fetch_assoc();
            $current_id = $register_row['id'];
            $previous_closing = $register_row['closing'];
            
            // Get last closing before this record
            $last_register = $conn->prepare("SELECT Closing FROM stockregister 
                                             WHERE ProductCode=? AND id < ? and vendorID=? ORDER BY id DESC LIMIT 1");
            $last_register->bind_param("iiii", $productCode, $current_id, $vendorID);
            $last_register->execute();
            $last_result = $last_register->get_result();
            $last_closing = $last_result->num_rows > 0 ? $last_result->fetch_assoc()['Closing'] : 0;
            
            $new_closing = $last_closing + $totalQtyToAdd;
            $closing_diff = $new_closing - $previous_closing;
            
            $conn->query("UPDATE stockregister SET openingqty=$last_closing, purchase=$totalQtyToAdd, 
                         closing=$new_closing WHERE id=$current_id and vendorID=$vendorID");
            $conn->query("UPDATE stockregister SET openingqty=openingqty+$closing_diff, 
                         closing=closing+$closing_diff WHERE ProductCode=$productCode AND id > $current_id and vendorID=$vendorID");
            
            $last_register->close();
        } else {
            // Insert new register entry
            $last_register = $conn->prepare("SELECT closing FROM stockregister 
                                             WHERE ProductCode=? and vendorID=? ORDER BY Tdate DESC, id DESC LIMIT 1");
            $last_register->bind_param("ii", $productCode, $vendorID);
            $last_register->execute();
            $last_result = $last_register->get_result();
            $last_closing = $last_result->num_rows > 0 ? $last_result->fetch_assoc()['closing'] : 0;
            
            $new_closing = $last_closing + $totalQtyToAdd;
            
            $query_register = "INSERT INTO stockregister 
                              (Tdate, ProductCode, ProductName, VoucherType, VoucherNo, OpeningQty, 
                               Purchase, SaleRet, Sale, PurchaseRet, Closing, PartyID, PartyName, vendorID)
                              VALUES (?, ?, ?, 'Purchase', ?, ?, ?, 0, 0, 0, ?, ?, ?, ?)";
            
            $stmt_register = $conn->prepare($query_register);
            $stmt_register->bind_param("sisididissi", $purchasedate, $productCode, $itemName, $slno,
                                      $last_closing, $totalQtyToAdd, $new_closing, $accId, $supplier, $vendorID);
            
            if (!$stmt_register->execute()) {
                throw new Exception("Execute failed for stockregister: " . $stmt_register->error);
            }
            $stmt_register->close();
            
            $last_register->close();
        }
        $check_register->close();
    }
    
    // Delete existing ledger transactions for this purchase
    $conn->query("DELETE FROM LedgerTran WHERE VoucherType='Purchase' AND VoucherSlno=$slno and vendorID=$vendorID");
    
    // Get Purchase Account from LedgerMaster
    $purchase_acc = $conn->query("SELECT AccId, GroupId FROM LedgerMaster WHERE AccName LIKE 'Purchase%' and vendorID=$vendorID");
    $prAccId = 0;
    if ($purchase_acc && $purchase_acc->num_rows > 0) {
        $pacc = $purchase_acc->fetch_assoc();
        $prAccId = $pacc['AccId'];
        $purchaseGroupId = $pacc['GroupId'];
    }
    
    // Party Ledger Entry
    $party_last = $conn->query("SELECT MAX(Slno) as max_slno, Balance FROM LedgerTran 
                               WHERE AccId=$accId and vendorID=$vendorID ORDER BY Slno DESC LIMIT 1");
    $temp_ledger_slno = 1;
    $temp_ledger_balance = 0;
    
    if ($party_last && $party_last->num_rows > 0) {
        $last_ledger = $party_last->fetch_assoc();
        $temp_ledger_slno = $last_ledger['max_slno'] + 1;
        $temp_ledger_balance = $last_ledger['Balance'];
    }
    
    $new_balance = $temp_ledger_balance + $grandTotal;
    $remarks = "Inv No: $invNo - $invDate";
    
    $query_party_ledger = "INSERT INTO LedgerTran 
                          (Slno, TDate, Particulars, Dr, Cr, Balance, AccId, Remarks, VoucherType, 
                           VoucherSlno, GroupId, TranAccId, Username, vendorID)
                          VALUES (?, ?, 'By Purchase', 0, ?, ?, ?, ?, 'Purchase', ?, ?, ?, ?, ?)";
    
    $stmt_party = $conn->prepare($query_party_ledger);
    $groupId = 1; // Default group ID for vendor
    $stmt_party->bind_param("issdiisiiisi", $temp_ledger_slno, $purchasedate, $grandTotal, $new_balance,
                           $accId, $remarks, $slno, $groupId, $prAccId, $username, $vendorID);
    
    if (!$stmt_party->execute()) {
        throw new Exception("Execute failed for party LedgerTran: " . $stmt_party->error);
    }
    $stmt_party->close();
    
    // Purchase Ledger Entry
    if ($prAccId > 0) {
        $purchase_last = $conn->query("SELECT MAX(Slno) as max_slno, Balance FROM LedgerTran 
                                      WHERE AccId=$prAccId and vendorID=$vendorID ORDER BY Slno DESC LIMIT 1");
        $temp_purchase_slno = 1;
        $temp_purchase_balance = 0;
        
        if ($purchase_last && $purchase_last->num_rows > 0) {
            $last_purchase = $purchase_last->fetch_assoc();
            $temp_purchase_slno = $last_purchase['max_slno'] + 1;
            $temp_purchase_balance = $last_purchase['Balance'];
        }
        
        $purchase_amount = $grandTotal - $vatAmount;
        $new_purchase_balance = $temp_purchase_balance + $purchase_amount;
        
        $query_purchase_ledger = "INSERT INTO LedgerTran 
                                 (Slno, TDate, Particulars, Dr, Cr, Balance, AccId, Remarks, 
                                  VoucherType, VoucherSlno, GroupId, TranAccId, Username, vendorID)
                                 VALUES (?, ?, ?, ?, 0, ?, ?, ?, 'Purchase', ?, ?, ?, ?, ?)";
        
        $stmt_purchase = $conn->prepare($query_purchase_ledger);
        $stmt_purchase->bind_param("issddsiisiisi", $temp_purchase_slno, $purchasedate, $supplier,
                                  $purchase_amount, $new_purchase_balance, $prAccId, $remarks, $slno,
                                  $purchaseGroupId, $accId, $username, $vendorID);

        if (!$stmt_purchase->execute()) {
            throw new Exception("Execute failed for purchase LedgerTran: " . $stmt_purchase->error);
        }
        $stmt_purchase->close();
    }
    
    // Tax Ledger Entries - SGST/CGST for state code 21, IGST for others
    if ($vatAmount > 0) {
        if ($stateCode == 21) {
            // SGST Entry
            $sgst_acc = $conn->query("SELECT AccId, GroupId FROM LedgerMaster WHERE AccName LIKE 'SGST%' and vendorID=$vendorID");
            if ($sgst_acc && $sgst_acc->num_rows > 0) {
                $sgst = $sgst_acc->fetch_assoc();
                $sgst_last = $conn->query("SELECT MAX(Slno) as max_slno, Balance FROM LedgerTran 
                                          WHERE AccId=".$sgst['AccId']." and vendorID=$vendorID ORDER BY Slno DESC LIMIT 1");
                $sgst_slno = 1;
                $sgst_balance = 0;
                
                if ($sgst_last && $sgst_last->num_rows > 0) {
                    $last_sgst = $sgst_last->fetch_assoc();
                    $sgst_slno = $last_sgst['max_slno'] + 1;
                    $sgst_balance = $last_sgst['Balance'];
                }
                
                $sgst_amount = $vatAmount / 2;
                $new_sgst_balance = $sgst_balance - $sgst_amount;
                
                $query_sgst = "INSERT INTO LedgerTran 
                              (Slno, TDate, Particulars, Dr, Cr, Balance, AccId, Remarks, VoucherType, 
                               VoucherSlno, GroupId, TranAccId, Username, vendorID)
                              VALUES (?, ?, ?, ?, 0, ?, ?, ?, 'Purchase', ?, ?, ?, ?, ?)";
                
                $stmt_sgst = $conn->prepare($query_sgst);
                $stmt_sgst->bind_param("issddsiisiisi", $sgst_slno, $purchasedate, $supplier, $sgst_amount,
                                      $new_sgst_balance, $sgst['AccId'], $remarks, $slno,
                                      $sgst['GroupId'], $accId, $username, $vendorID);

                if (!$stmt_sgst->execute()) {
                    throw new Exception("Execute failed for SGST LedgerTran: " . $stmt_sgst->error);
                }
                $stmt_sgst->close();
            }
            
            // CGST Entry
            $cgst_acc = $conn->query("SELECT AccId, GroupId FROM LedgerMaster WHERE AccName LIKE 'CGST%' and vendorID=$vendorID");
            if ($cgst_acc && $cgst_acc->num_rows > 0) {
                $cgst = $cgst_acc->fetch_assoc();
                $cgst_last = $conn->query("SELECT MAX(Slno) as max_slno, Balance FROM LedgerTran 
                                          WHERE AccId=".$cgst['AccId']." and vendorID=$vendorID ORDER BY Slno DESC LIMIT 1");
                $cgst_slno = 1;
                $cgst_balance = 0;
                
                if ($cgst_last && $cgst_last->num_rows > 0) {
                    $last_cgst = $cgst_last->fetch_assoc();
                    $cgst_slno = $last_cgst['max_slno'] + 1;
                    $cgst_balance = $last_cgst['Balance'];
                }
                
                $cgst_amount = $vatAmount / 2;
                $new_cgst_balance = $cgst_balance - $cgst_amount;
                
                $query_cgst = "INSERT INTO LedgerTran 
                              (Slno, TDate, Particulars, Dr, Cr, Balance, AccId, Remarks, VoucherType, 
                               VoucherSlno, GroupId, TranAccId, Username, vendorID)
                              VALUES (?, ?, ?, ?, 0, ?, ?, ?, 'Purchase', ?, ?, ?, ?, ?)";
                
                $stmt_cgst = $conn->prepare($query_cgst);
                $stmt_cgst->bind_param("issddsiisiisi", $cgst_slno, $purchasedate, $supplier, $cgst_amount,
                                      $new_cgst_balance, $cgst['AccId'], $remarks, $slno,
                                      $cgst['GroupId'], $accId, $username, $vendorID);

                if (!$stmt_cgst->execute()) {
                    throw new Exception("Execute failed for CGST LedgerTran: " . $stmt_cgst->error);
                }
                $stmt_cgst->close();
            }
        } else {
            // IGST Entry for other states
            $igst_acc = $conn->query("SELECT AccId, GroupId FROM LedgerMaster WHERE AccName LIKE 'IGST%' and vendorID=$vendorID");
            if ($igst_acc && $igst_acc->num_rows > 0) {
                $igst = $igst_acc->fetch_assoc();
                $igst_last = $conn->query("SELECT MAX(Slno) as max_slno, Balance FROM LedgerTran 
                                          WHERE AccId=".$igst['AccId']." and vendorID=$vendorID ORDER BY Slno DESC LIMIT 1");
                $igst_slno = 1;
                $igst_balance = 0;
                
                if ($igst_last && $igst_last->num_rows > 0) {
                    $last_igst = $igst_last->fetch_assoc();
                    $igst_slno = $last_igst['max_slno'] + 1;
                    $igst_balance = $last_igst['Balance'];
                }
                
                $new_igst_balance = $igst_balance - $vatAmount;
                
                $query_igst = "INSERT INTO LedgerTran 
                              (Slno, TDate, Particulars, Dr, Cr, Balance, AccId, Remarks, VoucherType, 
                               VoucherSlno, GroupId, TranAccId, Username, vendorID)
                              VALUES (?, ?, ?, ?, 0, ?, ?, ?, 'Purchase', ?, ?, ?, ?, ?)";
                
                $stmt_igst = $conn->prepare($query_igst);
                $stmt_igst->bind_param("issddsiisiisi", $igst_slno, $purchasedate, $supplier, $vatAmount,
                                      $new_igst_balance, $igst['AccId'], $remarks, $slno,
                                      $igst['GroupId'], $accId, $username, $vendorID);

                if (!$stmt_igst->execute()) {
                    throw new Exception("Execute failed for IGST LedgerTran: " . $stmt_igst->error);
                }
                $stmt_igst->close();
            }
        }
    }
    
    // Clear temporary stock in table (if applicable)
    // $conn->query("DELETE FROM Temp_Stockin");
    
    // Commit transaction
    $conn->commit();
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Purchase entry saved successfully',
        'slno' => $slno,
        'totalAmount' => $grandTotal,
        'operation' => $head_exists ? 'updated' : 'inserted'
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>