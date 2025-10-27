<?php
if (isset($_POST['index'])) {
    $data = json_decode(file_get_contents("customers.json"), true);
    $index = $_POST['index'];

    // Remove all fruit and boxes data for this customer
    $fruitFile = "fruits_{$index}.json";
    if (file_exists($fruitFile)) {
        $fruits = json_decode(file_get_contents($fruitFile), true);
        foreach ($fruits as $fruitIndex => $fruit) {
            $boxFile = "boxes_{$index}_{$fruitIndex}.json";
            if (file_exists($boxFile)) {
                unlink($boxFile);
            }
        }
        unlink($fruitFile);
    }

    // Remove uploaded photo too
    $photo = isset($data[$index]['photo']) ? $data[$index]['photo'] : '';
    $photo_path = "uploads/" . $photo;
    if (!empty($photo) && file_exists($photo_path) && is_file($photo_path)) unlink($photo_path);

    array_splice($data, $index, 1);
    file_put_contents("customers.json", json_encode($data, JSON_PRETTY_PRINT));
}
if (!headers_sent()) {
    header("Location: costumer-list.php");
    exit;
}
?>