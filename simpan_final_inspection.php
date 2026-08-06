<?php
session_start();
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login") {
    header("location:login.php"); exit();
}
include 'koneksi.php';
include 'image_helper.php';
@ini_set('memory_limit', '256M');
@set_time_limit(120);

$engine_no = mysqli_real_escape_string($koneksi, trim($_POST['engine_no'] ?? ''));

// Validasi: engine_no wajib ada, TR-nya harus sudah approved Foreman, dan belum ada FI-nya.
// engine_model TIDAK diambil dari form lagi -> selalu ditarik dari data TR yang sudah tersimpan,
// supaya penulisan model selalu konsisten dan tidak bisa diketik ulang secara manual.
if ($engine_no === '') {
    header("location:index.php?fi_error=engine_kosong#final-inspection"); exit();
}

$tr = mysqli_fetch_assoc(mysqli_query($koneksi, "
    SELECT tr.id, tr.engine_model
    FROM result_test_run tr
    INNER JOIN approvals a ON a.test_run_id = tr.id AND a.stage='Test_Running' AND a.role='Foreman' AND a.status='approved'
    WHERE tr.engine_no = '$engine_no'
    ORDER BY tr.id DESC LIMIT 1
"));
if (!$tr) {
    header("location:index.php?fi_error=belum_approved_tr#final-inspection"); exit();
}

$existing_fi = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT id FROM final_inspection_data WHERE engine_no = '$engine_no' LIMIT 1"));
if ($existing_fi) {
    header("location:index.php?fi_error=sudah_ada#final-inspection"); exit();
}

$engine_model = mysqli_real_escape_string($koneksi, $tr['engine_model']);
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

// 2. Upload foto per grup dulu (satu file per grup, dipakai bareng sama semua item di grup itu)
$upload_dir = 'uploads/final_inspection/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

$group_photo_paths = []; // group_no => path foto yang sudah di-resize & disimpan
$group_files = $_FILES['foto_group'] ?? [];
if (!empty($group_files['name']) && is_array($group_files['name'])) {
    foreach ($group_files['name'] as $group_no => $orig_name) {
        if (empty($orig_name) || $group_files['error'][$group_no] !== UPLOAD_ERR_OK) continue;
        $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','webp'];
        if (!in_array($ext, $allowed)) continue;
        $filename = 'fi_' . $fi_id . '_grp' . $group_no . '_' . time() . '.' . $ext;
        $dest     = $upload_dir . $filename;
        $saved    = resizeAndSaveImage($group_files['tmp_name'][$group_no], $dest, 1200, 75);
        if ($saved) {
            $group_photo_paths[$group_no] = $saved;
        }
    }
}

// 3. Insert checklist items - tiap item pakai foto dari grup-nya (dari $group_photo_paths),
// bukan upload sendiri-sendiri.
$items       = $_POST['item_name']            ?? [];
$params      = $_POST['parameter']             ?? [];
$results     = $_POST['result']                ?? [];
$item_groups = $_POST['foto_group_of_item']    ?? [];

foreach ($items as $i => $item_name) {
    $item_esc   = mysqli_real_escape_string($koneksi, $item_name);
    $param_esc  = mysqli_real_escape_string($koneksi, $params[$i] ?? '');
    $result     = in_array($results[$i] ?? '', ['OK','NG']) ? $results[$i] : 'OK';
    $group_no   = intval($item_groups[$i] ?? 0);
    $foto_path  = $group_photo_paths[$group_no] ?? '';

    $foto_esc = mysqli_real_escape_string($koneksi, $foto_path);
    $sql_item = "INSERT INTO final_inspection_checklist (fi_id, item_name, parameter, result, foto_path, foto_group)
                 VALUES ($fi_id, '$item_esc', '$param_esc', '$result', '$foto_esc', $group_no)";
    mysqli_query($koneksi, $sql_item);
}

// Tidak ada notifikasi email saat submit (operator tidak punya email)

header("location:index.php?fi_success=1#final-inspection");
exit();