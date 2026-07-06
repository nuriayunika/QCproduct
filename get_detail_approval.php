<?php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login") {
    echo json_encode(['status'=>'error','message'=>'Unauthorized']); exit();
}
include 'koneksi.php';

// Kolom DECIMAL di MySQL selalu balik dengan nol di belakang koma sesuai scale-nya
// (mis. decimal(4,2) -> "1.00"). Fungsi ini membersihkannya jadi angka bersih tanpa
// mengubah field yang isinya teks campuran (mis. "4.8 kW/2600 rpm", "16.5°±0.7") atau
// angka desimal yang memang beneran (mis. "16.5" tetap "16.5", bukan dibulatkan).
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

// ===== MODE LOOKUP BY ENGINE_NO (untuk fitur Search TR di form Final Inspection) =====
// Dipanggil via POST { engine_no: '...' } tanpa id/modul.
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

    // Checklist visual inspection TR
    $checklist = [];
    $q = mysqli_query($koneksi, "SELECT * FROM checklist WHERE engine_no = '$engine_no'");
    if ($q) while ($r = mysqli_fetch_assoc($q)) $checklist[] = $r;

    // Status approval Foreman untuk Test Running (approval TR = selesai, tidak berjenjang)
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

    // Foto engine (base64, indeks 0/1/2 = foto_engine_1/2/3)
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

// ===== MODE LOOKUP BY ID + MODUL (dipakai Approval Dashboard) =====
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

// Foto (base64 untuk ditampilkan di modal)
// - test_running: 3 foto tetap di kolom foto_engine_1/2/3 pada result_test_run -> gallery terpisah
// - final_inspection / packing: foto per-item checklist -> ditempel langsung ke masing-masing
//   item checklist (key 'foto_base64'), supaya bisa ditampilkan di samping item-nya, bukan gallery
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