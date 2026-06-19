<?php
// 1. Memulai session
session_start();

// 2. Menghubungkan ke database
include 'koneksi.php';

// 3. Menangkap data yang dikirim dari form login.php
$nik      = mysqli_real_escape_string($koneksi, $_POST['nik']);
$password = $_POST['password'];

// 4. Query untuk mengecek apakah NIK dan Password cocok
// Nama kolom disamakan dengan struktur tabel baru kamu ('NIK' dan 'password')
$query = mysqli_query($koneksi, "SELECT * FROM users WHERE NIK='$nik' AND password='$password'");
$cek   = mysqli_num_rows($query);

if($cek > 0) {
    // Jika data ditemukan, ambil data user tersebut
    $data = mysqli_fetch_assoc($query);
    
    // 5. Menyimpan data user ke dalam session global
    $_SESSION['id_user']      = $data['id_user'];
    $_SESSION['nik']          = $data['NIK'];
    $_SESSION['nama_lengkap'] = $data['nama_lengkap'];
    $_SESSION['role']         = $data['role'];
    $_SESSION['status']       = "login";
    
    // 6. Alihkan halaman ke dashboard utama QC
    header("location:index.php"); // sesuaikan dengan nama file utama kamu (bisa .php nanti)
} else {
    // Jika gagal, kembalikan ke halaman login dengan pesan error
    header("location:login.php?pesan=gagal");
}
?>