<?php
session_start();
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login") {
    header("location:login.php"); exit();
}
include 'koneksi.php';

$engine_no    = mysqli_real_escape_string($koneksi, $_POST['engine_no'] ?? '');
$engine_model = mysqli_real_escape_string($koneksi, $_POST['engine_model'] ?? '');
$operator     = mysqli_real_escape_string($koneksi, $_SESSION['nama_lengkap'] ?? '');
$inspect_date = date('Y-m-d');
$noted        = mysqli_real_escape_string($koneksi, $_POST['noted'] ?? '');

// 1. Insert header
$sql_header = "INSERT INTO final_inspection_data (engine_no, engine_model, operator_name, inspect_date, noted)
               VALUES ('$engine_no', '$engine_model', '$operator', '$inspect_date', '$noted')";

if (!mysqli_query($koneksi, $sql_header)) {
    die("Error header: " . mysqli_error($koneksi));
}
$fi_id = mysqli_insert_id($koneksi);

// 2. Insert checklist items
$items  = $_POST['item_name']  ?? [];
$params = $_POST['parameter']  ?? [];
$results= $_POST['result']     ?? [];
$files  = $_FILES['foto']      ?? [];

// Folder upload
$upload_dir = 'uploads/final_inspection/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

foreach ($items as $i => $item_name) {
    $item_esc  = mysqli_real_escape_string($koneksi, $item_name);
    $param_esc = mysqli_real_escape_string($koneksi, $params[$i] ?? '');
    $result    = in_array($results[$i] ?? '', ['OK','NG']) ? $results[$i] : 'OK';
    $foto_path = '';

    // Handle upload foto
    if (!empty($files['name'][$i]) && $files['error'][$i] === UPLOAD_ERR_OK) {
        $ext       = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
        $allowed   = ['jpg','jpeg','png','webp'];
        if (in_array($ext, $allowed)) {
            $filename  = 'fi_' . $fi_id . '_' . $i . '_' . time() . '.' . $ext;
            $dest      = $upload_dir . $filename;
            if (move_uploaded_file($files['tmp_name'][$i], $dest)) {
                $foto_path = $dest;
            }
        }
    }

    $foto_esc = mysqli_real_escape_string($koneksi, $foto_path);
    $sql_item = "INSERT INTO final_inspection_checklist (fi_id, item_name, parameter, result, foto_path)
                 VALUES ($fi_id, '$item_esc', '$param_esc', '$result', '$foto_esc')";
    mysqli_query($koneksi, $sql_item);
}

header("location:index.php?fi_success=1#final-inspection");
exit();