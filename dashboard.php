<?php
// filepath: c:\Users\ayanr\OneDrive\Desktop\cold-storage\deshboard.php

// Load project data
$customers = json_decode(file_get_contents("customers.json"), true);
$totalCustomers = count($customers);

$fruitTypes = [];
$totalBoxes = 0;
$pendingRent = 0;
$pendingRows = [];
$todayRows = [];
$fruitBoxCount = [];

// Collect all unique fruit names across all customers
$allFruitNames = [];
foreach ($customers as $custIndex => $customer) {
    $fruitFile = "fruits_$custIndex.json";
    if (!file_exists($fruitFile)) continue;
    $fruits = json_decode(file_get_contents($fruitFile), true);
    foreach ($fruits as $fruitIndex => $fruit) {
        $fruitName = trim(strtolower($fruit['fruitname']));
        $allFruitNames[$fruitName] = $fruit['fruitname']; // preserve original case
    }
}
$uniqueFruitNames = array_values(array_unique($allFruitNames));
$totalFruitTypes = count($uniqueFruitNames);

// Now count boxes for each unique fruit name
$fruitBoxCount = array_fill_keys($uniqueFruitNames, 0);
foreach ($customers as $custIndex => $customer) {
    $fruitFile = "fruits_$custIndex.json";
    if (!file_exists($fruitFile)) continue;
    $fruits = json_decode(file_get_contents($fruitFile), true);
    foreach ($fruits as $fruitIndex => $fruit) {
        $fruitName = trim(strtolower($fruit['fruitname']));
        $boxFile = "boxes_{$custIndex}_{$fruitIndex}.json";
        if (!file_exists($boxFile)) continue;
        $boxes = json_decode(file_get_contents($boxFile), true);
        foreach ($boxes as $box) {
            $boxCount = isset($box['box']) ? $box['box'] : 0;
            $fruitBoxCount[$allFruitNames[$fruitName]] += $boxCount;

            // Pending rent calculation
            $months = isset($box['months']) ? $box['months'] : 1;
            $rentPerBox = isset($box['rent_per_box']) ? $box['rent_per_box'] : 0;
            $totalRent = isset($box['total_rent']) ? $box['total_rent'] : 0;
            if ($totalRent > 0) {
                $pendingRent += $totalRent;
                $pendingRows[] = [
                    'customer' => $customer['name'],
                    'fruit' => $allFruitNames[$fruitName],
                    'boxes' => $boxCount,
                    'months' => $months,
                    'rent_per_box' => $rentPerBox,
                    'total_rent' => $totalRent
                ];
            }

            // Today activity
            if (isset($box['date'])) {
                $todayRows[] = [
                    'time' => date('H:i', strtotime($box['date'])),
                    'activity' => 'New Arrival',
                    'customer' => $customer['name'],
                    'fruit' => $allFruitNames[$fruitName],
                    'boxes' => $boxCount
                ];
            }
            if (!empty($box['removed'])) {
                foreach ($box['removed'] as $rem) {
                    $todayRows[] = [
                        'time' => isset($rem['datetime']) ? date('H:i', strtotime($rem['datetime'])) : '-',
                        'activity' => 'Withdrawal',
                        'customer' => $customer['name'],
                        'fruit' => $allFruitNames[$fruitName],
                        'boxes' => isset($rem['count']) ? $rem['count'] : 0
                    ];
                }
            }
        }
    }
}
$chartLabels = json_encode(array_keys($fruitBoxCount));
$chartData = json_encode(array_values($fruitBoxCount));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Fruit Chamber Dashboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
     * { box-sizing: border-box; }
    body {
      margin: 0;
      font-family: 'Segoe UI', sans-serif;
      background-color: #f4f6f9;
      padding: 15px;
    }

    h1 {
      text-align: center;
      margin-bottom: 25px;
      color: #333;
    }

    .dashboard {
      display: flex;
      flex-wrap: wrap;
      gap: 15px;
      justify-content: center;
    }

    .card {
      flex: 1 1 230px;
      background: #fff;
      border-radius: 10px;
      padding: 15px;
      box-shadow: 0 2px 6px rgba(0,0,0,0.1);
      text-align: center;
    }

    .card h2 {
      font-size: 24px;
      margin: 5px 0;
      color: #007bff;
    }

    .card p {
      font-size: 14px;
      color: #666;
    }

    .section {
      margin-top: 35px;
    }

    .section h2 {
      font-size: 26px;
      margin-bottom: 15px;
      text-align: center;
      color: #1976d2;
      font-weight: bold;
      background: linear-gradient(90deg, #e3f2fd 0%, #bbdefb 100%);
      border-radius: 8px;
      padding: 10px 0 10px 0;
      box-shadow: 0 2px 6px rgba(25, 118, 210, 0.08);
      letter-spacing: 1px;
    }

    .charts {
      display: flex;
      flex-wrap: nowrap;
      justify-content: flex-start;
      overflow-x: auto;
      padding-bottom: 12px;
      max-width: 100vw;
    }

    canvas {
      background: #fff;
      padding: 10px;
      border-radius: 10px;
      min-width: 600px;
      max-width: none;
      box-shadow: 0 2px 6px rgba(0,0,0,0.1);
      margin-right: 18px;
    }

    .table-box {
      overflow-x: auto;
      background: #fff;
      padding: 10px;
      border-radius: 10px;
      box-shadow: 0 2px 6px rgba(0,0,0,0.1);
      margin-top: 20px;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 14px;
    }

    table th, table td {
      padding: 8px;
      border: 1px solid #ddd;
      text-align: left;
    }

    .download-btn {
      display: block;
      margin: 30px auto;
      padding: 10px 20px;
      background: #007bff;
      color: white;
      border: none;
      border-radius: 5px;
      font-size: 14px;
      cursor: pointer;
    }

    .download-btn:hover {
      background: #0056b3;
    }

    @media (max-width: 600px) {
      .card, canvas {
        flex: 1 1 100%;
      }
    }
  </style>
</head>
<body>
<a href="index.html" class="back-link" style="display:inline-block;margin-bottom:18px;font-weight:600;color:#1976d2;text-decoration:none;font-size:18px;">← Back</a>
  <h1> Admin Dashboard</h1>

  <!-- CARDS -->
  <div class="dashboard">
    <div class="card"><h2><?= $totalCustomers ?></h2><p>Total Customers</p></div>
    <div class="card"><h2>₹<?= number_format($pendingRent) ?></h2><p>Pending Rent</p></div>
    <div class="card"><h2><?= $totalBoxes ?></h2><p>Total Boxes Stored</p></div>
    <div class="card"><h2><?= $totalFruitTypes ?></h2><p>Fruit Types Stored</p></div>
  </div>

 <!-- TODAY HISTORY TABLE -->
  <div class="section">
    <h2>📅 Today’s Activity - <?= date('d M Y') ?></h2>
    <div class="table-box">
      <table>
        <thead>
          <tr>
            <th>Time</th>
            <th>Activity</th>
            <th>Customer</th>
            <th>Fruit</th>
            <th>Boxes</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($todayRows as $row): ?>
            <tr>
              <td><?= $row['time'] ?></td>
              <td><?= $row['activity'] ?></td>
              <td><?= $row['customer'] ?></td>
              <td><?= $row['fruit'] ?></td>
              <td><?= $row['boxes'] ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- RENT COLLECTION TABLE -->
  <div class="section">
    <h2>💰 Pending Rent Collection</h2>
    <div class="table-box">
      <table>
        <thead>
          <tr>
            <th>Customer</th>
            <th>Fruit</th>
            <th>Boxes</th>
            <th>Months Stored</th>
            <th>Rent/Box</th>
            <th>Total Rent</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($pendingRows as $row): ?>
            <tr>
              <td><?= $row['customer'] ?></td>
              <td><?= $row['fruit'] ?></td>
              <td><?= $row['boxes'] ?></td>
              <td><?= $row['months'] ?></td>
              <td>₹<?= $row['rent_per_box'] ?></td>
              <td>₹<?= number_format($row['total_rent'], 2) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- CHART -->
  <div class="section">
    <h2>📊 Fruit-wise Total Boxes Stored</h2>
    <div class="charts" style="height:420px;">
      <canvas id="fruitChart" height="400"></canvas>
    </div>
  </div>

  <!-- PDF DOWNLOAD BUTTON -->
  <button class="download-btn">📄 Download PDF Report</button>

  <!-- CHART SCRIPT -->
  <script>
    const fruitChart = new Chart(document.getElementById('fruitChart'), {
      type: 'bar',
      data: {
        labels: <?= $chartLabels ?>,
        datasets: [{
          label: 'Total Boxes Stored',
          data: <?= $chartData ?>,
          backgroundColor: '#42a5f5'
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            enabled: true,
            callbacks: {
              title: function(context) {
                // Show full fruit name in tooltip
                return context[0].label;
              },
              label: function(context) {
                return 'Total Boxes: ' + context.parsed.y;
              }
            }
          }
        },
        layout: {
          padding: { top: 10, bottom: 10 }
        },
        scales: {
          x: {
            title: { display: true, text: 'Fruit Name' },
            ticks: {
              autoSkip: false,
              maxRotation: 0,
              minRotation: 0,
              font: { size: 14 },
              callback: function(value, index, values) {
                // Prevent stretching: truncate long names and add ellipsis
                let label = this.getLabelForValue(value);
                return label.length > 12 ? label.slice(0, 12) + '…' : label;
              }
            },
            grid: { display: true }
          },
          y: {
            beginAtZero: true,
            title: { display: true, text: 'Box Count' },
            grid: { display: true },
            ticks: { font: { size: 14 } }
          }
        }
      }
    });

    // Show bar details on click
    document.getElementById('fruitChart').onclick = function(evt) {
      const points = fruitChart.getElementsAtEventForMode(evt, 'nearest', { intersect: true }, true);
      if (points.length) {
        const idx = points[0].index;
        const fruit = fruitChart.data.labels[idx];
        const count = fruitChart.data.datasets[0].data[idx];
        alert('Fruit: ' + fruit + '\nTotal Boxes: ' + count);
      }
    };
  </script>

</body>
</html>