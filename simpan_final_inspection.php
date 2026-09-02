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
$edit_id   = intval($_POST['edit_id'] ?? 0);

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
$engine_model = mysqli_real_escape_string($koneksi, $tr['engine_model']);
$operator     = mysqli_real_escape_string($koneksi, $_SESSION['nama_lengkap'] ?? '');
$inspect_date = date('Y-m-d');
$noted        = mysqli_real_escape_string($koneksi, $_POST['noted'] ?? '');

$upload_dir = 'uploads/final_inspection/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

// =====================================================================
// MODE REWORK: edit_id diisi -> UPDATE record yang REJECTED, bukan INSERT baru.
// =====================================================================
if ($edit_id > 0) {
    // Keamanan: record yang mau diedit harus benar ada, punya engine_no yang sama,
    // dan status Final_Inspection-nya memang REJECTED (bukan sembarang record).
    $existing = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT id, engine_no FROM final_inspection_data WHERE id = $edit_id"));
    if (!$existing || $existing['engine_no'] !== $engine_no) {
        die("Data rework tidak valid (engine_no tidak cocok).");
    }
    $isRejected = mysqli_fetch_assoc(mysqli_query($koneksi, "
        SELECT id FROM approvals WHERE test_run_id = $edit_id AND stage = 'Final_Inspection' AND status = 'rejected' LIMIT 1
    "));
    if (!$isRejected) {
        die("Data ini bukan data yang di-reject, tidak bisa di-rework.");
    }

    $fi_id = $edit_id;

    // Update header
    mysqli_query($koneksi, "
        UPDATE final_inspection_data
        SET engine_model = '$engine_model', operator_name = '$operator', inspect_date = '$inspect_date', noted = '$noted'
        WHERE id = $fi_id
    ");

    // Ambil path foto grup LAMA dulu (buat dipertahankan kalau operator nggak upload foto baru buat grup itu)
    $old_group_photos = []; // group_no => path lama
    $qold = mysqli_query($koneksi, "SELECT DISTINCT foto_group, foto_path FROM final_inspection_checklist WHERE fi_id = $fi_id AND foto_path != ''");
    if ($qold) while ($o = mysqli_fetch_assoc($qold)) $old_group_photos[(int)$o['foto_group']] = $o['foto_path'];

    // Upload foto grup BARU kalau ada yang di-ganti
    $group_photo_paths = $old_group_photos; // mulai dari yang lama, ditimpa kalau ada upload baru
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
            if ($saved) $group_photo_paths[$group_no] = $saved;
        }
    }

    // Hapus checklist lama, insert ulang yang baru (item boleh berubah kalau checklist master
    // udah direvisi sejak submission lama - tapi biasanya sama, cuma hasil/foto yang berubah)
    mysqli_query($koneksi, "DELETE FROM final_inspection_checklist WHERE fi_id = $fi_id");

    $items        = $_POST['item_name']         ?? [];
    $params       = $_POST['parameter']         ?? [];
    $results      = $_POST['result']            ?? [];
    $item_groups  = $_POST['foto_group_of_item'] ?? [];
    $repair_notes = $_POST['repair_note']        ?? [];

    foreach ($items as $i => $item_name) {
        $item_esc  = mysqli_real_escape_string($koneksi, $item_name);
        $param_esc = mysqli_real_escape_string($koneksi, $params[$i] ?? '');
        $result    = in_array($results[$i] ?? '', ['OK','NG','Rework']) ? $results[$i] : 'OK';
        $group_no  = intval($item_groups[$i] ?? 0);
        $foto_path = $group_photo_paths[$group_no] ?? '';
        $foto_esc  = mysqli_real_escape_string($koneksi, $foto_path);
        $rnote_esc = mysqli_real_escape_string($koneksi, trim($repair_notes[$i] ?? ''));
        mysqli_query($koneksi, "INSERT INTO final_inspection_checklist (fi_id, item_name, parameter, result, foto_path, foto_group, repair_note)
                     VALUES ($fi_id, '$item_esc', '$param_esc', '$result', '$foto_esc', $group_no, '$rnote_esc')");
    }

    // Reset approval: hapus status Rejected yang lama, biar balik ke Pending buat direview ulang
    mysqli_query($koneksi, "DELETE FROM approvals WHERE test_run_id = $fi_id AND stage = 'Final_Inspection'");

    header("location:index.php?fi_rework_success=1#final-inspection");
    exit();
}

// =====================================================================
// MODE NORMAL: submission baru (engine_no belum pernah ada FI, atau FI terakhirnya rejected
// dan operator memilih submit BARU dari nol, bukan lewat tombol rework).
// =====================================================================

// Boleh submit ulang kalau: belum pernah ada FI sama sekali, ATAU FI terakhirnya Rejected.
$existing_fi = mysqli_fetch_assoc(mysqli_query($koneksi, "
    SELECT fi.id FROM final_inspection_data fi
    WHERE fi.engine_no = '$engine_no'
      AND fi.id = (SELECT MAX(id) FROM final_inspection_data WHERE engine_no = '$engine_no')
      AND NOT EXISTS (
          SELECT 1 FROM approvals ap
          WHERE ap.test_run_id = fi.id AND ap.stage = 'Final_Inspection' AND ap.status = 'rejected'
      )
"));
if ($existing_fi) {
    header("location:index.php?fi_error=sudah_ada#final-inspection"); exit();
}

// 1. Insert header
$sql_header = "INSERT INTO final_inspection_data (engine_no, engine_model, operator_name, inspect_date, noted)
               VALUES ('$engine_no', '$engine_model', '$operator', '$inspect_date', '$noted')";

if (!mysqli_query($koneksi, $sql_header)) {
    die("Error header: " . mysqli_error($koneksi));
}
$fi_id = mysqli_insert_id($koneksi);

// 2. Upload foto per grup dulu (satu file per grup, dipakai bareng sama semua item di grup itu)
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
$items        = $_POST['item_name']            ?? [];
$params       = $_POST['parameter']             ?? [];
$results      = $_POST['result']                ?? [];
$item_groups  = $_POST['foto_group_of_item']    ?? [];
$repair_notes = $_POST['repair_note']           ?? [];

foreach ($items as $i => $item_name) {
    $item_esc   = mysqli_real_escape_string($koneksi, $item_name);
    $param_esc  = mysqli_real_escape_string($koneksi, $params[$i] ?? '');
    $result     = in_array($results[$i] ?? '', ['OK','NG','Rework']) ? $results[$i] : 'OK';
    $group_no   = intval($item_groups[$i] ?? 0);
    $foto_path  = $group_photo_paths[$group_no] ?? '';
    $rnote_esc  = mysqli_real_escape_string($koneksi, trim($repair_notes[$i] ?? ''));

    $foto_esc = mysqli_real_escape_string($koneksi, $foto_path);
    $sql_item = "INSERT INTO final_inspection_checklist (fi_id, item_name, parameter, result, foto_path, foto_group, repair_note)
                 VALUES ($fi_id, '$item_esc', '$param_esc', '$result', '$foto_esc', $group_no, '$rnote_esc')";
    mysqli_query($koneksi, $sql_item);
}

// Tidak ada notifikasi email saat submit (operator tidak punya email)

header("location:index.php?fi_success=1#final-inspection");
exit();