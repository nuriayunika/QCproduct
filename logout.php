<?php 
// 1. Jalankan session untuk mendeteksi siapa yang sedang aktif
session_start();

// 2. Hapus semua data session (menghapus status login, nama, dan role)
session_destroy();

// 3. Alihkan halaman layar kembali ke menu login utama
header("location:login.php");
exit();
?>