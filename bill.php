<?php
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
$withdrawnRent = 0; // <-- Add this line to initialize the variable
$withdrawals = [];
if (!empty($box['removed'])) {
    foreach ($box['removed'] as $rem) {
        // Defensive: check if keys exist and are not null
        $dateValue = '-';
        if (isset($rem['datetime']) && !empty($rem['datetime'])) {
            $dateValue = date('d-m-Y H:i', strtotime($rem['datetime']));
        } elseif (isset($rem['date']) && !empty($rem['date'])) {
            $dateValue = date('d-m-Y H:i', strtotime($rem['date']));
        }
        $withdrawnRent += isset($rem['rent']) ? $rem['rent'] : 0; // <-- Add this line to calculate withdrawnRent
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Fruit Chamber Rent Bill</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
  <style>
    * { box-sizing: border-box; }
    body { 
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      margin: 0; 
      padding: 10px; 
      line-height: 1.4;
      min-height: 100vh;
    }
    
    .bill-container {
      background: #fff;
      max-width: 1000px;
      margin: 0 auto;
      padding: 20px;
      border-radius: 15px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
      font-size: 14px;
      position: relative;
    }
    
    .bill-container::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: linear-gradient(90deg, #667eea, #764ba2);
    }
    
    .header-section {
      display: flex;
      justify-content: flex-start;
      align-items: center;
      margin-bottom: 15px;
      padding-bottom: 10px;
      border-bottom: 1px solid #e8ecf3;
    }
    
    .back-btn {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      font-size: 13px;
      font-weight: 600;
      border: none;
      border-radius: 8px;
      padding: 8px 16px;
      cursor: pointer;
      transition: all 0.2s ease;
      text-decoration: none;
    }
    
    .back-btn:hover {
      transform: translateY(-1px);
      box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
    }
    
    h1 {
      text-align: center;
      margin: 15px 0 20px 0;
      font-size: 24px;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      font-weight: 700;
    }
    
    .content-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
      margin-bottom: 20px;
    }
    
    .section { 
      background: #f8fafe;
      border-radius: 10px;
      padding: 15px;
      border: 1px solid #e8ecf3;
      border-left: 4px solid #667eea;
    }
    
    .section-title {
      font-weight: 600;
      font-size: 16px;
      margin-bottom: 12px;
      color: #2d3748;
      text-transform: uppercase;
      letter-spacing: 0.3px;
    }
    
    .full-width {
      grid-column: 1 / -1;
    }
    
    .table-container {
      overflow-x: auto;
      border-radius: 8px;
      overflow: hidden;
    }
    
    table { 
      width: 100%; 
      border-collapse: collapse; 
      font-size: 13px;
      background: #fff;
    }
    
    table th, table td { 
      padding: 10px 12px; 
      text-align: left;
      border-bottom: 1px solid #f1f5f9;
    }
    
    table th {
      background: #f7fafc;
      font-weight: 600;
      color: #2d3748;
      font-size: 12px;
      text-transform: uppercase;
      letter-spacing: 0.3px;
    }
    
    table td {
      color: #4a5568;
      font-weight: 500;
    }
    
    table tr:last-child th,
    table tr:last-child td {
      border-bottom: none;
    }
    
    .info-table th {
      width: 45%;
      background: #f7fafc;
      border-right: 1px solid #e8ecf3;
    }
    
    .withdrawal-table th,
    .payment-table th {
      background: #667eea;
      color: white;
      text-align: center;
    }
    
    .withdrawal-table td,
    .payment-table td {
      text-align: center;
      padding: 8px 6px;
    }
    
    .total-row th,
    .total-row td { 
      background: #48bb78;
      color: white;
      font-weight: 700;
      text-align: center;
    }
    
    .amount {
      font-weight: 600;
      color: #2d3748;
    }
    
    .positive-amount {
      color: #38a169;
    }
    
    .formula {
      margin-top: 15px;
      font-family: 'SF Mono', monospace;
      background: #f7fafc;
      border: 1px solid #e8ecf3;
      border-left: 4px solid #667eea;
      padding: 12px;
      font-size: 12px;
      border-radius: 6px;
      line-height: 1.6;
    }
    
    .browser-tip {
      color: #718096;
      font-size: 12px;
      text-align: center;
      margin: 15px 0;
      font-style: italic;
    }
    
    .download-section {
      text-align: center;
      margin-top: 20px;
      padding-top: 15px;
      border-top: 1px solid #e8ecf3;
    }
    
    .download-btn {
      background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
      color: white;
      font-size: 14px;
      font-weight: 600;
      border: none;
      border-radius: 8px;
      padding: 10px 24px;
      cursor: pointer;
      transition: all 0.2s ease;
      text-decoration: none;
    }
    
    .download-btn:hover {
      transform: translateY(-1px);
      box-shadow: 0 6px 20px rgba(72, 187, 120, 0.3);
    }
    
    .no-data {
      text-align: center;
      color: #718096;
      font-style: italic;
      padding: 15px;
    }
    
    /* Mobile Optimizations */
    @media (max-width: 768px) {
      body {
        padding: 5px;
      }
      
      .bill-container { 
        padding: 15px;
        font-size: 13px;
      }
      
      h1 { 
        font-size: 20px;
        margin: 10px 0 15px 0;
      }
      
      .content-grid {
        grid-template-columns: 1fr;
        gap: 15px;
      }
      
      .section {
        padding: 12px;
      }
      
      .section-title { 
        font-size: 14px;
        margin-bottom: 10px;
      }
      
      table th, table td { 
        padding: 8px 6px;
        font-size: 12px;
      }
      
      .formula {
        font-size: 11px;
        padding: 10px;
      }
      
      .download-btn, .back-btn {
        width: 100%;
        padding: 12px;
        font-size: 14px;
      }
    }
    
    @media (max-width: 480px) {
      .responsive-table thead {
        display: none;
      }
      
      .responsive-table tr {
        border: 1px solid #e8ecf3;
        display: block;
        margin-bottom: 10px;
        padding: 12px;
        border-radius: 8px;
        background: #fff;
      }
      
      .responsive-table td {
        border: none;
        display: block;
        font-size: 12px;
        text-align: left;
        padding: 4px 0;
      }
      
      .responsive-table td:before {
        content: attr(data-label) ": ";
        font-weight: 600;
        color: #667eea;
        display: inline-block;
        width: 120px;
      }
      
      .total-row {
        background: #48bb78;
        color: white;
      }
    }
    
    @media print {
      body { 
        background: white; 
        margin: 0; 
        padding: 0;
      }
      .bill-container { 
        box-shadow: none; 
        border: 1px solid #ddd;
        margin: 0;
        padding: 20px;
      }
      .download-btn, .browser-tip, .back-btn, .header-section { 
        display: none;
      }
      .content-grid {
        grid-template-columns: 1fr 1fr;
      }
    }
  </style>
</head>
<body>
<div class="bill-container" id="bill-content">
  <div class="header-section">
    <button class="back-btn" onclick="goBackToFruitDetail()">← Back</button>
  </div>
  
  <h1 id="bill-heading">Fruit Chamber Rent Bill</h1>
  
  <div class="section">
    <div class="section-title" id="customer-title">Customer Details</div>
    <div class="table-container">
      <table class="responsive-table">
        <tr>
          <th data-label="Field">Customer Name</th>
          <td data-label="Value"><?= htmlspecialchars($customer['name']) ?></td>
        </tr>
        <tr>
          <th data-label="Field">Phone Number</th>
          <td data-label="Value"><?= isset($customer['phone']) ? htmlspecialchars($customer['phone']) : '-' ?></td>
        </tr>
      </table>
    </div>
  </div>
  
  <div class="section">
    <div class="section-title" id="payment-title">Payment History</div>
    <div class="table-container">
      <table class="responsive-table payment-table">
        <thead>
          <tr>
            <th>Date & Time</th>
            <th>Amount Paid</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($paymentHistory)): ?>
            <?php foreach ($paymentHistory as $payment): ?>
            <tr>
              <td data-label="Date & Time"><?= htmlspecialchars($payment['datetime']) ?></td>
              <td data-label="Amount Paid" class="amount-highlight positive-amount">Rs.<?= number_format($payment['amount']) ?></td>
            </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="2" data-label="No Payments" style="text-align: center; color: #718096; font-style: italic;">No payments made yet</td>
            </tr>
          <?php endif; ?>
        </tbody>
        <tfoot>
          <tr class="total-row">
            <th>Total Amount Paid</th>
            <th>Rs.<?= number_format($amountPaid) ?></th>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
  
  <div class="section">
    <div class="section-title" id="fruit-title">Fruit Storage Details</div>
    <div class="table-container">
      <table class="responsive-table">
        <tr>
          <th data-label="Field">Fruit Name</th>
          <td data-label="Value"><?= htmlspecialchars($fruit['fruitname']) ?></td>
        </tr>
        <tr>
          <th data-label="Field">Arrival Date & Time</th>
          <td data-label="Value"><?= $startDate ?></td>
        </tr>
        <tr>
          <th data-label="Field">Total Boxes Stored</th>
          <td data-label="Value"><?= $totalBoxes ?></td>
        </tr>
        <tr>
          <th data-label="Field">Rent per Box per Month</th>
          <td data-label="Value">Rs.<?= number_format($rentPerBox) ?></td>
        </tr>
        <tr>
          <th data-label="Field">Current Boxes (Stored)</th>
          <td data-label="Value"><?= isset($box['box']) ? $box['box'] : '0' ?></td>
        </tr>
      </table>
    </div>
  </div>
  
  <div class="section">
    <div class="section-title" id="withdrawal-title">Box Withdrawal History</div>
    <div class="table-container">
      <table class="responsive-table withdrawal-table">
        <thead>
          <tr>
            <th>Date & Time</th>
            <th>Boxes Withdrawn</th>
            <th>Rent</th>
            <th>Months</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($withdrawals)): ?>
            <?php foreach ($withdrawals as $w): ?>
            <tr>
              <td data-label="Date & Time"><?= isset($w['datetime']) ? htmlspecialchars($w['datetime']) : '-' ?></td>
              <td data-label="Boxes Withdrawn"><?= isset($w['count']) ? htmlspecialchars($w['count']) : '0' ?></td>
              <td data-label="Rent">Rs.<?= isset($w['rent']) ? number_format($w['rent']) : '0' ?></td>
              <td data-label="Months"><?= isset($w['months']) ? htmlspecialchars($w['months']) : '1' ?></td>
            </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="4" data-label="No Withdrawals" style="text-align: center; color: #718096; font-style: italic;">No withdrawals made yet</td>
            </tr>
          <?php endif; ?>
        </tbody>
        <tfoot>
          <tr class="total-row">
            <th colspan="2">Total Withdrawn Rent</th>
            <th colspan="2">Rs.<?= number_format($withdrawnRent) ?></th>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
  
  <div class="section">
    <div class="section-title" id="billing-title">Billing Summary</div>
    <div class="table-container">
      <table class="responsive-table">
        <tr>
          <th data-label="Field">Storage Start Date & Time</th>
          <td data-label="Value"><?= $startDate ?></td>
        </tr>
        <tr>
          <th data-label="Field">Last Box Withdrawal Date & Time</th>
          <td data-label="Value"><?= $lastWithdrawDate ?></td>
        </tr>
        <tr>
          <th data-label="Field">Total Storage Duration</th>
          <td data-label="Value"><?= $months ?> Months <?= $rem_days ?> Days</td>
        </tr>
        <tr>
          <th data-label="Field">Amount Paid</th>
          <td data-label="Value" style="font-weight: bold; color: #4CAF50;">Rs.<?= number_format($amountPaid) ?></td>
        </tr>
        <tr>
          <th data-label="Field">Withdrawn Box Rent</th>
          <td data-label="Value" style="font-weight: bold; color: #1976D2;">Rs.<?= number_format($totalRent) ?></td>
        </tr>
      </table>
    </div>
    
    <div class="formula">
      <strong>Formula:</strong><br>
      Withdrawn Rent = Σ (Withdrawn Box × Months × Rent per Box)<br>
      Amount Paid = All payments sum<br>
      Withdrawn Box Rent = Withdrawn Rent - Amount Paid
    </div>
  </div>
  
  <div class="download-section">
    <a href="download-bill.php?cust=<?= $custIndex ?>&fruit=<?= $fruitIndex ?>&entity=<?= $entity ?>" class="download-btn">Download PDF</a>
  </div>
</div>

<div class="browser-tip">
  Best viewed in portrait mode on mobile devices
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script>
function goBackToFruitDetail() {
  window.location.href = 'fruit-detail.php?cust=<?= $custIndex ?>&fruit=<?= $fruitIndex ?>';
}
</script>
</body>
</html>