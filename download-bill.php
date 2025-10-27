<?php
require_once __DIR__ . '/dompdf/autoload.inc.php';

use Dompdf\Dompdf;

$custIndex = isset($_GET['cust']) ? intval($_GET['cust']) : 0;
$fruitIndex = isset($_GET['fruit']) ? intval($_GET['fruit']) : 0;
$entity = isset($_GET['entity']) ? intval($_GET['entity']) : 0;

$customers = json_decode(file_get_contents("customers.json"), true);
$customer = $customers[$custIndex];
$fruitFile = "fruits_$custIndex.json";
$fruits = json_decode(file_get_contents($fruitFile), true);
$fruit = $fruits[$fruitIndex];
$boxFile = "boxes_{$custIndex}_{$fruitIndex}.json";
$boxes = json_decode(file_get_contents($boxFile), true);
$box = $boxes[$entity];

// Calculate months, rem_days between arrival date and last withdrawal date
$start = strtotime($box['date']);

// Find the most recent removal date
$latestRemovalDate = $start; // Default to start date if no removals
if (!empty($box['removed'])) {
    foreach ($box['removed'] as $rem) {
        if (isset($rem['datetime']) && !empty($rem['datetime'])) {
            $removalTime = strtotime($rem['datetime']);
            if ($removalTime > $latestRemovalDate) {
                $latestRemovalDate = $removalTime;
            }
        }
    }
}

// Calculate difference between arrival and last withdrawal
$diff = $latestRemovalDate - $start;
$days = max(0, floor($diff / (60*60*24)));
$months = floor($days / 30);
$rem_days = $days % 30;
$origBox = isset($box['original_box']) ? $box['original_box'] : $box['box'];
$rentPerBox = $box['rent_per_box'];

// Calculate rent for withdrawn boxes
$withdrawnRent = 0;
$withdrawals = [];
if (!empty($box['removed'])) {
    foreach ($box['removed'] as $rem) {
        $dateValue = '-';
        if (isset($rem['datetime']) && !empty($rem['datetime'])) {
            $dateValue = date('d-m-Y H:i', strtotime($rem['datetime']));
        } elseif (isset($rem['date']) && !empty($rem['date'])) {
            $dateValue = date('d-m-Y H:i', strtotime($rem['date']));
        }
        $withdrawnRent += isset($rem['rent']) ? $rem['rent'] : 0;
        $withdrawals[] = [
            'datetime' => $dateValue,
            'count' => isset($rem['count']) ? $rem['count'] : 0,
            'rent' => isset($rem['rent']) ? $rem['rent'] : 0,
            'months' => isset($rem['months']) ? $rem['months'] : 1
        ];
    }
}

// Get total rent from fruit-detail.php logic
$totalRent = 0;
if (!empty($box['removed'])) {
    foreach ($box['removed'] as $rem) {
        $totalRent += isset($rem['rent']) ? $rem['rent'] : 0;
    }
}
if (!empty($box['paid'])) {
    foreach ($box['paid'] as $paid) {
        $totalRent -= $paid['amount'];
    }
    if ($totalRent < 0) $totalRent = 0;
}

// Calculate total amount paid and prepare payment history
$amountPaid = 0;
$paymentHistory = [];
if (!empty($box['paid'])) {
    foreach ($box['paid'] as $paid) {
        $amountPaid += $paid['amount'];
        $paymentDate = '-';
        if (isset($paid['datetime']) && !empty($paid['datetime'])) {
            $paymentDate = date('d-m-Y H:i', strtotime($paid['datetime']));
        }
        $paymentHistory[] = [
            'datetime' => $paymentDate,
            'amount' => $paid['amount']
        ];
    }
}

$startDate = date('d-m-Y H:i', strtotime($box['date']));
$lastWithdrawDate = !empty($withdrawals) ? end($withdrawals)['datetime'] : $startDate;
$totalBoxes = $origBox;

// HTML for PDF with proper bill format
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 20px; 
            font-size: 12px; 
            line-height: 1.4;
            color: #333;
        }
        
        .bill-header {
            text-align: center;
            border-bottom: 3px solid #667eea;
            padding-bottom: 15px;
            margin-bottom: 20px;
            page-break-inside: avoid;
        }
        
        .company-name {
            font-size: 24px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 5px;
        }
        
        .bill-title {
            font-size: 16px;
            color: #666;
            margin-bottom: 10px;
        }
        
        .bill-number {
            font-size: 14px;
            color: #333;
        }
        
        .info-section {
            display: table;
            width: 100%;
            margin-bottom: 20px;
            page-break-inside: avoid;
        }
        
        .info-left, .info-right {
            display: table-cell;
            width: 48%;
            vertical-align: top;
        }
        
        .info-right {
            text-align: right;
            padding-left: 4%;
        }
        
        .info-box {
            border: 1px solid #ddd;
            padding: 10px;
            border-radius: 5px;
            background: #f9f9f9;
        }
        
        .info-title {
            font-weight: bold;
            color: #667eea;
            margin-bottom: 8px;
            font-size: 13px;
        }
        
        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            page-break-inside: avoid;
        }
        
        .details-table th {
            background: #667eea;
            color: white;
            padding: 10px;
            text-align: left;
            font-weight: bold;
        }
        
        .details-table td {
            padding: 8px 10px;
            border-bottom: 1px solid #eee;
        }
        
        .details-table tr:nth-child(even) {
            background: #f9f9f9;
        }
        
        .transaction-section {
            margin-bottom: 15px;
            page-break-inside: avoid;
        }
        
        .section-header {
            background: #667eea;
            color: white;
            padding: 8px;
            font-weight: bold;
            text-align: center;
            page-break-after: avoid;
        }
        
        .transaction-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #ddd;
        }
        
        .transaction-table th {
            background: #667eea;
            color: white;
            padding: 8px;
            text-align: center;
            font-weight: bold;
            border: 1px solid #ddd;
            page-break-after: avoid;
        }
        
        .transaction-table td {
            padding: 6px 8px;
            text-align: center;
            border: 1px solid #ddd;
        }
        
        .transaction-table tr:nth-child(even) {
            background: #f9f9f9;
        }
        
        .total-row {
            background: #48bb78 !important;
            color: white;
            font-weight: bold;
            page-break-inside: avoid;
        }
        
        .summary-section {
            border: 2px solid #667eea;
            padding: 15px;
            margin-top: 20px;
            border-radius: 5px;
            page-break-inside: avoid;
        }
        
        .summary-title {
            font-size: 16px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 10px;
            text-align: center;
        }
        
        .summary-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .summary-table td {
            padding: 8px 10px;
            border-bottom: 1px solid #eee;
        }
        
        .summary-table .label {
            font-weight: bold;
            width: 60%;
        }
        
        .summary-table .amount {
            text-align: right;
            font-weight: bold;
            color: #333;
        }
        
        .final-amount {
            background: #48bb78;
            color: white;
            font-size: 14px;
        }
        
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 10px;
            page-break-inside: avoid;
        }
        
        .no-data {
            text-align: center;
            color: #666;
            font-style: italic;
            padding: 10px;
        }
        
        /* Page break rules */
        @media print {
            .page-break-before { page-break-before: always; }
            .page-break-after { page-break-after: always; }
            .no-page-break { page-break-inside: avoid; }
        }
    </style>
</head>
<body>
    <div class="bill-header">
        <div class="company-name">FRUIT CHAMBER STORAGE</div>
        <div class="bill-title">RENT BILL</div>
        <div class="bill-number">Bill No: FC-' . date('Ymd') . '-' . str_pad($entity + 1, 3, '0', STR_PAD_LEFT) . '</div>
    </div>
    
    <div class="info-section">
        <div class="info-left">
            <div class="info-box">
                <div class="info-title">CUSTOMER DETAILS</div>
                <strong>' . htmlspecialchars($customer['name']) . '</strong><br>
                Phone: ' . (isset($customer['phone']) ? htmlspecialchars($customer['phone']) : 'N/A') . '
            </div>
        </div>
        <div class="info-right">
            <div class="info-box">
                <div class="info-title">BILL DATE</div>
                <strong>' . date('d-m-Y H:i') . '</strong><br>
                <div class="info-title" style="margin-top: 8px;">FRUIT TYPE</div>
                <strong>' . htmlspecialchars($fruit['fruitname']) . '</strong>
            </div>
        </div>
    </div>
    
    <table class="details-table">
        <tr><th colspan="2">STORAGE DETAILS</th></tr>
        <tr><td><strong>Arrival Date & Time</strong></td><td>' . $startDate . '</td></tr>
        <tr><td><strong>Total Boxes Stored</strong></td><td>' . $totalBoxes . '</td></tr>
        <tr><td><strong>Current Boxes (Stored)</strong></td><td>' . (isset($box['box']) ? $box['box'] : '0') . '</td></tr>
        <tr><td><strong>Rent per Box per Month</strong></td><td>Rs. ' . number_format($rentPerBox) . '</td></tr>
        <tr><td><strong>Storage Duration</strong></td><td>' . $months . ' Months ' . $rem_days . ' Days</td></tr>
        <tr><td><strong>Last Withdrawal Date</strong></td><td>' . $lastWithdrawDate . '</td></tr>
    </table>';

// Withdrawal History
$html .= '<div class="transaction-section">
    <div class="section-header">BOX WITHDRAWAL HISTORY</div>
    <table class="transaction-table">
        <tr><th>Date & Time</th><th>Boxes</th><th>Months</th><th>Amount</th></tr>';

if (!empty($withdrawals)) {
    foreach ($withdrawals as $w) {
        $html .= '<tr>
            <td>' . htmlspecialchars($w['datetime']) . '</td>
            <td>' . htmlspecialchars($w['count']) . '</td>
            <td>' . htmlspecialchars($w['months']) . '</td>
            <td>Rs. ' . number_format($w['rent']) . '</td>
        </tr>';
    }
} else {
    $html .= '<tr><td colspan="4" class="no-data">No withdrawals made</td></tr>';
}

$html .= '<tr class="total-row">
            <td colspan="3"><strong>TOTAL WITHDRAWAL RENT</strong></td>
            <td><strong>Rs. ' . number_format($withdrawnRent) . '</strong></td>
        </tr>
    </table>
</div>';

// Payment History
$html .= '<div class="transaction-section">
    <div class="section-header">PAYMENT HISTORY</div>
    <table class="transaction-table">
        <tr><th>Date & Time</th><th>Amount Paid</th></tr>';

if (!empty($paymentHistory)) {
    foreach ($paymentHistory as $payment) {
        $html .= '<tr>
            <td>' . htmlspecialchars($payment['datetime']) . '</td>
            <td>Rs. ' . number_format($payment['amount']) . '</td>
        </tr>';
    }
} else {
    $html .= '<tr><td colspan="2" class="no-data">No payments made</td></tr>';
}

$html .= '<tr class="total-row">
            <td><strong>TOTAL AMOUNT PAID</strong></td>
            <td><strong>Rs. ' . number_format($amountPaid) . '</strong></td>
        </tr>
    </table>
</div>';

// Final Summary
$html .= '<div class="summary-section">
        <div class="summary-title">BILLING SUMMARY</div>
        <table class="summary-table">
            <tr><td class="label">Total Withdrawal Rent:</td><td class="amount">Rs. ' . number_format($withdrawnRent) . '</td></tr>
            <tr><td class="label">Total Amount Paid:</td><td class="amount">Rs. ' . number_format($amountPaid) . '</td></tr>
            <tr class="final-amount"><td class="label">Outstanding Balance:</td><td class="amount">Rs. ' . number_format($totalRent) . '</td></tr>
        </table>
    </div>
    
    <div class="footer">
        <p><strong>Thank you for using our storage services!</strong></p>
        <p>For any queries, please contact us at the above details.</p>
        <p>Generated on: ' . date('d-m-Y H:i:s') . '</p>
    </div>
</body>
</html>';

$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("Fruit-Bill.pdf", array("Attachment" => 1));
exit;