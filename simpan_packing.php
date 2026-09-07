<?php
session_start();
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login") {
    header("location:login.php"); exit();
}
include 'koneksi.php';
include 'image_helper.php';
@ini_set('memory_limit', '256M');
@set_time_limit(120);

$role_s   = strtolower(trim($_SESSION['role']));
$is_op_pk = strpos($role_s, 'packing') !== false || strpos($role_s, 'foreman') !== false || $role_s === 'operator';
if (!$is_op_pk) {
    die("Akses ditolak.");
}

$engine_no = mysqli_real_escape_string($koneksi, trim($_POST['engine_no'] ?? ''));
$edit_id   = intval($_POST['edit_id'] ?? 0);

if ($engine_no === '') {
    die("Engine No. tidak boleh kosong.");
}

$fi = mysqli_fetch_assoc(mysqli_query($koneksi, "
    SELECT fi.id, fi.engine_model
    FROM final_inspection_data fi
    INNER JOIN approvals a ON a.test_run_id = fi.id AND a.stage='Final_Inspection' AND a.role='Supervisor' AND a.status='approved'
    WHERE fi.engine_no = '$engine_no'
    ORDER BY fi.id DESC LIMIT 1
"));
if (!$fi) {
    die("Engine No. ini belum di-approve Supervisor di Final Inspection, atau belum ada data Final Inspection-nya.");
}

$engine_model = mysqli_real_escape_string($koneksi, $fi['engine_model']);
$operator     = mysqli_real_escape_string($koneksi, $_POST['operator_name']  ?? $_SESSION['nama_lengkap']);
$dicatat_oleh = mysqli_real_escape_string($koneksi, $_SESSION['nama_lengkap']);
$pack_date    = date('Y-m-d');
$noted        = mysqli_real_escape_string($koneksi, $_POST['noted'] ?? '');

$upload_dir = 'uploads/packing/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

// =====================================================================
// MODE REWORK: edit_id diisi -> UPDATE record yang REJECTED, bukan INSERT baru.
// =====================================================================
if ($edit_id > 0) {
    $existing = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT id, engine_no FROM packing_data WHERE id = $edit_id"));
    if (!$existing || $existing['engine_no'] !== $engine_no) {
        die("Data rework tidak valid (engine_no tidak cocok).");
    }
    $isRejected = mysqli_fetch_assoc(mysqli_query($koneksi, "
        SELECT id FROM approvals WHERE test_run_id = $edit_id AND stage = 'Packing' AND status = 'rejected' LIMIT 1
    "));
    if (!$isRejected) {
        die("Data ini bukan data yang di-reject, tidak bisa di-rework.");
    }

    $pack_id = $edit_id;
    $reworked_by = mysqli_real_escape_string($koneksi, $_SESSION['nama_lengkap'] ?? '');

    // operator_name (operator packing asli) JANGAN ditimpa, biar tetap nyimpen siapa yang
    // packing PERTAMA KALI. dicatat_oleh boleh update (emang representasi "siapa yang input
    // data sesi ini"). Yang ngerjain rework dicatat terpisah di reworked_by.
    mysqli_query($koneksi, "
        UPDATE packing_data
        SET engine_model = '$engine_model', dicatat_oleh = '$dicatat_oleh', pack_date = '$pack_date', noted = '$noted',
            is_reworked = 1, reworked_by = '$reworked_by', reworked_at = NOW()
        WHERE id = $pack_id
    ");

    // Ambil path foto LAMA per item (index urutan item), buat dipertahankan kalau
    // operator nggak upload foto baru buat item itu.
    $old_photos = [];
    $qold = mysqli_query($koneksi, "SELECT id, item_name, foto_path FROM packing_checklist WHERE pack_id = $pack_id ORDER BY id ASC");
    $old_photos_by_item = [];
    if ($qold) while ($o = mysqli_fetch_assoc($qold)) $old_photos_by_item[$o['item_name']] = $o['foto_path'];

    mysqli_query($koneksi, "DELETE FROM packing_checklist WHERE pack_id = $pack_id");

    $items        = $_POST['item_name']  ?? [];
    $params       = $_POST['parameter']  ?? [];
    $results      = $_POST['result']     ?? [];
    $files        = $_FILES['foto']      ?? [];
    $repair_notes = $_POST['repair_note'] ?? [];

    foreach ($items as $i => $item_name) {
        $item_esc  = mysqli_real_escape_string($koneksi, $item_name);
        $param_esc = mysqli_real_escape_string($koneksi, $params[$i] ?? '');
        $result    = in_array($results[$i] ?? '', ['Check','NG','Rework','-']) ? $results[$i] : 'Check';
        $foto_path = $old_photos_by_item[$item_name] ?? ''; // default: pertahankan foto lama
        $rnote_esc = mysqli_real_escape_string($koneksi, trim($repair_notes[$i] ?? ''));

        if (!empty($files['name'][$i]) && $files['error'][$i] === UPLOAD_ERR_OK) {
            $ext     = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','webp'];
            if (in_array($ext, $allowed)) {
                $filename = 'pk_' . $pack_id . '_' . $i . '_' . time() . '.' . $ext;
                $dest     = $upload_dir . $filename;
                $saved    = resizeAndSaveImage($files['tmp_name'][$i], $dest, 1200, 75);
                if ($saved) $foto_path = $saved;
            }
        }

        $foto_esc = mysqli_real_escape_string($koneksi, $foto_path);
        mysqli_query($koneksi, "INSERT INTO packing_checklist (pack_id, item_name, parameter, result, foto_path, repair_note)
                                VALUES ($pack_id, '$item_esc', '$param_esc', '$result', '$foto_esc', '$rnote_esc')");
    }

    mysqli_query($koneksi, "DELETE FROM approvals WHERE test_run_id = $pack_id AND stage = 'Packing'");

    header("location:index.php?pk_rework_success=1#packing");
    exit();
}

// =====================================================================
// MODE NORMAL: submission baru
// =====================================================================

// Boleh submit ulang kalau: belum pernah ada Packing sama sekali, ATAU
// Packing terakhirnya di-reject di level manapun.
$existing_pk = mysqli_fetch_assoc(mysqli_query($koneksi, "
    SELECT pk.id FROM packing_data pk
    WHERE pk.engine_no = '$engine_no'
      AND pk.id = (SELECT MAX(id) FROM packing_data WHERE engine_no = '$engine_no')
      AND NOT EXISTS (
          SELECT 1 FROM approvals ap
          WHERE ap.test_run_id = pk.id AND ap.stage = 'Packing' AND ap.status = 'rejected'
      )
"));
if ($existing_pk) {
    die("Engine No. ini sudah ada data Packing-nya.");
}

// Validasi: SEMUA item checklist wajib ada fotonya. Dicek dulu sebelum insert
// apapun ke database, biar kalau ada yang kurang, submit ditolak total (bukan
// setengah tersimpan).
$items_check = $_POST['item_name'] ?? [];
$files_check = $_FILES['foto']     ?? [];
$missing_foto = [];
foreach ($items_check as $i => $item_name) {
    $has_file = !empty($files_check['name'][$i]) && $files_check['error'][$i] === UPLOAD_ERR_OK;
    if (!$has_file) {
        $missing_foto[] = $item_name;
    }
}
if (!empty($missing_foto)) {
    die("Foto wajib diisi untuk semua item checklist. Item berikut belum ada fotonya: " . implode(', ', $missing_foto));
}

// 1. Insert header
$sql_header = "INSERT INTO packing_data (engine_no, engine_model, operator_name, dicatat_oleh, pack_date, noted)
               VALUES ('$engine_no', '$engine_model', '$operator', '$dicatat_oleh', '$pack_date', '$noted')";
if (!mysqli_query($koneksi, $sql_header)) {
    die("Error: " . mysqli_error($koneksi));
}
$pack_id = mysqli_insert_id($koneksi);

// 2. Insert checklist items
$items        = $_POST['item_name']  ?? [];
$params       = $_POST['parameter']  ?? [];
$results      = $_POST['result']     ?? [];
$files        = $_FILES['foto']      ?? [];
$repair_notes = $_POST['repair_note'] ?? [];

foreach ($items as $i => $item_name) {
    $item_esc  = mysqli_real_escape_string($koneksi, $item_name);
    $param_esc = mysqli_real_escape_string($koneksi, $params[$i] ?? '');
    $result    = in_array($results[$i] ?? '', ['Check','NG','Rework','-']) ? $results[$i] : 'Check';
    $foto_path = '';
    $rnote_esc = mysqli_real_escape_string($koneksi, trim($repair_notes[$i] ?? ''));

    if (!empty($files['name'][$i]) && $files['error'][$i] === UPLOAD_ERR_OK) {
        $ext     = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','webp'];
        if (in_array($ext, $allowed)) {
            $filename = 'pk_' . $pack_id . '_' . $i . '_' . time() . '.' . $ext;
            $dest     = $upload_dir . $filename;
            $saved    = resizeAndSaveImage($files['tmp_name'][$i], $dest, 1200, 75);
            if ($saved) {
                $foto_path = $saved;
            }
        }
    }

    $foto_esc = mysqli_real_escape_string($koneksi, $foto_path);
    mysqli_query($koneksi, "INSERT INTO packing_checklist (pack_id, item_name, parameter, result, foto_path, repair_note)
                            VALUES ($pack_id, '$item_esc', '$param_esc', '$result', '$foto_esc', '$rnote_esc')");
}

// 3. Tidak ada notif email (operator packing tidak punya email)
header("location:index.php?pk_success=1#packing");
exit();