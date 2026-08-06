<?php
// ambil_checklist_fi.php - dipanggil via AJAX
session_start();
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login") {
    echo json_encode([]); exit();
}
include 'koneksi.php';

$model = trim($_POST['engine_model'] ?? '');
if (empty($model)) { echo json_encode([]); exit(); }

// Cocokkan engine_model secara "longgar" (abaikan beda spasi/tanda hubung/kapitalisasi),
// supaya nggak gagal cuma karena penulisan model beda dikit antara result_test_run dan
// master_final_inspection (mis. "TF 70V E-2" vs "TF70V-E2").
$norm_target = strtoupper(preg_replace('/[\s\-]+/', '', $model));

$result = mysqli_query($koneksi, "SELECT item_name, parameter, engine_model, sort_order, foto_group, lokasi FROM master_final_inspection ORDER BY sort_order ASC, id ASC");

$items = [];
while ($row = mysqli_fetch_assoc($result)) {
    $norm_row = strtoupper(preg_replace('/[\s\-]+/', '', $row['engine_model']));
    if ($norm_row === $norm_target) {
        $items[] = [
            'item_name'  => $row['item_name'],
            'parameter'  => $row['parameter'],
            'foto_group' => (int) $row['foto_group'],
            'lokasi'     => $row['lokasi'],
        ];
    }
}
echo json_encode($items);