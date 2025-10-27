<?php
$data = json_decode(file_get_contents("customers.json"), true);
$total = count($data);
?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Customer List</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .img-popup {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.8);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 999;
    flex-direction: column;
  }
  .img-popup img {
    max-width: 90vw;
    max-height: 80vh; /* <-- fix */
    border-radius: 10px;
    margin-bottom: 10px;
    background: #fff;
    box-shadow: 0 2px 12px rgba(0,0,0,0.2); /* <-- fix */
  }
  .back-btn {
    background: #fff;
    padding: 10px 20px;
    border-radius: 8px;
    font-weight: bold;
    color: #333;
    text-decoration: none;
  }
  .profile-placeholder {
      width: 60px;
      height: 60px;
      border-radius: 50%;
      background: #e3f2fd;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 2rem;
      font-weight: 700;
      color: #1976d2;
      box-shadow: 0 2px 8px rgba(33,150,243,0.10);
      margin-right: 12px;
      user-select: none;
    }
    .customer-card img, .profile-placeholder {
      margin-right: 12px;
    }
  </style>
</head>
<body>
  <div class="container">
    <a href="index.html" class="back-link">← Back</a>
    <h2>Total Customers: <?= $total ?></h2>
    <input type="text" class="search-bar" placeholder="Search..." onkeyup="searchCustomer(this.value)">

    <div id="customerList">
      <?php foreach ($data as $index => $customer): ?>
        <div class="customer-card" onclick="goToDetail(<?= $index ?>)">
          <?php
            $hasPhoto = !empty($customer['photo']) && file_exists('uploads/' . $customer['photo']);
            $firstLetter = strtoupper(substr($customer['name'], 0, 1));
          ?>
          <?php if ($hasPhoto): ?>
            <img src="uploads/<?= htmlspecialchars($customer['photo']) ?>" alt="Profile" onclick="event.stopPropagation(); showImage('uploads/<?= htmlspecialchars($customer['photo']) ?>')">
          <?php else: ?>
            <div class="profile-placeholder" onclick="event.stopPropagation();">
              <?= $firstLetter ?>
            </div>
          <?php endif; ?>
          <div class="info">
        &nbsp  Name:&nbsp  <?= htmlspecialchars($customer['name']) ?><br>
        &nbsp  Phone:&nbsp <?= htmlspecialchars($customer['phone']) ?>
          </div>
          <form method="POST" action="delete_customer.php" onsubmit="return confirm('Delete this customer?');">
            <input type="hidden" name="index" value="<?= $index ?>">
            <button class="delete-btn" onclick="event.stopPropagation();">Delete</button>
          </form>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Image Popup -->
  <div class="img-popup" id="imgPopup">
    <img id="popupImg" src="">
    <button class="back-btn" onclick="hideImage()">Back</button>
  </div>

 <script>
  function searchCustomer(value) {
    const cards = document.querySelectorAll('.customer-card');
    cards.forEach(card => {
      const name = card.querySelector('.info').innerText.toLowerCase();
      card.style.display = name.includes(value.toLowerCase()) ? 'flex' : 'none';
    });
  }

  function goToDetail(index) {
    window.location.href = `costumer-detail.php?index=${index}`; // <-- fix
  }

  function showImage(src) {
    document.getElementById("popupImg").src = src;
    document.getElementById("imgPopup").style.display = "flex";
  }

  function hideImage() {
    document.getElementById("imgPopup").style.display = "none";
  }
</script>
</body>
</html>