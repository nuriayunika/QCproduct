<?php 
session_start();

if(!isset($_SESSION['status']) || $_SESSION['status'] != "login"){
    header("location:login.php");
    exit();
}
include 'koneksi.php'; 

// Ambil role, lowercase & trim untuk konsistensi perbandingan
$role = strtolower(trim($_SESSION['role']));

// is_operator: role apapun yang MENGANDUNG kata "operator"
$is_operator = (strpos($role, 'operator') !== false || strpos($role, 'foreman') !== false);

// Area operator berdasarkan role
// operator_test / operator_test_*       -> hanya Test Running
// operator_final_inspection / operator_fi -> hanya Final Inspection  
// operator_packing                       -> hanya Packing
// operator (tanpa spesifik)              -> semua area (backward compatible)
$op_area_tr = (strpos($role, 'operator') !== false && (
    strpos($role, 'final') === false &&
    strpos($role, 'packing') === false
)); // test running: operator_test, operator, operator_*

$op_area_fi = (strpos($role, 'final') !== false || 
               strpos($role, 'fi') !== false && strpos($role, 'operator') !== false);

$op_area_pk = (strpos($role, 'packing') !== false || strpos($role, 'foreman') !== false);

// Kalau role operator generic (tidak spesifik), bisa semua area
if ($is_operator && !$op_area_fi && !$op_area_pk) {
    $op_area_tr = true; // default ke test running
}

// is_approver: role yang MENGANDUNG salah satu kata kunci approver
// Menangkap semua varian: foreman, supervisor, assistant_manager, asisten_manager, manager
$is_approver = (
    strpos($role, 'foreman')    !== false ||
    strpos($role, 'supervisor') !== false ||
    strpos($role, 'manager')    !== false   // menangkap: manager, assistant_manager, asisten_manager
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QC Product - Quality Management Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        /* =============================================
           GLOBAL
        ============================================= */
        :root {
            --maroon:     #7B1D1D;
            --maroon-dk:  #5a1414;
            --maroon-lt:  #a83232;
            --maroon-xlt: #f5e6e6;
            --gray-dk:    #3d3d3d;
            --gray-md:    #6c757d;
            --gray-lt:    #f0f0f0;
            --gray-bd:    #d0d0d0;
            --white:      #ffffff;
        }

        body { background-color: #ebebeb; font-family: 'Segoe UI', sans-serif; }

        .module-section      { display: none; }
        .module-section.active-module { display: block !important; }

        /* =============================================
           TOP NAVBAR
        ============================================= */
        .top-navbar {
            background: linear-gradient(135deg, var(--maroon-dk) 0%, var(--maroon) 60%, var(--maroon-lt) 100%);
            border-radius: 10px;
            padding: 10px 18px;
            box-shadow: 0 4px 15px rgba(123,29,29,0.25);
            margin: 12px 12px 0 12px;
        }

        /* Nav buttons operator */
        .qc-nav-btn {
            font-size: 12px; font-weight: 600; border-radius: 6px;
            padding: 6px 16px; border: 1.5px solid rgba(255,255,255,0.3);
            background: rgba(255,255,255,0.12); color: rgba(255,255,255,0.85);
            transition: all 0.2s ease; letter-spacing: 0.3px;
        }
        .qc-nav-btn:hover { background: rgba(255,255,255,0.22); color: #fff; border-color: rgba(255,255,255,0.5); }
        .qc-nav-btn.active {
            background: #fff; color: var(--maroon); border-color: #fff;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }

        /* Nav buttons approver */
        .approval-nav-btn {
            font-size: 12px; font-weight: 600; border-radius: 6px;
            padding: 6px 16px; border: 1.5px solid rgba(255,255,255,0.3);
            background: rgba(255,255,255,0.12); color: rgba(255,255,255,0.85);
            transition: all 0.2s ease;
        }
        .approval-nav-btn:hover { background: rgba(255,255,255,0.22); color: #fff; }
        .approval-nav-btn.active { background: #fff; color: var(--maroon); border-color: #fff; box-shadow: 0 2px 8px rgba(0,0,0,0.15); }

        /* User info pill */
        .user-pill {
            background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.25);
            border-radius: 8px; padding: 5px 12px;
            display: flex; align-items: center; gap: 8px;
        }
        .user-pill .user-name { font-size: 12px; font-weight: 700; color: #fff; }
        .user-pill .role-badge {
            background: rgba(0,0,0,0.25); color: #fff; font-size: 9px;
            font-weight: 700; padding: 2px 7px; border-radius: 4px;
            text-transform: uppercase; letter-spacing: 0.5px;
        }
        .btn-logout {
            background: rgba(255,255,255,0.15); border: 1.5px solid rgba(255,255,255,0.4);
            color: #fff; font-size: 11px; font-weight: 700; border-radius: 6px;
            padding: 6px 14px; transition: all 0.2s;
        }
        .btn-logout:hover { background: #fff; color: var(--maroon); }

        /* =============================================
           TEST RUNNING FORM
        ============================================= */
        .tr-header-card {
            background: linear-gradient(135deg, #fff 0%, #fdf5f5 100%);
            border: none; border-radius: 12px;
            box-shadow: 0 2px 12px rgba(123,29,29,0.1);
            border-left: 4px solid var(--maroon);
        }
        .tr-section-title {
            font-size: 11px; font-weight: 800; letter-spacing: 1.2px;
            text-transform: uppercase; color: var(--maroon);
            border-bottom: 2px solid var(--maroon-xlt);
            padding-bottom: 5px; margin-bottom: 10px;
        }
        .tr-label {
            font-size: 11px; font-weight: 600; color: var(--gray-dk);
            display: flex; align-items: center;
        }
        .tr-input {
            font-size: 12px; border-radius: 6px;
            border: 1.5px solid var(--gray-bd);
            transition: border-color 0.2s, box-shadow 0.2s;
            padding: 5px 10px; height: 32px;
        }
        .tr-input:focus {
            border-color: var(--maroon); box-shadow: 0 0 0 3px rgba(123,29,29,0.1);
            outline: none;
        }
        .tr-input.readonly-field {
            background: var(--gray-lt); color: var(--gray-md);
            font-weight: 600; cursor: default;
        }
        .tr-select {
            font-size: 12px; border-radius: 6px; height: 32px;
            border: 1.5px solid var(--maroon); font-weight: 700;
            color: var(--gray-dk); padding: 0 8px;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .tr-select:focus { border-color: var(--maroon-dk); box-shadow: 0 0 0 3px rgba(123,29,29,0.1); }

        /* Visual checklist section */
        .checklist-card {
            border: none; border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.07);
            background: #fff;
        }
        .checklist-header {
            background: linear-gradient(90deg, var(--maroon) 0%, var(--maroon-lt) 100%);
            color: #fff; font-size: 11px; font-weight: 700;
            letter-spacing: 1px; text-transform: uppercase;
            padding: 8px 14px; border-radius: 8px 8px 0 0;
            text-align: center;
        }
        .checklist-item {
            display: flex; justify-content: space-between; align-items: center;
            padding: 6px 10px; border-bottom: 1px solid #f5f5f5;
            transition: background 0.15s;
        }
        .checklist-item:hover { background: #fdf5f5; }
        .checklist-item:last-child { border-bottom: none; }
        .checklist-item-name { font-size: 11px; font-weight: 600; color: var(--gray-dk); max-width: 70%; }
        .checklist-select {
            font-size: 11px; font-weight: 700; border-radius: 5px;
            border: 1.5px solid var(--gray-bd); padding: 2px 6px;
            min-width: 75px; text-align: center; cursor: pointer;
        }

        /* Performance table */
        .perf-card {
            border: none; border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.07);
            overflow: hidden;
        }
        .perf-card-header {
            background: linear-gradient(90deg, var(--maroon-dk) 0%, var(--maroon) 100%);
            color: #fff; font-size: 12px; font-weight: 700;
            letter-spacing: 0.8px; padding: 10px 16px;
        }
        .perf-table { font-size: 11px; }
        .perf-table thead tr th {
            background-color: var(--gray-dk) !important; color: #fff !important;
            font-size: 10px; font-weight: 700; vertical-align: middle;
            text-align: center; padding: 6px 4px; border-color: #555;
        }
        .perf-table thead tr th.th-data1 { background-color: var(--maroon) !important; }
        .perf-table thead tr th.th-data2 { background-color: #1a5c3a !important; }
        .perf-table tbody td { vertical-align: middle; padding: 3px; }
        .perf-table .form-control {
            font-size: 11px; padding: 2px 4px; height: 28px;
            border-radius: 4px; border: 1px solid #ccc; text-align: center;
        }
        .perf-table .form-control:focus { border-color: var(--maroon); box-shadow: 0 0 0 2px rgba(123,29,29,0.1); }
        .std-label {
            display: block; font-size: 9px; font-weight: 700;
            color: #ffc107; margin-top: 2px;
        }

        /* Bottom cards */
        .bottom-card {
            border: none; border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.07);
            overflow: hidden;
        }
        .bottom-card-header {
            background: linear-gradient(90deg, var(--maroon) 0%, var(--maroon-lt) 100%);
            color: #fff; font-size: 11px; font-weight: 700;
            text-align: center; padding: 8px;
        }
        .bottom-table { font-size: 11px; }
        .bottom-table td, .bottom-table th {
            padding: 6px 8px; vertical-align: middle;
        }
        .bottom-table th {
            background-color: #f0f0f0; color: var(--gray-dk);
            font-weight: 700; font-size: 10px;
        }
        .bottom-table .form-control {
            font-size: 11px; height: 28px; padding: 2px 6px;
            border-radius: 5px; border: 1.5px solid #ccc; text-align: center;
        }
        .bottom-table .input-group-text {
            font-size: 10px; font-weight: 700; background: #e8e8e8;
            color: var(--gray-dk); border-color: #ccc;
        }

        /* Submit button */
        .btn-submit-tr {
            background: linear-gradient(135deg, var(--maroon) 0%, var(--maroon-lt) 100%);
            color: #fff; font-weight: 700; font-size: 14px;
            border: none; border-radius: 10px; padding: 14px 0;
            width: 100%; letter-spacing: 0.5px;
            box-shadow: 0 4px 15px rgba(123,29,29,0.3);
            transition: all 0.2s; cursor: pointer;
        }
        .btn-submit-tr:hover {
            background: linear-gradient(135deg, var(--maroon-dk) 0%, var(--maroon) 100%);
            box-shadow: 0 6px 20px rgba(123,29,29,0.4);
            transform: translateY(-1px);
        }

        /* =============================================
           APPROVAL DASHBOARD
        ============================================= */
        .pipeline-step { display:inline-flex; align-items:center; gap:4px; font-size:10px; font-weight:600; padding:3px 8px; border-radius:20px; border:1px solid #dee2e6; background:#f8f9fa; color:#6c757d; white-space:nowrap; }
        .pipeline-step.done   { background:#d1e7dd; color:#0a3622; border-color:#a3cfbb; }
        .pipeline-step.active { background:#fff3cd; color:#664d03; border-color:#ffc107; }
        .pipeline-step.reject { background:#f8d7da; color:#58151c; border-color:#f1aeb5; }
        .pipeline-arrow { color:#adb5bd; font-size:9px; margin:0 2px; }
        .approval-table th { font-size:11px; vertical-align:middle; background:linear-gradient(90deg,#5a1414,#7B1D1D); color:#fff; }
        .approval-table td { font-size:12px; vertical-align:middle; }
        .approval-table tr:hover td { background-color:#fdf5f5; transition: background 0.15s; }
        .badge-pending  { background-color:#ffc107; color:#212529; }
        .badge-approved { background-color:#198754; color:#fff; }
        .badge-rejected { background-color:#dc3545; color:#fff; }
        .badge-waiting  { background-color:#6c757d; color:#fff; }
        #modalRejectReason .modal-header { background: linear-gradient(90deg, var(--maroon-dk), var(--maroon)); color:#fff; }
    </style>
</head>
<body>

<!-- ===================================================
     TOP NAVBAR (shared semua role)
=================================================== -->
<div class="top-navbar d-flex justify-content-between align-items-center flex-nowrap">

    <div class="d-flex gap-2 align-items-center flex-wrap">
        <?php
/**
 * Render tabel approval.
 * Membaca data dari tabel utama (result_test_run / final_inspection_data / packing_data)
 * dan membaca status approval dari tabel `approvals` (tabel terpisah).
 *
 * @param string $dataTable  Tabel data utama: 'result_test_run', 'final_inspection_data', 'packing_data'
 * @param string $stage      Nilai stage di tabel approvals: 'Test_Running', 'Final_Inspection', 'Packing'
 * @param array  $levels     Urutan role approver beserta label DB-nya:
 *                           [ ['role_key'=>'Foreman','label'=>'Foreman'], ... ]
 * @param string $role       Role user login (sudah strtolower)
 * @param mysqli $koneksi    Koneksi DB
 */
function renderApprovalTable($dataTable, $stage, $levels, $role, $koneksi) {

    // Mapping role session (lowercase) ke nilai role di tabel approvals
    $roleMap = [
        'foreman'           => 'Foreman',
        'supervisor'        => 'Supervisor',
        'assistant_manager' => 'Asst_Manager',
        'asisten_manager'   => 'Asst_Manager',
        'manager'           => 'Asst_Manager',
    ];
    // Cari role_key DB dari role session yang aktif
    $myRoleDB = null;
    foreach ($roleMap as $sessionKey => $dbVal) {
        if (strpos($role, $sessionKey) !== false) {
            $myRoleDB = $dbVal;
            break;
        }
    }
    // Apakah role ini punya hak approve di modul ini?
    $canApprove = false;
    $myIdx      = -1;
    foreach ($levels as $i => $lvl) {
        if ($lvl['role_key'] === $myRoleDB) {
            $canApprove = true;
            $myIdx      = $i;
            break;
        }
    }
    // Supervisor & Manager bisa download semua PDF meski tidak ada di level approval modul
    $canView = (strpos($role, 'supervisor') !== false || strpos($role, 'manager') !== false);

    // Cek tabel data utama ada
    $tblCheck = mysqli_query($koneksi, "SHOW TABLES LIKE '$dataTable'");
    if (!$tblCheck || mysqli_num_rows($tblCheck) === 0) {
        echo '<div class="alert alert-info mb-0">
                <i class="fa-solid fa-circle-info me-2"></i>
                Tabel data <strong>'.$dataTable.'</strong> belum ada di database.
              </div>';
        return;
    }

    // Ambil semua data submit operator
    $rows = mysqli_query($koneksi,
        "SELECT * FROM `$dataTable` ORDER BY id DESC"
    );
    if (!$rows) {
        echo '<div class="alert alert-warning mb-0">Query gagal: '.mysqli_error($koneksi).'</div>';
        return;
    }

    // Ambil semua approval record untuk stage ini sekaligus (biar tidak N+1 query)
    $stage_esc    = mysqli_real_escape_string($koneksi, $stage);
    $allApprovals = [];
    $apvQuery = mysqli_query($koneksi,
        "SELECT * FROM approvals WHERE stage = '$stage_esc' ORDER BY id ASC"
    );
    if ($apvQuery) {
        while ($apvRow = mysqli_fetch_assoc($apvQuery)) {
            // Index: [test_run_id][role] = row
            $allApprovals[$apvRow['test_run_id']][$apvRow['role']] = $apvRow;
        }
    }
?>
    <div class="table-responsive">
    <table class="table table-bordered table-hover approval-table mb-0">
        <thead>
            <tr>
                <th style="width:40px;">#</th>
                <th>Engine No.</th>
                <th>Engine Model</th>
                <th>Operator</th>
                <th>Tgl Submit</th>
                <th>Pipeline Approval</th>
                <th>Status</th>
                <?php if($canApprove || $canView): ?><th style="width:190px;">Aksi</th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
        <?php
        $no = 1; $found = false;
        while ($row = mysqli_fetch_assoc($rows)):
            $found     = true;
            $recordId  = $row['id'];
            $apvRecord = $allApprovals[$recordId] ?? [];

            // Hitung status tiap level & status akhir
            $levelStatus  = [];  // ['Foreman' => 'approved'/'rejected'/'pending', ...]
            $finalStatus  = 'Pending';
            $allApproved  = true;
            $anyRejected  = false;
            $rejectNote   = '';

            foreach ($levels as $lvl) {
                $rk  = $lvl['role_key'];
                $apv = $apvRecord[$rk] ?? null;
                if ($apv) {
                    $levelStatus[$rk] = $apv['status']; // 'approved' or 'rejected'
                    if ($apv['status'] === 'rejected') {
                        $anyRejected = true;
                        $rejectNote  = $apv['rejection_note'] ?? '';
                        $allApproved = false;
                    }
                } else {
                    $levelStatus[$rk] = 'pending';
                    $allApproved      = false;
                }
            }
            if ($anyRejected)  $finalStatus = 'Rejected';
            elseif ($allApproved) $finalStatus = 'Approved';

            // Status role saya sendiri
            $myStatus   = $myRoleDB ? ($levelStatus[$myRoleDB] ?? 'pending') : 'pending';

            // Cek prerequisite (level sebelumnya harus approved)
            $prereqOk      = true;
            $anyPrevReject = false;
            for ($pi = 0; $pi < $myIdx; $pi++) {
                $prevKey = $levels[$pi]['role_key'];
                $prevSt  = $levelStatus[$prevKey] ?? 'pending';
                if ($prevSt === 'rejected') { $anyPrevReject = true; break; }
                if ($prevSt !== 'approved') { $prereqOk = false; }
            }

            $badgeClass = ($finalStatus==='Approved') ? 'badge-approved'
                        : (($finalStatus==='Rejected') ? 'badge-rejected' : 'badge-pending');
            $badgeIcon  = ($finalStatus==='Approved') ? 'fa-circle-check'
                        : (($finalStatus==='Rejected') ? 'fa-circle-xmark' : 'fa-clock');
        ?>
            <tr>
                <td class="text-center"><?php echo $no++; ?></td>
                <td class="fw-bold"><?php echo htmlspecialchars($row['engine_no'] ?? '-'); ?></td>
                <td><?php echo htmlspecialchars($row['engine_model'] ?? '-'); ?></td>
                <td><?php echo htmlspecialchars($row['operator_name'] ?? '-'); ?></td>
                <td><?php
                    $tgl = $row['test_date'] ?? $row['created_at'] ?? null;
                    echo $tgl ? date('d/m/Y', strtotime($tgl)) : '-';
                ?></td>

                <!-- Pipeline -->
                <td>
                    <div class="d-flex align-items-center flex-wrap gap-1">
                    <?php foreach ($levels as $i => $lvl):
                        $st  = $levelStatus[$lvl['role_key']] ?? 'pending';
                        $lbl = $lvl['label'];
                        $cls = ($st==='approved') ? 'done' : (($st==='rejected') ? 'reject' : 'active');
                        $ico = ($st==='approved') ? 'fa-check' : (($st==='rejected') ? 'fa-xmark' : 'fa-hourglass-half');
                        echo '<span class="pipeline-step '.$cls.'"><i class="fa-solid '.$ico.' me-1"></i>'.$lbl.'</span>';
                        if ($i < count($levels)-1) echo '<span class="pipeline-arrow"><i class="fa-solid fa-chevron-right"></i></span>';
                    endforeach; ?>
                    </div>
                    <?php if ($rejectNote): ?>
                        <div class="mt-1 text-danger" style="font-size:10px;">
                            <i class="fa-solid fa-triangle-exclamation me-1"></i><?php echo htmlspecialchars($rejectNote); ?>
                        </div>
                    <?php endif; ?>
                </td>

                <!-- Status akhir -->
                <td class="text-center">
                    <span class="badge <?php echo $badgeClass; ?> px-2 py-1" style="font-size:11px;">
                        <i class="fa-solid <?php echo $badgeIcon; ?> me-1"></i><?php echo $finalStatus; ?>
                    </span>
                </td>

                <!-- Tombol aksi -->
                <?php if ($canApprove || $canView): ?>
                <td class="text-center">
                    <?php if ($canView && !$canApprove):
                        // Supervisor/Manager: hanya tampilkan tombol Download PDF jika sudah approved semua level
                        $modul_pdf = $stage === 'Test_Running' ? 'test_running' : ($stage === 'Final_Inspection' ? 'final_inspection' : 'packing');
                        if ($finalStatus === 'Approved'): ?>
                        <a href="download_pdf.php?id=<?php echo $recordId; ?>&modul=<?php echo $modul_pdf; ?>"
                           class="btn btn-sm fw-bold" target="_blank"
                           style="background:#7B1D1D;color:#fff;border:none;font-size:10px;padding:2px 8px;border-radius:4px;">
                            <i class="fa-solid fa-file-pdf me-1"></i>Download PDF
                        </a>
                    <?php else: ?>
                        <span class="badge badge-waiting px-2 py-1" style="font-size:10px;">
                            <i class="fa-solid fa-lock me-1"></i>Belum Selesai Approve
                        </span>
                    <?php endif; ?>
                    <?php elseif ($myStatus === 'approved'): ?>
                        <div class="d-flex flex-column gap-1 align-items-center">
                            <span class="badge badge-approved px-2 py-1" style="font-size:10px;">
                                <i class="fa-solid fa-check me-1"></i>Sudah Approved
                            </span>
                            <?php 
                            $modul_pdf = $stage === 'Test_Running' ? 'test_running' : ($stage === 'Final_Inspection' ? 'final_inspection' : 'packing');
                            ?>
                            <a href="download_pdf.php?id=<?php echo $recordId; ?>&modul=<?php echo $modul_pdf; ?>"
                               class="btn btn-sm fw-bold" target="_blank"
                               style="background:#7B1D1D;color:#fff;border:none;font-size:10px;padding:2px 8px;border-radius:4px;">
                                <i class="fa-solid fa-file-pdf me-1"></i>Download PDF
                            </a>
                        </div>
                    <?php elseif ($myStatus === 'rejected'): ?>
                        <span class="badge badge-rejected px-2 py-1" style="font-size:10px;">
                            <i class="fa-solid fa-xmark me-1"></i>Sudah Rejected
                        </span>
                    <?php elseif ($anyPrevReject): ?>
                        <span class="badge badge-waiting px-2 py-1" style="font-size:10px;">
                            <i class="fa-solid fa-ban me-1"></i>Ada yang Direject
                        </span>
                    <?php elseif (!$prereqOk): ?>
                        <span class="badge badge-waiting px-2 py-1" style="font-size:10px;">
                            <i class="fa-solid fa-lock me-1"></i>Tunggu Level Sebelumnya
                        </span>
                    <?php else: ?>
                        <?php
                        $modul_detail = $stage === 'Test_Running' ? 'test_running' : ($stage === 'Final_Inspection' ? 'final_inspection' : 'packing');
                        ?>
                        <div class="d-flex gap-1 justify-content-center">
                            <button class="btn btn-sm fw-bold"
                                    style="background:#5a1414;color:#fff;border:none;"
                                    onclick="lihatDetail(<?php echo $recordId; ?>, '<?php echo $modul_detail; ?>', <?php echo $recordId; ?>, '<?php echo $stage; ?>', '<?php echo $myRoleDB; ?>')">
                                <i class="fa-solid fa-magnifying-glass me-1"></i>Detail
                            </button>
                            <button class="btn btn-success btn-sm fw-bold"
                                    onclick="doApprove(<?php echo $recordId; ?>, '<?php echo $stage; ?>', '<?php echo $myRoleDB; ?>')">
                                <i class="fa-solid fa-check me-1"></i>Approve
                            </button>
                            <button class="btn btn-danger btn-sm fw-bold"
                                    onclick="openRejectModal(<?php echo $recordId; ?>, '<?php echo $stage; ?>', '<?php echo $myRoleDB; ?>')">
                                <i class="fa-solid fa-xmark me-1"></i>Reject
                            </button>
                        </div>
                    <?php endif; ?>
                </td>
                <?php endif; ?>
            </tr>
        <?php endwhile; ?>
        <?php if (!$found): ?>
            <tr>
                <td colspan="<?php echo ($canApprove || $canView) ? 8 : 7; ?>" class="text-center text-muted py-5">
                    <i class="fa-solid fa-inbox fa-2x mb-2 d-block"></i>
                    Belum ada data yang disubmit operator.
                </td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
<?php
} // end renderApprovalTable
?>


<?php if($is_operator): ?>
            <i class="fa-solid fa-industry text-white opacity-75 me-1" style="font-size:16px;"></i>
            <button type="button" class="btn qc-nav-btn active" id="btn-test-running" onclick="switchModule('test-running')">
                <i class="fa-solid fa-gauge-high me-1"></i>Test Running
            </button>
            <button type="button" class="btn qc-nav-btn" id="btn-final-inspection" onclick="switchModule('final-inspection')">
                <i class="fa-solid fa-clipboard-list me-1"></i>Final Inspection
            </button>
            <button type="button" class="btn qc-nav-btn" id="btn-packing" onclick="switchModule('packing')">
                <i class="fa-solid fa-box me-1"></i>Packing
            </button>
            <?php if($is_approver): ?>
            <div style="width:1px; background:rgba(255,255,255,0.3); height:24px; margin:0 4px; align-self:center;"></div>
            <button type="button" class="btn qc-nav-btn" id="btn-approval-mode" onclick="switchToApprovalMode()"
                    style="background:rgba(255,255,255,0.15); border-color:rgba(255,255,255,0.5);">
                <i class="fa-solid fa-clipboard-check me-1"></i>Approval
            </button>
            <?php endif; ?>
        <?php elseif($is_approver): ?>
            <i class="fa-solid fa-check-double text-white opacity-75 me-1" style="font-size:16px;"></i>
            <button type="button" class="btn approval-nav-btn active" id="btn-test-running" onclick="switchModule('test-running')">
                <i class="fa-solid fa-gauge-high me-1"></i>Test Running
            </button>
            <button type="button" class="btn approval-nav-btn" id="btn-final-inspection" onclick="switchModule('final-inspection')">
                <i class="fa-solid fa-clipboard-list me-1"></i>Final Inspection
            </button>
            <button type="button" class="btn approval-nav-btn" id="btn-packing" onclick="switchModule('packing')">
                <i class="fa-solid fa-box me-1"></i>Packing
            </button>
            <?php if(strpos($role,'supervisor')!==false || strpos($role,'manager')!==false): ?>
            <div style="width:1px; background:rgba(255,255,255,0.3); height:24px; margin:0 4px;"></div>
            <a href="batch_pdf.php" class="btn qc-nav-btn" style="background:rgba(255,255,255,0.15); border-color:rgba(255,255,255,0.5);">
                <i class="fa-solid fa-file-pdf me-1"></i>Batch PDF
            </a>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <div class="d-flex align-items-center gap-2">
        <div class="user-pill">
            <i class="fa-solid fa-user-gear text-white opacity-75" style="font-size:13px;"></i>
            <span class="user-name"><?php echo htmlspecialchars($_SESSION['nama_lengkap']); ?></span>
            <span class="role-badge"><?php echo str_replace('_', ' ', $_SESSION['role']); ?></span>
        </div>
        <a href="logout.php" class="btn btn-logout"
           onclick="return confirm('Yakin ingin keluar?')">
            <i class="fa-solid fa-right-from-bracket me-1"></i>LOG OUT
        </a>
    </div>

</div>


<?php if($is_operator): ?>
<!-- ===================================================
     OPERATOR VIEW — FORM INPUT
=================================================== -->

<!-- ---- TAB: TEST RUNNING ---- -->
<div id="sec-test-running" class="module-section active-module">
    <div class="container-fluid pb-3">
        <form action="simpan_test_run.php" method="POST" enctype="multipart/form-data" onsubmit="return validateTRForm(this)">

            <div class="card mb-3 tr-header-card">
                <div class="card-header py-0 border-0" style="background:linear-gradient(135deg,#5a1414 0%,#7B1D1D 60%,#a83232 100%); border-radius:12px 12px 0 0;">
                    <div class="d-flex align-items-center gap-2 py-2 px-1">
                        <i class="fa-solid fa-gauge-high text-white" style="font-size:18px;"></i>
                        <h5 class="card-title m-0 fw-bold text-white" style="letter-spacing:1px;">TEST RUNNING</h5>
                        <span class="ms-auto badge" style="background:rgba(255,255,255,0.2); color:#fff; font-size:10px;">Full Load</span>
                    </div>
                </div>
                <div class="card-body p-3" style="background:linear-gradient(135deg,#fff 0%,#fdf5f5 100%);">
                    <div class="row g-2">
                        <div class="col-md-3">
                            <div class="row mb-1">
                                <label class="col-sm-5 col-form-label col-form-label-sm" style="font-size:12px; font-weight:500; color:#555;" style="font-size:12px; font-weight:500; color:#555;">Test Name</label>
                                <div class="col-sm-7">
                                    <input type="text" name="test_name" class="form-control form-control-sm" style="font-size:12px; background:#f7f7f7; color:#666;" value="Full load" readonly>
                                </div>
                            </div>
                            <div class="row mb-1">
                                <label class="col-sm-5 col-form-label col-form-label-sm" style="font-size:12px; font-weight:500; color:#555;" style="font-size:12px; font-weight:500; color:#555;">Engine Model</label>
                                <div class="col-sm-7">
                                    <select name="engine_model" id="engine_model" class="form-select form-select-sm" style="font-size:12px;" style="font-size:12px; font-weight:500; border-color:#7B1D1D;" required>
                                        <option value="">- Pilih Model -</option>
                                        <?php 
                                        $q_model = mysqli_query($koneksi, "SELECT DISTINCT engine_model FROM master_engine_spec ORDER BY CAST(REPLACE(REPLACE(REPLACE(engine_model,'TF',''),'V',''),'-','') AS UNSIGNED) ASC, engine_model ASC");
                                        while($m = mysqli_fetch_array($q_model)) {
                                            echo "<option value='".$m['engine_model']."'>".$m['engine_model']."</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                            <div class="row mb-1">
                                <label class="col-sm-5 col-form-label col-form-label-sm" style="font-size:12px; font-weight:500; color:#555;" style="font-size:12px; font-weight:500; color:#555;">Engine No.</label>
                                <div class="col-sm-7">
                                    <input type="text" name="engine_no" class="form-control form-control-sm" style="font-size:12px;" required placeholder="Ketik No. Mesin...">
                                </div>
                            </div>
                            <div class="row mb-1">
                                <label class="col-sm-5 col-form-label col-form-label-sm" style="font-size:12px; font-weight:500; color:#555;" style="font-size:12px; font-weight:500; color:#555;">Cont. Power</label>
                                <div class="col-sm-7">
                                    <input type="text" name="cont_power" id="cont_power" class="form-control form-control-sm" style="font-size:12px; background:#f7f7f7; color:#666;" readonly placeholder="Menunggu model...">
                                </div>
                            </div>
                            <div class="row mb-1">
                                <label class="col-sm-5 col-form-label col-form-label-sm" style="font-size:12px; font-weight:500; color:#555;" style="font-size:12px; font-weight:500; color:#555;">Max Power</label>
                                <div class="col-sm-7">
                                    <input type="text" name="max_power" id="max_power" class="form-control form-control-sm" style="font-size:12px; background:#f7f7f7; color:#666;" readonly placeholder="Menunggu model...">
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="row mb-1">
                                <label class="col-sm-5 col-form-label col-form-label-sm" style="font-size:12px; font-weight:500; color:#555;" style="font-size:12px; font-weight:500; color:#555;">Test Date</label>
                                <div class="col-sm-7">
                                    <input type="text" class="form-control form-control-sm" style="font-size:12px; background:#f7f7f7; color:#666;" value="<?php echo date('d/m/Y'); ?>" readonly>
                                    <input type="hidden" name="test_date" value="<?php echo date('Y-m-d'); ?>">
                                </div>
                            </div>
                            <div class="row mb-1">
                                <label class="col-sm-5 col-form-label col-form-label-sm" style="font-size:12px; font-weight:500; color:#555;" style="font-size:12px; font-weight:500; color:#555;">Bench Test</label>
                                <div class="col-sm-7">
                                    <select name="bench_test" class="form-select form-select-sm" style="font-size:12px;">
                                        <option value="No.1 ED 22">No.1 ED 22</option>
                                        <option value="No.2 ED 22">No.2 ED 22</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row mb-1">
                                <label class="col-sm-5 col-form-label col-form-label-sm" style="font-size:12px; font-weight:500; color:#555;" style="font-size:12px; font-weight:500; color:#555;">Operator Name</label>
                                <div class="col-sm-7">
                                    <input type="text" class="form-control" style="font-size:12px;" name="operator_name" value="<?php echo $_SESSION['nama_lengkap']; ?>" readonly>
                                </div>
                            </div>
                            <div class="row mb-1">
                                <label class="col-sm-5 col-form-label col-form-label-sm" style="font-size:12px; font-weight:500; color:#555;">Lube Oil</label>
                                <div class="col-sm-7">
                                    <input type="text" name="lube_oil" class="form-control form-control-sm" style="font-size:12px; background:#f7f7f7; color:#666;" value="Meditran SAE-40" readonly>
                                    <input type="hidden" name="cont_power" id="cont_power_val">
                                    <input type="hidden" name="max_power" id="max_power_val">
                                    <input type="hidden" name="hi_idle_std" id="hi_idle_std_val">
                                </div>
                            </div>

                        </div>

                        <div class="col-md-3">
                            <div class="row mb-1">
                                <label class="col-sm-5 col-form-label col-form-label-sm" style="font-size:12px; font-weight:500; color:#555;" style="font-size:12px; font-weight:500; color:#555;">Fuel</label>
                                <div class="col-sm-7">
                                    <input type="text" name="fuel_type" class="form-control form-control-sm" style="font-size:12px; background:#f7f7f7; color:#666;" value="B0" readonly>
                                </div>
                            </div>
                            <div class="row mb-1">
                                <label class="col-sm-5 col-form-label col-form-label-sm" style="font-size:12px; font-weight:500; color:#555;" style="font-size:12px; font-weight:500; color:#555;">Fuel sp. Grafity</label>
                                <div class="col-sm-7"><input type="number" name="fuel_sp_gravity" step="0.001" class="form-control form-control-sm" style="font-size:12px;"></div>
                            </div>
                            <div class="row mb-1">
                                <label class="col-sm-5 col-form-label col-form-label-sm" style="font-size:12px; font-weight:500; color:#555;" style="font-size:12px; font-weight:500; color:#555;">Dry temp (°C)</label>
                                <div class="col-sm-7"><input type="number" name="dry_temp" step="0.1" class="form-control form-control-sm" style="font-size:12px;"></div>
                            </div>
                            <div class="row mb-1">
                                <label class="col-sm-5 col-form-label col-form-label-sm" style="font-size:12px; font-weight:500; color:#555;" style="font-size:12px; font-weight:500; color:#555;">Wet temp (°C)</label>
                                <div class="col-sm-7"><input type="number" name="wet_temp" step="0.1" class="form-control form-control-sm" style="font-size:12px;"></div>
                            </div>
                            <div class="row mb-1">
                                <label class="col-sm-5 col-form-label col-form-label-sm" style="font-size:12px; font-weight:500; color:#555;" style="font-size:12px; font-weight:500; color:#555;">Atmosphere press</label>
                                <div class="col-sm-7"><input type="number" name="atmosphere_press" step="0.1" class="form-control form-control-sm" style="font-size:12px;"></div>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="row mb-1 align-items-center">
                                <label class="col-sm-4 col-form-label col-form-label-sm" style="font-size:12px; font-weight:500; color:#555;">Limiter Act.</label>
                                <div class="col-sm-8">
                                    <input type="text" name="limiter_actual" class="form-control form-control-sm" style="font-size:12px;">
                                </div>
                            </div>
                            <div class="row mb-1 align-items-center">
                                <label class="col-sm-4 col-form-label col-form-label-sm" style="font-size:12px; font-weight:500; color:#555;">Limiter After Set</label>
                                <div class="col-sm-8">
                                    <input type="text" name="limiter_after_set" class="form-control form-control-sm" style="font-size:12px;">
                                </div>
                            </div>
                            <div class="row mb-1 align-items-center">
                                <label class="col-sm-4 col-form-label col-form-label-sm" style="font-size:12px; font-weight:500; color:#555;">Hi Idle</label>
                                <div class="col-sm-4">
                                    <input type="text" id="lbl_hi_idle" class="form-control form-control-sm bg-light text-secondary text-center fw-bold" readonly placeholder="-">
                                </div>
                                <div class="col-sm-4">
                                    <input type="number" name="hi_idle_actual" class="form-control form-control-sm bg-white text-center">
                                </div>
                            </div>
                            <div class="row mb-1 align-items-center">
                                <label class="col-sm-4 col-form-label col-form-label-sm" style="font-size:12px; font-weight:500; color:#555;">Eng. Speed Max</label>
                                <div class="col-sm-8">
                                    <input type="number" name="eng_speed_max" class="form-control form-control-sm" style="font-size:12px;">
                                </div>
                            </div>
                            <div class="row mb-1 align-items-center">
                                <label class="col-sm-4 col-form-label col-form-label-sm" style="font-size:12px; font-weight:500; color:#555;">Eng. Speed Min</label>
                                <div class="col-sm-8">
                                    <input type="number" name="eng_speed_min" class="form-control form-control-sm" style="font-size:12px;">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3 shadow-sm">
                <div class="card-body p-3">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="checklist-header">Leakage Check</div>
                            <div class="px-2" style="max-height: 200px; overflow-y: auto;">
                                <?php 
                                $q_leak = mysqli_query($koneksi, "SELECT * FROM master_visual_checklist WHERE visual_inspection='Leakage Check'");
                                while($l = mysqli_fetch_array($q_leak)) { ?>
                                    <div class="mb-2 d-flex justify-content-between align-items-center border-bottom pb-2">
                                        <span style="font-size:12px; max-width:70%;" class="fw-semibold"><?php echo $l['item_checking']; ?></span>
                                        <input type="hidden" name="chk_item[]" value="<?php echo $l['item_checking']; ?>">
                                        <input type="hidden" name="chk_type[]" value="Leakage Check">
                                        <select name="chk_val[]" class="form-select form-select-sm text-center fw-bold border-secondary" style="min-width: 90px; max-width: 100px;">
                                            <option value="Yes">Yes</option>
                                            <option value="No">No</option>
                                        </select>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>

                        <div class="col-md-4 border-start border-end">
                            <div class="checklist-header">Assembly Check</div>
                            <div class="px-2" style="max-height: 200px; overflow-y: auto;">
                                <?php 
                                $q_ass = mysqli_query($koneksi, "SELECT * FROM master_visual_checklist WHERE visual_inspection='Assembly Check'");
                                while($a = mysqli_fetch_array($q_ass)) { ?>
                                    <div class="mb-2 d-flex justify-content-between align-items-center border-bottom pb-2">
                                        <span style="font-size:12px; max-width:70%;" class="fw-semibold"><?php echo $a['item_checking']; ?></span>
                                        <input type="hidden" name="chk_item[]" value="<?php echo $a['item_checking']; ?>">
                                        <input type="hidden" name="chk_type[]" value="Assembly Check">
                                        <select name="chk_val[]" class="form-select form-select-sm text-center fw-bold border-secondary" style="min-width: 90px; max-width: 100px;">
                                            <option value="Yes">Yes</option>
                                            <option value="No">No</option>
                                        </select>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="checklist-header">Function of Component</div>
                            <div class="px-2" style="max-height: 200px; overflow-y: auto;">
                                <?php 
                                $q_fun = mysqli_query($koneksi, "SELECT * FROM master_visual_checklist WHERE visual_inspection='Function of Component'");
                                while($f = mysqli_fetch_array($q_fun)) { ?>
                                    <div class="mb-2 d-flex justify-content-between align-items-center border-bottom pb-2">
                                        <span style="font-size:12px; max-width:70%;" class="fw-semibold"><?php echo $f['item_checking']; ?></span>
                                        <input type="hidden" name="chk_item[]" value="<?php echo $f['item_checking']; ?>">
                                        <input type="hidden" name="chk_type[]" value="Function of Component">
                                        <select name="chk_val[]" class="form-select form-select-sm text-center fw-bold border-secondary" style="min-width: 90px; max-width: 100px;">
                                            <option value="OK">OK</option>
                                            <option value="NG">NG</option>
                                        </select>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3 perf-card">
                <div class="perf-card-header"><i class="fa-solid fa-chart-line me-2"></i>MAIN DATA PERFORMANCE TEST (DATA 1 & DATA 2 INTEGRATED)</div>
                <div class="card-body p-2">
                    <div class="scrollable-table">
                        <table class="table table-sm table-bordered m-0 text-center align-middle">
                            <thead>
                                <tr>
                                    <th rowspan="3" style="width: 40px;">No</th>
                                    <th rowspan="3" style="min-width: 130px;">Eng. Speed min-1</th>
                                    <th colspan="8" class="th-data1">OUTPUT, TORQUE & FUEL (DATA 1)</th>
                                    <th colspan="10" class="th-data2">TEMPERATURE, PRESSURE & EMISSION (DATA 2)</th>
                                </tr>
                                <tr>
                                    <th colspan="2">Output</th>
                                    <th rowspan="2">Torque<br>(Nm)<span id="std_torque_lbl" class="std-label">-</span></th>
                                    <th rowspan="2">Load<br>(kgm)<span id="std_load_lbl" class="std-label">-</span></th>
                                    <th colspan="3">Fuel Cons</th>
                                    <th rowspan="2">Sd<br>(BSU)<span id="std_sd_lbl" class="std-label">-</span></th>
                                    <th rowspan="2">Exhaust<br>(°C)<span id="lbl_ex_r1" class="std-label">-</span></th>
                                    <th rowspan="2">Oil Temp<br>(°C)<span id="lbl_oil_r1" class="std-label">-</span></th>
                                    <th rowspan="2">LO<br>(Mpa)<span id="lbl_lo_r1" class="std-label">-</span></th>
                                    <th rowspan="2">Intake (kPa)</th>
                                    <th rowspan="2">Exhaust (kPa)</th>
                                    <th rowspan="2">NOx (ppm)</th>
                                    <th rowspan="2">CO (ppm)</th>
                                    <th rowspan="2">CO2 (%)</th>
                                    <th rowspan="2">O2 (%)</th>
                                    <th rowspan="2">Correct CO<span id="std_correct_co_lbl" class="std-label">-</span></th>
                                </tr>
                                <tr>
                                    <th>Actual (Nm)</th>
                                    <th>Corrected (kW)<span id="std_output_lbl" class="std-label">-</span></th>
                                    <th>cc/30 sec</th>
                                    <th>mm³/st<span id="std_fuel_mm3_lbl" class="std-label">-</span></th> 
                                    <th>g/kWh<span id="std_fuel_gkwh_lbl" class="std-label">-</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>1</td>
                                    <td class="text-start fw-bold" id="lbl_speed1">-</td>
                                    <td><input type="number" name="r1_actual_nm" step="0.01" class="form-control form-control-sm"></td>
                                    <td><input type="number" name="r1_corrected_kw" step="0.01" class="form-control form-control-sm"></td>
                                    <td><input type="number" name="r1_torque_nm" step="0.01" class="form-control form-control-sm"></td>
                                    <td><input type="number" name="r1_load_kgm" step="0.01" class="form-control form-control-sm"></td>
                                    <td><input type="number" name="r1_fuel_cc_30sec" step="0.1" class="form-control form-control-sm"></td>
                                    <td><input type="number" name="r1_fuel_mm3_st" step="0.1" class="form-control form-control-sm"></td>
                                    <td><input type="number" name="r1_fuel_g_kwh" step="0.1" class="form-control form-control-sm"></td>
                                    <td><input type="number" name="r1_sd_bsu" step="0.1" class="form-control form-control-sm"></td>
                                    <td><input type="number" name="r1_temp_exhaust" class="form-control form-control-sm"></td>
                                    <td><input type="number" name="r1_temp_oil" class="form-control form-control-sm"></td>
                                    <td><input type="number" name="r1_lo_press" step="0.001" class="form-control form-control-sm"></td>
                                    <td><input type="number" name="r1_intake_press" step="0.01" class="form-control form-control-sm"></td>
                                    <td><input type="number" name="r1_exhaust_press" step="0.01" class="form-control form-control-sm"></td>
                                    <td><input type="number" name="r1_nox" class="form-control form-control-sm"></td>
                                    <td><input type="number" name="r1_co" class="form-control form-control-sm"></td>
                                    <td><input type="number" name="r1_co2" step="0.01" class="form-control form-control-sm"></td>
                                    <td><input type="number" name="r1_o2" step="0.01" class="form-control form-control-sm"></td>
                                    <td class="bg-secondary"></td>
                                </tr>
                                <tr>
                                    <td>2</td>
                                    <td class="text-start fw-bold" id="lbl_speed2">-</td>
                                    <td><input type="number" name="r2_actual_nm" step="0.01" class="form-control form-control-sm"></td>
                                    <td><input type="number" name="r2_corrected_kw" step="0.01" class="form-control form-control-sm"></td>
                                    <td class="bg-secondary" colspan="6"></td>
                                    <td><input type="number" name="r2_temp_exhaust" class="form-control form-control-sm"></td>
                                    <td class="bg-secondary"></td>
                                    <td><input type="number" name="r2_lo_press" step="0.001" class="form-control form-control-sm"></td>
                                    <td><input type="number" name="r2_intake_press" step="0.01" class="form-control form-control-sm"></td>
                                    <td><input type="number" name="r2_exhaust_press" step="0.01" class="form-control form-control-sm"></td>
                                    <td><input type="number" name="r2_nox" class="form-control form-control-sm"></td>
                                    <td><input type="number" name="r2_co" class="form-control form-control-sm"></td>
                                    <td><input type="number" name="r2_co2" step="0.01" class="form-control form-control-sm"></td>
                                    <td><input type="number" name="r2_o2" step="0.01" class="form-control form-control-sm"></td>
                                    <td><input type="number" name="r2_correct_co" class="form-control form-control-sm"></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm table-bordered m-0 text-center align-middle" style="font-size: 11px; border: 1px solid #808080;">
                            <thead>
                                <tr style="background-color: #f5e6e6; color: var(--maroon); font-weight: bold; text-align: center; vertical-align: middle;">
                                    <th rowspan="2" style="border: 1px solid #000000;">Eng. Speed</th>
                                    <th rowspan="2" style="border: 1px solid #000000;">Torque (Nm)</th>
                                    <th rowspan="2" style="border: 1px solid #000000;">Coolant (°C)</th>
                                    <th colspan="2" style="border: 1px solid #000000;">Current (A)</th> 
                                    <th rowspan="2" style="border: 1px solid #000000;">Torque Box LO</th>
                                    <th rowspan="2" style="border: 1px solid #000000;">Torque Air Intake</th>
                                    <th rowspan="2" style="border: 1px solid #000000;">Torque Bolt CW</th>
                                    <th colspan="2" style="border: 1px solid #000000;">Torque Injection pipe</th> 
                                    <th rowspan="2" style="border: 1px solid #000000;">Torque Nut Joint</th>
                                </tr>
                                <tr style="background-color: #f5e6e6; color: var(--maroon); text-align: center; vertical-align: middle; font-size: 11px;">
                                    <th style="border: 1px solid #000000; font-weight: normal;">at glow plug a</th>
                                    <th style="border: 1px solid #000000; font-weight: normal;">wire battery</th>
                                    <th style="border: 1px solid #000000; font-weight: normal;">at injector</th>
                                    <th style="border: 1px solid #000000; font-weight: normal;">at FOP</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="fw-bold" id="lbl_speed3">-</td>
                                    <td><input type="number" name="r3_torque_nm" step="0.01" class="form-control form-control-sm"></td>
                                    <td><input type="number" name="r3_coolant_temp" class="form-control form-control-sm"></td>
                                    <td><input type="number" name="r3_current_glow" step="0.01" class="form-control form-control-sm"></td>
                                    <td><input type="number" name="r3_current_wire" step="0.01" class="form-control form-control-sm"></td>
                                    <td><input type="number" name="r3_torque_switch_lo" class="form-control form-control-sm" placeholder="Std 10-12"></td>
                                    <td><input type="number" name="r3_torque_pipe_air" class="form-control form-control-sm" placeholder="Std 24-28"></td>
                                    <td><input type="number" name="r3_torque_bolt_cw" class="form-control form-control-sm" placeholder="Std 25-29"></td>
                                    <td><input type="number" name="r3_torque_injection_injector" class="form-control form-control-sm" placeholder="at injector"></td>
                                    <td><input type="number" name="r3_torque_injection_fop" class="form-control form-control-sm" placeholder="at FOP"></td>
                                    <td><input type="number" name="r3_torque_nut_joint" class="form-control form-control-sm" placeholder="Std 27-37"></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="row g-2">
                <div class="col-md-4">
                    <div class="card shadow-sm h-100">
                        <div class="bottom-card-header">Correction Factor & Blow By</div>
                        <div style="display:none">
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-bordered m-0 text-center align-middle" style="border: 1px solid #808080; font-size: 12px; height: 100%;">
                                <tbody>
                                    <tr style="background-color: #f5e6e6; color: var(--maroon);">
                                        <td colspan="2" class="fw-bold py-2" style="width: 50%;">Correction Factor</td>
                                        <td rowspan="2" class="fw-bold py-2" style="width: 50%; vertical-align: middle;">Blow by (std &lt;0.8%)</td>
                                    </tr>
                                    <tr style="background-color: #f5e6e6; color: var(--maroon);">
                                        <td class="fw-bold py-1" style="width: 25%;">α</td>
                                        <td class="fw-bold py-1" style="width: 25%;">β</td>
                                    </tr>
                                    <tr>
                                        <td class="p-2"><input type="text" name="correction_alpha" class="form-control text-center" style="border-radius: 4px;"></td>
                                        <td class="p-2"><input type="text" name="correction_beta" class="form-control text-center" style="border-radius: 4px;"></td>
                                        <td class="p-2"><input type="text" name="blow_by" class="form-control text-center" style="border-radius: 4px;"></td>
                                    </tr>
                                    <tr style="background-color: #f5e6e6; color: var(--maroon);">
                                        <td colspan="2" class="fw-bold py-2" style="line-height: 1.3; font-size: 11px; height: 40px; vertical-align: middle;">
                                            Min eng. Speed when LO switch<br>ON &le;500 rpm
                                        </td>
                                        <td class="fw-bold py-2" style="line-height: 1.3; font-size: 11px; height: 40px; vertical-align: middle;">
                                            Distance of Pulley Crank Shaft to Ring<br>Gear (std 92-93 mm/96-97 mm)
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="2" class="p-2">
                                            <div class="input-group input-group-sm">
                                                <input type="text" name="min_eng_speed_lo" class="form-control text-center" style="border-radius: 4px 0 0 4px;">
                                                <span class="input-group-text justify-content-center text-dark fw-bold" style="width: 55px; background-color: #f5e6e6; border-left: 0; font-size: 11px; color: var(--maroon);">Rpm</span>
                                            </div>
                                        </td>
                                        <td class="p-2">
                                            <div class="input-group input-group-sm">
                                                <input type="text" name="pulley_distance" class="form-control text-center" style="border-radius: 4px 0 0 4px;">
                                                <span class="input-group-text justify-content-center text-dark fw-bold" style="width: 55px; background-color: #f5e6e6; border-left: 0; font-size: 11px; color: var(--maroon);">mm</span>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card shadow-sm h-100">
                        <div class="bottom-card-header">Fuel Injection Timing (FIC)</div>
                        <div style="display:none">
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-bordered m-0 align-middle" style="border: 1px solid #808080; font-size: 12px; height: 100%;">
                                <tbody>
                                    <tr>
                                        <td class="fw-bold table-light" style="width: 35%; padding-left: 10px; color: var(--gray-dk);">FIC standard</td>
                                        <td colspan="2" class="p-1">
                                            <input type="text" name="fic_standard" id="fic_standard" class="form-control form-control-sm fw-bold text-primary text-center bg-transparent border-0" readonly placeholder="-">
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="fw-bold table-light" style="padding-left: 10px; color: var(--gray-dk);">FIC actual</td>
                                        <td class="p-1" colspan="2">
                                            <div class="input-group input-group-sm">
                                                <input type="text" name="fic_actual_left" class="form-control text-center" style="border-radius: 4px 0 0 4px;">
                                                <span class="input-group-text justify-content-center text-dark fw-bold" style="width: 50px; background: transparent; border-left: 0; border-right: 0; color: var(--gray-md);">°/</span>
                                                <input type="text" name="fic_actual_right" class="form-control text-center" style="border-radius: 0 4px 4px 0;">
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="fw-bold table-light" style="padding-left: 10px; color: var(--gray-dk);">FIC before test</td>
                                        <td class="p-1" colspan="2">
                                            <div class="input-group input-group-sm">
                                                <input type="text" name="fic_before_test_left" class="form-control text-center" style="border-radius: 4px 0 0 4px;">
                                                <span class="input-group-text justify-content-center text-dark fw-bold" style="width: 50px; background: transparent; border-left: 0; border-right: 0; color: var(--gray-md);">°/</span>
                                                <input type="text" name="fic_before_test_right" class="form-control text-center" style="border-radius: 0 4px 4px 0;">
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="fw-bold table-light" style="padding-left: 10px; color: var(--gray-dk);">FIC after test</td>
                                        <td class="p-1" colspan="2">
                                            <div class="input-group input-group-sm">
                                                <input type="text" name="fic_after_test_left" class="form-control text-center" style="border-radius: 4px 0 0 4px;">
                                                <span class="input-group-text justify-content-center text-dark fw-bold" style="width: 50px; background: transparent; border-left: 0; border-right: 0; color: var(--gray-md);">°/</span>
                                                <input type="text" name="fic_after_test_right" class="form-control text-center" style="border-radius: 0 4px 4px 0;">
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="fw-bold table-light" style="padding-left: 10px; color: var(--gray-dk);">Belt tension 15-20 mm</td>
                                        <td class="p-1" colspan="2">
                                            <div class="input-group input-group-sm">
                                                <input type="text" name="belt_tension_left" class="form-control text-center" style="border-radius: 4px 0 0 4px;">
                                                <span class="input-group-text justify-content-center text-dark fw-bold" style="width: 50px; background: transparent; border-left: 0; border-right: 0; color: var(--gray-md);">mm</span>
                                                <input type="text" name="belt_tension_right" class="form-control text-center" style="border-radius: 0 4px 4px 0;">
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card shadow-sm h-100 bg-light border-secondary">
                        <div class="card-body d-flex flex-column justify-content-center align-items-center p-3">
                            <!-- Foto Engine 1, 2, 3 -->
                            <?php for($f=1; $f<=3; $f++): ?>
                            <div class="w-100 mb-2">
                                <label class="fw-bold mb-1 d-block" style="font-size:12px; color:#555;">
                                    <i class="fa-solid fa-camera me-1" style="color:#7B1D1D;"></i>Foto Engine <?php echo $f; ?>
                                </label>
                                <input type="file" name="foto_engine_<?php echo $f; ?>" id="foto_engine_<?php echo $f; ?>"
                                       accept="image/*" capture="environment"
                                       class="form-control form-control-sm foto-engine-input" style="font-size:11px; padding:3px 6px;"
                                       data-preview="preview_foto_engine_<?php echo $f; ?>">
                                <div id="preview_foto_engine_<?php echo $f; ?>" style="display:none; margin-top:4px; text-align:center;">
                                    <img src="" style="max-width:100%; max-height:80px; border-radius:6px; border:1px solid #ddd; object-fit:cover;">
                                </div>
                            </div>
                            <?php endfor; ?>
                            <?php if($op_area_tr): ?>
                            <button type="submit" class="btn-submit-tr">
                                <i class="fa-solid fa-paper-plane me-2"></i>SIMPAN DATA TEST RUN
                            </button>
                            <?php else: ?>
                            <button type="button" class="btn btn-secondary w-100 py-3 fw-bold shadow-sm" disabled>
                                <i class="fa-solid fa-lock me-2"></i>BUKAN AREA ANDA
                            </button>
                            <small class="text-muted mt-2 d-block text-center" style="font-size:11px;">
                                Anda adalah Operator <?php echo strtoupper(str_replace('_',' ',$_SESSION['role'])); ?>
                            </small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        </form>
    </div>
</div>



<!-- ---- TAB: FINAL INSPECTION (placeholder) ---- -->
<div id="sec-final-inspection" class="module-section">
    <div class="container-fluid pb-3">
        <form action="simpan_final_inspection.php" method="POST" enctype="multipart/form-data" id="form-fi">

            <!-- HEADER CARD -->
            <div class="card mb-3 shadow-sm">
                <div class="card-header py-0 border-0" style="background:linear-gradient(135deg,#5a1414 0%,#7B1D1D 60%,#a83232 100%); border-radius:12px 12px 0 0;">
                    <div class="d-flex align-items-center gap-2 py-2 px-2">
                        <i class="fa-solid fa-clipboard-list text-white" style="font-size:16px;"></i>
                        <h5 class="card-title m-0 fw-bold text-white" style="letter-spacing:0.8px;">FINAL INSPECTION</h5>
                    </div>
                </div>
                <div class="card-body p-3">
                    <div class="row g-2">
                        <div class="col-md-3">
                            <div class="row mb-1">
                                <label class="col-sm-5 col-form-label col-form-label-sm" style="font-size:12px; font-weight:500; color:#555;" style="font-size:12px; font-weight:500; color:#555;">Inspect Date</label>
                                <div class="col-sm-7">
                                    <input type="text" class="form-control form-control-sm" style="font-size:12px; background:#f7f7f7; color:#666;"
                                           value="<?php echo date('d/m/Y'); ?>" readonly>
                                </div>
                            </div>
                            <div class="row mb-1">
                                <label class="col-sm-5 col-form-label col-form-label-sm" style="font-size:12px; font-weight:500; color:#555;" style="font-size:12px; font-weight:500; color:#555;">Engine Model</label>
                                <div class="col-sm-7">
                                    <select name="engine_model" id="fi_engine_model"
                                            class="form-select form-select-sm fw-bold text-dark border-success shadow-sm" required>
                                        <option value="">- Pilih Model -</option>
                                        <?php
                                        $q_fi_model = mysqli_query($koneksi, "SELECT DISTINCT engine_model FROM master_final_inspection ORDER BY CAST(REPLACE(REPLACE(REPLACE(engine_model,'TF',''),'V',''),'-','') AS UNSIGNED) ASC, engine_model ASC");
                                        while ($fm = mysqli_fetch_array($q_fi_model)) {
                                            echo "<option value='".$fm['engine_model']."'>".$fm['engine_model']."</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                            <div class="row mb-1">
                                <label class="col-sm-5 col-form-label col-form-label-sm" style="font-size:12px; font-weight:500; color:#555;" style="font-size:12px; font-weight:500; color:#555;">Engine No.</label>
                                <div class="col-sm-7">
                                    <input type="text" name="engine_no" class="form-control form-control-sm"
                                           required placeholder="Ketik No. Mesin...">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="row mb-1">
                                <label class="col-sm-5 col-form-label col-form-label-sm" style="font-size:12px; font-weight:500; color:#555;" style="font-size:12px; font-weight:500; color:#555;">Operator</label>
                                <div class="col-sm-7">
                                    <input type="text" class="form-control form-control-sm" style="font-size:12px; background:#f7f7f7; color:#666;"
                                           value="<?php echo htmlspecialchars($_SESSION['nama_lengkap']); ?>" readonly>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="row mb-1">
                                <label class="col-sm-2 col-form-label col-form-label-sm">Noted</label>
                                <div class="col-sm-10">
                                    <textarea name="noted" class="form-control form-control-sm" rows="3"
                                              placeholder="Catatan tambahan (opsional)..."></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CHECKLIST TABLE -->
            <div class="card mb-3 shadow-sm">
                <div class="card-body p-2">
                    <div class="d-flex align-items-center justify-content-between mb-2 px-1">
                        <span class="fw-bold text-success" style="font-size:13px;">
                            <i class="fa-solid fa-list-check me-1"></i>CHECKLIST FINAL INSPECTION
                        </span>
                        <span class="text-muted" style="font-size:11px;" id="fi_item_count">
                            Pilih Engine Model untuk memuat checklist
                        </span>
                    </div>

                    <div id="fi_checklist_container">
                        <!-- Diisi via AJAX setelah model dipilih -->
                        <div class="text-center text-muted py-5" id="fi_empty_msg">
                            <i class="fa-solid fa-magnifying-glass fa-2x mb-2 d-block text-success opacity-50"></i>
                            Pilih Engine Model terlebih dahulu untuk memuat daftar checklist.
                        </div>
                    </div>
                </div>
            </div>

            <!-- SUBMIT -->
            <div class="row g-2">
                <div class="col-md-12">
                    <div class="card shadow-sm bg-light">
                        <div class="card-body d-flex justify-content-end align-items-center p-3 gap-3">
                            <button type="button" class="btn btn-outline-danger btn-sm fw-bold"
                                    onclick="resetFIForm()">
                                <i class="fa-solid fa-rotate-left me-1"></i>Reset Form
                            </button>
                            <?php if($op_area_fi): ?>
                            <button type="submit" class="btn-submit-tr px-4 py-2"
                                    id="btn_fi_submit" disabled style="width:auto; font-size:13px;">
                                <i class="fa-solid fa-paper-plane me-2"></i>SIMPAN FINAL INSPECTION
                            </button>
                            <?php else: ?>
                            <button type="button" class="btn fw-bold px-4 py-2" disabled style="background:#ccc;color:#888;border-radius:8px;">
                                <i class="fa-solid fa-lock me-2"></i>BUKAN AREA ANDA
                            </button>
                            <small class="text-muted" style="font-size:11px;">
                                Anda adalah Operator <?php echo strtoupper(str_replace('_',' ',$_SESSION['role'])); ?>
                            </small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        </form>
    </div>
</div>

<!-- Template tabel checklist (diisi AJAX) -->
<template id="tpl_fi_table">
<div class="table-responsive">
<table class="table table-sm table-bordered m-0 text-center align-middle" style="font-size:12px;">
    <thead>
        <tr style="background:linear-gradient(90deg,#5a1414,#7B1D1D); color:#fff;">
            <th style="width:35px;">#</th>
            <th class="text-start" style="min-width:200px;">Inspection Item</th>
            <th class="text-start" style="min-width:200px;">Parameter / Standard</th>
            <th style="width:100px;">Hasil</th>
            <th style="width:160px;">Foto</th>
        </tr>
    </thead>
    <tbody id="fi_tbody"></tbody>
</table>
</div>
</template>




<!-- ---- TAB: PACKING (placeholder) ---- -->
<div id="sec-packing" class="module-section">
    <div class="container-fluid pb-3">
        <form action="simpan_packing.php" method="POST" enctype="multipart/form-data" id="form-pk">
            <div class="card mb-3 shadow-sm">
                <div class="card-header py-0 border-0" style="background:linear-gradient(135deg,#5a1414 0%,#7B1D1D 60%,#a83232 100%); border-radius:12px 12px 0 0;">
                    <div class="d-flex align-items-center gap-2 py-2 px-2">
                        <i class="fa-solid fa-box text-white" style="font-size:16px;"></i>
                        <h5 class="card-title m-0 fw-bold text-white" style="letter-spacing:0.8px;">PACKING</h5>
                    </div>
                </div>
                <div class="card-body p-3" style="background:linear-gradient(135deg,#fff 0%,#fdf5f5 100%);">
                    <div class="row g-2">
                        <div class="col-md-3">
                            <div class="row mb-1">
                                <label class="col-sm-5 col-form-label col-form-label-sm" style="font-size:12px; font-weight:500; color:#555;">Pack Date</label>
                                <div class="col-sm-7">
                                    <input type="text" class="form-control form-control-sm" style="font-size:12px; background:#f7f7f7; color:#666;" value="<?php echo date('d/m/Y'); ?>" readonly>
                                </div>
                            </div>
                            <div class="row mb-1">
                                <label class="col-sm-5 col-form-label col-form-label-sm" style="font-size:12px; font-weight:500; color:#555;">Engine Model</label>
                                <div class="col-sm-7">
                                    <select name="engine_model" id="pk_engine_model" class="form-select form-select-sm" style="font-size:12px; font-weight:500; border-color:#7B1D1D;" required>
                                        <option value="">- Pilih Model -</option>
                                        <?php
                                        $q_pk_model = mysqli_query($koneksi, "SELECT DISTINCT engine_model FROM master_engine_spec ORDER BY CAST(REPLACE(REPLACE(REPLACE(engine_model,'TF',''),'V',''),'-','') AS UNSIGNED) ASC, engine_model ASC");
                                        while ($pm = mysqli_fetch_array($q_pk_model)) {
                                            echo "<option value='".$pm['engine_model']."'>".$pm['engine_model']."</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                            <div class="row mb-1">
                                <label class="col-sm-5 col-form-label col-form-label-sm" style="font-size:12px; font-weight:500; color:#555;">Engine No.</label>
                                <div class="col-sm-7">
                                    <input type="text" name="engine_no" class="form-control form-control-sm" style="font-size:12px;" required placeholder="Ketik No. Mesin...">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="row mb-1">
                                <label class="col-sm-5 col-form-label col-form-label-sm" style="font-size:12px; font-weight:500; color:#555;">Operator Packing</label>
                                <div class="col-sm-7">
                                    <?php if(strpos($role, 'foreman') !== false): ?>
                                    <input type="text" name="operator_name" class="form-control form-control-sm" style="font-size:12px;" placeholder="Nama operator packing...">
                                    <?php else: ?>
                                    <input type="text" name="operator_name" class="form-control form-control-sm" style="font-size:12px; background:#f7f7f7; color:#666;"
                                           value="<?php echo htmlspecialchars($_SESSION['nama_lengkap']); ?>" readonly>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="row mb-1">
                                <label class="col-sm-5 col-form-label col-form-label-sm" style="font-size:12px; font-weight:500; color:#555;">Dicatat oleh</label>
                                <div class="col-sm-7">
                                    <input type="text" class="form-control form-control-sm" style="font-size:12px; background:#f7f7f7; color:#666;"
                                           value="<?php echo htmlspecialchars($_SESSION['nama_lengkap']); ?>" readonly>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="row mb-1">
                                <label class="col-sm-2 col-form-label col-form-label-sm" style="font-size:12px; font-weight:500; color:#555;">Noted</label>
                                <div class="col-sm-10">
                                    <textarea name="noted" class="form-control form-control-sm" rows="3" style="font-size:12px;" placeholder="Catatan tambahan (opsional)..."></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card mb-3 shadow-sm">
                <div class="card-body p-2">
                    <div class="d-flex align-items-center justify-content-between mb-2 px-1">
                        <span class="fw-bold" style="font-size:13px; color:#7B1D1D;"><i class="fa-solid fa-list-check me-1"></i>PACKING CHECKLIST</span>
                        <span class="text-muted" id="pk_item_count" style="font-size:11px;">Memuat checklist...</span>
                    </div>
                    <div id="pk_checklist_container">
                        <div class="text-center text-muted py-4">
                            <div class="spinner-border" role="status" style="color:#7B1D1D;"></div>
                            <div class="mt-2" style="font-size:12px;">Memuat checklist...</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row g-2">
                <div class="col-md-12">
                    <div class="card shadow-sm bg-light">
                        <div class="card-body d-flex justify-content-end align-items-center p-3 gap-3">
                            <?php if($op_area_pk): ?>
                            <button type="submit" class="btn-submit-tr px-4 py-2" id="btn_pk_submit" style="width:auto; font-size:13px;">
                                <i class="fa-solid fa-paper-plane me-2"></i>SIMPAN PACKING
                            </button>
                            <?php else: ?>
                            <button type="button" class="btn fw-bold px-4 py-2" disabled style="background:#ccc;color:#888;border-radius:8px;">
                                <i class="fa-solid fa-lock me-2"></i>BUKAN AREA ANDA
                            </button>
                            <small class="text-muted" style="font-size:11px;">Anda adalah Operator <?php echo strtoupper(str_replace('_',' ',$_SESSION['role'])); ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>




<?php if($is_approver): ?>
<!-- FOREMAN: APPROVAL DASHBOARD -->
<div id="sec-approval-mode" class="module-section" style="display:none;">
    <div class="container-fluid pb-3">
        <div class="d-flex gap-2 mb-3 flex-wrap align-items-center" style="background:linear-gradient(135deg,#5a1414,#7B1D1D); padding:10px 14px; border-radius:10px;">
            <button class="btn qc-nav-btn active" id="abtn-test-running" onclick="switchApprovalTab('test-running')">
                <i class="fa-solid fa-gauge-high me-1"></i>Test Running
            </button>
            <button class="btn qc-nav-btn" id="abtn-final-inspection" onclick="switchApprovalTab('final-inspection')">
                <i class="fa-solid fa-clipboard-list me-1"></i>Final Inspection
            </button>
            <button class="btn qc-nav-btn" id="abtn-packing" onclick="switchApprovalTab('packing')">
                <i class="fa-solid fa-box me-1"></i>Packing
            </button>

        </div>
        <div id="asec-test-running" class="approval-sub-section">
            <div class="card shadow-sm">
                <div class="card-header py-2 d-flex align-items-center justify-content-between" style="background:linear-gradient(135deg,#5a1414 0%,#7B1D1D 60%,#a83232 100%);">
                    <h5 class="card-title m-0 fw-bold text-white"><i class="fa-solid fa-clipboard-check me-2"></i>APPROVAL – TEST RUNNING</h5>
                    <span class="badge" style="font-size:11px; background:rgba(255,255,255,0.2); color:#fff;">Level: <strong>Foreman</strong></span>
                </div>
                <div class="card-body p-3">
                    <?php renderApprovalTable('result_test_run', 'Test_Running', [['role_key'=>'Foreman','label'=>'Foreman']], $role, $koneksi); ?>
                </div>
            </div>
        </div>
        <div id="asec-final-inspection" class="approval-sub-section" style="display:none;">
            <div class="card shadow-sm">
                <div class="card-header py-2 d-flex align-items-center justify-content-between" style="background:linear-gradient(135deg,#5a1414 0%,#7B1D1D 60%,#a83232 100%);">
                    <h5 class="card-title m-0 fw-bold text-white"><i class="fa-solid fa-magnifying-glass-chart me-2"></i>APPROVAL – FINAL INSPECTION</h5>
                    <span class="badge" style="font-size:11px; background:rgba(255,255,255,0.2); color:#fff;">Level: <strong>Foreman → Supervisor</strong></span>
                </div>
                <div class="card-body p-3">
                    <?php renderApprovalTable('final_inspection_data', 'Final_Inspection', [['role_key'=>'Foreman','label'=>'Foreman'],['role_key'=>'Supervisor','label'=>'Supervisor']], $role, $koneksi); ?>
                </div>
            </div>
        </div>
        <div id="asec-packing" class="approval-sub-section" style="display:none;">
            <div class="card shadow-sm">
                <div class="card-header py-2 d-flex align-items-center justify-content-between" style="background:linear-gradient(135deg,#5a1414 0%,#7B1D1D 60%,#a83232 100%);">
                    <h5 class="card-title m-0 fw-bold text-white"><i class="fa-solid fa-box-archive me-2"></i>APPROVAL – PACKING</h5>
                    <span class="badge" style="font-size:11px; background:rgba(255,255,255,0.2); color:#fff;">Level: <strong>Foreman → Supervisor → Asst. Manager</strong></span>
                </div>
                <div class="card-body p-3">
                    <?php renderApprovalTable('packing_data', 'Packing', [['role_key'=>'Foreman','label'=>'Foreman'],['role_key'=>'Supervisor','label'=>'Supervisor'],['role_key'=>'Asst_Manager','label'=>'Asst. Manager']], $role, $koneksi); ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php elseif($is_approver): ?>
<!-- ===================================================
     APPROVER VIEW — DASHBOARD APPROVAL
=================================================== -->


<!-- ---- TAB: TEST RUNNING APPROVAL (Foreman only) ---- -->
<div id="sec-test-running" class="module-section active-module">
    <div class="container-fluid pb-3">
        <div class="card shadow-sm">
            <div class="card-header py-2 d-flex align-items-center justify-content-between" style="background:linear-gradient(135deg,#5a1414 0%,#7B1D1D 60%,#a83232 100%);">
                <h5 class="card-title m-0 fw-bold text-white"><i class="fa-solid fa-clipboard-check me-2"></i>APPROVAL – TEST RUNNING</h5>
                <span class="badge" style="font-size:11px; background:rgba(255,255,255,0.2); color:#fff;">Level: <strong>Foreman</strong></span>
            </div>
            <div class="card-body p-3">
                <?php renderApprovalTable('result_test_run', 'Test_Running', [['role_key'=>'Foreman','label'=>'Foreman']], $role, $koneksi); ?>
            </div>
        </div>
    </div>
</div>

<!-- ---- TAB: FINAL INSPECTION APPROVAL (Foreman → Supervisor) ---- -->
<div id="sec-final-inspection" class="module-section">
    <div class="container-fluid pb-3">
        <div class="card shadow-sm">
            <div class="card-header py-2 d-flex align-items-center justify-content-between" style="background:linear-gradient(135deg,#5a1414,#7B1D1D);">
                <h5 class="card-title m-0 fw-bold text-white"><i class="fa-solid fa-magnifying-glass-chart me-2"></i>APPROVAL – FINAL INSPECTION</h5>
                <span class="badge" style="font-size:11px; background:rgba(255,255,255,0.2); color:#fff;">Level: <strong>Foreman → Supervisor</strong></span>
            </div>
            <div class="card-body p-3">
                <?php renderApprovalTable('final_inspection_data', 'Final_Inspection', [['role_key'=>'Foreman','label'=>'Foreman'],['role_key'=>'Supervisor','label'=>'Supervisor']], $role, $koneksi); ?>
            </div>
        </div>
    </div>
</div>

<!-- ---- TAB: PACKING APPROVAL (Foreman → Supervisor → Asisten Manager) ---- -->
<div id="sec-packing" class="module-section">
    <div class="container-fluid pb-3">
        <div class="card shadow-sm">
            <div class="card-header py-2 d-flex align-items-center justify-content-between" style="background:linear-gradient(135deg,#3d1010,#5a1414);">
                <h5 class="card-title m-0 fw-bold text-white"><i class="fa-solid fa-box-archive me-2"></i>APPROVAL – PACKING</h5>
                <span class="badge bg-light text-dark" style="font-size:11px;">Level: <strong>Foreman → Supervisor → Asisten Manager</strong></span>
            </div>
            <div class="card-body p-3">
                <?php renderApprovalTable('packing_data', 'Packing', [['role_key'=>'Foreman','label'=>'Foreman'],['role_key'=>'Supervisor','label'=>'Supervisor'],['role_key'=>'Asst_Manager','label'=>'Asst. Manager']], $role, $koneksi); ?>
            </div>
        </div>
    </div>
</div>



<?php else: ?>
<!-- Role tidak dikenali -->
<div class="container-fluid pt-3">
    <div class="alert alert-danger">
        Role <strong><?php echo htmlspecialchars($_SESSION['role']); ?></strong> tidak dikenali. Hubungi administrator.
    </div>
</div>
<?php endif; ?>


<!-- MODAL DETAIL DATA -->
<div class="modal fade" id="modalDetailData" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header py-2" style="background:linear-gradient(135deg,#5a1414,#7B1D1D);">
                <h5 class="modal-title fw-bold text-white">
                    <i class="fa-solid fa-magnifying-glass me-2"></i>Detail Data
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3" id="modalDetailBody">
                <div class="text-center py-4">
                    <div class="spinner-border" style="color:#7B1D1D;"></div>
                    <div class="mt-2 text-muted">Memuat data...</div>
                </div>
            </div>
            <div class="modal-footer" id="modalDetailFooter">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL REJECT -->
<div class="modal fade" id="modalRejectReason" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-triangle-exclamation me-2"></i>Alasan Penolakan</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-2" style="font-size:13px;">Isi alasan penolakan. Catatan ini akan terlihat oleh operator.</p>
                <textarea id="rejectReasonText" class="form-control" style="font-size:12px;" rows="4" placeholder="Contoh: Output melebihi toleransi, perlu pengecekan ulang..."></textarea>
                <div id="rejectReasonError" class="text-danger mt-1" style="font-size:12px; display:none;">
                    <i class="fa-solid fa-circle-exclamation me-1"></i>Alasan tidak boleh kosong.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-danger fw-bold" onclick="submitReject()">
                    <i class="fa-solid fa-xmark me-1"></i>Konfirmasi Reject
                </button>
            </div>
        </div>
    </div>
</div>
<!-- ===================================================
     SCRIPTS
=================================================== -->
<!-- Modal Validasi -->
<div class="modal fade" id="validasiModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background:linear-gradient(135deg,#5a1414,#7B1D1D);">
                <h5 class="modal-title fw-bold text-white">
                    <i class="fa-solid fa-triangle-exclamation me-2"></i>Data Belum Lengkap
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="validasiModalBody" style="font-size:13px;"></div>
            <div class="modal-footer">
                <button type="button" class="btn fw-bold" data-bs-dismiss="modal"
                        style="background:#7B1D1D; color:#fff; border:none;">
                    <i class="fa-solid fa-pen me-1"></i>Lengkapi Data
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// --- Auto-switch tab dari URL hash atau query param ---
$(document).ready(function(){
    // Restore mode approval Foreman setelah reload
    var foremanMode = sessionStorage.getItem('foremanApprovalMode');
    if (foremanMode === '1') {
        sessionStorage.removeItem('foremanApprovalMode');
        switchToApprovalMode();
        var foremanTab = sessionStorage.getItem('foremanApprovalTab');
        if (foremanTab) {
            sessionStorage.removeItem('foremanApprovalTab');
            switchApprovalTab(foremanTab);
        }
    }

    // Restore tab approval biasa setelah reload (approve/reject)
    var savedTab = sessionStorage.getItem('activeApprovalTab');
    if (savedTab && document.getElementById('btn-' + savedTab)) {
        switchModule(savedTab);
        sessionStorage.removeItem('activeApprovalTab');
    }

    // Cek hash URL e.g. #final-inspection
    var hash = window.location.hash.replace('#','');
    if (hash && document.getElementById('btn-' + hash)) {
        switchModule(hash);
    }
    // Cek query param sukses
    var urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('fi_success') === '1') {
        showToast('success', 'Data Final Inspection berhasil disimpan!');
        // Bersihkan URL tanpa reload
        history.replaceState(null, '', window.location.pathname);
    }
    if (urlParams.get('tr_success') === '1') {
        showToast('success', 'Data Test Running berhasil disimpan!');
        history.replaceState(null, '', window.location.pathname);
    }
    if (urlParams.get('tr_error') === 'duplikat') {
        var en = urlParams.get('engine_no') || '';
        showToast('danger', 'Engine No. ' + en + ' sudah ada di database!');
        history.replaceState(null, '', window.location.pathname);
    }
    if (urlParams.get('pk_success') === '1') {
        showToast('success', 'Data Packing berhasil disimpan!');
        history.replaceState(null, '', window.location.pathname);
    }
});

// --- Switch ke mode approval (khusus Foreman yang juga operator packing) ---
function switchToApprovalMode() {
    document.querySelectorAll('.module-section').forEach(s => {
        s.classList.remove('active-module');
        s.style.display = 'none';
    });
    document.querySelectorAll('.qc-nav-btn, .approval-nav-btn').forEach(b => b.classList.remove('active'));
    var el = document.getElementById('sec-approval-mode');
    if (el) { el.classList.add('active-module'); el.style.display = 'block'; }
    var btn = document.getElementById('btn-approval-mode');
    if (btn) btn.classList.add('active');
    // Default aktifkan tab Test Running di dalam approval mode
    switchApprovalTab('test-running');
}

function switchBackToForm() {
    document.querySelectorAll('.module-section').forEach(s => {
        s.classList.remove('active-module');
        s.style.display = 'none';
    });
    document.querySelectorAll('.qc-nav-btn').forEach(b => b.classList.remove('active'));
    var tr = document.getElementById('sec-test-running');
    if (tr) { tr.classList.add('active-module'); tr.style.display = 'block'; }
    var btn = document.getElementById('btn-test-running');
    if (btn) btn.classList.add('active');
    var abtn = document.getElementById('btn-approval-mode');
    if (abtn) abtn.classList.remove('active');
}

function switchApprovalTab(name) {
    document.querySelectorAll('.approval-sub-section').forEach(s => s.style.display = 'none');
    document.querySelectorAll('[id^="abtn-"]').forEach(b => b.classList.remove('active'));
    var sec = document.getElementById('asec-' + name);
    if (sec) sec.style.display = 'block';
    var btn = document.getElementById('abtn-' + name);
    if (btn) btn.classList.add('active');
}

// --- Tab switching (sama untuk operator & approver) ---
function switchModule(name) {
    document.querySelectorAll('.qc-nav-btn, .approval-nav-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.module-section').forEach(s => {
        s.classList.remove('active-module');
        s.style.display = 'none';
    });
    var btn = document.getElementById('btn-' + name);
    if (btn) btn.classList.add('active');
    var sec = document.getElementById('sec-' + name);
    if (sec) { sec.classList.add('active-module'); sec.style.display = 'block'; }
}

<?php if($is_approver || (strpos($role,"foreman") !== false)): ?>
// --- Approve: parameter (id, stage, role) -> POST ke proses_approve.php ---
function doApprove(recordId, stage, role) {
    console.log('doApprove called:', recordId, stage, role);
    if (!confirm('Yakin ingin APPROVE data ini?')) return;
    $.post('proses_approve.php',
        { action:'approve', id:recordId, stage:stage, role:role },
        function(res) {
            if (res.status === 'ok') {
                showToast('success','Data berhasil di-approve!');
                setTimeout(()=>reloadKeepTab(), 1200);
            } else showToast('danger','Gagal: '+(res.message||'error'));
        }, 'json'
    ).fail(function(xhr, status, error) {
        showToast('danger', 'Koneksi gagal: ' + xhr.status + ' ' + xhr.responseText.substring(0,100));
    });
}

// --- Reject modal ---
var _ri=0, _rs='', _rr='', _modal=null;
function openRejectModal(recordId, stage, role) {
    _ri=recordId; _rs=stage; _rr=role;
    document.getElementById('rejectReasonText').value='';
    document.getElementById('rejectReasonError').style.display='none';
    if(!_modal) _modal=new bootstrap.Modal(document.getElementById('modalRejectReason'));
    _modal.show();
}
function submitReject() {
    var reason = document.getElementById('rejectReasonText').value.trim();
    if (!reason) { document.getElementById('rejectReasonError').style.display='block'; return; }
    document.getElementById('rejectReasonError').style.display='none';
    $.post('proses_approve.php',
        { action:'reject', id:_ri, stage:_rs, role:_rr, reason:reason },
        function(res) {
            _modal.hide();
            if (res.status==='ok') {
                showToast('warning','Data berhasil di-reject.');
                setTimeout(()=>reloadKeepTab(), 1200);
            } else showToast('danger','Gagal: '+(res.message||'error'));
        }, 'json'
    ).fail(()=>{ _modal.hide(); showToast('danger','Koneksi gagal.'); });
}

// --- Reload tapi tetap di tab yang sama ---
function reloadKeepTab() {
    // Cek apakah sedang di mode approval foreman (sec-approval-mode aktif)
    var approvalMode = document.getElementById('sec-approval-mode');
    if (approvalMode && approvalMode.style.display === 'block') {
        // Simpan tab approval yang aktif di dalam sec-approval-mode
        var activeAbtn = document.querySelector('[id^="abtn-"].active');
        if (activeAbtn) {
            var tabName = activeAbtn.id.replace('abtn-', '');
            sessionStorage.setItem('foremanApprovalTab', tabName);
        }
        sessionStorage.setItem('foremanApprovalMode', '1');
    } else {
        // Mode approval biasa (supervisor/asst manager)
        var activeBtn = document.querySelector('.approval-nav-btn.active');
        if (activeBtn) {
            sessionStorage.setItem('activeApprovalTab', activeBtn.id.replace('btn-', ''));
        }
    }
    location.reload();
}

// --- Lihat Detail Data sebelum Approve/Reject ---
var _detail_id = 0, _detail_stage = '', _detail_role = '';
var _detailModal = null;

function lihatDetail(recordId, modul, approveId, stage, role) {
    _detail_id    = approveId;
    _detail_stage = stage;
    _detail_role  = role;

    if (!_detailModal) _detailModal = new bootstrap.Modal(document.getElementById('modalDetailData'));

    // Reset modal
    document.getElementById('modalDetailBody').innerHTML = '<div class="text-center py-4"><div class="spinner-border" style="color:#7B1D1D;"></div><div class="mt-2 text-muted">Memuat data...</div></div>';
    document.getElementById('modalDetailFooter').innerHTML = '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>';
    _detailModal.show();

    // Load detail via AJAX
    $.get('get_detail_approval.php', { id: recordId, modul: modul }, function(res) {
        if (res.status !== 'ok') {
            document.getElementById('modalDetailBody').innerHTML = '<div class="alert alert-danger">Gagal memuat data: ' + res.message + '</div>';
            return;
        }

        var row = res.row;
        var checklist = res.checklist;
        var foto = res.foto;
        var html = '';

        // ---- INFO UMUM ----
        html += '<div class="row g-2 mb-3">';
        if (modul === 'test_running') {
            html += infoItem('Engine Model', row.engine_model);
            html += infoItem('Engine No.', row.engine_no);
            html += infoItem('Operator', row.operator_name);
            html += infoItem('Test Date', row.test_date);
            html += infoItem('Bench Test', row.bench_test);
            html += infoItem('Test Name', row.test_name);
            html += infoItem('Lube Oil', row.lube_oil);
            html += infoItem('Fuel', row.fuel_type);
            html += infoItem('Fuel sp. Gravity', row.fuel_sp_gravity);
            html += infoItem('Dry Temp (°C)', row.dry_temp);
            html += infoItem('Wet Temp (°C)', row.wet_temp);
            html += infoItem('Atm. Press', row.atmosphere_press);
            html += infoItem('Limiter Actual', row.limiter_actual);
            html += infoItem('Limiter After Set', row.limiter_after_set);
            html += infoItem('Hi Idle Actual', row.hi_idle_actual);
            html += infoItem('Eng. Speed Max', row.eng_speed_max);
            html += infoItem('Eng. Speed Min', row.eng_speed_min);
        } else if (modul === 'final_inspection') {
            html += infoItem('Engine Model', row.engine_model);
            html += infoItem('Engine No.', row.engine_no);
            html += infoItem('Operator', row.operator_name);
            html += infoItem('Inspect Date', row.inspect_date);
            if (row.noted) html += infoItem('Noted', row.noted);
        } else if (modul === 'packing') {
            html += infoItem('Engine Model', row.engine_model);
            html += infoItem('Engine No.', row.engine_no);
            html += infoItem('Operator Packing', row.operator_name);
            html += infoItem('Dicatat Oleh', row.dicatat_oleh);
            html += infoItem('Pack Date', row.pack_date);
            if (row.noted) html += infoItem('Noted', row.noted);
        }
        html += '</div>';

        // ---- CHECKLIST (muncul setelah header, sebelum performance) ----
        if (checklist && checklist.length > 0) {
            html += '<div class="fw-bold mb-2" style="font-size:12px;color:#7B1D1D;"><i class="fa-solid fa-list-check me-1"></i>Visual Inspection Checklist</div>';
            html += '<div class="table-responsive mb-3"><table class="table table-sm table-bordered mb-0" style="font-size:11px;">';
            html += '<thead><tr style="background:linear-gradient(90deg,#5a1414,#7B1D1D);color:#fff;">';
            html += '<th style="width:30px;">#</th><th>Item</th>';
            if (modul === 'test_running') html += '<th style="width:130px;">Kategori</th>';
            if (modul !== 'test_running') html += '<th>Parameter</th>';
            html += '<th style="width:70px;">Hasil</th></tr></thead><tbody>';
            checklist.forEach(function(c, i) {
                var result = c.jawaban || c.result || '-';
                var resultColor = (result==='OK'||result==='Yes'||result==='Check') ? '#198754' : (result==='NG'||result==='No') ? '#dc3545' : '#666';
                html += '<tr><td class="text-center">' + (i+1) + '</td>';
                html += '<td>' + (c.item_name || c.item || '-') + '</td>';
                if (modul === 'test_running') html += '<td>' + (c.kategori || '-') + '</td>';
                if (modul !== 'test_running') html += '<td style="font-size:10px;color:#666;">' + (c.parameter || '') + '</td>';
                html += '<td class="text-center fw-bold" style="color:' + resultColor + ';">' + result + '</td></tr>';
            });
            html += '</tbody></table></div>';
        }

        // ---- FOTO ENGINE (setelah checklist) ----
        if (foto && foto.length > 0) {
            html += '<div class="mb-3"><div class="fw-bold mb-2" style="font-size:12px;color:#7B1D1D;"><i class="fa-solid fa-camera me-1"></i>Foto Engine</div>';
            html += '<div class="d-flex gap-2 flex-wrap">';
            foto.forEach(function(src, i) {
                html += '<div class="text-center"><img src="' + src + '" style="max-width:150px;max-height:120px;border-radius:6px;border:1px solid #ddd;object-fit:cover;"><div style="font-size:10px;color:#aaa;margin-top:2px;">Foto ' + (i+1) + '</div></div>';
            });
            html += '</div></div>';
        }

        // ---- PERFORMANCE DATA (Test Running only) ----
        if (modul === 'test_running') {
            html += '<div class="fw-bold my-2" style="font-size:12px;color:#7B1D1D;"><i class="fa-solid fa-chart-line me-1"></i>Data Performance (Data 1 & Data 2)</div>';
            html += '<div class="table-responsive"><table class="table table-sm table-bordered mb-0" style="font-size:10px;">';
            html += '<thead>';
            html += '<tr style="background:#5a1414;color:#fff;">';
            html += '<th rowspan="2">No</th><th rowspan="2">Eng.Speed</th>';
            html += '<th colspan="8" style="background:#7B1D1D;">OUTPUT, TORQUE & FUEL (DATA 1)</th>';
            html += '<th colspan="10" style="background:#1a5c3a;">TEMPERATURE, PRESSURE & EMISSION (DATA 2)</th>';
            html += '</tr>';
            html += '<tr style="background:#5a1414;color:#fff;">';
            html += '<th>Actual Nm</th><th>Corrected kW</th><th>Torque Nm</th><th>Load kgm</th><th>cc/30sec</th><th>mm³/st</th><th>g/kWh</th><th>Sd BSU</th>';
            html += '<th>Exhaust°C</th><th>Oil°C</th><th>LO Mpa</th><th>Intake kPa</th><th>Exhaust kPa</th><th>NOx</th><th>CO</th><th>CO2%</th><th>O2%</th><th>Correct CO</th>';
            html += '</tr></thead><tbody>';
            html += '<tr><td>1</td><td>' + (row.r1_eng_speed||row.eng_speed_max||'-') + '</td>';
            html += '<td>' + (row.r1_actual_nm||'-') + '</td><td>' + (row.r1_corrected_kw||'-') + '</td>';
            html += '<td>' + (row.r1_torque_nm||'-') + '</td><td>' + (row.r1_load_kgm||'-') + '</td>';
            html += '<td>' + (row.r1_fuel_cc_30sec||'-') + '</td><td>' + (row.r1_fuel_mm3_st||'-') + '</td>';
            html += '<td>' + (row.r1_fuel_g_kwh||'-') + '</td><td>' + (row.r1_sd_bsu||'-') + '</td>';
            html += '<td>' + (row.r1_temp_exhaust||'-') + '</td><td>' + (row.r1_temp_oil||'-') + '</td>';
            html += '<td>' + (row.r1_lo_press||'-') + '</td><td>' + (row.r1_intake_press||'-') + '</td>';
            html += '<td>' + (row.r1_exhaust_press||'-') + '</td><td>' + (row.r1_nox||'-') + '</td>';
            html += '<td>' + (row.r1_co||'-') + '</td><td>' + (row.r1_co2||'-') + '</td>';
            html += '<td>' + (row.r1_o2||'-') + '</td><td>-</td></tr>';
            html += '<tr><td>2</td><td>' + (row.eng_speed_min||'-') + '</td>';
            html += '<td>' + (row.r2_actual_nm||'-') + '</td><td>' + (row.r2_corrected_kw||'-') + '</td>';
            html += '<td colspan="6" style="background:#eee;"></td>';
            html += '<td>' + (row.r2_temp_exhaust||'-') + '</td><td>-</td>';
            html += '<td>' + (row.r2_lo_press||'-') + '</td><td>' + (row.r2_intake_press||'-') + '</td>';
            html += '<td>' + (row.r2_exhaust_press||'-') + '</td><td>' + (row.r2_nox||'-') + '</td>';
            html += '<td>' + (row.r2_co||'-') + '</td><td>' + (row.r2_co2||'-') + '</td>';
            html += '<td>' + (row.r2_o2||'-') + '</td><td>' + (row.r2_correct_co||'-') + '</td></tr>';
            html += '</tbody></table></div>';

            // Additional Data
            html += '<div class="fw-bold my-2" style="font-size:12px;color:#7B1D1D;">Additional Data</div>';
            html += '<div class="table-responsive"><table class="table table-sm table-bordered mb-0" style="font-size:10px;">';
            html += '<thead><tr style="background:#5a1414;color:#fff;"><th>Eng.Speed</th><th>Torque Nm</th><th>Coolant°C</th><th>Curr.Glow</th><th>Curr.Wire</th><th>Box LO</th><th>Air Intake</th><th>Bolt CW</th><th>Inj.Injector</th><th>Inj.FOP</th><th>Nut Joint</th></tr></thead>';
            html += '<tbody><tr>';
            html += '<td>' + (row.eng_speed_max||'-') + '</td><td>' + (row.r3_torque_nm||'-') + '</td>';
            html += '<td>' + (row.r3_coolant_temp||'-') + '</td><td>' + (row.r3_current_glow||'-') + '</td>';
            html += '<td>' + (row.r3_current_wire||'-') + '</td><td>' + (row.r3_torque_switch_lo||'-') + '</td>';
            html += '<td>' + (row.r3_torque_pipe_air||'-') + '</td><td>' + (row.r3_torque_bolt_cw||'-') + '</td>';
            html += '<td>' + (row.r3_torque_injection_injector||'-') + '</td><td>' + (row.r3_torque_injection_fop||'-') + '</td>';
            html += '<td>' + (row.r3_torque_nut_joint||'-') + '</td>';
            html += '</tr></tbody></table></div>';

            // Correction & FIC
            html += '<div class="row g-2 mt-2">';
            html += infoItem('FIC Standard', row.fic_standard);
            html += infoItem('FIC Actual', (row.fic_actual_left||'-') + ' / ' + (row.fic_actual_right||'-'));
            html += infoItem('FIC Before Test', (row.fic_before_test_left||'-') + ' / ' + (row.fic_before_test_right||'-'));
            html += infoItem('FIC After Test', (row.fic_after_test_left||'-') + ' / ' + (row.fic_after_test_right||'-'));
            html += infoItem('Belt Tension', (row.belt_tension_left||'-') + ' / ' + (row.belt_tension_right||'-') + ' mm');
            html += infoItem('Correction α', row.correction_alpha);
            html += infoItem('Correction β', row.correction_beta);
            html += infoItem('Blow By', row.blow_by);
            html += infoItem('Min Eng.Speed LO', (row.min_eng_speed_lo||'-') + ' rpm');
            html += infoItem('Pulley Distance', (row.pulley_distance||'-') + ' mm');
            html += '</div>';
        }

        document.getElementById('modalDetailBody').innerHTML = html;

        // Footer dengan tombol Approve/Reject
        var footer = '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>';
        footer += '<button class="btn btn-success fw-bold ms-2" onclick="_detailModal.hide(); doApprove(_detail_id, _detail_stage, _detail_role);">';
        footer += '<i class="fa-solid fa-check me-1"></i>Approve</button>';
        footer += '<button class="btn btn-danger fw-bold ms-2" onclick="_detailModal.hide(); openRejectModal(_detail_id, _detail_stage, _detail_role);">';
        footer += '<i class="fa-solid fa-xmark me-1"></i>Reject</button>';
        document.getElementById('modalDetailFooter').innerHTML = footer;

    }, 'json').fail(function() {
        document.getElementById('modalDetailBody').innerHTML = '<div class="alert alert-danger">Gagal memuat data.</div>';
    });
}

function infoItem(label, value) {
    return '<div class="col-md-3 col-6"><div style="font-size:10px;color:#7B1D1D;font-weight:600;">' + label + '</div><div style="font-size:12px;color:#333;">' + (value||'-') + '</div></div>';
}

// --- Toast ---
function showToast(type, msg) {
    var bg = type==='success'?'#198754':type==='warning'?'#ffc107':'#dc3545';
    var tc = type==='warning'?'#212529':'#fff';
    var d  = document.createElement('div');
    d.style.cssText='position:fixed;top:20px;right:20px;z-index:9999;padding:12px 20px;border-radius:6px;font-weight:600;font-size:13px;box-shadow:0 4px 12px rgba(0,0,0,.2);';
    d.style.backgroundColor=bg; d.style.color=tc;
    d.innerHTML='<i class="fa-solid fa-'+(type==='success'?'circle-check':type==='warning'?'triangle-exclamation':'circle-xmark')+' me-2"></i>'+msg;
    document.body.appendChild(d);
    setTimeout(()=>{ d.style.opacity='0'; setTimeout(()=>d.remove(),300); },2500);
}
<?php endif; ?>

<?php if($is_operator): ?>
// --- AJAX load spec engine model ---
$(document).ready(function(){
    $('#engine_model').change(function(){
        var model = $(this).val();
        if(model){
            $.ajax({
                url:'ambil_master_spec.php', type:'POST', data:{engine_model:model}, dataType:'json',
                success:function(r){
                    $('#cont_power').val(r.cont_power||'');
                    $('#max_power').val(r.max_power||'');
                    $('#lbl_hi_idle').val(r.hi_idle||'');
                    $('#cont_power_val').val(r.cont_power||'');
                    $('#max_power_val').val(r.max_power||'');
                    $('#hi_idle_std_val').val(r.hi_idle||'');
                    $('#std_output_lbl').text(r.output||'-');
                    $('#std_torque_lbl').text(r.torque||'-');
                    $('#std_load_lbl').text(r.load||'-');
                    $('#std_fuel_mm3_lbl').text(r.fuel_mm3||'-');
                    $('#std_fuel_gkwh_lbl').text(r.fuel_gkwh||'-');
                    $('#std_sd_lbl').text(r.sd_bsu||'-');
                    $('#lbl_ex_r1').text(r.exhaust||'-');
                    $('#lbl_oil_r1').text(r.oil_temp||'-');
                    $('#lbl_lo_r1').text(r.lo||'-');
                    $('#std_correct_co_lbl').text(r.correct_co||'-');
                    $('#fic_standard').val(r.fic||'');
                    $('#lbl_speed1').text(r.speed1||'-');
                    $('#lbl_speed2').text(r.speed2||'-');
                    $('#lbl_speed3').text(r.speed3||'-');
                },
                error:function(){ console.log('Gagal memuat spesifikasi.'); }
            });
        } else {
            $('#cont_power,#max_power,#lbl_hi_idle,#fic_standard').val('');
            $('#std_output_lbl,#std_torque_lbl,#std_load_lbl,#std_fuel_mm3_lbl,#std_fuel_gkwh_lbl,#std_sd_lbl,#lbl_ex_r1,#lbl_oil_r1,#lbl_lo_r1,#std_correct_co_lbl,#lbl_speed1,#lbl_speed2,#lbl_speed3').text('-');
        }
    });
});
<?php endif; ?>

<?php if($is_operator): ?>
// -------------------------------------------------------
// AJAX: Load checklist Final Inspection sesuai model
// -------------------------------------------------------
$('#fi_engine_model').change(function(){
    var model = $(this).val();
    var container = $('#fi_checklist_container');
    var emptyMsg  = $('#fi_empty_msg');
    var submitBtn = $('#btn_fi_submit');
    var counter   = $('#fi_item_count');

    if (!model) {
        container.html('<div class="text-center text-muted py-5" id="fi_empty_msg"><i class="fa-solid fa-magnifying-glass fa-2x mb-2 d-block text-success opacity-50"></i>Pilih Engine Model terlebih dahulu.</div>');
        submitBtn.prop('disabled', true);
        counter.text('Pilih Engine Model untuk memuat checklist');
        return;
    }

    container.html('<div class="text-center py-5"><div class="spinner-border" role="status" style="color:#7B1D1D;"></div><div class="mt-2 text-muted" style="font-size:12px;">Memuat checklist...</div></div>');
    submitBtn.prop('disabled', true);

    $.ajax({
        url: 'ambil_checklist_fi.php',
        type: 'POST',
        data: { engine_model: model },
        dataType: 'json',
        success: function(items) {
            if (!items || items.length === 0) {
                container.html('<div class="alert alert-warning m-2">Tidak ada checklist untuk model ini.</div>');
                counter.text('0 item');
                return;
            }

            // Clone template tabel
            var tpl   = document.getElementById('tpl_fi_table');
            var clone = tpl.content.cloneNode(true);
            var tbody = clone.querySelector('#fi_tbody');

            items.forEach(function(item, i) {
                var param = item.parameter || '';
                var row = document.createElement('tr');
                row.innerHTML =
                    '<td class="text-center fw-bold text-muted">' + (i+1) + '</td>' +
                    '<td class="text-start fw-semibold">' +
                        '<input type="hidden" name="item_name[]" value="' + escHtml(item.item_name) + '">' +
                        '<input type="hidden" name="parameter[]" value="' + escHtml(param) + '">' +
                        escHtml(item.item_name) +
                    '</td>' +
                    '<td class="text-start text-muted" style="font-size:11px; white-space:pre-line;">' + escHtml(param) + '</td>' +
                    '<td>' +
                        '<select name="result[]" class="form-select form-select-sm text-center fw-bold fi-result-sel" style="min-width:70px;">' +
                            '<option value="OK" style="color:green;">OK</option>' +
                            '<option value="NG" style="color:red;">NG</option>' +
                        '</select>' +
                    '</td>' +
                    '<td>' +
                        '<input type="file" name="foto[' + i + ']" accept="image/*" capture="environment" ' +
                        'class="form-control form-control-sm fi-foto" style="font-size:10px; padding:2px 4px;">' +
                        '<div class="fi-preview mt-1" style="display:none;">' +
                            '<img src="" style="max-width:80px; max-height:60px; border-radius:4px; border:1px solid #dee2e6;">' +
                        '</div>' +
                    '</td>';
                tbody.appendChild(row);
            });

            container.empty().append(clone);
            counter.text(items.length + ' item checklist');
            submitBtn.prop('disabled', false);

            // Color coding hasil OK/NG
            $(document).on('change', '.fi-result-sel', function(){
                $(this).css('color', $(this).val() === 'NG' ? '#dc3545' : '#198754');
            });

            // Preview foto
            $(document).on('change', '.fi-foto', function(){
                var file = this.files[0];
                var preview = $(this).siblings('.fi-preview');
                if (file) {
                    var reader = new FileReader();
                    reader.onload = function(e) {
                        preview.find('img').attr('src', e.target.result);
                        preview.show();
                    };
                    reader.readAsDataURL(file);
                } else {
                    preview.hide();
                }
            });
        },
        error: function() {
            container.html('<div class="alert alert-danger m-2">Gagal memuat checklist.</div>');
        }
    });
});

function escHtml(str) {
    return String(str)
        .replace(/&/g,'&amp;')
        .replace(/</g,'&lt;')
        .replace(/>/g,'&gt;')
        .replace(/"/g,'&quot;');
}

// -------------------------------------------------------
// VALIDASI FORM TEST RUNNING - semua field wajib
// -------------------------------------------------------
function validateTRForm(form) {
    var errors = [];

    // Field teks/number wajib
    var requiredFields = [
        { name: 'engine_model',      label: 'Engine Model' },
        { name: 'engine_no',         label: 'Engine No.' },
        { name: 'fuel_sp_gravity',   label: 'Fuel sp. Gravity' },
        { name: 'dry_temp',          label: 'Dry Temp' },
        { name: 'wet_temp',          label: 'Wet Temp' },
        { name: 'atmosphere_press',  label: 'Atmosphere Press' },
        { name: 'limiter_actual',    label: 'Limiter Actual' },
        { name: 'limiter_after_set', label: 'Limiter After Set' },
        { name: 'hi_idle_actual',    label: 'Hi Idle Actual' },
        { name: 'eng_speed_max',     label: 'Eng. Speed Max' },
        { name: 'eng_speed_min',     label: 'Eng. Speed Min' },
        // Data 1
        { name: 'r1_actual_nm',      label: 'Row 1 - Actual (Nm)' },
        { name: 'r1_corrected_kw',   label: 'Row 1 - Corrected (kW)' },
        { name: 'r1_torque_nm',      label: 'Row 1 - Torque (Nm)' },
        { name: 'r1_load_kgm',       label: 'Row 1 - Load (kgm)' },
        { name: 'r1_fuel_cc_30sec',  label: 'Row 1 - Fuel cc/30sec' },
        { name: 'r1_fuel_mm3_st',    label: 'Row 1 - Fuel mm³/st' },
        { name: 'r1_fuel_g_kwh',     label: 'Row 1 - Fuel g/kWh' },
        { name: 'r1_sd_bsu',         label: 'Row 1 - Sd (BSU)' },
        // Data 2
        { name: 'r1_temp_exhaust',   label: 'Row 1 - Exhaust Temp' },
        { name: 'r1_temp_oil',       label: 'Row 1 - Oil Temp' },
        { name: 'r1_lo_press',       label: 'Row 1 - LO Press' },
        { name: 'r1_intake_press',   label: 'Row 1 - Intake Press' },
        { name: 'r1_exhaust_press',  label: 'Row 1 - Exhaust Press' },
        { name: 'r1_nox',            label: 'Row 1 - NOx' },
        { name: 'r1_co',             label: 'Row 1 - CO' },
        { name: 'r1_co2',            label: 'Row 1 - CO2' },
        { name: 'r1_o2',             label: 'Row 1 - O2' },
        // Row 2
        { name: 'r2_actual_nm',      label: 'Row 2 - Actual (Nm)' },
        { name: 'r2_corrected_kw',   label: 'Row 2 - Corrected (kW)' },
        { name: 'r2_temp_exhaust',   label: 'Row 2 - Exhaust Temp' },
        { name: 'r2_lo_press',       label: 'Row 2 - LO Press' },
        { name: 'r2_intake_press',   label: 'Row 2 - Intake Press' },
        { name: 'r2_exhaust_press',  label: 'Row 2 - Exhaust Press' },
        { name: 'r2_nox',            label: 'Row 2 - NOx' },
        { name: 'r2_co',             label: 'Row 2 - CO' },
        { name: 'r2_co2',            label: 'Row 2 - CO2' },
        { name: 'r2_o2',             label: 'Row 2 - O2' },
        { name: 'r2_correct_co',     label: 'Row 2 - Correct CO' },
        // Row 3
        { name: 'r3_torque_nm',      label: 'Row 3 - Torque (Nm)' },
        { name: 'r3_coolant_temp',   label: 'Row 3 - Coolant Temp' },
        { name: 'r3_current_glow',   label: 'Row 3 - Current Glow' },
        { name: 'r3_current_wire',   label: 'Row 3 - Current Wire' },
        { name: 'r3_torque_switch_lo',          label: 'Torque Box LO' },
        { name: 'r3_torque_pipe_air',           label: 'Torque Air Intake' },
        { name: 'r3_torque_bolt_cw',            label: 'Torque Bolt CW' },
        { name: 'r3_torque_injection_injector', label: 'Torque Injection at Injector' },
        { name: 'r3_torque_injection_fop',      label: 'Torque Injection at FOP' },
        { name: 'r3_torque_nut_joint',          label: 'Torque Nut Joint' },
        // Bottom
        { name: 'correction_alpha',  label: 'Correction Factor α' },
        { name: 'correction_beta',   label: 'Correction Factor β' },
        { name: 'blow_by',           label: 'Blow By' },
        { name: 'min_eng_speed_lo',  label: 'Min Eng. Speed LO' },
        { name: 'pulley_distance',   label: 'Pulley Distance' },
        // FIC
        { name: 'fic_actual_left',      label: 'FIC Actual (kiri)' },
        { name: 'fic_actual_right',     label: 'FIC Actual (kanan)' },
        { name: 'fic_before_test_left', label: 'FIC Before Test (kiri)' },
        { name: 'fic_before_test_right',label: 'FIC Before Test (kanan)' },
        { name: 'fic_after_test_left',  label: 'FIC After Test (kiri)' },
        { name: 'fic_after_test_right', label: 'FIC After Test (kanan)' },
        { name: 'belt_tension_left',    label: 'Belt Tension (kiri)' },
        { name: 'belt_tension_right',   label: 'Belt Tension (kanan)' },
    ];

    requiredFields.forEach(function(f) {
        var el = form.elements[f.name];
        if (el && el.value.trim() === '') {
            errors.push(f.label);
            $(el).css('border-color', '#dc3545');
        } else if (el) {
            $(el).css('border-color', '');
        }
    });

    // Validasi foto engine 1, 2, 3
    for (var fn = 1; fn <= 3; fn++) {
        var fotoEl = form.elements['foto_engine_' + fn];
        if (!fotoEl || !fotoEl.files || fotoEl.files.length === 0) {
            errors.push('Foto Engine ' + fn);
            $('#foto_engine_' + fn).css('border-color', '#dc3545');
        } else {
            $('#foto_engine_' + fn).css('border-color', '');
        }
    }

    if (errors.length > 0) {
        var listHtml = errors.slice(0, 10).map(e => '<li>' + e + '</li>').join('');
        if (errors.length > 10) listHtml += '<li>... dan ' + (errors.length - 10) + ' field lainnya</li>';
        document.getElementById('validasiModalBody').innerHTML =
            '<p class="mb-2">Harap lengkapi field berikut sebelum submit:</p><ul class="mb-0" style="padding-left:18px;">' + listHtml + '</ul>';
        var vm = new bootstrap.Modal(document.getElementById('validasiModal'));
        vm.show();
        var firstErrName = requiredFields.find(function(f) {
            var el = form.elements[f.name];
            return el && el.value.trim() === '';
        });
        if (firstErrName) {
            var el = form.elements[firstErrName.name];
            if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        return false;
    }
    return true;
}

// Reset border merah saat field diisi
$(document).on('input change', '#sec-test-running input, #sec-test-running select', function(){
    if ($(this).val() !== '') $(this).css('border-color', '');
});

// Preview foto engine 1, 2, 3 (handled by .foto-engine-input listener below)

function resetFIForm() {
    if (!confirm('Reset form Final Inspection?')) return;
    $('#fi_engine_model').val('').trigger('change');
    $('input[name="engine_no"]', '#form-fi').val('');
    $('textarea[name="noted"]', '#form-fi').val('');
}

// -------------------------------------------------------
// Load checklist Packing otomatis saat tab packing dibuka
// -------------------------------------------------------
function loadPackingChecklist() {
    $.ajax({
        url: 'ambil_checklist_packing.php', type: 'GET', dataType: 'json',
        success: function(items) {
            if (!items || items.length === 0) {
                $('#pk_checklist_container').html('<div class="alert alert-warning m-2">Tidak ada checklist.</div>');
                return;
            }
            var html = '<div class="table-responsive"><table class="table table-sm table-bordered m-0 text-center align-middle" style="font-size:12px;">';
            html += '<thead><tr style="background:linear-gradient(90deg,#5a1414,#7B1D1D); color:#fff;">';
            html += '<th style="width:35px;">#</th><th class="text-start" style="min-width:200px;">Item</th>';
            html += '<th class="text-start" style="min-width:200px;">Parameter / Standard</th>';
            html += '<th style="width:100px;">Hasil</th><th style="width:160px;">Foto</th>';
            html += '</tr></thead><tbody>';
            items.forEach(function(item, i) {
                html += '<tr><td class="text-center fw-bold text-muted">' + (i+1) + '</td>';
                html += '<td class="text-start fw-semibold"><input type="hidden" name="item_name[]" value="' + escHtml(item.item_name) + '">';
                html += '<input type="hidden" name="parameter[]" value="' + escHtml(item.parameter || '') + '">' + escHtml(item.item_name) + '</td>';
                html += '<td class="text-start text-muted" style="font-size:11px;">' + escHtml(item.parameter || '') + '</td>';
                html += '<td><select name="result[]" class="form-select form-select-sm text-center fw-bold pk-result-sel" style="min-width:70px;">';
                html += '<option value="Check">Check</option><option value="NG">NG</option><option value="-">-</option></select></td>';
                html += '<td><input type="file" name="foto[' + i + ']" accept="image/*" capture="environment" class="form-control form-control-sm pk-foto" style="font-size:10px; padding:2px 4px;">';
                html += '<div class="pk-preview mt-1" style="display:none;"><img src="" style="max-width:80px; max-height:60px; border-radius:4px; border:1px solid #dee2e6;"></div></td></tr>';
            });
            html += '</tbody></table></div>';
            $('#pk_checklist_container').html(html);
            $('#pk_item_count').text(items.length + ' item checklist');
            $(document).on('change', '.pk-result-sel', function(){ $(this).css('color', $(this).val() === 'NG' ? '#dc3545' : '#198754'); });
            $(document).on('change', '.pk-foto', function(){
                var file = this.files[0]; var preview = $(this).siblings('.pk-preview');
                if (file) { var reader = new FileReader(); reader.onload = function(e) { preview.find('img').attr('src', e.target.result); preview.show(); }; reader.readAsDataURL(file); } else { preview.hide(); }
            });
        },
        error: function() { $('#pk_checklist_container').html('<div class="alert alert-danger m-2">Gagal memuat checklist.</div>'); }
    });
}

$(document).on('click', '#btn-packing', function(){ setTimeout(loadPackingChecklist, 100); });
if (window.location.hash === '#packing') { $(document).ready(function(){ loadPackingChecklist(); }); }

// Preview foto engine 1, 2, 3
$(document).on('change', '.foto-engine-input', function(){
    var file = this.files[0]; var prevId = '#' + $(this).data('preview');
    if (file) { var reader = new FileReader(); reader.onload = function(e) { $(prevId).find('img').attr('src', e.target.result); $(prevId).show(); }; reader.readAsDataURL(file); } else { $(prevId).hide(); }
});

<?php endif; ?>
</script>
</body>
</html>