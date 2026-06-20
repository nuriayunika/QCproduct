<?php
ob_start();
error_reporting(0);
ini_set("display_errors", 0);
// File: proses_approve.php
session_start();
include 'koneksi.php';

// Notifikasi email via Brevo API (pakai cURL, tidak butuh vendor/autoload)
if (file_exists(__DIR__ . '/kirim_notif_email.php')) {
    include_once 'kirim_notif_email.php';
    define('EMAIL_ENABLED', true);
} else {
    define('EMAIL_ENABLED', false);
}

// Selalu return JSON
header('Content-Type: application/json');

// Proteksi: harus login
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login") {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$role_session = strtolower(trim($_SESSION['role']));
$is_approver  = (
    strpos($role_session, 'foreman')    !== false ||
    strpos($role_session, 'supervisor') !== false ||
    strpos($role_session, 'manager')    !== false
);
if (!$is_approver) {
    echo json_encode(['status' => 'error', 'message' => 'Akses ditolak.']);
    exit();
}

$action       = $_POST['action']  ?? '';
$test_run_id  = intval($_POST['id'] ?? 0);
$stage        = $_POST['stage']   ?? '';
$role_approve = $_POST['role']    ?? '';
$reason       = trim($_POST['reason'] ?? '');
$approved_by  = $_SESSION['nama_lengkap'] ?? $_SESSION['nama_user'] ?? 'Unknown';

// Whitelist
$allowedStages = ['Test_Running', 'Final_Inspection', 'Packing'];
$allowedRoles  = ['Foreman', 'Supervisor', 'Asst_Manager'];

if (!in_array($stage, $allowedStages) || !in_array($role_approve, $allowedRoles) || $test_run_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Data tidak valid. Stage: '.$stage.' Role: '.$role_approve.' ID: '.$test_run_id]);
    exit();
}

// Cek duplikat
$chk = mysqli_query($koneksi,
    "SELECT id FROM approvals 
     WHERE test_run_id = $test_run_id 
       AND stage = '".mysqli_real_escape_string($koneksi, $stage)."' 
       AND role  = '".mysqli_real_escape_string($koneksi, $role_approve)."'
     LIMIT 1"
);
if ($chk && mysqli_num_rows($chk) > 0) {
    echo json_encode(['status' => 'error', 'message' => 'Data ini sudah pernah diproses.']);
    exit();
}

$approved_by_esc = mysqli_real_escape_string($koneksi, $approved_by);
$stage_esc       = mysqli_real_escape_string($koneksi, $stage);
$role_esc        = mysqli_real_escape_string($koneksi, $role_approve);

if ($action === 'approve') {

    $sql = "INSERT INTO approvals (test_run_id, stage, role, approved_by, status, created_at)
            VALUES ($test_run_id, '$stage_esc', '$role_esc', '$approved_by_esc', 'approved', NOW())";

    if (mysqli_query($koneksi, $sql)) {
        // Kirim notifikasi email
        $email_debug = ['enabled' => EMAIL_ENABLED, 'stage' => $stage, 'role' => $role_approve];
        if (EMAIL_ENABLED) {
            $email_result = notifApprovalAction($koneksi, 'approve', $stage, $role_approve, $test_run_id, $approved_by);
            $email_debug['result'] = $email_result;
        }
        echo json_encode(['status' => 'ok', 'email_debug' => $email_debug]);
    } else {
        echo json_encode(['status' => 'error', 'message' => mysqli_error($koneksi)]);
    }

} elseif ($action === 'reject') {

    if (empty($reason)) {
        echo json_encode(['status' => 'error', 'message' => 'Alasan reject tidak boleh kosong.']);
        exit();
    }
    $reason_esc = mysqli_real_escape_string($koneksi, $reason);
    $sql = "INSERT INTO approvals (test_run_id, stage, role, approved_by, status, rejection_note, created_at)
            VALUES ($test_run_id, '$stage_esc', '$role_esc', '$approved_by_esc', 'rejected', '$reason_esc', NOW())";

    if (mysqli_query($koneksi, $sql)) {
        // Kirim notifikasi email ke operator
        if (EMAIL_ENABLED) {
            try {
                notifApprovalAction($koneksi, 'reject', $stage, $role_approve, $test_run_id, $approved_by, $reason);
            } catch (Exception $e) {
                error_log("Email notif error: " . $e->getMessage());
            }
        }
        echo json_encode(['status' => 'ok']);
    } else {
        echo json_encode(['status' => 'error', 'message' => mysqli_error($koneksi)]);
    }

} else {
    echo json_encode(['status' => 'error', 'message' => 'Action tidak dikenali.']);
}