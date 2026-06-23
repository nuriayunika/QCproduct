<?php
session_start();
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login") {
    header("location:login.php"); exit();
}
include 'koneksi.php';

$role_s   = strtolower(trim($_SESSION['role']));
$is_op_pk = strpos($role_s, 'packing') !== false || $role_s === 'operator';
if (!$is_op_pk) {
    die("Akses ditolak.");
}

$engine_no    = mysqli_real_escape_string($koneksi, $_POST['engine_no']    ?? '');
$engine_model = mysqli_real_escape_string($koneksi, $_POST['engine_model'] ?? '');
$operator     = mysqli_real_escape_string($koneksi, $_SESSION['nama_lengkap']);
$pack_date    = date('Y-m-d');
$noted        = mysqli_real_escape_string($koneksi, $_POST['noted'] ?? '');

// 1. Insert header
$sql_header = "INSERT INTO packing_data (engine_no, engine_model, operator_name, pack_date, noted)
               VALUES ('$engine_no', '$engine_model', '$operator', '$pack_date', '$noted')";
if (!mysqli_query($koneksi, $sql_header)) {
    die("Error: " . mysqli_error($koneksi));
}
$pack_id = mysqli_insert_id($koneksi);

// 2. Insert checklist items
$items   = $_POST['item_name']  ?? [];
$params  = $_POST['parameter']  ?? [];
$results = $_POST['result']     ?? [];
$files   = $_FILES['foto']      ?? [];

$upload_dir = 'uploads/packing/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

foreach ($items as $i => $item_name) {
    $item_esc  = mysqli_real_escape_string($koneksi, $item_name);
    $param_esc = mysqli_real_escape_string($koneksi, $params[$i] ?? '');
    $result    = in_array($results[$i] ?? '', ['Check','NG','-']) ? $results[$i] : 'OK';
    $foto_path = '';

    if (!empty($files['name'][$i]) && $files['error'][$i] === UPLOAD_ERR_OK) {
        $ext     = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','webp'];
        if (in_array($ext, $allowed)) {
            $filename = 'pk_' . $pack_id . '_' . $i . '_' . time() . '.' . $ext;
            $dest     = $upload_dir . $filename;
            if (move_uploaded_file($files['tmp_name'][$i], $dest)) {
                $foto_path = $dest;
            }
        }
    }

    $foto_esc = mysqli_real_escape_string($koneksi, $foto_path);
    mysqli_query($koneksi, "INSERT INTO packing_checklist (pack_id, item_name, parameter, result, foto_path)
                            VALUES ($pack_id, '$item_esc', '$param_esc', '$result', '$foto_esc')");
}

// 3. Tidak ada notif email (operator packing tidak punya email)
header("location:index.php?pk_success=1#packing");
exit();