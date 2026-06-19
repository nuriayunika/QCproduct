<?php
// ambil_checklist_fi.php - dipanggil via AJAX
session_start();
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login") {
    echo json_encode([]); exit();
}
include 'koneksi.php';

$model = mysqli_real_escape_string($koneksi, $_POST['engine_model'] ?? '');
if (empty($model)) { echo json_encode([]); exit(); }

$result = mysqli_query($koneksi,
    "SELECT item_name, parameter FROM master_final_inspection
     WHERE engine_model = '$model' ORDER BY id ASC"
);

$items = [];
while ($row = mysqli_fetch_assoc($result)) {
    $items[] = $row;
}
echo json_encode($items);