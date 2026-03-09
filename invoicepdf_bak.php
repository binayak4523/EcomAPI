<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');

try {
    if (!file_exists('fpdf/fpdf.php')) {
        throw new Exception('FPDF library not found at fpdf/fpdf.php');
    }
    require('fpdf/fpdf.php');
} catch (Exception $e) {
    ob_end_clean();
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => 'FPDF library not found: ' . $e->getMessage()]);
    exit;
}

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');

// Get order_id from request
$json = file_get_contents('php://input');
$requestData = json_decode($json, true);

if (!$requestData || !isset($requestData['order_id'])) {
    ob_end_clean();
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['error' => 'Order ID is required']);
    exit;
}

$orderId = $requestData['order_id'];

// Database connection
if (!file_exists('db.php')) {
    ob_end_clean();
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => 'Database configuration file not found']);
    exit;
}

include("db.php");
$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    ob_end_clean();
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]);
    exit;
}

// Get invoice source from settings
$settingsQuery = "SELECT invoice_sorce FROM settings LIMIT 1";
$settingsResult = $conn->query($settingsQuery);
$invoiceSource = 'orders'; // default

if ($settingsResult && $settingsResult->num_rows > 0) {
    $settings = $settingsResult->fetch_assoc();
    $invoiceSource = strtolower(trim($settings['invoice_sorce']));
}

$data = [];
$company = [];
$billingAddress = [];
$shippingAddress = [];

// Get company/store details
$storeQuery = "SELECT * FROM store WHERE id = 1 LIMIT 1";
$storeResult = $conn->query($storeQuery);
if ($storeResult && $storeResult->num_rows > 0) {
    $company = $storeResult->fetch_assoc();
}

// Fetch data based on invoice source
if ($invoiceSource === 'invoice') {
    // Get data from InvoiceHead and InvoiceDetails tables
    $invoiceHeadQuery = "SELECT * FROM InvoiceHead WHERE InvNo = ? OR OrderNo = ?";
    $stmt = $conn->prepare($invoiceHeadQuery);
    $stmt->bind_param("ss", $orderId, $orderId);
    $stmt->execute();
    $invoiceHeadResult = $stmt->get_result();
    
    if ($invoiceHeadResult && $invoiceHeadResult->num_rows > 0) {
        $invoiceHead = $invoiceHeadResult->fetch_assoc();
        
        // Get invoice details (items)
        $invoiceDetailsQuery = "SELECT * FROM InvoiceDetails WHERE InvNo = ?";
        $stmtDetails = $conn->prepare($invoiceDetailsQuery);
        $stmtDetails->bind_param("s", $invoiceHead['InvNo']);
        $stmtDetails->execute();
        $invoiceDetailsResult = $stmtDetails->get_result();
        
        $items = [];
        while ($row = $invoiceDetailsResult->fetch_assoc()) {
            $items[] = [
                'name' => $row['Itemname'] . ' - ' . $row['Size'],
                'quantity' => $row['Qty'],
                'price' => $row['SaleRate'],
                'discount' => $row['DiscountAmount'] ?? 0,
                'tax_rate' => $row['Vat'] ?? 18,
                'hsn' => $row['HSN'] ?? ''
            ];
        }
        
        // Get party/customer details if AccId is available
        if (!empty($invoiceHead['AccId'])) {
            $partyQuery = "SELECT * FROM accountmaster WHERE AccId = ?";
            $stmtParty = $conn->prepare($partyQuery);
            $stmtParty->bind_param("i", $invoiceHead['AccId']);
            $stmtParty->execute();
            $partyResult = $stmtParty->get_result();
            
            if ($partyResult && $partyResult->num_rows > 0) {
                $party = $partyResult->fetch_assoc();
                $billingAddress = [
                    'name' => $party['Name'] ?? $invoiceHead['Party'],
                    'address' => $party['Address'] ?? '',
                    'city' => $party['City'] ?? '',
                    'state' => $party['State'] ?? 'Maharashtra',
                    'pincode' => $party['Pin'] ?? '',
                    'country' => 'India'
                ];
            } else {
                $billingAddress = [
                    'name' => $invoiceHead['Party'],
                    'address' => '',
                    'city' => '',
                    'state' => 'Maharashtra',
                    'pincode' => '',
                    'country' => 'India'
                ];
            }
            $stmtParty->close();
        }
        
        $shippingAddress = [
            'name' => $invoiceHead['ReceiverName'] ?? $billingAddress['name'],
            'address' => $invoiceHead['ShippingAddress1'] . ' ' . ($invoiceHead['ShippingAddress2'] ?? ''),
            'city' => $invoiceHead['ShippingCity'] ?? '',
            'state' => $invoiceHead['ShippingState'] ?? 'Maharashtra',
            'pincode' => $invoiceHead['ShippingPINCode'] ?? '',
            'country' => 'India'
        ];
        
        $data = [
            'order_id' => $invoiceHead['OrderNo'] ?? $invoiceHead['InvNo'],
            'invoice_no' => $invoiceHead['InvNo'],
            'order_details' => [
                'order_date' => $invoiceHead['InvDate'],
                'total_price' => $invoiceHead['GrandTotal'],
                'order_status' => 'Completed'
            ],
            'items' => $items
        ];
        
        $stmtDetails->close();
    } else {
        ob_end_clean();
        $conn->close();
        header('Content-Type: application/json');
        http_response_code(404);
        echo json_encode(['error' => 'Invoice not found']);
        exit;
    }
    $stmt->close();
    
} else {
    // Get data from orders and order_items tables (default)
    $orderQuery = "SELECT * FROM orders WHERE order_id = ?";
    $stmt = $conn->prepare($orderQuery);
    $stmt->bind_param("i", $orderId);
    $stmt->execute();
    $orderResult = $stmt->get_result();
    
    if ($orderResult && $orderResult->num_rows > 0) {
        $order = $orderResult->fetch_assoc();
        
        // Get order items - try to join with products table if it exists
        $itemsQuery = "SELECT oi.* FROM order_items oi WHERE oi.order_id = ?";
        $stmtItems = $conn->prepare($itemsQuery);
        $stmtItems->bind_param("i", $orderId);
        $stmtItems->execute();
        $itemsResult = $stmtItems->get_result();
        
        $items = [];
        while ($row = $itemsResult->fetch_assoc()) {
            // Try to get product details if product_id exists
            $productName = 'Product';
            $productPrice = 0;
            
            if (!empty($row['product_id'])) {
                // Replace the products table query with item_master
                $productQuery = "SELECT * FROM item_master WHERE id = ? LIMIT 1";
                $stmtProduct = $conn->prepare($productQuery);
                if ($stmtProduct) {
                    $productId = $row['product_id'];
                    $stmtProduct->bind_param("i", $productId);
                    $stmtProduct->execute();
                    $productResult = $stmtProduct->get_result();
                    
                    if ($productResult && $productResult->num_rows > 0) {
                        $product = $productResult->fetch_assoc();
                        // Adjust column names to match item_master table
                        $productName = $product['itemname'] ?? $product['name'] ?? 'Product';
                        $productPrice = $product['sale_price'] ?? $product['price'] ?? 0;
                    }
                    $stmtProduct->close();
                }
            }
            
            // Use data from order_items or fallback to product data
            $items[] = [
                'name' => $productName,
                'quantity' => $row['quantity'] ?? 1,
                'price' => $row['price'] ?? $productPrice,
                'product_id' => $row['product_id'] ?? null
            ];
        }
        
        // Get billing address
        if (!empty($order['billingad_id'])) {
            $billingQuery = "SELECT * FROM addresses WHERE id = ?";
            $stmtBilling = $conn->prepare($billingQuery);
            $stmtBilling->bind_param("i", $order['billingad_id']);
            $stmtBilling->execute();
            $billingResult = $stmtBilling->get_result();
            
            if ($billingResult && $billingResult->num_rows > 0) {
                $billing = $billingResult->fetch_assoc();
                $billingAddress = [
                    'name' => $billing['name'] ?? '',
                    'address' => $billing['address'] ?? $order['shipping_address1'],
                    'city' => $billing['city'] ?? '',
                    'state' => $billing['state'] ?? 'Maharashtra',
                    'pincode' => $billing['pincode'] ?? $order['pin_code'],
                    'country' => 'India'
                ];
            }
            $stmtBilling->close();
        }
        
        // Get shipping address
        if (!empty($order['shippingad_id'])) {
            $shippingQuery = "SELECT * FROM addresses WHERE id = ?";
            $stmtShipping = $conn->prepare($shippingQuery);
            $stmtShipping->bind_param("i", $order['shippingad_id']);
            $stmtShipping->execute();
            $shippingResult = $stmtShipping->get_result();
            
            if ($shippingResult && $shippingResult->num_rows > 0) {
                $shipping = $shippingResult->fetch_assoc();
                $shippingAddress = [
                    'name' => $shipping['name'] ?? '',
                    'address' => $shipping['address'] ?? $order['shipping_address1'] . ' ' . ($order['shipping_address2'] ?? ''),
                    'city' => $shipping['city'] ?? '',
                    'state' => $shipping['state'] ?? 'Maharashtra',
                    'pincode' => $shipping['pincode'] ?? $order['pin_code'],
                    'country' => 'India'
                ];
            }
            $stmtShipping->close();
        }
        
        // If addresses not found in address table, use order fields
        if (empty($billingAddress)) {
            $billingAddress = [
                'name' => 'Customer',
                'address' => $order['shipping_address1'] ?? '',
                'city' => '',
                'state' => 'Maharashtra',
                'pincode' => $order['pin_code'] ?? '',
                'country' => 'India'
            ];
        }
        
        if (empty($shippingAddress)) {
            $shippingAddress = $billingAddress;
        }
        
        $data = [
            'order_id' => $order['order_id'],
            'invoice_no' => $order['invoiceNo'] ?? 'INV-' . $order['order_id'],
            'order_details' => [
                'order_date' => $order['order_date'],
                'total_price' => $order['total_price'],
                'order_status' => $order['order_status']
            ],
            'items' => $items
        ];
        
        $stmtItems->close();
    } else {
        ob_end_clean();
        $conn->close();
        header('Content-Type: application/json');
        http_response_code(404);
        echo json_encode(['error' => 'Order not found']);
        exit;
    }
    $stmt->close();
}

$conn->close();

// Save debug data
file_put_contents('debug_invoice.json', json_encode([
    'invoice_source' => $invoiceSource,
    'data' => $data,
    'billing' => $billingAddress,
    'shipping' => $shippingAddress,
    'company' => $company
], JSON_PRETTY_PRINT));

class PDF_Invoice extends FPDF {
    public $billingAddress = [];
    public $shippingAddress = [];
    public $company = [];
    public $totalAmount = 0;
    public $subtotal = 0;
    public $totalTax = 0;

    function __construct($billingAddress = [], $shippingAddress = [], $company = []) {
        parent::__construct();
        $this->billingAddress = $billingAddress;
        $this->shippingAddress = $shippingAddress;
        $this->company = $company;
    }

    function safe_string($str) {
        return iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $str);
    }


// ...existing code...
    function Header() {
        $logoHeight = 15; // mm
        $logoX = 10;
        $logoY = 10;
        $padding = 5;
        $usedWidth = 0;

        // If logo exists, compute display width to preserve aspect ratio and draw it.
        if (!empty($this->company['logo']) && file_exists($this->company['logo'])) {
            $logoPath = $this->company['logo'];
            $size = @getimagesize($logoPath);
            if ($size && isset($size[0]) && isset($size[1]) && $size[1] > 0) {
                $pxW = $size[0];
                $pxH = $size[1];
                // compute width in mm so height = $logoHeight (ratio pxW/pxH)
                $usedWidth = ($pxW / $pxH) * $logoHeight;
                // ensure a minimum and maximum width to avoid layout break
                $usedWidth = max(12, min($usedWidth, 40));
            } else {
                $usedWidth = 15;
            }
            // Draw the logo with explicit width and height (preserves aspect ratio)
            $this->Image($logoPath, $logoX, $logoY, $usedWidth, $logoHeight);
        } else {
            // Placeholder when logo missing
            $usedWidth = 15;
            $this->SetLineWidth(0.3);
            $this->Rect($logoX, $logoY, $usedWidth, $logoHeight);
            $this->SetFont('Arial', '', 7);
            $this->SetXY($logoX, $logoY + ($logoHeight / 2) - 2);
            $this->Cell($usedWidth, 4, 'LOGO', 0, 0, 'C');
        }

        // Position store name to the right of logo/placeholder with padding
        $startX = $logoX + $usedWidth + $padding;
        $this->SetXY($startX, $logoY);

        $this->SetFont('Arial','B',20);
        $storeName = !empty($this->company['Store_Name']) ? $this->company['Store_Name'] : 'CT Kart';

        // calculate available width for the store name (page width minus right margin)
        $availWidth = $this->GetPageWidth() - $startX - $this->rMargin;
        // Print store name (single line, clipped if too long)
        $this->Cell($availWidth, 8, $this->safe_string($storeName), 0, 1, 'L');

        // Subtitle / invoice type lines under store name, right aligned within the same available width
        $this->SetFont('Arial','B',11);
        $this->SetXY($startX, $logoY + 8);
        $this->Cell($availWidth, 4, 'Tax Invoice/Bill of Supply/Cash Memo', 0, 1, 'R');

        $this->SetFont('Arial','',9);
        $this->SetXY($startX, $logoY + 12);
        $this->Cell($availWidth, 4, '(Original for Recipient)', 0, 1, 'R');

        $this->Ln(3);
    }
// ...existing code...

    function SellerBuyerInfo() {
        $company = $this->company;
        $billing = $this->billingAddress;
        $shipping = $this->shippingAddress;
        
        $this->SetFont('Arial','B',9);
        $this->Cell(95,5,'Sold By :',0,0,'L');
        $this->Cell(95,5,'Billing Address :',0,1,'L');
        
        $this->SetFont('Arial','',8);
        
        $this->Cell(95,4,$this->safe_string($company['Store_Name'] ?? 'CT Kart'),0,0,'L');
        $this->Cell(95,4,$this->safe_string($billing['name'] ?? ''),0,1,'L');
        
        $this->Cell(95,4,$this->safe_string($company['saddress'] ?? 'Store Address'),0,0,'L');
        $this->Cell(95,4,$this->safe_string($billing['address'] ?? ''),0,1,'L');
        
        //$this->Cell(95,4,$this->safe_string(($company['city'] ?? 'CHAKAN') . ', ' . ($company['state'] ?? 'MAHARASHTRA') . ', ' . ($company['pincode'] ?? '410501')),0,0,'L');
        //$this->Cell(95,4,$this->safe_string(($billing['city'] ?? '') . ', ' . ($billing['state'] ?? 'Maharashtra') . ' ' . ($billing['pincode'] ?? '')),0,1,'L');
        
        $this->Cell(95,4,'',0,0,'L');
        //$this->Cell(95,4,'State/UT Code: 27',0,1,'L');
        
        $this->Ln(1);
        
        $this->SetFont('Arial','B',8);
        $this->Cell(95,4,'PAN No: ' . ($company['PAN'] ?? 'xxxxxxxxxxxxxxxxxxx'),0,0,'L');
        $this->SetFont('Arial','B',9);
        $this->Cell(95,4,'Shipping Address :',0,1,'L');
        
        $this->SetFont('Arial','B',8);
        $this->Cell(95,4,'GST Registration No: ' . ($company['GSTNO'] ?? 'xxxxxxxxxxxxx'),0,0,'L');
        $this->SetFont('Arial','',8);
        $this->Cell(95,4,$this->safe_string($shipping['name'] ?? ''),0,1,'L');
        
        $this->Cell(95,4,'',0,0);
        $this->Cell(95,4,$this->safe_string($shipping['address'] ?? ''),0,1,'L');
        
        $this->Cell(95,4,'',0,0);
        $this->Cell(95,4,$this->safe_string(($shipping['city'] ?? '') . ', ' . ($shipping['state'] ?? 'Maharashtra') . ' ' . ($shipping['pincode'] ?? '')),0,1,'L');
        
        $this->Cell(95,4,'',0,0);
        $this->Cell(95,4,'',0,1,'L');
        
        $this->Cell(95,4,'',0,0);
        $this->Cell(95,4,'State/UT Code: 27',0,1,'L');
        
        $this->Cell(95,4,'',0,0);
        $this->Cell(95,4,'Place of supply: ' . $this->safe_string($shipping['state'] ?? 'Maharashtra'),0,1,'L');
        
        $this->Cell(95,4,'',0,0);
        $this->Cell(95,4,'Place of delivery: ' . $this->safe_string($shipping['state'] ?? 'Maharashtra'),0,1,'L');
        
        $this->Ln(2);
    }

    function InvoiceDetails($orderData) {
        $this->SetFont('Arial','',8);
        
        $orderId = $orderData['order_id'] ?? 'N/A';
        $invoiceNo = $orderData['invoice_no'] ?? 'INV-' . $orderId;
        
        $this->Cell(95,4,'Order Number: ' . $orderId,0,0,'L');
        $this->Cell(95,4,'Invoice Number: ' . $invoiceNo,0,1,'L');
        
        $orderDate = isset($orderData['order_details']['order_date']) ? 
            date('d.m.Y', strtotime($orderData['order_details']['order_date'])) : 
            date('d.m.Y');
        
        $this->Cell(95,4,'Order Date: ' . $orderDate,0,0,'L');
        $this->Cell(95,4,'Invoice Details: MH-' . $invoiceNo,0,1,'L');
        
        $this->Cell(95,4,'',0,0);
        $this->Cell(95,4,'Invoice Date: ' . date('d.m.Y'),0,1,'L');
        
        $this->Ln(3);
    }

    function ItemTable($items) {
        $this->SetFillColor(255, 255, 255);
        $this->SetFont('Arial','B',8);
        
        $this->Cell(8,7,'Sl.',1,0,'C');
        $this->Cell(52,7,'Description',1,0,'C');
        $this->Cell(20,7,'Unit Price',1,0,'C');
        $this->Cell(20,7,'Discount',1,0,'C');
        $this->Cell(20,7,'Net Amount',1,0,'C');
        $this->Cell(15,7,'Tax Rate',1,0,'C');
        $this->Cell(15,7,'Tax Type',1,0,'C');
        $this->Cell(20,7,'Tax Amount',1,0,'C');
        $this->Cell(20,7,'Total Amount',1,1,'C');
        
        $this->SetFont('Arial','',8);
        $sl = 1;
        $this->subtotal = 0;
        $this->totalTax = 0;
        
        foreach($items as $item) {
            $quantity = intval($item['quantity']);
            $unitPrice = floatval($item['price']);
            $discount = floatval($item['discount'] ?? 0);
            $netAmount = ($unitPrice * $quantity) - $discount;
            
            $cgstRate = 9;
            $sgstRate = 9;
            $cgstAmount = ($netAmount * $cgstRate) / 100;
            $sgstAmount = ($netAmount * $sgstRate) / 100;
            $totalTaxAmount = $cgstAmount + $sgstAmount;
            $totalAmount = $netAmount + $totalTaxAmount;
            
            $y = $this->GetY();
            
            $this->Cell(8,10,$sl++,1,0,'C');
            
            $x = $this->GetX();
            $productName = $this->safe_string($item['name']);
            if (strlen($productName) > 50) {
                $productName = substr($productName, 0, 47) . '...';
            }
            $this->MultiCell(52, 5, $productName . "\n(Qty: " . $quantity . ')', 1, 'L');
            $descHeight = $this->GetY() - $y;
            
            $this->SetXY($x + 52, $y);
            
            $this->Cell(20,$descHeight,number_format($unitPrice, 2),1,0,'R');
            $this->Cell(20,$descHeight,number_format($discount, 2),1,0,'R');
            $this->Cell(20,$descHeight,number_format($netAmount, 2),1,0,'R');
            
            $taxX = $this->GetX();
            $this->Cell(15,$descHeight/2,$cgstRate . '%',1,0,'C');
            $this->SetXY($taxX, $y + $descHeight/2);
            $this->Cell(15,$descHeight/2,$sgstRate . '%',1,0,'C');
            $this->SetXY($taxX + 15, $y);
            
            $typeX = $this->GetX();
            $this->Cell(15,$descHeight/2,'CGST',1,0,'C');
            $this->SetXY($typeX, $y + $descHeight/2);
            $this->Cell(15,$descHeight/2,'SGST',1,0,'C');
            $this->SetXY($typeX + 15, $y);
            
            $amtX = $this->GetX();
            $this->Cell(20,$descHeight/2,number_format($cgstAmount, 2),1,0,'R');
            $this->SetXY($amtX, $y + $descHeight/2);
            $this->Cell(20,$descHeight/2,number_format($sgstAmount, 2),1,0,'R');
            $this->SetXY($amtX + 20, $y);
            
            $this->Cell(20,$descHeight,number_format($totalAmount, 2),1,1,'R');
            
            $this->subtotal += $netAmount;
            $this->totalTax += $totalTaxAmount;
        }
        
        $this->SetFont('Arial','B',9);
        $this->Cell(100,6,'',0,0,'R');
        $this->Cell(70,6,'TOTAL Rs.',1,0,'R');
        //$this->Cell(50,6,'',1,0,'C');
        $this->totalAmount = $this->subtotal + $this->totalTax;
        $this->Cell(20,6,number_format($this->totalAmount, 2),1,0,'R');
        
        $this->Ln(3);
    }

    function convertNumberToWords($number) {
        $ones = array('', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen');
        $tens = array('', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety');
        
        $number = intval($number);
        
        if ($number == 0) return 'Zero';
        if ($number < 20) return $ones[$number];
        if ($number < 100) return trim($tens[intval($number / 10)] . ' ' . $ones[$number % 10]);
        if ($number < 1000) return trim($ones[intval($number / 100)] . ' Hundred ' . $this->convertNumberToWords($number % 100));
        if ($number < 100000) return trim($this->convertNumberToWords(intval($number / 1000)) . ' Thousand ' . $this->convertNumberToWords($number % 1000));
        if ($number < 10000000) return trim($this->convertNumberToWords(intval($number / 100000)) . ' Lakh ' . $this->convertNumberToWords($number % 100000));
        
        return trim($this->convertNumberToWords(intval($number / 10000000)) . ' Crore ' . $this->convertNumberToWords($number % 10000000));
    }

    function Footer() {
        $this->SetFont('Arial','B',9);
        $this->Cell(0,5,'Amount in Words:',0,1,'L');
        
        $this->SetFont('Arial','B',9);
        $amountInWords = trim($this->convertNumberToWords($this->totalAmount)) . ' only';
        $this->Cell(0,5,$amountInWords,0,1,'L');
        
        $this->Ln(2);
        
        $this->SetFont('Arial','',8);
        $companyName = !empty($this->company['Store_Name']) ? $this->company['Store_Name'] : 'CT Kart';
        $this->Cell(0,4,'For ' . $this->safe_string($companyName) . ':',0,1,'L');
        
        $this->Ln(8);
        $this->Cell(0,4,'Authorized Signatory',0,1,'L');
        $this->Ln(2);
        
        $this->SetFont('Arial','I',7);
        $this->Cell(0,3,'Whether tax is payable under reverse charge - No',0,1,'L');
        $this->Ln(1);
        $this->SetFont('Arial','',7);
        $this->Cell(0,3,'Page 1 of 2',0,0,'R');
    }
}

try {
    ob_end_clean();
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="invoice-' . ($data['order_id'] ?? 'unknown') . '.pdf"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    
    $pdf = new PDF_Invoice($billingAddress, $shippingAddress, $company);
    $pdf->AddPage();
    $pdf->SellerBuyerInfo();
    $pdf->InvoiceDetails($data);
    $pdf->ItemTable($data['items']);
    $pdf->Output('I', 'invoice-' . ($data['order_id'] ?? 'unknown') . '.pdf');
} catch (Exception $e) {
    ob_end_clean();
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => 'PDF generation failed: ' . $e->getMessage()]);
    error_log('PDF Error: ' . $e->getMessage());
    exit;
}
?>