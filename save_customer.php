<?php
$data = json_decode(file_get_contents("customers.json"), true) ?: [];

$target_dir = "uploads/";
$photo = '';
if (isset($_FILES["photo"]) && !empty($_FILES["photo"]["name"])) {
    $filename = basename($_FILES["photo"]["name"]);
    $target_file = $target_dir . time() . "_" . $filename;
    if (move_uploaded_file($_FILES["photo"]["tmp_name"], $target_file)) {
        $photo = basename($target_file);
    } else {
        echo "Image upload failed.";
        exit;
    }
}
$newCustomer = [
    "name" => $_POST["name"],
    "phone" => $_POST["phone"],
    "address" => $_POST["address"],
    "photo" => $photo
];
$data[] = $newCustomer;
file_put_contents("customers.json", json_encode($data, JSON_PRETTY_PRINT));
header("Location: costumer-list.php");
?>