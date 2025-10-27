<?php
$notifications = [];
if (file_exists('notifications.json')) {
    $notifications = json_decode(file_get_contents('notifications.json'), true);
}

// Delete notification logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    $delIndex = intval($_POST['delete']);
    if (isset($notifications[$delIndex])) {
        array_splice($notifications, $delIndex, 1);
        file_put_contents('notifications.json', json_encode($notifications));
        header("Location: notification.php");
        exit;
    }
}

function formatMonthsAndDays($days) {
    // Convert to integer to avoid precision loss warning
    $days = intval($days);
    $months = floor($days / 30);
    $remainingDays = $days % 30;
    return "{$months} Months & {$remainingDays} Days";
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Notifications</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    body {
      background: linear-gradient(135deg, #e0f7fa 0%, #f8bbd0 100%);
      min-height: 100vh;
      font-family: 'Segoe UI', Arial, sans-serif;
      margin: 0;
      padding: 0;
    }
    .top-bar {
      display: flex;
      align-items: center;
      background: #1976d2;
      color: #fff;
      padding: 14px 18px;
      border-radius: 0 0 18px 18px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.08);
      position: sticky;
      top: 0;
      z-index: 100;
      margin-bottom: 18px;
    }
    .back-btn {
      background: #fff;
      color: #1976d2;
      border: none;
      border-radius: 8px;
      font-size: 16px;
      font-weight: 600;
      padding: 8px 18px;
      margin-right: 18px;
      cursor: pointer;
      box-shadow: 0 2px 8px rgba(33,150,243,0.08);
      transition: background 0.2s;
    }
    .back-btn:hover {
      background: #e3f2fd;
    }
    .notif-title {
      font-size: 1.5rem;
      font-weight: 700;
      letter-spacing: 1px;
      flex: 1;
      text-align: center;
      color: #fff;
    }
    .notif-container {
      max-width: 500px;
      margin: 0 auto;
      background: #fff;
      border-radius: 14px;
      box-shadow: 0 2px 16px rgba(33,150,243,0.10);
      padding: 24px 18px 18px 18px;
      margin-top: 24px;
    }
    h2 {
      text-align: center;
      color: #1976d2;
      margin-bottom: 24px;
      font-size: 2rem;
      letter-spacing: 1px;
    }
    .notif-item {
      background: #e3f2fd;
      border-radius: 8px;
      padding: 16px 12px;
      margin-bottom: 18px;
      box-shadow: 0 2px 8px rgba(33,150,243,0.08);
      font-size: 16px;
      color: #222;
      position: relative;
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 12px;
      transition: box-shadow 0.2s;
    }
    .notif-item:hover {
      box-shadow: 0 4px 16px #90caf9;
    }
    .notif-message {
      flex: 1;
      white-space: pre-line;
      font-size: 16px;
      line-height: 1.6;
    }
    .delete-btn {
      background: #ff5252;
      color: #fff;
      border: none;
      border-radius: 6px;
      font-size: 14px;
      font-weight: 600;
      padding: 7px 14px;
      cursor: pointer;
      margin-left: 10px;
      transition: background 0.2s;
      align-self: flex-start;
      box-shadow: 0 2px 8px rgba(255,82,82,0.08);
    }
    .delete-btn:hover {
      background: #d32f2f;
    }
    @media (max-width: 600px) {
      .top-bar {
        flex-direction: column;
        align-items: flex-start;
        padding: 10px 6vw;
        border-radius: 0 0 12px 12px;
        margin-bottom: 10px;
      }
      .notif-title {
        font-size: 1.1rem;
        margin-top: 6px;
        text-align: left;
      }
      .notif-container {
        max-width: 98vw;
        margin: 0 auto;
        padding: 10px 2vw 10px 2vw;
        border-radius: 8px;
        margin-top: 12px;
      }
      h2 {
        font-size: 1.1rem;
        margin-bottom: 10px;
      }
      .notif-item {
        font-size: 13px;
        padding: 10px 4px;
        margin-bottom: 10px;
        flex-direction: column;
        gap: 6px;
      }
      .notif-message {
        font-size: 13px;
      }
      .delete-btn {
        font-size: 12px;
        padding: 6px 10px;
        margin-left: 0;
        margin-top: 6px;
        width: 100%;
      }
    }

h1{

color:#1976d2 ;

}
  </style>
</head>
<body>
  <div class="top-bar">
    <button class="back-btn" onclick="window.location.href='index.html'">← Back</button>
    <span class="notif-title">Notifications</span>
  </div>
  <div class="notif-container">
    <?php if (empty($notifications)): ?>
      <div class="notif-item">No notifications.</div>
    <?php else: ?>
      <?php foreach (array_reverse($notifications, true) as $i => $n): ?>
        <?php
        // Validate notification data
        $customer = $n['customer'] ?? 'Unknown Customer';
        // Get indices from notification data
        $custIndex = isset($n['cust_index']) ? $n['cust_index'] : null;
        $fruitIndex = isset($n['fruit_index']) ? $n['fruit_index'] : null;
        $boxIndex = isset($n['box_index']) ? $n['box_index'] : null;
        // Get the store box rent from the actual box data if possible
        $storeBoxRent = '0.00';        
        if ($custIndex !== null && $fruitIndex !== null && $boxIndex !== null) {
            $boxFile = "boxes_{$custIndex}_{$fruitIndex}.json";
            if (file_exists($boxFile)) {
                $boxData = json_decode(file_get_contents($boxFile), true);
                if (is_array($boxData) && isset($boxData[$boxIndex])) {
                    // Use future_rent directly without formatting to match fruit-detail.php display
                    if (isset($boxData[$boxIndex]['future_rent'])) {
                        $storeBoxRent = $boxData[$boxIndex]['future_rent'];
                    }
                }
            }
        }
        // If we couldn't get the actual value, fall back to the stored one
        if ($storeBoxRent === '0.00' && isset($n['store_box_rent']) && is_numeric($n['store_box_rent'])) {
            $storeBoxRent = $n['store_box_rent'];
        }
        $fruit = $n['fruit'] ?? 'Unknown Fruit';
        $chamber = $n['chamber'] ?? 'Unknown Chamber';
        $dueDays = isset($n['due_days']) && is_numeric($n['due_days']) ? formatMonthsAndDays($n['due_days']) : '0 Months & 0 Days';
        $dueDate = isset($n['date']) ? $n['date'] : 'Unknown Date';
        ?>
        <?php
        // Check if entity exists
        $entityExists = false;
        
        if ($custIndex !== null && $fruitIndex !== null && $boxIndex !== null) {
            $boxFile = "boxes_{$custIndex}_{$fruitIndex}.json";
            if (file_exists($boxFile)) {
                $boxData = json_decode(file_get_contents($boxFile), true);
                if (is_array($boxData) && isset($boxData[$boxIndex])) {
                    $entityExists = true;
                }
            }
        }
        
        // If entity doesn't exist, delete this notification
        if (!$entityExists && $custIndex !== null) {
            // Delete this notification silently
            array_splice($notifications, count($notifications) - 1 - $i, 1);
            file_put_contents('notifications.json', json_encode($notifications));
            continue; // Skip displaying this notification
        }
        ?>
        <div class="notif-item" style="cursor: pointer;" onclick="window.location.href='fruit-detail.php?cust=<?= $custIndex ?>&fruit=<?= $fruitIndex ?>#boxEntity_<?= $boxIndex ?>'">
          <div class="notif-message">
            Dear Admin,<br>
            🔔 Rent Due Alert:<h1> <?= htmlspecialchars($customer) ?></h1> has store box rent pending for <?= htmlspecialchars($fruit) ?> stored in Fruit Chamber <?= htmlspecialchars($chamber) ?>.<br>
            Due Months & Days: <?= htmlspecialchars($dueDays) ?><br>
            📅 Due Date: <?= htmlspecialchars($dueDate) ?>
          </div>
          <form method="POST" style="margin:0;" onclick="event.stopPropagation();">
            <input type="hidden" name="delete" value="<?= count($notifications) - 1 - $i ?>">
            <button type="submit" class="delete-btn" onclick="event.stopPropagation(); return confirm('Delete this notification?')">Delete</button>
          </form>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</body>
</html>