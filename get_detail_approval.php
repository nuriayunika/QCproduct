<?php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login") {
    echo json_encode(['status'=>'error','message'=>'Unauthorized']); exit();
}
include 'koneksi.php';
@ini_set('memory_limit', '256M');
@set_time_limit(60);

function cleanNum($v) {
    if ($v === null || $v === '') return $v;
    $s = trim((string) $v);
    if (preg_match('/^-?\d+(\.\d+)?$/', $s)) {
        return rtrim(rtrim(sprintf('%.10F', $s), '0'), '.') ?: '0';
    }
    return $s;
}
function cleanRow($row) {
    foreach ($row as $k => $v) $row[$k] = cleanNum($v);
    return $row;
}

if (!empty($_POST['engine_no'])) {
    $engine_no = mysqli_real_escape_string($koneksi, trim($_POST['engine_no']));

    $row = mysqli_fetch_assoc(mysqli_query(
        $koneksi,
        "SELECT * FROM result_test_run WHERE engine_no = '$engine_no' LIMIT 1"
    ));

    if (!$row) {
        echo json_encode(['status' => 'error', 'message' => 'Data Test Running tidak ditemukan untuk Engine No. tersebut.']);
        exit();
    }

    $checklist = [];
    $q = mysqli_query($koneksi, "SELECT * FROM checklist WHERE engine_no = '$engine_no'");
    if ($q) while ($r = mysqli_fetch_assoc($q)) $checklist[] = $r;

    $tr_id = intval($row['id']);
    $approved = false;
    $chk = mysqli_query(
        $koneksi,
        "SELECT id FROM approvals
         WHERE test_run_id = $tr_id
           AND stage = 'Test_Running'
           AND role  = 'Foreman'
           AND status = 'approved'
         LIMIT 1"
    );
    if ($chk && mysqli_num_rows($chk) > 0) $approved = true;

    $foto = [];
    for ($f = 1; $f <= 3; $f++) {
        $path = $row['foto_engine_'.$f] ?? '';
        if ($path && file_exists($path)) {
            $type = mime_content_type($path);
            $foto[] = 'data:'.$type.';base64,'.base64_encode(file_get_contents($path));
        } else {
            $foto[] = null;
        }
    }

    echo json_encode([
        'status'    => 'ok',
        'row'       => cleanRow($row),
        'checklist' => $checklist,
        'approved'  => $approved,
        'foto'      => $foto,
    ]);
    exit();
}

$id    = intval($_GET['id']    ?? ($_POST['id'] ?? 0));
$modul = $_GET['modul'] ?? ($_POST['modul'] ?? '');

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

$foto = [];
if ($modul === 'test_running') {
    for ($f = 1; $f <= 3; $f++) {
        $path = $row['foto_engine_'.$f] ?? '';
        if ($path && file_exists($path)) {
            $type = mime_content_type($path);
            $foto[] = 'data:'.$type.';base64,'.base64_encode(file_get_contents($path));
        }
    }
} elseif ($modul === 'final_inspection' || $modul === 'packing') {
    foreach ($checklist as $idx => $c) {
        $path = $c['foto_path'] ?? '';
        if ($path && file_exists($path)) {
            $type = mime_content_type($path);
            $checklist[$idx]['foto_base64'] = 'data:'.$type.';base64,'.base64_encode(file_get_contents($path));
        } else {
            $checklist[$idx]['foto_base64'] = null;
        }
    }
}

echo json_encode([
    'status'    => 'ok',
    'row'       => cleanRow($row),
    'checklist' => $checklist,
    'foto'      => $foto,
    'modul'     => $modul,
]);