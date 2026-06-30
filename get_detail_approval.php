<?php
session_start();
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login") {
    echo json_encode(['status'=>'error','message'=>'Unauthorized']); exit();
}
include 'koneksi.php';

$id    = intval($_GET['id']    ?? 0);
$modul = $_GET['modul'] ?? '';

if ($id <= 0) { echo json_encode(['status'=>'error','message'=>'ID tidak valid']); exit(); }

$tbl_map = [
    'test_running'     => 'result_test_run',
    'final_inspection' => 'final_inspection_data',
    'packing'          => 'packing_data',
];

$tbl = $tbl_map[$modul] ?? null;
if (!$tbl) { echo json_encode(['status'=>'error','message'=>'Modul tidak valid']); exit(); }

$row = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT * FROM `$tbl` WHERE id = $id"));
if (!$row) { echo json_encode(['status'=>'error','message'=>'Data tidak ditemukan']); exit(); }

// Ambil checklist
$checklist = [];
if ($modul === 'test_running') {
    $engine_no = mysqli_real_escape_string($koneksi, $row['engine_no']);
    $q = mysqli_query($koneksi, "SELECT * FROM checklist WHERE engine_no = '$engine_no'");
    if ($q) while ($r = mysqli_fetch_assoc($q)) $checklist[] = $r;
} elseif ($modul === 'final_inspection') {
    $q = mysqli_query($koneksi, "SELECT * FROM final_inspection_checklist WHERE fi_id = $id");
    if ($q) while ($r = mysqli_fetch_assoc($q)) $checklist[] = $r;
} elseif ($modul === 'packing') {
    $q = mysqli_query($koneksi, "SELECT * FROM packing_checklist WHERE pack_id = $id");
    if ($q) while ($r = mysqli_fetch_assoc($q)) $checklist[] = $r;
}

// Foto engine (base64 untuk ditampilkan di modal)
$foto = [];
for ($f = 1; $f <= 3; $f++) {
    $path = $row['foto_engine_'.$f] ?? '';
    if ($path && file_exists($path)) {
        $type = mime_content_type($path);
        $foto[] = 'data:'.$type.';base64,'.base64_encode(file_get_contents($path));
    }
}

echo json_encode([
    'status'    => 'ok',
    'row'       => $row,
    'checklist' => $checklist,
    'foto'      => $foto,
    'modul'     => $modul,
]);