<?php
session_start();
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login") {
    header("location:login.php"); exit();
}
include 'koneksi.php';

$role = strtolower(trim($_SESSION['role']));
$is_supervisor_up = strpos($role, 'supervisor') !== false || strpos($role, 'manager') !== false;
if (!$is_supervisor_up) {
    die('<div style="font-family:Arial;padding:40px;text-align:center;color:#7B1D1D;">Akses ditolak.</div>');
}

function e($s) { return htmlspecialchars($s ?? '', ENT_QUOTES); }

// Kolom DECIMAL di MySQL selalu balik dengan nol di belakang koma sesuai scale-nya
// (mis. decimal(4,2) -> "1.00"). Fungsi ini membersihkannya jadi angka bersih tanpa
// mengubah field yang isinya teks campuran (mis. "4.8 kW/2600 rpm", "16.5°±0.7").
function cleanNum($v) {
    if ($v === null || $v === '') return $v;
    $s = trim((string) $v);
    if (preg_match('/^-?\d+(\.\d+)?$/', $s)) {
        return rtrim(rtrim(sprintf('%.10F', $s), '0'), '.') ?: '0';
    }
    return $s;
}
function val($row, $key) { return e(cleanNum($row[$key] ?? '-')); }

function getCss() {
    return '
    body { font-family: Arial, sans-serif; font-size: 9pt; }
    .header-wrap { display:table; width:100%; border-bottom:2px solid #7B1D1D; margin-bottom:8px; padding-bottom:6px; }
    .header-logo { display:table-cell; width:80px; vertical-align:middle; }
    .header-logo img { width:70px; height:auto; }
    .header-info { display:table-cell; vertical-align:middle; padding-left:12px; }
    .header-info h1 { font-size:14pt; font-weight:bold; color:#7B1D1D; margin:0; }
    .header-info p { font-size:8pt; color:#555; margin:2px 0 0; }
    .header-meta { display:table-cell; text-align:right; vertical-align:top; font-size:7pt; color:#777; width:120px; }
    .section-title { text-align:center; font-size:12pt; font-weight:bold; color:#7B1D1D; margin:8px 0; letter-spacing:1px; border-top:1px solid #eee; border-bottom:1px solid #eee; padding:4px 0; }
    .info-table { width:100%; border-collapse:collapse; margin-bottom:8px; font-size:8pt; }
    .info-table td { padding:3px 6px; border:1px solid #ddd; }
    .info-label { font-weight:bold; color:#7B1D1D; background:#fdf5f5; width:120px; }
    table.data-tbl { width:100%; border-collapse:collapse; font-size:7pt; margin-bottom:6px; }
    table.data-tbl th { background:#7B1D1D; color:#fff; padding:3px 4px; text-align:center; border:1px solid #5a1414; }
    table.data-tbl th.th2 { background:#7B1D1D; }
    table.data-tbl td { padding:2px 4px; border:1px solid #ccc; text-align:center; }
    table.data-tbl tr:nth-child(even) td { background:#fdf5f5; }
    .approval-box { display:table; width:100%; margin-top:10px; border-top:1px solid #ddd; padding-top:6px; }
    .approval-cell { display:table-cell; text-align:center; font-size:8pt; }
    .approval-name { font-weight:bold; color:#7B1D1D; font-size:9pt; margin-top:4px; }
    .approval-date { font-size:7pt; color:#666; }
    .badge-approved { color:#198754; font-weight:bold; }
    ';
}

function getApproval($koneksi, $record_id, $stage) {
    $stage_esc = mysqli_real_escape_string($koneksi, $stage);
    $q = mysqli_query($koneksi, "SELECT * FROM approvals WHERE test_run_id = $record_id AND stage = '$stage_esc' AND status = 'approved'");
    $result = [];
    if ($q) while ($r = mysqli_fetch_assoc($q)) $result[$r['role']] = $r;
    return $result;
}

function approvalBox($approvals, $levels) {
    $html = '<div class="approval-box">';
    foreach ($levels as $lvl) {
        $apv = $approvals[$lvl['role_key']] ?? null;
        $html .= '<div class="approval-cell">';
        $html .= '<div style="font-size:7pt;color:#666;">' . $lvl['label'] . '</div>';
        if ($apv) {
            $html .= '<div class="approval-name">' . e($apv['approved_by']) . '</div>';
            $html .= '<div class="badge-approved">&#10003; APPROVED</div>';
            $html .= '<div class="approval-date">' . date('d/m/Y H:i', strtotime($apv['created_at'])) . '</div>';
        } else {
            $html .= '<div style="color:#aaa;font-size:8pt;">Not Approved Yet</div>';
        }
        $html .= '</div>';
    }
    $html .= '</div>';
    return $html;
}

function getLogoB64() {
    $path = __DIR__ . '/assets/logo.png';
    if (file_exists($path)) {
        $type = mime_content_type($path);
        return 'data:' . $type . ';base64,' . base64_encode(file_get_contents($path));
    }
    return '';
}

function generateTRHtml($row, $checklist, $logo_b64, $koneksi) {
    $approvals = getApproval($koneksi, $row['id'], 'Test_Running');
    $html = '<div class="header-wrap">';
    $html .= $logo_b64 ? ('<div class="header-logo"><img src="' . $logo_b64 . '"></div>') : '';
    $html .= '<div class="header-info"><h1>PT. YANMAR DIESEL INDONESIA</h1><p>Quality Control Department - Engine Manufacturing</p></div>';
    $html .= '<div class="header-meta">Doc No: QCTR-001<br>Rev: 00<br>' . date('d/m/Y') . '</div></div>';
    $html .= '<div class="section-title">TEST RUNNING REPORT</div>';
    // Ambil spec dari master
    $em = mysqli_real_escape_string($koneksi, $row['engine_model'] ?? '');
    $spec = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT * FROM master_engine_spec WHERE engine_model='$em' LIMIT 1"));
    $cont_power = $row['cont_power'] ?? ($spec['cont_power'] ?? '-');
    $max_power  = $row['max_power']  ?? ($spec['max_power']  ?? '-');
    $hi_idle_std= $row['hi_idle_std']?? ($spec['hi_idle']    ?? '-');

    $html .= '<table class="info-table">';
    $html .= '<tr><td class="info-label">Test Name</td><td>' . val($row,'test_name') . '</td><td class="info-label">Engine Model</td><td>' . val($row,'engine_model') . '</td><td class="info-label">Engine No.</td><td>' . val($row,'engine_no') . '</td><td class="info-label">Cont. Power</td><td>' . e(cleanNum($cont_power)) . '</td></tr>';
    $html .= '<tr><td class="info-label">Test Date</td><td>' . val($row,'test_date') . '</td><td class="info-label">Bench Test</td><td>' . val($row,'bench_test') . '</td><td class="info-label">Operator</td><td>' . val($row,'operator_name') . '</td><td class="info-label">Max Power</td><td>' . e(cleanNum($max_power)) . '</td></tr>';
    $html .= '<tr><td class="info-label">Fuel</td><td>' . val($row,'fuel_type') . '</td><td class="info-label">Fuel sp. Gravity</td><td>' . val($row,'fuel_sp_gravity') . '</td><td class="info-label">Dry Temp (°C)</td><td>' . val($row,'dry_temp') . '</td><td class="info-label">Wet Temp (°C)</td><td>' . val($row,'wet_temp') . '</td></tr>';
    $html .= '<tr><td class="info-label">Atm. Press</td><td>' . val($row,'atmosphere_press') . '</td><td class="info-label">Lube Oil</td><td>' . val($row,'lube_oil') . '</td><td class="info-label">Limiter Act.</td><td>' . val($row,'limiter_actual') . '</td><td class="info-label">Limiter After Set</td><td>' . val($row,'limiter_after_set') . '</td></tr>';
    $html .= '<tr><td class="info-label">Hi Idle (std)</td><td>' . e(cleanNum($hi_idle_std)) . '</td><td class="info-label">Hi Idle (actual)</td><td>' . val($row,'hi_idle_actual') . '</td><td class="info-label">Eng. Speed Max</td><td>' . val($row,'eng_speed_max') . '</td><td class="info-label">Eng. Speed Min</td><td>' . val($row,'eng_speed_min') . '</td></tr>';
    $html .= '</table>';

    if ($checklist) {
        $html .= '<table class="data-tbl"><thead><tr><th>#</th><th>Type</th><th>Item</th><th>Result</th></tr></thead><tbody>';
        foreach ($checklist as $i => $c) {
            $res = $c['jawaban'] ?? '-';
            $kategori = $c['kategori'] ?? '';
            if ($kategori === 'Leakage Check') {
                // Khusus Leakage Check: "No" = tidak ada kebocoran = bagus (hijau),
                // "Yes" = ada kebocoran = masalah (merah) - kebalikan dari kategori lain.
                $color = ($res === 'No') ? '#198754' : (($res === 'Yes') ? '#dc3545' : '#666');
            } else {
                $color = ($res === 'Yes' || $res === 'OK') ? '#198754' : '#dc3545';
            }
            $html .= '<tr><td>' . ($i+1) . '</td><td>' . e($c['kategori'] ?? '') . '</td><td>' . e($c['item_name'] ?? '') . '</td>';
            $html .= '<td style="color:' . $color . ';font-weight:bold;">' . e($res) . '</td></tr>';
        }
        $html .= '</tbody></table>';
    }

    // Paksa halaman baru sebelum tabel Data Performance, biar nggak kepotong
    // di tengah tabel (baris 1 di satu halaman, baris 2 + header lagi di halaman lain).
    $html .= '<pagebreak>';

    $html .= '<table class="data-tbl"><thead>';
    $html .= '<tr><th rowspan="3">No</th><th rowspan="3">Eng.Speed</th><th colspan="8">OUTPUT, TORQUE & FUEL (DATA 1)</th><th colspan="10" class="th2">TEMPERATURE, PRESSURE & EMISSION (DATA 2)</th></tr>';
    $html .= '<tr><th colspan="2">Output</th><th rowspan="2">Torque</th><th rowspan="2">Load</th><th colspan="3">Fuel</th><th rowspan="2">Sd</th><th rowspan="2">Exhaust</th><th rowspan="2">Oil Temp</th><th rowspan="2">LO</th><th rowspan="2">Intake</th><th rowspan="2">Exhaust kPa</th><th rowspan="2">NOx</th><th rowspan="2">CO</th><th rowspan="2">CO2</th><th rowspan="2">O2</th><th rowspan="2">Cor.CO</th></tr>';
    $html .= '<tr><th>Actual</th><th>Corrected</th><th>cc/30s</th><th>mm3/st</th><th>g/kWh</th></tr></thead><tbody>';
    $html .= '<tr><td>1</td><td>' . val($row,'eng_speed_max') . '</td><td>' . val($row,'r1_actual_nm') . '</td><td>' . val($row,'r1_corrected_kw') . '</td><td>' . val($row,'r1_torque_nm') . '</td><td>' . val($row,'r1_load_kgm') . '</td><td>' . val($row,'r1_fuel_cc_30sec') . '</td><td>' . val($row,'r1_fuel_mm3_st') . '</td><td>' . val($row,'r1_fuel_g_kwh') . '</td><td>' . val($row,'r1_sd_bsu') . '</td><td>' . val($row,'r1_temp_exhaust') . '</td><td>' . val($row,'r1_temp_oil') . '</td><td>' . val($row,'r1_lo_press') . '</td><td>' . val($row,'r1_intake_press') . '</td><td>' . val($row,'r1_exhaust_press') . '</td><td>' . val($row,'r1_nox') . '</td><td>' . val($row,'r1_co') . '</td><td>' . val($row,'r1_co2') . '</td><td>' . val($row,'r1_o2') . '</td><td>-</td></tr>';
    $html .= '<tr><td>2</td><td>' . val($row,'eng_speed_min') . '</td><td>' . val($row,'r2_actual_nm') . '</td><td>' . val($row,'r2_corrected_kw') . '</td><td colspan="6" style="background:#eee;"></td><td>' . val($row,'r2_temp_exhaust') . '</td><td>-</td><td>' . val($row,'r2_lo_press') . '</td><td>' . val($row,'r2_intake_press') . '</td><td>' . val($row,'r2_exhaust_press') . '</td><td>' . val($row,'r2_nox') . '</td><td>' . val($row,'r2_co') . '</td><td>' . val($row,'r2_co2') . '</td><td>' . val($row,'r2_o2') . '</td><td>' . val($row,'r2_correct_co') . '</td></tr>';
    $html .= '</tbody></table>';

    // Additional Data
    $html .= '<table class="data-tbl"><thead><tr><th>Eng.Speed</th><th>Torque(Nm)</th><th>Coolant°C</th><th>Curr.Glow</th><th>Curr.Wire</th><th>Box LO</th><th>Air Intake</th><th>Bolt CW</th><th>Inj.Injector</th><th>Inj.FOP</th><th>Nut Joint</th><th>α</th><th>β</th><th>Blow By</th></tr></thead><tbody>';
    $html .= '<tr><td>' . val($row,'eng_speed_max') . '</td><td>' . val($row,'r3_torque_nm') . '</td><td>' . val($row,'r3_coolant_temp') . '</td><td>' . val($row,'r3_current_glow') . '</td><td>' . val($row,'r3_current_wire') . '</td><td>' . val($row,'r3_torque_switch_lo') . '</td><td>' . val($row,'r3_torque_pipe_air') . '</td><td>' . val($row,'r3_torque_bolt_cw') . '</td><td>' . val($row,'r3_torque_injection_injector') . '</td><td>' . val($row,'r3_torque_injection_fop') . '</td><td>' . val($row,'r3_torque_nut_joint') . '</td><td>' . val($row,'correction_alpha') . '</td><td>' . val($row,'correction_beta') . '</td><td>' . val($row,'blow_by') . '</td></tr>';
    $html .= '</tbody></table>';
    // ---- Correction Factor & Blow By + Fuel Injection Timing (FIC) ----
    // Dibuat 2 tabel terpisah, struktur kolom sama persis kayak form input aslinya
    // (kiri/kanan dipisah, bukan digabung jadi satu teks "X / Y").
    $html .= '<table style="width:100%;border-collapse:collapse;margin-top:6px;"><tr>';

    // Kolom kiri: Correction Factor & Blow By
    $html .= '<td style="width:50%;vertical-align:top;padding-right:6px;">';
    $html .= '<table class="data-tbl" style="margin-bottom:0;"><thead>';
    $html .= '<tr><th colspan="2">Correction Factor</th><th rowspan="2">Blow by (std &lt;0.8%)</th></tr>';
    $html .= '<tr><th>α</th><th>β</th></tr></thead><tbody>';
    $html .= '<tr><td>' . val($row,'correction_alpha') . '</td><td>' . val($row,'correction_beta') . '</td><td>' . val($row,'blow_by') . '</td></tr>';
    $html .= '</tbody></table>';
    $html .= '<table class="data-tbl" style="margin-top:2px;"><thead>';
    $html .= '<tr><th colspan="2">Min eng. Speed when LO switch ON &le;500 rpm</th><th>Distance of Pulley Crank Shaft to Ring Gear (std 92-93 mm/96-97 mm)</th></tr></thead><tbody>';
    $html .= '<tr><td colspan="2">' . val($row,'min_eng_speed_lo') . ' rpm</td><td>' . val($row,'pulley_distance') . ' mm</td></tr>';
    $html .= '</tbody></table>';
    $html .= '</td>';

    // Kolom kanan: Fuel Injection Timing (FIC)
    $html .= '<td style="width:50%;vertical-align:top;padding-left:6px;">';
    $html .= '<table class="data-tbl" style="margin-bottom:0;"><tbody>';
    $html .= '<tr><td style="text-align:left;background:#7B1D1D;color:#fff;font-weight:bold;">FIC Standard</td><td colspan="3">' . val($row,'fic_standard') . '</td></tr>';
    $html .= '<tr><td style="text-align:left;background:#7B1D1D;color:#fff;font-weight:bold;">FIC Actual</td><td>' . val($row,'fic_actual_left') . '</td><td>&deg;/</td><td>' . val($row,'fic_actual_right') . '</td></tr>';
    $html .= '<tr><td style="text-align:left;background:#7B1D1D;color:#fff;font-weight:bold;">FIC Before Test</td><td>' . val($row,'fic_before_test_left') . '</td><td>&deg;/</td><td>' . val($row,'fic_before_test_right') . '</td></tr>';
    $html .= '<tr><td style="text-align:left;background:#7B1D1D;color:#fff;font-weight:bold;">FIC After Test</td><td>' . val($row,'fic_after_test_left') . '</td><td>&deg;/</td><td>' . val($row,'fic_after_test_right') . '</td></tr>';
    $html .= '<tr><td style="text-align:left;background:#7B1D1D;color:#fff;font-weight:bold;">Belt Tension 15-20mm</td><td>' . val($row,'belt_tension_left') . '</td><td>mm</td><td>' . val($row,'belt_tension_right') . '</td></tr>';
    $html .= '</tbody></table>';
    $html .= '</td>';

    $html .= '</tr></table>';
    // Foto Engine
    $foto_list = [];
    for ($fi = 1; $fi <= 3; $fi++) {
        $fp = $row['foto_engine_' . $fi] ?? '';
        if ($fp && file_exists($fp)) {
            $type = mime_content_type($fp);
            $foto_list[] = ['src' => 'data:' . $type . ';base64,' . base64_encode(file_get_contents($fp)), 'label' => 'Engine Photo ' . $fi];
        }
    }
    if (!empty($foto_list)) {
        $html .= '<div style="margin:6px 0;font-weight:bold;font-size:8pt;color:#7B1D1D;">ENGINE PHOTO</div>';
        $html .= '<table style="width:100%;border-collapse:collapse;"><tr>';
        foreach ($foto_list as $foto) {
            $html .= '<td style="text-align:center;padding:4px;width:33%;"><img src="' . $foto['src'] . '" style="max-width:200px;max-height:150px;border:1px solid #ddd;border-radius:4px;"><div style="font-size:7pt;color:#aaa;">' . $foto['label'] . '</div></td>';
        }
        for ($i = count($foto_list); $i < 3; $i++) {
            $html .= '<td style="width:33%;"></td>';
        }
        $html .= '</tr></table>';
    }

    $html .= approvalBox($approvals, [['role_key'=>'Foreman','label'=>'Foreman']]);
    return $html;
}

function generateFIHtml($row, $checklist, $logo_b64, $koneksi) {
    $approvals = getApproval($koneksi, $row['id'], 'Final_Inspection');
    $html = '<div class="header-wrap">';
    $html .= $logo_b64 ? ('<div class="header-logo"><img src="' . $logo_b64 . '"></div>') : '';
    $html .= '<div class="header-info"><h1>PT. YANMAR DIESEL INDONESIA</h1><p>Quality Control Department - Engine Manufacturing</p></div>';
    $html .= '<div class="header-meta">Doc No: QCFI-001<br>Rev: 00<br>' . date('d/m/Y') . '</div></div>';
    $html .= '<div class="section-title">FINAL INSPECTION REPORT</div>';
    $html .= '<table class="info-table">';
    $html .= '<tr><td class="info-label">Engine Model</td><td>' . val($row,'engine_model') . '</td><td class="info-label">Inspect Date</td><td>' . val($row,'inspect_date') . '</td></tr>';
    $html .= '<tr><td class="info-label">Engine No.</td><td>' . val($row,'engine_no') . '</td><td class="info-label">Operator</td><td>' . val($row,'operator_name') . '</td></tr>';
    if (!empty($row['noted'])) $html .= '<tr><td class="info-label">Noted</td><td colspan="3">' . e($row['noted']) . '</td></tr>';
    $html .= '</table>';

    if ($checklist) {
        $html .= '<table class="data-tbl"><thead><tr><th>#</th><th>Item</th><th>Parameter</th><th>Result</th><th>Photo</th></tr></thead><tbody>';
        foreach ($checklist as $i => $c) {
            $res = $c['result'] ?? '-';
            $color = ($res === 'OK') ? '#198754' : (($res === 'NG') ? '#dc3545' : '#666');
            $foto_td = '<td>-</td>';
            $fp = $c['foto_path'] ?? '';
            if ($fp && file_exists($fp)) {
                $ft = mime_content_type($fp);
                $fb = 'data:' . $ft . ';base64,' . base64_encode(file_get_contents($fp));
                $foto_td = '<td style="text-align:center;"><img src="' . $fb . '" style="max-width:60px;max-height:45px;border-radius:3px;border:0.5px solid #ddd;"></td>';
            }
            $html .= '<tr><td>' . ($i+1) . '</td><td>' . e($c['item_name'] ?? '') . '</td><td style="font-size:7pt;color:#666;">' . e($c['parameter'] ?? '') . '</td>';
            $html .= '<td style="color:' . $color . ';font-weight:bold;">' . e($res) . '</td>' . $foto_td . '</tr>';
        }
        $html .= '</tbody></table>';
    }
    $html .= approvalBox($approvals, [['role_key'=>'Foreman','label'=>'Foreman'],['role_key'=>'Supervisor','label'=>'Supervisor']]);
    return $html;
}

function generatePKHtml($row, $checklist, $logo_b64, $koneksi) {
    $approvals = getApproval($koneksi, $row['id'], 'Packing');
    $html = '<div class="header-wrap">';
    $html .= $logo_b64 ? ('<div class="header-logo"><img src="' . $logo_b64 . '"></div>') : '';
    $html .= '<div class="header-info"><h1>PT. YANMAR DIESEL INDONESIA</h1><p>Quality Control Department - Engine Manufacturing</p></div>';
    $html .= '<div class="header-meta">Doc No: QCPK-001<br>Rev: 00<br>' . date('d/m/Y') . '</div></div>';
    $html .= '<div class="section-title">PACKING REPORT</div>';
    $html .= '<table class="info-table">';
    $html .= '<tr><td class="info-label">Engine Model</td><td>' . val($row,'engine_model') . '</td><td class="info-label">Pack Date</td><td>' . val($row,'pack_date') . '</td></tr>';
    $html .= '<tr><td class="info-label">Engine No.</td><td>' . val($row,'engine_no') . '</td><td class="info-label">Operator</td><td>' . val($row,'operator_name') . '</td></tr>';
    $html .= '<tr><td class="info-label">Recorded By</td><td>' . val($row,'dicatat_oleh') . '</td><td class="info-label">Noted</td><td>' . val($row,'noted') . '</td></tr>';
    $html .= '</table>';

    if ($checklist) {
        $html .= '<table class="data-tbl"><thead><tr><th>#</th><th>Item</th><th>Parameter</th><th>Result</th><th>Photo</th></tr></thead><tbody>';
        foreach ($checklist as $i => $c) {
            $res = $c['result'] ?? '-';
            $color = ($res === 'Check') ? '#198754' : (($res === 'NG') ? '#dc3545' : '#666');
            $foto_td = '<td>-</td>';
            $fp = $c['foto_path'] ?? '';
            if ($fp && file_exists($fp)) {
                $ft = mime_content_type($fp);
                $fb = 'data:' . $ft . ';base64,' . base64_encode(file_get_contents($fp));
                $foto_td = '<td style="text-align:center;"><img src="' . $fb . '" style="max-width:60px;max-height:45px;border-radius:3px;border:0.5px solid #ddd;"></td>';
            }
            $html .= '<tr><td>' . ($i+1) . '</td><td>' . e($c['item_name'] ?? '') . '</td><td style="font-size:7pt;color:#666;">' . e($c['parameter'] ?? '') . '</td>';
            $html .= '<td style="color:' . $color . ';font-weight:bold;">' . e($res) . '</td>' . $foto_td . '</tr>';
        }
        $html .= '</tbody></table>';
    }
    $html .= approvalBox($approvals, [['role_key'=>'Foreman','label'=>'Foreman'],['role_key'=>'Supervisor','label'=>'Supervisor'],['role_key'=>'Asst_Manager','label'=>'Asst. Manager']]);
    return $html;
}

// ---- AJAX: CEK APPROVAL STATUS ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'check_approval') {
    header('Content-Type: application/json');
    $engine_nos = $_POST['engine_nos'] ?? [];
    $warnings = [];
    foreach ($engine_nos as $engine_no) {
        $en = mysqli_real_escape_string($koneksi, $engine_no);
        $tr = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT id FROM result_test_run WHERE engine_no='$en' LIMIT 1"));
        if ($tr) {
            $apv = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT id FROM approvals WHERE test_run_id={$tr['id']} AND stage='Test_Running' AND role='Foreman' AND status='approved'"));
            if (!$apv) $warnings[] = "Engine <b>$en</b> – Test Running belum di-approve Foreman";
        }
        $fi = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT id FROM final_inspection_data WHERE engine_no='$en' LIMIT 1"));
        if ($fi) {
            $apv = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT id FROM approvals WHERE test_run_id={$fi['id']} AND stage='Final_Inspection' AND role='Supervisor' AND status='approved'"));
            if (!$apv) $warnings[] = "Engine <b>$en</b> – Final Inspection belum di-approve Supervisor";
        }
        $pk = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT id FROM packing_data WHERE engine_no='$en' LIMIT 1"));
        if ($pk) {
            $apv = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT id FROM approvals WHERE test_run_id={$pk['id']} AND stage='Packing' AND role='Asst_Manager' AND status='approved'"));
            if (!$apv) $warnings[] = "Engine <b>$en</b> – Packing belum di-approve Asisten Manager";
        }
    }
    echo json_encode(['warnings' => $warnings]);
    exit();
}

// ---- HANDLE POST - GENERATE PDF ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['engine_nos'])) {
    require_once __DIR__ . '/vendor/autoload.php';
    @ini_set('pcre.backtrack_limit', '500000000');
    @ini_set('pcre.recursion_limit', '500000000');
    @ini_set('memory_limit', '1024M');
    @set_time_limit(300);

    $logo_b64 = getLogoB64();
    $engine_nos = $_POST['engine_nos'];

    $mpdf = new \Mpdf\Mpdf([
        'mode'          => 'utf-8',
        'format'        => 'A4-L',
        'margin_top'    => 10,
        'margin_bottom' => 10,
        'margin_left'   => 10,
        'margin_right'  => 10,
    ]);

    $is_first = true;
    $included_modules = []; // 'TR' / 'FI' / 'PK', dikumpulin buat nama file kalau cuma 1 engine
    $engine_model_for_name = '';
    $engine_no_for_name    = '';

    foreach ($engine_nos as $engine_no) {
        $en = mysqli_real_escape_string($koneksi, $engine_no);

        // TEST RUNNING - hanya jika sudah approved Foreman
        $tr = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT * FROM result_test_run WHERE engine_no = '$en' ORDER BY id DESC LIMIT 1"));
        if ($tr) {
            $apv_tr = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT id FROM approvals WHERE test_run_id={$tr['id']} AND stage='Test_Running' AND role='Foreman' AND status='approved'"));
            if ($apv_tr) {
                if (!$is_first) $mpdf->AddPage('L');
                $is_first = false;
                $included_modules[] = 'TR';
                $engine_model_for_name = $tr['engine_model'] ?? $engine_model_for_name;
                $engine_no_for_name    = $tr['engine_no'] ?? $engine_no_for_name;
                $checklist_tr = [];
                $q = mysqli_query($koneksi, "SELECT * FROM checklist WHERE engine_no = '$en'");
                if ($q) while ($r = mysqli_fetch_assoc($q)) $checklist_tr[] = $r;
                $html = generateTRHtml($tr, $checklist_tr, $logo_b64, $koneksi);
                $mpdf->WriteHTML(getCss(), \Mpdf\HTMLParserMode::HEADER_CSS);
                $mpdf->WriteHTML($html, \Mpdf\HTMLParserMode::HTML_BODY);
            }
        }

        // FINAL INSPECTION - hanya jika sudah approved Supervisor
        $fi = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT * FROM final_inspection_data WHERE engine_no = '$en' ORDER BY id DESC LIMIT 1"));
        if ($fi) {
            $apv_fi = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT id FROM approvals WHERE test_run_id={$fi['id']} AND stage='Final_Inspection' AND role='Supervisor' AND status='approved'"));
            if ($apv_fi) {
                if (!$is_first) $mpdf->AddPage('L');
                $is_first = false;
                $included_modules[] = 'FI';
                $engine_model_for_name = $fi['engine_model'] ?? $engine_model_for_name;
                $engine_no_for_name    = $fi['engine_no'] ?? $engine_no_for_name;
                $checklist_fi = [];
                $q = mysqli_query($koneksi, "SELECT * FROM final_inspection_checklist WHERE fi_id = " . intval($fi['id']));
                if ($q) while ($r = mysqli_fetch_assoc($q)) $checklist_fi[] = $r;
                $html = generateFIHtml($fi, $checklist_fi, $logo_b64, $koneksi);
                $mpdf->WriteHTML(getCss(), \Mpdf\HTMLParserMode::HEADER_CSS);
                $mpdf->WriteHTML($html, \Mpdf\HTMLParserMode::HTML_BODY);
            }
        }

        // PACKING - hanya jika sudah approved Asst_Manager
        $pk = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT * FROM packing_data WHERE engine_no = '$en' ORDER BY id DESC LIMIT 1"));
        if ($pk) {
            $apv_pk = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT id FROM approvals WHERE test_run_id={$pk['id']} AND stage='Packing' AND role='Asst_Manager' AND status='approved'"));
            if ($apv_pk) {
                if (!$is_first) $mpdf->AddPage('L');
                $is_first = false;
                $included_modules[] = 'PK';
                $engine_model_for_name = $pk['engine_model'] ?? $engine_model_for_name;
                $engine_no_for_name    = $pk['engine_no'] ?? $engine_no_for_name;
                $checklist_pk = [];
                $q = mysqli_query($koneksi, "SELECT * FROM packing_checklist WHERE pack_id = " . intval($pk['id']));
                if ($q) while ($r = mysqli_fetch_assoc($q)) $checklist_pk[] = $r;
                $html = generatePKHtml($pk, $checklist_pk, $logo_b64, $koneksi);
                $mpdf->WriteHTML(getCss(), \Mpdf\HTMLParserMode::HEADER_CSS);
                $mpdf->WriteHTML($html, \Mpdf\HTMLParserMode::HTML_BODY);
            }
        }
    }

    // Nama file: kalau cuma 1 engine yang di-download, pakai format
    // QC_TR-FI-PK_Model_Nomor.pdf (modul yang dipakai sesuai yang beneran ada & approved).
    // Kalau lebih dari 1 engine, nggak mungkin 1 nama file mewakili semuanya -> fallback ke nama batch umum.
    function sanitizeForFilename($s) {
        $s = preg_replace('/[^A-Za-z0-9]+/', '', (string) $s);
        return $s !== '' ? $s : 'X';
    }
    if (count($engine_nos) === 1 && !empty($included_modules)) {
        $mod_part   = implode('-', array_unique($included_modules));
        $model_part = sanitizeForFilename($engine_model_for_name);
        $eno_part   = sanitizeForFilename($engine_no_for_name);
        $filename   = 'QC_' . $mod_part . '_' . $model_part . '_' . $eno_part . '.pdf';
    } else {
        $filename = 'QC_Batch_' . date('Ymd_His') . '.pdf';
    }
    $mpdf->Output($filename, 'D');
    exit();
}

// ---- TAMPILAN HALAMAN ----
$all_engines = [];
$q = mysqli_query($koneksi, "
    SELECT
        engine_no,
        SUBSTRING_INDEX(GROUP_CONCAT(engine_model ORDER BY created_at DESC SEPARATOR '||'), '||', 1) AS engine_model,
        MAX(created_at) as last_date
    FROM (
        SELECT engine_no, engine_model, created_at FROM result_test_run
        UNION ALL
        SELECT engine_no, engine_model, created_at FROM final_inspection_data
        UNION ALL
        SELECT engine_no, engine_model, created_at FROM packing_data
    ) combined GROUP BY engine_no ORDER BY last_date DESC
");

// Daftar 5 model resmi dari master_engine_spec (satu-satunya sumber kebenaran).
// Dipakai buat menormalisasi variasi penulisan model (mis. "TF70V-E2" vs "TF 70V E-2")
// supaya engine dengan penulisan model yang beda tetap masuk grup yang sama.
$canonical_models = []; // normalized_key => display_name
$qm = mysqli_query($koneksi, "SELECT DISTINCT engine_model FROM master_engine_spec");
if ($qm) while ($m = mysqli_fetch_assoc($qm)) {
    $norm = strtoupper(preg_replace('/[\s\-]+/', '', $m['engine_model']));
    $canonical_models[$norm] = $m['engine_model'];
}

if ($q) while ($r = mysqli_fetch_assoc($q)) {
    // Samakan penulisan model apapun yang tersimpan di record (TR/FI/Packing) ke nama resmi
    // dari master_engine_spec, supaya perbedaan spasi/tanda hubung format lama tidak bikin grup terpecah.
    $norm = strtoupper(preg_replace('/[\s\-]+/', '', $r['engine_model'] ?? ''));
    if (isset($canonical_models[$norm])) {
        $r['engine_model'] = $canonical_models[$norm];
    }
    $all_engines[] = $r;
}

// Kelompokkan per engine_model, biar tidak campur jadi satu list
$grouped_engines = [];
foreach ($all_engines as $eng) {
    $model_key = $eng['engine_model'] ?: '(Tanpa Model)';
    $grouped_engines[$model_key][] = $eng;
}

// Urutkan berdasarkan ukuran mesin (70, 90, 120, dst), bukan alfabetis biasa
// (biar TF 70V... muncul duluan sebelum TF 120V..., bukan sebaliknya)
uksort($grouped_engines, function($a, $b) {
    $numA = (int) preg_replace('/[^0-9]/', '', $a);
    $numB = (int) preg_replace('/[^0-9]/', '', $b);
    if ($numA !== $numB) return $numA <=> $numB;
    return strcmp($a, $b);
});

// Simpan daftar SEMUA model (sebelum kena filter search) - dipakai buat render tab bar,
// supaya tab tidak ikut hilang cuma karena search tidak match di model itu.
$all_model_names = array_keys($grouped_engines);

// Filter pencarian server-side (via GET, reload halaman biasa - tidak pakai JS sama sekali)
$search_q = trim($_GET['q'] ?? '');
if ($search_q !== '') {
    $search_lower = strtolower($search_q);
    foreach ($grouped_engines as $model => &$engines_ref) {
        $engines_ref = array_values(array_filter($engines_ref, function($e) use ($search_lower) {
            return strpos(strtolower($e['engine_no']), $search_lower) !== false
                || strpos(strtolower($e['engine_model']), $search_lower) !== false;
        }));
    }
    unset($engines_ref);
    // Buang grup yang jadi kosong setelah difilter (hanya dari daftar ENGINE yang ditampilkan,
    // tab bar tetap pakai $all_model_names supaya semua tab tetap muncul)
    $grouped_engines = array_filter($grouped_engines, function($engines_in_group) { return count($engines_in_group) > 0; });
}

// Total hasil pencarian (dipakai buat badge "Semua"), dihitung SEBELUM difilter tab model,
// supaya tab model & search sama-sama disimpan di URL dan nggak saling menghapus.
$total_after_search = array_sum(array_map('count', $grouped_engines));

// Tab model aktif juga disimpan lewat URL, bukan JS, supaya nggak "loncat" balik ke Semua
// tiap kali search di-submit (form reload).
$active_model_param = $_GET['model'] ?? '__all__';
$grouped_engines_for_tabs = $grouped_engines; // dipakai buat hitung badge tiap tab (sebelum difilter tab)
if ($active_model_param !== '__all__') {
    // Kalau model ini valid tapi hasil search-nya nol, tampilkan KOSONG untuk tab ini -
    // jangan fallback ke semua model (itu bug sebelumnya, bikin model lain "ikut nongol").
    $grouped_engines = isset($grouped_engines[$active_model_param])
        ? [$active_model_param => $grouped_engines[$active_model_param]]
        : [];
}

function qs($params_override) {
    global $search_q, $active_model_param;
    $params = ['q' => $search_q, 'model' => $active_model_param];
    $params = array_merge($params, $params_override);
    $params = array_filter($params, function($v) { return $v !== '' && $v !== '__all__'; });
    return 'batch_pdf.php' . (count($params) ? '?' . http_build_query($params) : '');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Batch Download PDF - QC Yanmar</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    body { background:#ebebeb; font-family:'Segoe UI',sans-serif; }
    .top-bar { background:linear-gradient(135deg,#5a1414,#7B1D1D,#a83232); padding:12px 20px; margin-bottom:20px; }
    .engine-card { cursor:pointer; transition:all 0.2s; border:2px solid #dee2e6; border-radius:8px; padding:10px 14px; background:#fff; margin-bottom:8px; }
    .engine-card:hover { border-color:#7B1D1D; background:#fdf5f5; }
    .engine-card.selected { border-color:#7B1D1D; background:#fdf5f5; box-shadow:0 0 0 3px rgba(123,29,29,0.2); }
    .engine-card input[type=checkbox] { accent-color:#7B1D1D; width:16px; height:16px; }
    .btn-download { background:linear-gradient(135deg,#5a1414,#7B1D1D); color:#fff; font-weight:700; border:none; padding:12px 32px; border-radius:8px; font-size:14px; width:100%; }
    .btn-download:disabled { background:#ccc; cursor:not-allowed; }
    .badge-tr { background:#7B1D1D; color:#fff; font-size:9px; padding:2px 6px; border-radius:4px; }
    .badge-fi { background:#1a5c3a; color:#fff; font-size:9px; padding:2px 6px; border-radius:4px; }
    .badge-pk { background:#1a3a5c; color:#fff; font-size:9px; padding:2px 6px; border-radius:4px; }
    .engine-group { margin-bottom:14px; }
    .engine-group-header { padding:6px 10px; background:#f7f2f2; border-left:3px solid #7B1D1D; border-radius:4px; margin-bottom:6px; }
    .model-tab-btn { background:#fff; border:1px solid #dee2e6; color:#555; font-size:12px; font-weight:600; padding:6px 14px; border-radius:20px; cursor:pointer; transition:all 0.15s; }
    .model-tab-btn:hover { border-color:#7B1D1D; color:#7B1D1D; }
    .model-tab-btn.active { background:linear-gradient(135deg,#5a1414,#7B1D1D); border-color:#7B1D1D; color:#fff; }
</style>
</head>
<body>
<div class="top-bar d-flex justify-content-between align-items-center">
    <div class="d-flex align-items-center gap-3">
        <a href="index.php" class="btn btn-sm" style="background:rgba(255,255,255,0.2);color:#fff;border:1px solid rgba(255,255,255,0.4);">
            <i class="fa-solid fa-arrow-left me-1"></i>Kembali
        </a>
        <h5 class="text-white fw-bold m-0"><i class="fa-solid fa-file-pdf me-2"></i>Batch Download PDF</h5>
    </div>
    <div class="text-white" style="font-size:12px;">
        <i class="fa-solid fa-user me-1"></i><?php echo e($_SESSION['nama_lengkap']); ?>
        <span style="background:rgba(255,255,255,0.2);padding:2px 8px;border-radius:4px;margin-left:6px;font-size:10px;"><?php echo strtoupper(str_replace('_',' ',$_SESSION['role'])); ?></span>
    </div>
</div>

<div class="container-fluid px-4">
    <div class="row g-3">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center py-2" style="background:linear-gradient(135deg,#5a1414,#7B1D1D);">
                    <h6 class="text-white fw-bold m-0"><i class="fa-solid fa-list me-2"></i>Pilih Engine</h6>
                    <div class="d-flex gap-2">
                        <button class="btn btn-sm" style="background:rgba(255,255,255,0.2);color:#fff;font-size:11px;" onclick="selectAll()">Pilih Semua</button>
                        <button class="btn btn-sm" style="background:rgba(255,255,255,0.2);color:#fff;font-size:11px;" onclick="clearAll()">Bersihkan</button>
                    </div>
                </div>
                <div class="card-body p-3">
                    <div id="model-tabs" class="d-flex flex-wrap gap-2 mb-3">
                        <a href="<?php echo e(qs(['model' => '__all__'])); ?>" class="model-tab-btn<?php echo $active_model_param === '__all__' ? ' active' : ''; ?>" style="text-decoration:none;display:inline-block;">
                            Semua <span class="badge" style="background:rgba(255,255,255,0.3);"><?php echo $total_after_search; ?></span>
                        </a>
                        <?php foreach ($all_model_names as $model_name): $engines_in_model = $grouped_engines_for_tabs[$model_name] ?? []; ?>
                        <a href="<?php echo e(qs(['model' => $model_name])); ?>" class="model-tab-btn<?php echo $active_model_param === $model_name ? ' active' : ''; ?>" style="text-decoration:none;display:inline-block;">
                            <?php echo e($model_name); ?> <span class="badge" style="background:rgba(255,255,255,0.3);"><?php echo count($engines_in_model); ?></span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <form method="GET" class="d-flex gap-2 mb-3">
                        <input type="hidden" name="model" value="<?php echo e($active_model_param); ?>">
                        <input type="text" name="q" class="form-control form-control-sm" placeholder="Cari engine no atau model..." autocomplete="off" value="<?php echo e($search_q); ?>">
                        <button type="submit" class="btn btn-sm fw-bold" style="background:#7B1D1D;color:#fff;white-space:nowrap;">
                            <i class="fa-solid fa-magnifying-glass me-1"></i>Cari
                        </button>
                        <?php if ($search_q !== ''): ?>
                        <a href="<?php echo e(qs(['q' => ''])); ?>" class="btn btn-sm btn-outline-secondary fw-bold" style="white-space:nowrap;">
                            <i class="fa-solid fa-xmark me-1"></i>Reset
                        </a>
                        <?php endif; ?>
                    </form>
                    <div id="engine-list">
                    <?php foreach ($grouped_engines as $model_name => $engines_in_model): ?>
                    <div class="engine-group" data-model="<?php echo e($model_name); ?>">
                        <div class="engine-group-header d-flex align-items-center gap-2">
                            <i class="fa-solid fa-gear" style="color:#7B1D1D;font-size:11px;"></i>
                            <span style="font-size:12px;font-weight:700;color:#7B1D1D;"><?php echo e($model_name); ?></span>
                            <span class="badge" style="background:#eee;color:#666;font-size:10px;"><?php echo count($engines_in_model); ?> engine</span>
                        </div>
                        <?php foreach ($engines_in_model as $eng):
                            $en = e($eng['engine_no']);
                            $em = e($eng['engine_model'] ?? '');
                            $date_str = date('d/m/Y', strtotime($eng['last_date']));
                        ?>
                        <div class="engine-card d-flex align-items-center gap-3" onclick="toggleEngine(this)">
                            <input type="checkbox" class="engine-checkbox" value="<?php echo $en; ?>" onclick="event.stopPropagation();" onchange="onCheckboxChange(this)">
                            <div class="flex-grow-1">
                                <div class="fw-bold" style="font-size:13px;color:#333;"><?php echo $en; ?></div>
                                <div style="font-size:11px;color:#666;"><?php echo $em; ?> &nbsp;&middot;&nbsp; <?php echo $date_str; ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($all_engines)): ?>
                    <div class="text-center text-muted py-4"><i class="fa-solid fa-inbox fa-2x mb-2 d-block"></i>Belum ada data engine.</div>
                    <?php elseif (empty($grouped_engines)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="fa-solid fa-magnifying-glass fa-2x mb-2 d-block opacity-50"></i>
                        Tidak ada hasil<?php if ($search_q !== ''): ?> untuk "<strong><?php echo e($search_q); ?></strong>"<?php endif; ?><?php if ($active_model_param !== '__all__'): ?> di model <strong><?php echo e($active_model_param); ?></strong><?php endif; ?>.
                    </div>
                    <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card shadow-sm" style="position:sticky;top:20px;">
                <div class="card-header py-2" style="background:linear-gradient(135deg,#5a1414,#7B1D1D);">
                    <h6 class="text-white fw-bold m-0"><i class="fa-solid fa-file-pdf me-2"></i>Download</h6>
                </div>
                <div class="card-body p-3">
                    <div id="selected-info" class="text-muted mb-3" style="font-size:12px;">Belum ada engine yang dipilih.</div>
                    <div id="selected-list" class="mb-3"></div>
                    <!-- Warning Box -->
                    <div id="warning-box" style="display:none; background:#fff3cd; border:1px solid #ffc107; border-radius:8px; padding:12px; margin-bottom:12px;">
                        <div style="font-weight:700; color:#856404; margin-bottom:6px; font-size:12px;"><i class="fa-solid fa-triangle-exclamation me-1"></i>Ada modul belum selesai diapprove:</div>
                        <ul id="warning-list" style="margin:0; padding-left:16px; font-size:11px; color:#856404;"></ul>
                        <div class="mt-2 d-flex gap-2">
                            <button id="btn-confirm-download" class="btn btn-sm fw-bold" style="background:#7B1D1D;color:#fff;font-size:11px;"><i class="fa-solid fa-file-pdf me-1"></i>Tetap Download</button>
                            <button type="button" class="btn btn-sm btn-secondary fw-bold" style="font-size:11px;" onclick="document.getElementById('warning-box').style.display='none'">Batal</button>
                        </div>
                    </div>
                    <form method="POST" id="download-form">
                        <div id="hidden-inputs"></div>
                        <button type="button" class="btn-download" id="btn-download" disabled onclick="checkAndDownload()">
                            <i class="fa-solid fa-download me-2"></i>Download PDF
                        </button>
                    </form>
                    <div class="mt-3 p-2 rounded" style="background:#fdf5f5;font-size:11px;color:#666;">
                        <i class="fa-solid fa-circle-info me-1" style="color:#7B1D1D;"></i>
                        PDF berisi laporan TR, FI, dan Packing untuk tiap engine yang dipilih.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
var STORAGE_KEY = 'batchpdf_selected_engines';

function loadSelectedSet() {
    try {
        var raw = sessionStorage.getItem(STORAGE_KEY);
        return raw ? new Set(JSON.parse(raw)) : new Set();
    } catch (e) { return new Set(); }
}
function saveSelectedSet(set) {
    try { sessionStorage.setItem(STORAGE_KEY, JSON.stringify(Array.from(set))); } catch (e) {}
}

function toggleEngine(card) {
    var cb = card.querySelector('.engine-checkbox');
    cb.checked = !cb.checked;
    onCheckboxChange(cb);
}
function onCheckboxChange(cb) {
    var set = loadSelectedSet();
    if (cb.checked) set.add(cb.value); else set.delete(cb.value);
    saveSelectedSet(set);
    updateSelected();
}
function updateSelected() {
    // Sinkronkan status checkbox yang lagi tampil dengan set pilihan tersimpan
    var set = loadSelectedSet();
    document.querySelectorAll('.engine-checkbox').forEach(function(cb) {
        cb.checked = set.has(cb.value);
        cb.closest('.engine-card').classList.toggle('selected', cb.checked);
    });

    var info = document.getElementById('selected-info');
    var list = document.getElementById('selected-list');
    var hidden = document.getElementById('hidden-inputs');
    var btn = document.getElementById('btn-download');
    hidden.innerHTML = '';
    list.innerHTML = '';

    if (set.size === 0) {
        info.textContent = 'Belum ada engine yang dipilih.';
        btn.disabled = true;
        return;
    }
    info.innerHTML = '<strong>' + set.size + '</strong> engine dipilih';
    btn.disabled = false;
    set.forEach(function(engineNo) {
        var inp = document.createElement('input');
        inp.type = 'hidden'; inp.name = 'engine_nos[]'; inp.value = engineNo;
        hidden.appendChild(inp);
        var tag = document.createElement('span');
        tag.className = 'badge me-1 mb-1';
        tag.style.cssText = 'background:#7B1D1D;color:#fff;font-size:11px;';
        tag.textContent = engineNo;
        list.appendChild(tag);
    });
}
function selectAll() {
    var set = loadSelectedSet();
    document.querySelectorAll('.engine-card .engine-checkbox').forEach(function(cb) {
        set.add(cb.value);
    });
    saveSelectedSet(set);
    updateSelected();
}
function clearAll() {
    saveSelectedSet(new Set());
    updateSelected();
}
// Sinkronkan tampilan checkbox begitu halaman dimuat/reload (mis. setelah search)
document.addEventListener('DOMContentLoaded', updateSelected);
function checkAndDownload() {
    var checked = document.querySelectorAll('.engine-checkbox:checked');
    if (checked.length === 0) return;
    var engine_nos = Array.from(checked).map(function(c) { return c.value; });
    var formData = new FormData();
    formData.append('action', 'check_approval');
    engine_nos.forEach(function(en) { formData.append('engine_nos[]', en); });
    fetch('batch_pdf.php', { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.warnings && data.warnings.length > 0) {
                var list = data.warnings.map(function(w) { return '<li>' + w + '</li>'; }).join('');
                document.getElementById('warning-list').innerHTML = list;
                document.getElementById('warning-box').style.display = 'block';
                document.getElementById('btn-confirm-download').onclick = function() {
                    document.getElementById('warning-box').style.display = 'none';
                    document.getElementById('download-form').submit();
                };
            } else {
                document.getElementById('download-form').submit();
            }
        })
        .catch(function() { document.getElementById('download-form').submit(); });
}
</script>
</body>
</html>
