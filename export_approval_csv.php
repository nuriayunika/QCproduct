<?php
session_start();
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login") {
    die("Unauthorized");
}
include 'koneksi.php';

$stage = $_GET['stage'] ?? '';

$stageMap = [
    'Test_Running'     => ['table' => 'result_test_run',       'levels' => [['role_key'=>'Foreman','label'=>'Foreman']]],
    'Final_Inspection' => ['table' => 'final_inspection_data', 'levels' => [['role_key'=>'Foreman','label'=>'Foreman'],['role_key'=>'Supervisor','label'=>'Supervisor']]],
    'Packing'          => ['table' => 'packing_data',          'levels' => [['role_key'=>'Foreman','label'=>'Foreman'],['role_key'=>'Supervisor','label'=>'Supervisor']]],
];

if (!isset($stageMap[$stage])) {
    die("Stage tidak valid.");
}

$dataTable = $stageMap[$stage]['table'];
$levels    = $stageMap[$stage]['levels'];

// ---- Filter (logic sama persis kayak di renderApprovalTable, index.php) ----
$filterPfx    = strtolower($stage);
$fltEngineNo  = trim($_GET['flt_engineno_' . $filterPfx] ?? '');
$fltModels    = array_filter((array) ($_GET['flt_model_' . $filterPfx] ?? []));
$fltOperators = array_filter((array) ($_GET['flt_operator_' . $filterPfx] ?? []));
$fltDateFrom  = trim($_GET['flt_datefrom_' . $filterPfx] ?? '');
$fltDateTo    = trim($_GET['flt_dateto_' . $filterPfx] ?? '');

$dateCol = ($stage === 'Test_Running') ? 'test_date' : 'created_at';

$whereParts = [];
if ($fltEngineNo !== '') {
    $whereParts[] = "engine_no LIKE '%" . mysqli_real_escape_string($koneksi, $fltEngineNo) . "%'";
}
if (count($fltModels) > 0) {
    $esc = array_map(function($v) use ($koneksi) { return "'" . mysqli_real_escape_string($koneksi, $v) . "'"; }, $fltModels);
    $whereParts[] = "engine_model IN (" . implode(',', $esc) . ")";
}
if (count($fltOperators) > 0) {
    $esc = array_map(function($v) use ($koneksi) { return "'" . mysqli_real_escape_string($koneksi, $v) . "'"; }, $fltOperators);
    $whereParts[] = "operator_name IN (" . implode(',', $esc) . ")";
}
if ($fltDateFrom !== '') {
    $whereParts[] = "DATE(`$dateCol`) >= '" . mysqli_real_escape_string($koneksi, $fltDateFrom) . "'";
}
if ($fltDateTo !== '') {
    $whereParts[] = "DATE(`$dateCol`) <= '" . mysqli_real_escape_string($koneksi, $fltDateTo) . "'";
}
$whereSql = count($whereParts) > 0 ? ('WHERE ' . implode(' AND ', $whereParts)) : '';

// ---- Ambil SEMUA data yang cocok filter (tanpa limit paginasi, export harus lengkap) ----
$rows = mysqli_query($koneksi, "SELECT * FROM `$dataTable` $whereSql ORDER BY id DESC");
if (!$rows) die("Query gagal: " . mysqli_error($koneksi));

// ---- Ambil semua approval record buat stage ini ----
$stage_esc    = mysqli_real_escape_string($koneksi, $stage);
$allApprovals = [];
$apvQuery = mysqli_query($koneksi, "SELECT * FROM approvals WHERE stage = '$stage_esc' ORDER BY id ASC");
if ($apvQuery) {
    while ($apvRow = mysqli_fetch_assoc($apvQuery)) {
        $allApprovals[$apvRow['test_run_id']][$apvRow['role']] = $apvRow;
    }
}

// ---- Output CSV ----
$filename = 'Approval_' . $stage . '_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
// BOM biar kebuka bener di Excel (karakter non-ASCII kayak simbol panah/derajat)
fwrite($out, "\xEF\xBB\xBF");

fputcsv($out, ['Engine No.', 'Engine Model', 'Operator', 'Tgl Submit', 'Pipeline Approval', 'Status']);

while ($row = mysqli_fetch_assoc($rows)) {
    $recordId  = $row['id'];
    $apvRecord = $allApprovals[$recordId] ?? [];

    $levelTexts  = [];
    $allApproved = true;
    $anyRejected = false;

    foreach ($levels as $lvl) {
        $rk  = $lvl['role_key'];
        $apv = $apvRecord[$rk] ?? null;
        if ($apv) {
            $st = $apv['status'];
            $when = !empty($apv['created_at']) ? date('Y-m-d H:i', strtotime($apv['created_at'])) : '';
            $levelTexts[] = $lvl['label'] . ': ' . ucfirst($st) . ($when ? ' (' . $when . ')' : '');
            if ($st === 'rejected') $anyRejected = true;
            if ($st !== 'approved') $allApproved = false;
        } else {
            $levelTexts[] = $lvl['label'] . ': Pending';
            $allApproved = false;
        }
    }
    $finalStatus = $anyRejected ? 'Rejected' : ($allApproved ? 'Approved' : 'Pending');

    $tgl = $row['test_date'] ?? $row['created_at'] ?? null;
    $tglStr = $tgl ? date('Y-m-d', strtotime($tgl)) : '-';

    fputcsv($out, [
        $row['engine_no'] ?? '-',
        $row['engine_model'] ?? '-',
        $row['operator_name'] ?? '-',
        $tglStr,
        implode(' -> ', $levelTexts),
        $finalStatus,
    ]);
}

fclose($out);