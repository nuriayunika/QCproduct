<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - QC Management System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        /* Override body khusus halaman login */
        body {
            background-color: #f4f6f9;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="text-center mb-4">
        <h4 class="fw-bold text-dark m-0">QC SYSTEM LOGIN</h4>
        <small class="text-muted">Gunakan NIK dan Password Anda</small>
    </div>
    
    <?php if (isset($_GET['pesan']) && $_GET['pesan'] == 'gagal'): ?>
        <div class="alert alert-danger text-center p-2 small fw-semibold" role="alert">
            <i class="fa-solid fa-circle-exclamation me-1"></i> NIK atau Password salah!
        </div>
    <?php endif; ?>

    <form action="proses_login.php" method="POST">
        <div class="mb-3">
            <label class="form-label small fw-bold text-secondary">NIK (Nomor Induk Karyawan)</label>
            <div class="input-group input-group-sm">
                <span class="input-group-text"><i class="fa-solid fa-id-card"></i></span>
                <input type="text" name="nik" class="form-control text-center fw-bold" placeholder="Contoh: 12345" required autocomplete="off">
            </div>
        </div>
        <div class="mb-4">
            <label class="form-label small fw-bold text-secondary">Password</label>
            <div class="input-group input-group-sm">
                <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                <input type="password" name="password" class="form-control text-center" placeholder="••••••••" required>
            </div>
        </div>
        <button type="submit" class="btn btn-primary btn-sm w-100 fw-bold shadow-sm py-2">
            <i class="fa-solid fa-right-to-bracket me-1"></i> MASUK KE DASHBOARD
        </button>
    </form>
</div>

</body>
</html>