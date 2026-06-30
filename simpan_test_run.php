<?php
session_start();
include 'koneksi.php';

if (!isset($_SESSION['status']) || $_SESSION['status'] != "login") {
    header("location:login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $operator_name = mysqli_real_escape_string($koneksi, $_SESSION['nama_lengkap']);
    $operator_test = mysqli_real_escape_string($koneksi, $_SESSION['nama_lengkap']);

    // 1. DATA HEADER
    $test_name         = mysqli_real_escape_string($koneksi, $_POST['test_name']);
    $engine_model      = mysqli_real_escape_string($koneksi, $_POST['engine_model']);
    $engine_no         = mysqli_real_escape_string($koneksi, $_POST['engine_no']);
    $test_date         = mysqli_real_escape_string($koneksi, $_POST['test_date']);
    $bench_test        = mysqli_real_escape_string($koneksi, $_POST['bench_test']);
    $lube_oil          = mysqli_real_escape_string($koneksi, $_POST['lube_oil']);
    $fuel_type         = mysqli_real_escape_string($koneksi, $_POST['fuel_type']);
    $fuel_sp_gravity   = !empty($_POST['fuel_sp_gravity'])  ? $_POST['fuel_sp_gravity']  : 'NULL';
    $dry_temp          = !empty($_POST['dry_temp'])          ? $_POST['dry_temp']          : 'NULL';
    $wet_temp          = !empty($_POST['wet_temp'])          ? $_POST['wet_temp']          : 'NULL';
    $atmosphere_press  = !empty($_POST['atmosphere_press'])  ? $_POST['atmosphere_press']  : 'NULL';
    $limiter_actual    = mysqli_real_escape_string($koneksi, $_POST['limiter_actual']);
    $limiter_after_set = mysqli_real_escape_string($koneksi, $_POST['limiter_after_set']);
    $hi_idle_actual    = !empty($_POST['hi_idle_actual'])    ? $_POST['hi_idle_actual']    : 'NULL';
    $eng_speed_max     = !empty($_POST['eng_speed_max'])     ? $_POST['eng_speed_max']     : 'NULL';
    $eng_speed_min     = !empty($_POST['eng_speed_min'])     ? $_POST['eng_speed_min']     : 'NULL';
    $cont_power        = mysqli_real_escape_string($koneksi, $_POST['cont_power']   ?? '');
    $max_power         = mysqli_real_escape_string($koneksi, $_POST['max_power']    ?? '');
    $hi_idle_std       = mysqli_real_escape_string($koneksi, $_POST['hi_idle_std']  ?? '');

    // 2. ROW 1
    $r1_actual_nm     = !empty($_POST['r1_actual_nm'])     ? $_POST['r1_actual_nm']     : 'NULL';
    $r1_corrected_kw  = !empty($_POST['r1_corrected_kw'])  ? $_POST['r1_corrected_kw']  : 'NULL';
    $r1_torque_nm     = !empty($_POST['r1_torque_nm'])     ? $_POST['r1_torque_nm']     : 'NULL';
    $r1_load_kgm      = !empty($_POST['r1_load_kgm'])      ? $_POST['r1_load_kgm']      : 'NULL';
    $r1_fuel_cc_30sec = !empty($_POST['r1_fuel_cc_30sec']) ? $_POST['r1_fuel_cc_30sec'] : 'NULL';
    $r1_fuel_mm3_st   = !empty($_POST['r1_fuel_mm3_st'])   ? $_POST['r1_fuel_mm3_st']   : 'NULL';
    $r1_fuel_g_kwh    = !empty($_POST['r1_fuel_g_kwh'])    ? $_POST['r1_fuel_g_kwh']    : 'NULL';
    $r1_sd_bsu        = !empty($_POST['r1_sd_bsu'])        ? $_POST['r1_sd_bsu']        : 'NULL';
    $r1_temp_exhaust  = !empty($_POST['r1_temp_exhaust'])  ? $_POST['r1_temp_exhaust']  : 'NULL';
    $r1_temp_oil      = !empty($_POST['r1_temp_oil'])      ? $_POST['r1_temp_oil']      : 'NULL';
    $r1_lo_press      = !empty($_POST['r1_lo_press'])      ? $_POST['r1_lo_press']      : 'NULL';
    $r1_intake_press  = !empty($_POST['r1_intake_press'])  ? $_POST['r1_intake_press']  : 'NULL';
    $r1_exhaust_press = !empty($_POST['r1_exhaust_press']) ? $_POST['r1_exhaust_press'] : 'NULL';
    $r1_nox           = !empty($_POST['r1_nox'])           ? $_POST['r1_nox']           : 'NULL';
    $r1_co            = !empty($_POST['r1_co'])            ? $_POST['r1_co']            : 'NULL';
    $r1_co2           = !empty($_POST['r1_co2'])           ? $_POST['r1_co2']           : 'NULL';
    $r1_o2            = !empty($_POST['r1_o2'])            ? $_POST['r1_o2']            : 'NULL';

    // 3. ROW 2
    $r2_actual_nm     = !empty($_POST['r2_actual_nm'])     ? $_POST['r2_actual_nm']     : 'NULL';
    $r2_corrected_kw  = !empty($_POST['r2_corrected_kw'])  ? $_POST['r2_corrected_kw']  : 'NULL';
    $r2_temp_exhaust  = !empty($_POST['r2_temp_exhaust'])  ? $_POST['r2_temp_exhaust']  : 'NULL';
    $r2_lo_press      = !empty($_POST['r2_lo_press'])      ? $_POST['r2_lo_press']      : 'NULL';
    $r2_intake_press  = !empty($_POST['r2_intake_press'])  ? $_POST['r2_intake_press']  : 'NULL';
    $r2_exhaust_press = !empty($_POST['r2_exhaust_press']) ? $_POST['r2_exhaust_press'] : 'NULL';
    $r2_nox           = !empty($_POST['r2_nox'])           ? $_POST['r2_nox']           : 'NULL';
    $r2_co            = !empty($_POST['r2_co'])            ? $_POST['r2_co']            : 'NULL';
    $r2_co2           = !empty($_POST['r2_co2'])           ? $_POST['r2_co2']           : 'NULL';
    $r2_o2            = !empty($_POST['r2_o2'])            ? $_POST['r2_o2']            : 'NULL';
    $r2_correct_co    = !empty($_POST['r2_correct_co'])    ? $_POST['r2_correct_co']    : 'NULL';

    // 4. ROW 3
    $r3_torque_nm                 = !empty($_POST['r3_torque_nm'])                 ? $_POST['r3_torque_nm']                 : 'NULL';
    $r3_coolant_temp              = !empty($_POST['r3_coolant_temp'])              ? $_POST['r3_coolant_temp']              : 'NULL';
    $r3_current_glow              = !empty($_POST['r3_current_glow'])              ? $_POST['r3_current_glow']              : 'NULL';
    $r3_current_wire              = !empty($_POST['r3_current_wire'])              ? $_POST['r3_current_wire']              : 'NULL';
    $r3_torque_switch_lo          = !empty($_POST['r3_torque_switch_lo'])          ? $_POST['r3_torque_switch_lo']          : 'NULL';
    $r3_torque_pipe_air           = !empty($_POST['r3_torque_pipe_air'])           ? $_POST['r3_torque_pipe_air']           : 'NULL';
    $r3_torque_bolt_cw            = !empty($_POST['r3_torque_bolt_cw'])            ? $_POST['r3_torque_bolt_cw']            : 'NULL';
    $r3_torque_injection_injector = !empty($_POST['r3_torque_injection_injector']) ? $_POST['r3_torque_injection_injector'] : 0;
    $r3_torque_injection_fop      = !empty($_POST['r3_torque_injection_fop'])      ? $_POST['r3_torque_injection_fop']      : 0;
    $r3_torque_nut_joint          = !empty($_POST['r3_torque_nut_joint'])          ? $_POST['r3_torque_nut_joint']          : 'NULL';

    // 5. BLOK BAWAH
    $correction_alpha      = !empty($_POST['correction_alpha'])      ? $_POST['correction_alpha']      : 'NULL';
    $correction_beta       = !empty($_POST['correction_beta'])       ? $_POST['correction_beta']       : 'NULL';
    $blow_by               = !empty($_POST['blow_by'])               ? $_POST['blow_by']               : 'NULL';
    $min_eng_speed_lo      = !empty($_POST['min_eng_speed_lo'])      ? $_POST['min_eng_speed_lo']      : 'NULL';
    $pulley_distance       = !empty($_POST['pulley_distance'])       ? $_POST['pulley_distance']       : 'NULL';
    $fic_standard          = mysqli_real_escape_string($koneksi, $_POST['fic_standard']);
    $fic_actual_left       = !empty($_POST['fic_actual_left'])       ? $_POST['fic_actual_left']       : 'NULL';
    $fic_actual_right      = !empty($_POST['fic_actual_right'])      ? $_POST['fic_actual_right']      : 'NULL';
    $fic_before_test_left  = !empty($_POST['fic_before_test_left'])  ? $_POST['fic_before_test_left']  : 'NULL';
    $fic_before_test_right = !empty($_POST['fic_before_test_right']) ? $_POST['fic_before_test_right'] : 'NULL';
    $fic_after_test_left   = !empty($_POST['fic_after_test_left'])   ? $_POST['fic_after_test_left']   : 'NULL';
    $fic_after_test_right  = !empty($_POST['fic_after_test_right'])  ? $_POST['fic_after_test_right']  : 'NULL';
    $belt_tension_left     = !empty($_POST['belt_tension_left'])     ? $_POST['belt_tension_left']     : 'NULL';
    $belt_tension_right    = !empty($_POST['belt_tension_right'])    ? $_POST['belt_tension_right']    : 'NULL';
    $noted                 = isset($_POST['noted']) ? mysqli_real_escape_string($koneksi, $_POST['noted']) : '';

    // 6. UPLOAD FOTO ENGINE (3 foto)
    $upload_dir = 'uploads/test_running/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];

    $foto_engine_1 = 'NULL';
    $foto_engine_2 = 'NULL';
    $foto_engine_3 = 'NULL';

    for ($fn = 1; $fn <= 3; $fn++) {
        $field = 'foto_engine_' . $fn;
        if (!empty($_FILES[$field]['name']) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
            if (in_array($ext, $allowed)) {
                $filename = 'tr_' . date('Ymd_His') . '_' . $fn . '_' . rand(100,999) . '.' . $ext;
                $dest     = $upload_dir . $filename;
                if (move_uploaded_file($_FILES[$field]['tmp_name'], $dest)) {
                    ${'foto_engine_' . $fn} = "'" . mysqli_real_escape_string($koneksi, $dest) . "'";
                }
            }
        }
    }

    // 7. CEK DUPLIKAT ENGINE NO DI TEST RUNNING
    $cek_dup = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT id FROM result_test_run WHERE engine_no='$engine_no' LIMIT 1"));
    if ($cek_dup) {
        header("location:index.php?tr_error=duplikat&engine_no=" . urlencode($engine_no));
        exit();
    }

    // 8. INSERT DATA UTAMA
    $query_utama = "INSERT INTO result_test_run (
        test_name, engine_model, engine_no, test_date, bench_test, operator_name, lube_oil, fuel_type,
        fuel_sp_gravity, dry_temp, wet_temp, atmosphere_press, limiter_actual, limiter_after_set,
        cont_power, max_power, hi_idle_std, hi_idle_actual, eng_speed_max, eng_speed_min,
        r1_actual_nm, r1_corrected_kw, r1_torque_nm, r1_load_kgm, r1_fuel_cc_30sec, r1_fuel_mm3_st,
        r1_fuel_g_kwh, r1_sd_bsu, r1_temp_exhaust, r1_temp_oil, r1_lo_press, r1_intake_press,
        r1_exhaust_press, r1_nox, r1_co, r1_co2, r1_o2,
        r2_actual_nm, r2_corrected_kw, r2_temp_exhaust, r2_lo_press, r2_intake_press, r2_exhaust_press,
        r2_nox, r2_co, r2_co2, r2_o2, r2_correct_co,
        r3_torque_nm, r3_coolant_temp, r3_current_glow, r3_current_wire, r3_torque_switch_lo,
        r3_torque_pipe_air, r3_torque_bolt_cw, r3_torque_injection_injector, r3_torque_injection_fop,
        r3_torque_nut_joint, correction_alpha, correction_beta, blow_by, min_eng_speed_lo,
        pulley_distance, fic_standard, fic_actual_left, fic_actual_right, fic_before_test_left,
        fic_before_test_right, fic_after_test_left, fic_after_test_right, belt_tension_left,
        belt_tension_right, noted, operator_test, foto_engine_1, foto_engine_2, foto_engine_3
    ) VALUES (
        '$test_name', '$engine_model', '$engine_no', '$test_date', '$bench_test', '$operator_name',
        '$lube_oil', '$fuel_type', $fuel_sp_gravity, $dry_temp, $wet_temp, $atmosphere_press,
        '$limiter_actual', '$limiter_after_set', '$cont_power', '$max_power', '$hi_idle_std', $hi_idle_actual, $eng_speed_max, $eng_speed_min,
        $r1_actual_nm, $r1_corrected_kw, $r1_torque_nm, $r1_load_kgm, $r1_fuel_cc_30sec,
        $r1_fuel_mm3_st, $r1_fuel_g_kwh, $r1_sd_bsu, $r1_temp_exhaust, $r1_temp_oil, $r1_lo_press,
        $r1_intake_press, $r1_exhaust_press, $r1_nox, $r1_co, $r1_co2, $r1_o2,
        $r2_actual_nm, $r2_corrected_kw, $r2_temp_exhaust, $r2_lo_press, $r2_intake_press,
        $r2_exhaust_press, $r2_nox, $r2_co, $r2_co2, $r2_o2, $r2_correct_co,
        $r3_torque_nm, $r3_coolant_temp, $r3_current_glow, $r3_current_wire, $r3_torque_switch_lo,
        $r3_torque_pipe_air, $r3_torque_bolt_cw, $r3_torque_injection_injector, $r3_torque_injection_fop,
        $r3_torque_nut_joint, $correction_alpha, $correction_beta, $blow_by, $min_eng_speed_lo,
        $pulley_distance, '$fic_standard', $fic_actual_left, $fic_actual_right, $fic_before_test_left,
        $fic_before_test_right, $fic_after_test_left, $fic_after_test_right, $belt_tension_left,
        $belt_tension_right, '$noted', '$operator_test', $foto_engine_1, $foto_engine_2, $foto_engine_3
    )";

    if (mysqli_query($koneksi, $query_utama)) {
        $id_test_run = mysqli_insert_id($koneksi);

        // 8. INSERT CHECKLIST VISUAL
        if (isset($_POST['chk_item'])) {
            $chk_items = $_POST['chk_item'];
            $chk_types = $_POST['chk_type'];
            $chk_vals  = $_POST['chk_val'];
            for ($i = 0; $i < count($chk_items); $i++) {
                $item = mysqli_real_escape_string($koneksi, $chk_items[$i]);
                $type = mysqli_real_escape_string($koneksi, $chk_types[$i]);
                $val  = mysqli_real_escape_string($koneksi, $chk_vals[$i]);
                mysqli_query($koneksi, "INSERT INTO checklist
                    (id_test_run, engine_no, kategori, item_name, jawaban)
                    VALUES ('$id_test_run', '$engine_no', '$type', '$item', '$val')");
            }
        }

        // 9. REDIRECT dengan pesan sukses
        header("location:index.php?tr_success=1");
        exit();

    } else {
        echo "<h3>Gagal Simpan ke Database!</h3>";
        echo "<p>Error: " . mysqli_error($koneksi) . "</p>";
        echo "<p>Query: " . $query_utama . "</p>";
        die();
    }

} else {
    header("location:index.php");
    exit();
}
?>