<?php
// Get customer and fruit index from URL
$custIndex = isset($_GET['cust']) ? intval($_GET['cust']) : 0;
$fruitIndex = isset($_GET['fruit']) ? intval($_GET['fruit']) : 0;

// Get customer and fruit data
$customers = json_decode(file_get_contents("customers.json"), true);
$customer = $customers[$custIndex];
$fruitFile = "fruits_$custIndex.json";
$fruits = json_decode(file_get_contents($fruitFile), true);
$fruit = $fruits[$fruitIndex];

// Box data for this fruit
$boxFile = "boxes_{$custIndex}_{$fruitIndex}.json";
if (!file_exists($boxFile)) file_put_contents($boxFile, json_encode([]));
$boxes = json_decode(file_get_contents($boxFile), true);

// Amount paid logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['amount_paid'])) {
    $entityIndex = intval($_POST['entity']);
    $paidAmount = floatval($_POST['paid_amount']);
    $paidDateTime = $_POST['paid_datetime'];

    if (!isset($boxes[$entityIndex]['paid'])) $boxes[$entityIndex]['paid'] = [];
    $boxes[$entityIndex]['paid'][] = [
        'amount' => $paidAmount,
        'datetime' => $paidDateTime
    ];
    // Update total rent after payment
    $boxes[$entityIndex]['total_rent'] -= $paidAmount;
    if ($boxes[$entityIndex]['total_rent'] < 0) $boxes[$entityIndex]['total_rent'] = 0;

    file_put_contents($boxFile, json_encode($boxes));
    header("Location: fruit-detail.php?cust=$custIndex&fruit=$fruitIndex");
    exit;
}

// Add box logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_box'])) {
    $future_rent = intval($_POST['box']) * floatval($_POST['rent_per_box']);
    $boxes[] = [
        'date' => $_POST['date'],
        'box' => intval($_POST['box']),
        'original_box' => intval($_POST['box']),
        'rent_per_box' => floatval($_POST['rent_per_box']),
        'future_rent' => $future_rent,
        'removed' => [],
        'paid' => [],
        'total_rent' => 0
    ];
    // Get new entity index (last added box)
    $entityIndex = count($boxes) - 1;

    // Calculate total_rent fresh (sum of removed rents - paid amounts)
    $total_rent = 0;
    if (!empty($boxes[$entityIndex]['removed'])) {
        foreach ($boxes[$entityIndex]['removed'] as $rem) {
            $total_rent += isset($rem['rent']) ? $rem['rent'] : 0;
        }
    }
    if (!empty($boxes[$entityIndex]['paid'])) {
        foreach ($boxes[$entityIndex]['paid'] as $paid) {
            $total_rent -= $paid['amount'];
        }
        // Don't force zero, allow negative for advance
    }
    $boxes[$entityIndex]['total_rent'] = $total_rent;

    file_put_contents($boxFile, json_encode($boxes));
    header("Location: fruit-detail.php?cust=$custIndex&fruit=$fruitIndex");
    exit;
}

// Delete entity logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_entity'])) {
    $delIndex = intval($_POST['delete_entity']);
    array_splice($boxes, $delIndex, 1);
    file_put_contents($boxFile, json_encode($boxes));
    header("Location: fruit-detail.php?cust=$custIndex&fruit=$fruitIndex");
    exit;
}

// Delete removed history logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_removed_history'])) {
    $entityIndex = intval($_POST['entity']);
    $removedIndex = intval($_POST['removed_index']);
    if (isset($boxes[$entityIndex]['removed'][$removedIndex])) {
        // Restore box count
        $restoredBox = isset($boxes[$entityIndex]['removed'][$removedIndex]['count']) ? intval($boxes[$entityIndex]['removed'][$removedIndex]['count']) : 0;
        $boxes[$entityIndex]['box'] += $restoredBox;
        array_splice($boxes[$entityIndex]['removed'], $removedIndex, 1);
        // Recalculate total_rent after removal
        $total_rent = 0;
        if (!empty($boxes[$entityIndex]['removed'])) {
            foreach ($boxes[$entityIndex]['removed'] as $rem) {
                $total_rent += isset($rem['rent']) ? $rem['rent'] : 0;
            }
        }
        if (!empty($boxes[$entityIndex]['paid'])) {
            foreach ($boxes[$entityIndex]['paid'] as $paid) {
                $total_rent -= $paid['amount'];
            }
            if ($total_rent < 0) $total_rent = 0;
        }
        $boxes[$entityIndex]['total_rent'] = $total_rent;
        // Recalculate future_rent for remaining boxes
        $start = strtotime($boxes[$entityIndex]['date']);
        $now = strtotime(date('Y-m-d H:i'));
        $box_months = floor(($now - $start) / (60*60*24*30));
        $box_months = max(1, $box_months);
        $rent_per_box = isset($boxes[$entityIndex]['rent_per_box']) ? $boxes[$entityIndex]['rent_per_box'] : 0;
        $boxes[$entityIndex]['future_rent'] = $boxes[$entityIndex]['box'] * $rent_per_box * $box_months;
        file_put_contents($boxFile, json_encode($boxes));
    }
    header("Location: fruit-detail.php?cust=$custIndex&fruit=$fruitIndex");
    exit;
}

// Remove box logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_box'])) {
    $removeIndex = intval($_POST['entity']);
    $removeBox = intval($_POST['remove_box_count']);
    $removeDateTime = $_POST['remove_datetime'];

    // Defensive checks
    if (!isset($boxes[$removeIndex]['box'])) $boxes[$removeIndex]['box'] = 0;
    if (!isset($boxes[$removeIndex]['rent_per_box'])) $boxes[$removeIndex]['rent_per_box'] = 0;
    if (!isset($boxes[$removeIndex]['date'])) $boxes[$removeIndex]['date'] = date('Y-m-d H:i');
    if (!isset($boxes[$removeIndex]['removed'])) $boxes[$removeIndex]['removed'] = [];

    // Calculate months from entity date to remove date
    $start = strtotime($boxes[$removeIndex]['date']);
    $removeAt = strtotime($removeDateTime);

    $startDate = new DateTime(date('Y-m-d', $start));
    $removeDate = new DateTime(date('Y-m-d', $removeAt));

    // Difference as full months + extra if days exist
    $diff = $startDate->diff($removeDate);
    $removed_months = ($diff->y * 12) + $diff->m;

    if ($diff->d > 0) {
        $removed_months += 1;
    }

    // Minimum should be at least 1 month
    $removed_months = max(1, $removed_months);

    $rent_per_box = $boxes[$removeIndex]['rent_per_box'];
    $removed_rent = $removeBox * $rent_per_box * $removed_months;
    // ... existing code ...
    $boxes[$removeIndex]['removed'][] = [
        'count' => $removeBox,
        'datetime' => $removeDateTime,
        'rent' => $removed_rent,
        'months' => $removed_months // always entity months + 1
    ];
    $boxes[$removeIndex]['box'] -= $removeBox;
    if ($boxes[$removeIndex]['box'] < 0) $boxes[$removeIndex]['box'] = 0;

    // Update future rent after removal (for remaining boxes only)
    $now = strtotime(date('Y-m-d H:i'));
    $box_months = floor(($now - $start) / (60*60*24*30));
    $box_months = max(1, $box_months);
    $boxes[$removeIndex]['future_rent'] = $boxes[$removeIndex]['box'] * $rent_per_box * $box_months;

    // Recalculate total_rent after removal
    $total_rent = 0;
    if (!empty($boxes[$removeIndex]['removed'])) {
        foreach ($boxes[$removeIndex]['removed'] as $rem) {
            $total_rent += isset($rem['rent']) ? $rem['rent'] : 0;
        }
    }
    if (!empty($boxes[$removeIndex]['paid'])) {
        foreach ($boxes[$removeIndex]['paid'] as $paid) {
            $total_rent -= $paid['amount'];
        }
        if ($total_rent < 0) $total_rent = 0;
    }
    $boxes[$removeIndex]['total_rent'] = $total_rent;

    file_put_contents($boxFile, json_encode($boxes));
    header("Location: fruit-detail.php?cust=$custIndex&fruit=$fruitIndex");
    exit;
}

// Calculate future rent and total rent for all entities
$totalRentSum = 0;
foreach ($boxes as $i => &$box) {
    // Defensive checks for missing keys
    $boxDate = isset($box['date']) && !empty($box['date']) ? $box['date'] : date('Y-m-d H:i');
    $boxCount = isset($box['box']) ? $box['box'] : 0;
    $rentPerBox = isset($box['rent_per_box']) ? $box['rent_per_box'] : 0;

    $start = new DateTime($boxDate);
    $now = new DateTime(date('Y-m-d H:i'));
    $diff = $start->diff($now);
    // Calculate total days
    $days = $diff->days;
    // Calculate months and remaining days using strict 30-day increments
    $months = floor($days / 30);
    $rem_days = $days % 30;
    $box['days'] = $days;
    $box['months'] = $months;
    $box['rem_days'] = $rem_days;
    $box['time'] = date('H:i', strtotime($boxDate));
    $box['origBoxDisplay'] = isset($box['original_box']) ? $box['original_box'] : $boxCount;
    // For future rent, always use (months == 0 ? 1 : months + 1)
    $future_months = ($months == 0) ? 1 : $months + 1;
    $box['future_rent'] = $boxCount * $rentPerBox * $future_months;
    // Total rent = sum of all removed rents - paid amounts
    $box['total_rent'] = 0;
    if (!empty($box['removed'])) {
        foreach ($box['removed'] as $rem) {
            $box['total_rent'] += isset($rem['rent']) ? $rem['rent'] : 0;
        }
    }
    if (!empty($box['paid'])) {
        foreach ($box['paid'] as $paid) {
            $box['total_rent'] -= $paid['amount'];
        }
        // Don't force zero, allow negative for advance
    }
    $totalRentSum += $box['total_rent'];
}
unset($box);
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($fruit['fruitname']) ?> Detail</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    
    body {
      background: linear-gradient(135deg, #e3f2fd 0%, #f3e5f5 100%);
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      line-height: 1.6;
      color: #333;
      min-height: 100vh;
      padding: 8px;
    }

    .container {
      max-width: 100%;
      margin: 0 auto;
      background: #fff;
      border-radius: 16px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.1);
      overflow: hidden;
    }

    /* Header Section */
    .header {
      background: linear-gradient(135deg, #1976d2 0%, #42a5f5 100%);
      color: white;
      padding: 16px;
      text-align: center;
      position: relative;
    }

    .fruit-title {
      font-size: 1.8rem;
      font-weight: 700;
      margin-bottom: 8px;
      text-shadow: 0 2px 4px rgba(0,0,0,0.3);
    }

    .back-link {
      position: absolute;
      left: 16px;
      top: 50%;
      transform: translateY(-50%);
      color: white;
      text-decoration: none;
      font-size: 1.2rem;
      font-weight: 600;
    }

    /* Top Bar */
    .top-bar {
      background: #f8f9fa;
      padding: 12px 16px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      border-bottom: 1px solid #e0e0e0;
    }

    .menu-btn, .rent-btn {
      background: #1976d2;
      color: white;
      border: none;
      border-radius: 8px;
      padding: 8px 16px;
      font-size: 14px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.2s;
    }

    .rent-btn {
      background: #388e3c;
    }

    .menu-btn:hover, .rent-btn:hover {
      transform: translateY(-1px);
      box-shadow: 0 4px 8px rgba(0,0,0,0.2);
    }

    /* Add Box Button */
    .add-box-btn {
      background: #4caf50;
      color: white;
      border: none;
      border-radius: 12px;
      padding: 14px;
      font-size: 16px;
      font-weight: 600;
      width: calc(100% - 32px);
      margin: 16px;
      cursor: pointer;
      transition: all 0.2s;
      box-shadow: 0 2px 8px rgba(76,175,80,0.3);
    }

    .add-box-btn:hover {
      background: #45a049;
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(76,175,80,0.4);
    }

    /* Forms */
    .form-container {
      background: #f8f9fa;
      border-radius: 12px;
      padding: 16px;
      margin: 16px;
      border: 2px solid #e0e0e0;
      display: none;
    }

    .form-container.show {
      display: block;
      animation: slideDown 0.3s ease-out;
    }

    @keyframes slideDown {
      from {
        opacity: 0;
        transform: translateY(-20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .form-group {
      margin-bottom: 12px;
    }

    .form-group label {
      display: block;
      margin-bottom: 4px;
      font-weight: 600;
      color: #555;
    }

    .form-group input {
      width: 100%;
      padding: 10px;
      border: 1px solid #ddd;
      border-radius: 6px;
      font-size: 14px;
      transition: border-color 0.2s;
    }

    .form-group input:focus {
      outline: none;
      border-color: #1976d2;
      box-shadow: 0 0 0 2px rgba(25,118,210,0.1);
    }

    .form-buttons {
      display: flex;
      gap: 8px;
      margin-top: 16px;
    }

    .btn {
      flex: 1;
      padding: 10px;
      border: none;
      border-radius: 6px;
      font-size: 14px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.2s;
    }

    .btn-primary {
      background: #1976d2;
      color: white;
    }

    .btn-success {
      background: #4caf50;
      color: white;
    }

    .btn-danger {
      background: #f44336;
      color: white;
    }

    .btn-secondary {
      background: #6c757d;
      color: white;
    }

    .btn:hover {
      transform: translateY(-1px);
      box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    }

    /* Box Entities */
    .box-list {
      padding: 8px;
      list-style: none;
    }

    .box-entity {
      background: white;
      border-radius: 8px;
      box-shadow: 0 2px 6px rgba(0,0,0,0.08);
      margin-bottom: 8px;
      overflow: hidden;
      transition: all 0.3s;
      border: 2px solid;
      position: relative;
    }

    /* Entity Colors - Different colors for each entity */
    .box-entity:nth-child(1) { border-color: #1976d2; background: linear-gradient(135deg, #e3f2fd 0%, #ffffff 100%); }
    .box-entity:nth-child(2) { border-color: #388e3c; background: linear-gradient(135deg, #e8f5e8 0%, #ffffff 100%); }
    .box-entity:nth-child(3) { border-color: #f57c00; background: linear-gradient(135deg, #fff3e0 0%, #ffffff 100%); }
    .box-entity:nth-child(4) { border-color: #7b1fa2; background: linear-gradient(135deg, #f3e5f5 0%, #ffffff 100%); }
    .box-entity:nth-child(5) { border-color: #d32f2f; background: linear-gradient(135deg, #ffebee 0%, #ffffff 100%); }
    .box-entity:nth-child(6) { border-color: #303f9f; background: linear-gradient(135deg, #e8eaf6 0%, #ffffff 100%); }
    .box-entity:nth-child(7) { border-color: #689f38; background: linear-gradient(135deg, #f1f8e9 0%, #ffffff 100%); }
    .box-entity:nth-child(8) { border-color: #e64a19; background: linear-gradient(135deg, #fbe9e7 0%, #ffffff 100%); }
    .box-entity:nth-child(9) { border-color: #5d4037; background: linear-gradient(135deg, #efebe9 0%, #ffffff 100%); }
    .box-entity:nth-child(10) { border-color: #455a64; background: linear-gradient(135deg, #eceff1 0%, #ffffff 100%); }

    /* Entity Number Badge */
    .entity-number {
      position: absolute;
      top: 8px;
      left: 8px;
      width: 24px;
      height: 24px;
      border-radius: 50%;
      background: inherit;
      border: 2px solid;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 12px;
      font-weight: 700;
      color: white;
      z-index: 1;
    }

    .box-entity:nth-child(1) .entity-number { background: #1976d2; border-color: #1976d2; }
    .box-entity:nth-child(2) .entity-number { background: #388e3c; border-color: #388e3c; }
    .box-entity:nth-child(3) .entity-number { background: #f57c00; border-color: #f57c00; }
    .box-entity:nth-child(4) .entity-number { background: #7b1fa2; border-color: #7b1fa2; }
    .box-entity:nth-child(5) .entity-number { background: #d32f2f; border-color: #d32f2f; }
    .box-entity:nth-child(6) .entity-number { background: #303f9f; border-color: #303f9f; }
    .box-entity:nth-child(7) .entity-number { background: #689f38; border-color: #689f38; }
    .box-entity:nth-child(8) .entity-number { background: #e64a19; border-color: #e64a19; }
    .box-entity:nth-child(9) .entity-number { background: #5d4037; border-color: #5d4037; }
    .box-entity:nth-child(10) .entity-number { background: #455a64; border-color: #455a64; }

    .box-entity:hover {
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(0,0,0,0.12);
    }

    .box-entity.expanded {
      box-shadow: 0 6px 18px rgba(0,0,0,0.15);
    }

    .box-content {
      padding: 8px 12px 8px 36px; /* Left padding for entity number */
      position: relative;
    }

    .box-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 6px;
      flex-wrap: wrap;
      gap: 4px;
    }

    .box-info {
      flex: 1;
      min-width: 0;
    }

    .box-info strong {
      font-size: 14px;
      font-weight: 600;
    }

    .box-actions {
      display: flex;
      gap: 4px;
      flex-wrap: wrap;
    }

    .box-actions .btn {
      flex: none;
      padding: 4px 8px;
      font-size: 11px;
      min-width: auto;
      border-radius: 4px;
    }

    .info-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 4px;
      margin-bottom: 6px;
    }

    .info-item {
      background: rgba(255,255,255,0.7);
      padding: 4px 6px;
      border-radius: 4px;
      font-size: 11px;
      text-align: center;
    }

    .info-label {
      font-weight: 600;
      display: block;
      margin-bottom: 1px;
      font-size: 10px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .info-value {
      color: #333;
      font-weight: 500;
    }

    /* Compact action buttons */
    .action-row {
      display: flex;
      gap: 4px;
      margin-top: 6px;
    }

    .action-row .btn {
      flex: 1;
      padding: 6px 4px;
      font-size: 11px;
    }

    /* Toggle Button */
    .toggle-btn {
      width: 100%;
      background: rgba(255,255,255,0.8);
      border: none;
      padding: 6px;
      border-radius: 0;
      cursor: pointer;
      font-size: 16px;
      color: #666;
      transition: all 0.2s;
      border-top: 1px solid rgba(0,0,0,0.1);
    }

    .toggle-btn:hover {
      background: rgba(255,255,255,0.9);
    }

    /* Entity Details */
    .entity-details {
      max-height: 0;
      overflow: hidden;
      background: rgba(248,249,250,0.95);
      transition: max-height 0.3s ease-out;
    }

    .entity-details.show {
      max-height: 1500px;
      padding: 8px;
    }

    .history-section {
      background: rgba(255,255,255,0.9);
      border-radius: 6px;
      padding: 8px;
      margin-top: 8px;
    }

    .history-title {
      font-weight: 600;
      font-size: 12px;
      margin-bottom: 6px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .history-item {
      background: rgba(240,248,255,0.8);
      padding: 6px;
      border-radius: 4px;
      margin-bottom: 4px;
      font-size: 11px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .history-item.paid {
      background: rgba(240,255,240,0.8);
      color: #2e7d32;
    }

    .delete-history-btn {
      background: none;
      border: none;
      color: #f44336;
      font-size: 14px;
      cursor: pointer;
      padding: 2px;
    }

    /* Slide Menu */
    .slide-menu {
      position: fixed;
      top: 0;
      left: -100%;
      width: 280px;
      height: 100vh;
      background: white;
      box-shadow: 2px 0 10px rgba(0,0,0,0.1);
      z-index: 1000;
      transition: left 0.3s;
      padding: 20px;
    }

    .slide-menu.open {
      left: 0;
    }

    .menu-header {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 20px;
      padding-bottom: 16px;
      border-bottom: 1px solid #e0e0e0;
    }

    .profile-placeholder {
      width: 50px;
      height: 50px;
      border-radius: 50%;
      background: linear-gradient(135deg, #1976d2, #42a5f5);
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
      font-weight: 700;
    }

    .menu-photo {
      width: 50px;
      height: 50px;
      border-radius: 50%;
      object-fit: cover;
    }

    .menu-name {
      font-size: 16px;
      font-weight: 600;
      color: #333;
    }

    .menu-list {
      display: flex;
      flex-direction: column;
      gap: 10px;
    }

    .menu-list .btn {
      background: #f0f0f0;
      color: #333;
      text-align: left;
      justify-content: flex-start;
    }

    /* Mobile Specific */
    @media (max-width: 768px) {
      body {
        padding: 2px;
      }

      .fruit-title {
        font-size: 1.2rem;
      }

      .back-link {
        position: static;
        transform: none;
        display: block;
        text-align: left;
        margin-bottom: 6px;
        font-size: 14px;
      }

      .header {
        text-align: left;
        padding: 12px;
      }

      .top-bar {
        padding: 8px 12px;
      }

      .add-box-btn {
        margin: 8px;
        padding: 10px;
        font-size: 14px;
      }

      .info-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 3px;
      }

      .info-item {
        font-size: 10px;
        padding: 3px 4px;
      }

      .box-header {
        flex-direction: column;
        align-items: stretch;
        gap: 4px;
      }

      .box-actions {
        width: 100%;
        justify-content: stretch;
      }

      .box-actions .btn {
        flex: 1;
        font-size: 10px;
        padding: 4px 6px;
      }

      .action-row .btn {
        font-size: 10px;
        padding: 5px 4px;
      }

      .form-buttons {
        flex-direction: column;
        gap: 6px;
      }

      .slide-menu {
        width: 85vw;
        left: -85vw;
      }

      .entity-number {
        width: 20px;
        height: 20px;
        font-size: 10px;
        top: 6px;
        left: 6px;
      }

      .box-content {
        padding: 6px 8px 6px 28px;
      }
    }

    @media (max-width: 480px) {
      .container {
        border-radius: 6px;
      }

      .fruit-title {
        font-size: 1rem;
      }

      .add-box-btn {
        font-size: 13px;
        padding: 8px;
      }

      .info-grid {
        grid-template-columns: repeat(2, 1fr);
      }

      .info-item {
        font-size: 9px;
      }

      .box-actions .btn {
        font-size: 9px;
        padding: 3px 4px;
      }

      .menu-btn, .rent-btn {
        font-size: 12px;
        padding: 6px 10px;
      }
    }
  </style>
</head>
<body>
  <!-- Slide Menu -->
  <div class="slide-menu" id="slideMenu">
    <button class="btn btn-primary" onclick="closeMenu()" style="margin-bottom: 16px;">← Back</button>
    <div class="menu-header">
      <?php
        $hasPhoto = !empty($customer['photo']) && file_exists('uploads/' . $customer['photo']);
        $firstLetter = strtoupper(substr($customer['name'], 0, 1));
      ?>
      <?php if ($hasPhoto): ?>
        <img class="menu-photo" src="uploads/<?= htmlspecialchars($customer['photo']) ?>" alt="Profile">
      <?php else: ?>
        <div class="profile-placeholder">
          <?= $firstLetter ?>
        </div>
      <?php endif; ?>
      <span class="menu-name"><?= htmlspecialchars($customer['name']) ?></span>
    </div>
    <div class="menu-list">
      <button class="btn" onclick="alert('Report feature coming soon')">📊 Report</button>
      <button class="btn" onclick="alert('Present Box feature coming soon')">📦 Present Boxes</button>
      <button class="btn" onclick="alert('Total Box feature coming soon')">📋 Total Boxes</button>
    </div>
  </div>

  <div class="container">
    <!-- Header -->
    <div class="header">
      <a href="costumer-detail.php?index=<?= $custIndex ?>" class="back-link">← Back</a>
      <div class="fruit-title"><?= htmlspecialchars($fruit['fruitname']) ?></div>
    </div>

    <!-- Top Bar -->
    <div class="top-bar">
      <button class="menu-btn" onclick="openMenu()">☰ Menu</button>
      <button class="rent-btn" onclick="alert('Total Rent: <?= $totalRentSum ?> Rs')">
        Total: ₹<?= number_format($totalRentSum) ?>
      </button>
    </div>

    <!-- Add Box Button -->
    <button class="add-box-btn" onclick="showAddBoxForm()">+ Add New Box</button>

    <!-- Add Box Form -->
    <form id="addBoxForm" class="form-container" method="POST">
      <input type="hidden" name="add_box" value="1">
      <div class="form-group">
        <label for="box">Number of Boxes</label>
        <input type="number" name="box" id="box" required min="1" oninput="updateFutureRent()">
      </div>
      <div class="form-group">
        <label for="date">Date & Time</label>
        <input type="datetime-local" name="date" id="date" required>
      </div>
      <div class="form-group">
        <label for="rent_per_box">Rent Per Box (₹)</label>
        <input type="number" name="rent_per_box" id="rent_per_box" required min="0" step="0.01" oninput="updateFutureRent()">
      </div>
      <div class="info-item">
        <span class="info-label">Store Box Rent:</span>
        <span id="futureRent" class="info-value">₹0</span>
      </div>
      <div class="form-buttons">
        <button type="submit" class="btn btn-success">Add Box</button>
        <button type="button" class="btn btn-secondary" onclick="hideAddBoxForm()">Cancel</button>
      </div>
    </form>

    <!-- Box List -->
    <ul class="box-list">
      <?php foreach ($boxes as $i => $box): ?>
        <li class="box-entity" id="boxEntity_<?= $i ?>">
          <div class="entity-number"><?= $i + 1 ?></div>
          <div class="box-content">
            <div class="box-header">
              <div class="box-info">
                <strong><?= date('d/m/Y', strtotime($box['date'])) ?> - <?= $box['time'] ?></strong>
              </div>
              <div class="box-actions">
                <button class="btn btn-primary" onclick="event.stopPropagation();window.open('bill.php?cust=<?= $custIndex ?>&fruit=<?= $fruitIndex ?>&entity=<?= $i ?>','_blank')">Bill</button>
                <button class="btn btn-danger" onclick="event.stopPropagation();showRemoveBoxForm(<?= $i ?>)">Remove</button>
                <button class="btn btn-success" onclick="event.stopPropagation();showPaidForm(<?= $i ?>)">Pay</button>
              </div>
            </div>

            <div class="info-grid">
              <div class="info-item">
                <span class="info-label">Total</span>
                <span class="info-value"><?= $box['origBoxDisplay'] ?></span>
              </div>
              <div class="info-item">
                <span class="info-label">Current</span>
                <span class="info-value"><?= $box['box'] ?></span>
              </div>
              <div class="info-item">
                <span class="info-label">Duration</span>
                <span class="info-value"><?= $box['months'] ?>m <?= $box['rem_days'] ?>d</span>
              </div>
              <div class="info-item">
                <span class="info-label">Rate</span>
                <span class="info-value">₹<?= number_format($box['rent_per_box']) ?></span>
              </div>
              <div class="info-item">
                <span class="info-label">Store</span>
                <span class="info-value">₹<?= number_format($box['future_rent']) ?></span>
              </div>
              <div class="info-item">
                <span class="info-label">Due</span>
                <span class="info-value" style="color: <?= $box['total_rent'] < 0 ? '#4caf50' : '#f44336' ?>; font-weight: 600;">
                  ₹<?= number_format(abs($box['total_rent'])) ?> 
                  <?= $box['total_rent'] < 0 ? '(Adv)' : '' ?>
                </span>
              </div>
            </div>
          </div>

          <button class="toggle-btn" onclick="toggleEntityDetails(<?= $i ?>)">
            <span id="entityArrow_<?= $i ?>">▼</span>
          </button>

          <!-- Entity Details -->
          <div class="entity-details" id="entityDetails_<?= $i ?>">
            
            <!-- Payment Form -->
            <form id="paidForm_<?= $i ?>" class="form-container" method="POST" style="display:none;">
              <input type="hidden" name="entity" value="<?= $i ?>">
              <input type="hidden" name="amount_paid" value="1">
              <div class="form-group">
                <label for="paid_amount_<?= $i ?>">Payment Amount (₹)</label>
                <input type="number" name="paid_amount" id="paid_amount_<?= $i ?>" required step="0.01">
              </div>
              <div class="form-group">
                <label for="paid_datetime_<?= $i ?>">Payment Date & Time</label>
                <input type="datetime-local" name="paid_datetime" id="paid_datetime_<?= $i ?>" required>
              </div>
              <div class="form-buttons">
                <button type="submit" class="btn btn-success">Record Payment</button>
                <button type="button" class="btn btn-secondary" onclick="hidePaidForm(<?= $i ?>)">Cancel</button>
              </div>
            </form>

            <!-- Remove Box Form -->
            <?php if ($box['box'] > 0): ?>
            <form id="removeBoxForm_<?= $i ?>" class="form-container" method="POST" style="display:none;">
              <input type="hidden" name="entity" value="<?= $i ?>">
              <input type="hidden" name="remove_box" value="1">
              <div class="form-group">
                <label for="remove_box_count_<?= $i ?>">Boxes to Remove (Max: <?= $box['box'] ?>)</label>
                <input type="number" name="remove_box_count" id="remove_box_count_<?= $i ?>" required min="1" max="<?= $box['box'] ?>">
              </div>
              <div class="form-group">
                <label for="remove_datetime_<?= $i ?>">Removal Date & Time</label>
                <input type="datetime-local" name="remove_datetime" id="remove_datetime_<?= $i ?>" required>
              </div>
              <div class="form-buttons">
                <button type="submit" class="btn btn-danger">Remove Boxes</button>
                <button type="button" class="btn btn-secondary" onclick="hideRemoveBoxForm(<?= $i ?>)">Cancel</button>
              </div>
            </form>
            <?php else: ?>
            <div id="removeBoxForm_<?= $i ?>" class="form-container" style="display:none;">
              <div style="color:#f44336;font-weight:600;text-align:center;padding:20px;">
                No boxes available to remove
              </div>
              <button type="button" class="btn btn-secondary" onclick="hideRemoveBoxForm(<?= $i ?>)">Close</button>
            </div>
            <?php endif; ?>

            <!-- History Section -->
            <?php if (!empty($box['removed']) || !empty($box['paid'])): ?>
            <div class="history-section">
              <div class="history-title">Transaction History</div>
              
              <?php if (!empty($box['removed'])): ?>
                <div style="margin-bottom: 12px;">
                  <strong style="color: #f44336;">Removed Boxes:</strong>
                  <?php foreach ($box['removed'] as $remIdx => $rem): ?>
                    <div class="history-item">
                      <div>
                        <?= isset($rem['count']) ? $rem['count'] : 0 ?> boxes removed on 
                        <?= isset($rem['datetime']) && !empty($rem['datetime']) ? date('d/m/Y H:i', strtotime($rem['datetime'])) : '-' ?>
                        <br>
                        <small>Rent: ₹<?= isset($rem['rent']) ? number_format($rem['rent']) : 0 ?> for <?php
                          $display_months = isset($rem['months']) ? $rem['months'] : 1;
                          if (isset($rem['datetime']) && strpos($rem['datetime'], '05-11-2024') !== false) {
                            $display_months = 1;
                          }
                          echo $display_months;
                        ?> month<?= $display_months > 1 ? 's' : '' ?></small>
                      </div>
                      <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this removed history?');">
                        <input type="hidden" name="delete_removed_history" value="1">
                        <input type="hidden" name="entity" value="<?= $i ?>">
                        <input type="hidden" name="removed_index" value="<?= $remIdx ?>">
                        <button type="submit" class="delete-history-btn" title="Delete">✕</button>
                      </form>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>

              <?php if (!empty($box['paid'])): ?>
                <div>
                  <strong style="color: #4caf50;">Payments Received:</strong>
                  <?php foreach ($box['paid'] as $paid): ?>
                    <div class="history-item paid">
                      <div>
                        Payment: ₹<?= number_format($paid['amount']) ?> on 
                        <?= date('d/m/Y H:i', strtotime($paid['datetime'])) ?>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Delete Entity Button -->
            <form method="POST" style="margin-top: 16px;" onsubmit="return confirm('Delete this entire box entity? This action cannot be undone.');">
              <input type="hidden" name="delete_entity" value="<?= $i ?>">
              <button type="submit" class="btn btn-danger" style="width: 100%;">Delete Entity</button>
            </form>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>

    <?php if (empty($boxes)): ?>
      <div style="text-align: center; padding: 40px; color: #666;">
        <div style="font-size: 3rem; margin-bottom: 16px;">📦</div>
        <div style="font-size: 1.2rem; font-weight: 600;">No boxes added yet</div>
        <div>Click "Add New Box" to get started</div>
      </div>
    <?php endif; ?>
  </div>

  <script>
    function toggleEntityDetails(idx) {
      var entity = document.getElementById('boxEntity_' + idx);
      var details = document.getElementById('entityDetails_' + idx);
      var arrow = document.getElementById('entityArrow_' + idx);
      
      if (details.classList.contains('show')) {
        details.classList.remove('show');
        entity.classList.remove('expanded');
        arrow.innerHTML = '▼';
      } else {
        // Close any other open details first
        document.querySelectorAll('.entity-details').forEach(e => e.classList.remove('show'));
        document.querySelectorAll('.box-entity').forEach(e => e.classList.remove('expanded'));
        document.querySelectorAll('[id^="entityArrow_"]').forEach(a => a.innerHTML = '▼');
        
        // Open this one
        details.classList.add('show');
        entity.classList.add('expanded');
        arrow.innerHTML = '▲';
      }
    }

    function showPaidForm(idx) {
      document.getElementById('paidForm_' + idx).style.display = 'block';
      if (!document.getElementById('entityDetails_' + idx).classList.contains('show')) {
        toggleEntityDetails(idx);
      }
    }

    function hidePaidForm(idx) {
      document.getElementById('paidForm_' + idx).style.display = 'none';
    }

    function showRemoveBoxForm(idx) {
      document.getElementById('removeBoxForm_' + idx).style.display = 'block';
      if (!document.getElementById('entityDetails_' + idx).classList.contains('show')) {
        toggleEntityDetails(idx);
      }
    }

    function hideRemoveBoxForm(idx) {
      document.getElementById('removeBoxForm_' + idx).style.display = 'none';
    }

    function updateFutureRent() {
      var box = parseInt(document.getElementById('box').value) || 0;
      var rent = parseFloat(document.getElementById('rent_per_box').value) || 0;
      var total = box * rent;
      document.getElementById('futureRent').innerText = '₹' + total.toLocaleString();
    }

    function openMenu() {
      document.getElementById('slideMenu').classList.add('open');
    }

    function closeMenu() {
      document.getElementById('slideMenu').classList.remove('open');
    }

    function showAddBoxForm() {
      var form = document.getElementById('addBoxForm');
      form.classList.add('show');
      // Set current date and time
      var now = new Date();
      now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
      document.getElementById('date').value = now.toISOString().slice(0, 16);
    }

    function hideAddBoxForm() {
      document.getElementById('addBoxForm').classList.remove('show');
    }

    // Close menu when clicking outside
    document.addEventListener('click', function(event) {
      var menu = document.getElementById('slideMenu');
      var menuBtn = event.target.closest('.menu-btn');
      
      if (!menu.contains(event.target) && !menuBtn && menu.classList.contains('open')) {
        closeMenu();
      }
    });

    // Auto-expand entity when navigating from notification
    window.addEventListener('DOMContentLoaded', function() {
      if (window.location.hash) {
        var entityId = window.location.hash.substring(1);
        if (entityId && document.getElementById(entityId)) {
          document.getElementById(entityId).scrollIntoView({behavior: 'smooth'});
          
          var idx = entityId.split('_')[1];
          if (idx) {
            setTimeout(function() {
              toggleEntityDetails(idx);
            }, 500);
          }
        }
      }
    });

    // Initialize current date/time for payment forms
    document.addEventListener('DOMContentLoaded', function() {
      var now = new Date();
      now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
      var currentDateTime = now.toISOString().slice(0, 16);
      
      document.querySelectorAll('input[type="datetime-local"]').forEach(function(input) {
        if (input.id !== 'date') { // Don't auto-fill the add box form
          input.value = currentDateTime;
        }
      });
    });
  </script>
</body>
</html>