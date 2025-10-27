<?php
// --- Notification check logic with entity existence verification ---
$notifFile = 'notifications.json';
$notifications = [];
if (file_exists($notifFile)) {
    $notifications = json_decode(file_get_contents($notifFile), true);
    if (!is_array($notifications)) $notifications = [];
    
    // Check if any notification's entity doesn't exist and remove it
    $updated = false;
    foreach ($notifications as $i => $n) {
        $custIndex = isset($n['cust_index']) ? $n['cust_index'] : null;
        $fruitIndex = isset($n['fruit_index']) ? $n['fruit_index'] : null;
        $boxIndex = isset($n['box_index']) ? $n['box_index'] : null;
        
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
        
        // If entity doesn't exist, remove this notification
        if (!$entityExists && $custIndex !== null) {
            array_splice($notifications, $i, 1);
            $updated = true;
        }
    }
    
    // Save updated notifications if any were removed
    if ($updated) {
        file_put_contents($notifFile, json_encode($notifications));
    }
}

// Count unseen notifications
$unseenCount = 0;
foreach ($notifications as $n) {
    if (empty($n['seen'])) $unseenCount++;
}
echo $unseenCount;