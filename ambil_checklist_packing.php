<?php
session_start();
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login") {
    echo json_encode([]); exit();
}
include 'koneksi.php';

$result = mysqli_query($koneksi, "SELECT item_name, parameter FROM master_packing ORDER BY id ASC");
$items  = [];
while ($row = mysqli_fetch_assoc($result)) $items[] = $row;
echo json_encode($items);