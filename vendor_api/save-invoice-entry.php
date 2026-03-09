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

$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Connection failed: ' . $conn->connect_error]);
    exit();
}

// Function to convert number to words
function numberToWords($num) {
    $ones = array('', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine');
    $teens = array('Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen');
    $tens = array('', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety');
    $scales = array('', 'Thousand', 'Million', 'Billion', 'Trillion');

    if ($num == 0) return 'Zero';

    $num = abs($num);
    $groupedNum = array();
    while ($num > 0) {
        array_unshift($groupedNum, $num % 1000);
        $num = floor($num / 1000);
    }

    $words = array();
    foreach ($groupedNum as $groupKey => $groupVal) {
        $scaleKey = count($groupedNum) - $groupKey - 1;
        $groupWords = array();

        $hundreds = floor($groupVal / 100);
        if ($hundreds > 0) {
            $groupWords[] = $ones[$hundreds] . ' Hundred';
        }

        $remainder = $groupVal % 100;
        if ($remainder >= 20) {
            $groupWords[] = $tens[floor($remainder / 10)];
            if ($remainder % 10 > 0) {
                $groupWords[count($groupWords) - 1] .= ' ' . $ones[$remainder % 10];
            }
        } elseif ($remainder >= 10) {
            $groupWords[] = $teens[$remainder - 10];
        } elseif ($remainder > 0) {
            $groupWords[] = $ones[$remainder];
        }

        if (!empty($groupWords) && $scaleKey > 0) {
            $groupWords[] = $scales[$scaleKey];
        }

        $words = array_merge($words, $groupWords);
    }

    return implode(' ', $words);
}

try {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (!$data) {
        throw new Exception('Invalid JSON data');
    }

    // Extract fields (frontend sends these keys)
    $vendorID = isset($data['vendorID']) ? intval($data['vendorID']) : 0;
    $companyId = isset($data['companyId']) ? intval($data['companyId']) : $vendorID;
    $slno = isset($data['slno']) ? intval($data['slno']) : 0;
    $invoicedate = isset($data['invoicedate']) ? $data['invoicedate'] : '';
    $customer = isset($data['customer']) ? $data['customer'] : '';
    $accId = isset($data['accId']) ? intval($data['accId']) : 0;
    $lrNo = isset($data['lrNo']) ? $data['lrNo'] : '';
    $waybill = isset($data['waybill']) ? $data['waybill'] : '';
    $chalanNo = isset($data['chalanNo']) ? $data['chalanNo'] : '';
    $chalanDate = isset($data['chalanDate']) ? $data['chalanDate'] : '';
    $totalQty = isset($data['totalQty']) ? floatval($data['totalQty']) : 0;
    $totalGross = isset($data['totalGross']) ? floatval($data['totalGross']) : 0;
    $tradeDiscount = isset($data['tradeDiscount']) ? floatval($data['tradeDiscount']) : 0;
    $specialDiscount = isset($data['specialDiscount']) ? floatval($data['specialDiscount']) : 0;
    $gstAmount = isset($data['gstAmount']) ? floatval($data['gstAmount']) : 0;
    $netAmount = isset($data['netAmount']) ? floatval($data['netAmount']) : 0;
    $roundup = isset($data['roundup']) ? floatval($data['roundup']) : 0;
    $grandTotal = isset($data['grandTotal']) ? floatval($data['grandTotal']) : 0;
    $freight = isset($data['freight']) ? floatval($data['freight']) : 0;
    $mrpAmount = isset($data['mrpAmount']) ? floatval($data['mrpAmount']) : 0;
    $totalCase = isset($data['totalCase']) ? floatval($data['totalCase']) : 0;
    $addDed = isset($data['addDed']) ? $data['addDed'] : '';
    $outstanding = isset($data['outstanding']) ? floatval($data['outstanding']) : 0;
    $billType = isset($data['billType']) ? $data['billType'] : 'CREDIT';
    $stateCode = isset($data['stateCode']) ? intval($data['stateCode']) : 0;
    $items = isset($data['items']) ? $data['items'] : [];
    $username = isset($data['username']) ? $data['username'] : 'system';
    $branchname = isset($data['branchname']) ? $data['branchname'] : '';
    $orderNo = isset($data['orderNo']) ? $data['orderNo'] : '';

    if (!$vendorID || !$slno) {
        throw new Exception('vendorID and slno are required');
    }

    // Normalize invoice date (accept DD/MM/YYYY or other formats)
    $tdate = '';
    if (!empty($invoicedate)) {
        $invoicedate = str_replace('-', '/', $invoicedate);
        $parts = explode('/', $invoicedate);
        if (count($parts) == 3) {
            if (strlen($parts[2]) == 4) {
                $tdate = date('Y-m-d', strtotime($parts[2].'-'.$parts[1].'-'.$parts[0]));
            } else {
                $tdate = date('Y-m-d', strtotime($invoicedate));
            }
        } else {
            $tdate = date('Y-m-d', strtotime($invoicedate));
        }
    }

    // Normalize chalan date
    $tchalanDate = '';
    if (!empty($chalanDate)) {
        $chalanDate = str_replace('-', '/', $chalanDate);
        $parts = explode('/', $chalanDate);
        if (count($parts) == 3) {
            if (strlen($parts[2]) == 4) {
                $tchalanDate = date('Y-m-d', strtotime($parts[2].'-'.$parts[1].'-'.$parts[0]));
            } else {
                $tchalanDate = date('Y-m-d', strtotime($chalanDate));
            }
        } else {
            $tchalanDate = date('Y-m-d', strtotime($chalanDate));
        }
    }

    // Convert grand total to words
    $amountInText = numberToWords(round($grandTotal));

    $conn->begin_transaction();

    // Check if InvoiceHead table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'invoicehead'");
    if ($table_check && $table_check->num_rows == 0) {
        $table_check = $conn->query("SHOW TABLES LIKE 'invoicehead'");
    }

    if (!$table_check || $table_check->num_rows == 0) {
        throw new Exception("Required table 'InvoiceHead' not found in database");
    }

    // Check if record exists
    $check_head = $conn->prepare("SELECT AccId FROM invoicehead WHERE InvNo = ? AND vendorID = ?");
    if (!$check_head) throw new Exception('Prepare failed: ' . $conn->error);
    $check_head->bind_param("ii", $slno, $vendorID);
    $check_head->execute();
    $head_result = $check_head->get_result();
    $head_exists = $head_result->num_rows > 0;

    if ($head_exists) {
        $head_row = $head_result->fetch_assoc();
        $accId = $head_row['AccId'];

        // Restore previous stock before updating
        $prev_items = $conn->query("SELECT ProductCode, Qty, Free_Qty, mfgdate, expdate, batchno FROM invoicedetails WHERE InvNo = " . intval($slno) . " AND vendorID = " . intval($vendorID));
        if ($prev_items && $prev_items->num_rows > 0) {
            while ($prev_item = $prev_items->fetch_assoc()) {
                $stockqty = floatval($prev_item['Qty']) + floatval($prev_item['Free_Qty']);
                $conn->query("UPDATE stock SET Qty = Qty + " . $stockqty . " WHERE ProductCode = " . intval($prev_item['ProductCode']) . " AND vendorID = " . intval($vendorID));
                $conn->query("UPDATE stockdetails SET Qty = Qty + " . $stockqty . " WHERE ProductCode = " . intval($prev_item['ProductCode']) . " AND mfgdate = '" . $conn->real_escape_string($prev_item['mfgdate']) . "' AND expdate = '" . $conn->real_escape_string($prev_item['expdate']) . "' AND batchno = '" . $conn->real_escape_string($prev_item['batchno']) . "' AND vendorID = " . intval($vendorID));
            }
        }

        $query_head = "UPDATE invoicehead SET InvDate=?, ChalanNo=?, ChalanDate=?, Party=?, AccId=?, LrNo=?, TotalQty=?, TotalGross=?, TradeDiscount=?, SpecialDiscount=?, VatAmount=?, Net=?, RndUp=?, GrandTotal=?, AmountInText=?, Freight=?, MrpAmount=?, BillType=?, TotalCase=?, add_ded=?, outstanding=?, OrderNo=? WHERE InvNo=? AND vendorID=?";
        $stmt_head = $conn->prepare($query_head);
        if (!$stmt_head) throw new Exception('Prepare failed (update): ' . $conn->error);
        $stmt_head->bind_param("ssssissdddddddsddsdsdii",
            $tdate, $chalanNo, $tchalanDate, $customer, $accId, $lrNo, $totalQty, $totalGross, $tradeDiscount, $specialDiscount, $gstAmount, $netAmount, $roundup, $grandTotal, $amountInText, $freight, $mrpAmount, $billType, $totalCase, $addDed, $outstanding, $orderNo, $slno, $vendorID
        );
        if (!$stmt_head->execute()) {
            throw new Exception('Execute failed (update InvoiceHead): ' . $stmt_head->error);
        }
        $stmt_head->close();

        // Delete existing details for this invoice
        $del = $conn->prepare("DELETE FROM invoicedetails WHERE InvNo = ? AND vendorID = ?");
        $del->bind_param("ii", $slno, $vendorID);
        $del->execute();
        $del->close();
    } else {
        $query_head = "INSERT INTO invoicehead (InvNo, InvDate, ChalanNo, ChalanDate, Party, AccId, LrNo, TotalQty, TotalGross, TradeDiscount, SpecialDiscount, VatAmount, Net, RndUp, GrandTotal, AmountInText, Freight, MrpAmount, BillType, TotalCase, add_ded, outstanding, OrderNo, vendorID) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_head = $conn->prepare($query_head);
        if (!$stmt_head) throw new Exception('Prepare failed (insert): ' . $conn->error);
        $stmt_head->bind_param("issssissdddddddsddsdsdii",
            $slno, $tdate, $chalanNo, $tchalanDate, $customer, $accId, $lrNo, $totalQty, $totalGross, $tradeDiscount, $specialDiscount, $gstAmount, $netAmount, $roundup, $grandTotal, $amountInText, $freight, $mrpAmount, $billType, $totalCase, $addDed, $outstanding, $orderNo, $vendorID
        );
        if (!$stmt_head->execute()) {
            throw new Exception('Execute failed (insert InvoiceHead): ' . $stmt_head->error);
        }
        $stmt_head->close();
    }
    $check_head->close();

    // Insert InvoiceDetails and update stock
    foreach ($items as $item) {
        $itemName = isset($item['itemName']) ? $item['itemName'] : '';
        $qty = isset($item['qty']) ? floatval($item['qty']) : 0;
        $freeQty = isset($item['free']) ? floatval($item['free']) : 0;
        $rate = isset($item['rate']) ? floatval($item['rate']) : 0;
        $productCode = isset($item['code']) ? intval($item['code']) : 0;
        $mrp = isset($item['mrp']) ? floatval($item['mrp']) : 0;
        $vat = isset($item['gst']) ? floatval($item['gst']) : 0;
        $unitType = isset($item['unit']) ? $item['unit'] : '';
        $pack = isset($item['pack']) ? floatval($item['pack']) : 0;
        $mfgDate = isset($item['mfgDate']) ? $item['mfgDate'] : '';
        $expDate = isset($item['expDate']) ? $item['expDate'] : '';
        $batchNo = isset($item['batchNo']) ? $item['batchNo'] : '';
        $discount1 = isset($item['dis']) ? floatval($item['dis']) : 0;
        $discount2 = isset($item['sDis']) ? floatval($item['sDis']) : 0;
        $taxType = isset($item['taxType']) ? $item['taxType'] : 'GST';

        $amount = $qty * $rate;
        $discountAmount = ($amount * $discount1) / 100;
        $discountAmount2 = ((($amount - $discountAmount) * $discount2) / 100);
        $grossAmount = $amount - $discountAmount - $discountAmount2;
        $vatAmountItem = ($grossAmount * $vat) / 100;
        $netAmt = $grossAmount + $vatAmountItem;

        $query_details = "INSERT INTO invoicedetails (InvNo, ItemName, Units, Pack, Qty, MRP, SaleRate, Gross, ProductCode, Vat, VatAmount, Net, Free_Qty, Tradediscount, SpecialDiscount, DiscountAmount, mfgdate, expdate, batchno, Tax_type, slno, vendorID) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_details = $conn->prepare($query_details);
        if (!$stmt_details) throw new Exception('Prepare failed invoicedetails: ' . $conn->error);

        // Prepare computed variables (bind_param requires variables, not expressions/literals)
        $discountTotal = $discountAmount + $discountAmount2;
        $detailSlno = 1;

        // Types: InvNo(i), ItemName(s), Units(s), Pack(d), Qty(d), MRP(d), SaleRate(d), Gross(d), ProductCode(i),
        // Vat(d), VatAmount(d), Net(d), Free_Qty(d), Tradediscount(d), SpecialDiscount(d), DiscountAmount(d),
        // mfgdate(s), expdate(s), batchno(s), Tax_type(s), slno(i), vendorID(i)
        $stmt_details->bind_param("issdddddidddddddssssii",
            $slno, $itemName, $unitType, $pack, $qty, $mrp, $rate, $amount, $productCode,
            $vat, $vatAmountItem, $netAmt, $freeQty, $discount1, $discount2, $discountTotal, $mfgDate, $expDate, $batchNo, $taxType, $detailSlno, $vendorID
        );
        if (!$stmt_details->execute()) {
            throw new Exception('Execute failed (InvoiceDetails): ' . $stmt_details->error);
        }
        $stmt_details->close();

        // Reduce stock in item_master
        $stmt_item = $conn->prepare("UPDATE item_master SET Qty = GREATEST(0, Qty - ?) WHERE id = ?");
        if ($stmt_item) {
            $totalQtyItem = $qty + $freeQty;
            $stmt_item->bind_param("di", $totalQtyItem, $productCode);
            $stmt_item->execute();
            $stmt_item->close();
        }

        // Update stock table
        $conn->query("UPDATE stock SET Qty = GREATEST(0, Qty - " . ($qty + $freeQty) . ") WHERE ProductCode = " . $productCode . " AND vendorID = " . $vendorID);

        // Update stockdetails
        if (!empty($mfgDate) && !empty($expDate) && !empty($batchNo)) {
            $conn->query("UPDATE stockdetails SET Qty = GREATEST(0, Qty - " . ($qty + $freeQty) . ") WHERE ProductCode = " . $productCode . " AND mfgdate = '" . $conn->real_escape_string($mfgDate) . "' AND expdate = '" . $conn->real_escape_string($expDate) . "' AND batchno = '" . $conn->real_escape_string($batchNo) . "' AND vendorID = " . $vendorID);
        }

        // Insert/Update stockregister entry for Sales
        $check_register = $conn->prepare("SELECT id, closing FROM stockregister WHERE VoucherType='Invoice' AND VoucherNo=? AND ProductCode=? AND vendorID=?");
        if ($check_register) {
            $check_register->bind_param("iii", $slno, $productCode, $vendorID);
            $check_register->execute();
            $reg_result = $check_register->get_result();
            
            if ($reg_result && $reg_result->num_rows > 0) {
                $reg_row = $reg_result->fetch_assoc();
                $current_id = $reg_row['id'];
                $previous_closing = $reg_row['closing'];
                
                // Get previous closing
                $prev_register = $conn->prepare("SELECT closing FROM stockregister WHERE ProductCode=? AND vendorID=? AND id < ? ORDER BY id DESC LIMIT 1");
                if ($prev_register) {
                    $prev_register->bind_param("iii", $productCode, $vendorID, $current_id);
                    $prev_register->execute();
                    $prev_res = $prev_register->get_result();
                    $last_closing = 0;
                    if ($prev_res && $prev_res->num_rows > 0) {
                        $prev_row = $prev_res->fetch_assoc();
                        $last_closing = floatval($prev_row['closing']);
                    }
                    $prev_register->close();
                    
                    $total_qty = floatval($qty);
                    $new_closing = $last_closing - $total_qty;
                    $closing_diff = $new_closing - $previous_closing;
                    
                    $conn->query("UPDATE stockregister SET openingqty=" . $last_closing . ", sale=" . $total_qty . ", closing=" . $new_closing . " WHERE id=" . $current_id);
                    $conn->query("UPDATE stockregister SET openingqty=openingqty+" . $closing_diff . ", closing=closing+" . $closing_diff . " WHERE ProductCode=" . $productCode . " AND vendorID=" . $vendorID . " AND id > " . $current_id);
                }
            } else {
                // New entry - get last closing
                $last_register = $conn->prepare("SELECT closing FROM stockregister WHERE ProductCode=? AND vendorID=? ORDER BY Tdate DESC, id DESC LIMIT 1");
                if ($last_register) {
                    $last_register->bind_param("ii", $productCode, $vendorID);
                    $last_register->execute();
                    $res = $last_register->get_result();
                    $last_closing = 0;
                    if ($res && $res->num_rows > 0) {
                        $last_row = $res->fetch_assoc();
                        $last_closing = floatval($last_row['closing']);
                    }
                    $last_register->close();
                    
                    $total_qty = floatval($qty);
                    $new_closing = max(0, $last_closing - $total_qty);

                    $query_register = "INSERT INTO stockregister (Tdate, ProductCode, ProductName, VoucherType, VoucherNo, OpeningQty, Purchase, Sale, Closing, vendorID) VALUES (?, ?, ?, 'Invoice', ?, ?, 0, ?, ?, ?)";
                    $stmt_register = $conn->prepare($query_register);
                    if ($stmt_register) {
                        $stmt_register->bind_param("sisidddi", $tdate, $productCode, $itemName, $slno, $last_closing, $total_qty, $new_closing, $vendorID);
                        $stmt_register->execute();
                        $stmt_register->close();
                    }
                }
            }
            $check_register->close();
        }
    }

    // Remove existing ledger transactions for this invoice voucher
    $conn->query("DELETE FROM ledgertran WHERE VoucherType='Invoice' AND VoucherSlno=" . intval($slno) . " AND vendorID=" . intval($vendorID));

    // Create party ledger (Customer) entry
    $party_last = $conn->query("SELECT MAX(Slno) as max_slno, Balance FROM ledgertran WHERE AccId=$accId AND vendorID=$vendorID ORDER BY Slno DESC LIMIT 1");
    $temp_ledger_slno = 1;
    $temp_ledger_balance = 0;
    if ($party_last && $party_last->num_rows > 0) {
        $last_ledger = $party_last->fetch_assoc();
        $temp_ledger_slno = $last_ledger['max_slno'] + 1;
        $temp_ledger_balance = $last_ledger['Balance'];
    }
    $new_balance = $temp_ledger_balance + $grandTotal;
    $remarks = "Inv No: $slno - $invoicedate";

    $query_party_ledger = "INSERT INTO ledgertran (Slno, TDate, Particulars, Dr, Cr, Balance, AccId, Remarks, VoucherType, VoucherSlno, Username, vendorID) VALUES (?, ?, ?, ?, 0, ?, ?, ?, 'Invoice', ?, ?, ?)";
    $stmt_party = $conn->prepare($query_party_ledger);
    if ($stmt_party) {
        $partyParticulars = 'To Sales A/c';
        $stmt_party->bind_param("issddisisi", $temp_ledger_slno, $tdate, $partyParticulars, $grandTotal, $new_balance, $accId, $remarks, $slno, $username, $vendorID);
        $stmt_party->execute();
        $stmt_party->close();
    }

    // Sales Ledger Entry
    $sales_acc_res = $conn->query("SELECT AccId, GroupId FROM ledgermaster WHERE AccName LIKE 'Sales%' AND vendorID=$vendorID LIMIT 1");
    $salesAccId = 0;
    $salesGroupId = 0;
    if ($sales_acc_res && $sales_acc_res->num_rows > 0) {
        $sa = $sales_acc_res->fetch_assoc();
        $salesAccId = $sa['AccId'];
        $salesGroupId = $sa['GroupId'];

        // Delete previous sales ledger entry for this invoice
        $conn->query("DELETE FROM ledgertran WHERE AccId=" . $salesAccId . " AND VoucherType='Invoice' AND VoucherSlno=" . intval($slno) . " AND vendorID=" . intval($vendorID));

        $sales_last = $conn->query("SELECT MAX(Slno) as max_slno, Balance FROM ledgertran WHERE AccId=$salesAccId AND vendorID=$vendorID ORDER BY Slno DESC LIMIT 1");
        $temp_sales_slno = 1;
        $sales_balance = 0;
        if ($sales_last && $sales_last->num_rows > 0) {
            $ls = $sales_last->fetch_assoc();
            $temp_sales_slno = $ls['max_slno'] + 1;
            $sales_balance = $ls['Balance'];
        }
        $netAmountForSales = $grandTotal - $gstAmount;
        $new_sales_balance = $sales_balance + $netAmountForSales;

        $query_sales_ledger = "INSERT INTO ledgertran (Slno, TDate, Particulars, Dr, Cr, Balance, AccId, Remarks, VoucherType, VoucherSlno, GroupId, Username, vendorID) VALUES (?, ?, ?, 0, ?, ?, ?, ?, 'Invoice', ?, ?, ?, ?)";
        $stmt_sales = $conn->prepare($query_sales_ledger);
        if ($stmt_sales) {
            $salesParticulars = 'By ' . $customer;
            $stmt_sales->bind_param("issddisiisi", $temp_sales_slno, $tdate, $salesParticulars, $netAmountForSales, $new_sales_balance, $salesAccId, $remarks, $slno, $salesGroupId, $username, $vendorID);
            $stmt_sales->execute();
            $stmt_sales->close();
        }
    }

    // GST Ledger Entries (SGST/CGST for state code 21, IGST for others)
    if ($stateCode == 21 && $gstAmount > 0) {
        // SGST Entry
        $sgst_acc_res = $conn->query("SELECT AccId, GroupId FROM ledgermaster WHERE AccName LIKE 'SGST%' AND vendorID=$vendorID LIMIT 1");
        if ($sgst_acc_res && $sgst_acc_res->num_rows > 0) {
            $sgst_acc = $sgst_acc_res->fetch_assoc();
            $sgst_accid = $sgst_acc['AccId'];
            $sgst_groupid = $sgst_acc['GroupId'];

            // Delete previous entry
            $conn->query("DELETE FROM ledgertran WHERE AccId=" . $sgst_accid . " AND VoucherType='Invoice' AND VoucherSlno=" . intval($slno) . " AND vendorID=" . intval($vendorID));

            $sgst_last = $conn->query("SELECT MAX(Slno) as max_slno, Balance FROM ledgertran WHERE AccId=$sgst_accid AND vendorID=$vendorID ORDER BY Slno DESC LIMIT 1");
            $sgst_slno = 1;
            $sgst_balance = 0;
            if ($sgst_last && $sgst_last->num_rows > 0) {
                $sl = $sgst_last->fetch_assoc();
                $sgst_slno = $sl['max_slno'] + 1;
                $sgst_balance = $sl['Balance'];
            }
            $sgst_amount = $gstAmount / 2;
            $sgst_new_balance = $sgst_balance + $sgst_amount;

            $query_sgst = "INSERT INTO ledgertran (Slno, TDate, Particulars, Dr, Cr, Balance, AccId, Remarks, VoucherType, VoucherSlno, GroupId, Username, vendorID) VALUES (?, ?, ?, 0, ?, ?, ?, ?, 'Invoice', ?, ?, ?, ?)";
            $stmt_sgst = $conn->prepare($query_sgst);
            if ($stmt_sgst) {
                $gstParticulars = 'By ' . $customer;
                $stmt_sgst->bind_param("issddisiisi", $sgst_slno, $tdate, $gstParticulars, $sgst_amount, $sgst_new_balance, $sgst_accid, $remarks, $slno, $sgst_groupid, $username, $vendorID);
                $stmt_sgst->execute();
                $stmt_sgst->close();
            }
        }

        // CGST Entry
        $cgst_acc_res = $conn->query("SELECT AccId, GroupId FROM ledgermaster WHERE AccName LIKE 'CGST%' AND vendorID=$vendorID LIMIT 1");
        if ($cgst_acc_res && $cgst_acc_res->num_rows > 0) {
            $cgst_acc = $cgst_acc_res->fetch_assoc();
            $cgst_accid = $cgst_acc['AccId'];
            $cgst_groupid = $cgst_acc['GroupId'];

            // Delete previous entry
            $conn->query("DELETE FROM ledgertran WHERE AccId=" . $cgst_accid . " AND VoucherType='Invoice' AND VoucherSlno=" . intval($slno) . " AND vendorID=" . intval($vendorID));

            $cgst_last = $conn->query("SELECT MAX(Slno) as max_slno, Balance FROM ledgertran WHERE AccId=$cgst_accid AND vendorID=$vendorID ORDER BY Slno DESC LIMIT 1");
            $cgst_slno = 1;
            $cgst_balance = 0;
            if ($cgst_last && $cgst_last->num_rows > 0) {
                $cl = $cgst_last->fetch_assoc();
                $cgst_slno = $cl['max_slno'] + 1;
                $cgst_balance = $cl['Balance'];
            }
            $cgst_amount = $gstAmount / 2;
            $cgst_new_balance = $cgst_balance + $cgst_amount;

            $query_cgst = "INSERT INTO ledgertran (Slno, TDate, Particulars, Dr, Cr, Balance, AccId, Remarks, VoucherType, VoucherSlno, GroupId, Username, vendorID) VALUES (?, ?, ?, 0, ?, ?, ?, ?, 'Invoice', ?, ?, ?, ?)";
            $stmt_cgst = $conn->prepare($query_cgst);
            if ($stmt_cgst) {
                $gstParticulars = isset($gstParticulars) ? $gstParticulars : ('By ' . $customer);
                $stmt_cgst->bind_param("issddisiisi", $cgst_slno, $tdate, $gstParticulars, $cgst_amount, $cgst_new_balance, $cgst_accid, $remarks, $slno, $cgst_groupid, $username, $vendorID);
                $stmt_cgst->execute();
                $stmt_cgst->close();
            }
        }
    } elseif ($stateCode != 21 && $gstAmount > 0) {
        // IGST Entry for non-21 state codes
        $igst_acc_res = $conn->query("SELECT AccId, GroupId FROM ledgermaster WHERE AccName LIKE 'IGST%' AND vendorID=$vendorID LIMIT 1");
        if ($igst_acc_res && $igst_acc_res->num_rows > 0) {
            $igst_acc = $igst_acc_res->fetch_assoc();
            $igst_accid = $igst_acc['AccId'];
            $igst_groupid = $igst_acc['GroupId'];

            // Delete previous entry
            $conn->query("DELETE FROM ledgertran WHERE AccId=" . $igst_accid . " AND VoucherType='Invoice' AND VoucherSlno=" . intval($slno) . " AND vendorID=" . intval($vendorID));

            $igst_last = $conn->query("SELECT MAX(Slno) as max_slno, Balance FROM ledgertran WHERE AccId=$igst_accid AND vendorID=$vendorID ORDER BY Slno DESC LIMIT 1");
            $igst_slno = 1;
            $igst_balance = 0;
            if ($igst_last && $igst_last->num_rows > 0) {
                $il = $igst_last->fetch_assoc();
                $igst_slno = $il['max_slno'] + 1;
                $igst_balance = $il['Balance'];
            }
            $igst_new_balance = $igst_balance + $gstAmount;

            $query_igst = "INSERT INTO ledgertran (Slno, TDate, Particulars, Dr, Cr, Balance, AccId, Remarks, VoucherType, VoucherSlno, GroupId, Username, vendorID) VALUES (?, ?, ?, 0, ?, ?, ?, ?, 'Invoice', ?, ?, ?, ?)";
            $stmt_igst = $conn->prepare($query_igst);
            if ($stmt_igst) {
                $gstParticulars = isset($gstParticulars) ? $gstParticulars : ('By ' . $customer);
                $stmt_igst->bind_param("issddisiisi", $igst_slno, $tdate, $gstParticulars, $gstAmount, $igst_new_balance, $igst_accid, $remarks, $slno, $igst_groupid, $username, $vendorID);
                $stmt_igst->execute();
                $stmt_igst->close();
            }
        }
    }

    // Update related orders (if provided) to link this invoice
    $statusInvoiced = 'Invoiced';
    $orderIdSingle = isset($data['orderNo']) ? intval($data['orderNo']) : (isset($data['order_id']) ? intval($data['order_id']) : (isset($data['orderId']) ? intval($data['orderId']) : 0));
    $orderIdsArray = isset($data['order_ids']) && is_array($data['order_ids']) ? $data['order_ids'] : [];

    try {
        if ($orderIdSingle > 0) {
            $stmt_order = $conn->prepare("UPDATE orders SET InvoiceNo = ?, invoicedate = ?, order_status = ? WHERE order_id = ?");
            if (!$stmt_order) throw new Exception('Prepare failed (update orders): ' . $conn->error);
            $stmt_order->bind_param("issi", $slno, $tdate, $statusInvoiced, $orderIdSingle);
            if (!$stmt_order->execute()) throw new Exception('Execute failed (update orders): ' . $stmt_order->error);
            // Safety: ensure only one record was modified (orders is a master table)
            if ($stmt_order->affected_rows > 1) {
                throw new Exception('Update affected multiple orders for id: ' . $orderIdSingle);
            }
            $stmt_order->close();
        }

        if (!empty($orderIdsArray)) {
            $stmt_order = $conn->prepare("UPDATE orders SET InvoiceNo = ?, invoicedate = ?, order_status = ? WHERE order_id = ?");
            if (!$stmt_order) throw new Exception('Prepare failed (update orders array): ' . $conn->error);
            foreach ($orderIdsArray as $oid) {
                $oidInt = intval($oid);
                $stmt_order->bind_param("issi", $slno, $tdate, $statusInvoiced, $oidInt);
                if (!$stmt_order->execute()) throw new Exception('Execute failed (update orders array): ' . $stmt_order->error);
                if ($stmt_order->affected_rows > 1) {
                    throw new Exception('Update affected multiple orders for id: ' . $oidInt);
                }
            }
            $stmt_order->close();
        }
    } catch (Exception $e) {
        throw $e; // let outer catch roll back the transaction
    }

    $conn->commit();

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Invoice saved successfully',
        'slno' => $slno,
        'totalAmount' => $grandTotal,
        'operation' => $head_exists ? 'updated' : 'inserted'
    ]);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
