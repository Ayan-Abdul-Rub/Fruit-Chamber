<?php
$customers = json_decode(file_get_contents("customers.json"), true);
$notifFile = 'notifications.json';
$notifications = [];
if (file_exists($notifFile)) {
    $notifications = json_decode(file_get_contents($notifFile), true);
    if (!is_array($notifications)) $notifications = [];
}

foreach ($customers as $custIndex => $customer) {
    $fruitFile = "fruits_$custIndex.json";
    if (!file_exists($fruitFile)) continue;
    $fruits = json_decode(file_get_contents($fruitFile), true);
    if (!is_array($fruits)) continue;

    foreach ($fruits as $fruitIndex => $fruit) {
        $boxFile = "boxes_{$custIndex}_{$fruitIndex}.json";
        if (!file_exists($boxFile)) continue;
        $boxes = json_decode(file_get_contents($boxFile), true);
        if (!is_array($boxes)) continue;

        foreach ($boxes as $box) {
            $start = strtotime($box['date']);
            $now = strtotime(date('Y-m-d H:i'));
            $months = floor(($now - $start) / (60*60*24*30));
            if ($months >= 1 && isset($box['box']) && isset($box['rent_per_box'])) {
                // Calculate total rent: box * rent_per_box * months
                $pending = $box['box'] * $box['rent_per_box'] * $months;
                
                // Get store_box_rent from the box data if available
                $storeBoxRent = isset($box['future_rent']) ? $box['future_rent'] : $pending;

                // Ignore if rent is zero or negative
                if ($pending <= 0) continue;

                // Check for duplicate notification for this specific entity
                $alreadyExists = false;
                $boxIndex = array_search($box, $boxes);
                foreach ($notifications as $n) {
                    if (
                        $n['customer'] == $customer['name'] &&
                        $n['fruit'] == $fruit['fruitname'] &&
                        $n['chamber'] == $fruit['chamber'] &&
                        isset($n['cust_index']) && $n['cust_index'] == $custIndex &&
                        isset($n['fruit_index']) && $n['fruit_index'] == $fruitIndex &&
                        isset($n['box_index']) && $n['box_index'] == $boxIndex
                    ) {
                        $alreadyExists = true;
                        break;
                    }
                }
                if (!$alreadyExists) {
                    $boxIndex = array_search($box, $boxes);
                    $notifications[] = [
                        'customer' => $customer['name'],
                        'fruit' => $fruit['fruitname'],
                        'chamber' => $fruit['chamber'],
                        'pending' => $pending,
                        'store_box_rent' => $storeBoxRent,
                        'due_days' => ($now - $start) / (60*60*24),
                        'date' => date('d/m/Y', $start),
                        'cust_index' => $custIndex,
                        'fruit_index' => $fruitIndex,
                        'box_index' => $boxIndex
                    ];
                }
            }
        }
    }
}
file_put_contents($notifFile, json_encode($notifications));
echo "ok";