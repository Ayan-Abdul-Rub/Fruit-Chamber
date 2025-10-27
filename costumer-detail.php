<?php
// Get customer index from URL
$index = isset($_GET['index']) ? intval($_GET['index']) : 0;
$customers = json_decode(file_get_contents("customers.json"), true);
$customer = $customers[$index];

// Fruits data for this customer
$fruitFile = "fruits_$index.json";
if (!file_exists($fruitFile)) file_put_contents($fruitFile, json_encode([]));
$fruits = json_decode(file_get_contents($fruitFile), true);

// Add fruit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fruitname'], $_POST['chamber'])) {
    $fruits[] = [
        'fruitname' => $_POST['fruitname'],
        'chamber' => $_POST['chamber']
    ];
    file_put_contents($fruitFile, json_encode($fruits));
    // Create a fresh boxes file for the new fruit (no old data)
    $newIndex = count($fruits) - 1;
    $boxFile = "boxes_{$index}_{$newIndex}.json";
    file_put_contents($boxFile, json_encode([]));
    header("Location: costumer-detail.php?index=$index");
    exit;
}

// Delete fruit
if (isset($_GET['delete'])) {
    $del = intval($_GET['delete']);
    array_splice($fruits, $del, 1);
    file_put_contents($fruitFile, json_encode($fruits));
    // Also delete the corresponding boxes file for this fruit
    $boxFile = "boxes_{$index}_{$del}.json";
    if (file_exists($boxFile)) {
        unlink($boxFile);
    }
    // Remove notifications for this entity
    $notifFile = 'notifications.json';
    if (file_exists($notifFile)) {
        $notifications = json_decode(file_get_contents($notifFile), true);
        $notifications = array_values(array_filter($notifications, function($n) use ($index, $del) {
            return !(isset($n['cust']) && isset($n['fruit_index']) && $n['cust'] == $index && $n['fruit_index'] == $del);
        }));
        file_put_contents($notifFile, json_encode($notifications));
    }
    header("Location: costumer-detail.php?index=$index");
    exit;
}
$totalFruits = count($fruits);

// Calculate total rent for all fruits of this customer
$totalRent = 0;
$totalPaid = 0;
foreach ($fruits as $fruitIndex => $fruit) {
    $boxFile = "boxes_{$index}_{$fruitIndex}.json";
    if (file_exists($boxFile)) {
        $boxes = json_decode(file_get_contents($boxFile), true);
        foreach ($boxes as $box) {
            // Add only removed boxes rent (total_rent)
            $removedRent = 0;
            if (!empty($box['removed'])) {
                foreach ($box['removed'] as $rem) {
                    $removedRent += isset($rem['rent']) ? $rem['rent'] : 0;
                }
            }
            $totalRent += $removedRent;

            // Add paid amount
            if (!empty($box['paid'])) {
                foreach ($box['paid'] as $paid) {
                    $totalPaid += isset($paid['amount']) ? $paid['amount'] : 0;
                }
            }
        }
    }
}

// Net rent after payment
$netRent = $totalRent - $totalPaid;
if ($netRent < 0) $netRent = 0;

// Handle customer info update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_customer'])) {
    $newName = trim($_POST['name'] ?? $customer['name']);
    $newPhone = trim($_POST['phone'] ?? $customer['phone']);
    $newAddress = trim($_POST['address'] ?? $customer['address']);
    $customers[$index]['name'] = $newName;
    $customers[$index]['phone'] = $newPhone;
    $customers[$index]['address'] = $newAddress;
    file_put_contents('customers.json', json_encode($customers));
    header("Location: costumer-detail.php?index=$index");
    exit;
}

?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Customer Detail</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <!-- <link rel="stylesheet" href="style.css"> -->
  <style>
    .container {
      background: #fff;
      border-radius: 18px;
      box-shadow: 0 4px 16px rgba(33,150,243,0.10);
      margin: 32px auto;
      padding: 24px 16px;
      max-width: 500px;
      font-family: 'Poppins', sans-serif;
    }
    .detail-top {
      display: flex;
      align-items: center;
      gap: 28px;
      margin-bottom: 18px;
      background: linear-gradient(90deg,#e3f2fd 60%,#fff 100%);
      padding: 18px 12px 18px 0;
      border-radius: 14px;
      box-shadow: 0 2px 8px rgba(33,150,243,0.10);
    }
    .detail-photo {
      width: 120px;
      height: 120px;
      border-radius: 50%;
      object-fit: cover;
      border: 3px solid #1976d2;
      background: #e0e0e0;
      box-shadow: 0 2px 8px rgba(33,150,243,0.12);
    }
    .profile-placeholder-detail {
      width: 120px;
      height: 120px;
      border-radius: 50%;
      background: #e3f2fd;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 3.2rem;
      font-weight: 700;
      color: #1976d2;
      box-shadow: 0 2px 8px rgba(33,150,243,0.10);
      margin-right: 12px;
      user-select: none;
    }
    .detail-info-horizontal {
      display: flex;
      flex-direction: row;
      gap: 32px;
    }
    .detail-info-horizontal .label {
      color: #1976d2;
      font-weight: 600;
      font-size: 17px;
      margin-right: 4px;
    }
    .detail-info-horizontal .value {
      color: #222;
      font-size: 17px;
      font-weight: 500;
    }
    .total-rent-block {
      background: #e3f2fd;
      border-radius: 8px;
      padding: 10px 18px;
      display: inline-block;
      box-shadow: 0 2px 8px rgba(33,150,243,0.10);
      font-size: 18px;
      font-weight: 700;
      color: #388e3c;
      margin-top: 10px;
    }
    @media (max-width: 600px) {
      .detail-top {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
        padding: 12px 4px;
      }
      .detail-info-horizontal {
        flex-direction: column;
        gap: 8px;
      }
      .detail-photo {
        width: 80px;
        height: 80px;
      }
      .profile-placeholder-detail {
        width: 80px;
        height: 80px;
        font-size: 2.2rem;
      }
    }
    .detail-info {
      display: flex;
      flex-direction: column;
      gap: 10px;
      font-size: 18px;
    }
    .detail-info strong {
      font-size: 22px;
      color: #1976d2;
      font-weight: 700;
      margin-bottom: 2px;
    }
    .block-row {
      display: flex;
      gap: 16px;
      margin-bottom: 18px;
      justify-content: center;
    }
    .block {
      flex: 1;
      background: #f7f7f7;
      border-radius: 12px;
      box-shadow: 0 2px 6px rgba(0,0,0,0.08);
      padding: 18px 10px;
      text-align: center;
      font-size: 17px;
      font-weight: 600;
      color: #1976d2;
      transition: box-shadow 0.2s, background 0.2s;
    }
    .add-fruit-block {
      background: linear-gradient(90deg, #1976d2 0%, #42a5f5 100%);
      color: #fff;
      border: 2px solid #1976d2;
      font-size: 19px;
      font-weight: 700;
      box-shadow: 0 4px 16px rgba(33,150,243,0.18);
      letter-spacing: 1px;
      cursor: pointer;
    }
    .add-fruit-block:hover {
      background: linear-gradient(90deg, #42a5f5 0%, #1976d2 100%);
      color: #fff;
      box-shadow: 0 6px 18px rgba(33,150,243,0.22);
    }
    .total-fruits-block {
      background: #e3f2fd;
      color: #388e3c;
      border: 2px solid #388e3c;
      font-size: 18px;
      font-weight: 700;
      box-shadow: 0 2px 8px rgba(56,142,60,0.12);
      letter-spacing: 1px;
      cursor: default;
    }
    .fruit-list-heading {
      font-size: 1.1rem;
      font-weight: 600;
      margin: 18px 0 8px 0;
      color: #388e3c;
      text-align: left;
    }
    .fruit-list {
      list-style: none;
      padding: 0;
      margin: 0;
    }
    .fruit-entity {
      display: flex;
      align-items: center;
      justify-content: space-between;
      background: #fff;
      border-radius: 8px;
      box-shadow: 0 2px 6px rgba(0,0,0,0.08);
      margin-bottom: 10px;
      padding: 10px 12px;
      cursor: pointer;
      transition: box-shadow 0.2s, background 0.2s;
    }
    .fruit-entity:hover {
      box-shadow: 0 4px 12px rgba(33,150,243,0.15);
      background: #e0f7fa;
    }
    .fruit-labels {
      display: flex;
      gap: 30px;
      align-items: center;
      font-size: 15px;
      color: #333;
    }
    .fruit-labels span {
      color: #111;
      font-weight: 700;
      margin-right: 4px;
    }
    .fruit-labels br {
      display: none;
    }
    .fruit-labels .fruit-value {
      color: #1565c0;
      font-weight: 600;
      background: #e3f2fd;
      padding: 2px 8px;
      border-radius: 6px;
      margin-right: 10px;
      font-size: 15px;
      letter-spacing: 0.5px;
    }
    .fruit-delete-btn {
      background-color: #ff5252;
      border: none;
      color: white;
      padding: 6px 10px;
      border-radius: 6px;
      font-size: 13px;
      cursor: pointer;
      margin-left: 8px;
    }
    .add-fruit-form {
      background: #fff;
      border-radius: 10px;
      box-shadow: 0 2px 8px rgba(33,150,243,0.08);
      padding: 16px 12px;
      margin-bottom: 18px;
      display: none;
      flex-direction: column;
      gap: 10px;
      position: absolute;
      left: 50%;
      transform: translateX(-50%);
      top: 180px;
      z-index: 100;
      max-width: 350px;
      width: 90%;
      border: 2px solid #222; /* Black border */
    }
    .add-fruit-form input {
      padding: 10px;
      border-radius: 6px;
      border: 1px solid #ccc;
      font-size: 15px;
      font-family: 'Poppins', sans-serif;
    }
    .add-fruit-form label {
      font-weight: 500;
      color: #333;
      margin-bottom: 2px;
      font-size: 15px;
    }
    .add-fruit-form .button {
      width: 100%;
      margin-top: 8px;
      background: #1976d2;
      color: #fff;
      border: none;
      border-radius: 8px;
      font-size: 16px;
      padding: 10px;
      font-weight: 500;
      cursor: pointer;
    }
    .back-link {
      display: inline-block;
      margin-bottom: 16px;
      color: #1976d2;
      text-decoration: none;
      font-weight: bold;
    }
    .edit-icon {
      position: absolute;
      top: 0;
      right: -38px;
      background: #fff;
      border-radius: 50%;
      box-shadow: 0 2px 8px rgba(33,150,243,0.10);
      padding: 4px;
      transition: box-shadow 0.2s;
      z-index: 10;
    }
    .edit-icon:hover {
      box-shadow: 0 4px 12px #90caf9;
      background: #e3f2fd;
    }
    .edit-cust-form input {
      padding: 8px;
      border-radius: 6px;
      border: 1px solid #ccc;
      font-size: 15px;
      font-family: 'Poppins', sans-serif;
    }
    .edit-cust-form label {
      font-weight: 500;
      color: #1976d2;
      margin-bottom: 2px;
      font-size: 15px;
    }
    .edit-cust-form .button {
      width: 100%;
      margin-top: 8px;
      background: #1976d2;
      color: #fff;
      border: none;
      border-radius: 8px;
      font-size: 16px;
      padding: 10px;
      font-weight: 500;
      cursor: pointer;
    }
    @media (max-width: 600px) {
      .container {
        max-width: 100%;
        padding: 10px 4px;
      }
      .detail-top {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
      }
      .detail-photo {
        width: 80px;
        height: 80px;
      }
      .profile-placeholder-detail {
        width: 80px;
        height: 80px;
        font-size: 2.2rem;
      }
      .block-row {
        flex-direction: column;
        gap: 10px;
      }
      .block {
        font-size: 16px;
        padding: 14px 8px;
      }
      .add-fruit-form {
        top: 220px;
        max-width: 98vw;
      }
      .fruit-labels {
        gap: 10px;
        font-size: 14px;
      }
      .edit-icon {
        right: -10px;
        top: -10px;
        padding: 2px;
      }
      .edit-cust-form input {
        font-size: 13px;
        padding: 6px;
      }
      .edit-cust-form label {
        font-size: 13px;
      }
      .edit-cust-form .button {
        font-size: 14px;
        padding: 8px;
      }
    }
  </style>
  <script>
    function showAddFruitForm() {
      document.getElementById('addFruitForm').style.display = 'flex';
    }
    function hideAddFruitForm() {
      document.getElementById('addFruitForm').style.display = 'none';
    }
    function goToFruitDetail(custIndex, fruitIndex) {
      window.location.href = `fruit-detail.php?cust=${custIndex}&fruit=${fruitIndex}`;
    }
    function showEditForm() {
      document.getElementById('editCustomerForm').style.display = 'flex';
    }
    function hideEditForm() {
      document.getElementById('editCustomerForm').style.display = 'none';
    }
    function validateEditForm() {
      var name = document.getElementById('editName').value.trim();
      var phone = document.getElementById('editPhone').value.trim();
      var address = document.getElementById('editAddress').value.trim();
      if (!name || !phone || !address) {
        alert('Please fill all fields.');
        return false;
      }
      return true;
    }
  </script>
</head>
<body>
  <div class="container">
    <a href="costumer-list.php" class="back-link">← Back</a>
    <div class="detail-top">
      <?php
        $hasPhoto = !empty($customer['photo']) && file_exists('uploads/' . $customer['photo']);
        $firstLetter = strtoupper(substr($customer['name'], 0, 1));
      ?>
      <?php if ($hasPhoto): ?>
        <img class="detail-photo" src="uploads/<?= htmlspecialchars($customer['photo']) ?>" alt="Profile">
      <?php else: ?>
        <div class="profile-placeholder-detail">
          <?= $firstLetter ?>
        </div>
      <?php endif; ?>
      <div style="position:relative;">
        <span class="edit-icon" onclick="showEditForm()" title="Edit Info">
          <!-- Pencil/Edit SVG icon -->
          <svg width="28" height="28" viewBox="0 0 24 24" fill="none" style="vertical-align:middle;cursor:pointer;">
            <path d="M3 17.25V21h3.75l11.06-11.06a1.003 1.003 0 0 0 0-1.42l-2.83-2.83a1.003 1.003 0 0 0-1.42 0L3 17.25zm14.85-9.34a2.003 2.003 0 0 1 0 2.83l-1.06 1.06-2.83-2.83 1.06-1.06a2.003 2.003 0 0 1 2.83 0z" fill="#1976d2"/>
          </svg>
        </span>
        <div class="detail-info-horizontal">
          <div><span class="label">Name:</span> <span class="value" id="custNameDisp"><?= htmlspecialchars($customer['name']) ?></span></div>
          <div><span class="label">Phone:</span> <span class="value" id="custPhoneDisp"><?= htmlspecialchars($customer['phone']) ?></span></div>
          <div><span class="label">Address:</span> <span class="value" id="custAddrDisp"><?= htmlspecialchars($customer['address']) ?></span></div>
        </div>
        <div class="total-rent-block">
          Total Rent: <?= $totalRent ?> Rs<br>
          Amount Paid: <?= $totalPaid ?> Rs<br>
          <span style="color:#d32f2f;">Net Due: <?= $netRent ?> Rs</span>
        </div>
        <!-- Edit Form (hidden by default) -->
        <form id="editCustomerForm" class="edit-cust-form" method="POST" style="display:none;flex-direction:column;gap:10px;margin-top:10px;" onsubmit="return validateEditForm();">
          <input type="hidden" name="edit_customer" value="1">
          <label for="editName">Name</label>
          <input type="text" name="name" id="editName" value="<?= htmlspecialchars($customer['name']) ?>" required>
          <label for="editPhone">Phone</label>
          <input type="text" name="phone" id="editPhone" value="<?= htmlspecialchars($customer['phone']) ?>" required pattern="[0-9+\- ]{8,}">
          <label for="editAddress">Address</label>
          <input type="text" name="address" id="editAddress" value="<?= htmlspecialchars($customer['address']) ?>" required>
          <div style="display:flex;gap:10px;">
            <button type="submit" class="button" style="background:#1976d2;color:#fff;">Update</button>
            <button type="button" class="button" style="background:#ff5252;color:#fff;" onclick="hideEditForm()">Cancel</button>
          </div>
        </form>
      </div>
    </div>
    <div class="block-row">
      <div class="block total-fruits-block">Total Fruits: <?= $totalFruits ?></div>
      <div class="block add-fruit-block" onclick="showAddFruitForm()">+ Add Fruit</div>
    </div>
    <div class="fruit-list-heading">Available Fruits</div>
    <ul class="fruit-list">
      <?php foreach ($fruits as $i => $fruit): ?>
        <li class="fruit-entity" onclick="goToFruitDetail(<?= $index ?>, <?= $i ?>)">
          <div class="fruit-labels">
            <span>Fruit:</span> <span class="fruit-value"><?= htmlspecialchars($fruit['fruitname']) ?></span>
            <span>Chamber:</span> <span class="fruit-value"><?= htmlspecialchars($fruit['chamber']) ?></span>
          </div>
          <form method="GET" style="margin:0;" onclick="event.stopPropagation();" onsubmit="return confirm('Delete this fruit?');">
            <input type="hidden" name="index" value="<?= $index ?>">
            <input type="hidden" name="delete" value="<?= $i ?>">
            <button type="submit" class="fruit-delete-btn">Delete</button>
          </form>
        </li>
      <?php endforeach; ?>
    </ul>
    <!-- Add Fruit Form -->
    <form id="addFruitForm" class="add-fruit-form" method="POST" onsubmit="hideAddFruitForm()">
      <label for="fruitname">Fruit Name</label>
      <input type="text" name="fruitname" id="fruitname" required placeholder="Enter fruit name">
      <label for="chamber">Chamber Number</label>
      <input type="text" name="chamber" id="chamber" required placeholder="Enter chamber number">
      <button type="submit" class="button">Save</button>
      <button type="button" class="button" style="background:#ff5252;" onclick="hideAddFruitForm()">Cancel</button>
    </form>
  </div>
</body>
</html>