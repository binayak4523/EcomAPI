<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: text/html; charset=UTF-8');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require 'db.php';

$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

$conn->set_charset("utf8");

// Get parameters
$invNo = isset($_GET['invNo']) ? intval($_GET['invNo']) : 0;
$vendorID = isset($_GET['vendorID']) ? intval($_GET['vendorID']) : 0;

if (!$invNo || !$vendorID) {
    die('Invalid invoice number or vendor ID');
}

// Get invoice header details
$headerQuery = $conn->prepare("
    SELECT 
        ih.InvNo,
        ih.InvDate,
        ih.Party,
        ih.AccId,
        ih.LrNo,
        ih.TotalQty,
        ih.TotalGross,
        ih.TradeDiscount,
        ih.SpecialDiscount,
        ih.VatAmount,
        ih.Net,
        ih.RndUp,
        ih.GrandTotal,
        ih.AmountInText,
        ih.Freight,
        ih.MrpAmount,
        ih.BillType,
        ih.TotalCase,
        ih.add_ded,
        ih.outstanding,
        ih.OrderNo,
        ih.ChalanNo,
        ih.ChalanDate,
        ih.ShippingAddress1,
        ih.ShippingAddress2
    FROM invoicehead ih
    WHERE ih.InvNo = ? AND ih.vendorID = ?
");

if (!$headerQuery) {
    die('Prepare failed: ' . $conn->error);
}

$headerQuery->bind_param("ii", $invNo, $vendorID);
if (!$headerQuery->execute()) {
    die('Execute failed: ' . $headerQuery->error);
}

$headerResult = $headerQuery->get_result();
if ($headerResult->num_rows === 0) {
    die('Invoice not found');
}

$invoiceHeader = $headerResult->fetch_assoc();
$headerQuery->close();

// Get invoice details
$detailsQuery = $conn->prepare("
    SELECT 
        id.ItemName,
        id.Units,
        id.Pack,
        id.Qty,
        id.MRP,
        id.SaleRate,
        id.Gross,
        id.Vat,
        id.VatAmount,
        id.Net,
        id.Free_Qty,
        id.Tradediscount,
        id.SpecialDiscount,
        id.ProductCode
    FROM invoicedetails id
    WHERE id.InvNo = ? AND id.vendorID = ?
    ORDER BY id.slno ASC
");

if (!$detailsQuery) {
    die('Prepare failed: ' . $conn->error);
}

$detailsQuery->bind_param("ii", $invNo, $vendorID);
if (!$detailsQuery->execute()) {
    die('Execute failed: ' . $detailsQuery->error);
}

$detailsResult = $detailsQuery->get_result();
$invoiceDetails = [];
while ($row = $detailsResult->fetch_assoc()) {
    $invoiceDetails[] = $row;
}
$detailsQuery->close();

// Get ledger master information (Bill To details)
$ledgerInfo = null;
if (!empty($invoiceHeader['AccId'])) {
    $ledgerQuery = $conn->prepare("
        SELECT AccName, Address1, Address2
        FROM ledgermaster
        WHERE AccID = ?
        LIMIT 1
    ");
    
    if ($ledgerQuery) {
        $ledgerQuery->bind_param("i", $invoiceHeader['AccId']);
        if ($ledgerQuery->execute()) {
            $ledgerResult = $ledgerQuery->get_result();
            if ($ledgerResult->num_rows > 0) {
                $ledgerInfo = $ledgerResult->fetch_assoc();
            }
            $ledgerResult->close();
        }
        $ledgerQuery->close();
    }
}

// Get store information
$storeInfo = null;
if (!empty($invoiceHeader['OrderNo'])) {
    // Get store_id from orders table using OrderNo
    $orderQuery = $conn->prepare("
        SELECT store_id
        FROM orders
        WHERE order_id = ?
        LIMIT 1
    ");
    
    if ($orderQuery) {
        $orderQuery->bind_param("i", $invoiceHeader['OrderNo']);
        if ($orderQuery->execute()) {
            $orderResult = $orderQuery->get_result();
            if ($orderResult->num_rows > 0) {
                $orderData = $orderResult->fetch_assoc();
                $storeID = $orderData['store_id'];
                
                // Get store details using store_id
                $storeQuery = $conn->prepare("
                    SELECT Store_Name, saddress, phoneno, GSTNO
                    FROM store
                    WHERE ID = ?
                    LIMIT 1
                ");
                
                if ($storeQuery) {
                    $storeQuery->bind_param("i", $storeID);
                    if ($storeQuery->execute()) {
                        $storeResult = $storeQuery->get_result();
                        if ($storeResult->num_rows > 0) {
                            $storeInfo = $storeResult->fetch_assoc();
                        }
                        $storeResult->close();
                    }
                    $storeQuery->close();
                }
            }
            $orderResult->close();
        }
        $orderQuery->close();
    }
}

// Get company settings (logo and site name)
$settingsQuery = $conn->prepare("
    SELECT siteName, logo
    FROM settings
    LIMIT 1
");

if (!$settingsQuery) {
    die('Prepare failed: ' . $conn->error);
}

if (!$settingsQuery->execute()) {
    die('Execute failed: ' . $settingsQuery->error);
}

$settingsResult = $settingsQuery->get_result();
$settings = $settingsResult->fetch_assoc();
$settingsQuery->close();

$conn->close();

// Format functions
function formatDate($date) {
    if (empty($date)) return '-';
    return date('d-m-Y', strtotime($date));
}

function formatCurrency($amount) {
    return number_format($amount, 2, '.', ',');
}

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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice <?php echo $invoiceHeader['InvNo']; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
        }

        .company-logo-section {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }

        .company-logo-section img {
            max-width: 80px;
            max-height: 80px;
            object-fit: contain;
            border-radius: 50%;
        }

        .company-logo-section .company-name {
            font-size: 20px;
            font-weight: 600;
            color: #333;
        }

        .store-info-section {
            font-size: 12px;
            color: #333;
            line-height: 1.5;
            margin-top: 10px;
        }

        .store-info-section p {
            margin: 3px 0;
        }

        .store-info-section strong {
            color: #000;
        }

        .company-info h1 {
            font-size: 24px;
            color: #333;
            margin-bottom: 5px;
        }

        .company-info p {
            color: #666;
            font-size: 13px;
            margin-bottom: 3px;
        }

        .invoice-title {
            text-align: right;
        }

        .invoice-title h1 {
            font-size: 24px;
            color: #333;
            margin-bottom: 5px;
        }

        .invoice-title h2 {
            font-size: 32px;
            color: #0066cc;
            margin-bottom: 10px;
        }

        .invoice-title p {
            color: #666;
            font-size: 13px;
            margin-bottom: 3px;
        }

        .invoice-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .detail-section {
            border: 1px solid #ddd;
            padding: 15px;
            border-radius: 4px;
        }

        .detail-section h3 {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .detail-section p {
            font-size: 13px;
            color: #333;
            margin-bottom: 5px;
            line-height: 1.6;
        }

        .detail-section strong {
            color: #000;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .items-table thead {
            background: #f8f8f8;
            border-top: 2px solid #333;
            border-bottom: 2px solid #333;
        }

        .items-table th {
            padding: 12px;
            text-align: left;
            font-size: 12px;
            font-weight: 600;
            color: #333;
            text-transform: uppercase;
        }

        .items-table td {
            padding: 12px;
            font-size: 12px;
            border-bottom: 1px solid #eee;
            color: #333;
        }

        .items-table tr:last-child td {
            border-bottom: 2px solid #333;
        }

        .items-table .numeric {
            text-align: right;
        }

        .summary-section {
            width: 100%;
            margin-bottom: 20px;
        }

        .summary-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .summary-table tr:last-child td {
            border-bottom: 2px solid #333;
        }

        .summary-table td {
            padding: 10px;
            font-size: 12px;
            text-align: right;
            border-bottom: 1px solid #eee;
        }

        .summary-table td:first-child {
            text-align: left;
            font-weight: 500;
        }

        .summary-table tr.total td {
            font-weight: 600;
            font-size: 13px;
            background: #f8f8f8;
        }

        .amount-in-words {
            border: 1px solid #ddd;
            padding: 15px;
            background: #f8f8f8;
            margin-bottom: 20px;
            border-radius: 4px;
            font-size: 12px;
        }

        .amount-in-words strong {
            margin-right: 5px;
        }

        .footer {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
            margin-top: 40px;
            padding-top: 30px;
            border-top: 2px solid #333;
        }

        .footer-section {
            text-align: center;
        }

        .footer-section p {
            font-size: 12px;
            margin-bottom: 40px;
            color: #333;
        }

        .signature-line {
            border-top: 1px solid #333;
            display: inline-block;
            width: 80%;
        }

        .signature-label {
            font-size: 11px;
            margin-top: 5px;
            color: #666;
            font-weight: 600;
        }

        .print-button {
            margin-bottom: 20px;
            text-align: center;
        }

        .print-button button {
            padding: 10px 30px;
            background: #0066cc;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            cursor: pointer;
            font-weight: 600;
        }

        .print-button button:hover {
            background: #0052a3;
        }

        @media print {
            body {
                background: white;
                padding: 0;
            }

            .print-button {
                display: none;
            }

            .container {
                box-shadow: none;
                max-width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="print-button">
        <button onclick="window.print()">🖨️ Print Invoice</button>
    </div>

    <div class="container">
        <div class="header">
            <div class="company-info">
                <?php if (!empty($settings['logo'])): ?>
                    <div class="company-logo-section">
                        <img src="http://localhost/api/<?php echo htmlspecialchars($settings['logo']); ?>" alt="Company Logo">
                        <div class="company-name"><?php echo htmlspecialchars($settings['siteName'] ?? 'Company'); ?></div>
                    </div>
                <?php else: ?>
                    <div class="company-name"><?php echo htmlspecialchars($settings['siteName'] ?? 'Company'); ?></div>
                <?php endif; ?>
                
                <?php if (!empty($storeInfo)): ?>
                    <h3 style="font-size: 12px; color: #666; text-transform: uppercase; margin-top: 15px; margin-bottom: 10px; font-weight: 600;">Seller Information</h3>
                    <div class="store-info-section">
                        <p><strong><?php echo htmlspecialchars($storeInfo['Store_Name']); ?></strong></p>
                        <p><?php echo htmlspecialchars($storeInfo['saddress']); ?></p>
                        <?php if (!empty($storeInfo['phoneno'])): ?>
                            <p><strong>Phone:</strong> <?php echo htmlspecialchars($storeInfo['phoneno']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($storeInfo['GSTNO'])): ?>
                            <p><strong>GST:</strong> <?php echo htmlspecialchars($storeInfo['GSTNO']); ?></p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="invoice-title">
                <h2><?php echo strtoupper($invoiceHeader['BillType'] ?? 'SALES'); ?></h2>
                <h1>INVOICE</h1>
                <p><strong>Invoice #:</strong> <?php echo $invoiceHeader['InvNo']; ?></p>
                <p><strong>Invoice Date:</strong> <?php echo formatDate($invoiceHeader['InvDate']); ?></p>
            </div>
        </div>

        <div class="invoice-details">
            <div class="detail-section">
                <h3>Bill To</h3>
                <?php if (!empty($ledgerInfo)): ?>
                    <p><strong><?php echo htmlspecialchars($ledgerInfo['AccName']); ?></strong></p>
                    <?php if (!empty($ledgerInfo['Address1'])): ?>
                        <p><?php echo htmlspecialchars($ledgerInfo['Address1']); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($ledgerInfo['Address2'])): ?>
                        <p><?php echo htmlspecialchars($ledgerInfo['Address2']); ?></p>
                    <?php endif; ?>
                <?php else: ?>
                    <p><strong><?php echo htmlspecialchars($invoiceHeader['Party']); ?></strong></p>
                <?php endif; ?>
                <?php if (!empty($invoiceHeader['LrNo'])) : ?>
                    <p><strong>LR #:</strong> <?php echo htmlspecialchars($invoiceHeader['LrNo']); ?></p>
                <?php endif; ?>
                <?php if (!empty($invoiceHeader['ChalanNo'])) : ?>
                    <p><strong>Chalan #:</strong> <?php echo htmlspecialchars($invoiceHeader['ChalanNo']); ?></p>
                    <p><strong>Chalan Date:</strong> <?php echo formatDate($invoiceHeader['ChalanDate']); ?></p>
                <?php endif; ?>
                <?php if (!empty($invoiceHeader['OrderNo'])) : ?>
                    <p><strong>Order #:</strong> <?php echo htmlspecialchars($invoiceHeader['OrderNo']); ?></p>
                <?php endif; ?>
            </div>

            <div class="detail-section">
                <h3>Shipping Address</h3>
                <?php if (!empty($invoiceHeader['ShippingAddress1']) || !empty($invoiceHeader['ShippingAddress2'])): ?>
                    <?php if (!empty($invoiceHeader['ShippingAddress1'])): ?>
                        <p><?php echo htmlspecialchars($invoiceHeader['ShippingAddress1']); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($invoiceHeader['ShippingAddress2'])): ?>
                        <p><?php echo htmlspecialchars($invoiceHeader['ShippingAddress2']); ?></p>
                    <?php endif; ?>
                <?php else: ?>
                    <p>-</p>
                <?php endif; ?>
                <?php if (!empty($invoiceHeader['add_ded'])) : ?>
                    <p><strong>Additional/Deduction:</strong> <?php echo htmlspecialchars($invoiceHeader['add_ded']); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 5%;">SL</th>
                    <th style="width: 40%;">Item Name</th>
                    <th style="width: 8%;" class="numeric">Units</th>
                    <th style="width: 8%;" class="numeric">Pack</th>
                    <th style="width: 8%;" class="numeric">Qty</th>
                    <th style="width: 8%;" class="numeric">Free</th>
                    <th style="width: 10%;" class="numeric">Rate</th>
                    <th style="width: 12%;" class="numeric">Gross</th>
                    <th style="width: 10%;" class="numeric">Net</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($invoiceDetails as $index => $detail) : ?>
                    <tr>
                        <td><?php echo $index + 1; ?></td>
                        <td><?php echo htmlspecialchars($detail['ItemName']); ?></td>
                        <td class="numeric"><?php echo htmlspecialchars($detail['Units']); ?></td>
                        <td class="numeric"><?php echo formatCurrency($detail['Pack']); ?></td>
                        <td class="numeric"><?php echo formatCurrency($detail['Qty']); ?></td>
                        <td class="numeric"><?php echo formatCurrency($detail['Free_Qty']); ?></td>
                        <td class="numeric">₹<?php echo formatCurrency($detail['SaleRate']); ?></td>
                        <td class="numeric">₹<?php echo formatCurrency($detail['Gross']); ?></td>
                        <td class="numeric">₹<?php echo formatCurrency($detail['Net']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="summary-section">
            <table class="summary-table">
                <tr>
                    <td>Total Quantity</td>
                    <td><?php echo formatCurrency($invoiceHeader['TotalQty']); ?></td>
                </tr>
                <tr>
                    <td>Total Gross Amount</td>
                    <td>₹<?php echo formatCurrency($invoiceHeader['TotalGross']); ?></td>
                </tr>
                <?php if ($invoiceHeader['TradeDiscount'] > 0) : ?>
                    <tr>
                        <td>Trade Discount</td>
                        <td>-₹<?php echo formatCurrency($invoiceHeader['TradeDiscount']); ?></td>
                    </tr>
                <?php endif; ?>
                <?php if ($invoiceHeader['SpecialDiscount'] > 0) : ?>
                    <tr>
                        <td>Special Discount</td>
                        <td>-₹<?php echo formatCurrency($invoiceHeader['SpecialDiscount']); ?></td>
                    </tr>
                <?php endif; ?>
                <tr>
                    <td>Total After Discount</td>
                    <td>₹<?php echo formatCurrency($invoiceHeader['TotalGross'] - $invoiceHeader['TradeDiscount'] - $invoiceHeader['SpecialDiscount']); ?></td>
                </tr>
                <?php if ($invoiceHeader['VatAmount'] > 0) : ?>
                    <tr>
                        <td>GST/VAT Amount</td>
                        <td>₹<?php echo formatCurrency($invoiceHeader['VatAmount']); ?></td>
                    </tr>
                <?php endif; ?>
                <?php if ($invoiceHeader['Freight'] > 0) : ?>
                    <tr>
                        <td>Freight</td>
                        <td>₹<?php echo formatCurrency($invoiceHeader['Freight']); ?></td>
                    </tr>
                <?php endif; ?>
                <?php if ($invoiceHeader['RndUp'] != 0) : ?>
                    <tr>
                        <td>Round Up/Down</td>
                        <td><?php echo $invoiceHeader['RndUp'] > 0 ? '+' : ''; ?>₹<?php echo formatCurrency($invoiceHeader['RndUp']); ?></td>
                    </tr>
                <?php endif; ?>
                <tr class="total">
                    <td>GRAND TOTAL</td>
                    <td>₹<?php echo formatCurrency($invoiceHeader['GrandTotal']); ?></td>
                </tr>
            </table>
        </div>

        <?php if (!empty($invoiceHeader['AmountInText'])) : ?>
            <div class="amount-in-words">
                <strong>Amount in Words:</strong>
                <?php echo htmlspecialchars($invoiceHeader['AmountInText']); ?>
            </div>
        <?php endif; ?>

        <div class="footer">
            <div class="footer-section">
                <p>Authorized By</p>
                <div class="signature-line"></div>
                <div class="signature-label">Signature</div>
            </div>
            <div class="footer-section">
                <p>Prepared By</p>
                <div class="signature-line"></div>
                <div class="signature-label">Signature</div>
            </div>
            <div class="footer-section">
                <p>Received By</p>
                <div class="signature-line"></div>
                <div class="signature-label">Signature</div>
            </div>
        </div>
    </div>
</body>
</html>
