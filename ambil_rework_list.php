<?php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login") {
    echo json_encode(['status'=>'error','message'=>'Unauthorized']); exit();
}
include 'koneksi.php';

$modul = $_GET['modul'] ?? '';

$map = [
    'test_running'     => ['table' => 'result_test_run',       'stage' => 'Test_Running',     'date_col' => 'test_date'],
    'final_inspection' => ['table' => 'final_inspection_data', 'stage' => 'Final_Inspection', 'date_col' => 'inspect_date'],
    'packing'          => ['table' => 'packing_data',          'stage' => 'Packing',          'date_col' => 'pack_date'],
];

if (!isset($map[$modul])) {
    echo json_encode(['status'=>'error','message'=>'Modul tidak valid']); exit();
}

$table    = $map[$modul]['table'];
$stage    = $map[$modul]['stage'];
$date_col = $map[$modul]['date_col'];

// Ambil record yang REJECTED TERAKHIR statusnya buat stage ini (belum ada rework-submit
// yang menimpanya - begitu di-rework, row approvals rejected-nya dihapus, jadi otomatis
// hilang dari list ini).
$rows = mysqli_query($koneksi, "
    SELECT t.id, t.engine_no, t.engine_model, t.$date_col AS tgl, a.rejection_note, a.role AS rejected_by, a.created_at AS rejected_at
    FROM `$table` t
    INNER JOIN approvals a ON a.test_run_id = t.id AND a.stage = '$stage' AND a.status = 'rejected'
    ORDER BY a.created_at DESC
");

$list = [];
if ($rows) {
    while ($r = mysqli_fetch_assoc($rows)) {
        $list[] = [
            'id'            => $r['id'],
            'engine_no'     => $r['engine_no'],
            'engine_model'  => $r['engine_model'],
            'tgl'           => $r['tgl'],
            'rejection_note'=> $r['rejection_note'],
            'rejected_by'   => $r['rejected_by'],
            'rejected_at'   => $r['rejected_at'],
        ];
    }
}

echo json_encode(['status' => 'ok', 'list' => $list]);